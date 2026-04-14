<?php

class AfsImporter
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

    public function importArticles(): void
    {
        $stmt = $this->runEntityQuery('articles');
        $this->importIntoTable($stmt, 'afs.articles', 'raw_afs_articles');
    }

    public function importCategories(): void
    {
        $stmt = $this->runEntityQuery('categories');
        $this->importIntoTable($stmt, 'afs.categories', 'raw_afs_categories');
    }

    private function runEntityQuery(string $entityName): PDOStatement
    {
        $entityConfig = $this->sourceConfig['entities'][$entityName] ?? null;

        if (!is_array($entityConfig)) {
            throw new RuntimeException("AFS entity '{$entityName}' is not configured.");
        }

        $table = $entityConfig['table'] ?? null;
        $columns = $entityConfig['columns'] ?? null;
        $where = $entityConfig['where'] ?? [];

        if (!is_string($table) || $table === '') {
            throw new RuntimeException("AFS entity '{$entityName}' is missing a table configuration.");
        }

        if (!is_array($columns) || $columns === []) {
            throw new RuntimeException("AFS entity '{$entityName}' is missing column definitions.");
        }

        $selectColumns = array_map(
            fn (mixed $column): string => $this->quoteIdentifier((string) $column),
            $columns
        );

        $sql = 'SELECT ' . implode(', ', $selectColumns) . ' FROM ' . $this->quoteQualifiedTable($table);

        if (is_array($where) && $where !== []) {
            $sql .= ' WHERE ' . implode(' AND ', $where);
        }

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

    private function quoteQualifiedTable(string $table): string
    {
        $segments = array_values(array_filter(
            array_map('trim', explode('.', $table)),
            static fn (string $segment): bool => $segment !== ''
        ));

        if ($segments === []) {
            throw new RuntimeException('AFS table name is empty.');
        }

        return implode('.', array_map([$this, 'quoteIdentifier'], $segments));
    }

    private function quoteIdentifier(string $identifier): string
    {
        return '[' . str_replace(']', ']]', $identifier) . ']';
    }
}
