<?php

declare(strict_types=1);

namespace App\Web\Repository;

use App\Web\Core\Paginator;

final class PipelineAdminRepository
{
    private const MYSQL_TABLE_NOT_FOUND = 1146;

    public function __construct(private \PDO $stageDb, private array $adminConfig)
    {
    }

    public function paginatedQueueEntries(array $filters, Paginator $paginator): array
    {
        try {
            [$whereSql, $params] = $this->buildQueueFilters($filters);

            $sql = "SELECT id, entity_type, entity_id, action, payload, status, created_at
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
            'done' => $this->countWhere('export_queue', "status = 'done'"),
            'error' => $this->countWhere('export_queue', "status = 'error'"),
        ];
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
        $this->stageDb->exec('TRUNCATE TABLE `product_export_state`');
        $this->logAdminAction('Produkt Export State wurde geleert.', [
            'action' => 'reset_delta_state',
        ], 'warning');
    }

    public function fullReset(): void
    {
        $this->resetQueue();
        $this->resetStageTables();
        $this->resetDeltaState();

        $this->logAdminAction('Vollreset wurde ausgefuehrt.', [
            'action' => 'full_reset',
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

    private function isMissingTable(\PDOException $exception): bool
    {
        return (int) ($exception->errorInfo[1] ?? 0) === self::MYSQL_TABLE_NOT_FOUND;
    }
}
