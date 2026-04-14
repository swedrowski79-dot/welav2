<?php

declare(strict_types=1);

final class ExportQueueWorker
{
    private string $queueTable;
    private string $stateTable;
    private string $entityType;
    private string $stateHashField;
    private string $offlineHash;

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
    }

    public function run(int $limit = 100): array
    {
        $entries = $this->pendingEntries($limit);
        $stats = [
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

                if ($entityId <= 0) {
                    throw new RuntimeException('Queue-Eintrag enthaelt keine gueltige Entity-ID.');
                }

                $this->stageDb->beginTransaction();

                $this->markDone($queueId);
                $this->updateConfirmedState($entityId, $confirmedHash);

                $this->stageDb->commit();
                $stats['done']++;
            } catch (Throwable $exception) {
                if ($this->stageDb->inTransaction()) {
                    $this->stageDb->rollBack();
                }

                $this->markError($queueId, $exception->getMessage());
                $stats['error']++;
            }
        }

        if ($this->monitor !== null) {
            $this->monitor->log($this->runId, 'info', 'Export Queue verarbeitet.', $stats);
        }

        return $stats;
    }

    private function pendingEntries(int $limit): array
    {
        $stmt = $this->stageDb->prepare(
            "SELECT id, entity_type, entity_id, action, payload, status, created_at
             FROM `{$this->queueTable}`
             WHERE status = :status AND entity_type = :entity_type
             ORDER BY created_at ASC, id ASC
             LIMIT :limit"
        );
        $stmt->bindValue(':status', 'pending');
        $stmt->bindValue(':entity_type', $this->entityType);
        $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
        $stmt->execute();

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

    private function markDone(int $queueId): void
    {
        $stmt = $this->stageDb->prepare(
            "UPDATE `{$this->queueTable}` SET status = :status WHERE id = :id"
        );
        $stmt->execute([
            ':status' => 'done',
            ':id' => $queueId,
        ]);
    }

    private function markError(int $queueId, string $message): void
    {
        if ($queueId > 0) {
            $stmt = $this->stageDb->prepare(
                "UPDATE `{$this->queueTable}` SET status = :status WHERE id = :id"
            );
            $stmt->execute([
                ':status' => 'error',
                ':id' => $queueId,
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
