<?php

declare(strict_types=1);

final class SyncMonitor
{
    private bool $enabled = false;

    public function __construct(private PDO $stageDb)
    {
        $this->enabled = $this->tablesExist();
    }

    public function start(string $runType, array $context = []): ?int
    {
        if (!$this->enabled) {
            return null;
        }

        $stmt = $this->stageDb->prepare(
            'INSERT INTO sync_runs (run_type, status, started_at, context_json)
             VALUES (:run_type, :status, NOW(), :context_json)'
        );
        $stmt->execute([
            ':run_type' => $runType,
            ':status' => 'running',
            ':context_json' => $this->json($context),
        ]);

        return (int) $this->stageDb->lastInsertId();
    }

    public function log(?int $runId, string $level, string $message, array $context = []): void
    {
        if (!$this->enabled) {
            return;
        }

        $stmt = $this->stageDb->prepare(
            'INSERT INTO sync_logs (sync_run_id, level, message, context_json, created_at)
             VALUES (:run_id, :level, :message, :context_json, NOW())'
        );
        $stmt->execute([
            ':run_id' => $runId,
            ':level' => $level,
            ':message' => $message,
            ':context_json' => $this->json($context),
        ]);
    }

    public function error(?int $runId, string $message, array $context = []): void
    {
        if (!$this->enabled) {
            return;
        }

        $stmt = $this->stageDb->prepare(
            'INSERT INTO sync_errors (sync_run_id, source, record_identifier, message, details, status, created_at)
             VALUES (:run_id, :source, :record_identifier, :message, :details, :status, NOW())'
        );
        $stmt->execute([
            ':run_id' => $runId,
            ':source' => $context['source'] ?? null,
            ':record_identifier' => $context['record_identifier'] ?? null,
            ':message' => $message,
            ':details' => $this->json($context),
            ':status' => 'open',
        ]);
    }

    public function finish(?int $runId, string $status, array $metrics = [], ?string $message = null): void
    {
        if (!$this->enabled || $runId === null) {
            return;
        }

        $stmt = $this->stageDb->prepare(
            'UPDATE sync_runs
             SET status = :status,
                 ended_at = NOW(),
                 imported_records = :imported_records,
                 merged_records = :merged_records,
                 error_count = :error_count,
                 message = :message,
                 context_json = :context_json
             WHERE id = :id'
        );
        $stmt->execute([
            ':status' => $status,
            ':imported_records' => (int) ($metrics['imported_records'] ?? 0),
            ':merged_records' => (int) ($metrics['merged_records'] ?? 0),
            ':error_count' => (int) ($metrics['error_count'] ?? 0),
            ':message' => $message,
            ':context_json' => $this->json($metrics['context'] ?? []),
            ':id' => $runId,
        ]);
    }

    private function tablesExist(): bool
    {
        try {
            $stmt = $this->stageDb->query("SHOW TABLES LIKE 'sync_runs'");
            return (bool) $stmt->fetchColumn();
        } catch (Throwable) {
            return false;
        }
    }

    private function json(array $payload): string
    {
        return json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT) ?: '{}';
    }
}
