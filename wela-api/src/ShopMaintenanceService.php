<?php

declare(strict_types=1);

namespace WelaApi;

use RuntimeException;

final class ShopMaintenanceService
{
    public function refreshShopState(): array
    {
        $shopRoot = dirname(__DIR__, 2);
        $targets = [
            'cache',
            'templates_c',
        ];
        $results = [];
        $totalRemovedFiles = 0;
        $totalRemovedDirectories = 0;

        foreach ($targets as $relativePath) {
            $path = $shopRoot . DIRECTORY_SEPARATOR . $relativePath;
            $stats = $this->clearDirectoryContents($path);
            $results[$relativePath] = $stats;
            $totalRemovedFiles += (int) ($stats['removed_files'] ?? 0);
            $totalRemovedDirectories += (int) ($stats['removed_directories'] ?? 0);
        }

        clearstatcache(true);

        return [
            'shop_root' => $shopRoot,
            'targets' => $results,
            'removed_files' => $totalRemovedFiles,
            'removed_directories' => $totalRemovedDirectories,
        ];
    }

    private function clearDirectoryContents(string $path): array
    {
        if (!is_dir($path)) {
            return [
                'path' => $path,
                'exists' => false,
                'removed_files' => 0,
                'removed_directories' => 0,
            ];
        }

        $removedFiles = 0;
        $removedDirectories = 0;
        $entries = scandir($path);

        if (!is_array($entries)) {
            throw new RuntimeException("Cache-Verzeichnis '{$path}' konnte nicht gelesen werden.");
        }

        foreach ($entries as $entry) {
            if ($entry === '.' || $entry === '..' || in_array($entry, ['.htaccess', 'index.html', 'index.htm', '.gitignore'], true)) {
                continue;
            }

            $entryPath = $path . DIRECTORY_SEPARATOR . $entry;
            $stats = $this->removePathRecursive($entryPath);
            $removedFiles += (int) ($stats['removed_files'] ?? 0);
            $removedDirectories += (int) ($stats['removed_directories'] ?? 0);
        }

        return [
            'path' => $path,
            'exists' => true,
            'removed_files' => $removedFiles,
            'removed_directories' => $removedDirectories,
        ];
    }

    private function removePathRecursive(string $path): array
    {
        if (!file_exists($path) && !is_link($path)) {
            return ['removed_files' => 0, 'removed_directories' => 0];
        }

        if (is_link($path) || is_file($path)) {
            if (!unlink($path)) {
                throw new RuntimeException("Cache-Datei '{$path}' konnte nicht geloescht werden.");
            }

            return ['removed_files' => 1, 'removed_directories' => 0];
        }

        if (!is_dir($path)) {
            return ['removed_files' => 0, 'removed_directories' => 0];
        }

        $removedFiles = 0;
        $removedDirectories = 0;
        $entries = scandir($path);

        if (!is_array($entries)) {
            throw new RuntimeException("Cache-Pfad '{$path}' konnte nicht gelesen werden.");
        }

        foreach ($entries as $entry) {
            if ($entry === '.' || $entry === '..') {
                continue;
            }

            $entryPath = $path . DIRECTORY_SEPARATOR . $entry;
            $stats = $this->removePathRecursive($entryPath);
            $removedFiles += (int) ($stats['removed_files'] ?? 0);
            $removedDirectories += (int) ($stats['removed_directories'] ?? 0);
        }

        if (!rmdir($path)) {
            throw new RuntimeException("Cache-Verzeichnis '{$path}' konnte nicht geloescht werden.");
        }

        return [
            'removed_files' => $removedFiles,
            'removed_directories' => $removedDirectories + 1,
        ];
    }
}
