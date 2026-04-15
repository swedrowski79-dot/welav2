<?php

declare(strict_types=1);

namespace App\Web\Repository;

final class StatusRepository
{
    private const MYSQL_TABLE_NOT_FOUND = 1146;

    public function __construct(private \PDO $stageDb, private array $adminConfig)
    {
    }

    public function tableCounts(): array
    {
        $tables = $this->adminConfig['stage_tables'] ?? [];

        $counts = [];
        foreach ($tables as $table => $label) {
            if (!is_string($table) || $table === '') {
                continue;
            }

            $counts[] = [
                'table' => $table,
                'label' => is_string($label) && $label !== '' ? $label : $table,
                'count' => $this->countTable($table),
            ];
        }

        return $counts;
    }

    private function countTable(string $table): int
    {
        try {
            $stmt = $this->stageDb->query("SELECT COUNT(*) FROM `{$table}`");

            return (int) $stmt->fetchColumn();
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
}
