<?php

declare(strict_types=1);

namespace App\Web\Repository;

use App\Web\Core\Paginator;
use RuntimeException;

final class StageBrowserRepository
{
    /** @var array<string, array<int, array<string, mixed>>> */
    private array $schemaCache = [];

    public function __construct(
        private \PDO $stageDb,
        private array $allowedTables
    ) {
    }

    public function tables(): array
    {
        return $this->allowedTables;
    }

    public function schema(string $table): array
    {
        $this->assertAllowed($table);

        if (isset($this->schemaCache[$table])) {
            return $this->schemaCache[$table];
        }

        $stmt = $this->stageDb->query("SHOW COLUMNS FROM `{$table}`");
        return $this->schemaCache[$table] = $stmt->fetchAll(\PDO::FETCH_ASSOC);
    }

    public function primaryKey(string $table): string
    {
        foreach ($this->schema($table) as $column) {
            if (($column['Key'] ?? '') === 'PRI') {
                return (string) $column['Field'];
            }
        }

        throw new RuntimeException("No primary key found for {$table}");
    }

    public function paginatedRows(string $table, string $search, Paginator $paginator): array
    {
        $columns = $this->schema($table);
        $whereSql = '';
        $params = [];

        if ($search !== '') {
            $searchParts = [];
            foreach ($columns as $column) {
                $field = (string) $column['Field'];
                $searchParts[] = "CAST(`{$field}` AS CHAR) LIKE :search";
            }
            $whereSql = 'WHERE ' . implode(' OR ', $searchParts);
            $params[':search'] = '%' . $search . '%';
        }

        $primaryKey = $this->primaryKey($table);
        $stmt = $this->stageDb->prepare("SELECT * FROM `{$table}` {$whereSql} ORDER BY `{$primaryKey}` DESC LIMIT :limit OFFSET :offset");

        foreach ($params as $key => $value) {
            $stmt->bindValue($key, $value);
        }

        $stmt->bindValue(':limit', $paginator->perPage, \PDO::PARAM_INT);
        $stmt->bindValue(':offset', $paginator->offset(), \PDO::PARAM_INT);
        $stmt->execute();

        return $stmt->fetchAll(\PDO::FETCH_ASSOC);
    }

    public function countRows(string $table, string $search): int
    {
        $columns = $this->schema($table);
        $whereSql = '';
        $params = [];

        if ($search !== '') {
            $searchParts = [];
            foreach ($columns as $column) {
                $field = (string) $column['Field'];
                $searchParts[] = "CAST(`{$field}` AS CHAR) LIKE :search";
            }
            $whereSql = 'WHERE ' . implode(' OR ', $searchParts);
            $params[':search'] = '%' . $search . '%';
        }

        $stmt = $this->stageDb->prepare("SELECT COUNT(*) FROM `{$table}` {$whereSql}");
        $stmt->execute($params);

        return (int) $stmt->fetchColumn();
    }

    public function findRow(string $table, string|int $id): ?array
    {
        $primaryKey = $this->primaryKey($table);
        $stmt = $this->stageDb->prepare("SELECT * FROM `{$table}` WHERE `{$primaryKey}` = :id LIMIT 1");
        $stmt->bindValue(':id', (string) $id);
        $stmt->execute();

        $row = $stmt->fetch(\PDO::FETCH_ASSOC);
        return $row ?: null;
    }

    public function editableColumns(string $table): array
    {
        $primaryKey = $this->primaryKey($table);
        $editable = [];

        foreach ($this->schema($table) as $column) {
            $field = (string) ($column['Field'] ?? '');
            if ($field === '' || $field === $primaryKey) {
                continue;
            }

            if (str_contains((string) ($column['Extra'] ?? ''), 'auto_increment')) {
                continue;
            }

            $editable[] = $field;
        }

        return $editable;
    }

    public function updateField(string $table, string|int $id, string $field, string $value): array
    {
        $schema = $this->columnSchema($table, $field);
        $primaryKey = $this->primaryKey($table);

        if ($field === $primaryKey) {
            throw new RuntimeException('Primary key cannot be edited.');
        }

        $normalizedValue = $this->normalizeValue($schema, $value);
        $statement = $this->stageDb->prepare("UPDATE `{$table}` SET `{$field}` = :value WHERE `{$primaryKey}` = :id LIMIT 1");
        $statement->bindValue(':id', (string) $id);

        if ($normalizedValue === null) {
            $statement->bindValue(':value', null, \PDO::PARAM_NULL);
        } else {
            $statement->bindValue(':value', $normalizedValue);
        }

        $statement->execute();

        if ($statement->rowCount() === 0 && $this->findRow($table, $id) === null) {
            throw new RuntimeException('Record not found.');
        }

        $row = $this->findRow($table, $id);
        if ($row === null) {
            throw new RuntimeException('Record not found.');
        }

        return $row;
    }

    private function columnSchema(string $table, string $field): array
    {
        foreach ($this->schema($table) as $column) {
            if (($column['Field'] ?? null) === $field) {
                return $column;
            }
        }

        throw new RuntimeException('Column is not allowed.');
    }

    private function normalizeValue(array $schema, string $value): mixed
    {
        $trimmed = trim($value);
        $nullable = strtoupper((string) ($schema['Null'] ?? '')) === 'YES';

        if ($nullable && $trimmed === '') {
            return null;
        }

        $type = strtolower((string) ($schema['Type'] ?? ''));

        if (preg_match('/^(tinyint|smallint|mediumint|int|bigint)\b/', $type)) {
            if ($trimmed === '') {
                return 0;
            }

            if (!preg_match('/^-?\d+$/', $trimmed)) {
                throw new RuntimeException('Bitte eine gueltige Ganzzahl eingeben.');
            }

            return (int) $trimmed;
        }

        if (preg_match('/^(decimal|float|double)\b/', $type)) {
            if ($trimmed === '') {
                return 0;
            }

            if (!is_numeric($trimmed)) {
                throw new RuntimeException('Bitte eine gueltige Zahl eingeben.');
            }

            return (float) $trimmed;
        }

        return $value;
    }

    private function assertAllowed(string $table): void
    {
        if (!isset($this->allowedTables[$table])) {
            throw new RuntimeException('Table is not allowed.');
        }
    }
}
