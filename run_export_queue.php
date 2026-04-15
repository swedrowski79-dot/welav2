<?php

require __DIR__ . '/src/Database/ConnectionFactory.php';
require __DIR__ . '/src/Monitoring/SyncMonitor.php';
require __DIR__ . '/src/Service/ExportQueueWorker.php';
require __DIR__ . '/src/Service/XtQueueWriter.php';
require __DIR__ . '/src/Service/WelaApiClient.php';
require __DIR__ . '/src/Service/AbstractXtWriter.php';
require __DIR__ . '/src/Service/XtCompositeWriter.php';
require __DIR__ . '/src/Service/XtProductWriter.php';
require __DIR__ . '/src/Service/XtMediaDocumentWriter.php';

$configSources = require __DIR__ . '/config/sources.php';
$configDelta = require __DIR__ . '/config/delta.php';
$configXtWrite = require __DIR__ . '/config/xt_write.php';

$stageDb = ConnectionFactory::create($configSources['sources']['stage']);
$monitor = new SyncMonitor($stageDb);
$runId = $monitor->start('export_queue_worker', [
    'script' => 'run_export_queue.php',
]);

try {
    $monitor->log($runId, 'info', 'Export Queue Worker gestartet.');
    $xtWriter = new XtCompositeWriter([
        new XtProductWriter($configSources, $configXtWrite),
        new XtMediaDocumentWriter($configSources, $configXtWrite),
    ]);
    $stats = (new ExportQueueWorker($stageDb, $configDelta, $monitor, $runId, $xtWriter))->run();

    $monitor->finish($runId, 'success', [
        'merged_records' => (int) ($stats['done'] ?? 0),
        'error_count' => (int) ($stats['error'] ?? 0),
        'context' => $stats,
    ], 'Export Queue Worker abgeschlossen.');

    echo "Export Queue Worker abgeschlossen.\n";
} catch (Throwable $exception) {
    $monitor->log($runId, 'error', 'Export Queue Worker fehlgeschlagen.', [
        'exception' => $exception->getMessage(),
    ]);
    $monitor->error($runId, $exception->getMessage(), [
        'source' => 'export_queue_worker',
        'trace' => $exception->getTraceAsString(),
    ]);
    $monitor->finish($runId, 'failed', [
        'error_count' => 1,
    ], 'Export Queue Worker fehlgeschlagen.');

    throw $exception;
}
