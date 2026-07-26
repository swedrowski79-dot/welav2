<?php

declare(strict_types=1);

function wela_regenerate_uploaded_product_image_if_needed(string $filename, string $targetDirectory): array
{
    wela_log('info', 'Checking whether uploaded file requires XT image processing.', [
        'file_name' => $filename,
        'target_directory' => $targetDirectory,
    ]);

    if (!wela_is_product_image_target_directory($targetDirectory)) {
        wela_log('info', 'XT image processing skipped: target directory is not media/images/org.', [
            'file_name' => $filename,
            'target_directory' => $targetDirectory,
        ]);
        return [
            'success' => false,
            'filename' => basename(str_replace('\\', '/', $filename)),
            'class' => null,
            'generated_files' => [],
            'missing_files' => [],
            'skipped' => true,
        ];
    }

    $imageClass = wela_detect_xt_image_class($filename);
    wela_log('info', 'XT image class detected.', [
        'file_name' => $filename,
        'image_class' => $imageClass,
    ]);

    return wela_process_xt_commerce_image($filename, $imageClass);
}

function wela_process_xt_commerce_image(string $filename, string $imageClass): array
{
    global $db;

    $allowedClasses = ['product', 'category'];
    if (!in_array($imageClass, $allowedClasses, true)) {
        throw new InvalidArgumentException('Nicht unterstuetzte xt:Commerce-Bildklasse: ' . $imageClass);
    }

    $safeFileName = basename(trim(str_replace('\\', '/', $filename)));
    if ($safeFileName === '' || $safeFileName === '.' || $safeFileName === '..') {
        throw new InvalidArgumentException('Der Bilddateiname ist leer oder ungueltig.');
    }

    if (!defined('_SRV_WEBROOT') || !defined('_SRV_WEB_IMAGES')) {
        throw new RuntimeException('xt:Commerce-Bildpfade sind nicht initialisiert.');
    }
    if (!defined('TABLE_IMAGE_TYPE')) {
        throw new RuntimeException('TABLE_IMAGE_TYPE ist nicht definiert.');
    }
    if (!isset($db) || !is_object($db)) {
        throw new RuntimeException('xt:Commerce-Datenbankverbindung ist nicht verfuegbar.');
    }

    $originalFile = wela_xt_original_image_path($safeFileName);
    if (!is_file($originalFile) || !is_readable($originalFile)) {
        throw new RuntimeException('Originalbild nicht gefunden oder nicht lesbar: ' . $originalFile);
    }

    $imageClassFile = rtrim((string) _SRV_WEBROOT, '/\\')
        . DIRECTORY_SEPARATOR
        . trim((string) _SRV_WEB_FRAMEWORK, '/\\')
        . DIRECTORY_SEPARATOR
        . 'classes'
        . DIRECTORY_SEPARATOR
        . 'class.image.php';

    if (!class_exists('image', false)) {
        if (!is_file($imageClassFile)) {
            throw new RuntimeException('xt:Commerce-Bildklasse nicht gefunden: ' . $imageClassFile);
        }
        require_once $imageClassFile;
    }

    if (!class_exists('image', false)) {
        throw new RuntimeException('Die xt:Commerce-Klasse image konnte nicht geladen werden.');
    }

    wela_log('info', 'Loading XT image type configuration directly.', [
        'file_name' => $safeFileName,
        'image_class' => $imageClass,
        'table' => TABLE_IMAGE_TYPE,
    ]);

    $record = $db->Execute(
        'SELECT * FROM ' . TABLE_IMAGE_TYPE . ' WHERE class = ?',
        [$imageClass]
    );

    if (!$record || $record->RecordCount() === 0) {
        if ($record && method_exists($record, 'Close')) {
            $record->Close();
        }
        $record = $db->Execute(
            "SELECT * FROM " . TABLE_IMAGE_TYPE . " WHERE class = 'default'"
        );
    }

    $imageTypes = [];
    if ($record && $record->RecordCount() > 0) {
        while (!$record->EOF) {
            $imageTypes[] = $record->fields;
            $record->MoveNext();
        }
        $record->Close();
    }

    wela_log('info', 'Loaded XT image types directly.', [
        'file_name' => $safeFileName,
        'image_class' => $imageClass,
        'image_types_count' => count($imageTypes),
        'image_types' => $imageTypes,
    ]);

    if ($imageTypes === []) {
        throw new RuntimeException('Keine xt:Commerce-Bildtypen fuer "' . $imageClass . '" oder "default" gefunden.');
    }

    $imagesRoot = rtrim((string) _SRV_WEBROOT, '/\\')
        . DIRECTORY_SEPARATOR
        . trim((string) _SRV_WEB_IMAGES, '/\\')
        . DIRECTORY_SEPARATOR;

    $generatedFiles = [];
    $missingFiles = [];
    $errors = [];

    foreach ($imageTypes as $type) {
        $folder = trim((string) ($type['folder'] ?? ''), '/\\');
        if ($folder === '') {
            $errors[] = 'Bildtyp ohne Zielordner.';
            continue;
        }

        $targetDirectory = $imagesRoot . $folder . DIRECTORY_SEPARATOR;
        if (!is_dir($targetDirectory) && !mkdir($targetDirectory, 0777, true) && !is_dir($targetDirectory)) {
            $errors[] = 'Zielordner konnte nicht erstellt werden: ' . $targetDirectory;
            continue;
        }

        $targetFile = $targetDirectory . $safeFileName;
        $process = strtolower((string) ($type['process'] ?? 'true')) !== 'false';

        wela_log('info', 'Processing one XT image type.', [
            'file_name' => $safeFileName,
            'image_class' => $imageClass,
            'folder' => $folder,
            'width' => $type['width'] ?? null,
            'height' => $type['height'] ?? null,
            'process' => $process,
            'target_file' => $targetFile,
        ]);

        try {
            if ($process) {
                $image = new \image();
                $image->max_height = (int) ($type['height'] ?? 0);
                $image->max_width = (int) ($type['width'] ?? 0);
                $image->resource = $originalFile;
                $image->target_dir = $targetDirectory;
                $image->target_name = $safeFileName;
                if (array_key_exists('crop_status', $type)) {
                    $image->crop = $type['crop_status'];
                }

                $result = $image->createThumbnail();
                if ($result === false) {
                    $errors[] = 'createThumbnail() meldete Fehler fuer: ' . $targetFile;
                }
            } else {
                if (!copy($originalFile, $targetFile)) {
                    $errors[] = 'Originalbild konnte nicht kopiert werden: ' . $targetFile;
                }
            }
        } catch (Throwable $exception) {
            $errors[] = $targetFile . ': ' . $exception->getMessage();
            wela_log('error', 'XT image type processing failed.', [
                'file_name' => $safeFileName,
                'folder' => $folder,
                'target_file' => $targetFile,
                'exception' => $exception->getMessage(),
                'trace' => $exception->getTraceAsString(),
            ]);
        }

        clearstatcache(true, $targetFile);
        if (is_file($targetFile) && filesize($targetFile) > 0) {
            $generatedFiles[] = $targetFile;
        } else {
            $missingFiles[] = $targetFile;
        }
    }

    wela_log($errors === [] && $missingFiles === [] ? 'info' : 'error', 'Direct XT image processing finished.', [
        'file_name' => $safeFileName,
        'image_class' => $imageClass,
        'generated_files' => $generatedFiles,
        'missing_files' => $missingFiles,
        'errors' => $errors,
    ]);

    if ($errors !== [] || $missingFiles !== []) {
        throw new RuntimeException(
            'XT-Bildverarbeitung unvollstaendig. Fehler: ' . implode(' | ', array_merge($errors, $missingFiles))
        );
    }

    return [
        'success' => true,
        'filename' => $safeFileName,
        'class' => $imageClass,
        'generated_files' => $generatedFiles,
        'missing_files' => [],
        'skipped' => false,
    ];
}

function wela_is_product_image_target_directory(string $targetDirectory): bool
{
    $normalizedTargetDirectory = wela_normalize_image_directory_path($targetDirectory);

    if (defined('_SRV_WEBROOT') && defined('_SRV_WEB_IMAGES')) {
        $expected = rtrim((string) _SRV_WEBROOT, '/\\')
            . DIRECTORY_SEPARATOR
            . trim((string) _SRV_WEB_IMAGES, '/\\')
            . DIRECTORY_SEPARATOR
            . 'org';
    } else {
        $expected = dirname(__DIR__)
            . DIRECTORY_SEPARATOR . 'media'
            . DIRECTORY_SEPARATOR . 'images'
            . DIRECTORY_SEPARATOR . 'org';
    }

    $expectedImageDirectory = wela_normalize_image_directory_path($expected);

    wela_log('info', 'Compared upload directory with XT original image directory.', [
        'target_directory' => $targetDirectory,
        'normalized_target_directory' => $normalizedTargetDirectory,
        'expected_directory' => $expected,
        'normalized_expected_directory' => $expectedImageDirectory,
        'matches' => $normalizedTargetDirectory === $expectedImageDirectory,
    ]);

    return $normalizedTargetDirectory === $expectedImageDirectory;
}

function wela_verify_xt_generated_images(string $filename, array $imageTypes): array
{
    $generatedFiles = [];
    $missingFiles = [];

    foreach ($imageTypes as $imageType) {
        if (!is_array($imageType)) {
            continue;
        }

        $folder = trim((string) ($imageType['folder'] ?? ''), '/');
        if ($folder === '') {
            continue;
        }

        $targetFile = rtrim((string) _SRV_WEBROOT, '/\\')
            . DIRECTORY_SEPARATOR
            . trim((string) _SRV_WEB_IMAGES, '/\\')
            . DIRECTORY_SEPARATOR
            . str_replace(['/', '\\'], DIRECTORY_SEPARATOR, $folder)
            . DIRECTORY_SEPARATOR
            . $filename;

        $normalizedTargetFile = str_replace('\\', '/', $targetFile);

        if (is_file($normalizedTargetFile)) {
            $generatedFiles[] = $normalizedTargetFile;
        } else {
            $missingFiles[] = $normalizedTargetFile;
        }
    }

    return [
        'generated_files' => $generatedFiles,
        'missing_files' => $missingFiles,
    ];
}

function wela_xt_original_image_path(string $filename): string
{
    return rtrim((string) _SRV_WEBROOT, '/\\')
        . DIRECTORY_SEPARATOR
        . trim((string) _SRV_WEB_IMAGES, '/\\')
        . DIRECTORY_SEPARATOR
        . 'org'
        . DIRECTORY_SEPARATOR
        . $filename;
}

function wela_detect_xt_image_class(string $filename): string
{
    $pdo = $GLOBALS['wela_xt_pdo'] ?? null;

    if (!$pdo instanceof PDO) {
        return 'product';
    }

    if (wela_xt_category_image_exists($pdo, $filename)) {
        return 'category';
    }

    return 'product';
}

function wela_xt_category_image_exists(PDO $pdo, string $filename): bool
{
    foreach (['categories_image', 'categories_master_image'] as $field) {
        $statement = $pdo->prepare(sprintf(
            'SELECT 1 FROM `xt_categories` WHERE `%s` = :file_name LIMIT 1',
            $field
        ));
        $statement->execute([
            ':file_name' => $filename,
        ]);

        if ($statement->fetchColumn() !== false) {
            return true;
        }
    }

    return false;
}

function wela_normalize_image_directory_path(string $path): string
{
    $normalized = str_replace('\\', '/', trim($path));
    $realPath = realpath($normalized);

    if (is_string($realPath) && $realPath !== '') {
        $normalized = str_replace('\\', '/', $realPath);
    } elseif ($normalized !== '' && $normalized[0] !== '/' && preg_match('/^[A-Za-z]:[\/\\\\]/', $normalized) !== 1) {
        $normalized = str_replace('\\', '/', dirname(__DIR__) . DIRECTORY_SEPARATOR . ltrim($normalized, DIRECTORY_SEPARATOR));
    }

    return rtrim($normalized, '/');
}

