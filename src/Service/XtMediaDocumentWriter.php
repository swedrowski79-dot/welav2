<?php

declare(strict_types=1);

final class XtMediaDocumentWriter extends AbstractXtWriter
{
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

        $this->requireConfiguredClient('XT-API URL oder API-Key fehlt fuer Media-/Document-Export.');

        $definitions = $this->definitionsForEntityType($entityType);
        $entityDefinition = $definitions['entity'];
        $relationDefinition = $definitions['relation'];
        $stageRow = $this->injectQueueIdentity($entry, $stageRow, $entityDefinition);

        if ($this->isDeletedPayload($payload)) {
            $this->deleteRelation($entityDefinition, $relationDefinition, $stageRow);

            return;
        }

        $this->assertRelationPrerequisites($relationDefinition, $stageRow);

        $identityValue = $this->entityIdentityValue($entityDefinition, $stageRow);
        $entityMap = $this->lookupMap('xt_media', 'external_id', 'id');
        $existingPrimaryKey = $entityMap[$identityValue] ?? null;
        $entityColumns = $this->resolveColumns(
            (array) ($entityDefinition['columns'] ?? []),
            ['stage' => $stageRow],
            $existingPrimaryKey === null
        );
        $entityIdentity = [
            (string) ($entityDefinition['identity']['target_field'] ?? 'external_id') => $identityValue,
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

        $this->storeLookupValue('xt_media', 'external_id', 'id', $identityValue, $primaryKeyValue);

        $relationColumns = $this->resolveColumns(
            (array) ($relationDefinition['columns'] ?? []),
            ['stage' => $stageRow],
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

        return [
            'entity' => $this->definition($keys['entity']),
            'relation' => $this->definition($keys['relation']),
        ];
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
        $mediaId = $this->lookupMap('xt_media', 'external_id', 'id')[$entityIdentityValue] ?? null;

        if ($mediaId === null) {
            return;
        }

        $deleteColumns = (array) ($relationDefinition['delete_match_columns'] ?? []);
        if ($deleteColumns === []) {
            throw new RuntimeException('XT-Relation enthaelt keine Delete-Match-Spalten.');
        }

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

            $where[$column] = $this->resolveExpression($expression, ['stage' => $stageRow], false);
        }

        $this->client->deleteRows((string) ($relationDefinition['table'] ?? ''), $where);
    }

    private function assertRelationPrerequisites(array $relationDefinition, array $stageRow): void
    {
        $linkExpression = $relationDefinition['columns']['link_id'] ?? null;

        if (!is_string($linkExpression)) {
            throw new RuntimeException('XT-Relation enthaelt keine Produkt-Referenzspalte.');
        }

        $this->resolveExpression($linkExpression, ['stage' => $stageRow], false);
    }
}
