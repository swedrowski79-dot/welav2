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

        foreach ($config as $targetTable => $definition) {
            if (!is_array($definition)) {
                throw new RuntimeException("Merge definition for '{$targetTable}' must be an array.");
            }

            $this->mergeTable($targetTable, $definition);
        }
    }

    private function mergeTable(string $targetTable, array $definition): void
    {
        $this->stageDb->exec("TRUNCATE TABLE `{$targetTable}`");

        $baseTable = $definition['base'];
        $baseRows = $this->fetchRows($baseTable);
        $matches = $definition['match'] ?? [];
        $requiredFields = $definition['required_fields'] ?? [];
        $uniqueBy = $definition['unique_by'] ?? [];
        $matchIndexes = $this->buildMatchIndexes($matches);
        $seenUniqueRows = [];
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

            if (!$this->hasRequiredFields($merged, $requiredFields)) {
                continue;
            }

            if ($this->isDuplicateMergedRow($merged, $uniqueBy, $seenUniqueRows)) {
                continue;
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

    private function hasRequiredFields(array $row, array $requiredFields): bool
    {
        foreach ($requiredFields as $field) {
            if (!is_string($field) || $field === '') {
                throw new RuntimeException('Merge required_fields must contain only non-empty field names.');
            }

            $value = $row[$field] ?? null;
            if ($value === null || $value === '') {
                return false;
            }
        }

        return true;
    }

    private function isDuplicateMergedRow(array $row, array $uniqueBy, array &$seenUniqueRows): bool
    {
        if ($uniqueBy === []) {
            return false;
        }

        $values = [];

        foreach ($uniqueBy as $field) {
            if (!is_string($field) || $field === '') {
                throw new RuntimeException('Merge unique_by must contain only non-empty field names.');
            }

            $values[$field] = $row[$field] ?? null;
        }

        $key = json_encode($values, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        if (!is_string($key)) {
            throw new RuntimeException('Merge unique_by key could not be serialized.');
        }

        if (isset($seenUniqueRows[$key])) {
            return true;
        }

        $seenUniqueRows[$key] = true;

        return false;
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
