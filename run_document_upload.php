<?php

require __DIR__ . '/src/Web/bootstrap.php';

$envValues = (new App\Web\Repository\EnvFileRepository(__DIR__ . '/.env'))->load();
$documentPath = trim((string) ($envValues['DOCUMENTS_ROOT_PATH'] ?? ''));

if ($documentPath === '') {
    throw new RuntimeException('DOCUMENTS_ROOT_PATH ist nicht gesetzt.');
}

$sources = web_config('sources');
$connection = $sources['sources']['xt']['connection'] ?? [];
$client = new WelaApiClient(
    (string) ($connection['url'] ?? ''),
    (string) ($connection['key'] ?? ''),
    max(1, (int) ($connection['request_timeout_seconds'] ?? 30))
);
$targetPath = trim((string) ($envValues['XT_DOCUMENTS_TARGET_PATH'] ?? ''));

$stageDb = App\Web\Repository\StageConnection::make();
$monitor = new SyncMonitor($stageDb);
$runId = $monitor->start('document_upload', [
    'script' => 'run_document_upload.php',
    'root_path' => $documentPath,
    'target_path' => $targetPath,
]);
$repository = new App\Web\Repository\DocumentFileRepository($stageDb);

try {
    $monitor->log($runId, 'info', 'Dokument-Upload gestartet.', [
        'root_path' => $documentPath,
        'target_path' => $targetPath,
    ]);
    $result = $repository->uploadPending($documentPath, $client, $targetPath, $monitor, $runId);
    $status = (int) ($result['errors'] ?? 0) > 0 ? 'warning' : 'success';
    $message = (int) ($result['pending'] ?? 0) === 0
        ? 'Keine offenen Dokument-Dateien zum Upload gefunden.'
        : 'Dokument-Upload abgeschlossen.';
    $monitor->finish($runId, $status, [
        'error_count' => (int) ($result['errors'] ?? 0),
        'context' => $result,
    ], $message);

    echo $message . "\n";
} catch (Throwable $exception) {
    $monitor->log($runId, 'error', 'Dokument-Upload fehlgeschlagen.', [
        'exception' => $exception->getMessage(),
        'root_path' => $documentPath,
        'target_path' => $targetPath,
    ]);
    $monitor->error($runId, $exception->getMessage(), [
        'source' => 'document_upload',
        'root_path' => $documentPath,
        'target_path' => $targetPath,
        'trace' => $exception->getTraceAsString(),
    ]);
    $monitor->finish($runId, 'failed', [
        'error_count' => 1,
        'context' => [
            'root_path' => $documentPath,
            'target_path' => $targetPath,
        ],
    ], 'Dokument-Upload fehlgeschlagen.');

    throw $exception;
}
