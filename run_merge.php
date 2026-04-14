<?php

require __DIR__ . '/src/Database/ConnectionFactory.php';
require __DIR__ . '/src/Monitoring/SyncMonitor.php';
require __DIR__ . '/src/Service/MergeService.php';

$configSources = require __DIR__ . '/config/sources.php';
$configMerge = require __DIR__ . '/config/merge.php';

$stageDb = ConnectionFactory::create($configSources['sources']['stage']);

$mergeService = new MergeService($stageDb, $configMerge);
$monitor = new SyncMonitor($stageDb);
$runId = $monitor->start('merge', [
    'script' => 'run_merge.php',
]);

try {
    $monitor->log($runId, 'info', 'Merge gestartet.');
    $mergeService->run();

    $mergedRecords = 0;
    foreach ([
        'stage_products',
        'stage_product_translations',
        'stage_categories',
        'stage_category_translations',
    ] as $table) {
        $mergedRecords += (int) $stageDb->query("SELECT COUNT(*) FROM `{$table}`")->fetchColumn();
    }

    $monitor->finish($runId, 'success', [
        'merged_records' => $mergedRecords,
        'context' => ['tables' => 4],
    ], 'Merge abgeschlossen.');

    echo "Merge abgeschlossen.\n";
} catch (Throwable $exception) {
    $monitor->log($runId, 'error', 'Merge fehlgeschlagen.', [
        'exception' => $exception->getMessage(),
    ]);
    $monitor->error($runId, $exception->getMessage(), [
        'source' => 'merge',
        'trace' => $exception->getTraceAsString(),
    ]);
    $monitor->finish($runId, 'failed', [
        'error_count' => 1,
    ], 'Merge fehlgeschlagen.');

    throw $exception;
}
