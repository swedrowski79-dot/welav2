<?php

declare(strict_types=1);

namespace App\Web\Repository;

use App\Web\Core\Paginator;

final class DocumentFileRepository
{
    public function __construct(private \PDO $stageDb)
    {
    }

    public function ensureSchema(): void
    {
        $this->stageDb->exec(
            'CREATE TABLE IF NOT EXISTS `documents_file` (
                `id` INT AUTO_INCREMENT PRIMARY KEY,
                `title` VARCHAR(255) NOT NULL,
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
                UNIQUE KEY `uniq_documents_file_title` (`title`),
                KEY `idx_documents_file_upload` (`upload`),
                KEY `idx_documents_file_hash` (`file_hash`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4'
        );
    }

    public function resetTable(): void
    {
        $this->ensureSchema();
        $this->stageDb->exec('TRUNCATE TABLE `documents_file`');
    }

    public function syncTitlesFromStage(): int
    {
        $this->ensureSchema();

        // Fachlicher Hinweis:
        // `title` ist in diesem Projekt der relevante Dokument-Dateiname fuer Scan und Upload.
        // `stage_product_documents.file_name` stammt aus einer Fremdsoftware und ist bewusst
        // nicht die lokale Pfad-/Dateiname-Quelle fuer den Dokumentlauf.
        $stmt = $this->stageDb->query(
            'SELECT `title`, COUNT(*) AS reference_count
             FROM `stage_product_documents`
             WHERE COALESCE(`title`, \'\') <> \'\'
             GROUP BY `title`
             ORDER BY `title` ASC'
        );
        $rows = $stmt->fetchAll(\PDO::FETCH_ASSOC);

        $upsert = $this->stageDb->prepare(
            'INSERT INTO `documents_file` (`title`, `reference_count`)
             VALUES (:title, :reference_count)
             ON DUPLICATE KEY UPDATE `reference_count` = VALUES(`reference_count`)'
        );

        foreach ($rows as $row) {
            $upsert->execute([
                ':title' => (string) ($row['title'] ?? ''),
                ':reference_count' => (int) ($row['reference_count'] ?? 0),
            ]);
        }

        return count($rows);
    }

    public function scanDirectory(string $rootPath): array
    {
        $this->ensureSchema();
        $titleCount = $this->syncTitlesFromStage();
        $now = gmdate('Y-m-d H:i:s');
        $fileIndex = $this->buildFileIndex($rootPath);
        $rows = $this->allDocumentRows();
        $updated = 0;
        $missing = 0;
        $markedForUpload = 0;

        $stmt = $this->stageDb->prepare(
            'UPDATE `documents_file`
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
            $title = (string) ($row['title'] ?? '');
            $match = $fileIndex[$this->normalizeKey($title)] ?? null;

            if (!is_array($match)) {
                $stmt->execute([
                    ':local_path' => null,
                    ':file_hash' => null,
                    ':file_size' => null,
                    ':file_created_at' => null,
                    ':file_modified_at' => null,
                    ':upload' => 0,
                    ':last_scan_at' => $now,
                    ':last_error' => 'Datei im gewaehlten Dokumentpfad nicht gefunden.',
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
            'titles' => $titleCount,
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
        $lookupMap = $client->lookupMap('xt_media', 'external_id', 'id');
        $uploaded = 0;
        $errors = 0;

        if ($monitor !== null) {
            $monitor->log($runId, 'info', 'Offene Dokument-Dateien ermittelt.', [
                'pending' => count($pendingRows),
                'root_path' => $rootPath,
                'target_path' => $targetPath,
            ]);
        }

        $updateStmt = $this->stageDb->prepare(
            'UPDATE `documents_file`
             SET `upload` = :upload,
                 `uploaded_at` = :uploaded_at,
                 `shop_server_path` = :shop_server_path,
                 `last_error` = :last_error
             WHERE `id` = :id'
        );

        foreach ($pendingRows as $row) {
            $localPath = (string) ($row['local_path'] ?? '');
            $title = (string) ($row['title'] ?? '');
            $id = (int) ($row['id'] ?? 0);

            try {
                if ($localPath === '' || !is_file($localPath)) {
                    throw new \RuntimeException('Lokale Datei fehlt oder ist nicht lesbar.');
                }

                $content = file_get_contents($localPath);
                if (!is_string($content)) {
                    throw new \RuntimeException('Lokale Datei konnte nicht gelesen werden.');
                }

                $result = $client->uploadDocumentFileToPath($title, base64_encode($content), $targetPath !== '' ? $targetPath : null);
                $shopServerPath = (string) ($result['stored_path'] ?? '');
                $this->syncXtMediaFileNames($title, $lookupMap, $client);

                $updateStmt->execute([
                    ':upload' => 0,
                    ':uploaded_at' => gmdate('Y-m-d H:i:s'),
                    ':shop_server_path' => $shopServerPath,
                    ':last_error' => null,
                    ':id' => $id,
                ]);
                $uploaded++;

                if ($monitor !== null) {
                    $monitor->log($runId, 'info', 'Dokument-Datei hochgeladen.', [
                        'record_identifier' => $title,
                        'title' => $title,
                        'stored_path' => $shopServerPath,
                    ]);
                }
            } catch (\Throwable $exception) {
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
                        'source' => 'document_upload',
                        'record_identifier' => $title,
                        'title' => $title,
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
             FROM `documents_file`
             ' . $whereSql
        );
        $stmt->execute($params);

        return (int) $stmt->fetchColumn();
    }

    public function findDocumentById(int $documentId): ?array
    {
        $this->ensureSchema();
        $stmt = $this->stageDb->prepare(
            'SELECT *
             FROM `documents_file`
             WHERE `id` = :id
             LIMIT 1'
        );
        $stmt->bindValue(':id', $documentId, \PDO::PARAM_INT);
        $stmt->execute();
        $row = $stmt->fetch(\PDO::FETCH_ASSOC);

        return $row ?: null;
    }

    public function countReferenceRows(int $documentId): int
    {
        $this->ensureSchema();
        $stmt = $this->stageDb->prepare(
            'SELECT COUNT(*)
             FROM `stage_product_documents` d
             INNER JOIN `documents_file` f
                 ON f.`id` = :id
                AND d.`title` = f.`title`'
        );
        $stmt->bindValue(':id', $documentId, \PDO::PARAM_INT);
        $stmt->execute();

        return (int) $stmt->fetchColumn();
    }

    public function paginatedReferenceRows(int $documentId, Paginator $paginator): array
    {
        $this->ensureSchema();
        $stmt = $this->stageDb->prepare(
            'SELECT
                 d.`afs_artikel_id`,
                 COALESCE(p.`sku`, \'\') AS `sku`,
                 COALESCE(p.`name_default`, \'\') AS `product_name`,
                 d.`file_name`,
                 d.`document_type`,
                 d.`sort_order`,
                 d.`path`
             FROM `stage_product_documents` d
             INNER JOIN `documents_file` f
                 ON f.`id` = :id
                AND d.`title` = f.`title`
             LEFT JOIN `stage_products` p
                 ON p.`afs_artikel_id` = d.`afs_artikel_id`
             ORDER BY
                 COALESCE(p.`sku`, \'\') ASC,
                 d.`afs_artikel_id` ASC,
                 COALESCE(d.`sort_order`, 0) ASC,
                 COALESCE(d.`file_name`, \'\') ASC
             LIMIT :limit OFFSET :offset'
        );
        $stmt->bindValue(':id', $documentId, \PDO::PARAM_INT);
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
              FROM `documents_file`
             ' . $whereSql . '
              ORDER BY `upload` DESC, `updated_at` DESC, `title` ASC
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

    private function syncXtMediaFileNames(string $title, array $lookupMap, \WelaApiClient $client): void
    {
        $stmt = $this->stageDb->prepare(
            'SELECT DISTINCT `afs_document_id`
             FROM `stage_product_documents`
             WHERE `title` = :title
               AND `afs_document_id` IS NOT NULL'
        );
        $stmt->execute([':title' => $title]);

        foreach ($stmt->fetchAll(\PDO::FETCH_COLUMN) as $documentId) {
            $externalId = trim((string) $documentId);
            if ($externalId === '' || !array_key_exists($externalId, $lookupMap)) {
                continue;
            }

            $client->upsertRow(
                'xt_media',
                ['external_id' => $externalId],
                [
                    'file' => $title,
                    'last_modified' => gmdate('Y-m-d H:i:s'),
                ],
                'id'
            );
        }
    }

    private function pendingUploadRows(): array
    {
        $stmt = $this->stageDb->query(
            'SELECT *
             FROM `documents_file`
             WHERE `upload` = 1
               AND COALESCE(`local_path`, \'\') <> \'\'
             ORDER BY `updated_at` ASC, `title` ASC'
        );

        return $stmt->fetchAll(\PDO::FETCH_ASSOC);
    }

    private function allDocumentRows(): array
    {
        $stmt = $this->stageDb->query(
            'SELECT *
             FROM `documents_file`
             ORDER BY `title` ASC'
        );

        return $stmt->fetchAll(\PDO::FETCH_ASSOC);
    }

    private function countWhere(string $whereSql = '1=1'): int
    {
        $stmt = $this->stageDb->query('SELECT COUNT(*) FROM `documents_file` WHERE ' . $whereSql);

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
            throw new \InvalidArgumentException('Dokumentenpfad ist nicht gesetzt.');
        }

        $resolved = realpath($candidate);
        if ($resolved === false || !is_dir($resolved)) {
            throw new \InvalidArgumentException('Dokumentenpfad existiert nicht oder ist kein Verzeichnis.');
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
