<?php

class ExpandService
{
    private const NULL_SCOPE_KEY = '__NULL_SCOPE__';

    private int $insertBatchSize;

    public function __construct(
        private PDO $stageDb,
        private array $expandConfig,
        private ?SyncMonitor $monitor = null,
        private ?int $runId = null
    ) {
        $definitions = $this->expandConfig['expand'] ?? [];
        $this->insertBatchSize = max(1, (int) ($definitions['insert_batch_size'] ?? 500));
    }

    public function run(): array
    {
        $definitions = $this->expandConfig['expand'] ?? null;

        if (!is_array($definitions) || $definitions === []) {
            throw new RuntimeException('Expand config is missing or empty.');
        }

        unset($definitions['insert_batch_size']);

        $startedAt = microtime(true);
        $stats = [
            'definitions' => [],
            'definitions_count' => 0,
            'source_rows' => 0,
            'written_rows' => 0,
            'insert_batches' => 0,
            'duration_seconds' => 0.0,
        ];

        foreach ($definitions as $definitionName => $definition) {
            if (!is_array($definition)) {
                throw new RuntimeException("Expand definition '{$definitionName}' must be an array.");
            }

            $definitionStats = $this->expandDefinition($definitionName, $definition);
            $stats['definitions'][$definitionName] = $definitionStats;
            $stats['definitions_count']++;
            $stats['source_rows'] += (int) ($definitionStats['source_rows'] ?? 0);
            $stats['written_rows'] += (int) ($definitionStats['written_rows'] ?? 0);
            $stats['insert_batches'] += (int) ($definitionStats['insert_batches'] ?? 0);
        }

        $stats['duration_seconds'] = $this->roundDuration(microtime(true) - $startedAt);

        if ($this->monitor !== null) {
            $this->monitor->log($this->runId, 'info', 'Expand abgeschlossen.', [
                'definitions_count' => $stats['definitions_count'],
                'source_rows' => $stats['source_rows'],
                'written_rows' => $stats['written_rows'],
                'insert_batches' => $stats['insert_batches'],
                'duration_seconds' => $stats['duration_seconds'],
            ]);
        }

        return $stats;
    }

    private function expandDefinition(string $definitionName, array $definition): array
    {
        $mode = $definition['mode'] ?? 'attribute_slots';

        if (!is_string($mode) || $mode === '') {
            throw new RuntimeException("Expand definition '{$definitionName}' is missing a valid mode.");
        }

        if ($mode === 'attribute_slots') {
            return $this->expandAttributeSlots($definitionName, $definition);
        }

        if ($mode === 'attribute_rows') {
            return $this->expandAttributeRows($definitionName, $definition);
        }

        if ($mode === 'media_slots') {
            return $this->expandMediaSlots($definitionName, $definition);
        }

        throw new RuntimeException("Expand definition '{$definitionName}' uses an unsupported mode '{$mode}'.");
    }

    private function expandAttributeSlots(string $definitionName, array $definition): array
    {
        $sourceTable = $definition['source'] ?? null;
        $targetTable = $definition['target'] ?? null;
        $slots = $definition['slots'] ?? null;

        if (!is_string($sourceTable) || $sourceTable === '') {
            throw new RuntimeException("Expand definition '{$definitionName}' is missing a valid source table.");
        }

        if (!is_string($targetTable) || $targetTable === '') {
            throw new RuntimeException("Expand definition '{$definitionName}' is missing a valid target table.");
        }

        if (!is_array($slots) || $slots === []) {
            throw new RuntimeException("Expand definition '{$definitionName}' is missing slots.");
        }

        $sourceColumns = ['afs_artikel_id', 'sku', 'language_code', 'source_directory'];
        foreach ($slots as $slotIndex => $slot) {
            if (!is_array($slot)) {
                throw new RuntimeException(
                    "Expand definition '{$definitionName}' slot at index {$slotIndex} must be an array."
                );
            }

            $nameField = $slot['name'] ?? null;
            $valueField = $slot['value'] ?? null;
            $sortOrder = $slot['sort'] ?? null;

            if (!is_string($nameField) || $nameField === '') {
                throw new RuntimeException(
                    "Expand definition '{$definitionName}' slot at index {$slotIndex} is missing a valid name field."
                );
            }

            if (!is_string($valueField) || $valueField === '') {
                throw new RuntimeException(
                    "Expand definition '{$definitionName}' slot at index {$slotIndex} is missing a valid value field."
                );
            }

            if (!is_int($sortOrder) && !ctype_digit((string) $sortOrder)) {
                throw new RuntimeException(
                    "Expand definition '{$definitionName}' slot at index {$slotIndex} is missing a valid sort value."
                );
            }

            $sourceColumns[] = $nameField;
            $sourceColumns[] = $valueField;
        }

        $startedAt = microtime(true);
        $sourceRows = 0;
        $writtenRows = 0;
        $insertBatches = 0;
        $deletedRows = 0;
        $targetColumns = [
            'afs_artikel_id',
            'sku',
            'language_code',
            'sort_order',
            'attribute_name',
            'attribute_value',
            'source_directory',
        ];
        $rebuiltRowsByScope = [];

        $select = $this->stageDb->query(
            sprintf(
                'SELECT %s FROM %s',
                $this->buildSelectColumnList($sourceColumns),
                $this->quoteIdentifier($sourceTable)
            )
        );

        while ($row = $select->fetch(PDO::FETCH_ASSOC)) {
            $sourceRows++;
            $scopeKey = $this->buildScopeKey($row['afs_artikel_id'] ?? null);
            $rebuiltRowsByScope[$scopeKey] ??= [];

            foreach ($slots as $slotIndex => $slot) {
                $nameField = $slot['name'] ?? null;
                $valueField = $slot['value'] ?? null;
                $sortOrder = $slot['sort'] ?? null;

                $attributeName = $this->normalizeString($row[$nameField] ?? null);
                $attributeValue = $this->normalizeString($row[$valueField] ?? null);

                if ($attributeName === null || $attributeValue === null) {
                    continue;
                }

                $rebuiltRowsByScope[$scopeKey][] = [
                    'afs_artikel_id' => $row['afs_artikel_id'] ?? null,
                    'sku' => $row['sku'] ?? null,
                    'language_code' => $row['language_code'] ?? null,
                    'sort_order' => (int) $sortOrder,
                    'attribute_name' => $attributeName,
                    'attribute_value' => $attributeValue,
                    'source_directory' => $row['source_directory'] ?? null,
                ];
            }
        }

        $existingRowsByScope = $this->fetchTargetRowsByScope($targetTable, $targetColumns, 'afs_artikel_id');
        $scopeStats = $this->applyIncrementalRows(
            $targetTable,
            'afs_artikel_id',
            $targetColumns,
            $rebuiltRowsByScope,
            $existingRowsByScope
        );

        $deletedRows = (int) ($scopeStats['deleted_rows'] ?? 0);
        $writtenRows = (int) ($scopeStats['written_rows'] ?? 0);
        $insertBatches = (int) ($scopeStats['insert_batches'] ?? 0);

        $stats = [
            'mode' => 'attribute_slots',
            'source_table' => $sourceTable,
            'target_table' => $targetTable,
            'source_rows' => $sourceRows,
            'source_products' => count($rebuiltRowsByScope),
            'affected_products' => (int) ($scopeStats['affected_scopes'] ?? 0),
            'unchanged_products' => (int) ($scopeStats['unchanged_scopes'] ?? 0),
            'deleted_rows' => $deletedRows,
            'written_rows' => $writtenRows,
            'insert_batches' => $insertBatches,
            'duration_seconds' => $this->roundDuration(microtime(true) - $startedAt),
        ];

        if ($this->monitor !== null) {
            $this->monitor->log($this->runId, 'info', "Expand-Definition '{$definitionName}' abgeschlossen.", $stats);
        }

        return $stats;
    }

    private function expandAttributeRows(string $definitionName, array $definition): array
    {
        $sourceTable = $definition['source'] ?? null;
        $targetTable = $definition['target'] ?? null;

        if (!is_string($sourceTable) || $sourceTable === '') {
            throw new RuntimeException("Expand definition '{$definitionName}' is missing a valid source table.");
        }

        if (!is_string($targetTable) || $targetTable === '') {
            throw new RuntimeException("Expand definition '{$definitionName}' is missing a valid target table.");
        }

        $startedAt = microtime(true);
        $sourceRows = 0;
        $targetColumns = [
            'afs_artikel_id',
            'sku',
            'language_code',
            'sort_order',
            'attribute_name',
            'attribute_value',
            'source_directory',
        ];
        $rebuiltRowsByScope = [];

        $select = $this->stageDb->query(
            'SELECT `afs_artikel_id`, `sku`, `sort_order`, `language_code_normalized`, `attribute_name`, `attribute_value`, `source_directory`
             FROM ' . $this->quoteIdentifier($sourceTable)
        );

        while ($row = $select->fetch(PDO::FETCH_ASSOC)) {
            $sourceRows++;
            $attributeName = $this->normalizeString($row['attribute_name'] ?? null);
            $attributeValue = $this->normalizeString($row['attribute_value'] ?? null);
            $languageCode = $this->normalizeString($row['language_code_normalized'] ?? null);

            if ($attributeName === null || $attributeValue === null || $languageCode === null) {
                continue;
            }

            $scopeKey = $this->buildScopeKey($row['afs_artikel_id'] ?? null);
            $rebuiltRowsByScope[$scopeKey] ??= [];
            $rebuiltRowsByScope[$scopeKey][] = [
                'afs_artikel_id' => $row['afs_artikel_id'] ?? null,
                'sku' => $row['sku'] ?? null,
                'language_code' => $languageCode,
                'sort_order' => (int) ($row['sort_order'] ?? 0),
                'attribute_name' => $attributeName,
                'attribute_value' => $attributeValue,
                'source_directory' => $row['source_directory'] ?? null,
            ];
        }

        $existingRowsByScope = $this->fetchTargetRowsByScope($targetTable, $targetColumns, 'afs_artikel_id');
        $scopeStats = $this->applyIncrementalRows(
            $targetTable,
            'afs_artikel_id',
            $targetColumns,
            $rebuiltRowsByScope,
            $existingRowsByScope
        );

        return [
            'mode' => 'attribute_rows',
            'source_table' => $sourceTable,
            'target_table' => $targetTable,
            'source_rows' => $sourceRows,
            'source_products' => count($rebuiltRowsByScope),
            'affected_products' => (int) ($scopeStats['affected_scopes'] ?? 0),
            'unchanged_products' => (int) ($scopeStats['unchanged_scopes'] ?? 0),
            'deleted_rows' => (int) ($scopeStats['deleted_rows'] ?? 0),
            'written_rows' => (int) ($scopeStats['written_rows'] ?? 0),
            'insert_batches' => (int) ($scopeStats['insert_batches'] ?? 0),
            'duration_seconds' => $this->roundDuration(microtime(true) - $startedAt),
        ];
    }

    private function expandMediaSlots(string $definitionName, array $definition): array
    {
        $sourceTable = $definition['source'] ?? null;
        $targetTable = $definition['target'] ?? null;
        $slots = $definition['slots'] ?? null;

        if (!is_string($sourceTable) || $sourceTable === '') {
            throw new RuntimeException("Expand definition '{$definitionName}' is missing a valid source table.");
        }

        if (!is_string($targetTable) || $targetTable === '') {
            throw new RuntimeException("Expand definition '{$definitionName}' is missing a valid target table.");
        }

        if (!is_array($slots) || $slots === []) {
            throw new RuntimeException("Expand definition '{$definitionName}' is missing slots.");
        }

        $sourceColumns = ['afs_artikel_id'];
        foreach ($slots as $slotIndex => $slot) {
            if (!is_array($slot)) {
                throw new RuntimeException(
                    "Expand definition '{$definitionName}' slot at index {$slotIndex} must be an array."
                );
            }

            $sourceField = $slot['source'] ?? null;
            $slotName = $slot['slot'] ?? null;
            $sortOrder = $slot['sort'] ?? null;

            if (!is_string($sourceField) || $sourceField === '') {
                throw new RuntimeException(
                    "Expand definition '{$definitionName}' slot at index {$slotIndex} is missing a valid source field."
                );
            }

            if (!is_string($slotName) || $slotName === '') {
                throw new RuntimeException(
                    "Expand definition '{$definitionName}' slot at index {$slotIndex} is missing a valid slot name."
                );
            }

            if (!is_int($sortOrder) && !ctype_digit((string) $sortOrder)) {
                throw new RuntimeException(
                    "Expand definition '{$definitionName}' slot at index {$slotIndex} is missing a valid sort value."
                );
            }

            $sourceColumns[] = $sourceField;
        }

        $startedAt = microtime(true);
        $sourceRows = 0;
        $writtenRows = 0;
        $insertBatches = 0;
        $deletedRows = 0;
        $targetColumns = [
            'media_external_id',
            'afs_artikel_id',
            'source_slot',
            'file_name',
            'path',
            'type',
            'document_type',
            'sort_order',
            'position',
        ];
        $rebuiltRowsByScope = [];

        $select = $this->stageDb->query(
            sprintf(
                'SELECT %s FROM %s',
                $this->buildSelectColumnList($sourceColumns),
                $this->quoteIdentifier($sourceTable)
            )
        );

        while ($row = $select->fetch(PDO::FETCH_ASSOC)) {
            $sourceRows++;
            $afsArtikelId = $row['afs_artikel_id'] ?? null;
            $scopeKey = $this->buildScopeKey($afsArtikelId);
            $rebuiltRowsByScope[$scopeKey] ??= [];

            foreach ($slots as $slotIndex => $slot) {
                $sourceField = $slot['source'] ?? null;
                $slotName = $slot['slot'] ?? null;
                $sortOrder = $slot['sort'] ?? null;

                $path = $this->normalizeString($row[$sourceField] ?? null);

                if ($path === null) {
                    continue;
                }

                if ($afsArtikelId === null || $afsArtikelId === '') {
                    throw new RuntimeException(
                        "Expand definition '{$definitionName}' cannot create media rows without afs_artikel_id."
                    );
                }

                $fileName = $this->extractFileName($path);
                $position = (int) $sortOrder;

                $rebuiltRowsByScope[$scopeKey][] = [
                    'media_external_id' => $this->buildMediaExternalId($afsArtikelId, $slotName),
                    'afs_artikel_id' => $afsArtikelId,
                    'source_slot' => $slotName,
                    'file_name' => $fileName,
                    'path' => $path,
                    'type' => 'images',
                    'document_type' => 'image',
                    'sort_order' => $position,
                    'position' => $position,
                ];
            }
        }

        $existingRowsByScope = $this->fetchTargetRowsByScope($targetTable, $targetColumns, 'afs_artikel_id');
        $scopeStats = $this->applyIncrementalRows(
            $targetTable,
            'afs_artikel_id',
            $targetColumns,
            $rebuiltRowsByScope,
            $existingRowsByScope
        );

        $deletedRows = (int) ($scopeStats['deleted_rows'] ?? 0);
        $writtenRows = (int) ($scopeStats['written_rows'] ?? 0);
        $insertBatches = (int) ($scopeStats['insert_batches'] ?? 0);

        $stats = [
            'mode' => 'media_slots',
            'source_table' => $sourceTable,
            'target_table' => $targetTable,
            'source_rows' => $sourceRows,
            'source_products' => count($rebuiltRowsByScope),
            'affected_products' => (int) ($scopeStats['affected_scopes'] ?? 0),
            'unchanged_products' => (int) ($scopeStats['unchanged_scopes'] ?? 0),
            'deleted_rows' => $deletedRows,
            'written_rows' => $writtenRows,
            'insert_batches' => $insertBatches,
            'duration_seconds' => $this->roundDuration(microtime(true) - $startedAt),
        ];

        if ($this->monitor !== null) {
            $this->monitor->log($this->runId, 'info', "Expand-Definition '{$definitionName}' abgeschlossen.", $stats);
        }

        return $stats;
    }

    private function applyIncrementalRows(
        string $targetTable,
        string $identityField,
        array $targetColumns,
        array $rebuiltRowsByScope,
        array $existingRowsByScope
    ): array {
        $allScopeKeys = array_values(array_unique(array_merge(
            array_keys($rebuiltRowsByScope),
            array_keys($existingRowsByScope)
        )));
        $affectedScopeKeys = [];
        $unchangedScopes = 0;

        foreach ($allScopeKeys as $scopeKey) {
            $rebuiltRows = $rebuiltRowsByScope[$scopeKey] ?? [];
            $existingRows = $existingRowsByScope[$scopeKey] ?? [];

            if ($this->rowsDiffer($rebuiltRows, $existingRows, $targetColumns)) {
                $affectedScopeKeys[] = $scopeKey;
                continue;
            }

            $unchangedScopes++;
        }

        if ($affectedScopeKeys === []) {
            return [
                'affected_scopes' => 0,
                'unchanged_scopes' => $unchangedScopes,
                'deleted_rows' => 0,
                'written_rows' => 0,
                'insert_batches' => 0,
            ];
        }

        $deletedRows = $this->deleteRowsForScopes($targetTable, $identityField, $affectedScopeKeys);
        $rowsToInsert = $this->rowsForAffectedScopes($rebuiltRowsByScope, $affectedScopeKeys);
        $writtenRows = 0;
        $insertBatches = 0;

        foreach (array_chunk($rowsToInsert, $this->insertBatchSize) as $batch) {
            if ($batch === []) {
                continue;
            }

            $writtenRows += $this->insertRows($targetTable, $batch);
            $insertBatches++;
        }

        return [
            'affected_scopes' => count($affectedScopeKeys),
            'unchanged_scopes' => $unchangedScopes,
            'deleted_rows' => $deletedRows,
            'written_rows' => $writtenRows,
            'insert_batches' => $insertBatches,
        ];
    }

    private function fetchTargetRowsByScope(string $table, array $columns, string $identityField): array
    {
        $stmt = $this->stageDb->query(
            sprintf(
                'SELECT %s FROM %s',
                $this->buildSelectColumnList($columns),
                $this->quoteIdentifier($table)
            )
        );

        $rowsByScope = [];
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $scopeKey = $this->buildScopeKey($row[$identityField] ?? null);
            $rowsByScope[$scopeKey] ??= [];
            $rowsByScope[$scopeKey][] = $row;
        }

        return $rowsByScope;
    }

    private function rowsDiffer(array $rebuiltRows, array $existingRows, array $columns): bool
    {
        if (count($rebuiltRows) !== count($existingRows)) {
            return true;
        }

        $rebuiltSignatures = $this->normalizeRowSet($rebuiltRows, $columns);
        $existingSignatures = $this->normalizeRowSet($existingRows, $columns);

        return $rebuiltSignatures !== $existingSignatures;
    }

    private function normalizeRowSet(array $rows, array $columns): array
    {
        $signatures = [];

        foreach ($rows as $row) {
            $normalized = [];

            foreach ($columns as $column) {
                $value = $row[$column] ?? null;
                $normalized[$column] = $value === null ? null : trim((string) $value);
            }

            $signatures[] = json_encode($normalized, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?: '{}';
        }

        sort($signatures);

        return $signatures;
    }

    private function deleteRowsForScopes(string $table, string $identityField, array $scopeKeys): int
    {
        if ($scopeKeys === []) {
            return 0;
        }

        $identityValues = [];
        $hasNullScope = false;

        foreach ($scopeKeys as $scopeKey) {
            if ($scopeKey === self::NULL_SCOPE_KEY) {
                $hasNullScope = true;
                continue;
            }

            $identityValues[] = $scopeKey;
        }

        $whereParts = [];
        $params = [];

        if ($identityValues !== []) {
            $placeholders = [];
            foreach ($identityValues as $index => $identityValue) {
                $placeholder = ':identity_' . $index;
                $placeholders[] = $placeholder;
                $params[$placeholder] = $identityValue;
            }

            $whereParts[] = $this->quoteIdentifier($identityField) . ' IN (' . implode(', ', $placeholders) . ')';
        }

        if ($hasNullScope) {
            $whereParts[] = $this->quoteIdentifier($identityField) . ' IS NULL';
        }

        if ($whereParts === []) {
            return 0;
        }

        $stmt = $this->stageDb->prepare(
            'DELETE FROM ' . $this->quoteIdentifier($table) . ' WHERE ' . implode(' OR ', $whereParts)
        );
        $stmt->execute($params);

        return (int) $stmt->rowCount();
    }

    private function rowsForAffectedScopes(array $rowsByScope, array $scopeKeys): array
    {
        $rows = [];

        foreach ($scopeKeys as $scopeKey) {
            foreach ($rowsByScope[$scopeKey] ?? [] as $row) {
                $rows[] = $row;
            }
        }

        return $rows;
    }

    private function buildScopeKey(mixed $identityValue): string
    {
        if ($identityValue === null || $identityValue === '') {
            return self::NULL_SCOPE_KEY;
        }

        return trim((string) $identityValue);
    }

    private function normalizeString(mixed $value): ?string
    {
        if ($value === null) {
            return null;
        }

        $normalized = trim((string) $value);

        return $normalized === '' ? null : $normalized;
    }

    private function extractFileName(string $path): string
    {
        return basename(str_replace('\\', '/', $path));
    }

    private function buildMediaExternalId(mixed $afsArtikelId, string $slotName): string
    {
        return 'afs-article-' . trim((string) $afsArtikelId) . '-' . $slotName;
    }

    private function insertRows(string $table, array $rows): int
    {
        if ($rows === []) {
            return 0;
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

        $sql = 'INSERT INTO ' . $this->quoteIdentifier($table)
            . ' (' . implode(',', array_map([$this, 'quoteIdentifier'], $columns)) . ') VALUES '
            . implode(',', $valueGroups);
        $stmt = $this->stageDb->prepare($sql);
        $stmt->execute($params);

        return count($rows);
    }

    private function buildSelectColumnList(array $columns): string
    {
        $uniqueColumns = [];

        foreach ($columns as $column) {
            if (!is_string($column) || $column === '') {
                continue;
            }

            $uniqueColumns[$column] = true;
        }

        if ($uniqueColumns === []) {
            throw new RuntimeException('Expand select column list cannot be empty.');
        }

        return implode(', ', array_map([$this, 'quoteIdentifier'], array_keys($uniqueColumns)));
    }

    private function quoteIdentifier(string $identifier): string
    {
        if (!preg_match('/^[A-Za-z0-9_]+$/', $identifier)) {
            throw new RuntimeException("Invalid SQL identifier '{$identifier}' in expand configuration.");
        }

        return '`' . $identifier . '`';
    }

    private function roundDuration(float $seconds): float
    {
        return round($seconds, 3);
    }
}
