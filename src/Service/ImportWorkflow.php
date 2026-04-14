<?php

declare(strict_types=1);

final class ImportWorkflow
{
    private PDO $stageDb;
    private StageWriter $stageWriter;
    private SyncMonitor $monitor;
    private AfsImporter $afsImporter;
    private ExtraImporter $extraImporter;

    public function __construct(
        private array $configSources,
        private array $configNormalize
    ) {
        $afsDb = ConnectionFactory::create($this->configSources['sources']['afs']);
        $extraDb = ConnectionFactory::create($this->configSources['sources']['extra']);
        $this->stageDb = ConnectionFactory::create($this->configSources['sources']['stage']);

        $normalizer = new Normalizer($this->configNormalize);
        $this->stageWriter = new StageWriter($this->stageDb);
        $this->monitor = new SyncMonitor($this->stageDb);
        $this->afsImporter = new AfsImporter(
            $afsDb,
            $this->stageWriter,
            $normalizer,
            $this->configSources['sources']['afs']
        );
        $this->extraImporter = new ExtraImporter(
            $extraDb,
            $this->stageWriter,
            $normalizer,
            $this->configSources['sources']['extra']
        );
    }

    public function runAll(): void
    {
        $runId = $this->monitor->start('import_all', [
            'script' => 'run_import_all.php',
        ]);

        try {
            $this->monitor->log($runId, 'info', 'Importlauf gestartet.');

            $this->truncateTables([
                'raw_afs_articles',
                'raw_afs_categories',
                'raw_extra_article_translations',
                'raw_extra_category_translations',
            ]);

            $this->importProductData($runId);
            $this->importCategoryData($runId);

            $this->finishSuccess($runId, [
                'raw_afs_articles',
                'raw_afs_categories',
                'raw_extra_article_translations',
                'raw_extra_category_translations',
            ], 'Import aller Quellen abgeschlossen.');

            echo "Import aller Quellen abgeschlossen.\n";
        } catch (Throwable $exception) {
            $this->finishFailure($runId, 'import_all', $exception, 'Importlauf fehlgeschlagen.');
            throw $exception;
        }
    }

    public function runProducts(): void
    {
        $runId = $this->monitor->start('import_products', [
            'script' => 'run_import_products.php',
        ]);

        try {
            $this->monitor->log($runId, 'info', 'Produkt-Import gestartet.');

            $this->truncateTables([
                'raw_afs_articles',
                'raw_extra_article_translations',
            ]);

            $this->importProductData($runId);

            $this->finishSuccess($runId, [
                'raw_afs_articles',
                'raw_extra_article_translations',
            ], 'Produkt-Import abgeschlossen.');

            echo "Produkt-Import abgeschlossen.\n";
        } catch (Throwable $exception) {
            $this->finishFailure($runId, 'import_products', $exception, 'Produkt-Import fehlgeschlagen.');
            throw $exception;
        }
    }

    public function runCategories(): void
    {
        $runId = $this->monitor->start('import_categories', [
            'script' => 'run_import_categories.php',
        ]);

        try {
            $this->monitor->log($runId, 'info', 'Kategorie-Import gestartet.');

            $this->truncateTables([
                'raw_afs_categories',
                'raw_extra_category_translations',
            ]);

            $this->importCategoryData($runId);

            $this->finishSuccess($runId, [
                'raw_afs_categories',
                'raw_extra_category_translations',
            ], 'Kategorie-Import abgeschlossen.');

            echo "Kategorie-Import abgeschlossen.\n";
        } catch (Throwable $exception) {
            $this->finishFailure($runId, 'import_categories', $exception, 'Kategorie-Import fehlgeschlagen.');
            throw $exception;
        }
    }

    private function importProductData(?int $runId): void
    {
        $this->afsImporter->importArticles();
        $this->monitor->log($runId, 'info', 'AFS Artikel importiert.');

        $this->extraImporter->importArticleTranslations();
        $this->monitor->log($runId, 'info', 'Extra Artikel-Uebersetzungen importiert.');
    }

    private function importCategoryData(?int $runId): void
    {
        $this->afsImporter->importCategories();
        $this->monitor->log($runId, 'info', 'AFS Kategorien importiert.');

        $this->extraImporter->importCategoryTranslations();
        $this->monitor->log($runId, 'info', 'Extra Kategorie-Uebersetzungen importiert.');
    }

    private function truncateTables(array $tables): void
    {
        foreach ($tables as $table) {
            $this->stageWriter->truncate($table);
        }
    }

    private function finishSuccess(?int $runId, array $tables, string $message): void
    {
        $importedRecords = 0;

        foreach ($tables as $table) {
            $importedRecords += (int) $this->stageDb->query("SELECT COUNT(*) FROM `{$table}`")->fetchColumn();
        }

        $this->monitor->finish($runId, 'success', [
            'imported_records' => $importedRecords,
            'context' => ['tables' => count($tables)],
        ], $message);
    }

    private function finishFailure(?int $runId, string $source, Throwable $exception, string $message): void
    {
        $this->monitor->log($runId, 'error', $message, [
            'exception' => $exception->getMessage(),
        ]);
        $this->monitor->error($runId, $exception->getMessage(), [
            'source' => $source,
            'trace' => $exception->getTraceAsString(),
        ]);
        $this->monitor->finish($runId, 'failed', [
            'error_count' => 1,
        ], $message);
    }
}
