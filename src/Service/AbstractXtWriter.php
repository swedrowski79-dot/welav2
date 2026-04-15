<?php

declare(strict_types=1);

abstract class AbstractXtWriter implements XtQueueWriter
{
    protected array $writeConfig;
    protected WelaApiClient $client;

    private array $lookupCaches = [];

    public function __construct(array $sourcesConfig, array $xtWriteConfig)
    {
        $connection = $sourcesConfig['sources']['xt']['connection'] ?? [];
        $this->client = new WelaApiClient(
            (string) ($connection['url'] ?? ''),
            (string) ($connection['key'] ?? '')
        );
        $this->writeConfig = $xtWriteConfig['write'] ?? [];
    }

    protected function requireConfiguredClient(string $message): void
    {
        if (!$this->client->isConfigured()) {
            throw new RuntimeException($message);
        }
    }

    protected function definition(string $key): array
    {
        $definition = $this->writeConfig[$key] ?? null;

        if (!is_array($definition)) {
            throw new RuntimeException("XT-Write-Definition '{$key}' ist nicht vorhanden.");
        }

        return $definition;
    }

    protected function resolveColumns(array $columns, array $sources, bool $isInsert, array $skipColumns = []): array
    {
        $resolved = [];
        $skip = array_fill_keys(array_map('strval', $skipColumns), true);

        foreach ($columns as $targetColumn => $expression) {
            $targetColumn = (string) $targetColumn;

            if (isset($skip[$targetColumn])) {
                continue;
            }

            $value = $this->resolveExpression((string) $expression, $sources, $isInsert);

            if ($value === '__SKIP__') {
                continue;
            }

            $resolved[$targetColumn] = $value;
        }

        return $resolved;
    }

    protected function resolveExpression(string $expression, array $sources, bool $isInsert): mixed
    {
        if (preg_match('/^(?<source>[a-z0-9_]+)\.(?<field>[a-z0-9_]+)$/i', $expression, $matches) === 1) {
            return $this->sourceValue($sources, strtolower($matches['source']), $matches['field']);
        }

        if (str_starts_with($expression, 'context:')) {
            return $this->sourceValue($sources, 'context', substr($expression, 8));
        }

        if (str_starts_with($expression, 'const:')) {
            return $this->parseConst(substr($expression, 6));
        }

        if ($expression === 'calc:now') {
            return gmdate('Y-m-d H:i:s');
        }

        if ($expression === 'calc:now_if_insert') {
            return $isInsert ? gmdate('Y-m-d H:i:s') : '__SKIP__';
        }

        if (preg_match('/^ref:(?<table>[a-z0-9_]+)\.(?<field>[a-z0-9_]+) by (?<lookup>[a-z0-9_]+)=(?<source>[a-z0-9_]+)\.(?<source_field>[a-z0-9_]+)$/i', $expression, $matches) === 1) {
            return $this->resolveReference(
                strtolower($matches['table']),
                $matches['field'],
                $matches['lookup'],
                strtolower($matches['source']),
                $matches['source_field'],
                $sources
            );
        }

        return $this->resolveCalculatedExpression($expression, $sources, $isInsert);
    }

    protected function resolveCalculatedExpression(string $expression, array $sources, bool $isInsert): mixed
    {
        throw new RuntimeException("Nicht unterstuetzter XT-Ausdruck: {$expression}");
    }

    protected function resolveReference(
        string $table,
        string $field,
        string $lookupField,
        string $sourceName,
        string $sourceField,
        array $sources
    ): mixed {
        $lookupValue = trim((string) ($this->sourceValue($sources, $sourceName, $sourceField) ?? ''));

        if ($lookupValue === '') {
            throw new PermanentExportQueueException("XT-Referenzfeld '{$sourceName}.{$sourceField}' fehlt im Queue-Payload.");
        }

        $map = $this->lookupMap($table, $lookupField, $field);

        if (!array_key_exists($lookupValue, $map)) {
            throw new PermanentExportQueueException("XT-Referenz fuer '{$table}' mit {$lookupField} '{$lookupValue}' wurde nicht gefunden.");
        }

        return $map[$lookupValue];
    }

    protected function lookupMap(string $table, string $keyField, string $valueField): array
    {
        $cacheKey = strtolower($table . '|' . $keyField . '|' . $valueField);

        if (!isset($this->lookupCaches[$cacheKey])) {
            $this->lookupCaches[$cacheKey] = $this->client->lookupMap($table, $keyField, $valueField);
        }

        return $this->lookupCaches[$cacheKey];
    }

    protected function storeLookupValue(string $table, string $keyField, string $valueField, string $key, mixed $value): void
    {
        $cacheKey = strtolower($table . '|' . $keyField . '|' . $valueField);

        if (!isset($this->lookupCaches[$cacheKey])) {
            $this->lookupCaches[$cacheKey] = [];
        }

        $this->lookupCaches[$cacheKey][$key] = $value;
    }

    protected function sourceValue(array $sources, string $sourceName, string $field): mixed
    {
        $source = $sources[$sourceName] ?? null;

        if (!is_array($source)) {
            return null;
        }

        return $source[$field] ?? null;
    }

    protected function parseConst(string $value): mixed
    {
        $trimmed = trim($value);

        if (preg_match('/^"(.*)"$/', $trimmed, $matches) === 1) {
            return stripcslashes($matches[1]);
        }

        if (preg_match("/^'(.*)'$/", $trimmed, $matches) === 1) {
            return stripcslashes($matches[1]);
        }

        if (is_numeric($trimmed)) {
            return str_contains($trimmed, '.') ? (float) $trimmed : (int) $trimmed;
        }

        return $trimmed;
    }

    protected function isDeletedPayload(array $payload): bool
    {
        return ($payload['deleted'] ?? null) === 1
            || ($payload['deleted'] ?? null) === '1'
            || (($payload['data']['deleted'] ?? null) === 1)
            || (($payload['data']['deleted'] ?? null) === '1');
    }

    protected function entityIdentityValue(array $definition, array $record, string $sourceName = 'stage'): string
    {
        $sourceField = (string) ($definition['identity']['source_field'] ?? '');
        $value = trim((string) ($record[$sourceField] ?? ''));

        if ($value === '') {
            throw new PermanentExportQueueException("XT-Entity-Identitaet fuer '{$sourceName}' fehlt im Queue-Payload.");
        }

        return $value;
    }

    protected function injectQueueIdentity(array $entry, array $record, array $definition): array
    {
        $sourceField = (string) ($definition['identity']['source_field'] ?? '');
        $queueEntityId = trim((string) ($entry['entity_id'] ?? ''));

        if ($queueEntityId !== '' && $sourceField !== '' && !array_key_exists($sourceField, $record)) {
            $record[$sourceField] = $queueEntityId;
        }

        return $record;
    }
}
