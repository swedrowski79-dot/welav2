<?php

declare(strict_types=1);

namespace WelaApi;

use InvalidArgumentException;
use RuntimeException;
use Throwable;

final class FileTransferService
{
    public function __construct(private array $config)
    {
    }

    public function storeDocumentFile(string $fileName, string $contentBase64, ?string $targetPath = null, string $imageClass = 'product'): array
    {
        $safeFileName = basename(str_replace('\\', '/', $fileName));
        \wela_log('info', 'Document upload processing started.', [
            'file_name' => $safeFileName,
            'target_path' => $targetPath,
        ]);

        if ($safeFileName === '' || $safeFileName === '.' || $safeFileName === '..') {
            throw new RuntimeException('Dokument-Dateiname ist ungueltig.');
        }

        $binary = base64_decode($contentBase64, true);
        if (!is_string($binary)) {
            throw new RuntimeException('Dokument-Inhalt ist kein gueltiges Base64.');
        }

        $targetDir = $this->resolveDocumentTargetDirectory($targetPath, true);
        $targetFile = $targetDir . DIRECTORY_SEPARATOR . $safeFileName;
        \wela_log('info', 'Resolved upload target directory.', [
            'file_name' => $safeFileName,
            'target_directory' => $targetDir,
            'target_file' => $targetFile,
        ]);

        $bytesWritten = file_put_contents($targetFile, $binary, LOCK_EX);
        if ($bytesWritten === false) {
            $lastError = error_get_last();
            throw new RuntimeException(
                "Dokument konnte nicht nach '{$targetFile}' geschrieben werden."
                . (is_array($lastError) && isset($lastError['message']) ? ' PHP: ' . $lastError['message'] : '')
            );
        }

        clearstatcache(true, $targetFile);
        if (!is_file($targetFile)) {
            throw new RuntimeException("Datei wurde laut PHP geschrieben, ist am Ziel aber nicht vorhanden: '{$targetFile}'.");
        }

        $actualSize = filesize($targetFile);
        if ($actualSize === false || $actualSize !== strlen($binary)) {
            throw new RuntimeException(
                "Dateigroesse nach Upload stimmt nicht. Erwartet: " . strlen($binary)
                . ", vorhanden: " . var_export($actualSize, true)
                . ", Pfad: '{$targetFile}'."
            );
        }

        \wela_log('info', 'File written and verified at target path.', [
            'file_name' => $safeFileName,
            'bytes_written' => $bytesWritten,
            'verified_size' => $actualSize,
            'target_file' => $targetFile,
            'target_exists' => true,
        ]);

        $imageGenerationVerified = \wela_is_product_image_target_directory($targetDir);
        $imageProcessingResult = [
            'success' => false,
            'filename' => $safeFileName,
            'class' => null,
            'generated_files' => [],
            'missing_files' => [],
            'skipped' => true,
        ];

        \wela_log('info', 'Image post-processing decision reached.', [
            'file_name' => $safeFileName,
            'target_directory' => $targetDir,
            'is_xt_org_directory' => $imageGenerationVerified,
            'requested_image_class' => $imageClass,
        ]);

        if ($imageGenerationVerified) {
            if (!in_array($imageClass, ['product', 'category'], true)) {
                throw new InvalidArgumentException('image_class muss product oder category sein.');
            }

            $this->requireXtBootstrap();

            \wela_log('info', 'Calling XT MediaImages processing directly.', [
                'file_name' => $safeFileName,
                'image_class' => $imageClass,
            ]);

            $imageProcessingResult = \wela_process_xt_commerce_image($safeFileName, $imageClass);

            \wela_log('info', 'XT MediaImages processing returned.', [
                'file_name' => $safeFileName,
                'image_class' => $imageClass,
                'result' => $imageProcessingResult,
            ]);
        }

        \wela_log('info', 'Document upload processing finished.', [
            'file_name' => $safeFileName,
            'stored_path' => $targetFile,
            'image_generation_verified' => $imageGenerationVerified ? (bool) ($imageProcessingResult['success'] ?? false) : false,
            'image_processing' => $imageProcessingResult,
        ]);

        return [
            'file_name' => $safeFileName,
            'stored_path' => $targetFile,
            'target_directory' => $targetDir,
            'bytes_written' => strlen($binary),
            'wela_api_upload_logic_version' => '2026-07-16-config-collision-fix-5',
            'image_generation_verified' => $imageGenerationVerified ? (bool) ($imageProcessingResult['success'] ?? false) : false,
            'image_processing' => $imageProcessingResult,
        ];
    }

    public function browseServerDirectories(?string $path = null): array
    {
        $resolved = $this->resolveDocumentTargetDirectory($path, false);
        $entries = scandir($resolved);

        if (!is_array($entries)) {
            throw new RuntimeException("Verzeichnis '{$resolved}' konnte nicht gelesen werden.");
        }

        $directories = [];

        foreach ($entries as $entry) {
            if ($entry === '.' || $entry === '..') {
                continue;
            }

            $fullPath = $resolved . DIRECTORY_SEPARATOR . $entry;
            if (!is_dir($fullPath)) {
                continue;
            }

            $normalizedPath = realpath($fullPath);
            if (!is_string($normalizedPath) || $normalizedPath === '') {
                continue;
            }

            $directories[] = [
                'name' => $entry,
                'path' => $normalizedPath,
                'has_children' => $this->directoryHasChildren($normalizedPath),
            ];
        }

        usort($directories, static fn (array $left, array $right): int => strcmp((string) ($left['name'] ?? ''), (string) ($right['name'] ?? '')));

        return [
            'current_path' => $resolved,
            'parent_path' => dirname($resolved) !== $resolved ? dirname($resolved) : null,
            'directories' => $directories,
        ];
    }

    private function resolveDocumentTargetDirectory(?string $requestedPath = null, bool $createIfMissing = false): string
    {
        $candidate = $requestedPath;

        if ($candidate === null || trim($candidate) === '') {
            $configuredPath = $this->config['document_upload_path'] ?? null;
            $candidate = is_string($configuredPath) && trim($configuredPath) !== ''
                ? trim($configuredPath)
                : dirname(__DIR__, 2) . DIRECTORY_SEPARATOR . 'media' . DIRECTORY_SEPARATOR . 'files';
        }

        $absolute = $this->absoluteShopPath($candidate);

        if ($createIfMissing) {
            if (!is_dir($absolute) && !mkdir($absolute, 0775, true) && !is_dir($absolute)) {
                throw new RuntimeException("Zielverzeichnis '{$absolute}' konnte nicht erstellt werden.");
            }
        }

        return $this->existingOrParentDirectory($absolute);
    }

    private function absoluteShopPath(string $path): string
    {
        $path = trim(str_replace('\\', DIRECTORY_SEPARATOR, $path));
        if ($path === '') {
            throw new RuntimeException('Dokumentpfad ist leer.');
        }

        if ($path[0] === DIRECTORY_SEPARATOR || preg_match('/^[A-Za-z]:[\/\\\\]/', $path) === 1) {
            return $path;
        }

        return dirname(__DIR__, 2) . DIRECTORY_SEPARATOR . ltrim($path, DIRECTORY_SEPARATOR);
    }

    private function existingOrParentDirectory(string $path): string
    {
        $candidate = $path;

        while ($candidate !== '' && !is_dir($candidate)) {
            $parent = dirname($candidate);
            if ($parent === $candidate) {
                break;
            }
            $candidate = $parent;
        }

        if ($candidate === '' || !is_dir($candidate)) {
            throw new RuntimeException("Dokumentpfad '{$path}' existiert nicht.");
        }

        $resolved = realpath($candidate);
        if (!is_string($resolved) || $resolved === '') {
            throw new RuntimeException("Dokumentpfad '{$candidate}' konnte nicht aufgeloest werden.");
        }

        return $resolved;
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

    private function requireXtBootstrap(): void
    {
        if (defined('WELA_XT_IMAGE_BOOTSTRAPPED')) {
            return;
        }

        $xtBootstrapPath = dirname(__DIR__) . '/bootstrap/xtcommerce.php';
        if (!is_file($xtBootstrapPath)) {
            throw new RuntimeException('XT-Bootstrap-Datei fehlt: ' . $xtBootstrapPath);
        }

        $xtCommerceRoot = getenv('XT_COMMERCE_ROOT');
        if ($xtCommerceRoot === false || trim((string) $xtCommerceRoot) === '') {
            throw new RuntimeException('XT_COMMERCE_ROOT ist nicht konfiguriert.');
        }

        $bootstrapOutputLevel = ob_get_level();
        ob_start();

        try {
            require_once $xtBootstrapPath;

            while (ob_get_level() > $bootstrapOutputLevel) {
                $buffer = ob_get_clean();
                if (is_string($buffer) && trim($buffer) !== '') {
                    \wela_log('warning', 'Unexpected output produced during XT bootstrap.', [
                        'runtime_version' => $GLOBALS['wela_api_runtime_version'] ?? null,
                        'output' => substr($buffer, 0, 2000),
                    ]);
                }
            }

            \wela_log('info', 'XT bootstrap loaded on demand.', [
                'runtime_version' => $GLOBALS['wela_api_runtime_version'] ?? null,
                'xt_commerce_root' => $xtCommerceRoot,
                'bootstrap_file' => $xtBootstrapPath,
                'action' => $GLOBALS['wela_api_action'] ?? null,
            ]);
        } catch (Throwable $exception) {
            while (ob_get_level() > $bootstrapOutputLevel) {
                ob_end_clean();
            }

            \wela_log('error', 'XT bootstrap failed during on-demand load.', [
                'runtime_version' => $GLOBALS['wela_api_runtime_version'] ?? null,
                'xt_commerce_root' => $xtCommerceRoot,
                'bootstrap_file' => $xtBootstrapPath,
                'action' => $GLOBALS['wela_api_action'] ?? null,
                'exception' => $exception->getMessage(),
                'trace' => $exception->getTraceAsString(),
            ]);

            throw new RuntimeException('XT bootstrap failed: ' . $exception->getMessage(), 0, $exception);
        }
    }
}
