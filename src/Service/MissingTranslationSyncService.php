<?php

declare(strict_types=1);

final class MissingTranslationSyncService
{
    private MissingTranslationRepository $repository;
    private array $languageCodes;
    private App\Web\Repository\StageConsistencyRepository $consistencyRepository;

    public function __construct(
        private PDO $stageDb,
        private array $sourcesConfig,
        array $languageConfig
    ) {
        $connectionConfig = $this->sourcesConfig['sources']['missing_translations'] ?? null;
        if (!is_array($connectionConfig)) {
            throw new RuntimeException('Missing translations source is not configured.');
        }

        $this->repository = new MissingTranslationRepository($connectionConfig);
        $this->languageCodes = $this->languageCodes($languageConfig);
        $this->consistencyRepository = new App\Web\Repository\StageConsistencyRepository();
    }

    public function sync(): array
    {
        $this->repository->ensureSchema();

        $articleStats = $this->syncArticles();
        $categoryStats = $this->syncCategories();

        return [
            'articles_missing_upserts' => $articleStats['missing_upserts'],
            'articles_done_updates' => $articleStats['done_updates'],
            'categories_missing_upserts' => $categoryStats['missing_upserts'],
            'categories_done_updates' => $categoryStats['done_updates'],
        ];
    }

    private function syncArticles(): array
    {
        $products = $this->fetchProducts();
        $translationsByArticle = $this->fetchTranslationLanguages('stage_product_translations', 'afs_artikel_id');
        $withoutTranslations = array_fill_keys(
            array_map(
                static fn (array $row): string => (string) $row['article_id'],
                $this->consistencyRepository->missingProductsWithoutTranslations($this->stageDb)
            ),
            true
        );

        $stats = ['missing_upserts' => 0, 'done_updates' => 0];

        foreach ($products as $product) {
            $articleId = (string) ($product['article_id'] ?? '');
            if ($articleId === '') {
                continue;
            }

            $availableLanguages = $translationsByArticle[$articleId] ?? [];

            foreach ($this->languageCodes as $languageCode) {
                if (isset($withoutTranslations[$articleId]) || !isset($availableLanguages[$languageCode])) {
                    $this->repository->upsertMissingArticle(
                        $articleId,
                        $product['article_number'] ?? null,
                        $product['article_name'] ?? null,
                        $languageCode
                    );
                    $stats['missing_upserts']++;
                    continue;
                }

                $this->repository->markArticleTranslationDone($articleId, $languageCode);
                $stats['done_updates']++;
            }
        }

        return $stats;
    }

    private function syncCategories(): array
    {
        $categories = $this->fetchCategories();
        $translationsByCategory = $this->fetchTranslationLanguages('stage_category_translations', 'afs_wg_id');
        $withoutTranslations = array_fill_keys(
            array_map(
                static fn (array $row): string => (string) $row['category_id'],
                $this->consistencyRepository->missingCategoriesWithoutTranslations($this->stageDb)
            ),
            true
        );

        $stats = ['missing_upserts' => 0, 'done_updates' => 0];

        foreach ($categories as $category) {
            $categoryId = (string) ($category['category_id'] ?? '');
            if ($categoryId === '') {
                continue;
            }

            $availableLanguages = $translationsByCategory[$categoryId] ?? [];

            foreach ($this->languageCodes as $languageCode) {
                if (isset($withoutTranslations[$categoryId]) || !isset($availableLanguages[$languageCode])) {
                    $this->repository->upsertMissingCategory(
                        $categoryId,
                        $category['category_name'] ?? null,
                        $languageCode
                    );
                    $stats['missing_upserts']++;
                    continue;
                }

                $this->repository->markCategoryTranslationDone($categoryId, $languageCode);
                $stats['done_updates']++;
            }
        }

        return $stats;
    }

    private function fetchProducts(): array
    {
        $stmt = $this->stageDb->query(
            'SELECT afs_artikel_id AS article_id, sku AS article_number, name_default AS article_name
             FROM stage_products
             WHERE afs_artikel_id IS NOT NULL
             ORDER BY afs_artikel_id ASC'
        );

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    private function fetchCategories(): array
    {
        $stmt = $this->stageDb->query(
            'SELECT afs_wg_id AS category_id, name_default AS category_name
             FROM stage_categories
             WHERE afs_wg_id IS NOT NULL
             ORDER BY afs_wg_id ASC'
        );

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    private function fetchTranslationLanguages(string $table, string $identityField): array
    {
        $stmt = $this->stageDb->query(
            "SELECT `{$identityField}` AS identity_id, language_code
             FROM `{$table}`
             WHERE `{$identityField}` IS NOT NULL
               AND language_code IS NOT NULL
               AND language_code <> ''
             GROUP BY `{$identityField}`, language_code"
        );

        $map = [];

        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $identityId = trim((string) ($row['identity_id'] ?? ''));
            $languageCode = trim((string) ($row['language_code'] ?? ''));

            if ($identityId === '' || $languageCode === '') {
                continue;
            }

            $map[$identityId][$languageCode] = true;
        }

        return $map;
    }

    private function languageCodes(array $languageConfig): array
    {
        $languages = $languageConfig['languages'] ?? [];
        $codes = [];

        foreach ($languages as $language) {
            $code = trim((string) ($language['code'] ?? ''));
            if ($code === '') {
                continue;
            }

            $codes[] = $code;
        }

        return array_values(array_unique($codes));
    }
}
