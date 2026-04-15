<?php

require __DIR__ . '/src/Database/ConnectionFactory.php';
require __DIR__ . '/src/Monitoring/SyncMonitor.php';
require __DIR__ . '/src/Service/StageWriter.php';
require __DIR__ . '/src/Service/WelaApiClient.php';
require __DIR__ . '/src/Service/XtSnapshotService.php';

$configSources = require __DIR__ . '/config/sources.php';
$configXtSnapshot = require __DIR__ . '/config/xt_snapshot.php';

$stageDb = ConnectionFactory::create($configSources['sources']['stage']);
$xtConnection = $configSources['sources']['xt']['connection'] ?? [];

$monitor = new SyncMonitor($stageDb);
$runId = $monitor->start('xt_snapshot', [
    'script' => 'run_xt_snapshot.php',
]);

try {
    $monitor->log($runId, 'info', 'XT-Snapshot-Import gestartet.');

    $stats = (new XtSnapshotService(
        $stageDb,
        new WelaApiClient(
            (string) ($xtConnection['url'] ?? ''),
            (string) ($xtConnection['key'] ?? '')
        ),
        $configXtSnapshot,
        $monitor,
        $runId
    ))->run();

    $importedRecords = (int) (($stats['products'] ?? 0) + ($stats['categories'] ?? 0) + ($stats['media'] ?? 0) + ($stats['documents'] ?? 0));

    $monitor->finish($runId, 'success', [
        'imported_records' => $importedRecords,
        'context' => $stats,
    ], 'XT-Snapshot-Import abgeschlossen.');

    echo "XT-Snapshot-Import abgeschlossen.\n";
} catch (Throwable $exception) {
    $monitor->log($runId, 'error', 'XT-Snapshot-Import fehlgeschlagen.', [
        'exception' => $exception->getMessage(),
    ]);
    $monitor->error($runId, $exception->getMessage(), [
        'source' => 'xt_snapshot',
        'trace' => $exception->getTraceAsString(),
    ]);
    $monitor->finish($runId, 'failed', [
        'error_count' => 1,
    ], 'XT-Snapshot-Import fehlgeschlagen.');

    throw $exception;
}
