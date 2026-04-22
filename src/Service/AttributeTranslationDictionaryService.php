<?php

declare(strict_types=1);

final class AttributeTranslationDictionaryService
{
    private const LANGUAGES = ['de', 'en', 'fr', 'nl'];
    private const TABLE = 'attribute_translations';

    public function __construct(private PDO $stageDb, private PDO $extraDb)
    {
    }

    public function sync(): array
    {
        $this->ensureSchema();

        return [
            'inserted_rows' => $this->seedFromRawAfsArticles(),
        ];
    }

    private function ensureSchema(): void
    {
        $this->extraDb->exec(
            'CREATE TABLE IF NOT EXISTS `' . self::TABLE . '` (
                `id` INT NOT NULL PRIMARY KEY,
                `article_id` INT NULL,
                `article_number` VARCHAR(255) NULL,
                `sort_order` INT NULL,
                `language` VARCHAR(10) NULL,
                `attribute_name` VARCHAR(255) NULL,
                `attribute_value` VARCHAR(255) NULL,
                `source_directory` VARCHAR(255) NULL,
                KEY `idx_attribute_translations_article_id` (`article_id`),
                KEY `idx_attribute_translations_article_number` (`article_number`),
                KEY `idx_attribute_translations_language` (`language`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4'
        );
    }

    private function seedFromRawAfsArticles(): int
    {
        $attributeRows = $this->fetchRawAfsAttributeRows();
        $existingRows = $this->fetchExistingRowsByArticleAndSort();
        $count = 0;
        $nextId = $this->nextInsertId();

        foreach ($attributeRows as $attributeRow) {
            $articleId = (int) ($attributeRow['article_id'] ?? 0);
            $sortOrder = (int) ($attributeRow['sort_order'] ?? 0);
            if ($articleId <= 0 || $sortOrder <= 0) {
                continue;
            }
            foreach (self::LANGUAGES as $languageCode) {
                $key = $articleId . '|' . $sortOrder . '|' . $languageCode;
                if (isset($existingRows[$key])) {
                    continue;
                }

                $this->insertAttributeRow($nextId++, $attributeRow, $languageCode);
                $existingRows[$key] = true;
                $count++;
            }
        }

        return $count;
    }

    private function fetchRawAfsAttributeRows(): array
    {
        $stmt = $this->stageDb->query(
            "SELECT *
             FROM (
                 SELECT `afs_artikel_id` AS article_id, `sku` AS article_number, 1 AS sort_order, TRIM(`attribute_name1`) AS attribute_name, TRIM(COALESCE(`attribute_value1`, '')) AS attribute_value FROM `raw_afs_articles`
                 UNION ALL
                 SELECT `afs_artikel_id` AS article_id, `sku` AS article_number, 2 AS sort_order, TRIM(`attribute_name2`) AS attribute_name, TRIM(COALESCE(`attribute_value2`, '')) AS attribute_value FROM `raw_afs_articles`
                 UNION ALL
                 SELECT `afs_artikel_id` AS article_id, `sku` AS article_number, 3 AS sort_order, TRIM(`attribute_name3`) AS attribute_name, TRIM(COALESCE(`attribute_value3`, '')) AS attribute_value FROM `raw_afs_articles`
                 UNION ALL
                 SELECT `afs_artikel_id` AS article_id, `sku` AS article_number, 4 AS sort_order, TRIM(`attribute_name4`) AS attribute_name, TRIM(COALESCE(`attribute_value4`, '')) AS attribute_value FROM `raw_afs_articles`
             ) names
              WHERE NULLIF(attribute_name, '') IS NOT NULL
               AND NULLIF(attribute_value, '') IS NOT NULL
             ORDER BY article_id ASC, sort_order ASC"
        );

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    private function fetchExistingRowsByArticleAndSort(): array
    {
        $stmt = $this->extraDb->query(
            'SELECT article_id, sort_order, language
             FROM `' . self::TABLE . '`
             WHERE article_id IS NOT NULL
               AND sort_order IS NOT NULL
               AND language IS NOT NULL'
        );

        $existing = [];

        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $existing[(int) $row['article_id'] . '|' . (int) $row['sort_order'] . '|' . strtolower((string) $row['language'])] = true;
        }

        return $existing;
    }

    private function nextInsertId(): int
    {
        $stmt = $this->extraDb->query('SELECT COALESCE(MAX(id), 0) + 1 FROM `' . self::TABLE . '`');

        return (int) $stmt->fetchColumn();
    }

    private function insertAttributeRow(int $id, array $attributeRow, string $languageCode): void
    {
        $stmt = $this->extraDb->prepare(
            "INSERT INTO `" . self::TABLE . "` (
                `id`,
                `article_id`,
                `article_number`,
                `sort_order`,
                `language`,
                `attribute_name`,
                `attribute_value`,
                `source_directory`
            ) VALUES (
                :id,
                :article_id,
                :article_number,
                :sort_order,
                :language,
                :attribute_name,
                :attribute_value,
                :source_directory
            )"
        );

        $stmt->execute([
            ':id' => $id,
            ':article_id' => (int) ($attributeRow['article_id'] ?? 0),
            ':article_number' => (string) ($attributeRow['article_number'] ?? ''),
            ':sort_order' => (int) ($attributeRow['sort_order'] ?? 0),
            ':language' => $languageCode,
            ':attribute_name' => $languageCode === 'de' ? (string) ($attributeRow['attribute_name'] ?? '') : '',
            ':attribute_value' => $languageCode === 'de' ? (string) ($attributeRow['attribute_value'] ?? '') : '',
            ':source_directory' => 'afs_auto'
        ]);
    }
}
