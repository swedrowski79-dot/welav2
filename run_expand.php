<?php

require __DIR__ . '/src/Database/ConnectionFactory.php';
require __DIR__ . '/src/Monitoring/SyncMonitor.php';
require __DIR__ . '/src/Service/ExpandService.php';
require __DIR__ . '/src/Service/ProductDeltaService.php';
require __DIR__ . '/src/Service/DeltaRunnerService.php';

$configSources = require __DIR__ . '/config/sources.php';
$configExpand = require __DIR__ . '/config/expand.php';
$configDelta = require __DIR__ . '/config/delta.php';

$stageDb = ConnectionFactory::create($configSources['sources']['stage']);

$expandService = new ExpandService($stageDb, $configExpand);
$monitor = new SyncMonitor($stageDb);
$runId = $monitor->start('expand', [
    'script' => 'run_expand.php',
]);

try {
    $monitor->log($runId, 'info', 'Expand gestartet.');
    $expandService->run();
    $deltaService = new DeltaRunnerService($stageDb, $configDelta, $monitor, $runId);
    $deltaStats = $deltaService->run();

    $expandedRecords = 0;
    foreach ([
        'stage_attribute_translations',
        'stage_product_media',
    ] as $table) {
        $expandedRecords += (int) $stageDb->query("SELECT COUNT(*) FROM `{$table}`")->fetchColumn();
    }

    $monitor->finish($runId, 'success', [
        'merged_records' => $expandedRecords,
        'error_count' => (int) ($deltaStats['errors'] ?? 0),
        'context' => [
            'tables' => [
                'stage_attribute_translations',
                'stage_product_media',
            ],
            'delta' => $deltaStats,
        ],
    ], 'Expand von Attributen, Medien und Delta abgeschlossen.');

    echo "Expand von Attributen, Medien und Delta abgeschlossen.\n";
} catch (Throwable $exception) {
    $monitor->log($runId, 'error', 'Expand fehlgeschlagen.', [
        'exception' => $exception->getMessage(),
    ]);
    $monitor->error($runId, $exception->getMessage(), [
        'source' => 'expand',
        'trace' => $exception->getTraceAsString(),
    ]);
    $monitor->finish($runId, 'failed', [
        'error_count' => 1,
    ], 'Expand fehlgeschlagen.');

    throw $exception;
}
