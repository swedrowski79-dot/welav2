<?php

declare(strict_types=1);

final class AttributeTranslationProjectionService
{
    private const LANGUAGES = ['de', 'en', 'fr', 'nl'];
    private const DICTIONARY_TABLE = 'attribute_translations';

    public function __construct(
        private PDO $stageDb,
        private PDO $extraDb,
        private StageWriter $stageWriter
    ) {
    }

    public function rebuild(): array
    {
        $dictionary = $this->fetchActiveDictionary();
        $stmt = $this->stageDb->query(
            "SELECT *
             FROM (
                 SELECT `afs_artikel_id`, `sku`, 1 AS sort_order, TRIM(`attribute_name1`) AS attribute_name, TRIM(COALESCE(`attribute_value1`, '')) AS attribute_value FROM `raw_afs_articles`
                 UNION ALL
                 SELECT `afs_artikel_id`, `sku`, 2 AS sort_order, TRIM(`attribute_name2`) AS attribute_name, TRIM(COALESCE(`attribute_value2`, '')) AS attribute_value FROM `raw_afs_articles`
                 UNION ALL
                 SELECT `afs_artikel_id`, `sku`, 3 AS sort_order, TRIM(`attribute_name3`) AS attribute_name, TRIM(COALESCE(`attribute_value3`, '')) AS attribute_value FROM `raw_afs_articles`
                 UNION ALL
                 SELECT `afs_artikel_id`, `sku`, 4 AS sort_order, TRIM(`attribute_name4`) AS attribute_name, TRIM(COALESCE(`attribute_value4`, '')) AS attribute_value FROM `raw_afs_articles`
             ) attribute_rows
             WHERE NULLIF(`attribute_name`, '') IS NOT NULL
               AND NULLIF(`attribute_value`, '') IS NOT NULL
             ORDER BY `afs_artikel_id` ASC, `sort_order` ASC"
        );

        $batch = [];
        $sourceRows = 0;
        $writtenRows = 0;

        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $sourceRows++;
            $attributeName = $this->normalizeString($row['attribute_name'] ?? null);
            $attributeValue = $this->normalizeString($row['attribute_value'] ?? null);

            if ($attributeName === null || $attributeValue === null) {
                continue;
            }

            foreach (self::LANGUAGES as $languageCode) {
                $batch[] = [
                    'row_id' => null,
                    'afs_artikel_id' => $row['afs_artikel_id'] ?? null,
                    'sku' => $row['sku'] ?? null,
                    'sort_order' => (int) ($row['sort_order'] ?? 0),
                    'attribute_name' => $this->translatedText($attributeName, $languageCode, $dictionary),
                    'attribute_value' => $this->translatedText($attributeValue, $languageCode, $dictionary),
                    'language_code' => $languageCode,
                    'language_code_normalized' => $languageCode,
                    'source_directory' => 'attribute_dictionary',
                    'translated_name' => null,
                    'translated_value' => null,
                    'is_auto_generated' => 1,
                    'translation_source' => 'attribute_dictionary',
                ];

                if (count($batch) >= 500) {
                    $this->stageWriter->insertMany('raw_extra_attribute_translations', $batch);
                    $writtenRows += count($batch);
                    $batch = [];
                }
            }
        }

        if ($batch !== []) {
            $this->stageWriter->insertMany('raw_extra_attribute_translations', $batch);
            $writtenRows += count($batch);
        }

        return [
            'source_rows' => $sourceRows,
            'written_rows' => $writtenRows,
            'active_dictionary_terms' => count($dictionary),
        ];
    }

    private function fetchActiveDictionary(): array
    {
        $stmt = $this->extraDb->query(
            'SELECT `normalized_key`, `de`, `en`, `fr`, `nl`
             FROM `' . self::DICTIONARY_TABLE . '`
             WHERE `is_active` = 1'
        );

        $dictionary = [];

        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $normalizedKey = trim((string) ($row['normalized_key'] ?? ''));
            if ($normalizedKey === '') {
                continue;
            }

            $dictionary[$normalizedKey] = [
                'de' => $this->normalizeString($row['de'] ?? null),
                'en' => $this->normalizeString($row['en'] ?? null),
                'fr' => $this->normalizeString($row['fr'] ?? null),
                'nl' => $this->normalizeString($row['nl'] ?? null),
            ];
        }

        return $dictionary;
    }

    private function translatedText(string $sourceText, string $languageCode, array $dictionary): string
    {
        if ($languageCode === 'de') {
            return $sourceText;
        }

        $normalizedKey = $this->normalizedKey($sourceText);
        $entry = $dictionary[$normalizedKey] ?? null;
        if (!is_array($entry)) {
            return '';
        }

        return (string) ($entry[$languageCode] ?? '');
    }

    private function normalizedKey(string $value): string
    {
        $normalized = trim(mb_strtolower($value));
        $normalized = preg_replace('/\s+/u', ' ', $normalized) ?? $normalized;

        return $normalized;
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
