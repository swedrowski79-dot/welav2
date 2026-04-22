<?php

class ExtraImporter
{
    private int $writeBatchSize;

    public function __construct(
        private PDO $sourceDb,
        private StageWriter $stageWriter,
        private Normalizer $normalizer,
        private array $sourceConfig
    ) {
        $this->writeBatchSize = max(1, (int) ($this->sourceConfig['write_batch_size'] ?? 250));
    }

    public function importArticleTranslations(): void
    {
        $stmt = $this->runEntityQuery('article_translations');
        $this->importIntoTable($stmt, 'extra.article_translations', 'raw_extra_article_translations');
    }

    public function importCategoryTranslations(): void
    {
        $stmt = $this->runEntityQuery('category_translations');
        $this->importIntoTable($stmt, 'extra.category_translations', 'raw_extra_category_translations');
    }

    public function importAttributeTranslations(): void
    {
        $stmt = $this->runEntityQuery('attribute_translations');
        $this->importIntoTable($stmt, 'extra.attribute_translations', 'raw_extra_attribute_translations');
    }

    private function runEntityQuery(string $entityName): PDOStatement
    {
        $entityConfig = $this->sourceConfig['entities'][$entityName] ?? null;

        if (!is_array($entityConfig)) {
            throw new RuntimeException("Extra entity '{$entityName}' is not configured.");
        }

        $table = $entityConfig['table'] ?? null;
        $columns = $entityConfig['columns'] ?? null;

        if (!is_string($table) || $table === '') {
            throw new RuntimeException("Extra entity '{$entityName}' is missing a table configuration.");
        }

        if (!is_array($columns) || $columns === []) {
            throw new RuntimeException("Extra entity '{$entityName}' is missing column definitions.");
        }

        $selectColumns = array_map(
            fn (mixed $column): string => $this->quoteIdentifier((string) $column),
            $columns
        );

        $sql = 'SELECT ' . implode(', ', $selectColumns) . ' FROM ' . $this->quoteIdentifier($table);

        return $this->sourceDb->query($sql);
    }

    private function importIntoTable(PDOStatement $stmt, string $normalizeKey, string $targetTable): void
    {
        $batch = [];

        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $batch[] = $this->normalizer->normalize($normalizeKey, $row);

            if (count($batch) >= $this->writeBatchSize) {
                $this->stageWriter->insertMany($targetTable, $batch);
                $batch = [];
            }
        }

        if ($batch !== []) {
            $this->stageWriter->insertMany($targetTable, $batch);
        }
    }

    private function quoteIdentifier(string $identifier): string
    {
        $type = (string) ($this->sourceConfig['type'] ?? 'sqlite');

        if ($type === 'mysql') {
            return '`' . str_replace('`', '``', $identifier) . '`';
        }

        return '"' . str_replace('"', '""', $identifier) . '"';
    }
}
