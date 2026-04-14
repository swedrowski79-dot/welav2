<?php

class AfsImporter
{
    public function __construct(
        private PDO $sourceDb,
        private StageWriter $stageWriter,
        private Normalizer $normalizer,
        private array $sourceConfig
    ) {
    }

    public function importArticles(): void
    {
        $table = $this->sourceConfig['entities']['articles']['table'];
        $stmt = $this->sourceDb->query("SELECT * FROM {$table}");

        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $normalized = $this->normalizer->normalize('afs.articles', $row);
            $this->stageWriter->insert('raw_afs_articles', $normalized);
        }
    }

    public function importCategories(): void
    {
        $table = $this->sourceConfig['entities']['categories']['table'];
        $stmt = $this->sourceDb->query("SELECT * FROM {$table}");

        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $normalized = $this->normalizer->normalize('afs.categories', $row);
            $this->stageWriter->insert('raw_afs_categories', $normalized);
        }
    }
}
