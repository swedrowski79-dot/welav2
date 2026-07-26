<?php

declare(strict_types=1);

final class AttributeTranslationDictionaryService
{
    private const LANGUAGES = ['de', 'en', 'fr', 'nl'];
    private const TABLE = 'attribute_translations';
    private const LEGACY_BACKUP_TABLE = 'attribute_translations_legacy_20260717';

    public function __construct(private PDO $stageDb, private PDO $extraDb)
    {
    }

    public function sync(): array
    {
        $this->ensureSchema();
        $migrationStats = $this->migrateLegacyTableIfNeeded();

        $currentTerms = $this->fetchCurrentTermsFromRawAfsArticles();
        $existingRows = $this->fetchExistingDictionaryRows();

        return array_merge($migrationStats, [
            'inserted_rows' => $this->insertMissingTerms($currentTerms, $existingRows),
            'reactivated_rows' => $this->reactivateExistingTerms($currentTerms, $existingRows),
            'deactivated_rows' => $this->deactivateMissingTerms($currentTerms, $existingRows),
            'active_terms' => count($currentTerms),
        ]);
    }

    private function ensureSchema(): void
    {
        if (!$this->tableExists(self::TABLE)) {
            $this->createDictionaryTable();

            return;
        }

        if ($this->columnExists(self::TABLE, 'source_text')) {
            if (!$this->columnExists(self::TABLE, 'is_active')) {
                $this->extraDb->exec(
                    'ALTER TABLE `' . self::TABLE . '` ADD COLUMN `is_active` TINYINT(1) NOT NULL DEFAULT 1 AFTER `source_directory`'
                );
            }

            return;
        }

        if (!$this->columnExists(self::TABLE, 'is_active')) {
            $this->extraDb->exec(
                'ALTER TABLE `' . self::TABLE . '` ADD COLUMN `is_active` TINYINT(1) NOT NULL DEFAULT 1 AFTER `source_directory`'
            );
        }
    }

    private function migrateLegacyTableIfNeeded(): array
    {
        if (!$this->tableExists(self::TABLE) || $this->columnExists(self::TABLE, 'source_text')) {
            return [
                'legacy_migrated_rows' => 0,
                'legacy_dictionary_terms' => 0,
            ];
        }

        $legacyRows = $this->fetchLegacyRows();
        $legacyEntries = $this->buildLegacyDictionaryEntries($legacyRows);

        if (!$this->tableExists(self::LEGACY_BACKUP_TABLE)) {
            $this->extraDb->exec(
                'CREATE TABLE `' . self::LEGACY_BACKUP_TABLE . '` LIKE `' . self::TABLE . '`'
            );
            $this->extraDb->exec(
                'INSERT INTO `' . self::LEGACY_BACKUP_TABLE . '` SELECT * FROM `' . self::TABLE . '`'
            );
        }

        $this->extraDb->exec('DROP TABLE `' . self::TABLE . '`');
        $this->createDictionaryTable();
        $this->insertDictionaryEntries($legacyEntries);

        return [
            'legacy_migrated_rows' => count($legacyRows),
            'legacy_dictionary_terms' => count($legacyEntries),
        ];
    }

    private function createDictionaryTable(): void
    {
        $this->extraDb->exec(
            'CREATE TABLE IF NOT EXISTS `' . self::TABLE . '` (
                `id` INT NOT NULL AUTO_INCREMENT PRIMARY KEY,
                `source_text` VARCHAR(255) NOT NULL,
                `normalized_key` VARCHAR(255) NOT NULL,
                `de` VARCHAR(255) NULL,
                `en` VARCHAR(255) NULL,
                `fr` VARCHAR(255) NULL,
                `nl` VARCHAR(255) NULL,
                `source_directory` VARCHAR(255) NULL,
                `is_active` TINYINT(1) NOT NULL DEFAULT 1,
                UNIQUE KEY `uniq_attribute_translations_normalized_key` (`normalized_key`),
                KEY `idx_attribute_translations_is_active` (`is_active`),
                KEY `idx_attribute_translations_source_text` (`source_text`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4'
        );
    }

    private function fetchLegacyRows(): array
    {
        $stmt = $this->extraDb->query(
            'SELECT `article_id`, `sort_order`, `language`, `attribute_name`, `attribute_value`, `source_directory`, `is_active`
             FROM `' . self::TABLE . '`
             ORDER BY `article_id` ASC, `sort_order` ASC, `language` ASC'
        );

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    private function buildLegacyDictionaryEntries(array $legacyRows): array
    {
        $grouped = [];

        foreach ($legacyRows as $row) {
            $articleId = (int) ($row['article_id'] ?? 0);
            $sortOrder = (int) ($row['sort_order'] ?? 0);
            $languageCode = strtolower(trim((string) ($row['language'] ?? '')));

            if ($articleId <= 0 || $sortOrder <= 0 || !in_array($languageCode, self::LANGUAGES, true)) {
                continue;
            }

            $grouped[$articleId . '|' . $sortOrder][$languageCode] = $row;
        }

        $entries = [];

        foreach ($grouped as $translations) {
            $baseRow = $translations['de'] ?? reset($translations);
            if (!is_array($baseRow)) {
                continue;
            }

            $sourceName = $this->normalizeString($baseRow['attribute_name'] ?? null);
            $sourceValue = $this->normalizeString($baseRow['attribute_value'] ?? null);
            $isActive = $this->groupIsActive($translations);
            $sourceDirectory = $this->normalizeString($baseRow['source_directory'] ?? null) ?? 'legacy_migration';

            if ($sourceName !== null) {
                $entries = $this->mergeLegacyEntry($entries, $sourceName, $translations, 'attribute_name', $sourceDirectory, $isActive);
            }

            if ($sourceValue !== null) {
                $entries = $this->mergeLegacyEntry($entries, $sourceValue, $translations, 'attribute_value', $sourceDirectory, $isActive);
            }
        }

        return array_values($entries);
    }

    private function mergeLegacyEntry(
        array $entries,
        string $sourceText,
        array $translations,
        string $field,
        string $sourceDirectory,
        int $isActive
    ): array {
        $normalizedKey = $this->normalizedKey($sourceText);
        if ($normalizedKey === '') {
            return $entries;
        }

        if (!isset($entries[$normalizedKey])) {
            $entries[$normalizedKey] = [
                'source_text' => $sourceText,
                'normalized_key' => $normalizedKey,
                'de' => $sourceText,
                'en' => null,
                'fr' => null,
                'nl' => null,
                'source_directory' => $sourceDirectory,
                'is_active' => $isActive,
            ];
        }

        foreach (self::LANGUAGES as $languageCode) {
            $row = $translations[$languageCode] ?? null;
            if (!is_array($row)) {
                continue;
            }

            $translatedText = $this->normalizeString($row[$field] ?? null);
            if ($translatedText === null) {
                continue;
            }

            if ($entries[$normalizedKey][$languageCode] === null || $entries[$normalizedKey][$languageCode] === '') {
                $entries[$normalizedKey][$languageCode] = $translatedText;
            }
        }

        if (($entries[$normalizedKey]['source_directory'] ?? null) === null) {
            $entries[$normalizedKey]['source_directory'] = $sourceDirectory;
        }
        $entries[$normalizedKey]['is_active'] = max((int) ($entries[$normalizedKey]['is_active'] ?? 0), $isActive);

        return $entries;
    }

    private function groupIsActive(array $translations): int
    {
        foreach ($translations as $row) {
            if ((int) ($row['is_active'] ?? 1) === 1) {
                return 1;
            }
        }

        return 0;
    }

    private function insertDictionaryEntries(array $entries): void
    {
        if ($entries === []) {
            return;
        }

        $stmt = $this->extraDb->prepare(
            'INSERT INTO `' . self::TABLE . '` (
                `source_text`,
                `normalized_key`,
                `de`,
                `en`,
                `fr`,
                `nl`,
                `source_directory`,
                `is_active`
            ) VALUES (
                :source_text,
                :normalized_key,
                :de,
                :en,
                :fr,
                :nl,
                :source_directory,
                :is_active
            )'
        );

        foreach ($entries as $entry) {
            $stmt->execute([
                ':source_text' => $entry['source_text'] ?? '',
                ':normalized_key' => $entry['normalized_key'] ?? '',
                ':de' => $entry['de'] ?? null,
                ':en' => $entry['en'] ?? null,
                ':fr' => $entry['fr'] ?? null,
                ':nl' => $entry['nl'] ?? null,
                ':source_directory' => $entry['source_directory'] ?? null,
                ':is_active' => (int) ($entry['is_active'] ?? 1),
            ]);
        }
    }

    private function fetchCurrentTermsFromRawAfsArticles(): array
    {
        $stmt = $this->stageDb->query(
            "SELECT `attribute_name1`, `attribute_name2`, `attribute_name3`, `attribute_name4`,
                    `attribute_value1`, `attribute_value2`, `attribute_value3`, `attribute_value4`
             FROM `raw_afs_articles`"
        );

        $terms = [];

        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            foreach ([
                'attribute_name1',
                'attribute_name2',
                'attribute_name3',
                'attribute_name4',
                'attribute_value1',
                'attribute_value2',
                'attribute_value3',
                'attribute_value4',
            ] as $field) {
                $sourceText = $this->normalizeString($row[$field] ?? null);
                if ($sourceText === null) {
                    continue;
                }

                $normalizedKey = $this->normalizedKey($sourceText);
                if ($normalizedKey === '' || isset($terms[$normalizedKey])) {
                    continue;
                }

                $terms[$normalizedKey] = [
                    'source_text' => $sourceText,
                    'normalized_key' => $normalizedKey,
                    'source_directory' => 'afs_auto',
                ];
            }
        }

        return $terms;
    }

    private function fetchExistingDictionaryRows(): array
    {
        $stmt = $this->extraDb->query(
            'SELECT `id`, `source_text`, `normalized_key`, `de`, `en`, `fr`, `nl`, `source_directory`, `is_active`
             FROM `' . self::TABLE . '`'
        );

        $rows = [];

        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $normalizedKey = (string) ($row['normalized_key'] ?? '');
            if ($normalizedKey === '') {
                continue;
            }

            $rows[$normalizedKey] = $row;
        }

        return $rows;
    }

    private function insertMissingTerms(array $currentTerms, array $existingRows): int
    {
        $count = 0;
        $stmt = $this->extraDb->prepare(
            'INSERT INTO `' . self::TABLE . '` (
                `source_text`,
                `normalized_key`,
                `de`,
                `source_directory`,
                `is_active`
            ) VALUES (
                :source_text,
                :normalized_key,
                :de,
                :source_directory,
                1
            )'
        );

        foreach ($currentTerms as $normalizedKey => $term) {
            if (isset($existingRows[$normalizedKey])) {
                continue;
            }

            $stmt->execute([
                ':source_text' => $term['source_text'] ?? '',
                ':normalized_key' => $normalizedKey,
                ':de' => $term['source_text'] ?? '',
                ':source_directory' => $term['source_directory'] ?? 'afs_auto',
            ]);
            $count += $stmt->rowCount() > 0 ? 1 : 0;
        }

        return $count;
    }

    private function reactivateExistingTerms(array $currentTerms, array $existingRows): int
    {
        $count = 0;
        $stmt = $this->extraDb->prepare(
            'UPDATE `' . self::TABLE . '`
             SET `source_text` = :source_text,
                 `de` = CASE
                     WHEN `de` IS NULL OR TRIM(`de`) = "" THEN :de
                     ELSE `de`
                 END,
                 `source_directory` = :source_directory,
                 `is_active` = 1
             WHERE `id` = :id'
        );

        foreach ($currentTerms as $normalizedKey => $term) {
            $existingRow = $existingRows[$normalizedKey] ?? null;
            if (!is_array($existingRow)) {
                continue;
            }

            $needsUpdate = (int) ($existingRow['is_active'] ?? 1) !== 1
                || (string) ($existingRow['source_text'] ?? '') !== (string) ($term['source_text'] ?? '')
                || (string) ($existingRow['source_directory'] ?? '') !== (string) ($term['source_directory'] ?? '');

            if (!$needsUpdate && trim((string) ($existingRow['de'] ?? '')) !== '') {
                continue;
            }

            $stmt->execute([
                ':source_text' => $term['source_text'] ?? '',
                ':de' => $term['source_text'] ?? '',
                ':source_directory' => $term['source_directory'] ?? 'afs_auto',
                ':id' => (int) ($existingRow['id'] ?? 0),
            ]);
            $count += $stmt->rowCount() > 0 ? 1 : 0;
        }

        return $count;
    }

    private function deactivateMissingTerms(array $currentTerms, array $existingRows): int
    {
        $count = 0;
        $currentLookup = array_fill_keys(array_keys($currentTerms), true);
        $stmt = $this->extraDb->prepare(
            'UPDATE `' . self::TABLE . '`
             SET `is_active` = 0
             WHERE `id` = :id'
        );

        foreach ($existingRows as $normalizedKey => $existingRow) {
            if (isset($currentLookup[$normalizedKey])) {
                continue;
            }

            if ((int) ($existingRow['is_active'] ?? 1) === 0) {
                continue;
            }

            $stmt->execute([
                ':id' => (int) ($existingRow['id'] ?? 0),
            ]);
            $count += $stmt->rowCount() > 0 ? 1 : 0;
        }

        return $count;
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

    private function tableExists(string $table): bool
    {
        $stmt = $this->extraDb->prepare('SHOW TABLES LIKE :table');
        $stmt->execute([':table' => $table]);

        return $stmt->fetchColumn() !== false;
    }

    private function columnExists(string $table, string $column): bool
    {
        $stmt = $this->extraDb->query('SHOW COLUMNS FROM `' . $table . '`');
        $columns = $stmt->fetchAll(PDO::FETCH_COLUMN);

        return in_array($column, $columns, true);
    }
}
