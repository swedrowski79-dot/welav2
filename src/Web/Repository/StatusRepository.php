<?php

declare(strict_types=1);

namespace App\Web\Repository;

final class StatusRepository
{
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
            $stmt = $this->stageDb->query("SELECT COUNT(*) FROM `{$table}`");
            $counts[$table] = (int) $stmt->fetchColumn();
        }

        return $counts;
    }
}
