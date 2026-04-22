<?php

require __DIR__ . '/src/Database/ConnectionFactory.php';
require __DIR__ . '/src/Monitoring/SyncMonitor.php';
require __DIR__ . '/src/Service/MergeService.php';
require __DIR__ . '/src/Service/MissingTranslationRepository.php';
require __DIR__ . '/src/Service/MissingTranslationSyncService.php';
require __DIR__ . '/src/Service/StageProductVariantLinkService.php';
require __DIR__ . '/src/Web/Repository/StageConsistencyRepository.php';

$configSources = require __DIR__ . '/config/sources.php';
$configMerge = require __DIR__ . '/config/merge.php';
$configLanguages = require __DIR__ . '/config/languages.php';

$stageDb = ConnectionFactory::create($configSources['sources']['stage']);

$mergeService = new MergeService($stageDb, $configMerge);
$variantLinkService = new StageProductVariantLinkService($stageDb);
$missingTranslationSync = new MissingTranslationSyncService($stageDb, $configSources, $configLanguages);
$monitor = new SyncMonitor($stageDb);
$runId = $monitor->start('merge', [
    'script' => 'run_merge.php',
]);

try {
    $monitor->log($runId, 'info', 'Merge gestartet.');
    $mergeService->run();
    $variantLinkStats = $variantLinkService->sync();
    $monitor->log($runId, 'info', 'Master-/Slave-Verknuepfungen in stage_products aktualisiert.', $variantLinkStats);
    $missingTranslationStats = $missingTranslationSync->sync();
    $monitor->log($runId, 'info', 'Fehlende Uebersetzungen in Missing-SQLite synchronisiert.', $missingTranslationStats);

    $mergedRecords = 0;
    foreach ([
        'stage_products',
        'stage_product_translations',
        'stage_product_documents',
        'stage_categories',
        'stage_category_translations',
    ] as $table) {
        $mergedRecords += (int) $stageDb->query("SELECT COUNT(*) FROM `{$table}`")->fetchColumn();
    }

    $monitor->finish($runId, 'success', [
        'merged_records' => $mergedRecords,
        'context' => array_merge(['tables' => 5], $variantLinkStats, $missingTranslationStats),
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
