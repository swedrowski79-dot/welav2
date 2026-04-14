<?php

class MergeService
{
    public function __construct(
        private PDO $stageDb,
        private array $mergeConfig
    ) {
    }

    public function run(): void
    {
        $config = $this->mergeConfig['merge'];

        $this->mergeTable('stage_products', $config['stage_products']);
        $this->mergeTable('stage_product_translations', $config['stage_product_translations']);
        $this->mergeTable('stage_categories', $config['stage_categories']);
        $this->mergeTable('stage_category_translations', $config['stage_category_translations']);
    }

    private function mergeTable(string $targetTable, array $definition): void
    {
        $preservedRows = $this->loadPreservedRows($targetTable, $definition['preserve'] ?? null);
        $this->stageDb->exec("TRUNCATE TABLE `{$targetTable}`");

        $baseTable = $definition['base'];
        $baseRows = $this->fetchRows($baseTable);
        $matches = $definition['match'] ?? [];

        foreach ($baseRows as $baseRow) {
            $context = [$baseTable => $baseRow];

            foreach ($matches as $matchTable => $rule) {
                $localValue = $baseRow[$rule['local']] ?? null;
                $context[$matchTable] = $this->findMatch($matchTable, $rule['foreign'], $localValue);
            }

            $merged = [];
            foreach ($definition['fields'] as $field => $fieldDef) {
                $merged[$field] = $this->resolveField($fieldDef, $context);
            }

            $merged = $this->applyPreservedFields($merged, $preservedRows, $definition['preserve'] ?? null);
            $this->insertRow($targetTable, $merged);
        }
    }

    private function loadPreservedRows(string $targetTable, mixed $preserveConfig): array
    {
        if (!is_array($preserveConfig)) {
            return [];
        }

        $keyField = $preserveConfig['key_field'] ?? null;
        $fields = $preserveConfig['fields'] ?? null;

        if (!is_string($keyField) || $keyField === '' || !is_array($fields) || $fields === []) {
            return [];
        }

        $selectFields = array_merge([$keyField], $fields);
        $sql = "SELECT `" . implode("`,`", $selectFields) . "` FROM `{$targetTable}`";
        $stmt = $this->stageDb->query($sql);

        $preserved = [];
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $key = $row[$keyField] ?? null;
            if ($key === null || $key === '') {
                continue;
            }

            $preserved[(string) $key] = $row;
        }

        return $preserved;
    }

    private function fetchRows(string $table): array
    {
        $stmt = $this->stageDb->query("SELECT * FROM `{$table}`");
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    private function findMatch(string $table, string $foreignField, mixed $value): ?array
    {
        if ($value === null || $value === '') {
            return null;
        }

        $stmt = $this->stageDb->prepare("SELECT * FROM `{$table}` WHERE `{$foreignField}` = :value LIMIT 1");
        $stmt->execute(['value' => $value]);

        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row ?: null;
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

    private function applyPreservedFields(array $merged, array $preservedRows, mixed $preserveConfig): array
    {
        if (!is_array($preserveConfig)) {
            return $merged;
        }

        $keyField = $preserveConfig['key_field'] ?? null;
        $fields = $preserveConfig['fields'] ?? null;

        if (!is_string($keyField) || $keyField === '' || !is_array($fields) || $fields === []) {
            return $merged;
        }

        $key = $merged[$keyField] ?? null;
        if ($key === null || $key === '') {
            return $merged;
        }

        $preserved = $preservedRows[(string) $key] ?? [];

        foreach ($fields as $field) {
            $merged[$field] = $preserved[$field] ?? null;
        }

        return $merged;
    }

    private function insertRow(string $table, array $data): void
    {
        $columns = array_keys($data);
        $placeholders = array_map(fn($c) => ':' . $c, $columns);

        $sql = "INSERT INTO `{$table}` (`" . implode('`,`', $columns) . "`) VALUES (" . implode(',', $placeholders) . ")";
        $stmt = $this->stageDb->prepare($sql);
        foreach ($data as $key => $value) {
            $stmt->bindValue(':' . $key, $value);
        }
        $stmt->execute();
    }
}
