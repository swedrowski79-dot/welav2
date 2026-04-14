<?php

class StageWriter
{
    private array $columnTypeCache = [];
    private array $insertStatementCache = [];

    public function __construct(private PDO $stageDb)
    {
    }

    public function truncate(string $table): void
    {
        $this->stageDb->exec("TRUNCATE TABLE `{$table}`");
    }

    public function insert(string $table, array $data): void
    {
        $data = $this->normalizeForTable($table, $data);
        $stmt = $this->singleInsertStatement($table, array_keys($data));

        foreach ($data as $key => $value) {
            $stmt->bindValue(':' . $key, $value);
        }

        $stmt->execute();
    }

    public function insertMany(string $table, array $rows): void
    {
        if ($rows === []) {
            return;
        }

        if (count($rows) === 1) {
            $this->insert($table, $rows[0]);
            return;
        }

        $normalizedRows = [];
        $columns = null;

        foreach ($rows as $row) {
            $normalized = $this->normalizeForTable($table, $row);
            $rowColumns = array_keys($normalized);

            if ($columns === null) {
                $columns = $rowColumns;
            } elseif ($columns !== $rowColumns) {
                foreach ($normalizedRows as $normalizedRow) {
                    $this->insert($table, $normalizedRow);
                }
                $this->insert($table, $normalized);

                return;
            }

            $normalizedRows[] = $normalized;
        }

        if ($columns === null || $columns === []) {
            return;
        }

        $valueGroups = [];
        $params = [];

        foreach ($normalizedRows as $rowIndex => $row) {
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

    private function normalizeForTable(string $table, array $data): array
    {
        $columnTypes = $this->columnTypes($table);

        foreach ($data as $column => $value) {
            if (!$this->isNumericColumn($columnTypes[$column] ?? null)) {
                continue;
            }

            if (is_string($value)) {
                $value = trim($value);
            }

            if ($value === '') {
                $data[$column] = null;
            }
        }

        return $data;
    }

    private function columnTypes(string $table): array
    {
        if (isset($this->columnTypeCache[$table])) {
            return $this->columnTypeCache[$table];
        }

        $stmt = $this->stageDb->query("SHOW COLUMNS FROM `{$table}`");
        $types = [];

        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $field = $row['Field'] ?? null;
            $type = $row['Type'] ?? null;

            if (!is_string($field) || !is_string($type)) {
                continue;
            }

            $types[$field] = strtolower($type);
        }

        $this->columnTypeCache[$table] = $types;

        return $types;
    }

    private function isNumericColumn(?string $type): bool
    {
        if ($type === null) {
            return false;
        }

        foreach (['int', 'decimal', 'float', 'double', 'numeric', 'real', 'bit'] as $keyword) {
            if (str_contains($type, $keyword)) {
                return true;
            }
        }

        return false;
    }

    private function singleInsertStatement(string $table, array $columns): PDOStatement
    {
        $cacheKey = $table . '|' . implode('|', $columns);

        if (isset($this->insertStatementCache[$cacheKey])) {
            return $this->insertStatementCache[$cacheKey];
        }

        $placeholders = array_map(fn ($column) => ':' . $column, $columns);
        $sql = "INSERT INTO `{$table}` (`" . implode('`,`', $columns) . "`) VALUES (" . implode(',', $placeholders) . ")";

        return $this->insertStatementCache[$cacheKey] = $this->stageDb->prepare($sql);
    }
}
