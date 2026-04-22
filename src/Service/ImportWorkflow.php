<?php

declare(strict_types=1);

final class ImportWorkflow
{
    private PDO $stageDb;
    private PDO $extraDb;
    private StageWriter $stageWriter;
    private SyncMonitor $monitor;
    private AfsImporter $afsImporter;
    private ExtraImporter $extraImporter;
    private AttributeTranslationDictionaryService $attributeTranslationDictionary;

    public function __construct(
        private array $configSources,
        private array $configNormalize
    ) {
        $afsDb = ConnectionFactory::create($this->configSources['sources']['afs']);
        $extraDb = ConnectionFactory::create($this->configSources['sources']['extra']);
        $this->stageDb = ConnectionFactory::create($this->configSources['sources']['stage']);
        $this->extraDb = $extraDb;

        $normalizer = new Normalizer($this->configNormalize);
        $this->stageWriter = new StageWriter($this->stageDb);
        $this->monitor = new SyncMonitor($this->stageDb);
        $this->attributeTranslationDictionary = new AttributeTranslationDictionaryService($this->stageDb, $this->extraDb);
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
                'raw_afs_documents',
                'raw_extra_article_translations',
                'raw_extra_attribute_translations',
                'raw_extra_category_translations',
            ]);

            $this->importProductData($runId);
            $this->importCategoryData($runId);

            $this->finishSuccess($runId, [
                'raw_afs_articles',
                'raw_afs_categories',
                'raw_afs_documents',
                'raw_extra_article_translations',
                'raw_extra_attribute_translations',
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
                'raw_afs_documents',
                'raw_extra_article_translations',
                'raw_extra_attribute_translations',
            ]);

            $this->importProductData($runId);

            $this->finishSuccess($runId, [
                'raw_afs_articles',
                'raw_afs_documents',
                'raw_extra_article_translations',
                'raw_extra_attribute_translations',
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

        $dictionaryStats = $this->attributeTranslationDictionary->sync();
        $this->monitor->log($runId, 'info', 'Zentrale Attribut-Uebersetzungen in afs_extras synchronisiert.', $dictionaryStats);

        $this->afsImporter->importDocuments();
        $this->monitor->log($runId, 'info', 'AFS Dokumente importiert.');

        $this->extraImporter->importArticleTranslations();
        $this->monitor->log($runId, 'info', 'Extra Artikel-Uebersetzungen importiert.');

        $fallbackCount = $this->backfillAfsArticleTranslationFallbacks();
        $this->monitor->log($runId, 'info', 'AFS Attribut-Fallbacks in Extra Artikel-Uebersetzungen ergaenzt.', [
            'inserted_rows' => $fallbackCount,
        ]);

        $this->extraImporter->importAttributeTranslations();
        $this->monitor->log($runId, 'info', 'Zentrale Attribut-Uebersetzungen importiert.');
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

    private function backfillAfsArticleTranslationFallbacks(): int
    {
        $sql = <<<'SQL'
INSERT INTO `raw_extra_article_translations` (
    `afs_artikel_id`,
    `sku`,
    `master_sku`,
    `language_code`,
    `language_code_normalized`,
    `name`,
    `intro_text`,
    `description`,
    `attribute_name1`,
    `attribute_name2`,
    `attribute_name3`,
    `attribute_name4`,
    `attribute_value1`,
    `attribute_value2`,
    `attribute_value3`,
    `attribute_value4`,
    `meta_title`,
    `meta_description`,
    `is_master`,
    `source_directory`
)
SELECT
    a.`afs_artikel_id`,
    a.`sku`,
    a.`master_sku`,
    'de',
    'de',
    a.`name`,
    a.`short_text`,
    a.`description`,
    a.`attribute_name1`,
    a.`attribute_name2`,
    a.`attribute_name3`,
    a.`attribute_name4`,
    a.`attribute_value1`,
    a.`attribute_value2`,
    a.`attribute_value3`,
    a.`attribute_value4`,
    a.`name`,
    a.`description`,
    a.`is_master`,
    'afs_attribute_fallback'
FROM `raw_afs_articles` a
LEFT JOIN `raw_extra_article_translations` t
    ON t.`afs_artikel_id` = a.`afs_artikel_id`
   AND t.`language_code_normalized` = 'de'
WHERE t.`id` IS NULL
  AND (
      COALESCE(NULLIF(TRIM(a.`attribute_name1`), ''), NULLIF(TRIM(a.`attribute_value1`), '')) IS NOT NULL
      OR COALESCE(NULLIF(TRIM(a.`attribute_name2`), ''), NULLIF(TRIM(a.`attribute_value2`), '')) IS NOT NULL
      OR COALESCE(NULLIF(TRIM(a.`attribute_name3`), ''), NULLIF(TRIM(a.`attribute_value3`), '')) IS NOT NULL
      OR COALESCE(NULLIF(TRIM(a.`attribute_name4`), ''), NULLIF(TRIM(a.`attribute_value4`), '')) IS NOT NULL
  )
SQL;

        $stmt = $this->stageDb->prepare($sql);
        $stmt->execute();

        return $stmt->rowCount();
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
