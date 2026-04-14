<?php

require __DIR__ . '/src/Database/ConnectionFactory.php';
require __DIR__ . '/src/Monitoring/SyncMonitor.php';
require __DIR__ . '/src/Service/Normalizer.php';
require __DIR__ . '/src/Service/StageWriter.php';
require __DIR__ . '/src/Importer/AfsImporter.php';
require __DIR__ . '/src/Importer/ExtraImporter.php';

$configSources = require __DIR__ . '/config/sources.php';
$configNormalize = require __DIR__ . '/config/normalize.php';

$afsDb = ConnectionFactory::create($configSources['sources']['afs']);
$extraDb = ConnectionFactory::create($configSources['sources']['extra']);
$stageDb = ConnectionFactory::create($configSources['sources']['stage']);

$normalizer = new Normalizer($configNormalize);
$stageWriter = new StageWriter($stageDb);
$monitor = new SyncMonitor($stageDb);
$runId = $monitor->start('import_all', [
    'script' => 'run_import_all.php',
]);

try {
    $monitor->log($runId, 'info', 'Importlauf gestartet.');

    $stageWriter->truncate('raw_afs_articles');
    $stageWriter->truncate('raw_afs_categories');
    $stageWriter->truncate('raw_extra_article_translations');
    $stageWriter->truncate('raw_extra_category_translations');

    $afsImporter = new AfsImporter(
        $afsDb,
        $stageWriter,
        $normalizer,
        $configSources['sources']['afs']
    );

    $extraImporter = new ExtraImporter(
        $extraDb,
        $stageWriter,
        $normalizer,
        $configSources['sources']['extra']
    );

    $afsImporter->importArticles();
    $monitor->log($runId, 'info', 'AFS Artikel importiert.');

    $afsImporter->importCategories();
    $monitor->log($runId, 'info', 'AFS Kategorien importiert.');

    $extraImporter->importArticleTranslations();
    $monitor->log($runId, 'info', 'Extra Artikel-Uebersetzungen importiert.');

    $extraImporter->importCategoryTranslations();
    $monitor->log($runId, 'info', 'Extra Kategorie-Uebersetzungen importiert.');

    $importedRecords = 0;
    foreach ([
        'raw_afs_articles',
        'raw_afs_categories',
        'raw_extra_article_translations',
        'raw_extra_category_translations',
    ] as $table) {
        $importedRecords += (int) $stageDb->query("SELECT COUNT(*) FROM `{$table}`")->fetchColumn();
    }

    $monitor->finish($runId, 'success', [
        'imported_records' => $importedRecords,
        'context' => ['tables' => 4],
    ], 'Import aller Quellen abgeschlossen.');

    echo "Import aller Quellen abgeschlossen.\n";
} catch (Throwable $exception) {
    $monitor->log($runId, 'error', 'Importlauf fehlgeschlagen.', [
        'exception' => $exception->getMessage(),
    ]);
    $monitor->error($runId, $exception->getMessage(), [
        'source' => 'import_all',
        'trace' => $exception->getTraceAsString(),
    ]);
    $monitor->finish($runId, 'failed', [
        'error_count' => 1,
    ], 'Importlauf fehlgeschlagen.');

    throw $exception;
}
