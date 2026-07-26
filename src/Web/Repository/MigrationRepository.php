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

    public function lastResult(): ?array
    {
        $success = $this->latestMigrationSuccess();
        $error = $this->latestMigrationError();

        if ($success === null && $error === null) {
            return null;
        }

        if ($success !== null && $error !== null) {
            return strtotime((string) ($success['created_at'] ?? '')) >= strtotime((string) ($error['created_at'] ?? ''))
                ? $success
                : $error;
        }

        return $success ?? $error;
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
                if ($this->shouldSkipMigration($version, $sql)) {
                    $this->recordMigration($version, basename($path));
                    $executed[] = basename($path);
                    continue;
                }

                if ($this->applyPartialSafeMigration($version)) {
                    $this->recordMigration($version, basename($path));
                    $executed[] = basename($path);
                    continue;
                }

                $this->stageDb->beginTransaction();
                $this->stageDb->exec($sql);
                $this->recordMigration($version, basename($path));

                if ($this->stageDb->inTransaction()) {
                    $this->stageDb->commit();
                }
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
                version VARCHAR(191) NOT NULL,
                filename VARCHAR(255) NOT NULL,
                executed_at DATETIME NOT NULL,
                UNIQUE KEY uniq_schema_migrations_version (version)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4'
        );

        $versionLength = $this->columnLength('schema_migrations', 'version');

        if ($versionLength !== null && $versionLength < 191) {
            $this->stageDb->exec(
                'ALTER TABLE `schema_migrations`
                 MODIFY COLUMN `version` VARCHAR(191) NOT NULL'
            );
        }
    }

    private function shouldSkipMigration(string $version, string $sql): bool
    {
        if ($version === '001_add_stage_products_hash') {
            return $this->columnExists('stage_products', 'hash');
        }

        if ($version === '004_add_export_queue_claim_fields') {
            return $this->columnExists('export_queue', 'claim_token')
                && $this->columnExists('export_queue', 'claimed_at');
        }

        if ($version === '005_add_export_queue_retry_fields') {
            return $this->columnExists('export_queue', 'last_error')
                && $this->columnExists('export_queue', 'next_retry_at');
        }

        if ($version === '008_add_document_title_fields') {
            return $this->columnExists('raw_afs_documents', 'title')
                && $this->columnExists('stage_product_documents', 'title')
                && $this->columnExists('stage_product_documents', 'source_path');
        }

        if ($version === '009_add_raw_article_image_slots') {
            return $this->columnExists('raw_afs_articles', 'image_1')
                && $this->columnExists('raw_afs_articles', 'image_10');
        }

        if ($version === '011_add_media_document_delta_state') {
            return $this->columnExists('stage_product_media', 'hash')
                && $this->columnExists('stage_product_documents', 'hash')
                && $this->tableExists('product_media_export_state')
                && $this->tableExists('product_document_export_state');
        }

        if ($version === '012_create_xt_snapshot_tables') {
            return $this->tableExists('xt_products_snapshot')
                && $this->tableExists('xt_categories_snapshot')
                && $this->tableExists('xt_media_snapshot')
                && $this->tableExists('xt_documents_snapshot');
        }

        if ($version === '013_add_xt_product_snapshot_compare_fields') {
            return $this->columnExists('xt_products_snapshot', 'category_afs_id')
                && $this->columnExists('xt_products_snapshot', 'translation_hash')
                && $this->columnExists('xt_products_snapshot', 'attribute_hash')
                && $this->columnExists('xt_products_snapshot', 'seo_hash');
        }

        if ($version === '014_create_xt_mirror_tables') {
            return $this->tableExists('xt_mirror_products')
                && $this->tableExists('xt_mirror_categories')
                && $this->tableExists('xt_mirror_categories_description')
                && $this->tableExists('xt_mirror_products_description')
                && $this->tableExists('xt_mirror_products_to_categories')
                && $this->tableExists('xt_mirror_media')
                && $this->tableExists('xt_mirror_media_link')
                && $this->tableExists('xt_mirror_plg_products_attributes')
                && $this->tableExists('xt_mirror_plg_products_attributes_description')
                && $this->tableExists('xt_mirror_plg_products_to_attributes')
                && $this->tableExists('xt_mirror_seo_url');
        }

        if ($version === '015_add_category_delta_support') {
            return $this->columnExists('stage_categories', 'hash')
                && $this->tableExists('category_export_state');
        }

        if ($version === '016_add_stage_product_master_link') {
            return $this->columnExists('stage_products', 'master_afs_artikel_id');
        }

        if ($version === '017_add_raw_article_attribute_slots') {
            return $this->columnExists('raw_afs_articles', 'attribute_name1')
                && $this->columnExists('raw_afs_articles', 'attribute_name4')
                && $this->columnExists('raw_afs_articles', 'attribute_value1')
                && $this->columnExists('raw_afs_articles', 'attribute_value4');
        }

        if ($version === '018_create_raw_extra_attribute_translations') {
            return $this->tableExists('raw_extra_attribute_translations');
        }

        if ($version === '019_add_attribute_values_to_raw_extra_attribute_translations') {
            return $this->columnExists('raw_extra_attribute_translations', 'afs_artikel_id')
                && $this->columnExists('raw_extra_attribute_translations', 'sku')
                && $this->columnExists('raw_extra_attribute_translations', 'sort_order')
                && $this->columnExists('raw_extra_attribute_translations', 'attribute_value')
                && $this->columnExists('raw_extra_attribute_translations', 'source_directory')
                && $this->columnExists('raw_extra_attribute_translations', 'translated_value');
        }

        if ($version === '020_create_documents_file_table') {
            return $this->tableExists('documents_file')
                && $this->columnExists('documents_file', 'title')
                && $this->columnExists('documents_file', 'upload')
                && $this->columnExists('documents_file', 'shop_server_path');
        }

        if ($version === '021_add_intro_text_to_raw_extra_article_translations') {
            if (!$this->tableExists('raw_extra_article_translations')) {
                return false;
            }

            if ($this->columnExists('raw_extra_article_translations', 'intro_text')) {
                return true;
            }

            $this->stageDb->exec('ALTER TABLE `raw_extra_article_translations` ADD COLUMN `intro_text` MEDIUMTEXT NULL AFTER `name`');

            return true;
        }

        return false;
    }

    private function applyPartialSafeMigration(string $version): bool
    {
        if ($version === '005_add_export_queue_retry_fields') {
            if (!$this->tableExists('export_queue')) {
                return false;
            }

            $this->stageDb->beginTransaction();

            try {
                if (!$this->columnExists('export_queue', 'last_error')) {
                    $this->stageDb->exec('ALTER TABLE `export_queue` ADD COLUMN `last_error` TEXT NULL AFTER `claimed_at`');
                }

                if (!$this->columnExists('export_queue', 'next_retry_at')) {
                    $this->stageDb->exec('ALTER TABLE `export_queue` ADD COLUMN `next_retry_at` DATETIME NULL AFTER `last_error`');
                }

                if ($this->stageDb->inTransaction()) {
                    $this->stageDb->commit();
                }

                return true;
            } catch (\Throwable $exception) {
                if ($this->stageDb->inTransaction()) {
                    $this->stageDb->rollBack();
                }

                throw $exception;
            }
        }

        if ($version === '019_add_attribute_values_to_raw_extra_attribute_translations') {
            if (!$this->tableExists('raw_extra_attribute_translations')) {
                return false;
            }

            $this->stageDb->beginTransaction();

            try {
                if (!$this->columnExists('raw_extra_attribute_translations', 'afs_artikel_id')) {
                    $this->stageDb->exec('ALTER TABLE `raw_extra_attribute_translations` ADD COLUMN `afs_artikel_id` INT NULL AFTER `row_id`');
                }

                if (!$this->columnExists('raw_extra_attribute_translations', 'sku')) {
                    $this->stageDb->exec('ALTER TABLE `raw_extra_attribute_translations` ADD COLUMN `sku` VARCHAR(255) NULL AFTER `afs_artikel_id`');
                }

                if (!$this->columnExists('raw_extra_attribute_translations', 'sort_order')) {
                    $this->stageDb->exec('ALTER TABLE `raw_extra_attribute_translations` ADD COLUMN `sort_order` INT NULL AFTER `sku`');
                }

                if (!$this->columnExists('raw_extra_attribute_translations', 'attribute_value')) {
                    $this->stageDb->exec('ALTER TABLE `raw_extra_attribute_translations` ADD COLUMN `attribute_value` VARCHAR(255) NULL AFTER `attribute_name`');
                }

                if (!$this->columnExists('raw_extra_attribute_translations', 'source_directory')) {
                    $this->stageDb->exec('ALTER TABLE `raw_extra_attribute_translations` ADD COLUMN `source_directory` VARCHAR(255) NULL AFTER `language_code_normalized`');
                }

                if (!$this->columnExists('raw_extra_attribute_translations', 'translated_value')) {
                    $this->stageDb->exec('ALTER TABLE `raw_extra_attribute_translations` ADD COLUMN `translated_value` VARCHAR(255) NULL AFTER `translated_name`');
                }

                if (!$this->indexExists('raw_extra_attribute_translations', 'idx_raw_extra_attribute_translations_afs_artikel_id')) {
                    $this->stageDb->exec('ALTER TABLE `raw_extra_attribute_translations` ADD KEY `idx_raw_extra_attribute_translations_afs_artikel_id` (`afs_artikel_id`)');
                }

                if (!$this->indexExists('raw_extra_attribute_translations', 'idx_raw_extra_attribute_translations_sku')) {
                    $this->stageDb->exec('ALTER TABLE `raw_extra_attribute_translations` ADD KEY `idx_raw_extra_attribute_translations_sku` (`sku`)');
                }

                if ($this->stageDb->inTransaction()) {
                    $this->stageDb->commit();
                }

                return true;
            } catch (\Throwable $exception) {
                if ($this->stageDb->inTransaction()) {
                    $this->stageDb->rollBack();
                }

                throw $exception;
            }
        }

        return false;
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

    private function latestMigrationSuccess(): ?array
    {
        if (!$this->tableExists('sync_logs')) {
            return null;
        }

        $stmt = $this->stageDb->prepare(
            'SELECT created_at, level, message, context_json
             FROM sync_logs
             WHERE message = :message
             ORDER BY created_at DESC, id DESC
             LIMIT 1'
        );
        $stmt->execute([
            ':message' => 'Migrationen wurden ausgefuehrt.',
        ]);

        $row = $stmt->fetch(\PDO::FETCH_ASSOC);
        if (!$row) {
            return null;
        }

        $context = json_decode((string) ($row['context_json'] ?? '{}'), true);

        return [
            'status' => 'success',
            'created_at' => $row['created_at'] ?? null,
            'message' => $row['message'] ?? null,
            'executed_count' => is_array($context) ? (int) ($context['executed_count'] ?? 0) : 0,
            'executed' => is_array($context['executed'] ?? null) ? $context['executed'] : [],
        ];
    }

    private function latestMigrationError(): ?array
    {
        if (!$this->tableExists('sync_errors')) {
            return null;
        }

        $stmt = $this->stageDb->prepare(
            'SELECT created_at, message, details
             FROM sync_errors
             WHERE source = :source
             ORDER BY created_at DESC, id DESC
             LIMIT 1'
        );
        $stmt->execute([
            ':source' => 'migrations',
        ]);

        $row = $stmt->fetch(\PDO::FETCH_ASSOC);
        if (!$row) {
            return null;
        }

        $details = json_decode((string) ($row['details'] ?? '{}'), true);

        return [
            'status' => 'error',
            'created_at' => $row['created_at'] ?? null,
            'message' => $row['message'] ?? null,
            'error' => is_array($details) ? (string) ($details['error'] ?? '') : '',
            'version' => is_array($details) ? (string) ($details['version'] ?? '') : '',
        ];
    }

    private function columnExists(string $table, string $column): bool
    {
        if (!$this->tableExists($table)) {
            return false;
        }

        $stmt = $this->stageDb->prepare("SHOW COLUMNS FROM `{$table}` LIKE :column");
        $stmt->execute([':column' => $column]);

        return (bool) $stmt->fetchColumn();
    }

    private function columnLength(string $table, string $column): ?int
    {
        if (!$this->tableExists($table)) {
            return null;
        }

        $stmt = $this->stageDb->prepare("SHOW COLUMNS FROM `{$table}` LIKE :column");
        $stmt->execute([':column' => $column]);
        $definition = $stmt->fetch(\PDO::FETCH_ASSOC);

        if (!is_array($definition)) {
            return null;
        }

        if (!preg_match('/\((\d+)\)/', (string) ($definition['Type'] ?? ''), $matches)) {
            return null;
        }

        return (int) $matches[1];
    }

    private function recordMigration(string $version, string $filename): void
    {
        $stmt = $this->stageDb->prepare(
            'INSERT INTO schema_migrations (version, filename, executed_at)
             VALUES (:version, :filename, NOW())'
        );
        $stmt->execute([
            ':version' => $version,
            ':filename' => $filename,
        ]);
    }

    private function indexExists(string $table, string $indexName): bool
    {
        $stmt = $this->stageDb->prepare(
            'SHOW INDEX FROM `' . $table . '` WHERE Key_name = :index_name'
        );
        $stmt->execute([':index_name' => $indexName]);

        return (bool) $stmt->fetch(\PDO::FETCH_ASSOC);
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
