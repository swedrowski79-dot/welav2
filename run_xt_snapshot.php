<?php

require __DIR__ . '/src/Database/ConnectionFactory.php';
require __DIR__ . '/src/Monitoring/SyncMonitor.php';
require __DIR__ . '/src/Service/StageWriter.php';
require __DIR__ . '/src/Service/WelaApiClient.php';
require __DIR__ . '/src/Service/XtSnapshotService.php';

$configSources = require __DIR__ . '/config/sources.php';
$configXtRefresh = require __DIR__ . '/config/xt_snapshot.php';
$configXtMirror = require __DIR__ . '/config/xt_mirror.php';

$stageDb = ConnectionFactory::create($configSources['sources']['stage']);
$xtConnection = $configSources['sources']['xt']['connection'] ?? [];

$monitor = new SyncMonitor($stageDb);
$runId = $monitor->start('xt_snapshot', [
    'script' => 'run_xt_snapshot.php',
]);

try {
    $monitor->log($runId, 'info', 'XT-Mirror-Refresh gestartet.');

    $stats = (new XtSnapshotService(
        $stageDb,
        new WelaApiClient(
            (string) ($xtConnection['url'] ?? ''),
            (string) ($xtConnection['key'] ?? '')
        ),
        [
            'refresh' => $configXtRefresh['refresh'] ?? [],
            'mirror' => $configXtMirror['mirror'] ?? [],
        ],
        $monitor,
        $runId
    ))->run();

    $importedRecords = (int) (($stats['products'] ?? 0) + ($stats['categories'] ?? 0) + ($stats['media'] ?? 0) + ($stats['documents'] ?? 0));

    $monitor->finish($runId, 'success', [
        'imported_records' => $importedRecords,
        'context' => $stats,
    ], 'XT-Mirror-Refresh abgeschlossen.');

    echo "XT-Mirror-Refresh abgeschlossen.\n";
} catch (Throwable $exception) {
    $monitor->log($runId, 'error', 'XT-Mirror-Refresh fehlgeschlagen.', [
        'exception' => $exception->getMessage(),
    ]);
    $monitor->error($runId, $exception->getMessage(), [
        'source' => 'xt_snapshot',
        'trace' => $exception->getTraceAsString(),
    ]);
    $monitor->finish($runId, 'failed', [
        'error_count' => 1,
    ], 'XT-Mirror-Refresh fehlgeschlagen.');

    throw $exception;
}
