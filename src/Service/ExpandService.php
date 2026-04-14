<?php

class ExpandService
{
    public function __construct(
        private PDO $stageDb,
        private array $expandConfig
    ) {
    }

    public function run(): void
    {
        $definitions = $this->expandConfig['expand'] ?? null;

        if (!is_array($definitions) || $definitions === []) {
            throw new RuntimeException('Expand config is missing or empty.');
        }

        foreach ($definitions as $definitionName => $definition) {
            if (!is_array($definition)) {
                throw new RuntimeException("Expand definition '{$definitionName}' must be an array.");
            }

            $this->expandDefinition($definitionName, $definition);
        }
    }

    private function expandDefinition(string $definitionName, array $definition): void
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
        $insert = $this->stageDb->prepare(
            "INSERT INTO `{$targetTable}` (
                `afs_artikel_id`,
                `sku`,
                `language_code`,
                `sort_order`,
                `attribute_name`,
                `attribute_value`,
                `source_directory`
            ) VALUES (
                :afs_artikel_id,
                :sku,
                :language_code,
                :sort_order,
                :attribute_name,
                :attribute_value,
                :source_directory
            )"
        );

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

                $insert->execute([
                    'afs_artikel_id' => $row['afs_artikel_id'] ?? null,
                    'sku' => $row['sku'] ?? null,
                    'language_code' => $row['language_code'] ?? null,
                    'sort_order' => (int) $sortOrder,
                    'attribute_name' => $attributeName,
                    'attribute_value' => $attributeValue,
                    'source_directory' => $row['source_directory'] ?? null,
                ]);
            }
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
}
