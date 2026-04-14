<?php

declare(strict_types=1);

namespace App\Web\Repository;

use App\Web\Core\Paginator;

final class MonitoringRepository
{
    private const MYSQL_TABLE_NOT_FOUND = 1146;

    public function __construct(private \PDO $stageDb)
    {
    }

    public function paginatedRuns(array $filters, Paginator $paginator): array
    {
        try {
            [$whereSql, $params] = $this->buildRunFilters($filters);

            $sql = "SELECT *,
                           TIMESTAMPDIFF(SECOND, started_at, COALESCE(ended_at, UTC_TIMESTAMP())) AS duration_seconds
                    FROM sync_runs
                    {$whereSql}
                    ORDER BY started_at DESC
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

    public function countRuns(array $filters): int
    {
        try {
            [$whereSql, $params] = $this->buildRunFilters($filters);
            $stmt = $this->stageDb->prepare("SELECT COUNT(*) FROM sync_runs {$whereSql}");
            $stmt->execute($params);

            return (int) $stmt->fetchColumn();
        } catch (\PDOException $exception) {
            if ($this->isMissingTable($exception)) {
                return 0;
            }

            throw $exception;
        }
    }

    public function findRun(int $id): ?array
    {
        try {
            $stmt = $this->stageDb->prepare(
                'SELECT *,
                        TIMESTAMPDIFF(SECOND, started_at, COALESCE(ended_at, UTC_TIMESTAMP())) AS duration_seconds
                 FROM sync_runs
                 WHERE id = :id'
            );
            $stmt->execute([':id' => $id]);

            $row = $stmt->fetch(\PDO::FETCH_ASSOC);
            return $row ?: null;
        } catch (\PDOException $exception) {
            if ($this->isMissingTable($exception)) {
                return null;
            }

            throw $exception;
        }
    }

    public function runLogs(int $runId, int $limit = 50): array
    {
        try {
            $stmt = $this->stageDb->prepare('SELECT * FROM sync_logs WHERE sync_run_id = :runId ORDER BY created_at DESC LIMIT :limit');
            $stmt->bindValue(':runId', $runId, \PDO::PARAM_INT);
            $stmt->bindValue(':limit', $limit, \PDO::PARAM_INT);
            $stmt->execute();

            return $stmt->fetchAll(\PDO::FETCH_ASSOC);
        } catch (\PDOException $exception) {
            if ($this->isMissingTable($exception)) {
                return [];
            }

            throw $exception;
        }
    }

    public function runErrors(int $runId, int $limit = 50): array
    {
        try {
            $stmt = $this->stageDb->prepare('SELECT * FROM sync_errors WHERE sync_run_id = :runId ORDER BY created_at DESC LIMIT :limit');
            $stmt->bindValue(':runId', $runId, \PDO::PARAM_INT);
            $stmt->bindValue(':limit', $limit, \PDO::PARAM_INT);
            $stmt->execute();

            return $stmt->fetchAll(\PDO::FETCH_ASSOC);
        } catch (\PDOException $exception) {
            if ($this->isMissingTable($exception)) {
                return [];
            }

            throw $exception;
        }
    }

    public function paginatedLogs(array $filters, Paginator $paginator): array
    {
        try {
            [$whereSql, $params] = $this->buildLogFilters($filters);
            $sql = "SELECT l.*, r.run_type, r.status AS run_status
                    FROM sync_logs l
                    LEFT JOIN sync_runs r ON r.id = l.sync_run_id
                    {$whereSql}
                    ORDER BY l.created_at DESC
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

    public function countLogs(array $filters): int
    {
        try {
            [$whereSql, $params] = $this->buildLogFilters($filters);
            $stmt = $this->stageDb->prepare("SELECT COUNT(*) FROM sync_logs l {$whereSql}");
            $stmt->execute($params);

            return (int) $stmt->fetchColumn();
        } catch (\PDOException $exception) {
            if ($this->isMissingTable($exception)) {
                return 0;
            }

            throw $exception;
        }
    }

    public function paginatedErrors(array $filters, Paginator $paginator): array
    {
        try {
            [$whereSql, $params] = $this->buildErrorFilters($filters);
            $sql = "SELECT e.*, r.run_type, r.status AS run_status
                    FROM sync_errors e
                    LEFT JOIN sync_runs r ON r.id = e.sync_run_id
                    {$whereSql}
                    ORDER BY e.created_at DESC
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

    public function countErrors(array $filters): int
    {
        try {
            [$whereSql, $params] = $this->buildErrorFilters($filters);
            $stmt = $this->stageDb->prepare("SELECT COUNT(*) FROM sync_errors e {$whereSql}");
            $stmt->execute($params);

            return (int) $stmt->fetchColumn();
        } catch (\PDOException $exception) {
            if ($this->isMissingTable($exception)) {
                return 0;
            }

            throw $exception;
        }
    }

    public function findError(int $id): ?array
    {
        try {
            $stmt = $this->stageDb->prepare(
                'SELECT e.*, r.run_type, r.status AS run_status
                 FROM sync_errors e
                 LEFT JOIN sync_runs r ON r.id = e.sync_run_id
                 WHERE e.id = :id'
            );
            $stmt->execute([':id' => $id]);

            $row = $stmt->fetch(\PDO::FETCH_ASSOC);
            return $row ?: null;
        } catch (\PDOException $exception) {
            if ($this->isMissingTable($exception)) {
                return null;
            }

            throw $exception;
        }
    }

    public function latestRunningRun(): ?array
    {
        return $this->fetchOne(
            'SELECT *,
                    TIMESTAMPDIFF(SECOND, started_at, COALESCE(ended_at, UTC_TIMESTAMP())) AS duration_seconds
             FROM sync_runs
             WHERE status = "running"
             ORDER BY started_at DESC
             LIMIT 1'
        );
    }

    public function latestRun(): ?array
    {
        return $this->fetchOne(
            'SELECT *,
                    TIMESTAMPDIFF(SECOND, started_at, COALESCE(ended_at, UTC_TIMESTAMP())) AS duration_seconds
             FROM sync_runs
             ORDER BY started_at DESC
             LIMIT 1'
        );
    }

    public function latestPipelineError(): ?array
    {
        return $this->fetchOne('SELECT * FROM sync_errors ORDER BY created_at DESC LIMIT 1');
    }

    public function runSummary(): array
    {
        try {
            return [
                'running' => $this->countByStatus('running'),
                'success_24h' => $this->countByStatusSince('success', 1),
                'failed_24h' => $this->countByStatusSince('failed', 1),
                'avg_duration_seconds_24h' => $this->averageDurationSince(1),
            ];
        } catch (\PDOException $exception) {
            if ($this->isMissingTable($exception)) {
                return [
                    'running' => 0,
                    'success_24h' => 0,
                    'failed_24h' => 0,
                    'avg_duration_seconds_24h' => null,
                ];
            }

            throw $exception;
        }
    }

    public function recentPipelineLogs(?int $runId = null, int $limit = 10): array
    {
        try {
            if ($runId !== null && $runId > 0) {
                $stmt = $this->stageDb->prepare(
                    'SELECT l.*, r.run_type, r.status AS run_status
                     FROM sync_logs l
                     LEFT JOIN sync_runs r ON r.id = l.sync_run_id
                     WHERE l.sync_run_id = :run_id
                     ORDER BY l.created_at DESC
                     LIMIT :limit'
                );
                $stmt->bindValue(':run_id', $runId, \PDO::PARAM_INT);
                $stmt->bindValue(':limit', $limit, \PDO::PARAM_INT);
                $stmt->execute();

                return $stmt->fetchAll(\PDO::FETCH_ASSOC);
            }

            $stmt = $this->stageDb->prepare(
                'SELECT l.*, r.run_type, r.status AS run_status
                 FROM sync_logs l
                 LEFT JOIN sync_runs r ON r.id = l.sync_run_id
                 ORDER BY l.created_at DESC
                 LIMIT :limit'
            );
            $stmt->bindValue(':limit', $limit, \PDO::PARAM_INT);
            $stmt->execute();

            return $stmt->fetchAll(\PDO::FETCH_ASSOC);
        } catch (\PDOException $exception) {
            if ($this->isMissingTable($exception)) {
                return [];
            }

            throw $exception;
        }
    }

    public function countLogsForRun(?int $runId): int
    {
        if ($runId === null || $runId <= 0) {
            return 0;
        }

        try {
            $stmt = $this->stageDb->prepare('SELECT COUNT(*) FROM sync_logs WHERE sync_run_id = :run_id');
            $stmt->bindValue(':run_id', $runId, \PDO::PARAM_INT);
            $stmt->execute();

            return (int) $stmt->fetchColumn();
        } catch (\PDOException $exception) {
            if ($this->isMissingTable($exception)) {
                return 0;
            }

            throw $exception;
        }
    }

    public function latestLogForRun(?int $runId = null): ?array
    {
        if ($runId === null || $runId <= 0) {
            return null;
        }

        try {
            $stmt = $this->stageDb->prepare(
                'SELECT l.*, r.run_type, r.status AS run_status
                 FROM sync_logs l
                 LEFT JOIN sync_runs r ON r.id = l.sync_run_id
                 WHERE l.sync_run_id = :run_id
                 ORDER BY l.created_at DESC, l.id DESC
                 LIMIT 1'
            );
            $stmt->bindValue(':run_id', $runId, \PDO::PARAM_INT);
            $stmt->execute();

            $row = $stmt->fetch(\PDO::FETCH_ASSOC);
            return $row ?: null;
        } catch (\PDOException $exception) {
            if ($this->isMissingTable($exception)) {
                return null;
            }

            throw $exception;
        }
    }

    private function isMissingTable(\PDOException $exception): bool
    {
        return (int) ($exception->errorInfo[1] ?? 0) === self::MYSQL_TABLE_NOT_FOUND;
    }

    private function countByStatus(string $status): int
    {
        $stmt = $this->stageDb->prepare('SELECT COUNT(*) FROM sync_runs WHERE status = :status');
        $stmt->execute([':status' => $status]);

        return (int) $stmt->fetchColumn();
    }

    private function countByStatusSince(string $status, int $days): int
    {
        $stmt = $this->stageDb->prepare(
            'SELECT COUNT(*)
             FROM sync_runs
             WHERE status = :status
               AND started_at >= DATE_SUB(UTC_TIMESTAMP(), INTERVAL :days DAY)'
        );
        $stmt->bindValue(':status', $status);
        $stmt->bindValue(':days', $days, \PDO::PARAM_INT);
        $stmt->execute();

        return (int) $stmt->fetchColumn();
    }

    private function averageDurationSince(int $days): ?int
    {
        $stmt = $this->stageDb->prepare(
            'SELECT AVG(TIMESTAMPDIFF(SECOND, started_at, ended_at))
             FROM sync_runs
             WHERE ended_at IS NOT NULL
               AND started_at >= DATE_SUB(UTC_TIMESTAMP(), INTERVAL :days DAY)'
        );
        $stmt->bindValue(':days', $days, \PDO::PARAM_INT);
        $stmt->execute();

        $value = $stmt->fetchColumn();

        return $value === null ? null : (int) round((float) $value);
    }

    private function fetchOne(string $sql): ?array
    {
        try {
            $stmt = $this->stageDb->query($sql);
            $row = $stmt->fetch(\PDO::FETCH_ASSOC);

            return $row ?: null;
        } catch (\PDOException $exception) {
            if ($this->isMissingTable($exception)) {
                return null;
            }

            throw $exception;
        }
    }

    private function buildRunFilters(array $filters): array
    {
        $where = [];
        $params = [];

        if ($filters['status'] !== '') {
            $where[] = 'status = :status';
            $params[':status'] = $filters['status'];
        }

        if ($filters['q'] !== '') {
            $where[] = '(run_type LIKE :q OR COALESCE(message, "") LIKE :q)';
            $params[':q'] = '%' . $filters['q'] . '%';
        }

        return [$this->compileWhere($where), $params];
    }

    private function buildLogFilters(array $filters): array
    {
        $where = [];
        $params = [];

        if ($filters['level'] !== '') {
            $where[] = 'l.level = :level';
            $params[':level'] = $filters['level'];
        }

        if ($filters['q'] !== '') {
            $where[] = '(l.message LIKE :q OR COALESCE(l.context_json, "") LIKE :q)';
            $params[':q'] = '%' . $filters['q'] . '%';
        }

        return [$this->compileWhere($where), $params];
    }

    private function buildErrorFilters(array $filters): array
    {
        $where = [];
        $params = [];

        if ($filters['status'] !== '') {
            $where[] = 'e.status = :status';
            $params[':status'] = $filters['status'];
        }

        if ($filters['q'] !== '') {
            $where[] = '(e.message LIKE :q OR COALESCE(e.details, "") LIKE :q OR COALESCE(e.source, "") LIKE :q)';
            $params[':q'] = '%' . $filters['q'] . '%';
        }

        return [$this->compileWhere($where), $params];
    }

    private function compileWhere(array $parts): string
    {
        return $parts === [] ? '' : 'WHERE ' . implode(' AND ', $parts);
    }
}
