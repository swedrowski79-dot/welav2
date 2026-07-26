<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/bootstrap/xtcommerce.php';

$filename = $argv[1] ?? '';
$imageClass = $argv[2] ?? 'product';

if ($filename === '') {
    fwrite(STDERR, "Verwendung: php bin/test-xt-image.php DATEI [product|category]\n");
    exit(1);
}

if (!in_array($imageClass, ['product', 'category'], true)) {
    fwrite(STDERR, "Ungültige Bildklasse.\n");
    exit(1);
}

$originalPath = _SRV_WEBROOT . _SRV_WEB_IMAGES . 'org/' . basename($filename);

if (!is_file($originalPath)) {
    fwrite(STDERR, "Originalbild nicht gefunden: {$originalPath}\n");
    exit(1);
}

$mediaImages = new \MediaImages();
$mediaImages->setClass($imageClass);
$types = $mediaImages->getImageTypes();

echo 'Installationsstatus: ';
var_export($GLOBALS['_SYSTEM_INSTALL_SUCCESS'] ?? null);
echo PHP_EOL;
echo 'Bildklasse: ' . $imageClass . PHP_EOL;
echo 'Original: ' . $originalPath . PHP_EOL;
echo 'Bildtypen: ' . count((array) $types) . PHP_EOL;

$mediaImages->processImage(basename($filename), true);

echo "Bildverarbeitung abgeschlossen.\n";
