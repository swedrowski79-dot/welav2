<?php

declare(strict_types=1);

final class XtCategoryWriter extends AbstractXtWriter
{
    private array $languageConfig;
    private StageCategoryMap $categoryMap;
    private array $seoUrlCache = [];

    public function __construct(array $sourcesConfig, array $xtWriteConfig)
    {
        parent::__construct($sourcesConfig, $xtWriteConfig);

        $languageConfig = require dirname(__DIR__, 2) . '/config/languages.php';
        $this->languageConfig = is_array($languageConfig) ? $languageConfig : [];
        $this->categoryMap = new StageCategoryMap($sourcesConfig);
    }

    public function supports(string $entityType): bool
    {
        return $entityType === 'category';
    }

    public function write(string $entityType, array $entry, array $payload): void
    {
        if (!$this->supports($entityType)) {
            return;
        }

        $this->requireConfiguredClient('XT-API URL oder API-Key fehlt fuer Kategorie-Export.');

        if ($this->isOfflinePayload($payload)) {
            $this->writeOfflineCategory($entry);

            return;
        }

        $data = $payload['data'] ?? null;
        $category = is_array($data['category'] ?? null) ? $data['category'] : null;

        if (!is_array($data) || !is_array($category)) {
            throw new PermanentExportQueueException('Kategorie-Queue-Payload enthaelt keine gueltigen Kategoriedaten.');
        }

        $categoryDefinition = $this->definition('xt_categories');
        $category = $this->injectQueueIdentity($entry, $category, $categoryDefinition);
        $categoryIdentity = $this->entityIdentityValue($categoryDefinition, $category);
        $isInsert = !array_key_exists($categoryIdentity, $this->lookupMap('xt_categories', 'external_id', 'categories_id'));
        $translations = $this->normalizeTranslations($data['translations'] ?? []);

        $result = $this->client->syncCategory([
            'category' => [
                'identity' => [
                    (string) ($categoryDefinition['identity']['target_field'] ?? 'external_id') => $categoryIdentity,
                ],
                'columns' => $this->resolveColumns(
                    (array) ($categoryDefinition['columns'] ?? []),
                    ['stage' => $category],
                    $isInsert
                ),
            ],
            'translations' => $this->buildTranslationWrites($category, $translations),
            'seo_urls' => $this->buildSeoWrites($category, $translations, $isInsert),
        ]);

        $categoryId = $result['category_id'] ?? null;
        if (is_int($categoryId) || is_string($categoryId)) {
            $this->storeLookupValue('xt_categories', 'external_id', 'categories_id', $categoryIdentity, $categoryId);
        }
    }

    protected function resolveCalculatedExpression(string $expression, array $sources, bool $isInsert): mixed
    {
        $stage = is_array($sources['stage'] ?? null) ? $sources['stage'] : [];
        $categoryId = (string) ($stage['afs_wg_id'] ?? '');

        if (preg_match('/^calc:category_seo_url_(de|en|fr|nl)$/', $expression, $matches) === 1) {
            return $this->categorySeoUrl($categoryId, $matches[1]);
        }

        if (preg_match('/^calc:category_seo_url_md5_(de|en|fr|nl)$/', $expression, $matches) === 1) {
            return md5((string) $this->categorySeoUrl($categoryId, $matches[1]));
        }

        return match ($expression) {
            'calc:nested_set_left' => $this->categoryMap->leftRight($categoryId)['left'],
            'calc:nested_set_right' => $this->categoryMap->leftRight($categoryId)['right'],
            'calc:category_level' => $this->categoryLevel($categoryId),
            'calc:category_parent_id' => $this->resolveParentCategoryId($categoryId),
            'calc:category_status' => isset($stage['online_flag']) ? (int) $stage['online_flag'] : 0,
            'calc:is_top_category' => $this->categoryMap->isTopCategory($categoryId) ? 1 : 0,
            default => parent::resolveCalculatedExpression($expression, $sources, $isInsert),
        };
    }

    private function normalizeTranslations(mixed $translations): array
    {
        if (!is_array($translations)) {
            return [];
        }

        $normalized = [];

        foreach ($translations as $translation) {
            if (!is_array($translation)) {
                continue;
            }

            $languageCode = trim((string) ($translation['language_code'] ?? ''));
            if ($languageCode === '' || !in_array($languageCode, ['de', 'en', 'fr', 'nl'], true)) {
                continue;
            }

            $normalized[$languageCode] = $translation;
        }

        return $normalized;
    }

    private function buildTranslationWrites(array $category, array $translations): array
    {
        $definition = $this->definition('xt_categories_description');
        $writes = [];

        foreach ($definition['languages'] ?? ['de', 'en', 'fr', 'nl'] as $languageCode) {
            if (!is_string($languageCode) || $languageCode === '') {
                continue;
            }

            $languageColumns = $definition['columns_by_language'][$languageCode] ?? null;
            if (!is_array($languageColumns)) {
                continue;
            }

            $translation = $this->translationForLanguage($translations, $languageCode);
            $columns = $this->resolveColumns(
                $languageColumns,
                [
                    'stage' => $category,
                    'translation' => $translation,
                    'context' => ['language_code' => $languageCode],
                ],
                false,
                ['categories_id']
            );

            $writes[] = [
                'language_code' => $languageCode,
                'columns' => $columns,
            ];
        }

        return $writes;
    }

    private function buildSeoWrites(array $category, array $translations, bool $isInsert): array
    {
        $definition = $this->definition('xt_seo_url_categories');
        $languages = $definition['languages'] ?? ['de', 'en', 'fr', 'nl'];
        $writes = [];

        foreach ($languages as $languageCode) {
            if (!is_string($languageCode) || $languageCode === '') {
                continue;
            }

            $languageColumns = $definition['columns_by_language'][$languageCode] ?? null;
            if (!is_array($languageColumns)) {
                continue;
            }

            $translation = $this->translationForLanguage($translations, $languageCode);
            $columns = $this->resolveColumns(
                $languageColumns,
                [
                    'stage' => $category,
                    'translation' => $translation,
                    'context' => ['language_code' => $languageCode],
                ],
                $isInsert,
                ['link_id', 'url_text', 'url_md5']
            );

            $writes[] = [
                'language_code' => $languageCode,
                'auto_generate' => true,
                'auto_generate_class' => 'category',
                'columns' => $columns,
            ];
        }

        return $writes;
    }

    private function writeOfflineCategory(array $entry): void
    {
        $categoryDefinition = $this->definition('xt_categories');
        $categoryIdentity = trim((string) ($entry['entity_id'] ?? ''));

        if ($categoryIdentity === '') {
            throw new PermanentExportQueueException('Kategorie-Queue-Eintrag ohne Entity-ID kann nicht offline gesetzt werden.');
        }

        $result = $this->client->syncCategory([
            'category' => [
                'identity' => [
                    (string) ($categoryDefinition['identity']['target_field'] ?? 'external_id') => $categoryIdentity,
                ],
                'columns' => [
                    'categories_status' => 0,
                    'last_modified' => gmdate('Y-m-d H:i:s'),
                ],
            ],
            'translations' => [],
            'seo_urls' => [],
        ]);

        $categoryId = $result['category_id'] ?? null;
        if (is_int($categoryId) || is_string($categoryId)) {
            $this->storeLookupValue('xt_categories', 'external_id', 'categories_id', $categoryIdentity, $categoryId);
        }
    }

    private function isOfflinePayload(array $payload): bool
    {
        $data = $payload['data'] ?? null;

        return is_array($data)
            && !isset($data['category'])
            && (($data['online_flag'] ?? null) === 0 || ($data['online_flag'] ?? null) === '0');
    }

    private function resolveParentCategoryId(string $categoryId): int
    {
        $parentId = $this->categoryMap->parentId($categoryId);
        if ($parentId === null) {
            return 0;
        }

        $map = $this->lookupMap('xt_categories', 'external_id', 'categories_id');
        if (!array_key_exists($parentId, $map)) {
            throw new PermanentExportQueueException("XT-Elternkategorie mit external_id '{$parentId}' wurde nicht gefunden.");
        }

        return (int) $map[$parentId];
    }

    private function categoryLevel(string $categoryId): int
    {
        if (!$this->categoryMap->hasCategory($categoryId)) {
            return 0;
        }

        return $this->categoryMap->depth($categoryId) + 1;
    }

    private function categorySeoUrl(string $categoryId, string $languageCode): string
    {
        $cacheKey = $languageCode . '|' . $categoryId;

        if ($categoryId !== '' && array_key_exists($cacheKey, $this->seoUrlCache)) {
            return $this->seoUrlCache[$cacheKey];
        }

        $segments = $this->categoryMap->pathSegmentsLeafFirst($categoryId, $languageCode, $this->fallbackChain($languageCode));
        $slugs = array_values(array_filter(array_map([$this, 'slugify'], $segments), static fn (string $slug): bool => $slug !== ''));
        $prefix = $this->languagePrefix($languageCode);
        $url = implode('/', array_filter(array_merge([$prefix], $slugs), static fn (string $segment): bool => $segment !== ''));

        $resolved = $this->reserveUniqueSeoUrl($url, $languageCode, $this->slugify($categoryId));

        if ($categoryId !== '') {
            $this->seoUrlCache[$cacheKey] = $resolved;
        }

        return $resolved;
    }

    private function translationForLanguage(array $translations, string $languageCode): array
    {
        if (isset($translations[$languageCode]) && is_array($translations[$languageCode])) {
            return $translations[$languageCode];
        }

        foreach ($this->fallbackChain($languageCode) as $fallbackCode) {
            if (isset($translations[$fallbackCode]) && is_array($translations[$fallbackCode])) {
                return $translations[$fallbackCode];
            }
        }

        return [];
    }

    private function fallbackChain(string $languageCode): array
    {
        foreach ($this->languageConfig['languages'] ?? [] as $language) {
            if (!is_array($language) || ($language['code'] ?? null) !== $languageCode) {
                continue;
            }

            $chain = $language['fallback_chain'] ?? [];

            return array_values(array_filter(
                is_array($chain) ? $chain : [],
                static fn (mixed $code): bool => is_string($code) && $code !== ''
            ));
        }

        return [$languageCode, 'de'];
    }

    private function languagePrefix(string $languageCode): string
    {
        $prefixes = $this->languageConfig['seo']['prefixes'] ?? [];
        $prefix = $prefixes[$languageCode] ?? $languageCode;

        return trim((string) $prefix, '/');
    }

    private function slugify(string $value): string
    {
        $normalized = iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $value);
        if (!is_string($normalized) || $normalized === '') {
            $normalized = $value;
        }

        $normalized = strtolower($normalized);
        $normalized = preg_replace('/[^a-z0-9]+/', '-', $normalized) ?? '';
        $normalized = trim($normalized, '-');

        return $normalized !== '' ? $normalized : 'item';
    }
}
