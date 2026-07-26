<?php

require __DIR__ . '/src/Web/bootstrap.php';

$envValues = (new App\Web\Repository\EnvFileRepository(__DIR__ . '/.env'))->load();
$documentPath = trim((string) ($envValues['DOCUMENTS_ROOT_PATH'] ?? ''));

if ($documentPath === '') {
    throw new RuntimeException('DOCUMENTS_ROOT_PATH ist nicht gesetzt.');
}

$stageDb = App\Web\Repository\StageConnection::make();
$monitor = new SyncMonitor($stageDb);
$runId = $monitor->start('document_scan', [
    'script' => 'run_document_scan.php',
    'root_path' => $documentPath,
]);
$repository = new App\Web\Repository\DocumentFileRepository($stageDb);

try {
    $monitor->log($runId, 'info', 'Dokument-Scan gestartet.', [
        'root_path' => $documentPath,
    ]);
    $result = $repository->scanDirectory($documentPath);
    $monitor->finish($runId, 'success', [
        'merged_records' => (int) ($result['updated'] ?? 0),
        'error_count' => 0,
        'context' => $result + ['root_path' => $documentPath],
    ], 'Dokument-Scan abgeschlossen.');

    echo "Dokument-Scan abgeschlossen.\n";
} catch (Throwable $exception) {
    $monitor->log($runId, 'error', 'Dokument-Scan fehlgeschlagen.', [
        'exception' => $exception->getMessage(),
        'root_path' => $documentPath,
    ]);
    $monitor->error($runId, $exception->getMessage(), [
        'source' => 'document_scan',
        'root_path' => $documentPath,
        'trace' => $exception->getTraceAsString(),
    ]);
    $monitor->finish($runId, 'failed', [
        'error_count' => 1,
        'context' => [
            'root_path' => $documentPath,
        ],
    ], 'Dokument-Scan fehlgeschlagen.');

    throw $exception;
}
