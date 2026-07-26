<?php

declare(strict_types=1);

namespace App\Web\Repository;

use App\Web\Core\Paginator;

final class TranslationOverviewRepository
{
    private const MYSQL_TABLE_NOT_FOUND = 1146;

    public function __construct(private \PDO $stageDb)
    {
    }

    public function summary(): array
    {
        $languages = ['de', 'en', 'fr', 'nl'];
        $productsTotal = $this->countTable('stage_products');
        $productsTranslated = $this->countDistinctJoin(
            'stage_products',
            'p',
            'afs_artikel_id',
            'stage_product_translations',
            't',
            't.afs_artikel_id = p.afs_artikel_id'
        );

        $categoriesTotal = $this->countTable('stage_categories');
        $categoriesTranslated = $this->countDistinctJoin(
            'stage_categories',
            'c',
            'afs_wg_id',
            'stage_category_translations',
            't',
            't.afs_wg_id = c.afs_wg_id'
        );

        $attributeRowsTotal = $this->countQuery(
            'SELECT COUNT(DISTINCT a.afs_artikel_id)
             FROM stage_attribute_translations a
             INNER JOIN stage_products p
                 ON p.afs_artikel_id = a.afs_artikel_id'
        );
        $attributeRowsTranslated = $this->countQuery(
            'SELECT COUNT(DISTINCT a.afs_artikel_id)
             FROM stage_attribute_translations a
             INNER JOIN stage_products p
                 ON p.afs_artikel_id = a.afs_artikel_id
             INNER JOIN stage_product_translations t
                 ON t.afs_artikel_id = a.afs_artikel_id
                AND t.language_code = a.language_code'
        );

        $productLanguageCounts = [];
        $categoryLanguageCounts = [];
        $attributeLanguageCounts = [];

        foreach ($languages as $languageCode) {
            $productLanguageCounts[$languageCode] = $this->countQuery(
                sprintf(
                    "SELECT COUNT(DISTINCT t.afs_artikel_id)
                     FROM stage_product_translations t
                     INNER JOIN stage_products p
                         ON p.afs_artikel_id = t.afs_artikel_id
                     WHERE t.language_code = '%s'",
                    $languageCode
                )
            );

            $categoryLanguageCounts[$languageCode] = $this->countQuery(
                sprintf(
                    "SELECT COUNT(DISTINCT t.afs_wg_id)
                     FROM stage_category_translations t
                     INNER JOIN stage_categories c
                         ON c.afs_wg_id = t.afs_wg_id
                     WHERE t.language_code = '%s'",
                    $languageCode
                )
            );

            $attributeLanguageCounts[$languageCode] = $this->countQuery(
                sprintf(
                    "SELECT COUNT(DISTINCT a.afs_artikel_id)
                     FROM stage_attribute_translations a
                     INNER JOIN stage_products p
                         ON p.afs_artikel_id = a.afs_artikel_id
                     INNER JOIN stage_product_translations t
                         ON t.afs_artikel_id = a.afs_artikel_id
                        AND t.language_code = a.language_code
                     WHERE a.language_code = '%s'",
                    $languageCode
                )
            );
        }

        return [
            'products_total' => $productsTotal,
            'products_translated' => $productsTranslated,
            'products_missing' => max(0, $productsTotal - $productsTranslated),
            'products_by_language' => $productLanguageCounts,
            'categories_total' => $categoriesTotal,
            'categories_translated' => $categoriesTranslated,
            'categories_missing' => max(0, $categoriesTotal - $categoriesTranslated),
            'categories_by_language' => $categoryLanguageCounts,
            'attribute_rows_total' => $attributeRowsTotal,
            'attribute_rows_translated' => $attributeRowsTranslated,
            'attribute_rows_missing' => max(0, $attributeRowsTotal - $attributeRowsTranslated),
            'attribute_rows_by_language' => $attributeLanguageCounts,
        ];
    }

    public function translatedProducts(int $limit = 20): array
    {
        return $this->fetchAll(
            'SELECT
                CAST(p.afs_artikel_id AS CHAR) AS article_id,
                p.sku,
                p.name_default AS article_name,
                COUNT(DISTINCT t.language_code) AS language_count,
                GROUP_CONCAT(DISTINCT t.language_code ORDER BY t.language_code SEPARATOR ", ") AS languages
             FROM stage_products p
             INNER JOIN stage_product_translations t
                 ON t.afs_artikel_id = p.afs_artikel_id
             GROUP BY p.afs_artikel_id, p.sku, p.name_default
             ORDER BY p.afs_artikel_id ASC
             LIMIT :limit',
            $limit
        );
    }

    public function missingProducts(int $limit = 20): array
    {
        return $this->fetchAll(
            'SELECT
                CAST(p.afs_artikel_id AS CHAR) AS article_id,
                p.sku,
                p.name_default AS article_name
             FROM stage_products p
             LEFT JOIN stage_product_translations t
                 ON t.afs_artikel_id = p.afs_artikel_id
             WHERE p.afs_artikel_id IS NOT NULL
               AND t.afs_artikel_id IS NULL
             ORDER BY p.afs_artikel_id ASC
             LIMIT :limit',
            $limit
        );
    }

    public function translatedCategories(int $limit = 20): array
    {
        return $this->fetchAll(
            'SELECT
                CAST(c.afs_wg_id AS CHAR) AS category_id,
                c.name_default AS category_name,
                COUNT(DISTINCT t.language_code) AS language_count,
                GROUP_CONCAT(DISTINCT t.language_code ORDER BY t.language_code SEPARATOR ", ") AS languages
             FROM stage_categories c
             INNER JOIN stage_category_translations t
                 ON t.afs_wg_id = c.afs_wg_id
             GROUP BY c.afs_wg_id, c.name_default
             ORDER BY c.afs_wg_id ASC
             LIMIT :limit',
            $limit
        );
    }

    public function missingCategories(int $limit = 20): array
    {
        return $this->fetchAll(
            'SELECT
                CAST(c.afs_wg_id AS CHAR) AS category_id,
                c.name_default AS category_name
             FROM stage_categories c
             LEFT JOIN stage_category_translations t
                 ON t.afs_wg_id = c.afs_wg_id
             WHERE c.afs_wg_id IS NOT NULL
               AND t.afs_wg_id IS NULL
             ORDER BY c.afs_wg_id ASC
             LIMIT :limit',
            $limit
        );
    }

    public function translatedAttributeRows(int $limit = 20): array
    {
        return $this->fetchAll(
            'SELECT
                CAST(a.afs_artikel_id AS CHAR) AS article_id,
                p.sku,
                a.language_code,
                a.attribute_name,
                a.attribute_value
             FROM stage_attribute_translations a
             INNER JOIN stage_product_translations t
                 ON t.afs_artikel_id = a.afs_artikel_id
                AND t.language_code = a.language_code
             LEFT JOIN stage_products p
                 ON p.afs_artikel_id = a.afs_artikel_id
             ORDER BY a.afs_artikel_id ASC, a.language_code ASC, a.sort_order ASC
             LIMIT :limit',
            $limit
        );
    }

    public function missingAttributeRows(int $limit = 20): array
    {
        return $this->fetchAll(
            'SELECT
                CAST(a.afs_artikel_id AS CHAR) AS article_id,
                p.sku,
                a.language_code,
                a.attribute_name,
                a.attribute_value
             FROM stage_attribute_translations a
             LEFT JOIN stage_product_translations t
                 ON t.afs_artikel_id = a.afs_artikel_id
                AND t.language_code = a.language_code
             LEFT JOIN stage_products p
                 ON p.afs_artikel_id = a.afs_artikel_id
             WHERE a.afs_artikel_id IS NOT NULL
               AND t.afs_artikel_id IS NULL
             ORDER BY a.afs_artikel_id ASC, a.language_code ASC, a.sort_order ASC
             LIMIT :limit',
            $limit
        );
    }

    public function countDetailRows(array $filters): int
    {
        [$sql, $params] = $this->detailQueryParts($filters, true);

        try {
            $stmt = $this->stageDb->prepare($sql);
            $stmt->execute($params);

            return (int) $stmt->fetchColumn();
        } catch (\PDOException $exception) {
            if ($this->isMissingTable($exception)) {
                return 0;
            }

            throw $exception;
        }
    }

    public function paginatedDetailRows(array $filters, Paginator $paginator): array
    {
        [$sql, $params] = $this->detailQueryParts($filters, false);

        try {
            $stmt = $this->stageDb->prepare($sql);

            foreach ($params as $key => $value) {
                $stmt->bindValue($key, $value);
            }

            $stmt->bindValue(':limit', $paginator->perPage, \PDO::PARAM_INT);
            $stmt->bindValue(':offset', $paginator->offset(), \PDO::PARAM_INT);
            $stmt->execute();

            return $stmt->fetchAll(\PDO::FETCH_ASSOC);
        } catch (\PDOException $exception) {
            if ($this->isMissingTable($exception)) {
                return [];
            }

            throw $exception;
        }
    }

    public function deleteDetailRows(array $filters, array $ids): int
    {
        $normalizedIds = array_values(array_unique(array_filter(array_map(
            static fn (mixed $value): string => trim((string) $value),
            $ids
        ), static fn (string $value): bool => $value !== '')));

        if ($normalizedIds === []) {
            return 0;
        }

        $entityType = (string) ($filters['entity_type'] ?? 'product');
        $coverage = (string) ($filters['coverage'] ?? 'translated');
        $languageCode = (string) ($filters['language_code'] ?? '');

        if ($coverage === 'missing') {
            return 0;
        }

        return match ($entityType) {
            'category' => $this->deleteTranslationRows('stage_category_translations', 'afs_wg_id', $normalizedIds, $languageCode),
            'product' => $this->deleteTranslationRows('stage_product_translations', 'afs_artikel_id', $normalizedIds, $languageCode),
            default => 0,
        };
    }

    private function fetchAll(string $sql, int $limit): array
    {
        try {
            $stmt = $this->stageDb->prepare($sql);
            $stmt->bindValue(':limit', max(1, $limit), \PDO::PARAM_INT);
            $stmt->execute();

            return $stmt->fetchAll(\PDO::FETCH_ASSOC);
        } catch (\PDOException $exception) {
            if ($this->isMissingTable($exception)) {
                return [];
            }

            throw $exception;
        }
    }

    private function deleteTranslationRows(string $table, string $identityField, array $ids, string $languageCode): int
    {
        $params = [];
        $placeholders = [];

        foreach ($ids as $index => $id) {
            $placeholder = ':id_' . $index;
            $placeholders[] = $placeholder;
            $params[$placeholder] = ctype_digit($id) ? (int) $id : $id;
        }

        $sql = 'DELETE FROM `' . $table . '`
                WHERE `' . $identityField . '` IN (' . implode(', ', $placeholders) . ')';

        if ($languageCode !== '') {
            $sql .= ' AND `language_code` = :language_code';
            $params[':language_code'] = $languageCode;
        }

        $stmt = $this->stageDb->prepare($sql);
        $stmt->execute($params);

        return $stmt->rowCount();
    }

    private function countTable(string $table): int
    {
        return $this->countQuery("SELECT COUNT(*) FROM `{$table}`");
    }

    private function countDistinctJoin(
        string $baseTable,
        string $baseAlias,
        string $identityField,
        string $joinTable,
        string $joinAlias,
        string $joinCondition
    ): int {
        return $this->countQuery(
            "SELECT COUNT(DISTINCT {$baseAlias}.`{$identityField}`)
             FROM `{$baseTable}` {$baseAlias}
             INNER JOIN `{$joinTable}` {$joinAlias}
                 ON {$joinCondition}"
        );
    }

    private function countQuery(string $sql): int
    {
        try {
            return (int) $this->stageDb->query($sql)->fetchColumn();
        } catch (\PDOException $exception) {
            if ($this->isMissingTable($exception)) {
                return 0;
            }

            throw $exception;
        }
    }

    private function isMissingTable(\PDOException $exception): bool
    {
        return (int) ($exception->errorInfo[1] ?? 0) === self::MYSQL_TABLE_NOT_FOUND;
    }

    private function detailQueryParts(array $filters, bool $countOnly): array
    {
        $entityType = (string) ($filters['entity_type'] ?? 'product');
        $coverage = (string) ($filters['coverage'] ?? 'translated');
        $languageCode = (string) ($filters['language_code'] ?? '');

        return match ($entityType) {
            'category' => $this->categoryDetailQueryParts($coverage, $languageCode, $countOnly),
            'attribute' => $this->attributeDetailQueryParts($coverage, $languageCode, $countOnly),
            default => $this->productDetailQueryParts($coverage, $languageCode, $countOnly),
        };
    }

    private function productDetailQueryParts(string $coverage, string $languageCode, bool $countOnly): array
    {
        $params = [];

        if ($coverage === 'orphan') {
            if ($languageCode !== '') {
                $params[':language_code'] = $languageCode;
            }

            $languageWhere = $languageCode !== '' ? 'AND t.language_code = :language_code' : '';
            $sql = $countOnly
                ? "SELECT COUNT(*) FROM (
                        SELECT t.afs_artikel_id
                        FROM stage_product_translations t
                        LEFT JOIN stage_products p
                            ON p.afs_artikel_id = t.afs_artikel_id
                        WHERE t.afs_artikel_id IS NOT NULL
                          AND p.afs_artikel_id IS NULL
                          {$languageWhere}
                        GROUP BY t.afs_artikel_id
                   ) detail_count_rows"
                : "SELECT
                        'product' AS entity_type,
                        'orphan' AS coverage,
                        CAST(t.afs_artikel_id AS CHAR) AS entity_id,
                        COALESCE(MAX(t.sku), '') AS entity_code,
                        COALESCE(MAX(t.name), '') AS entity_name,
                        " . ($languageCode !== '' ? ':language_code' : 'NULL') . " AS language_code,
                        COUNT(DISTINCT t.language_code) AS language_count,
                        GROUP_CONCAT(DISTINCT t.language_code ORDER BY t.language_code SEPARATOR ', ') AS languages,
                        NULL AS detail_value
                   FROM stage_product_translations t
                   LEFT JOIN stage_products p
                       ON p.afs_artikel_id = t.afs_artikel_id
                   WHERE t.afs_artikel_id IS NOT NULL
                     AND p.afs_artikel_id IS NULL
                     {$languageWhere}
                   GROUP BY t.afs_artikel_id
                   ORDER BY t.afs_artikel_id ASC
                   LIMIT :limit OFFSET :offset";

            return [$sql, $params];
        }

        if ($coverage === 'missing') {
            if ($languageCode !== '') {
                $params[':language_code'] = $languageCode;
                $sql = $countOnly
                    ? "SELECT COUNT(*)
                       FROM stage_products p
                       LEFT JOIN stage_product_translations t
                           ON t.afs_artikel_id = p.afs_artikel_id
                          AND t.language_code = :language_code
                       WHERE p.afs_artikel_id IS NOT NULL
                         AND t.afs_artikel_id IS NULL"
                    : "SELECT
                            'product' AS entity_type,
                            'missing' AS coverage,
                            CAST(p.afs_artikel_id AS CHAR) AS entity_id,
                            p.sku AS entity_code,
                            p.name_default AS entity_name,
                            :language_code AS language_code,
                            NULL AS language_count,
                            NULL AS languages,
                            NULL AS detail_value
                       FROM stage_products p
                       LEFT JOIN stage_product_translations t
                           ON t.afs_artikel_id = p.afs_artikel_id
                          AND t.language_code = :language_code
                       WHERE p.afs_artikel_id IS NOT NULL
                         AND t.afs_artikel_id IS NULL
                       ORDER BY p.afs_artikel_id ASC
                       LIMIT :limit OFFSET :offset";

                return [$sql, $params];
            }

            $sql = $countOnly
                ? "SELECT COUNT(*)
                   FROM stage_products p
                   LEFT JOIN stage_product_translations t
                       ON t.afs_artikel_id = p.afs_artikel_id
                   WHERE p.afs_artikel_id IS NOT NULL
                     AND t.afs_artikel_id IS NULL"
                : "SELECT
                        'product' AS entity_type,
                        'missing' AS coverage,
                        CAST(p.afs_artikel_id AS CHAR) AS entity_id,
                        p.sku AS entity_code,
                        p.name_default AS entity_name,
                        NULL AS language_code,
                        NULL AS language_count,
                        NULL AS languages,
                        NULL AS detail_value
                   FROM stage_products p
                   LEFT JOIN stage_product_translations t
                       ON t.afs_artikel_id = p.afs_artikel_id
                   WHERE p.afs_artikel_id IS NOT NULL
                     AND t.afs_artikel_id IS NULL
                   ORDER BY p.afs_artikel_id ASC
                   LIMIT :limit OFFSET :offset";

            return [$sql, $params];
        }

        if ($languageCode !== '') {
            $params[':language_code'] = $languageCode;
        }

        $countFilterJoin = $languageCode !== ''
            ? 'INNER JOIN stage_product_translations tf
                   ON tf.afs_artikel_id = p.afs_artikel_id
                  AND tf.language_code = :language_code'
            : '';

        $detailFilterJoin = $languageCode !== ''
            ? 'INNER JOIN stage_product_translations tf
                   ON tf.afs_artikel_id = p.afs_artikel_id
                  AND tf.language_code = :language_code'
            : '';

        $sql = $countOnly
            ? "SELECT COUNT(*) FROM (
                    SELECT p.afs_artikel_id
                    FROM stage_products p
                    INNER JOIN stage_product_translations t
                        ON t.afs_artikel_id = p.afs_artikel_id
                    {$countFilterJoin}
                    GROUP BY p.afs_artikel_id
               ) detail_count_rows"
            : "SELECT
                    'product' AS entity_type,
                    'translated' AS coverage,
                    CAST(p.afs_artikel_id AS CHAR) AS entity_id,
                    p.sku AS entity_code,
                    p.name_default AS entity_name,
                    " . ($languageCode !== '' ? ':language_code' : 'NULL') . " AS language_code,
                    COUNT(DISTINCT t.language_code) AS language_count,
                    GROUP_CONCAT(DISTINCT t.language_code ORDER BY t.language_code SEPARATOR ', ') AS languages,
                    NULL AS detail_value
               FROM stage_products p
               INNER JOIN stage_product_translations t
                   ON t.afs_artikel_id = p.afs_artikel_id
               {$detailFilterJoin}
               GROUP BY p.afs_artikel_id, p.sku, p.name_default
               ORDER BY p.afs_artikel_id ASC
               LIMIT :limit OFFSET :offset";

        return [$sql, $params];
    }

    private function categoryDetailQueryParts(string $coverage, string $languageCode, bool $countOnly): array
    {
        $params = [];

        if ($coverage === 'orphan') {
            if ($languageCode !== '') {
                $params[':language_code'] = $languageCode;
            }

            $languageWhere = $languageCode !== '' ? 'AND t.language_code = :language_code' : '';
            $sql = $countOnly
                ? "SELECT COUNT(*) FROM (
                        SELECT t.afs_wg_id
                        FROM stage_category_translations t
                        LEFT JOIN stage_categories c
                            ON c.afs_wg_id = t.afs_wg_id
                        WHERE t.afs_wg_id IS NOT NULL
                          AND c.afs_wg_id IS NULL
                          {$languageWhere}
                        GROUP BY t.afs_wg_id
                   ) detail_count_rows"
                : "SELECT
                        'category' AS entity_type,
                        'orphan' AS coverage,
                        CAST(t.afs_wg_id AS CHAR) AS entity_id,
                        NULL AS entity_code,
                        COALESCE(MAX(t.name), COALESCE(MAX(t.original_name), '')) AS entity_name,
                        " . ($languageCode !== '' ? ':language_code' : 'NULL') . " AS language_code,
                        COUNT(DISTINCT t.language_code) AS language_count,
                        GROUP_CONCAT(DISTINCT t.language_code ORDER BY t.language_code SEPARATOR ', ') AS languages,
                        NULL AS detail_value
                   FROM stage_category_translations t
                   LEFT JOIN stage_categories c
                       ON c.afs_wg_id = t.afs_wg_id
                   WHERE t.afs_wg_id IS NOT NULL
                     AND c.afs_wg_id IS NULL
                     {$languageWhere}
                   GROUP BY t.afs_wg_id
                   ORDER BY t.afs_wg_id ASC
                   LIMIT :limit OFFSET :offset";

            return [$sql, $params];
        }

        if ($coverage === 'missing') {
            if ($languageCode !== '') {
                $params[':language_code'] = $languageCode;
                $sql = $countOnly
                    ? "SELECT COUNT(*)
                       FROM stage_categories c
                       LEFT JOIN stage_category_translations t
                           ON t.afs_wg_id = c.afs_wg_id
                          AND t.language_code = :language_code
                       WHERE c.afs_wg_id IS NOT NULL
                         AND t.afs_wg_id IS NULL"
                    : "SELECT
                            'category' AS entity_type,
                            'missing' AS coverage,
                            CAST(c.afs_wg_id AS CHAR) AS entity_id,
                            NULL AS entity_code,
                            c.name_default AS entity_name,
                            :language_code AS language_code,
                            NULL AS language_count,
                            NULL AS languages,
                            NULL AS detail_value
                       FROM stage_categories c
                       LEFT JOIN stage_category_translations t
                           ON t.afs_wg_id = c.afs_wg_id
                          AND t.language_code = :language_code
                       WHERE c.afs_wg_id IS NOT NULL
                         AND t.afs_wg_id IS NULL
                       ORDER BY c.afs_wg_id ASC
                       LIMIT :limit OFFSET :offset";

                return [$sql, $params];
            }

            $sql = $countOnly
                ? "SELECT COUNT(*)
                   FROM stage_categories c
                   LEFT JOIN stage_category_translations t
                       ON t.afs_wg_id = c.afs_wg_id
                   WHERE c.afs_wg_id IS NOT NULL
                     AND t.afs_wg_id IS NULL"
                : "SELECT
                        'category' AS entity_type,
                        'missing' AS coverage,
                        CAST(c.afs_wg_id AS CHAR) AS entity_id,
                        NULL AS entity_code,
                        c.name_default AS entity_name,
                        NULL AS language_code,
                        NULL AS language_count,
                        NULL AS languages,
                        NULL AS detail_value
                   FROM stage_categories c
                   LEFT JOIN stage_category_translations t
                       ON t.afs_wg_id = c.afs_wg_id
                   WHERE c.afs_wg_id IS NOT NULL
                     AND t.afs_wg_id IS NULL
                   ORDER BY c.afs_wg_id ASC
                   LIMIT :limit OFFSET :offset";

            return [$sql, $params];
        }

        if ($languageCode !== '') {
            $params[':language_code'] = $languageCode;
        }

        $countFilterJoin = $languageCode !== ''
            ? 'INNER JOIN stage_category_translations tf
                   ON tf.afs_wg_id = c.afs_wg_id
                  AND tf.language_code = :language_code'
            : '';

        $detailFilterJoin = $languageCode !== ''
            ? 'INNER JOIN stage_category_translations tf
                   ON tf.afs_wg_id = c.afs_wg_id
                  AND tf.language_code = :language_code'
            : '';

        $sql = $countOnly
            ? "SELECT COUNT(*) FROM (
                    SELECT c.afs_wg_id
                    FROM stage_categories c
                    INNER JOIN stage_category_translations t
                        ON t.afs_wg_id = c.afs_wg_id
                    {$countFilterJoin}
                    GROUP BY c.afs_wg_id
               ) detail_count_rows"
            : "SELECT
                    'category' AS entity_type,
                    'translated' AS coverage,
                    CAST(c.afs_wg_id AS CHAR) AS entity_id,
                    NULL AS entity_code,
                    c.name_default AS entity_name,
                    " . ($languageCode !== '' ? ':language_code' : 'NULL') . " AS language_code,
                    COUNT(DISTINCT t.language_code) AS language_count,
                    GROUP_CONCAT(DISTINCT t.language_code ORDER BY t.language_code SEPARATOR ', ') AS languages,
                    NULL AS detail_value
               FROM stage_categories c
               INNER JOIN stage_category_translations t
                   ON t.afs_wg_id = c.afs_wg_id
               {$detailFilterJoin}
               GROUP BY c.afs_wg_id, c.name_default
               ORDER BY c.afs_wg_id ASC
               LIMIT :limit OFFSET :offset";

        return [$sql, $params];
    }

    private function attributeDetailQueryParts(string $coverage, string $languageCode, bool $countOnly): array
    {
        $params = [];
        $languageWhere = $languageCode !== '' ? 'AND a.language_code = :language_code' : '';
        $attributeLanguageJoin = "LEFT JOIN (
                    SELECT
                        at.afs_artikel_id,
                        at.sort_order,
                        GROUP_CONCAT(DISTINCT at.language_code ORDER BY at.language_code SEPARATOR ', ') AS languages
                    FROM stage_attribute_translations at
                    GROUP BY at.afs_artikel_id, at.sort_order
               ) al
                   ON al.afs_artikel_id = a.afs_artikel_id
                  AND al.sort_order = a.sort_order";

        if ($languageCode !== '') {
            $params[':language_code'] = $languageCode;
        }

        if ($coverage === 'orphan') {
            $sql = $countOnly
                ? "SELECT COUNT(*)
                   FROM stage_attribute_translations a
                   LEFT JOIN stage_products p
                       ON p.afs_artikel_id = a.afs_artikel_id
                   WHERE a.afs_artikel_id IS NOT NULL
                     AND p.afs_artikel_id IS NULL
                     {$languageWhere}"
                : "SELECT
                        'attribute' AS entity_type,
                        'orphan' AS coverage,
                        CAST(a.afs_artikel_id AS CHAR) AS entity_id,
                        a.sku AS entity_code,
                        a.attribute_name AS entity_name,
                        a.language_code AS language_code,
                        NULL AS language_count,
                        al.languages AS languages,
                        a.attribute_value AS detail_value
                   FROM stage_attribute_translations a
                   LEFT JOIN stage_products p
                       ON p.afs_artikel_id = a.afs_artikel_id
                   {$attributeLanguageJoin}
                   WHERE a.afs_artikel_id IS NOT NULL
                     AND p.afs_artikel_id IS NULL
                     {$languageWhere}
                   ORDER BY a.afs_artikel_id ASC, a.language_code ASC, a.sort_order ASC
                   LIMIT :limit OFFSET :offset";

            return [$sql, $params];
        }

        if ($coverage === 'missing') {
            $sql = $countOnly
                ? "SELECT COUNT(*)
                   FROM stage_attribute_translations a
                   INNER JOIN stage_products p
                       ON p.afs_artikel_id = a.afs_artikel_id
                   LEFT JOIN stage_product_translations t
                       ON t.afs_artikel_id = a.afs_artikel_id
                      AND t.language_code = a.language_code
                   WHERE a.afs_artikel_id IS NOT NULL
                     AND t.afs_artikel_id IS NULL
                     {$languageWhere}"
                : "SELECT
                        'attribute' AS entity_type,
                        'missing' AS coverage,
                        CAST(a.afs_artikel_id AS CHAR) AS entity_id,
                        p.sku AS entity_code,
                        a.attribute_name AS entity_name,
                        a.language_code AS language_code,
                        NULL AS language_count,
                        al.languages AS languages,
                        a.attribute_value AS detail_value
                   FROM stage_attribute_translations a
                   INNER JOIN stage_products p
                       ON p.afs_artikel_id = a.afs_artikel_id
                   LEFT JOIN stage_product_translations t
                       ON t.afs_artikel_id = a.afs_artikel_id
                      AND t.language_code = a.language_code
                   {$attributeLanguageJoin}
                   WHERE a.afs_artikel_id IS NOT NULL
                     AND t.afs_artikel_id IS NULL
                     {$languageWhere}
                   ORDER BY a.afs_artikel_id ASC, a.language_code ASC, a.sort_order ASC
                   LIMIT :limit OFFSET :offset";

            return [$sql, $params];
        }

        $sql = $countOnly
            ? "SELECT COUNT(*)
               FROM stage_attribute_translations a
               INNER JOIN stage_products p
                   ON p.afs_artikel_id = a.afs_artikel_id
               INNER JOIN stage_product_translations t
                   ON t.afs_artikel_id = a.afs_artikel_id
                  AND t.language_code = a.language_code
               WHERE 1=1 {$languageWhere}"
            : "SELECT
                    'attribute' AS entity_type,
                    'translated' AS coverage,
                    CAST(a.afs_artikel_id AS CHAR) AS entity_id,
                    p.sku AS entity_code,
                    a.attribute_name AS entity_name,
                    a.language_code AS language_code,
                    NULL AS language_count,
                    al.languages AS languages,
                    a.attribute_value AS detail_value
               FROM stage_attribute_translations a
               INNER JOIN stage_products p
                   ON p.afs_artikel_id = a.afs_artikel_id
               INNER JOIN stage_product_translations t
                   ON t.afs_artikel_id = a.afs_artikel_id
                  AND t.language_code = a.language_code
               {$attributeLanguageJoin}
               WHERE 1=1 {$languageWhere}
               ORDER BY a.afs_artikel_id ASC, a.language_code ASC, a.sort_order ASC
               LIMIT :limit OFFSET :offset";

        return [$sql, $params];
    }
}
