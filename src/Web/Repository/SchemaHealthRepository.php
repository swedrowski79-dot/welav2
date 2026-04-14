<?php

declare(strict_types=1);

namespace App\Web\Repository;

final class SchemaHealthRepository
{
    /**
     * @return list<array{type:string, table:string, column:?string, message:string}>
     */
    public function issues(\PDO $stageDb): array
    {
        $issues = [];

        foreach ($this->requiredSchema() as $table => $columns) {
            if (!$this->tableExists($stageDb, $table)) {
                $issues[] = [
                    'type' => 'missing_table',
                    'table' => $table,
                    'column' => null,
                    'message' => sprintf('Tabelle `%s` fehlt.', $table),
                ];
                continue;
            }

            $presentColumns = $this->tableColumns($stageDb, $table);

            foreach ($columns as $column) {
                if (!in_array($column, $presentColumns, true)) {
                    $issues[] = [
                        'type' => 'missing_column',
                        'table' => $table,
                        'column' => $column,
                        'message' => sprintf('Spalte `%s.%s` fehlt.', $table, $column),
                    ];
                }
            }
        }

        return $issues;
    }

    private function requiredSchema(): array
    {
        return [
            'stage_products' => [
                'hash',
            ],
            'product_export_state' => [
                'product_id',
                'last_exported_hash',
                'last_seen_at',
            ],
            'export_queue' => [
                'id',
                'entity_type',
                'entity_id',
                'action',
                'payload',
                'status',
                'created_at',
            ],
        ];
    }

    private function tableExists(\PDO $stageDb, string $table): bool
    {
        $stmt = $stageDb->prepare('SHOW TABLES LIKE :table');
        $stmt->execute([':table' => $table]);

        return (bool) $stmt->fetchColumn();
    }

    private function tableColumns(\PDO $stageDb, string $table): array
    {
        $stmt = $stageDb->query("SHOW COLUMNS FROM `{$table}`");
        $columns = [];

        while ($row = $stmt->fetch(\PDO::FETCH_ASSOC)) {
            $field = $row['Field'] ?? null;

            if (is_string($field) && $field !== '') {
                $columns[] = $field;
            }
        }

        return $columns;
    }
}
