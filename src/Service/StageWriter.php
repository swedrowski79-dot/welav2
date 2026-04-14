<?php

class StageWriter
{
    private array $columnTypeCache = [];

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
        $columns = array_keys($data);
        $placeholders = array_map(fn ($c) => ':' . $c, $columns);

        $sql = "INSERT INTO `{$table}` (`" . implode('`,`', $columns) . "`) VALUES (" . implode(',', $placeholders) . ")";
        $stmt = $this->stageDb->prepare($sql);

        foreach ($data as $key => $value) {
            $stmt->bindValue(':' . $key, $value);
        }

        $stmt->execute();
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
}
