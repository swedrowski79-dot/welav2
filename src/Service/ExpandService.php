<?php

class ExpandService
{
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

        $this->stageDb->exec('TRUNCATE TABLE ' . $this->quoteIdentifier($targetTable));

        $select = $this->stageDb->query(
            sprintf(
                'SELECT %s FROM %s',
                $this->buildSelectColumnList($sourceColumns),
                $this->quoteIdentifier($sourceTable)
            )
        );
        $batch = [];

        while ($row = $select->fetch(PDO::FETCH_ASSOC)) {
            $sourceRows++;

            foreach ($slots as $slotIndex => $slot) {
                $nameField = $slot['name'] ?? null;
                $valueField = $slot['value'] ?? null;
                $sortOrder = $slot['sort'] ?? null;

                $attributeName = $this->normalizeString($row[$nameField] ?? null);
                $attributeValue = $this->normalizeString($row[$valueField] ?? null);

                if ($attributeName === null || $attributeValue === null) {
                    continue;
                }

                $batch[] = [
                    'afs_artikel_id' => $row['afs_artikel_id'] ?? null,
                    'sku' => $row['sku'] ?? null,
                    'language_code' => $row['language_code'] ?? null,
                    'sort_order' => (int) $sortOrder,
                    'attribute_name' => $attributeName,
                    'attribute_value' => $attributeValue,
                    'source_directory' => $row['source_directory'] ?? null,
                ];

                if (count($batch) >= $this->insertBatchSize) {
                    $writtenRows += $this->insertRows($targetTable, $batch);
                    $insertBatches++;
                    $batch = [];
                }
            }
        }

        if ($batch !== []) {
            $writtenRows += $this->insertRows($targetTable, $batch);
            $insertBatches++;
        }

        $stats = [
            'mode' => 'attribute_slots',
            'source_table' => $sourceTable,
            'target_table' => $targetTable,
            'source_rows' => $sourceRows,
            'written_rows' => $writtenRows,
            'insert_batches' => $insertBatches,
            'duration_seconds' => $this->roundDuration(microtime(true) - $startedAt),
        ];

        if ($this->monitor !== null) {
            $this->monitor->log($this->runId, 'info', "Expand-Definition '{$definitionName}' abgeschlossen.", $stats);
        }

        return $stats;
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

        $this->stageDb->exec('TRUNCATE TABLE ' . $this->quoteIdentifier($targetTable));

        $select = $this->stageDb->query(
            sprintf(
                'SELECT %s FROM %s',
                $this->buildSelectColumnList($sourceColumns),
                $this->quoteIdentifier($sourceTable)
            )
        );
        $batch = [];

        while ($row = $select->fetch(PDO::FETCH_ASSOC)) {
            $sourceRows++;
            $afsArtikelId = $row['afs_artikel_id'] ?? null;

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

                $batch[] = [
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

                if (count($batch) >= $this->insertBatchSize) {
                    $writtenRows += $this->insertRows($targetTable, $batch);
                    $insertBatches++;
                    $batch = [];
                }
            }
        }

        if ($batch !== []) {
            $writtenRows += $this->insertRows($targetTable, $batch);
            $insertBatches++;
        }

        $stats = [
            'mode' => 'media_slots',
            'source_table' => $sourceTable,
            'target_table' => $targetTable,
            'source_rows' => $sourceRows,
            'written_rows' => $writtenRows,
            'insert_batches' => $insertBatches,
            'duration_seconds' => $this->roundDuration(microtime(true) - $startedAt),
        ];

        if ($this->monitor !== null) {
            $this->monitor->log($this->runId, 'info', "Expand-Definition '{$definitionName}' abgeschlossen.", $stats);
        }

        return $stats;
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
