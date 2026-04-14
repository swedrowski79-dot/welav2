<?php

class MergeService
{
    private int $insertBatchSize;

    public function __construct(
        private PDO $stageDb,
        private array $mergeConfig
    ) {
        $config = $this->mergeConfig['merge'] ?? [];
        $this->insertBatchSize = max(1, (int) ($config['insert_batch_size'] ?? 250));
    }

    public function run(): void
    {
        $config = $this->mergeConfig['merge'] ?? [];
        unset($config['insert_batch_size']);

        $this->mergeTable('stage_products', $config['stage_products']);
        $this->mergeTable('stage_product_translations', $config['stage_product_translations']);
        $this->mergeTable('stage_categories', $config['stage_categories']);
        $this->mergeTable('stage_category_translations', $config['stage_category_translations']);
    }

    private function mergeTable(string $targetTable, array $definition): void
    {
        $this->stageDb->exec("TRUNCATE TABLE `{$targetTable}`");

        $baseTable = $definition['base'];
        $baseRows = $this->fetchRows($baseTable);
        $matches = $definition['match'] ?? [];
        $matchIndexes = $this->buildMatchIndexes($matches);
        $batch = [];

        foreach ($baseRows as $baseRow) {
            $context = [$baseTable => $baseRow];

            foreach ($matches as $matchTable => $rule) {
                $localValue = $baseRow[$rule['local']] ?? null;
                $context[$matchTable] = $this->findMatch($matchIndexes[$matchTable] ?? [], $localValue);
            }

            $merged = [];
            foreach ($definition['fields'] as $field => $fieldDef) {
                $merged[$field] = $this->resolveField($fieldDef, $context);
            }

            $batch[] = $merged;

            if (count($batch) >= $this->insertBatchSize) {
                $this->insertRows($targetTable, $batch);
                $batch = [];
            }
        }

        if ($batch !== []) {
            $this->insertRows($targetTable, $batch);
        }
    }

    private function fetchRows(string $table): array
    {
        $stmt = $this->stageDb->query("SELECT * FROM `{$table}`");
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    private function buildMatchIndexes(array $matches): array
    {
        $indexes = [];

        foreach ($matches as $matchTable => $rule) {
            $foreignField = $rule['foreign'] ?? null;

            if (!is_string($foreignField) || $foreignField === '') {
                continue;
            }

            $stmt = $this->stageDb->query("SELECT * FROM `{$matchTable}`");
            $index = [];

            while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
                $key = $row[$foreignField] ?? null;

                if ($key === null || $key === '') {
                    continue;
                }

                $index[(string) $key] = $row;
            }

            $indexes[$matchTable] = $index;
        }

        return $indexes;
    }

    private function findMatch(array $index, mixed $value): ?array
    {
        if ($value === null || $value === '') {
            return null;
        }

        return $index[(string) $value] ?? null;
    }

    private function resolveField(array $fieldDef, array $context): mixed
    {
        $sources = $fieldDef['from'] ?? [];
        $strategy = $fieldDef['strategy'] ?? null;

        $values = [];
        foreach ($sources as $source) {
            $values[] = $this->resolveSourcePath($source, $context);
        }

        if ($strategy === 'first_not_empty') {
            foreach ($values as $value) {
                if ($value !== null && $value !== '') {
                    return $value;
                }
            }
            return null;
        }

        return $values[0] ?? null;
    }

    private function resolveSourcePath(string $path, array $context): mixed
    {
        $parts = explode('.', $path, 2);
        if (count($parts) !== 2) {
            return null;
        }

        [$table, $field] = $parts;
        if (!isset($context[$table]) || !is_array($context[$table])) {
            return null;
        }

        return $context[$table][$field] ?? null;
    }

    private function insertRows(string $table, array $rows): void
    {
        if ($rows === []) {
            return;
        }

        $columns = array_keys($rows[0]);
        $valueGroups = [];
        $params = [];

        foreach ($rows as $rowIndex => $row) {
            $placeholders = [];

            foreach ($columns as $column) {
                $placeholder = ':' . $column . '_' . $rowIndex;
                $placeholders[] = $placeholder;
                $params[$placeholder] = $row[$column] ?? null;
            }

            $valueGroups[] = '(' . implode(',', $placeholders) . ')';
        }

        $sql = "INSERT INTO `{$table}` (`" . implode('`,`', $columns) . "`) VALUES " . implode(',', $valueGroups);
        $stmt = $this->stageDb->prepare($sql);
        $stmt->execute($params);
    }
}
