<?php

require __DIR__ . '/src/Web/bootstrap.php';

$envValues = (new App\Web\Repository\EnvFileRepository(__DIR__ . '/.env'))->load();
$imagePath = trim((string) ($envValues['IMAGES_ROOT_PATH'] ?? ''));

if ($imagePath === '') {
    throw new RuntimeException('IMAGES_ROOT_PATH ist nicht gesetzt.');
}

$sources = web_config('sources');
$connection = $sources['sources']['xt']['connection'] ?? [];
$client = new WelaApiClient(
    (string) ($connection['url'] ?? ''),
    (string) ($connection['key'] ?? ''),
    max(1, (int) ($connection['request_timeout_seconds'] ?? 30))
);
$targetPath = trim((string) ($envValues['XT_IMAGES_TARGET_PATH'] ?? ''));

$stageDb = App\Web\Repository\StageConnection::make();
$monitor = new SyncMonitor($stageDb);
$runId = $monitor->start('image_upload', [
    'script' => 'run_image_upload.php',
    'root_path' => $imagePath,
    'target_path' => $targetPath,
]);
$repository = new App\Web\Repository\ImageFileRepository($stageDb);

try {
    $monitor->log($runId, 'info', 'Bild-Upload gestartet.', [
        'root_path' => $imagePath,
        'target_path' => $targetPath,
    ]);
    $result = $repository->uploadPending($imagePath, $client, $targetPath, $monitor, $runId);
    $status = (int) ($result['errors'] ?? 0) > 0 ? 'warning' : 'success';
    $message = (int) ($result['pending'] ?? 0) === 0
        ? 'Keine offenen Bild-Dateien zum Upload gefunden.'
        : 'Bild-Upload abgeschlossen.';
    $monitor->finish($runId, $status, [
        'error_count' => (int) ($result['errors'] ?? 0),
        'context' => $result,
    ], $message);

    echo $message . "\n";
} catch (Throwable $exception) {
    $monitor->log($runId, 'error', 'Bild-Upload fehlgeschlagen.', [
        'exception' => $exception->getMessage(),
        'root_path' => $imagePath,
        'target_path' => $targetPath,
    ]);
    $monitor->error($runId, $exception->getMessage(), [
        'source' => 'image_upload',
        'root_path' => $imagePath,
        'target_path' => $targetPath,
        'trace' => $exception->getTraceAsString(),
    ]);
    $monitor->finish($runId, 'failed', [
        'error_count' => 1,
        'context' => [
            'root_path' => $imagePath,
            'target_path' => $targetPath,
        ],
    ], 'Bild-Upload fehlgeschlagen.');

    throw $exception;
}
