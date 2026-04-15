<?php

declare(strict_types=1);

final class PermanentExportQueueException extends RuntimeException
{
}

final class ExportQueueWorker
{
    private array $configKeys;
    private string $configKey;
    private string $queueTable;
    private string $stateTable;
    private string $entityType;
    private string $stateIdentityField;
    private string $stateHashField;
    private string $removedHash;
    private int $workerBatchSize;
    private int $workerMaxAttempts;
    private int $workerRetryDelaySeconds;

    public function __construct(
        private PDO $stageDb,
        private array $deltaConfig,
        private ?SyncMonitor $monitor = null,
        private ?int $runId = null
    ) {
        $configKeys = $this->deltaConfig['export_queue_entities'] ?? ['product_export_queue'];
        $this->configKeys = array_values(array_filter(
            is_array($configKeys) ? $configKeys : [],
            static fn (mixed $key): bool => is_string($key) && $key !== ''
        ));

        if ($this->configKeys === []) {
            $this->configKeys = ['product_export_queue'];
        }
    }

    public function run(?int $limit = null): array
    {
        $aggregate = [
            'claimed' => 0,
            'processed' => 0,
            'done' => 0,
            'retried' => 0,
            'permanent_error' => 0,
            'error' => 0,
            'entities' => [],
        ];

        foreach ($this->configKeys as $configKey) {
            $this->applyConfig($configKey);
            $entityStats = $this->runCurrentEntity($limit);
            $aggregate['entities'][$this->entityType] = $entityStats;

            foreach (['claimed', 'processed', 'done', 'retried', 'permanent_error', 'error'] as $field) {
                $aggregate[$field] += (int) ($entityStats[$field] ?? 0);
            }
        }

        if ($this->monitor !== null) {
            $this->monitor->log($this->runId, 'info', 'Export Queue verarbeitet.', $aggregate);
        }

        return $aggregate;
    }

    private function applyConfig(string $configKey): void
    {
        $config = $this->deltaConfig[$configKey] ?? null;
        if (!is_array($config)) {
            throw new RuntimeException("Delta config '{$configKey}' not found for export queue worker.");
        }

        $this->configKey = $configKey;
        $this->queueTable = (string) ($config['queue_table'] ?? 'export_queue');
        $this->stateTable = (string) ($config['state_table'] ?? 'product_export_state');
        $this->entityType = (string) ($config['entity_type'] ?? 'product');
        $this->stateIdentityField = (string) ($config['state_identity_field'] ?? 'product_id');
        $this->stateHashField = (string) ($config['state_hash_field'] ?? 'last_exported_hash');
        $this->removedHash = hash('sha256', (string) ($config['removed_hash_value'] ?? $config['offline_hash_value'] ?? 'offline'));
        $this->workerBatchSize = max(1, (int) ($config['worker_batch_size'] ?? 100));
        $this->workerMaxAttempts = max(1, (int) ($config['worker_max_attempts'] ?? 3));
        $this->workerRetryDelaySeconds = max(0, (int) ($config['worker_retry_delay_seconds'] ?? 300));
    }

    private function runCurrentEntity(?int $limit = null): array
    {
        $entries = $this->claimPendingEntries($limit ?? $this->workerBatchSize);
        $stats = [
            'claimed' => count($entries),
            'processed' => 0,
            'done' => 0,
            'retried' => 0,
            'permanent_error' => 0,
            'error' => 0,
            'entity_type' => $this->entityType,
            'config_key' => $this->configKey,
        ];

        foreach ($entries as $entry) {
            $queueId = (int) ($entry['id'] ?? 0);
            if ($queueId <= 0) {
                continue;
            }

            $stats['processed']++;

            try {
                $this->processEntry($entry);
                $stats['done']++;
            } catch (Throwable $exception) {
                if ($this->stageDb->inTransaction()) {
                    $this->stageDb->rollBack();
                }

                $result = $this->handleFailureSafely($entry, $exception);
                $stats[$result]++;
                $stats['error']++;
            }
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
                 WHERE status = :status
                   AND entity_type = :entity_type
                   AND claim_token IS NULL
                   AND (available_at IS NULL OR available_at <= NOW())
                 ORDER BY available_at ASC, created_at ASC, id ASC
                 LIMIT :limit
                 FOR UPDATE"
            );
            $stmt->bindValue(':status', 'pending');
            $stmt->bindValue(':entity_type', $this->entityType);
            $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
            $stmt->execute();

            $queueIds = array_map('intval', $stmt->fetchAll(PDO::FETCH_COLUMN));
            $selectedCount = count($queueIds);

            if ($this->monitor !== null) {
                $this->monitor->log($this->runId, 'info', 'Export Queue Claim-Auswahl ermittelt.', [
                    'selected_count' => $selectedCount,
                    'limit' => $limit,
                    'entity_type' => $this->entityType,
                ]);
            }

            if ($queueIds === []) {
                $this->stageDb->commit();

                return [];
            }

            $placeholders = [];
            $params = [
                ':claim_token' => $claimToken,
                ':processing_status' => 'processing',
                ':status' => 'pending',
                ':entity_type' => $this->entityType,
            ];

            foreach ($queueIds as $index => $queueId) {
                $placeholder = ':id_' . $index;
                $placeholders[] = $placeholder;
                $params[$placeholder] = $queueId;
            }

            $claimSql = sprintf(
                "UPDATE `%s`
                 SET status = :processing_status,
                     attempt_count = attempt_count + 1,
                     claim_token = :claim_token,
                     claimed_at = NOW()
                 WHERE id IN (%s)
                   AND status = :status
                   AND entity_type = :entity_type
                   AND claim_token IS NULL
                   AND (available_at IS NULL OR available_at <= NOW())",
                $this->queueTable,
                implode(', ', $placeholders)
            );

            $claimStmt = $this->stageDb->prepare($claimSql);
            $claimStmt->execute($params);
            $updatedCount = $claimStmt->rowCount();

            if ($this->monitor !== null) {
                $this->monitor->log($this->runId, 'info', 'Export Queue Claim-Update ausgefuehrt.', [
                    'selected_count' => $selectedCount,
                    'updated_count' => $updatedCount,
                    'claim_token' => $claimToken,
                    'entity_type' => $this->entityType,
                ]);
            }

            if ($updatedCount < 1) {
                throw new RuntimeException('Es konnten keine pending Queue-Eintraege geclaimt werden.');
            }

            if ($updatedCount !== $selectedCount) {
                throw new RuntimeException('Nicht alle ausgewaehlten Queue-Eintraege konnten geclaimt werden.');
            }

            $this->stageDb->commit();
        } catch (Throwable $exception) {
            if ($this->stageDb->inTransaction()) {
                $this->stageDb->rollBack();
            }

            throw $exception;
        }

        $stmt = $this->stageDb->prepare(
            "SELECT id, entity_type, entity_id, action, payload, status, attempt_count, available_at, claim_token, claimed_at, processed_at, last_error, created_at
             FROM `{$this->queueTable}`
             WHERE claim_token = :claim_token
             ORDER BY claimed_at ASC, id ASC"
        );
        $stmt->execute([
            ':claim_token' => $claimToken,
        ]);

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    private function processEntry(array $entry): void
    {
        $queueId = (int) ($entry['id'] ?? 0);
        $entityId = trim((string) ($entry['entity_id'] ?? ''));
        $claimToken = (string) ($entry['claim_token'] ?? '');
        $confirmedHash = $this->confirmedHash($entry);

        if ($entityId === '') {
            throw new PermanentExportQueueException('Queue-Eintrag enthaelt keine gueltige Entity-ID.');
        }

        if ($claimToken === '') {
            throw new RuntimeException('Queue-Eintrag wurde nicht korrekt geclaimt.');
        }

        $this->stageDb->beginTransaction();

        try {
            $this->updateConfirmedState($entityId, $confirmedHash);
            $this->markDone($queueId, $claimToken);
            $this->stageDb->commit();
        } catch (Throwable $exception) {
            if ($this->stageDb->inTransaction()) {
                $this->stageDb->rollBack();
            }

            throw $exception;
        }

        if ($this->monitor !== null) {
            $this->monitor->log($this->runId, 'info', 'Export Queue Eintrag verarbeitet.', [
                'queue_id' => $queueId,
                'entity_id' => $entityId,
                'entity_type' => $this->entityType,
                'final_status' => 'done',
            ]);
        }
    }

    private function confirmedHash(array $entry): string
    {
        $payload = json_decode((string) ($entry['payload'] ?? ''), true);

        if (!is_array($payload)) {
            throw new PermanentExportQueueException('Queue-Payload ist kein gueltiges JSON.');
        }

        $hash = $payload['hash'] ?? null;
        if (is_string($hash) && trim($hash) !== '') {
            return $hash;
        }

        if (($entry['action'] ?? '') === 'update' && (($payload['online'] ?? null) === 0 || ($payload['online'] ?? null) === '0')) {
            return $this->removedHash;
        }

        throw new PermanentExportQueueException('Queue-Payload enthaelt keinen bestaetigbaren Hash.');
    }

    private function markDone(int $queueId, string $claimToken): void
    {
        $stmt = $this->stageDb->prepare(
            "UPDATE `{$this->queueTable}`
             SET status = :status,
                 processed_at = NOW(),
                 last_error = NULL
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

    private function handleFailure(array $entry, Throwable $exception): string
    {
        $queueId = (int) ($entry['id'] ?? 0);
        $claimToken = (string) ($entry['claim_token'] ?? '');
        $attemptCount = (int) ($entry['attempt_count'] ?? 0);
        $retryable = !$exception instanceof PermanentExportQueueException;
        $hasAttemptsLeft = $attemptCount < $this->workerMaxAttempts;
        $context = [
            'queue_id' => $queueId,
            'entity_type' => $this->entityType,
            'attempt_count' => $attemptCount,
            'max_attempts' => $this->workerMaxAttempts,
            'retryable' => $retryable,
            'exception' => $exception->getMessage(),
        ];

        if ($queueId > 0 && $claimToken !== '') {
            if ($retryable && $hasAttemptsLeft) {
                $availableAt = (new DateTimeImmutable('now', new DateTimeZone('UTC')))
                    ->modify('+' . $this->workerRetryDelaySeconds . ' seconds')
                    ->format('Y-m-d H:i:s');

                $stmt = $this->stageDb->prepare(
                    "UPDATE `{$this->queueTable}`
                     SET status = :status,
                         claim_token = NULL,
                         available_at = :available_at,
                         last_error = :last_error
                     WHERE id = :id AND claim_token = :claim_token"
                );
                $stmt->execute([
                    ':status' => 'pending',
                    ':available_at' => $availableAt,
                    ':last_error' => $exception->getMessage(),
                    ':id' => $queueId,
                    ':claim_token' => $claimToken,
                ]);

                $context['next_retry_at'] = $availableAt;
                $this->logFailure('warning', 'Export Queue Eintrag fuer Retry vorgemerkt.', $context);

                return 'retried';
            }

            $stmt = $this->stageDb->prepare(
                "UPDATE `{$this->queueTable}`
                 SET status = :status,
                     processed_at = NOW(),
                     last_error = :last_error
                 WHERE id = :id AND claim_token = :claim_token"
            );
            $stmt->execute([
                ':status' => 'error',
                ':last_error' => $exception->getMessage(),
                ':id' => $queueId,
                ':claim_token' => $claimToken,
            ]);
        }

        $context['final_status'] = 'error';

        if ($retryable && !$hasAttemptsLeft) {
            $context['reason'] = 'max_attempts_exhausted';
        } else {
            $context['reason'] = 'permanent_error';
        }

        $this->logFailure('error', 'Export Queue Eintrag fehlgeschlagen.', $context);

        return 'permanent_error';
    }

    private function handleFailureSafely(array $entry, Throwable $exception): string
    {
        try {
            return $this->handleFailure($entry, $exception);
        } catch (Throwable $failureException) {
            $context = [
                'queue_id' => (int) ($entry['id'] ?? 0),
                'entity_type' => $this->entityType,
                'original_exception' => $exception->getMessage(),
                'failure_handler_exception' => $failureException->getMessage(),
            ];

            $this->logFailure('error', 'Export Queue Fehlerbehandlung fehlgeschlagen.', $context);

            return 'permanent_error';
        }
    }

    private function logFailure(string $level, string $message, array $context): void
    {
        if ($this->monitor === null) {
            return;
        }

        $this->monitor->log($this->runId, $level, $message, $context);

        if ($level === 'error') {
            $this->monitor->error($this->runId, $message, [
                'source' => 'export_queue_worker',
                'record_identifier' => isset($context['queue_id']) ? (string) $context['queue_id'] : null,
                ...$context,
            ]);
        }
    }

    private function updateConfirmedState(string $entityId, string $hash): void
    {
        $stmt = $this->stageDb->prepare(
            "INSERT INTO `{$this->stateTable}` (`{$this->stateIdentityField}`, `{$this->stateHashField}`, `last_seen_at`)
             VALUES (:state_id, :hash, NOW())
             ON DUPLICATE KEY UPDATE
                `{$this->stateHashField}` = VALUES(`{$this->stateHashField}`),
                `last_seen_at` = VALUES(`last_seen_at`)"
        );
        $stmt->execute([
            ':state_id' => $entityId,
            ':hash' => $hash,
        ]);
    }
}
