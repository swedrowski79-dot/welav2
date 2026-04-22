<?php

declare(strict_types=1);

final class AfsExtrasBootstrapService
{
    private int $writeBatchSize;

    public function __construct(
        private PDO $sqliteSourceDb,
        private PDO $extraTargetDb,
        private array $sqliteSourceConfig,
        private array $extraTargetConfig
    ) {
        $this->writeBatchSize = max(
            1,
            (int) (
                $this->extraTargetConfig['write_batch_size']
                ?? $this->sqliteSourceConfig['write_batch_size']
                ?? 250
            )
        );
    }

    public function syncAll(): void
    {
        $this->ensureSchema();
        $this->syncEntity('article_translations');
        $this->syncEntity('category_translations');
    }

    private function syncEntity(string $entityName): void
    {
        $sourceEntity = $this->entityConfig($this->sqliteSourceConfig, $entityName, 'SQLite bootstrap');
        $targetEntity = $this->entityConfig($this->extraTargetConfig, $entityName, 'AFS Extras');

        $sourceTable = (string) $sourceEntity['table'];
        $targetTable = (string) $targetEntity['table'];
        $columns = $sourceEntity['columns'];

        if ($columns !== ($targetEntity['columns'] ?? [])) {
            throw new RuntimeException("Entity '{$entityName}' column definitions do not match between bootstrap and target source.");
        }

        $quotedColumns = array_map(
            fn (mixed $column): string => $this->quoteIdentifier((string) $column, 'sqlite'),
            $columns
        );

        $sql = 'SELECT ' . implode(', ', $quotedColumns) . ' FROM ' . $this->quoteIdentifier($sourceTable, 'sqlite');
        $stmt = $this->sqliteSourceDb->query($sql);

        $writer = new StageWriter($this->extraTargetDb);
        $writer->truncate($targetTable);

        $batch = [];

        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $batch[] = $row;

            if (count($batch) >= $this->writeBatchSize) {
                $writer->insertMany($targetTable, $batch);
                $batch = [];
            }
        }

        if ($batch !== []) {
            $writer->insertMany($targetTable, $batch);
        }
    }

    private function ensureSchema(): void
    {
        $articleTable = (string) $this->entityConfig($this->extraTargetConfig, 'article_translations', 'AFS Extras')['table'];
        $categoryTable = (string) $this->entityConfig($this->extraTargetConfig, 'category_translations', 'AFS Extras')['table'];

        $this->extraTargetDb->exec(
            'CREATE TABLE IF NOT EXISTS ' . $this->quoteIdentifier($articleTable, 'mysql') . ' (
                `id` INT NOT NULL PRIMARY KEY,
                `artikel_id` INT NULL,
                `article_number` VARCHAR(255) NULL,
                `master_article_number` VARCHAR(255) NULL,
                `language` VARCHAR(10) NULL,
                `article_name` VARCHAR(255) NULL,
                `intro_text` MEDIUMTEXT NULL,
                `description` MEDIUMTEXT NULL,
                `technical_data_html` MEDIUMTEXT NULL,
                `attribute_name1` VARCHAR(255) NULL,
                `attribute_name2` VARCHAR(255) NULL,
                `attribute_name3` VARCHAR(255) NULL,
                `attribute_name4` VARCHAR(255) NULL,
                `attribute_value1` VARCHAR(255) NULL,
                `attribute_value2` VARCHAR(255) NULL,
                `attribute_value3` VARCHAR(255) NULL,
                `attribute_value4` VARCHAR(255) NULL,
                `meta_title` VARCHAR(255) NULL,
                `meta_description` MEDIUMTEXT NULL,
                `is_master` TINYINT NULL,
                `source_directory` VARCHAR(255) NULL,
                KEY `idx_article_translations_artikel_id` (`artikel_id`),
                KEY `idx_article_translations_language` (`language`),
                KEY `idx_article_translations_article_number` (`article_number`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4'
        );

        if (!$this->columnExists($articleTable, 'intro_text')) {
            $this->extraTargetDb->exec(
                'ALTER TABLE ' . $this->quoteIdentifier($articleTable, 'mysql') . ' ADD COLUMN `intro_text` MEDIUMTEXT NULL AFTER `article_name`'
            );
        }

        $this->extraTargetDb->exec(
            'CREATE TABLE IF NOT EXISTS ' . $this->quoteIdentifier($categoryTable, 'mysql') . ' (
                `id` INT NOT NULL PRIMARY KEY,
                `warengruppen_id` INT NULL,
                `original_name` VARCHAR(255) NULL,
                `language` VARCHAR(10) NULL,
                `translated_name` VARCHAR(255) NULL,
                `meta_description` MEDIUMTEXT NULL,
                `meta_title` VARCHAR(255) NULL,
                KEY `idx_category_translations_warengruppen_id` (`warengruppen_id`),
                KEY `idx_category_translations_language` (`language`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4'
        );

        $this->extraTargetDb->exec(
            'CREATE TABLE IF NOT EXISTS `attribute_translations` (
                `id` INT NOT NULL PRIMARY KEY,
                `article_id` INT NULL,
                `article_number` VARCHAR(255) NULL,
                `sort_order` INT NULL,
                `language` VARCHAR(10) NULL,
                `attribute_name` VARCHAR(255) NULL,
                `attribute_value` VARCHAR(255) NULL,
                `source_directory` VARCHAR(255) NULL,
                KEY `idx_attribute_translations_article_id` (`article_id`),
                KEY `idx_attribute_translations_article_number` (`article_number`),
                KEY `idx_attribute_translations_language` (`language`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4'
        );
    }

    private function entityConfig(array $sourceConfig, string $entityName, string $sourceLabel): array
    {
        $entity = $sourceConfig['entities'][$entityName] ?? null;

        if (!is_array($entity)) {
            throw new RuntimeException("{$sourceLabel} entity '{$entityName}' is not configured.");
        }

        if (!is_string($entity['table'] ?? null) || !is_array($entity['columns'] ?? null) || $entity['columns'] === []) {
            throw new RuntimeException("{$sourceLabel} entity '{$entityName}' is incomplete.");
        }

        return $entity;
    }

    private function quoteIdentifier(string $identifier, string $type): string
    {
        return match ($type) {
            'sqlite' => '"' . str_replace('"', '""', $identifier) . '"',
            'mysql' => '`' . str_replace('`', '``', $identifier) . '`',
            default => $identifier,
        };
    }

    private function columnExists(string $table, string $column): bool
    {
        $stmt = $this->extraTargetDb->query('SHOW COLUMNS FROM ' . $this->quoteIdentifier($table, 'mysql'));
        $columns = $stmt->fetchAll(PDO::FETCH_COLUMN);

        return in_array($column, $columns, true);
    }
}
