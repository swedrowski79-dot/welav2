<?php

declare(strict_types=1);

namespace App\Web\Repository;

use App\Web\Core\Paginator;

final class PipelineAdminRepository
{
    private const MYSQL_TABLE_NOT_FOUND = 1146;
    private ?array $exportQueueColumns = null;

    public function __construct(private \PDO $stageDb, private array $adminConfig)
    {
    }

    public function paginatedQueueEntries(array $filters, Paginator $paginator): array
    {
        try {
            [$whereSql, $params] = $this->buildQueueFilters($filters);
            $columns = $this->queueSelectColumns();

            $sql = "SELECT {$columns}
                    FROM export_queue
                    {$whereSql}
                    ORDER BY created_at DESC, id DESC
                    LIMIT :limit OFFSET :offset";
            $stmt = $this->stageDb->prepare($sql);

            foreach ($params as $key => $value) {
                $stmt->bindValue($key, $value);
            }

            $stmt->bindValue(':limit', $paginator->perPage, \PDO::PARAM_INT);
            $stmt->bindValue(':offset', $paginator->offset(), \PDO::PARAM_INT);
            $stmt->execute();

            return $stmt->fetchAll(\PDO::FETCH_ASSOC);
        } catch (\PDOException $exception) {
            if ($this->isMissingTable($exception)) {
                return [];
            }

            throw $exception;
        }
    }

    public function countQueueEntries(array $filters): int
    {
        try {
            [$whereSql, $params] = $this->buildQueueFilters($filters);
            $stmt = $this->stageDb->prepare("SELECT COUNT(*) FROM export_queue {$whereSql}");
            $stmt->execute($params);

            return (int) $stmt->fetchColumn();
        } catch (\PDOException $exception) {
            if ($this->isMissingTable($exception)) {
                return 0;
            }

            throw $exception;
        }
    }

    public function queueSummary(): array
    {
        return [
            'pending' => $this->countWhere('export_queue', "status = 'pending'"),
            'processing' => $this->countWhere('export_queue', "status = 'processing'"),
            'done' => $this->countWhere('export_queue', "status = 'done'"),
            'error' => $this->countWhere('export_queue', "status = 'error'"),
        ];
    }

    public function queueSummaryByEntity(): array
    {
        try {
            $stmt = $this->stageDb->query(
                "SELECT entity_type, status, COUNT(*) AS item_count
                 FROM export_queue
                 GROUP BY entity_type, status
                 ORDER BY entity_type ASC, status ASC"
            );

            $summary = [];

            while ($row = $stmt->fetch(\PDO::FETCH_ASSOC)) {
                $entityType = (string) ($row['entity_type'] ?? 'unknown');
                $status = (string) ($row['status'] ?? 'unknown');
                $summary[$entityType] ??= [
                    'pending' => 0,
                    'processing' => 0,
                    'done' => 0,
                    'error' => 0,
                ];

                if (!array_key_exists($status, $summary[$entityType])) {
                    continue;
                }

                $summary[$entityType][$status] = (int) ($row['item_count'] ?? 0);
            }

            return $summary;
        } catch (\PDOException $exception) {
            if ($this->isMissingTable($exception)) {
                return [];
            }

            throw $exception;
        }
    }

    public function stateSummary(): array
    {
        return [
            'entries' => $this->countWhere('product_export_state'),
            'stage_tables' => count($this->stageTables()),
        ];
    }

    public function paginatedStateEntries(string $search, Paginator $paginator): array
    {
        try {
            $whereSql = '';
            $params = [];

            if ($search !== '') {
                $whereSql = 'WHERE CAST(product_id AS CHAR) LIKE :search OR COALESCE(last_exported_hash, "") LIKE :search';
                $params[':search'] = '%' . $search . '%';
            }

            $stmt = $this->stageDb->prepare(
                "SELECT product_id, last_exported_hash, last_seen_at
                 FROM product_export_state
                 {$whereSql}
                 ORDER BY last_seen_at DESC, product_id DESC
                 LIMIT :limit OFFSET :offset"
            );

            foreach ($params as $key => $value) {
                $stmt->bindValue($key, $value);
            }

            $stmt->bindValue(':limit', $paginator->perPage, \PDO::PARAM_INT);
            $stmt->bindValue(':offset', $paginator->offset(), \PDO::PARAM_INT);
            $stmt->execute();

            return $stmt->fetchAll(\PDO::FETCH_ASSOC);
        } catch (\PDOException $exception) {
            if ($this->isMissingTable($exception)) {
                return [];
            }

            throw $exception;
        }
    }

    public function countStateEntries(string $search): int
    {
        try {
            $whereSql = '';
            $params = [];

            if ($search !== '') {
                $whereSql = 'WHERE CAST(product_id AS CHAR) LIKE :search OR COALESCE(last_exported_hash, "") LIKE :search';
                $params[':search'] = '%' . $search . '%';
            }

            $stmt = $this->stageDb->prepare("SELECT COUNT(*) FROM product_export_state {$whereSql}");
            $stmt->execute($params);

            return (int) $stmt->fetchColumn();
        } catch (\PDOException $exception) {
            if ($this->isMissingTable($exception)) {
                return 0;
            }

            throw $exception;
        }
    }

    public function resetQueue(): void
    {
        $deleted = $this->stageDb->exec('DELETE FROM `export_queue`');
        $this->logAdminAction('Export Queue wurde geleert.', [
            'action' => 'reset_queue',
            'deleted_rows' => (int) $deleted,
        ], 'warning');
    }

    public function resetStageTables(): void
    {
        $count = 0;
        foreach ($this->stageTables() as $table) {
            $this->stageDb->exec("TRUNCATE TABLE `{$table}`");
            $count++;
        }

        $this->logAdminAction('Stage-Tabellen wurden geleert.', [
            'action' => 'reset_stage',
            'tables' => $this->stageTables(),
            'table_count' => $count,
        ], 'warning');
    }

    public function resetDeltaState(): void
    {
        $tables = ['product_export_state', 'product_media_export_state', 'product_document_export_state'];

        foreach ($tables as $table) {
            try {
                $this->stageDb->exec("TRUNCATE TABLE `{$table}`");
            } catch (\PDOException $exception) {
                if (!$this->isMissingTable($exception)) {
                    throw $exception;
                }
            }
        }

        $this->logAdminAction('Export States wurden geleert.', [
            'action' => 'reset_delta_state',
            'tables' => $tables,
        ], 'warning');
    }

    public function resetLogs(): void
    {
        $deleted = $this->stageDb->exec('DELETE FROM `sync_logs`');
        $this->logAdminAction('Sync-Logs wurden geleert.', [
            'action' => 'reset_logs',
            'deleted_rows' => (int) $deleted,
        ], 'warning');
    }

    public function resetErrors(): void
    {
        $deleted = $this->stageDb->exec('DELETE FROM `sync_errors`');
        $this->logAdminAction('Sync-Fehler wurden geleert.', [
            'action' => 'reset_errors',
            'deleted_rows' => (int) $deleted,
        ], 'warning');
    }

    public function resetRunsHistory(): void
    {
        $deleted = $this->stageDb->exec('DELETE FROM `sync_runs`');
        $this->logAdminAction('Sync-Laufhistorie wurde geleert.', [
            'action' => 'reset_runs',
            'deleted_rows' => (int) $deleted,
        ], 'warning');
    }

    public function fullReset(): void
    {
        $this->resetQueue();
        $rawTables = $this->rawTables();
        $rawCount = 0;

        foreach ($rawTables as $table) {
            $this->stageDb->exec("TRUNCATE TABLE `{$table}`");
            $rawCount++;
        }

        $this->resetStageTables();
        $this->resetDeltaState();

        $this->logAdminAction('Vollreset wurde ausgefuehrt.', [
            'action' => 'full_reset',
            'raw_tables' => $rawTables,
            'raw_table_count' => $rawCount,
            'stage_tables' => $this->stageTables(),
        ], 'warning');
    }

    private function buildQueueFilters(array $filters): array
    {
        $where = [];
        $params = [];

        if (($filters['entity_type'] ?? '') !== '') {
            $where[] = 'entity_type = :entity_type';
            $params[':entity_type'] = $filters['entity_type'];
        }

        if (($filters['status'] ?? '') !== '') {
            $where[] = 'status = :status';
            $params[':status'] = $filters['status'];
        }

        if (($filters['action'] ?? '') !== '') {
            $where[] = 'action = :action';
            $params[':action'] = $filters['action'];
        }

        return [$where === [] ? '' : 'WHERE ' . implode(' AND ', $where), $params];
    }

    private function logAdminAction(string $message, array $context, string $level): void
    {
        try {
            $stmt = $this->stageDb->prepare(
                'INSERT INTO sync_logs (sync_run_id, level, message, context_json, created_at)
                 VALUES (NULL, :level, :message, :context_json, NOW())'
            );
            $stmt->execute([
                ':level' => $level,
                ':message' => $message,
                ':context_json' => json_encode($context, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
            ]);
        } catch (\PDOException $exception) {
            if (!$this->isMissingTable($exception)) {
                throw $exception;
            }
        }
    }

    private function countWhere(string $table, string $whereSql = '1=1'): int
    {
        try {
            $stmt = $this->stageDb->query("SELECT COUNT(*) FROM `{$table}` WHERE {$whereSql}");
            return (int) $stmt->fetchColumn();
        } catch (\PDOException $exception) {
            if ($this->isMissingTable($exception)) {
                return 0;
            }

            throw $exception;
        }
    }

    private function stageTables(): array
    {
        $tables = $this->adminConfig['stage_tables'] ?? [];

        return array_values(array_filter(
            array_keys($tables),
            static fn (string $table): bool => str_starts_with($table, 'stage_')
        ));
    }

    private function rawTables(): array
    {
        $tables = $this->adminConfig['stage_tables'] ?? [];

        return array_values(array_filter(
            array_keys($tables),
            static fn (string $table): bool => str_starts_with($table, 'raw_')
        ));
    }

    private function isMissingTable(\PDOException $exception): bool
    {
        return (int) ($exception->errorInfo[1] ?? 0) === self::MYSQL_TABLE_NOT_FOUND;
    }

    private function queueSelectColumns(): string
    {
        $required = ['id', 'entity_type', 'entity_id', 'action', 'payload', 'status', 'created_at'];
        $optional = ['attempt_count', 'available_at', 'claimed_at', 'processed_at', 'last_error'];
        $present = $this->exportQueueColumns();
        $selects = $required;

        foreach ($optional as $column) {
            if (in_array($column, $present, true)) {
                $selects[] = $column;
                continue;
            }

            $selects[] = "NULL AS {$column}";
        }

        return implode(', ', $selects);
    }

    private function exportQueueColumns(): array
    {
        if ($this->exportQueueColumns !== null) {
            return $this->exportQueueColumns;
        }

        try {
            $stmt = $this->stageDb->query('SHOW COLUMNS FROM `export_queue`');
            $columns = [];

            while ($row = $stmt->fetch(\PDO::FETCH_ASSOC)) {
                $field = $row['Field'] ?? null;
                if (is_string($field) && $field !== '') {
                    $columns[] = $field;
                }
            }

            return $this->exportQueueColumns = $columns;
        } catch (\PDOException $exception) {
            if ($this->isMissingTable($exception)) {
                return $this->exportQueueColumns = [];
            }

            throw $exception;
        }
    }
}
