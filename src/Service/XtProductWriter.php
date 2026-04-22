<?php

declare(strict_types=1);

final class XtProductWriter extends AbstractXtWriter implements XtBatchQueueWriter
{
    private array $languageConfig;
    private StageCategoryMap $categoryMap;
    private array $productsBySku = [];
    private int $productBatchRequestSize;
    private array $seoUrlCache = [];

    public function __construct(array $sourcesConfig, array $xtWriteConfig)
    {
        parent::__construct($sourcesConfig, $xtWriteConfig);

        $languageConfig = require dirname(__DIR__, 2) . '/config/languages.php';
        $this->languageConfig = is_array($languageConfig) ? $languageConfig : [];
        $this->categoryMap = new StageCategoryMap($sourcesConfig);
        $this->productsBySku = $this->loadProductsBySku($sourcesConfig);
        $xtConnection = $sourcesConfig['sources']['xt']['connection'] ?? [];
        $this->productBatchRequestSize = max(1, (int) ($xtConnection['product_batch_request_size'] ?? 1000));
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

        $prepared = $this->prepareProductSyncPayload($entry, $payload);
        $result = $this->client->syncProduct($prepared['single_sync_payload']);
        $productId = (int) ($result['product_id'] ?? 0);
        if ($productId <= 0) {
            throw new RuntimeException("Produkt-Sync lieferte keine gueltige product_id fuer '{$prepared['product_identity']}'.");
        }

        $this->syncProductAttributes(
            $productId,
            $prepared['attribute_entities'],
            $prepared['attribute_descriptions'],
            $prepared['attribute_relations']
        );
    }

    public function supportsBatch(string $entityType): bool
    {
        return $this->supports($entityType);
    }

    public function writeBatch(string $entityType, array $entries): array
    {
        if (!$this->supportsBatch($entityType)) {
            return ['done' => [], 'failed' => []];
        }

        $this->requireConfiguredClient('XT-API URL oder API-Key fehlt fuer Produkt-Export.');

        $done = [];
        $failed = [];
        $batchItems = [];

        foreach ($entries as $entry) {
            $queueId = (int) ($entry['id'] ?? 0);

            try {
                $payload = $this->decodeQueuePayload($entry);

                if ($this->isOfflinePayload($payload)) {
                    $this->write($entityType, $entry, $payload);
                    $done[$queueId] = true;

                    continue;
                }

                $prepared = $this->prepareProductSyncPayload($entry, $payload);
                $batchItems[] = [
                    'queue_id' => $queueId,
                    'entity_id' => trim((string) ($entry['entity_id'] ?? '')),
                    'single_sync_payload' => $prepared['single_sync_payload'],
                    'batch_sync_payload' => $prepared['batch_sync_payload'],
                ];
            } catch (Throwable $exception) {
                $failed[$queueId] = $exception;
            }
        }

        if ($batchItems === []) {
            return ['done' => $done, 'failed' => $failed];
        }

        foreach (array_chunk($batchItems, $this->productBatchRequestSize) as $chunk) {
            $response = $this->client->syncProductsBatch($chunk);
            $results = is_array($response['results'] ?? null) ? $response['results'] : [];

            foreach ($results as $result) {
                if (!is_array($result)) {
                    continue;
                }

                $queueId = (int) ($result['queue_id'] ?? 0);
                if ($queueId <= 0) {
                    continue;
                }

                if (($result['ok'] ?? false) === true) {
                    $done[$queueId] = true;
                    unset($failed[$queueId]);

                    continue;
                }

                $failed[$queueId] = new RuntimeException((string) ($result['error'] ?? 'Produkt-Batch-Export fehlgeschlagen.'));
            }

            foreach ($chunk as $item) {
                $queueId = (int) ($item['queue_id'] ?? 0);
                if ($queueId <= 0 || isset($done[$queueId]) || isset($failed[$queueId])) {
                    continue;
                }

                $failed[$queueId] = new RuntimeException('Produkt-Batch-Export lieferte kein Ergebnis fuer Queue-Eintrag.');
            }
        }

        return ['done' => $done, 'failed' => $failed];
    }

    private function normalizeProductColumns(array $columns): array
    {
        if (($columns['products_shippingtime_nostock'] ?? null) === '') {
            $columns['products_shippingtime_nostock'] = null;
        }

        $productUnit = $columns['products_unit'] ?? null;
        if (!is_int($productUnit) && !is_float($productUnit) && !is_numeric((string) $productUnit)) {
            $columns['products_unit'] = 0;
        }

        if (($columns['google_product_cat'] ?? null) === '') {
            $columns['google_product_cat'] = null;
        }

        return $columns;
    }

    private function syncProductAttributes(
        int $productId,
        array $attributeEntities,
        array $attributeDescriptions,
        array $attributeRelations
    ): void
    {
        $attributeIdMap = [];

        foreach ($attributeEntities as $attributeEntity) {
            if (!is_array($attributeEntity)) {
                continue;
            }

            $attributeModel = trim((string) ($attributeEntity['attribute_model'] ?? ''));
            if ($attributeModel === '') {
                continue;
            }

            $parentAttributeModel = trim((string) ($attributeEntity['parent_attribute_model'] ?? ''));
            $columns = $attributeEntity['columns'] ?? null;
            if (!is_array($columns)) {
                continue;
            }

            if ($parentAttributeModel !== '') {
                if (!isset($attributeIdMap[$parentAttributeModel])) {
                    throw new RuntimeException("Attribut-Sync kennt kein Parent-Attribut fuer '{$parentAttributeModel}'.");
                }

                $columns['attributes_parent'] = $attributeIdMap[$parentAttributeModel];
            }

            $result = $this->client->upsertRow(
                'xt_plg_products_attributes',
                ['attributes_model' => $attributeModel],
                $columns,
                'attributes_id'
            );
            $attributeId = (int) ($result['primary_key_value'] ?? 0);
            if ($attributeId <= 0) {
                throw new RuntimeException("Attribut-Sync konnte keine XT-Attribut-ID fuer '{$attributeModel}' ermitteln.");
            }

            $attributeIdMap[$attributeModel] = $attributeId;
            $this->storeLookupValue('xt_plg_products_attributes', 'attributes_model', 'attributes_id', $attributeModel, $attributeId);
        }

        foreach ($attributeDescriptions as $attributeDescription) {
            if (!is_array($attributeDescription)) {
                continue;
            }

            $attributeModel = trim((string) ($attributeDescription['attribute_model'] ?? ''));
            $languageCode = trim((string) ($attributeDescription['language_code'] ?? ''));
            $columns = $attributeDescription['columns'] ?? null;

            if ($attributeModel === '' || $languageCode === '' || !is_array($columns)) {
                continue;
            }

            if (!isset($attributeIdMap[$attributeModel])) {
                throw new RuntimeException("Attribut-Sync kennt kein XT-Attribut fuer '{$attributeModel}'.");
            }

            $this->client->upsertRow(
                'xt_plg_products_attributes_description',
                [
                    'attributes_id' => $attributeIdMap[$attributeModel],
                    'language_code' => $languageCode,
                ],
                $columns,
                ['attributes_id', 'language_code']
            );
        }

        $this->client->deleteRows('xt_plg_products_to_attributes', [
            'products_id' => $productId,
        ]);

        foreach ($attributeRelations as $attributeRelation) {
            if (!is_array($attributeRelation)) {
                continue;
            }

            $attributeModel = trim((string) ($attributeRelation['attribute_model'] ?? ''));
            $parentAttributeModel = trim((string) ($attributeRelation['parent_attribute_model'] ?? ''));
            $columns = $attributeRelation['columns'] ?? null;

            if ($attributeModel === '' || !is_array($columns)) {
                continue;
            }

            if (!isset($attributeIdMap[$attributeModel])) {
                throw new RuntimeException("Attribut-Link kennt kein XT-Attribut fuer '{$attributeModel}'.");
            }

            if ($parentAttributeModel !== '') {
                if (!isset($attributeIdMap[$parentAttributeModel])) {
                    throw new RuntimeException("Attribut-Link kennt kein Parent-Attribut fuer '{$parentAttributeModel}'.");
                }

                $columns['attributes_parent_id'] = $attributeIdMap[$parentAttributeModel];
            }

            $this->client->upsertRow(
                'xt_plg_products_to_attributes',
                [
                    'products_id' => $productId,
                    'attributes_id' => $attributeIdMap[$attributeModel],
                ],
                $columns,
                ['products_id', 'attributes_id']
            );
        }
    }

    private function prepareProductSyncPayload(array $entry, array $payload): array
    {
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
        $productColumns = $this->resolveColumns(
            (array) ($productDefinition['columns'] ?? []),
            ['stage' => $product],
            $isInsert
        );
        $productColumns = $this->normalizeProductColumns($productColumns);
        $attributeEntities = $this->buildAttributeEntities($attributes);
        $attributeDescriptions = $this->buildAttributeDescriptions($attributes);
        $attributeRelations = $this->buildAttributeRelations($attributes);

        $singleSyncPayload = [
            'product' => [
                'identity' => [
                    (string) ($productDefinition['identity']['target_field'] ?? 'external_id') => $productIdentity,
                ],
                'columns' => $productColumns,
            ],
            'translations' => $this->buildTranslationWrites($product, $translations),
            'replace_categories' => true,
            'category_relations' => $this->buildCategoryRelations($product),
            'replace_attributes' => false,
            'attribute_entities' => [],
            'attribute_descriptions' => [],
            'attribute_relations' => [],
            'seo_urls' => $this->buildSeoWrites($product, $translations, $isInsert),
        ];

        $batchSyncPayload = $singleSyncPayload;
        $batchSyncPayload['replace_attributes'] = true;
        $batchSyncPayload['attribute_entities'] = $attributeEntities;
        $batchSyncPayload['attribute_descriptions'] = $attributeDescriptions;
        $batchSyncPayload['attribute_relations'] = $attributeRelations;

        return [
            'product_identity' => $productIdentity,
            'single_sync_payload' => $singleSyncPayload,
            'batch_sync_payload' => $batchSyncPayload,
            'attribute_entities' => $attributeEntities,
            'attribute_descriptions' => $attributeDescriptions,
            'attribute_relations' => $attributeRelations,
        ];
    }

    private function decodeQueuePayload(array $entry): array
    {
        $payload = json_decode((string) ($entry['payload'] ?? ''), true);

        if (!is_array($payload)) {
            throw new PermanentExportQueueException('Produkt-Queue-Payload ist kein gueltiges JSON.');
        }

        return $payload;
    }

    protected function resolveCalculatedExpression(string $expression, array $sources, bool $isInsert): mixed
    {
        $stage = is_array($sources['stage'] ?? null) ? $sources['stage'] : [];
        $translation = is_array($sources['translation'] ?? null) ? $sources['translation'] : [];

        if (preg_match('/^calc:product_seo_url_(de|en|fr|nl)$/', $expression, $matches) === 1) {
            return $this->productSeoUrl($matches[1], $sources);
        }

        if (preg_match('/^calc:product_seo_url_md5_(de|en|fr|nl)$/', $expression, $matches) === 1) {
            return md5((string) $this->productSeoUrl($matches[1], $sources));
        }

        return match ($expression) {
            'calc:product_price' => $stage['price'] ?? null,
            'calc:product_status' => isset($stage['online_flag']) ? (int) $stage['online_flag'] : 0,
            'calc:tax_class_id' => $this->taxClassId($stage['tax_rate'] ?? null),
            'calc:product_unit' => $stage['unit'] ?? null,
            'calc:product_translation_description' => $this->combineTranslationDescription(
                $translation['description'] ?? null,
                $translation['technical_data_html'] ?? null
            ),
            default => parent::resolveCalculatedExpression($expression, $sources, $isInsert),
        };
    }

    private function combineTranslationDescription(mixed $description, mixed $technicalDataHtml): ?string
    {
        $parts = [];

        foreach ([$description, $technicalDataHtml] as $value) {
            $normalized = trim((string) ($value ?? ''));
            if ($normalized === '') {
                continue;
            }

            $parts[] = $normalized;
        }

        if ($parts === []) {
            return null;
        }

        return implode("\n\n", $parts);
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

        $attributeIdMap = $this->lookupMap('xt_plg_products_attributes', 'attributes_model', 'attributes_id');
        $grouped = [];

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
                || $attributeName === ''
                || $attributeValue === ''
            ) {
                continue;
            }

            $sortOrder = (int) $sortOrder;
            $attribute['sort_order'] = $sortOrder;
            $attribute['afs_artikel_id'] = $product['afs_artikel_id'] ?? null;
            $grouped[$sortOrder]['translations'][$languageCode] = $attribute;
        }

        $normalized = [];

        foreach ($grouped as $sortOrder => $attributeGroup) {
            $translations = is_array($attributeGroup['translations'] ?? null)
                ? $attributeGroup['translations']
                : [];
            $baseTranslation = $translations['de'] ?? reset($translations);

            if (!is_array($baseTranslation)) {
                continue;
            }

            $parentLabel = trim((string) ($baseTranslation['attribute_name'] ?? ''));
            $childLabel = trim((string) ($baseTranslation['attribute_value'] ?? ''));

            if ($parentLabel === '' || $childLabel === '') {
                continue;
            }

            $parentModel = $this->attributeParentModel($parentLabel);
            $childModel = $this->attributeValueModel($parentLabel, $childLabel);
            $parentId = isset($attributeIdMap[$parentModel]) ? (int) $attributeIdMap[$parentModel] : 0;

            $normalized[$sortOrder] = [
                'parent_entity' => [
                    'attribute_model' => $parentModel,
                    'attributes_parent' => 0,
                    'sort_order' => $sortOrder,
                ],
                'child_entity' => [
                    'attribute_model' => $childModel,
                    'attributes_parent' => $parentId,
                    'sort_order' => 0,
                    'parent_attribute_model' => $parentModel,
                ],
                'parent_translations' => [],
                'child_translations' => [],
                'relation' => [
                    'attribute_model' => $childModel,
                    'attributes_parent_id' => $parentId,
                    'parent_attribute_model' => $parentModel,
                    'afs_artikel_id' => $baseTranslation['afs_artikel_id'] ?? null,
                ],
            ];

            foreach ($translations as $translationLanguage => $translation) {
                if (!is_array($translation)) {
                    continue;
                }

                $parentDisplayName = trim((string) ($translation['attribute_name'] ?? ''));
                $childDisplayName = trim((string) ($translation['attribute_value'] ?? ''));

                if ($parentDisplayName === '' || $childDisplayName === '') {
                    continue;
                }

                $normalized[$sortOrder]['parent_translations'][$translationLanguage] = [
                    'attribute_model' => $parentModel,
                    'language_code' => $translationLanguage,
                    'display_name' => $parentDisplayName,
                ];
                $normalized[$sortOrder]['child_translations'][$translationLanguage] = [
                    'attribute_model' => $childModel,
                    'language_code' => $translationLanguage,
                    'display_name' => $childDisplayName,
                ];
            }
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
        $categoryId = $this->resolvedCategoryId($product);

        if ($categoryId === null || $categoryId === '') {
            return [];
        }

        return [[
            'columns' => $this->resolveColumns(
                (array) ($definition['columns'] ?? []),
                ['stage' => array_merge($product, ['category_afs_id' => $categoryId])],
                false,
                ['products_id']
            ),
        ]];
    }

    private function resolvedCategoryId(array $product): ?string
    {
        $categoryId = trim((string) ($product['category_afs_id'] ?? ''));
        if ($categoryId !== '') {
            return $categoryId;
        }

        if (!$this->isTruthy($product['is_slave'] ?? null)) {
            return null;
        }

        $masterSku = trim((string) ($product['master_sku'] ?? ''));
        if ($masterSku === '') {
            return null;
        }

        return $this->resolvedCategoryIdForSku($masterSku, [$masterSku => true]);
    }

    private function resolvedCategoryIdForSku(string $sku, array $visited): ?string
    {
        $product = $this->productsBySku[$sku] ?? null;
        if (!is_array($product)) {
            return null;
        }

        $categoryId = trim((string) ($product['category_afs_id'] ?? ''));
        if ($categoryId !== '') {
            return $categoryId;
        }

        if (!$this->isTruthy($product['is_slave'] ?? null)) {
            return null;
        }

        $masterSku = trim((string) ($product['master_sku'] ?? ''));
        if ($masterSku === '' || isset($visited[$masterSku])) {
            return null;
        }

        $visited[$masterSku] = true;

        return $this->resolvedCategoryIdForSku($masterSku, $visited);
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
                ['link_id', 'url_text', 'url_md5']
            );

            $writes[] = [
                'language_code' => $languageCode,
                'auto_generate' => true,
                'auto_generate_class' => 'product',
                'columns' => $columns,
            ];
        }

        return $writes;
    }

    private function buildAttributeEntities(array $attributes): array
    {
        $definition = $this->definition('xt_plg_products_attributes');
        $writes = [];
        $seen = [];

        foreach ($attributes as $attributeGroup) {
            foreach (['parent_entity', 'child_entity'] as $entityKey) {
                $attributeEntity = $attributeGroup[$entityKey] ?? null;

                if (!is_array($attributeEntity)) {
                    continue;
                }

                $attributeModel = (string) ($attributeEntity['attribute_model'] ?? '');
                if ($attributeModel === '' || isset($seen[$attributeModel])) {
                    continue;
                }

                $seen[$attributeModel] = true;
                $writes[] = [
                    'attribute_model' => $attributeModel,
                    'parent_attribute_model' => $attributeEntity['parent_attribute_model'] ?? null,
                    'columns' => $this->resolveColumns(
                        (array) ($definition['columns'] ?? []),
                        ['attribute' => $attributeEntity],
                        !array_key_exists(
                            $attributeModel,
                            $this->lookupMap('xt_plg_products_attributes', 'attributes_model', 'attributes_id')
                        )
                    ),
                ];
            }
        }

        return array_values(array_filter($writes, static fn (array $write): bool => $write['attribute_model'] !== ''));
    }

    private function buildAttributeDescriptions(array $attributes): array
    {
        $definition = $this->definition('xt_plg_products_attributes_description');
        $writes = [];
        $seen = [];

        foreach ($attributes as $attributeGroup) {
            foreach (['parent_translations', 'child_translations'] as $translationKey) {
                foreach (($attributeGroup[$translationKey] ?? []) as $languageCode => $attribute) {
                    $attributeModel = (string) ($attribute['attribute_model'] ?? '');
                    $seenKey = $attributeModel . '|' . $languageCode;

                    if ($attributeModel === '' || isset($seen[$seenKey])) {
                        continue;
                    }

                    $seen[$seenKey] = true;
                    $writes[] = [
                        'attribute_model' => $attributeModel,
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
        }

        return array_values(array_filter($writes, static fn (array $write): bool => $write['attribute_model'] !== ''));
    }

    private function buildAttributeRelations(array $attributes): array
    {
        $definition = $this->definition('xt_plg_products_to_attributes');
        $writes = [];

        foreach ($attributes as $attributeGroup) {
            $relation = $attributeGroup['relation'] ?? null;

            if (!is_array($relation)) {
                continue;
            }

            $writes[] = [
                'attribute_model' => (string) ($relation['attribute_model'] ?? ''),
                'parent_attribute_model' => $relation['parent_attribute_model'] ?? null,
                'columns' => $this->resolveColumns(
                    (array) ($definition['columns'] ?? []),
                    [
                        'stage' => ['afs_artikel_id' => $relation['afs_artikel_id'] ?? null],
                        'attribute' => $relation,
                    ],
                    false,
                    ['products_id', 'attributes_id']
                ),
            ];
        }

        return array_values(array_filter($writes, static fn (array $write): bool => $write['attribute_model'] !== ''));
    }

    private function attributeParentModel(string $label): string
    {
        return 'afs-attr-parent-' . $this->slugify($label) . '-' . substr(sha1($label), 0, 8);
    }

    private function attributeValueModel(string $parentLabel, string $value): string
    {
        return 'afs-attr-value-'
            . $this->slugify($parentLabel)
            . '-'
            . $this->slugify($value)
            . '-'
            . substr(sha1($parentLabel . '|' . $value), 0, 8);
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

    private function loadProductsBySku(array $sourcesConfig): array
    {
        $stageConfig = $sourcesConfig['sources']['stage'] ?? null;
        if (!is_array($stageConfig)) {
            return [];
        }

        $stageDb = ConnectionFactory::create($stageConfig);
        $stmt = $stageDb->query(
            'SELECT sku, category_afs_id, is_slave, master_sku
             FROM `stage_products`
             WHERE sku IS NOT NULL AND sku <> ""
             ORDER BY sku ASC'
        );

        $products = [];
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $sku = trim((string) ($row['sku'] ?? ''));
            if ($sku === '') {
                continue;
            }

            $products[$sku] = $row;
        }

        return $products;
    }

    private function isTruthy(mixed $value): bool
    {
        if (is_bool($value)) {
            return $value;
        }

        if (is_int($value) || is_float($value)) {
            return (int) $value !== 0;
        }

        $normalized = strtolower(trim((string) $value));

        return $normalized !== '' && $normalized !== '0' && $normalized !== 'false' && $normalized !== 'no';
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
        $productId = trim((string) ($stage['afs_artikel_id'] ?? ''));
        $cacheKey = $languageCode . '|' . $productId;

        if ($productId !== '' && array_key_exists($cacheKey, $this->seoUrlCache)) {
            return $this->seoUrlCache[$cacheKey];
        }

        $translation = is_array($sources['translation'] ?? null) ? $sources['translation'] : [];
        $definition = $this->definition('xt_seo_url_products');
        $policy = is_array($definition['policy'] ?? null) ? $definition['policy'] : [];

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

        $prefix = $this->languagePrefix($languageCode);
        $segments = [$prefix];

        if (($policy['use_category_path'] ?? false) === true) {
            $resolvedCategoryId = $this->resolvedCategoryId($stage);
            $pathSegments = $this->categoryMap->pathSegmentsLeafFirst(
                $resolvedCategoryId,
                $languageCode,
                $this->fallbackChain($languageCode)
            );

            foreach ($pathSegments as $pathSegment) {
                $segments[] = $this->slugify($pathSegment);
            }
        }

        $segments[] = $this->slugify($baseText);

        $url = implode('/', array_values(array_filter($segments, static fn (string $segment): bool => $segment !== '')));
        $fallbackSlug = $this->slugify((string) ($stage['sku'] ?? $stage['afs_artikel_id'] ?? 'product'));

        $resolved = $this->reserveUniqueSeoUrl($url, $languageCode, $fallbackSlug);

        if ($productId !== '') {
            $this->seoUrlCache[$cacheKey] = $resolved;
        }

        return $resolved;
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
