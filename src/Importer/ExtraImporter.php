<?php

class ExtraImporter
{
    public function __construct(
        private PDO $sourceDb,
        private StageWriter $stageWriter,
        private Normalizer $normalizer,
        private array $sourceConfig
    ) {
    }

    public function importArticleTranslations(): void
    {
        $table = $this->sourceConfig['entities']['article_translations']['table'];
        $stmt = $this->sourceDb->query("SELECT * FROM {$table}");

        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $normalized = $this->normalizer->normalize('extra.article_translations', $row);
            $this->stageWriter->insert('raw_extra_article_translations', $normalized);
        }
    }

    public function importCategoryTranslations(): void
    {
        $table = $this->sourceConfig['entities']['category_translations']['table'];
        $stmt = $this->sourceDb->query("SELECT * FROM {$table}");

        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $normalized = $this->normalizer->normalize('extra.category_translations', $row);
            $this->stageWriter->insert('raw_extra_category_translations', $normalized);
        }
    }
}
