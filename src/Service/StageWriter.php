<?php

class StageWriter
{
    public function __construct(private PDO $stageDb)
    {
    }

    public function truncate(string $table): void
    {
        $this->stageDb->exec("TRUNCATE TABLE `{$table}`");
    }

    public function insert(string $table, array $data): void
    {
        $columns = array_keys($data);
        $placeholders = array_map(fn ($c) => ':' . $c, $columns);

        $sql = "INSERT INTO `{$table}` (`" . implode('`,`', $columns) . "`) VALUES (" . implode(',', $placeholders) . ")";
        $stmt = $this->stageDb->prepare($sql);

        foreach ($data as $key => $value) {
            $stmt->bindValue(':' . $key, $value);
        }

        $stmt->execute();
    }
}
