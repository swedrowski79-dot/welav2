<?php

require __DIR__ . '/src/Web/bootstrap.php';

$envValues = (new App\Web\Repository\EnvFileRepository(__DIR__ . '/.env'))->load();
$imagePath = trim((string) ($envValues['IMAGES_ROOT_PATH'] ?? ''));

if ($imagePath === '') {
    throw new RuntimeException('IMAGES_ROOT_PATH ist nicht gesetzt.');
}

$stageDb = App\Web\Repository\StageConnection::make();
$monitor = new SyncMonitor($stageDb);
$runId = $monitor->start('image_scan', [
    'script' => 'run_image_scan.php',
    'root_path' => $imagePath,
]);
$repository = new App\Web\Repository\ImageFileRepository($stageDb);

try {
    $monitor->log($runId, 'info', 'Bild-Scan gestartet.', [
        'root_path' => $imagePath,
    ]);
    $result = $repository->scanDirectory($imagePath);
    $monitor->finish($runId, 'success', [
        'merged_records' => (int) ($result['updated'] ?? 0),
        'error_count' => 0,
        'context' => $result + ['root_path' => $imagePath],
    ], 'Bild-Scan abgeschlossen.');

    echo "Bild-Scan abgeschlossen.\n";
} catch (Throwable $exception) {
    $monitor->log($runId, 'error', 'Bild-Scan fehlgeschlagen.', [
        'exception' => $exception->getMessage(),
        'root_path' => $imagePath,
    ]);
    $monitor->error($runId, $exception->getMessage(), [
        'source' => 'image_scan',
        'root_path' => $imagePath,
        'trace' => $exception->getTraceAsString(),
    ]);
    $monitor->finish($runId, 'failed', [
        'error_count' => 1,
        'context' => [
            'root_path' => $imagePath,
        ],
    ], 'Bild-Scan fehlgeschlagen.');

    throw $exception;
}
