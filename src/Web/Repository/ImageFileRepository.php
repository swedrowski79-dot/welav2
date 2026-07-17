<?php

declare(strict_types=1);

namespace App\Web\Repository;

use App\Web\Core\Paginator;

final class ImageFileRepository
{
    public function __construct(private \PDO $stageDb)
    {
    }

    public function ensureSchema(): void
    {
        $this->stageDb->exec(
            'CREATE TABLE IF NOT EXISTS `images_file` (
                `id` INT AUTO_INCREMENT PRIMARY KEY,
                `file_name` VARCHAR(255) NOT NULL,
                `reference_count` INT NOT NULL DEFAULT 0,
                `local_path` VARCHAR(1024) NULL,
                `file_hash` VARCHAR(64) NULL,
                `file_size` BIGINT NULL,
                `file_created_at` DATETIME NULL,
                `file_modified_at` DATETIME NULL,
                `upload` TINYINT NOT NULL DEFAULT 0,
                `uploaded_at` DATETIME NULL,
                `shop_server_path` VARCHAR(1024) NULL,
                `last_scan_at` DATETIME NULL,
                `last_error` TEXT NULL,
                `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                UNIQUE KEY `uniq_images_file_file_name` (`file_name`),
                KEY `idx_images_file_upload` (`upload`),
                KEY `idx_images_file_hash` (`file_hash`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4'
        );
    }

    public function resetTable(): void
    {
        $this->ensureSchema();
        $this->stageDb->exec('TRUNCATE TABLE `images_file`');
    }

    public function syncFilesFromStage(): int
    {
        $this->ensureSchema();

        $stmt = $this->stageDb->query(
            'SELECT `file_name`, COUNT(*) AS reference_count
             FROM (
                 SELECT m.`file_name`
                 FROM `stage_product_media` m
                 WHERE COALESCE(m.`file_name`, \'\') <> \'\'
                   AND m.`type` = \'images\'

                 UNION ALL

                 SELECT c.`image` AS `file_name`
                 FROM `stage_categories` c
                 WHERE COALESCE(c.`image`, \'\') <> \'\'

                 UNION ALL

                 SELECT c.`header_image` AS `file_name`
                 FROM `stage_categories` c
                 WHERE COALESCE(c.`header_image`, \'\') <> \'\'
             ) AS refs
             GROUP BY `file_name`
             ORDER BY `file_name` ASC'
        );
        $rows = $stmt->fetchAll(\PDO::FETCH_ASSOC);

        $upsert = $this->stageDb->prepare(
            'INSERT INTO `images_file` (`file_name`, `reference_count`)
             VALUES (:file_name, :reference_count)
             ON DUPLICATE KEY UPDATE `reference_count` = VALUES(`reference_count`)'
        );

        foreach ($rows as $row) {
            $upsert->execute([
                ':file_name' => (string) ($row['file_name'] ?? ''),
                ':reference_count' => (int) ($row['reference_count'] ?? 0),
            ]);
        }

        return count($rows);
    }

    public function scanDirectory(string $rootPath): array
    {
        $this->ensureSchema();
        $fileCount = $this->syncFilesFromStage();
        $now = gmdate('Y-m-d H:i:s');
        $fileIndex = $this->buildFileIndex($rootPath);
        $rows = $this->allImageRows();
        $updated = 0;
        $missing = 0;
        $markedForUpload = 0;

        $stmt = $this->stageDb->prepare(
            'UPDATE `images_file`
             SET `local_path` = :local_path,
                 `file_hash` = :file_hash,
                 `file_size` = :file_size,
                 `file_created_at` = :file_created_at,
                 `file_modified_at` = :file_modified_at,
                 `upload` = :upload,
                 `last_scan_at` = :last_scan_at,
                 `last_error` = :last_error
             WHERE `id` = :id'
        );

        foreach ($rows as $row) {
            $fileName = (string) ($row['file_name'] ?? '');
            $match = $fileIndex[$this->normalizeKey($fileName)] ?? null;

            if (!is_array($match)) {
                $stmt->execute([
                    ':local_path' => null,
                    ':file_hash' => null,
                    ':file_size' => null,
                    ':file_created_at' => null,
                    ':file_modified_at' => null,
                    ':upload' => 0,
                    ':last_scan_at' => $now,
                    ':last_error' => 'Datei im gewaehlten Bildpfad nicht gefunden.',
                    ':id' => (int) ($row['id'] ?? 0),
                ]);
                $updated++;
                $missing++;
                continue;
            }

            $fileHash = $this->buildFileHash($match);
            $needsUpload = (string) ($row['file_hash'] ?? '') !== $fileHash
                || (string) ($row['local_path'] ?? '') !== (string) ($match['path'] ?? '')
                || (string) ($row['shop_server_path'] ?? '') === '';

            $stmt->execute([
                ':local_path' => (string) ($match['path'] ?? ''),
                ':file_hash' => $fileHash,
                ':file_size' => (int) ($match['size'] ?? 0),
                ':file_created_at' => $this->formatTimestamp((int) ($match['ctime'] ?? 0)),
                ':file_modified_at' => $this->formatTimestamp((int) ($match['mtime'] ?? 0)),
                ':upload' => $needsUpload ? 1 : (int) ($row['upload'] ?? 0),
                ':last_scan_at' => $now,
                ':last_error' => null,
                ':id' => (int) ($row['id'] ?? 0),
            ]);
            $updated++;

            if ($needsUpload) {
                $markedForUpload++;
            }
        }

        return [
            'files' => $fileCount,
            'updated' => $updated,
            'missing' => $missing,
            'marked_for_upload' => $markedForUpload,
        ];
    }

    public function uploadPending(
        string $rootPath,
        \WelaApiClient $client,
        string $targetPath = '',
        ?\SyncMonitor $monitor = null,
        ?int $runId = null
    ): array
    {
        $this->ensureSchema();
        $pendingRows = $this->pendingUploadRows();
        $uploaded = 0;
        $errors = 0;

        if ($monitor !== null) {
            $monitor->log($runId, 'info', 'Offene Bild-Dateien ermittelt.', [
                'pending' => count($pendingRows),
                'root_path' => $rootPath,
                'target_path' => $targetPath,
            ]);
        }

        $updateStmt = $this->stageDb->prepare(
            'UPDATE `images_file`
             SET `upload` = :upload,
                 `uploaded_at` = :uploaded_at,
                 `shop_server_path` = :shop_server_path,
                 `last_error` = :last_error
             WHERE `id` = :id'
        );

        foreach ($pendingRows as $row) {
            $localPath = (string) ($row['local_path'] ?? '');
            $fileName = (string) ($row['file_name'] ?? '');
            $id = (int) ($row['id'] ?? 0);

            try {
                if ($localPath === '' || !is_file($localPath)) {
                    throw new \RuntimeException('Lokale Bilddatei fehlt oder ist nicht lesbar.');
                }

                $content = file_get_contents($localPath);
                if (!is_string($content)) {
                    throw new \RuntimeException('Lokale Bilddatei konnte nicht gelesen werden.');
                }

                $result = $client->uploadDocumentFileToPath($fileName, base64_encode($content), $targetPath !== '' ? $targetPath : null);
                $shopServerPath = (string) ($result['stored_path'] ?? '');
                $imageGenerationVerified = (bool) ($result['image_generation_verified'] ?? false);

                if (!$imageGenerationVerified) {
                    throw new \RuntimeException('Bild-Upload wurde gespeichert, aber die XT-Bildgroessen wurden nicht bestaetigt.');
                }

                $this->markUploadSuccess($updateStmt, $id, $fileName, $shopServerPath, $targetPath, $monitor, $runId);
                $uploaded++;
            } catch (\Throwable $exception) {
                if ($this->isKnownXtImageUploadPostWriteFailure($exception)) {
                    $this->markUploadSuccess($updateStmt, $id, $fileName, (string) ($row['shop_server_path'] ?? ''), $targetPath, $monitor, $runId);
                    $uploaded++;
                    continue;
                }

                $updateStmt->execute([
                    ':upload' => 1,
                    ':uploaded_at' => $row['uploaded_at'] ?? null,
                    ':shop_server_path' => $row['shop_server_path'] ?? null,
                    ':last_error' => $exception->getMessage(),
                    ':id' => $id,
                ]);
                $errors++;

                if ($monitor !== null) {
                    $monitor->error($runId, $exception->getMessage(), [
                        'source' => 'image_upload',
                        'record_identifier' => $fileName,
                        'file_name' => $fileName,
                        'local_path' => $localPath,
                    ]);
                }
            }
        }

        return [
            'pending' => count($pendingRows),
            'uploaded' => $uploaded,
            'errors' => $errors,
            'root_path' => $rootPath,
            'target_path' => $targetPath,
        ];
    }

    private function markUploadSuccess(
        \PDOStatement $updateStmt,
        int $id,
        string $fileName,
        string $shopServerPath,
        string $targetPath,
        ?\SyncMonitor $monitor,
        ?int $runId
    ): void {
        $resolvedShopPath = $this->resolveStoredShopPath($fileName, $shopServerPath, $targetPath);

        $updateStmt->execute([
            ':upload' => 0,
            ':uploaded_at' => gmdate('Y-m-d H:i:s'),
            ':shop_server_path' => $resolvedShopPath,
            ':last_error' => null,
            ':id' => $id,
        ]);

        if ($monitor !== null) {
            $monitor->log($runId, 'info', 'Bild-Datei hochgeladen.', [
                'record_identifier' => $fileName,
                'file_name' => $fileName,
                'stored_path' => $resolvedShopPath,
            ]);
        }
    }

    public function summary(): array
    {
        $this->ensureSchema();

        return [
            'total' => $this->countWhere(),
            'pending_upload' => $this->countWhere('`upload` = 1'),
            'missing_path' => $this->countWhere('COALESCE(`local_path`, \'\') = \'\''),
            'uploaded' => $this->countWhere('`uploaded_at` IS NOT NULL'),
        ];
    }

    public function countRows(string $filter = 'all'): int
    {
        $this->ensureSchema();
        [$whereSql, $params] = $this->listFilter($filter);
        $stmt = $this->stageDb->prepare(
            'SELECT COUNT(*)
             FROM `images_file`
             ' . $whereSql
        );
        $stmt->execute($params);

        return (int) $stmt->fetchColumn();
    }

    public function findImageById(int $imageId): ?array
    {
        $this->ensureSchema();
        $stmt = $this->stageDb->prepare(
            'SELECT *
             FROM `images_file`
             WHERE `id` = :id
             LIMIT 1'
        );
        $stmt->bindValue(':id', $imageId, \PDO::PARAM_INT);
        $stmt->execute();
        $row = $stmt->fetch(\PDO::FETCH_ASSOC);

        return $row ?: null;
    }

    public function countReferenceRows(int $imageId): int
    {
        $this->ensureSchema();
        $stmt = $this->stageDb->prepare(
            'SELECT COUNT(*)
             FROM (
                 SELECT 1
                 FROM `stage_product_media` m
                 INNER JOIN `images_file` f
                     ON f.`id` = :id
                    AND m.`file_name` = f.`file_name`
                 WHERE m.`type` = \'images\'

                 UNION ALL

                 SELECT 1
                 FROM `stage_categories` c
                 INNER JOIN `images_file` f
                     ON f.`id` = :id
                    AND c.`image` = f.`file_name`
                 WHERE COALESCE(c.`image`, \'\') <> \'\'

                 UNION ALL

                 SELECT 1
                 FROM `stage_categories` c
                 INNER JOIN `images_file` f
                     ON f.`id` = :id
                    AND c.`header_image` = f.`file_name`
                 WHERE COALESCE(c.`header_image`, \'\') <> \'\'
             ) AS refs'
        );
        $stmt->bindValue(':id', $imageId, \PDO::PARAM_INT);
        $stmt->execute();

        return (int) $stmt->fetchColumn();
    }

    public function paginatedReferenceRows(int $imageId, Paginator $paginator): array
    {
        $this->ensureSchema();
        $stmt = $this->stageDb->prepare(
            'SELECT *
             FROM (
                 SELECT
                     \'product\' AS `reference_type`,
                     COALESCE(p.`sku`, \'\') AS `reference_code`,
                     CAST(m.`afs_artikel_id` AS CHAR) AS `reference_id`,
                     COALESCE(p.`name_default`, \'\') AS `reference_name`,
                     COALESCE(m.`source_slot`, \'\') AS `usage_context`,
                     COALESCE(m.`sort_order`, 0) AS `sort_order`,
                     COALESCE(m.`media_external_id`, \'\') AS `reference_external_id`
                 FROM `stage_product_media` m
                 INNER JOIN `images_file` f
                     ON f.`id` = :id
                    AND m.`file_name` = f.`file_name`
                 LEFT JOIN `stage_products` p
                     ON p.`afs_artikel_id` = m.`afs_artikel_id`
                 WHERE m.`type` = \'images\'

                 UNION ALL

                 SELECT
                     \'category\' AS `reference_type`,
                     \'\' AS `reference_code`,
                     CAST(c.`afs_wg_id` AS CHAR) AS `reference_id`,
                     COALESCE(c.`name_default`, \'\') AS `reference_name`,
                     \'image\' AS `usage_context`,
                     0 AS `sort_order`,
                     \'\' AS `reference_external_id`
                 FROM `stage_categories` c
                 INNER JOIN `images_file` f
                     ON f.`id` = :id
                    AND c.`image` = f.`file_name`
                 WHERE COALESCE(c.`image`, \'\') <> \'\'

                 UNION ALL

                 SELECT
                     \'category\' AS `reference_type`,
                     \'\' AS `reference_code`,
                     CAST(c.`afs_wg_id` AS CHAR) AS `reference_id`,
                     COALESCE(c.`name_default`, \'\') AS `reference_name`,
                     \'header_image\' AS `usage_context`,
                     0 AS `sort_order`,
                     \'\' AS `reference_external_id`
                 FROM `stage_categories` c
                 INNER JOIN `images_file` f
                     ON f.`id` = :id
                    AND c.`header_image` = f.`file_name`
                 WHERE COALESCE(c.`header_image`, \'\') <> \'\'
             ) AS refs
             ORDER BY
                 CASE `reference_type` WHEN \'product\' THEN 0 ELSE 1 END ASC,
                 `reference_code` ASC,
                 `reference_name` ASC,
                 `reference_id` ASC,
                 `sort_order` ASC,
                 `usage_context` ASC
             LIMIT :limit OFFSET :offset'
        );
        $stmt->bindValue(':id', $imageId, \PDO::PARAM_INT);
        $stmt->bindValue(':limit', $paginator->perPage, \PDO::PARAM_INT);
        $stmt->bindValue(':offset', $paginator->offset(), \PDO::PARAM_INT);
        $stmt->execute();

        return $stmt->fetchAll(\PDO::FETCH_ASSOC);
    }

    public function paginatedRows(Paginator $paginator, string $filter = 'all'): array
    {
        $this->ensureSchema();
        [$whereSql, $params] = $this->listFilter($filter);
        $stmt = $this->stageDb->prepare(
            'SELECT *
              FROM `images_file`
             ' . $whereSql . '
              ORDER BY `upload` DESC, `updated_at` DESC, `file_name` ASC
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

    public function browseDirectories(string $path): array
    {
        $resolved = $this->resolveDirectory($path);
        $entries = scandir($resolved) ?: [];
        $directories = [];

        foreach ($entries as $entry) {
            if ($entry === '.' || $entry === '..') {
                continue;
            }

            $fullPath = $resolved . DIRECTORY_SEPARATOR . $entry;
            if (!is_dir($fullPath)) {
                continue;
            }

            $directories[] = [
                'name' => $entry,
                'path' => $fullPath,
                'has_children' => $this->directoryHasChildren($fullPath),
            ];
        }

        usort($directories, static fn (array $left, array $right): int => strcmp((string) $left['name'], (string) $right['name']));

        return [
            'current_path' => $resolved,
            'parent_path' => dirname($resolved) !== $resolved ? dirname($resolved) : null,
            'directories' => $directories,
        ];
    }

    private function pendingUploadRows(): array
    {
        $stmt = $this->stageDb->query(
            'SELECT *
             FROM `images_file`
             WHERE `upload` = 1
               AND COALESCE(`local_path`, \'\') <> \'\'
             ORDER BY `updated_at` ASC, `file_name` ASC'
        );

        return $stmt->fetchAll(\PDO::FETCH_ASSOC);
    }

    private function allImageRows(): array
    {
        $stmt = $this->stageDb->query(
            'SELECT *
             FROM `images_file`
             ORDER BY `file_name` ASC'
        );

        return $stmt->fetchAll(\PDO::FETCH_ASSOC);
    }

    private function countWhere(string $whereSql = '1=1'): int
    {
        $stmt = $this->stageDb->query('SELECT COUNT(*) FROM `images_file` WHERE ' . $whereSql);

        return (int) $stmt->fetchColumn();
    }

    private function listFilter(string $filter): array
    {
        if ($filter === 'missing') {
            return [
                'WHERE COALESCE(`local_path`, \'\') = \'\'',
                [],
            ];
        }

        return ['', []];
    }

    private function isKnownXtImageUploadPostWriteFailure(\Throwable $exception): bool
    {
        return str_contains($exception->getMessage(), 'in_array(): Argument #2 ($haystack) must be of type array, null given');
    }

    private function resolveStoredShopPath(string $fileName, string $shopServerPath, string $targetPath): string
    {
        $resolvedPath = trim($shopServerPath);
        if ($resolvedPath !== '') {
            return $resolvedPath;
        }

        $trimmedTargetPath = trim($targetPath);
        if ($trimmedTargetPath === '') {
            return $fileName;
        }

        return rtrim(str_replace('\\', '/', $trimmedTargetPath), '/') . '/' . ltrim($fileName, '/');
    }

    private function buildFileIndex(string $rootPath): array
    {
        $resolved = $this->resolveDirectory($rootPath);
        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($resolved, \FilesystemIterator::SKIP_DOTS)
        );
        $index = [];

        foreach ($iterator as $fileInfo) {
            if (!$fileInfo instanceof \SplFileInfo || !$fileInfo->isFile()) {
                continue;
            }

            $key = $this->normalizeKey($fileInfo->getBasename());
            $candidate = [
                'path' => $fileInfo->getPathname(),
                'size' => $fileInfo->getSize(),
                'ctime' => $fileInfo->getCTime(),
                'mtime' => $fileInfo->getMTime(),
            ];

            if (!isset($index[$key]) || (int) $candidate['mtime'] > (int) ($index[$key]['mtime'] ?? 0)) {
                $index[$key] = $candidate;
            }
        }

        return $index;
    }

    private function buildFileHash(array $file): string
    {
        return sha1(implode('|', [
            (string) ($file['size'] ?? 0),
            (string) ($file['ctime'] ?? 0),
            (string) ($file['mtime'] ?? 0),
        ]));
    }

    private function formatTimestamp(int $timestamp): ?string
    {
        return $timestamp > 0 ? gmdate('Y-m-d H:i:s', $timestamp) : null;
    }

    private function resolveDirectory(string $path): string
    {
        $candidate = trim($path);
        if ($candidate === '') {
            throw new \InvalidArgumentException('Bildpfad ist nicht gesetzt.');
        }

        $resolved = realpath($candidate);
        if ($resolved === false || !is_dir($resolved)) {
            throw new \InvalidArgumentException('Bildpfad existiert nicht oder ist kein Verzeichnis.');
        }

        return $resolved;
    }

    private function normalizeKey(string $value): string
    {
        $value = trim($value);

        return function_exists('mb_strtolower')
            ? mb_strtolower($value, 'UTF-8')
            : strtolower($value);
    }

    private function directoryHasChildren(string $path): bool
    {
        $entries = scandir($path);
        if (!is_array($entries)) {
            return false;
        }

        foreach ($entries as $entry) {
            if ($entry === '.' || $entry === '..') {
                continue;
            }

            if (is_dir($path . DIRECTORY_SEPARATOR . $entry)) {
                return true;
            }
        }

        return false;
    }
}
