<?php

require __DIR__ . '/src/Database/ConnectionFactory.php';
require __DIR__ . '/src/Monitoring/SyncMonitor.php';
require __DIR__ . '/src/Service/ExpandService.php';

$configSources = require __DIR__ . '/config/sources.php';
$configExpand = require __DIR__ . '/config/expand.php';

$stageDb = ConnectionFactory::create($configSources['sources']['stage']);

$expandService = new ExpandService($stageDb, $configExpand);
$monitor = new SyncMonitor($stageDb);
$runId = $monitor->start('expand', [
    'script' => 'run_expand.php',
]);

try {
    $monitor->log($runId, 'info', 'Expand gestartet.');
    $expandService->run();

    $expandedRecords = (int) $stageDb->query('SELECT COUNT(*) FROM `stage_attribute_translations`')->fetchColumn();

    $monitor->finish($runId, 'success', [
        'merged_records' => $expandedRecords,
        'context' => ['table' => 'stage_attribute_translations'],
    ], 'Expand abgeschlossen.');

    echo "Expand abgeschlossen.\n";
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
