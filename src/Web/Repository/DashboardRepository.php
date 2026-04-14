<?php

declare(strict_types=1);

namespace App\Web\Repository;

final class DashboardRepository
{
    private const MYSQL_TABLE_NOT_FOUND = 1146;

    public function __construct(private \PDO $stageDb)
    {
    }

    public function metrics(): array
    {
        return [
            'products' => $this->countTable('stage_products'),
            'categories' => $this->countTable('stage_categories'),
            'translations' => $this->countTable('stage_product_translations'),
            'attribute_translations' => $this->countTable('stage_attribute_translations'),
            'open_errors' => $this->countOpenErrors(),
        ];
    }

    public function latestRuns(int $limit = 5): array
    {
        try {
            $stmt = $this->stageDb->prepare('SELECT * FROM sync_runs ORDER BY started_at DESC LIMIT :limit');
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

    public function lastSuccessfulRun(): ?array
    {
        return $this->fetchOne('SELECT * FROM sync_runs WHERE status = "success" ORDER BY ended_at DESC LIMIT 1');
    }

    public function lastError(): ?array
    {
        return $this->fetchOne('SELECT * FROM sync_errors ORDER BY created_at DESC LIMIT 1');
    }

    private function countTable(string $table): int
    {
        try {
            $stmt = $this->stageDb->query("SELECT COUNT(*) FROM `{$table}`");

            return (int) $stmt->fetchColumn();
        } catch (\PDOException $exception) {
            if ($this->isMissingTable($exception)) {
                return 0;
            }

            throw $exception;
        }
    }

    private function countOpenErrors(): int
    {
        try {
            $stmt = $this->stageDb->query("SELECT COUNT(*) FROM sync_errors WHERE status = 'open'");

            return (int) $stmt->fetchColumn();
        } catch (\PDOException $exception) {
            if ($this->isMissingTable($exception)) {
                return 0;
            }

            throw $exception;
        }
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

    private function isMissingTable(\PDOException $exception): bool
    {
        return (int) ($exception->errorInfo[1] ?? 0) === self::MYSQL_TABLE_NOT_FOUND;
    }
}
