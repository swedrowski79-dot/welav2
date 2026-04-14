<?php

declare(strict_types=1);

namespace App\Web\Repository;

use App\Web\Core\Paginator;
use RuntimeException;

final class StageBrowserRepository
{
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

        $stmt = $this->stageDb->query("SHOW COLUMNS FROM `{$table}`");
        return $stmt->fetchAll(\PDO::FETCH_ASSOC);
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

    private function assertAllowed(string $table): void
    {
        if (!isset($this->allowedTables[$table])) {
            throw new RuntimeException('Table is not allowed.');
        }
    }
}
