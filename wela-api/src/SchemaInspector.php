<?php

declare(strict_types=1);

namespace WelaApi;

use PDO;

final class SchemaInspector
{
    private array $tableColumnsCache = [];
    private array $tablePrimaryKeyCache = [];
    private array $uniqueIndexCache = [];

    public function __construct(private PDO $pdo)
    {
    }

    public function tableColumns(string $table): array
    {
        if (isset($this->tableColumnsCache[$table])) {
            return $this->tableColumnsCache[$table];
        }

        $stmt = $this->pdo->query(sprintf('SHOW COLUMNS FROM `%s`', $table));
        $columns = $stmt->fetchAll(PDO::FETCH_COLUMN);
        $columns = array_values(array_filter($columns, static fn (mixed $column): bool => is_string($column) && $column !== ''));

        if ($columns === []) {
            \wela_respond(400, [
                'ok' => false,
                'error' => 'XT-Tabelle enthaelt keine lesbaren Spalten.',
            ]);
        }

        return $this->tableColumnsCache[$table] = $columns;
    }

    public function tablePrimaryKey(string $table): string|array
    {
        if (isset($this->tablePrimaryKeyCache[$table])) {
            return $this->tablePrimaryKeyCache[$table];
        }

        $stmt = $this->pdo->query(sprintf("SHOW INDEX FROM `%s` WHERE Key_name = 'PRIMARY'", $table));
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

        if ($rows !== []) {
            usort($rows, static fn (array $left, array $right): int => ((int) ($left['Seq_in_index'] ?? 0)) <=> ((int) ($right['Seq_in_index'] ?? 0)));
            $fields = array_values(array_filter(array_map(
                static fn (array $row): ?string => is_string($row['Column_name'] ?? null) ? $row['Column_name'] : null,
                $rows
            )));

            if ($fields !== []) {
                return $this->tablePrimaryKeyCache[$table] = (count($fields) === 1 ? $fields[0] : $fields);
            }
        }

        $columns = $this->tableColumns($table);

        return $this->tablePrimaryKeyCache[$table] = $columns[0];
    }

    public function tableHasUniqueIndex(string $table, array $fields): bool
    {
        $normalizedFields = array_values(array_filter(array_map(
            static fn (mixed $field): ?string => is_string($field) && $field !== '' ? $field : null,
            $fields
        )));
        sort($normalizedFields);

        if ($normalizedFields === []) {
            return false;
        }

        $cacheKey = $table . '|' . implode('|', $normalizedFields);
        if (array_key_exists($cacheKey, $this->uniqueIndexCache)) {
            return $this->uniqueIndexCache[$cacheKey];
        }

        $stmt = $this->pdo->query(sprintf('SHOW INDEX FROM `%s`', $table));
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
        $indexFields = [];

        foreach ($rows as $row) {
            if ((int) ($row['Non_unique'] ?? 1) !== 0) {
                continue;
            }

            $keyName = (string) ($row['Key_name'] ?? '');
            $columnName = is_string($row['Column_name'] ?? null) ? $row['Column_name'] : null;
            $seqInIndex = (int) ($row['Seq_in_index'] ?? 0);

            if ($keyName === '' || $columnName === null || $columnName === '' || $seqInIndex <= 0) {
                continue;
            }

            $indexFields[$keyName][$seqInIndex] = $columnName;
        }

        foreach ($indexFields as $columnsBySeq) {
            ksort($columnsBySeq);
            $candidateFields = array_values($columnsBySeq);
            sort($candidateFields);

            if ($candidateFields === $normalizedFields) {
                return $this->uniqueIndexCache[$cacheKey] = true;
            }
        }

        return $this->uniqueIndexCache[$cacheKey] = false;
    }
}
