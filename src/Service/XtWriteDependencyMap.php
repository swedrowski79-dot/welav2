<?php

declare(strict_types=1);

final class XtWriteDependencyMap
{
    public static function tableDefinitions(array $xtWriteConfig): array
    {
        $definitions = $xtWriteConfig['write'] ?? [];
        $tables = [];

        foreach ($definitions as $configKey => $definition) {
            if (!is_string($configKey) || !is_array($definition)) {
                continue;
            }

            $table = trim((string) ($definition['table'] ?? ''));
            if ($table === '') {
                continue;
            }

            $tables[$table] ??= [
                'table' => $table,
                'mirror_table' => self::mirrorTableName($table),
                'primary_key' => [],
                'fields' => [],
                'config_keys' => [],
            ];

            $tables[$table]['primary_key'] = self::mergeValues(
                $tables[$table]['primary_key'],
                self::primaryKeyFields($definition)
            );
            $tables[$table]['fields'] = self::mergeValues(
                $tables[$table]['fields'],
                self::fieldsForDefinition($definition)
            );
            $tables[$table]['config_keys'][] = $configKey;
        }

        foreach ($tables as &$table) {
            sort($table['primary_key']);
            $table['fields'] = self::mergeValues($table['primary_key'], $table['fields']);
            sort($table['config_keys']);
        }
        unset($table);

        ksort($tables);

        return $tables;
    }

    public static function entityDependencies(array $xtWriteConfig): array
    {
        $definitions = $xtWriteConfig['write'] ?? [];
        $entities = [];

        foreach ($definitions as $configKey => $definition) {
            if (!is_string($configKey) || !is_array($definition)) {
                continue;
            }

            $table = trim((string) ($definition['table'] ?? ''));
            if ($table === '') {
                continue;
            }

            $entityType = self::entityTypeForConfigKey($configKey);
            $entities[$entityType] ??= [];
            $entities[$entityType][$configKey] = [
                'table' => $table,
                'fields' => self::fieldsForDefinition($definition),
                'references' => self::referenceTables($definition),
            ];
        }

        ksort($entities);

        return $entities;
    }

    private static function fieldsForDefinition(array $definition): array
    {
        $fields = [];

        $fields = self::mergeValues($fields, self::collectColumnKeys($definition['columns'] ?? []));
        $fields = self::mergeValues($fields, self::collectLanguageColumnKeys($definition['columns_by_language'] ?? []));
        $fields = self::mergeValues($fields, self::identityFields($definition['identity'] ?? []));
        $fields = self::mergeValues($fields, self::normalizeFieldList($definition['identity_columns'] ?? []));
        $fields = self::mergeValues($fields, self::normalizeFieldList($definition['delete_match_columns'] ?? []));
        $fields = self::mergeValues($fields, self::normalizeFieldList($definition['replace_by'] ?? []));
        $fields = self::mergeValues($fields, self::normalizeFieldList($definition['primary_key'] ?? []));

        return $fields;
    }

    private static function primaryKeyFields(array $definition): array
    {
        $primaryKey = self::normalizeFieldList($definition['primary_key'] ?? []);
        if ($primaryKey !== []) {
            return $primaryKey;
        }

        $identityColumns = self::normalizeFieldList($definition['identity_columns'] ?? []);
        if ($identityColumns !== []) {
            return $identityColumns;
        }

        return self::identityFields($definition['identity'] ?? []);
    }

    private static function identityFields(mixed $identity): array
    {
        if (!is_array($identity)) {
            return [];
        }

        $fields = [];

        foreach ($identity as $field => $expression) {
            if (!is_string($field) || $field === '') {
                continue;
            }

            if (in_array($field, ['target_field', 'source_field'], true)) {
                continue;
            }

            $fields[] = $field;
        }

        return self::normalizeFieldList($fields);
    }

    private static function collectColumnKeys(mixed $columns): array
    {
        if (!is_array($columns)) {
            return [];
        }

        $keys = [];
        foreach ($columns as $column => $expression) {
            if (is_string($column) && $column !== '') {
                $keys[] = $column;
            }
        }

        return self::normalizeFieldList($keys);
    }

    private static function collectLanguageColumnKeys(mixed $columnsByLanguage): array
    {
        if (!is_array($columnsByLanguage)) {
            return [];
        }

        $keys = [];
        foreach ($columnsByLanguage as $columns) {
            $keys = self::mergeValues($keys, self::collectColumnKeys($columns));
        }

        return $keys;
    }

    private static function normalizeFieldList(mixed $fields): array
    {
        if (!is_array($fields)) {
            $fields = [$fields];
        }

        $normalized = [];

        foreach ($fields as $field) {
            if (!is_string($field)) {
                continue;
            }

            $field = trim($field);
            if ($field === '') {
                continue;
            }

            $normalized[] = $field;
        }

        return self::mergeValues([], $normalized);
    }

    private static function mergeValues(array $left, array $right): array
    {
        $merged = [];

        foreach (array_merge($left, $right) as $value) {
            if (!is_string($value) || $value === '') {
                continue;
            }

            $merged[$value] = true;
        }

        return array_keys($merged);
    }

    private static function mirrorTableName(string $table): string
    {
        return 'xt_mirror_' . preg_replace('/^xt_/', '', $table);
    }

    private static function entityTypeForConfigKey(string $configKey): string
    {
        return match (true) {
            str_starts_with($configKey, 'xt_categories') => 'category',
            str_starts_with($configKey, 'xt_seo_url_categories') => 'seo_category',
            str_starts_with($configKey, 'xt_seo_url_products') => 'seo_product',
            str_starts_with($configKey, 'xt_media_documents'),
            str_starts_with($configKey, 'xt_media_link_documents') => 'document',
            str_starts_with($configKey, 'xt_media'),
            str_starts_with($configKey, 'xt_media_link_images') => 'media',
            default => 'product',
        };
    }

    private static function referenceTables(array $definition): array
    {
        $tables = [];

        foreach (self::collectExpressions($definition) as $expression) {
            if (!preg_match('/^ref:(?<table>[a-z0-9_]+)\./i', $expression, $matches)) {
                continue;
            }

            $tables[] = strtolower($matches['table']);
        }

        return self::mergeValues([], $tables);
    }

    private static function collectExpressions(mixed $value): array
    {
        if (is_string($value)) {
            return [$value];
        }

        if (!is_array($value)) {
            return [];
        }

        $expressions = [];
        foreach ($value as $nested) {
            $expressions = array_merge($expressions, self::collectExpressions($nested));
        }

        return $expressions;
    }
}
