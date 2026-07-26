<?php

declare(strict_types=1);

namespace App\Web\Repository;

use App\Web\Core\Paginator;
use RuntimeException;

final class AttributeDictionaryRepository
{
    private const TABLE = 'attribute_translations';
    private const LANGUAGES = ['de', 'en', 'fr', 'nl'];

    public function __construct(private \PDO $extraDb)
    {
    }

    public function metrics(): array
    {
        return [
            'total' => $this->countByStatus(''),
            'active' => $this->countByStatus('1'),
            'inactive' => $this->countByStatus('0'),
        ];
    }

    public function summary(): array
    {
        $activeTotal = $this->countByStatus('1');
        $fullyTranslated = $this->countQuery(
            'SELECT COUNT(*)
             FROM `' . self::TABLE . '`
             WHERE `is_active` = 1
               AND COALESCE(NULLIF(TRIM(`en`), \'\'), NULL) IS NOT NULL
               AND COALESCE(NULLIF(TRIM(`fr`), \'\'), NULL) IS NOT NULL
               AND COALESCE(NULLIF(TRIM(`nl`), \'\'), NULL) IS NOT NULL'
        );

        $languageCounts = [];
        foreach (self::LANGUAGES as $languageCode) {
            if ($languageCode === 'de') {
                $languageCounts[$languageCode] = $activeTotal;
                continue;
            }

            $languageCounts[$languageCode] = $this->countQuery(
                'SELECT COUNT(*)
                 FROM `' . self::TABLE . '`
                 WHERE `is_active` = 1
                   AND COALESCE(NULLIF(TRIM(`' . $languageCode . '`), \'\'), NULL) IS NOT NULL'
            );
        }

        return [
            'attribute_rows_total' => $activeTotal,
            'attribute_rows_translated' => $fullyTranslated,
            'attribute_rows_missing' => max(0, $activeTotal - $fullyTranslated),
            'attribute_rows_by_language' => $languageCounts,
        ];
    }

    public function countDetailRows(array $filters): int
    {
        [$sql, $params] = $this->detailQueryParts($filters, true);
        $stmt = $this->extraDb->prepare($sql);
        $stmt->execute($params);

        return (int) $stmt->fetchColumn();
    }

    public function paginatedDetailRows(array $filters, Paginator $paginator): array
    {
        [$sql, $params] = $this->detailQueryParts($filters, false);
        $stmt = $this->extraDb->prepare($sql);

        foreach ($params as $key => $value) {
            $stmt->bindValue($key, $value);
        }

        $stmt->bindValue(':limit', $paginator->perPage, \PDO::PARAM_INT);
        $stmt->bindValue(':offset', $paginator->offset(), \PDO::PARAM_INT);
        $stmt->execute();

        return $stmt->fetchAll(\PDO::FETCH_ASSOC);
    }

    public function countRows(string $search, string $status): int
    {
        [$whereSql, $params] = $this->filterParts($search, $status);
        $stmt = $this->extraDb->prepare('SELECT COUNT(*) FROM `' . self::TABLE . '` ' . $whereSql);
        $stmt->execute($params);

        return (int) $stmt->fetchColumn();
    }

    public function paginatedRows(string $search, string $status, Paginator $paginator): array
    {
        [$whereSql, $params] = $this->filterParts($search, $status);
        $stmt = $this->extraDb->prepare(
            'SELECT `id`, `source_text`, `normalized_key`, `de`, `en`, `fr`, `nl`, `source_directory`, `is_active`
             FROM `' . self::TABLE . '`
             ' . $whereSql . '
             ORDER BY `is_active` DESC, `source_text` ASC, `id` ASC
             LIMIT :limit OFFSET :offset'
        );

        foreach ($params as $key => $value) {
            $stmt->bindValue($key, $value);
        }

        $stmt->bindValue(':limit', $paginator->perPage, \PDO::PARAM_INT);
        $stmt->bindValue(':offset', $paginator->offset(), \PDO::PARAM_INT);
        $stmt->execute();

        return $stmt->fetchAll(\PDO::FETCH_ASSOC);
    }

    public function findRow(string|int $id): ?array
    {
        $stmt = $this->extraDb->prepare(
            'SELECT `id`, `source_text`, `normalized_key`, `de`, `en`, `fr`, `nl`, `source_directory`, `is_active`
             FROM `' . self::TABLE . '`
             WHERE `id` = :id
             LIMIT 1'
        );
        $stmt->bindValue(':id', (int) $id, \PDO::PARAM_INT);
        $stmt->execute();

        $row = $stmt->fetch(\PDO::FETCH_ASSOC);

        return $row ?: null;
    }

    public function editableColumns(): array
    {
        return ['en', 'fr', 'nl', 'source_directory', 'is_active'];
    }

    public function updateField(string|int $id, string $field, string $value): array
    {
        if (!in_array($field, $this->editableColumns(), true)) {
            throw new RuntimeException('Column is not allowed.');
        }

        $normalizedValue = $this->normalizeValue($field, $value);
        $statement = $this->extraDb->prepare(
            'UPDATE `' . self::TABLE . '` SET `' . $field . '` = :value WHERE `id` = :id LIMIT 1'
        );
        $statement->bindValue(':id', (int) $id, \PDO::PARAM_INT);

        if ($normalizedValue === null) {
            $statement->bindValue(':value', null, \PDO::PARAM_NULL);
        } elseif ($field === 'is_active') {
            $statement->bindValue(':value', (int) $normalizedValue, \PDO::PARAM_INT);
        } else {
            $statement->bindValue(':value', $normalizedValue);
        }

        $statement->execute();

        if ($statement->rowCount() === 0 && $this->findRow($id) === null) {
            throw new RuntimeException('Record not found.');
        }

        $row = $this->findRow($id);
        if ($row === null) {
            throw new RuntimeException('Record not found.');
        }

        return $row;
    }

    public function deleteRows(array $ids): int
    {
        $normalizedIds = array_values(array_unique(array_filter(array_map(
            static fn (mixed $value): int => (int) $value,
            $ids
        ), static fn (int $value): bool => $value > 0)));

        if ($normalizedIds === []) {
            return 0;
        }

        $placeholders = [];
        $params = [];

        foreach ($normalizedIds as $index => $id) {
            $placeholder = ':id_' . $index;
            $placeholders[] = $placeholder;
            $params[$placeholder] = $id;
        }

        $stmt = $this->extraDb->prepare(
            'DELETE FROM `' . self::TABLE . '`
             WHERE `id` IN (' . implode(', ', $placeholders) . ')'
        );
        $stmt->execute($params);

        return $stmt->rowCount();
    }

    private function countByStatus(string $status): int
    {
        [$whereSql, $params] = $this->filterParts('', $status);
        return $this->countQuery('SELECT COUNT(*) FROM `' . self::TABLE . '` ' . $whereSql, $params);
    }

    private function countQuery(string $sql, array $params = []): int
    {
        $stmt = $this->extraDb->prepare($sql);
        $stmt->execute($params);

        return (int) $stmt->fetchColumn();
    }

    private function detailQueryParts(array $filters, bool $countOnly): array
    {
        $coverage = (string) ($filters['coverage'] ?? 'translated');
        $languageCode = (string) ($filters['language_code'] ?? '');
        $params = [];
        $conditions = [];

        if ($coverage === 'orphan') {
            $conditions[] = '`is_active` = 0';
        } else {
            $conditions[] = '`is_active` = 1';

            if ($coverage === 'missing') {
                $conditions[] = $this->missingCondition($languageCode);
            } else {
                $conditions[] = $this->translatedCondition($languageCode);
            }
        }

        $whereSql = 'WHERE ' . implode(' AND ', $conditions);

        if ($countOnly) {
            return ['SELECT COUNT(*) FROM `' . self::TABLE . '` ' . $whereSql, $params];
        }

        return [
            'SELECT
                \'attribute\' AS entity_type,
                :coverage AS coverage,
                CAST(`id` AS CHAR) AS entity_id,
                `normalized_key` AS entity_code,
                `source_text` AS entity_name,
                NULL AS language_code,
                NULL AS language_count,
                ' . $this->languagesExpression() . ' AS languages,
                NULL AS detail_value,
                CAST(`id` AS CHAR) AS dictionary_id,
                `source_text`,
                `normalized_key`,
                `de`,
                `en`,
                `fr`,
                `nl`,
                `source_directory`,
                CAST(`is_active` AS CHAR) AS is_active
             FROM `' . self::TABLE . '`
             ' . $whereSql . '
             ORDER BY `source_text` ASC, `id` ASC
             LIMIT :limit OFFSET :offset',
            [':coverage' => $coverage] + $params,
        ];
    }

    private function translatedCondition(string $languageCode): string
    {
        if ($languageCode !== '') {
            return $this->languagePresentCondition($languageCode);
        }

        return implode(' AND ', [
            $this->languagePresentCondition('en'),
            $this->languagePresentCondition('fr'),
            $this->languagePresentCondition('nl'),
        ]);
    }

    private function missingCondition(string $languageCode): string
    {
        if ($languageCode !== '') {
            return 'NOT (' . $this->languagePresentCondition($languageCode) . ')';
        }

        return '(' . implode(' OR ', [
            'NOT (' . $this->languagePresentCondition('en') . ')',
            'NOT (' . $this->languagePresentCondition('fr') . ')',
            'NOT (' . $this->languagePresentCondition('nl') . ')',
        ]) . ')';
    }

    private function languagePresentCondition(string $languageCode): string
    {
        return 'COALESCE(NULLIF(TRIM(`' . $languageCode . '`), \'\'), NULL) IS NOT NULL';
    }

    private function languagesExpression(): string
    {
        $parts = [];

        foreach (self::LANGUAGES as $languageCode) {
            $parts[] = 'IF(' . $this->languagePresentCondition($languageCode) . ", '" . $languageCode . "', NULL)";
        }

        return 'CONCAT_WS(\', \', ' . implode(', ', $parts) . ')';
    }

    private function filterParts(string $search, string $status): array
    {
        $parts = [];
        $params = [];

        if ($status === '1' || $status === '0') {
            $parts[] = '`is_active` = :is_active';
            $params[':is_active'] = (int) $status;
        }

        if ($search !== '') {
            $parts[] = '('
                . 'CAST(`source_text` AS CHAR) LIKE :search OR '
                . 'CAST(`normalized_key` AS CHAR) LIKE :search OR '
                . 'CAST(`de` AS CHAR) LIKE :search OR '
                . 'CAST(`en` AS CHAR) LIKE :search OR '
                . 'CAST(`fr` AS CHAR) LIKE :search OR '
                . 'CAST(`nl` AS CHAR) LIKE :search OR '
                . 'CAST(`source_directory` AS CHAR) LIKE :search'
                . ')';
            $params[':search'] = '%' . $search . '%';
        }

        $whereSql = $parts === [] ? '' : 'WHERE ' . implode(' AND ', $parts);

        return [$whereSql, $params];
    }

    private function normalizeValue(string $field, string $value): mixed
    {
        $trimmed = trim($value);

        if ($field === 'is_active') {
            if (!in_array($trimmed, ['0', '1'], true)) {
                throw new RuntimeException('Bitte 0 oder 1 eingeben.');
            }

            return (int) $trimmed;
        }

        return $trimmed === '' ? null : $value;
    }
}
