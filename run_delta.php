<?php

require __DIR__ . '/src/Database/ConnectionFactory.php';
require __DIR__ . '/src/Monitoring/SyncMonitor.php';
require __DIR__ . '/src/Service/ProductDeltaService.php';
require __DIR__ . '/src/Service/DeltaRunnerService.php';

$configSources = require __DIR__ . '/config/sources.php';
$configDelta = require __DIR__ . '/config/delta.php';

$stageDb = ConnectionFactory::create($configSources['sources']['stage']);

$monitor = new SyncMonitor($stageDb);
$runId = $monitor->start('delta', [
    'script' => 'run_delta.php',
]);
$deltaService = new DeltaRunnerService($stageDb, $configDelta, $monitor, $runId);

try {
    $monitor->log($runId, 'info', 'Delta gestartet.');
    $stats = $deltaService->run();

    $monitor->finish($runId, 'success', [
        'merged_records' => (int) ($stats['changed'] ?? 0),
        'error_count' => (int) ($stats['errors'] ?? 0),
        'context' => $stats,
    ], 'Delta abgeschlossen.');

    echo "Delta abgeschlossen.\n";
} catch (Throwable $exception) {
    $monitor->log($runId, 'error', 'Delta fehlgeschlagen.', [
        'exception' => $exception->getMessage(),
    ]);
    $monitor->error($runId, $exception->getMessage(), [
        'source' => 'delta',
        'trace' => $exception->getTraceAsString(),
    ]);
    $monitor->finish($runId, 'failed', [
        'error_count' => 1,
    ], 'Delta fehlgeschlagen.');

    throw $exception;
}
