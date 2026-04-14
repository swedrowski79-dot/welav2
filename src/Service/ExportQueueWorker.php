<?php

declare(strict_types=1);

final class ExportQueueWorker
{
    private string $queueTable;
    private string $stateTable;
    private string $entityType;
    private string $stateHashField;
    private string $offlineHash;
    private int $workerBatchSize;

    public function __construct(
        private PDO $stageDb,
        array $deltaConfig,
        private ?SyncMonitor $monitor = null,
        private ?int $runId = null
    ) {
        $config = $deltaConfig['product_export_queue'] ?? [];

        $this->queueTable = (string) ($config['queue_table'] ?? 'export_queue');
        $this->stateTable = (string) ($config['state_table'] ?? 'product_export_state');
        $this->entityType = (string) ($config['entity_type'] ?? 'product');
        $this->stateHashField = (string) ($config['state_hash_field'] ?? 'last_exported_hash');
        $this->offlineHash = hash('sha256', (string) ($config['offline_hash_value'] ?? 'offline'));
        $this->workerBatchSize = max(1, (int) ($config['worker_batch_size'] ?? 100));
    }

    public function run(?int $limit = null): array
    {
        $entries = $this->claimPendingEntries($limit ?? $this->workerBatchSize);
        $stats = [
            'claimed' => count($entries),
            'processed' => 0,
            'done' => 0,
            'error' => 0,
        ];

        foreach ($entries as $entry) {
            $queueId = (int) ($entry['id'] ?? 0);
            if ($queueId <= 0) {
                continue;
            }

            $stats['processed']++;

            try {
                $confirmedHash = $this->confirmedHash($entry);
                $entityId = (int) ($entry['entity_id'] ?? 0);
                $claimToken = (string) ($entry['claim_token'] ?? '');

                if ($entityId <= 0) {
                    throw new RuntimeException('Queue-Eintrag enthaelt keine gueltige Entity-ID.');
                }

                if ($claimToken === '') {
                    throw new RuntimeException('Queue-Eintrag wurde nicht korrekt geclaimt.');
                }

                $this->stageDb->beginTransaction();

                $this->markDone($queueId, $claimToken);
                $this->updateConfirmedState($entityId, $confirmedHash);

                $this->stageDb->commit();
                $stats['done']++;
            } catch (Throwable $exception) {
                if ($this->stageDb->inTransaction()) {
                    $this->stageDb->rollBack();
                }

                $this->markError($queueId, (string) ($entry['claim_token'] ?? ''), $exception->getMessage());
                $stats['error']++;
            }
        }

        if ($this->monitor !== null) {
            $this->monitor->log($this->runId, 'info', 'Export Queue verarbeitet.', $stats);
        }

        return $stats;
    }

    private function claimPendingEntries(int $limit): array
    {
        $claimToken = bin2hex(random_bytes(16));

        $this->stageDb->beginTransaction();

        try {
            $stmt = $this->stageDb->prepare(
                "SELECT id
                 FROM `{$this->queueTable}`
                 WHERE status = :status AND entity_type = :entity_type
                 ORDER BY created_at ASC, id ASC
                 LIMIT :limit
                 FOR UPDATE"
            );
            $stmt->bindValue(':status', 'pending');
            $stmt->bindValue(':entity_type', $this->entityType);
            $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
            $stmt->execute();

            $queueIds = array_map('intval', $stmt->fetchAll(PDO::FETCH_COLUMN));

            if ($queueIds === []) {
                $this->stageDb->commit();

                return [];
            }

            $placeholders = [];
            $params = [
                ':claim_token' => $claimToken,
                ':processing_status' => 'processing',
            ];

            foreach ($queueIds as $index => $queueId) {
                $placeholder = ':id_' . $index;
                $placeholders[] = $placeholder;
                $params[$placeholder] = $queueId;
            }

            $claimSql = sprintf(
                "UPDATE `%s`
                 SET status = :processing_status,
                     claim_token = :claim_token,
                     claimed_at = NOW()
                 WHERE id IN (%s)",
                $this->queueTable,
                implode(', ', $placeholders)
            );

            $claimStmt = $this->stageDb->prepare($claimSql);
            $claimStmt->execute($params);
            $this->stageDb->commit();
        } catch (Throwable $exception) {
            if ($this->stageDb->inTransaction()) {
                $this->stageDb->rollBack();
            }

            throw $exception;
        }

        $stmt = $this->stageDb->prepare(
            "SELECT id, entity_type, entity_id, action, payload, status, claim_token, claimed_at, created_at
             FROM `{$this->queueTable}`
             WHERE claim_token = :claim_token
             ORDER BY claimed_at ASC, id ASC"
        );
        $stmt->execute([
            ':claim_token' => $claimToken,
        ]);

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    private function confirmedHash(array $entry): string
    {
        $payload = json_decode((string) ($entry['payload'] ?? ''), true);

        if (!is_array($payload)) {
            throw new RuntimeException('Queue-Payload ist kein gueltiges JSON.');
        }

        if (($entry['action'] ?? '') === 'update' && (($payload['online'] ?? null) === 0 || ($payload['online'] ?? null) === '0')) {
            return $this->offlineHash;
        }

        $hash = $payload['hash'] ?? null;

        if (!is_string($hash) || trim($hash) === '') {
            throw new RuntimeException('Queue-Payload enthaelt keinen bestaetigbaren Hash.');
        }

        return $hash;
    }

    private function markDone(int $queueId, string $claimToken): void
    {
        $stmt = $this->stageDb->prepare(
            "UPDATE `{$this->queueTable}`
             SET status = :status,
                 claim_token = NULL
             WHERE id = :id AND claim_token = :claim_token"
        );
        $stmt->execute([
            ':status' => 'done',
            ':id' => $queueId,
            ':claim_token' => $claimToken,
        ]);

        if ($stmt->rowCount() !== 1) {
            throw new RuntimeException('Queue-Eintrag konnte nicht als verarbeitet bestaetigt werden.');
        }
    }

    private function markError(int $queueId, string $claimToken, string $message): void
    {
        if ($queueId > 0 && $claimToken !== '') {
            $stmt = $this->stageDb->prepare(
                "UPDATE `{$this->queueTable}`
                 SET status = :status,
                     claim_token = NULL
                 WHERE id = :id AND claim_token = :claim_token"
            );
            $stmt->execute([
                ':status' => 'error',
                ':id' => $queueId,
                ':claim_token' => $claimToken,
            ]);
        }

        if ($this->monitor !== null) {
            $this->monitor->log($this->runId, 'error', 'Export Queue Eintrag fehlgeschlagen.', [
                'queue_id' => $queueId,
                'exception' => $message,
            ]);
            $this->monitor->error($this->runId, 'Export Queue Eintrag fehlgeschlagen.', [
                'source' => 'export_queue_worker',
                'record_identifier' => (string) $queueId,
                'exception' => $message,
            ]);
        }
    }

    private function updateConfirmedState(int $entityId, string $hash): void
    {
        $stmt = $this->stageDb->prepare(
            "INSERT INTO `{$this->stateTable}` (`product_id`, `{$this->stateHashField}`, `last_seen_at`)
             VALUES (:product_id, :hash, NOW())
             ON DUPLICATE KEY UPDATE
                `{$this->stateHashField}` = VALUES(`{$this->stateHashField}`),
                `last_seen_at` = VALUES(`last_seen_at`)"
        );
        $stmt->execute([
            ':product_id' => $entityId,
            ':hash' => $hash,
        ]);
    }
}
