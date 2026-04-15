<?php

declare(strict_types=1);

final class XtMediaDocumentWriter
{
    private array $writeConfig;
    private WelaApiClient $client;
    private ?array $productIdMap = null;
    private ?array $mediaIdMap = null;

    public function __construct(array $sourcesConfig, array $xtWriteConfig)
    {
        $connection = $sourcesConfig['sources']['xt']['connection'] ?? [];
        $this->client = new WelaApiClient(
            (string) ($connection['url'] ?? ''),
            (string) ($connection['key'] ?? '')
        );
        $this->writeConfig = $xtWriteConfig['write'] ?? [];
    }

    public function supports(string $entityType): bool
    {
        return in_array($entityType, ['media', 'document'], true);
    }

    public function write(string $entityType, array $entry, array $payload): void
    {
        if (!$this->supports($entityType)) {
            return;
        }

        $stageRow = $payload['data'] ?? null;
        if (!is_array($stageRow)) {
            throw new PermanentExportQueueException('Queue-Payload enthaelt keine gueltigen Stage-Daten.');
        }

        if (!$this->client->isConfigured()) {
            throw new RuntimeException('XT-API URL oder API-Key fehlt fuer Media-/Document-Export.');
        }

        $definitions = $this->definitionsForEntityType($entityType);
        $entityDefinition = $definitions['entity'];
        $relationDefinition = $definitions['relation'];
        $identitySourceField = $this->entityIdentitySourceField($entityDefinition);
        $queueEntityId = trim((string) ($entry['entity_id'] ?? ''));

        if ($queueEntityId !== '' && !array_key_exists($identitySourceField, $stageRow)) {
            $stageRow[$identitySourceField] = $queueEntityId;
        }

        $this->ensureReferenceMaps();

        if ($this->isDeletedPayload($payload)) {
            $this->deleteRelation($entityDefinition, $relationDefinition, $stageRow);

            return;
        }

        $this->assertRelationPrerequisites($relationDefinition, $stageRow);

        $identityValue = $this->entityIdentityValue($entityDefinition, $stageRow);
        $existingPrimaryKey = $this->mediaIdMap[$identityValue] ?? null;
        $entityColumns = $this->resolveColumns(
            (array) ($entityDefinition['columns'] ?? []),
            $stageRow,
            $existingPrimaryKey === null
        );
        $entityIdentity = [
            (string) $entityDefinition['identity']['target_field'] => $identityValue,
        ];

        $entityResult = $this->client->upsertRow(
            (string) ($entityDefinition['table'] ?? ''),
            $entityIdentity,
            $entityColumns,
            (string) ($entityDefinition['primary_key'] ?? 'id')
        );

        $primaryKeyValue = $entityResult['primary_key_value'] ?? null;
        if (!is_int($primaryKeyValue) && !is_string($primaryKeyValue)) {
            throw new RuntimeException('XT-API lieferte keine gueltige Media-ID zurueck.');
        }

        $this->mediaIdMap[$identityValue] = $primaryKeyValue;

        $relationColumns = $this->resolveColumns(
            (array) ($relationDefinition['columns'] ?? []),
            $stageRow,
            false
        );
        $relationIdentity = $this->extractIdentity(
            $relationColumns,
            (array) ($relationDefinition['identity_columns'] ?? [])
        );

        $this->client->upsertRow(
            (string) ($relationDefinition['table'] ?? ''),
            $relationIdentity,
            $relationColumns,
            'ml_id'
        );
    }

    private function definitionsForEntityType(string $entityType): array
    {
        $map = [
            'media' => [
                'entity' => 'xt_media',
                'relation' => 'xt_media_link_images',
            ],
            'document' => [
                'entity' => 'xt_media_documents',
                'relation' => 'xt_media_link_documents',
            ],
        ];

        $keys = $map[$entityType] ?? null;
        if (!is_array($keys)) {
            throw new RuntimeException("Keine XT-Write-Definition fuer Entity-Type '{$entityType}' vorhanden.");
        }

        $entityDefinition = $this->writeConfig[$keys['entity']] ?? null;
        $relationDefinition = $this->writeConfig[$keys['relation']] ?? null;

        if (!is_array($entityDefinition) || !is_array($relationDefinition)) {
            throw new RuntimeException("XT-Write-Definition fuer Entity-Type '{$entityType}' ist unvollstaendig.");
        }

        return [
            'entity' => $entityDefinition,
            'relation' => $relationDefinition,
        ];
    }

    private function ensureReferenceMaps(): void
    {
        if ($this->productIdMap === null) {
            $this->productIdMap = $this->client->lookupMap('xt_products', 'external_id', 'products_id');
        }

        if ($this->mediaIdMap === null) {
            $this->mediaIdMap = $this->client->lookupMap('xt_media', 'external_id', 'id');
        }
    }

    private function entityIdentityValue(array $definition, array $stageRow): string
    {
        $sourceField = (string) ($definition['identity']['source_field'] ?? '');
        $value = trim((string) ($stageRow[$sourceField] ?? ''));

        if ($value === '') {
            throw new PermanentExportQueueException('XT-Entity-Identitaet fehlt im Queue-Payload.');
        }

        return $value;
    }

    private function resolveColumns(array $columns, array $stageRow, bool $isInsert): array
    {
        $resolved = [];

        foreach ($columns as $targetColumn => $expression) {
            $value = $this->resolveExpression((string) $expression, $stageRow, $isInsert);

            if ($value === '__SKIP__') {
                continue;
            }

            $resolved[(string) $targetColumn] = $value;
        }

        return $resolved;
    }

    private function resolveExpression(string $expression, array $stageRow, bool $isInsert): mixed
    {
        if (str_starts_with($expression, 'stage.')) {
            return $stageRow[substr($expression, 6)] ?? null;
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

        if (preg_match('/^ref:(?<table>[a-z0-9_]+)\.(?<field>[a-z0-9_]+) by (?<lookup>[a-z0-9_]+)=stage\.(?<stage>[a-z0-9_]+)$/i', $expression, $matches)) {
            return $this->resolveReference(
                strtolower($matches['table']),
                $matches['field'],
                $matches['lookup'],
                $matches['stage'],
                $stageRow
            );
        }

        throw new RuntimeException("Nicht unterstuetzter XT-Ausdruck: {$expression}");
    }

    private function resolveReference(
        string $table,
        string $field,
        string $lookupField,
        string $stageField,
        array $stageRow
    ): mixed {
        $lookupValue = trim((string) ($stageRow[$stageField] ?? ''));
        if ($lookupValue === '') {
            throw new PermanentExportQueueException("XT-Referenzfeld '{$stageField}' fehlt im Queue-Payload.");
        }

        $map = match ($table) {
            'xt_products' => $this->productIdMap,
            'xt_media' => $this->mediaIdMap,
            default => throw new RuntimeException("XT-Referenztabelle '{$table}' wird nicht unterstuetzt."),
        };

        if ($lookupField !== 'external_id') {
            throw new RuntimeException("XT-Lookup-Feld '{$lookupField}' wird nicht unterstuetzt.");
        }

        if (($table === 'xt_products' && $field !== 'products_id') || ($table === 'xt_media' && $field !== 'id')) {
            throw new RuntimeException("XT-Referenzfeld '{$table}.{$field}' wird nicht unterstuetzt.");
        }

        if (!is_array($map) || !array_key_exists($lookupValue, $map)) {
            throw new PermanentExportQueueException("XT-Referenz fuer '{$table}' mit external_id '{$lookupValue}' wurde nicht gefunden.");
        }

        return $map[$lookupValue];
    }

    private function parseConst(string $value): mixed
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

    private function extractIdentity(array $columns, array $identityColumns): array
    {
        if ($identityColumns === []) {
            throw new RuntimeException('XT-Relation enthaelt keine Identity-Spalten.');
        }

        $identity = [];

        foreach ($identityColumns as $column) {
            $column = (string) $column;

            if (!array_key_exists($column, $columns)) {
                throw new RuntimeException("XT-Relation-Identity-Spalte '{$column}' fehlt.");
            }

            $identity[$column] = $columns[$column];
        }

        return $identity;
    }

    private function deleteRelation(array $entityDefinition, array $relationDefinition, array $stageRow): void
    {
        $entityIdentityValue = $this->entityIdentityValue($entityDefinition, $stageRow);
        $mediaId = $this->mediaIdMap[$entityIdentityValue] ?? null;

        if ($mediaId === null) {
            return;
        }

        $deleteColumns = (array) ($relationDefinition['delete_match_columns'] ?? []);
        if ($deleteColumns === []) {
            throw new RuntimeException('XT-Relation enthaelt keine Delete-Match-Spalten.');
        }

        $stageRow[$this->entityIdentitySourceField($entityDefinition)] = $entityIdentityValue;
        $where = [];

        foreach ($deleteColumns as $column) {
            $column = (string) $column;
            $expression = $relationDefinition['columns'][$column] ?? null;

            if (!is_string($expression)) {
                throw new RuntimeException("XT-Delete-Spalte '{$column}' fehlt in der Relation.");
            }

            if ($column === 'm_id') {
                $where[$column] = $mediaId;
                continue;
            }

            $where[$column] = $this->resolveExpression($expression, $stageRow, false);
        }

        $this->client->deleteRows((string) ($relationDefinition['table'] ?? ''), $where);
    }

    private function entityIdentitySourceField(array $definition): string
    {
        return (string) ($definition['identity']['source_field'] ?? '');
    }

    private function isDeletedPayload(array $payload): bool
    {
        return ($payload['deleted'] ?? null) === 1
            || ($payload['deleted'] ?? null) === '1'
            || (($payload['data']['deleted'] ?? null) === 1)
            || (($payload['data']['deleted'] ?? null) === '1');
    }

    private function assertRelationPrerequisites(array $relationDefinition, array $stageRow): void
    {
        $linkExpression = $relationDefinition['columns']['link_id'] ?? null;

        if (!is_string($linkExpression)) {
            throw new RuntimeException('XT-Relation enthaelt keine Produkt-Referenzspalte.');
        }

        $this->resolveExpression($linkExpression, $stageRow, false);
    }
}
