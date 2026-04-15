<?php

class ExpandService
{
    private int $insertBatchSize;

    public function __construct(
        private PDO $stageDb,
        private array $expandConfig
    ) {
        $definitions = $this->expandConfig['expand'] ?? [];
        $this->insertBatchSize = max(1, (int) ($definitions['insert_batch_size'] ?? 500));
    }

    public function run(): void
    {
        $definitions = $this->expandConfig['expand'] ?? null;

        if (!is_array($definitions) || $definitions === []) {
            throw new RuntimeException('Expand config is missing or empty.');
        }

        unset($definitions['insert_batch_size']);

        foreach ($definitions as $definitionName => $definition) {
            if (!is_array($definition)) {
                throw new RuntimeException("Expand definition '{$definitionName}' must be an array.");
            }

            $this->expandDefinition($definitionName, $definition);
        }
    }

    private function expandDefinition(string $definitionName, array $definition): void
    {
        $mode = $definition['mode'] ?? 'attribute_slots';

        if (!is_string($mode) || $mode === '') {
            throw new RuntimeException("Expand definition '{$definitionName}' is missing a valid mode.");
        }

        if ($mode === 'attribute_slots') {
            $this->expandAttributeSlots($definitionName, $definition);
            return;
        }

        if ($mode === 'media_slots') {
            $this->expandMediaSlots($definitionName, $definition);
            return;
        }

        throw new RuntimeException("Expand definition '{$definitionName}' uses an unsupported mode '{$mode}'.");
    }

    private function expandAttributeSlots(string $definitionName, array $definition): void
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

        $this->stageDb->exec("TRUNCATE TABLE `{$targetTable}`");

        $select = $this->stageDb->query("SELECT * FROM `{$sourceTable}`");
        $batch = [];

        while ($row = $select->fetch(PDO::FETCH_ASSOC)) {
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
                    $this->insertRows($targetTable, $batch);
                    $batch = [];
                }
            }
        }

        if ($batch !== []) {
            $this->insertRows($targetTable, $batch);
        }
    }

    private function expandMediaSlots(string $definitionName, array $definition): void
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

        $this->stageDb->exec("TRUNCATE TABLE `{$targetTable}`");

        $select = $this->stageDb->query("SELECT * FROM `{$sourceTable}`");
        $batch = [];

        while ($row = $select->fetch(PDO::FETCH_ASSOC)) {
            $afsArtikelId = $row['afs_artikel_id'] ?? null;

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
                    $this->insertRows($targetTable, $batch);
                    $batch = [];
                }
            }
        }

        if ($batch !== []) {
            $this->insertRows($targetTable, $batch);
        }
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
