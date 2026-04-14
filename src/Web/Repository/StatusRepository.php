<?php

declare(strict_types=1);

namespace App\Web\Repository;

final class StatusRepository
{
    private const MYSQL_TABLE_NOT_FOUND = 1146;

    public function __construct(private \PDO $stageDb)
    {
    }

    public function tableCounts(): array
    {
        $tables = [
            'raw_afs_articles',
            'raw_afs_categories',
            'raw_extra_article_translations',
            'raw_extra_category_translations',
            'stage_products',
            'stage_product_translations',
            'stage_categories',
            'stage_category_translations',
            'stage_attribute_translations',
            'sync_runs',
            'sync_logs',
            'sync_errors',
        ];

        $counts = [];
        foreach ($tables as $table) {
            $counts[$table] = $this->countTable($table);
        }

        return $counts;
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

    private function isMissingTable(\PDOException $exception): bool
    {
        return (int) ($exception->errorInfo[1] ?? 0) === self::MYSQL_TABLE_NOT_FOUND;
    }
}
