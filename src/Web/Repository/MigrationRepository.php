<?php

declare(strict_types=1);

namespace App\Web\Repository;

final class MigrationRepository
{
    public function __construct(private \PDO $stageDb, private string $migrationDir)
    {
    }

    public function summary(): array
    {
        $files = $this->migrationFiles();
        $applied = $this->appliedMigrations();

        return [
            'total' => count($files),
            'applied' => count($applied),
            'pending' => count(array_diff(array_keys($files), $applied)),
            'files' => array_keys($files),
        ];
    }

    public function runPending(): array
    {
        $this->ensureSchemaMigrationsTable();

        $files = $this->migrationFiles();
        $applied = $this->appliedMigrations();
        $executed = [];

        foreach ($files as $version => $path) {
            if (in_array($version, $applied, true)) {
                continue;
            }

            $sql = trim((string) file_get_contents($path));

            if ($sql === '') {
                continue;
            }

            try {
                $this->stageDb->beginTransaction();
                $this->stageDb->exec($sql);

                $stmt = $this->stageDb->prepare(
                    'INSERT INTO schema_migrations (version, filename, executed_at)
                     VALUES (:version, :filename, NOW())'
                );
                $stmt->execute([
                    ':version' => $version,
                    ':filename' => basename($path),
                ]);

                $this->stageDb->commit();
                $executed[] = basename($path);
            } catch (\Throwable $exception) {
                if ($this->stageDb->inTransaction()) {
                    $this->stageDb->rollBack();
                }

                $this->logError($version, $exception->getMessage());

                throw new \RuntimeException(
                    sprintf('Migration `%s` fehlgeschlagen: %s', basename($path), $exception->getMessage()),
                    0,
                    $exception
                );
            }
        }

        $this->logInfo('Migrationen wurden ausgefuehrt.', [
            'action' => 'run_migrations',
            'executed' => $executed,
            'executed_count' => count($executed),
        ]);

        return $executed;
    }

    private function ensureSchemaMigrationsTable(): void
    {
        $this->stageDb->exec(
            'CREATE TABLE IF NOT EXISTS schema_migrations (
                id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                version VARCHAR(50) NOT NULL,
                filename VARCHAR(255) NOT NULL,
                executed_at DATETIME NOT NULL,
                UNIQUE KEY uniq_schema_migrations_version (version)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4'
        );
    }

    private function appliedMigrations(): array
    {
        if (!$this->tableExists('schema_migrations')) {
            return [];
        }

        $stmt = $this->stageDb->query('SELECT version FROM schema_migrations ORDER BY version ASC');

        return array_map(
            static fn (array $row): string => (string) $row['version'],
            $stmt->fetchAll(\PDO::FETCH_ASSOC)
        );
    }

    private function migrationFiles(): array
    {
        $paths = glob($this->migrationDir . '/*.sql') ?: [];
        sort($paths);
        $files = [];

        foreach ($paths as $path) {
            $version = pathinfo($path, PATHINFO_FILENAME);
            $files[$version] = $path;
        }

        return $files;
    }

    private function tableExists(string $table): bool
    {
        $stmt = $this->stageDb->prepare('SHOW TABLES LIKE :table');
        $stmt->execute([':table' => $table]);

        return (bool) $stmt->fetchColumn();
    }

    private function logInfo(string $message, array $context): void
    {
        if (!$this->tableExists('sync_logs')) {
            return;
        }

        $stmt = $this->stageDb->prepare(
            'INSERT INTO sync_logs (sync_run_id, level, message, context_json, created_at)
             VALUES (NULL, :level, :message, :context_json, NOW())'
        );
        $stmt->execute([
            ':level' => 'info',
            ':message' => $message,
            ':context_json' => json_encode($context, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
        ]);
    }

    private function logError(string $version, string $message): void
    {
        if ($this->tableExists('sync_logs')) {
            $stmt = $this->stageDb->prepare(
                'INSERT INTO sync_logs (sync_run_id, level, message, context_json, created_at)
                 VALUES (NULL, :level, :message, :context_json, NOW())'
            );
            $stmt->execute([
                ':level' => 'error',
                ':message' => 'Migration fehlgeschlagen.',
                ':context_json' => json_encode(['version' => $version, 'error' => $message], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
            ]);
        }

        if ($this->tableExists('sync_errors')) {
            $stmt = $this->stageDb->prepare(
                'INSERT INTO sync_errors (sync_run_id, source, record_identifier, message, details, status, created_at)
                 VALUES (NULL, :source, :record_identifier, :message, :details, :status, NOW())'
            );
            $stmt->execute([
                ':source' => 'migrations',
                ':record_identifier' => $version,
                ':message' => 'Migration fehlgeschlagen.',
                ':details' => json_encode(['version' => $version, 'error' => $message], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
                ':status' => 'open',
            ]);
        }
    }
}
