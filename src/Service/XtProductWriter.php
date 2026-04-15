<?php

declare(strict_types=1);

final class XtProductWriter extends AbstractXtWriter
{
    private array $languageConfig;

    public function __construct(array $sourcesConfig, array $xtWriteConfig)
    {
        parent::__construct($sourcesConfig, $xtWriteConfig);

        $languageConfig = require dirname(__DIR__, 2) . '/config/languages.php';
        $this->languageConfig = is_array($languageConfig) ? $languageConfig : [];
    }

    public function supports(string $entityType): bool
    {
        return $entityType === 'product';
    }

    public function write(string $entityType, array $entry, array $payload): void
    {
        if (!$this->supports($entityType)) {
            return;
        }

        $this->requireConfiguredClient('XT-API URL oder API-Key fehlt fuer Produkt-Export.');

        if ($this->isOfflinePayload($payload)) {
            $this->writeOfflineProduct($entry);

            return;
        }

        $data = $payload['data'] ?? null;
        $product = is_array($data['product'] ?? null) ? $data['product'] : null;

        if (!is_array($data) || !is_array($product)) {
            throw new PermanentExportQueueException('Produkt-Queue-Payload enthaelt keine gueltigen Produktdaten.');
        }

        $productDefinition = $this->definition('xt_products');
        $product = $this->injectQueueIdentity($entry, $product, $productDefinition);
        $productIdentity = $this->entityIdentityValue($productDefinition, $product);
        $isInsert = !array_key_exists($productIdentity, $this->lookupMap('xt_products', 'external_id', 'products_id'));

        $translations = $this->normalizeTranslations($data['translations'] ?? []);
        $attributes = $this->normalizeAttributes($data['attributes'] ?? [], $product);

        $this->client->syncProduct([
            'product' => [
                'identity' => [
                    (string) ($productDefinition['identity']['target_field'] ?? 'external_id') => $productIdentity,
                ],
                'columns' => $this->resolveColumns(
                    (array) ($productDefinition['columns'] ?? []),
                    ['stage' => $product],
                    $isInsert
                ),
            ],
            'translations' => $this->buildTranslationWrites($product, $translations),
            'replace_categories' => true,
            'category_relations' => $this->buildCategoryRelations($product),
            'replace_attributes' => true,
            'attribute_entities' => $this->buildAttributeEntities($attributes),
            'attribute_descriptions' => $this->buildAttributeDescriptions($attributes),
            'attribute_relations' => $this->buildAttributeRelations($attributes),
            'seo_urls' => $this->buildSeoWrites($product, $translations, $isInsert),
        ]);
    }

    protected function resolveCalculatedExpression(string $expression, array $sources, bool $isInsert): mixed
    {
        $stage = is_array($sources['stage'] ?? null) ? $sources['stage'] : [];

        if (preg_match('/^calc:product_seo_url_(de|en|fr|nl)$/', $expression, $matches) === 1) {
            return $this->productSeoUrl($matches[1], $sources);
        }

        if (preg_match('/^calc:product_seo_url_md5_(de|en|fr|nl)$/', $expression, $matches) === 1) {
            return md5($this->productSeoUrl($matches[1], $sources));
        }

        return match ($expression) {
            'calc:product_price' => $stage['price'] ?? null,
            'calc:product_status' => isset($stage['online_flag']) ? (int) $stage['online_flag'] : 0,
            'calc:tax_class_id' => $this->taxClassId($stage['tax_rate'] ?? null),
            'calc:product_unit' => $stage['unit'] ?? null,
            default => parent::resolveCalculatedExpression($expression, $sources, $isInsert),
        };
    }

    private function writeOfflineProduct(array $entry): void
    {
        $productDefinition = $this->definition('xt_products');
        $productIdentity = trim((string) ($entry['entity_id'] ?? ''));

        if ($productIdentity === '') {
            throw new PermanentExportQueueException('Produkt-Queue-Eintrag ohne Entity-ID kann nicht offline gesetzt werden.');
        }

        if (!array_key_exists($productIdentity, $this->lookupMap('xt_products', 'external_id', 'products_id'))) {
            throw new PermanentExportQueueException("XT-Produkt mit external_id '{$productIdentity}' wurde fuer Offline-Update nicht gefunden.");
        }

        $this->client->syncProduct([
            'product' => [
                'identity' => [
                    (string) ($productDefinition['identity']['target_field'] ?? 'external_id') => $productIdentity,
                ],
                'columns' => [
                    'products_status' => 0,
                    'last_modified' => gmdate('Y-m-d H:i:s'),
                ],
            ],
            'translations' => [],
            'replace_categories' => false,
            'category_relations' => [],
            'replace_attributes' => false,
            'attribute_entities' => [],
            'attribute_descriptions' => [],
            'attribute_relations' => [],
        ]);
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

    private function normalizeAttributes(mixed $attributes, array $product): array
    {
        if (!is_array($attributes)) {
            return [];
        }

        $productIdentity = trim((string) ($product['afs_artikel_id'] ?? ''));
        $normalized = [];

        foreach ($attributes as $attribute) {
            if (!is_array($attribute)) {
                continue;
            }

            $sortOrder = $attribute['sort_order'] ?? null;
            $languageCode = trim((string) ($attribute['language_code'] ?? ''));
            $attributeName = trim((string) ($attribute['attribute_name'] ?? ''));
            $attributeValue = trim((string) ($attribute['attribute_value'] ?? ''));

            if ((!is_int($sortOrder) && !ctype_digit((string) $sortOrder))
                || $languageCode === ''
                || ($attributeName === '' && $attributeValue === '')
            ) {
                continue;
            }

            $sortOrder = (int) $sortOrder;
            $attribute['sort_order'] = $sortOrder;
            $attribute['attribute_model'] = 'afs-product-' . $productIdentity . '-attribute-' . $sortOrder;
            $attribute['afs_artikel_id'] = $product['afs_artikel_id'] ?? null;

            $normalized[$sortOrder]['entity'] = [
                'attribute_model' => $attribute['attribute_model'],
                'sort_order' => $sortOrder,
            ];
            $normalized[$sortOrder]['translations'][$languageCode] = $attribute;
        }

        ksort($normalized);

        return $normalized;
    }

    private function buildTranslationWrites(array $product, array $translations): array
    {
        $definition = $this->definition('xt_products_description');
        $writes = [];

        foreach ($translations as $languageCode => $translation) {
            $languageColumns = $definition['columns_by_language'][$languageCode] ?? null;

            if (!is_array($languageColumns)) {
                continue;
            }

            $columns = $this->resolveColumns(
                $languageColumns,
                [
                    'stage' => $product,
                    'translation' => $translation,
                    'context' => ['language_code' => $languageCode],
                ],
                false,
                ['products_id']
            );

            $writes[] = [
                'language_code' => $languageCode,
                'columns' => $columns,
            ];
        }

        return $writes;
    }

    private function buildCategoryRelations(array $product): array
    {
        $definition = $this->definition('xt_products_to_categories');
        $categoryId = trim((string) ($product['category_afs_id'] ?? ''));

        if ($categoryId === '') {
            return [];
        }

        return [[
            'columns' => $this->resolveColumns(
                (array) ($definition['columns'] ?? []),
                ['stage' => $product],
                false,
                ['products_id']
            ),
        ]];
    }

    private function buildSeoWrites(array $product, array $translations, bool $isInsert): array
    {
        $definition = $this->definition('xt_seo_url_products');
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
                    'stage' => $product,
                    'translation' => $translation,
                    'context' => ['language_code' => $languageCode],
                ],
                $isInsert,
                ['link_id']
            );

            $urlText = trim((string) ($columns['url_text'] ?? ''));
            if ($urlText === '') {
                continue;
            }

            $writes[] = [
                'language_code' => $languageCode,
                'columns' => $columns,
            ];
        }

        return $writes;
    }

    private function buildAttributeEntities(array $attributes): array
    {
        $definition = $this->definition('xt_plg_products_attributes');
        $writes = [];

        foreach ($attributes as $attributeGroup) {
            $attributeEntity = $attributeGroup['entity'] ?? null;

            if (!is_array($attributeEntity)) {
                continue;
            }

            $writes[] = [
                'attribute_model' => (string) ($attributeEntity['attribute_model'] ?? ''),
                'columns' => $this->resolveColumns(
                    (array) ($definition['columns'] ?? []),
                    ['attribute' => $attributeEntity],
                    !array_key_exists(
                        (string) ($attributeEntity['attribute_model'] ?? ''),
                        $this->lookupMap('xt_plg_products_attributes', 'attributes_model', 'attributes_id')
                    )
                ),
            ];
        }

        return array_values(array_filter($writes, static fn (array $write): bool => $write['attribute_model'] !== ''));
    }

    private function buildAttributeDescriptions(array $attributes): array
    {
        $definition = $this->definition('xt_plg_products_attributes_description');
        $writes = [];

        foreach ($attributes as $attributeGroup) {
            foreach (($attributeGroup['translations'] ?? []) as $languageCode => $attribute) {
                $writes[] = [
                    'attribute_model' => (string) ($attribute['attribute_model'] ?? ''),
                    'language_code' => $languageCode,
                    'columns' => $this->resolveColumns(
                        (array) ($definition['columns'] ?? []),
                        ['attribute' => $attribute],
                        false,
                        ['attributes_id']
                    ),
                ];
            }
        }

        return array_values(array_filter($writes, static fn (array $write): bool => $write['attribute_model'] !== ''));
    }

    private function buildAttributeRelations(array $attributes): array
    {
        $definition = $this->definition('xt_plg_products_to_attributes');
        $writes = [];

        foreach ($attributes as $attributeGroup) {
            $attributeEntity = $attributeGroup['entity'] ?? null;
            $firstTranslation = is_array($attributeGroup['translations'] ?? null)
                ? reset($attributeGroup['translations'])
                : null;

            if (!is_array($attributeEntity) || !is_array($firstTranslation)) {
                continue;
            }

            $writes[] = [
                'attribute_model' => (string) ($attributeEntity['attribute_model'] ?? ''),
                'columns' => $this->resolveColumns(
                    (array) ($definition['columns'] ?? []),
                    [
                        'stage' => ['afs_artikel_id' => $firstTranslation['afs_artikel_id'] ?? null],
                        'attribute' => $attributeEntity,
                    ],
                    false,
                    ['products_id', 'attributes_id']
                ),
            ];
        }

        return array_values(array_filter($writes, static fn (array $write): bool => $write['attribute_model'] !== ''));
    }

    private function isOfflinePayload(array $payload): bool
    {
        $data = $payload['data'] ?? null;

        return is_array($data)
            && !isset($data['product'])
            && (($data['online'] ?? null) === 0 || ($data['online'] ?? null) === '0');
    }

    private function taxClassId(mixed $taxRate): int
    {
        if ($taxRate === null || $taxRate === '') {
            return 0;
        }

        return max(0, (int) round((float) $taxRate));
    }

    private function translationForLanguage(array $translations, string $languageCode): array
    {
        if (isset($translations[$languageCode]) && is_array($translations[$languageCode])) {
            return $translations[$languageCode];
        }

        $fallbacks = $this->fallbackChain($languageCode);

        foreach ($fallbacks as $fallbackCode) {
            if (isset($translations[$fallbackCode]) && is_array($translations[$fallbackCode])) {
                return $translations[$fallbackCode];
            }
        }

        return [];
    }

    private function fallbackChain(string $languageCode): array
    {
        $languages = $this->languageConfig['languages'] ?? [];

        foreach ($languages as $language) {
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

    private function productSeoUrl(string $languageCode, array $sources): string
    {
        $stage = is_array($sources['stage'] ?? null) ? $sources['stage'] : [];
        $translation = is_array($sources['translation'] ?? null) ? $sources['translation'] : [];

        $baseText = trim((string) (
            $translation['name']
            ?? $translation['meta_title']
            ?? $stage['name_default']
            ?? $stage['name']
            ?? $stage['sku']
            ?? 'product'
        ));

        if ($baseText === '') {
            $baseText = 'product';
        }

        $identity = trim((string) ($stage['afs_artikel_id'] ?? $stage['sku'] ?? ''));
        if ($identity === '') {
            $identity = 'product';
        }

        $prefix = $this->languagePrefix($languageCode);
        $slug = $this->slugify($baseText);
        $suffix = $this->slugify($identity);

        return $prefix . '/' . $slug . '-' . $suffix;
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
