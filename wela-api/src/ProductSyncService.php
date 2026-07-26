<?php

declare(strict_types=1);

namespace WelaApi;

use PDO;
use RuntimeException;
use Throwable;

final class ProductSyncService
{
    public function __construct(
        private PDO $pdo,
        private SchemaInspector $schemaInspector
    ) {
    }

    public function syncProductRequest(array $request, array $context = []): array
    {
        $product = \wela_required_array($request['product'] ?? null, 'Produkt-Sync benoetigt einen Produktblock.');
        $productIdentity = \wela_allowed_field_map(
            \wela_required_array($product['identity'] ?? null, 'Produkt-Sync benoetigt eine Produkt-Identitaet.'),
            ['external_id']
        );
        $existingProductRow = is_array($context['existing_product_row'] ?? null) ? $context['existing_product_row'] : null;
        $existingSeoUrls = is_array($context['existing_seo_urls'] ?? null) ? $context['existing_seo_urls'] : [];
        $existingAttributeRows = is_array($context['existing_attribute_rows'] ?? null) ? $context['existing_attribute_rows'] : [];
        $existingCategoryRelations = is_array($context['existing_category_relations'] ?? null) ? $context['existing_category_relations'] : [];
        $existingAttributeRelations = is_array($context['existing_attribute_relations'] ?? null) ? $context['existing_attribute_relations'] : [];
        $externalId = trim((string) ($productIdentity['external_id'] ?? ''));

        if (!is_array($existingProductRow) && $externalId !== '') {
            $prefetchedProducts = $this->prefetchXtProducts([$externalId]);
            $existingProductRow = is_array($prefetchedProducts[$externalId] ?? null) ? $prefetchedProducts[$externalId] : null;
        }

        $productColumns = \wela_allowed_field_map(
            \wela_required_array($product['columns'] ?? null, 'Produkt-Sync benoetigt Produktspalten.'),
            \wela_allowed_tables()['xt_products']['write_fields']
        );
        $productColumns = \wela_prepare_product_columns($this->pdo, $productIdentity, $productColumns, $existingProductRow);
        $translations = \wela_optional_array_list($request['translations'] ?? null, 'Produkt-Sync-Uebersetzungen muessen eine Liste sein.');
        $categoryRelations = \wela_optional_array_list($request['category_relations'] ?? null, 'Produkt-Sync-Kategorien muessen eine Liste sein.');
        $attributeEntities = \wela_optional_array_list($request['attribute_entities'] ?? null, 'Produkt-Sync-Attribute muessen eine Liste sein.');
        $attributeDescriptions = \wela_optional_array_list($request['attribute_descriptions'] ?? null, 'Produkt-Sync-Attribut-Uebersetzungen muessen eine Liste sein.');
        $attributeRelations = \wela_optional_array_list($request['attribute_relations'] ?? null, 'Produkt-Sync-Attribut-Links muessen eine Liste sein.');
        $seoUrls = \wela_optional_array_list($request['seo_urls'] ?? null, 'Produkt-Sync-SEO-URLs muessen eine Liste sein.');
        $replaceCategories = (bool) ($request['replace_categories'] ?? false);
        $replaceAttributes = (bool) ($request['replace_attributes'] ?? false);
        $masterCategoryIdFromPayload = 0;

        if ($existingAttributeRows === [] && $attributeEntities !== []) {
            $existingAttributeRows = $this->prefetchXtAttributeRows($this->collectAttributeModelsFromPayload($attributeEntities));
        }

        $this->pdo->beginTransaction();

        try {
            $productResult = $this->upsertXtProductRow($productIdentity, $productColumns, $existingProductRow);
            $productId = (int) ($productResult['primary_key_value'] ?? 0);

            if ($productId <= 0) {
                throw new RuntimeException('Produkt-Sync konnte keine gueltige XT-Produkt-ID ermitteln.');
            }

            if ($existingCategoryRelations === [] && $productId > 0) {
                $existingCategoryRelations = $this->prefetchXtProductCategoryRelations([$productId])[$productId] ?? [];
            }

            if ($existingAttributeRelations === [] && $productId > 0) {
                $existingAttributeRelations = $this->prefetchXtProductAttributeRelations([$productId])[$productId] ?? [];
            }

            $translationRows = [];
            foreach ($translations as $translation) {
                $languageCode = \wela_allowed_language($translation['language_code'] ?? null);
                $translationColumns = \wela_allowed_field_map(
                    \wela_required_array($translation['columns'] ?? null, 'Produkt-Sync-Uebersetzung benoetigt Spalten.'),
                    \wela_allowed_tables()['xt_products_description']['write_fields']
                );
                unset($translationColumns['products_id']);

                $translationRows[] = array_replace([
                    'products_id' => $productId,
                    'language_code' => $languageCode,
                ], $translationColumns);
            }
            \wela_batch_upsert_rows($this->pdo, 'xt_products_description', $translationRows, ['products_id', 'language_code']);

            $categoryRelationRows = [];
            foreach ($categoryRelations as $relation) {
                $relationColumns = \wela_allowed_field_map(
                    \wela_required_array($relation['columns'] ?? null, 'Produkt-Sync-Kategorie benoetigt Spalten.'),
                    \wela_allowed_tables()['xt_products_to_categories']['write_fields']
                );
                $categoryId = isset($relationColumns['categories_id']) ? (int) $relationColumns['categories_id'] : 0;

                if ($categoryId <= 0) {
                    throw new RuntimeException('Produkt-Sync-Kategorie enthaelt keine gueltige categories_id.');
                }

                unset($relationColumns['products_id'], $relationColumns['categories_id']);

                if ($masterCategoryIdFromPayload <= 0 && (int) ($relationColumns['master_link'] ?? 0) === 1) {
                    $masterCategoryIdFromPayload = $categoryId;
                }

                $categoryRelationRows[] = array_replace([
                    'products_id' => $productId,
                    'categories_id' => $categoryId,
                ], $relationColumns);
            }

            if ($masterCategoryIdFromPayload <= 0 && $categoryRelationRows !== []) {
                $masterCategoryIdFromPayload = (int) ($categoryRelationRows[0]['categories_id'] ?? 0);
            }

            if ($replaceCategories) {
                $this->deleteMissingRelationRows(
                    'xt_products_to_categories',
                    ['products_id' => $productId],
                    'categories_id',
                    $categoryRelationRows,
                    $existingCategoryRelations
                );
            }
            \wela_batch_upsert_rows($this->pdo, 'xt_products_to_categories', $categoryRelationRows, ['products_id', 'categories_id']);

            $attributeIdMap = $this->syncAttributeEntities($attributeEntities, $existingAttributeRows);

            $attributeDescriptionRows = [];
            foreach ($attributeDescriptions as $attributeDescription) {
                $attributeModel = \wela_required_non_empty_string(
                    $attributeDescription['attribute_model'] ?? null,
                    'Produkt-Sync-Attribut-Uebersetzung benoetigt attribute_model.'
                );
                $parentAttributeModel = trim((string) ($attributeDescription['parent_attribute_model'] ?? ''));
                $languageCode = \wela_allowed_language($attributeDescription['language_code'] ?? null);
                $parentAttributeId = $parentAttributeModel === ''
                    ? 0
                    : (int) ($attributeIdMap[$this->attributeLookupKey($parentAttributeModel, 0)] ?? 0);
                $attributeMapKey = $this->attributeLookupKey($attributeModel, $parentAttributeId);

                if (!isset($attributeIdMap[$attributeMapKey])) {
                    throw new RuntimeException("Produkt-Sync kennt kein XT-Attribut fuer '{$attributeModel}'.");
                }

                $descriptionColumns = \wela_allowed_field_map(
                    \wela_required_array($attributeDescription['columns'] ?? null, 'Produkt-Sync-Attribut-Uebersetzung benoetigt Spalten.'),
                    \wela_allowed_tables()['xt_plg_products_attributes_description']['write_fields']
                );
                unset($descriptionColumns['attributes_id']);

                $attributeDescriptionRows[] = array_replace([
                    'attributes_id' => $attributeIdMap[$attributeMapKey],
                    'language_code' => $languageCode,
                ], $descriptionColumns);
            }
            \wela_batch_upsert_rows($this->pdo, 'xt_plg_products_attributes_description', $attributeDescriptionRows, ['attributes_id', 'language_code']);

            $attributeRelationRows = [];
            foreach ($attributeRelations as $attributeRelation) {
                $attributeModel = \wela_required_non_empty_string(
                    $attributeRelation['attribute_model'] ?? null,
                    'Produkt-Sync-Attribut-Link benoetigt attribute_model.'
                );
                $parentAttributeModel = trim((string) ($attributeRelation['parent_attribute_model'] ?? ''));
                $parentAttributeId = $parentAttributeModel === ''
                    ? 0
                    : (int) ($attributeIdMap[$this->attributeLookupKey($parentAttributeModel, 0)] ?? 0);
                $attributeMapKey = $this->attributeLookupKey($attributeModel, $parentAttributeId);

                if (!isset($attributeIdMap[$attributeMapKey])) {
                    throw new RuntimeException("Produkt-Sync kennt kein XT-Attribut fuer '{$attributeModel}'.");
                }

                $relationColumns = \wela_allowed_field_map(
                    \wela_required_array($attributeRelation['columns'] ?? null, 'Produkt-Sync-Attribut-Link benoetigt Spalten.'),
                    \wela_allowed_tables()['xt_plg_products_to_attributes']['write_fields']
                );

                if ($parentAttributeModel !== '') {
                    $parentMapKey = $this->attributeLookupKey($parentAttributeModel, 0);
                    if (!isset($attributeIdMap[$parentMapKey])) {
                        throw new RuntimeException("Produkt-Sync kennt kein Parent-Attribut fuer '{$parentAttributeModel}'.");
                    }

                    $relationColumns['attributes_parent_id'] = $attributeIdMap[$parentMapKey];
                }

                unset($relationColumns['products_id'], $relationColumns['attributes_id']);

                $attributeRelationRows[] = array_replace([
                    'products_id' => $productId,
                    'attributes_id' => $attributeIdMap[$attributeMapKey],
                ], $relationColumns);
            }

            if ($replaceAttributes) {
                $this->deleteMissingRelationRows(
                    'xt_plg_products_to_attributes',
                    ['products_id' => $productId],
                    'attributes_id',
                    $attributeRelationRows,
                    $existingAttributeRelations
                );
            }
            \wela_batch_upsert_rows($this->pdo, 'xt_plg_products_to_attributes', $attributeRelationRows, ['products_id', 'attributes_id']);

            $seoWrites = 0;
            $seoRows = [];

            foreach ($seoUrls as $seoUrl) {
                $languageCode = \wela_allowed_language($seoUrl['language_code'] ?? null);
                $seoColumns = \wela_allowed_field_map(
                    \wela_required_array($seoUrl['columns'] ?? null, 'Produkt-Sync-SEO benoetigt Spalten.'),
                    \wela_allowed_tables()['xt_seo_url']['write_fields']
                );

                $linkType = isset($seoColumns['link_type']) ? (int) $seoColumns['link_type'] : 1;
                $storeId = isset($seoColumns['store_id']) ? (int) $seoColumns['store_id'] : 1;
                $seoIdentity = [
                    'link_type' => $linkType,
                    'link_id' => $productId,
                    'language_code' => $languageCode,
                    'store_id' => $storeId,
                ];

                unset($seoColumns['link_id'], $seoColumns['link_type'], $seoColumns['language_code'], $seoColumns['store_id']);

                if (($seoUrl['auto_generate'] ?? false) === true) {
                    $existingSeoRow = $existingSeoUrls[\wela_seo_identity_key($seoIdentity)] ?? null;
                    $seoColumns = \wela_auto_generate_seo_columns(
                        $this->pdo,
                        is_string($seoUrl['auto_generate_class'] ?? null) ? (string) $seoUrl['auto_generate_class'] : 'product',
                        $linkType,
                        $productId,
                        $languageCode,
                        $storeId,
                        $seoColumns,
                        is_string($seoUrl['auto_generate_text'] ?? null) ? (string) $seoUrl['auto_generate_text'] : null,
                        [
                            'product_master_category_id' => $masterCategoryIdFromPayload,
                            'existing_seo_row' => is_array($existingSeoRow) ? $existingSeoRow : null,
                        ]
                    );
                    $seoColumns = \wela_apply_auto_generated_seo_update(
                        $this->pdo,
                        $seoIdentity,
                        $seoColumns,
                        $existingSeoRow
                    );
                } else {
                    $seoColumns = \wela_preserve_existing_seo_url_columns(
                        $this->pdo,
                        $seoIdentity,
                        $seoColumns,
                        $existingSeoUrls[\wela_seo_identity_key($seoIdentity)] ?? null
                    );
                }

                $seoRow = array_replace($seoIdentity, $seoColumns);
                $existingSeoRow = $existingSeoUrls[\wela_seo_identity_key($seoIdentity)] ?? null;

                if ($this->replaceStaleSeoUrlRows($seoIdentity, $seoRow)) {
                    $seoRows[] = $seoRow;
                    $seoWrites++;

                    continue;
                }

                if ($this->seoRowNeedsWrite($seoRow, is_array($existingSeoRow) ? $existingSeoRow : null)) {
                    $seoRows[] = $seoRow;
                }

                $seoWrites++;
            }
            \wela_batch_upsert_rows($this->pdo, 'xt_seo_url', $seoRows, ['link_type', 'link_id', 'language_code', 'store_id']);

            \wela_flush_seo_redirect_buffer($this->pdo);
            $this->pdo->commit();

            return [
                'product_id' => $productId,
                'product_action' => $productResult['action'] ?? null,
                'translations' => count($translations),
                'category_relations' => count($categoryRelations),
                'attribute_entities' => count($attributeEntities),
                'attribute_descriptions' => count($attributeDescriptions),
                'attribute_relations' => count($attributeRelations),
                'seo_urls' => $seoWrites,
            ];
        } catch (Throwable $exception) {
            \wela_clear_seo_redirect_buffer($this->pdo);
            if ($this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }

            throw $exception;
        }
    }

    private function replaceStaleSeoUrlRows(array $identity, array $seoRow): bool
    {
        $stmt = $this->pdo->prepare(
            'SELECT `url_text`
             FROM `xt_seo_url`
             WHERE `link_type` = :link_type
               AND `link_id` = :link_id
               AND `language_code` = :language_code
               AND `store_id` = :store_id'
        );
        $stmt->execute([
            ':link_type' => (int) ($identity['link_type'] ?? 0),
            ':link_id' => (int) ($identity['link_id'] ?? 0),
            ':language_code' => (string) ($identity['language_code'] ?? ''),
            ':store_id' => (int) ($identity['store_id'] ?? 0),
        ]);
        $existingRows = $stmt->fetchAll(PDO::FETCH_ASSOC);

        if ($existingRows === []) {
            return false;
        }

        $newUrl = trim((string) ($seoRow['url_text'] ?? ''), '/');

        // Ohne eine neue kanonische URL darf keine bestehende URL entfernt werden.
        if ($newUrl === '') {
            return false;
        }

        $hasExactlyOneCanonicalRow = count($existingRows) === 1
            && trim((string) ($existingRows[0]['url_text'] ?? ''), '/') === $newUrl;

        if ($hasExactlyOneCanonicalRow) {
            return false;
        }

        foreach ($existingRows as $existingRow) {
            $oldUrl = trim((string) ($existingRow['url_text'] ?? ''), '/');
            if ($oldUrl === '' || $oldUrl === $newUrl) {
                continue;
            }

            \wela_queue_seo_redirect(
                $this->pdo,
                $oldUrl,
                $newUrl,
                (string) ($identity['language_code'] ?? ''),
                (int) ($identity['link_type'] ?? 0),
                (int) ($identity['link_id'] ?? 0),
                (int) ($identity['store_id'] ?? 0)
            );
        }

        $deleteStmt = $this->pdo->prepare(
            'DELETE FROM `xt_seo_url`
             WHERE `link_type` = :link_type
               AND `link_id` = :link_id
               AND `language_code` = :language_code
               AND `store_id` = :store_id'
        );
        $deleteStmt->execute([
            ':link_type' => (int) ($identity['link_type'] ?? 0),
            ':link_id' => (int) ($identity['link_id'] ?? 0),
            ':language_code' => (string) ($identity['language_code'] ?? ''),
            ':store_id' => (int) ($identity['store_id'] ?? 0),
        ]);

        return true;
    }

    public function prepareProductBatchContext(array $items): array
    {
        $externalIds = [];
        $attributeModels = [];

        foreach ($items as $item) {
            if (!is_array($item)) {
                continue;
            }

            $payload = is_array($item['batch_sync_payload'] ?? null) ? $item['batch_sync_payload'] : null;
            $product = is_array($payload['product'] ?? null) ? $payload['product'] : null;
            $identity = is_array($product['identity'] ?? null) ? $product['identity'] : null;
            $externalId = trim((string) ($identity['external_id'] ?? ''));

            if ($externalId === '') {
                continue;
            }

            $externalIds[$externalId] = true;

            foreach ($this->collectAttributeModelsFromRequest($payload ?? []) as $attributeModel) {
                $attributeModels[$attributeModel] = true;
            }
        }

        $productsByExternalId = $this->prefetchXtProducts(array_keys($externalIds));
        $productIds = [];

        foreach ($productsByExternalId as $row) {
            $productId = (int) ($row['products_id'] ?? 0);
            if ($productId > 0) {
                $productIds[$productId] = true;
            }
        }

        return [
            'products_by_external_id' => $productsByExternalId,
            'product_seo_by_link_id' => $this->prefetchXtProductSeoUrls(array_keys($productIds)),
            'attributes_by_model' => $this->prefetchXtAttributeRows(array_keys($attributeModels)),
            'category_relations_by_product_id' => $this->prefetchXtProductCategoryRelations(array_keys($productIds)),
            'attribute_relations_by_product_id' => $this->prefetchXtProductAttributeRelations(array_keys($productIds)),
        ];
    }

    public static function productBatchItemContext(array $batchContext, array $request): array
    {
        $product = is_array($request['product'] ?? null) ? $request['product'] : null;
        $identity = is_array($product['identity'] ?? null) ? $product['identity'] : null;
        $externalId = trim((string) ($identity['external_id'] ?? ''));
        $existingProductRow = $externalId !== ''
            ? ($batchContext['products_by_external_id'][$externalId] ?? null)
            : null;
        $existingProductId = is_array($existingProductRow) ? (int) ($existingProductRow['products_id'] ?? 0) : 0;

        return [
            'existing_product_row' => $existingProductRow,
            'existing_seo_urls' => $existingProductId > 0
                ? ($batchContext['product_seo_by_link_id'][$existingProductId] ?? [])
                : [],
            'existing_attribute_rows' => is_array($batchContext['attributes_by_model'] ?? null)
                ? $batchContext['attributes_by_model']
                : [],
            'existing_category_relations' => $existingProductId > 0
                ? ($batchContext['category_relations_by_product_id'][$existingProductId] ?? [])
                : [],
            'existing_attribute_relations' => $existingProductId > 0
                ? ($batchContext['attribute_relations_by_product_id'][$existingProductId] ?? [])
                : [],
        ];
    }

    private function collectAttributeModelsFromRequest(array $request): array
    {
        return $this->collectAttributeModelsFromPayload(
            \wela_optional_array_list($request['attribute_entities'] ?? null, 'Produkt-Sync-Attribute muessen eine Liste sein.')
        );
    }

    private function collectAttributeModelsFromPayload(array $attributeEntities): array
    {
        $attributeModels = [];

        foreach ($attributeEntities as $attributeEntity) {
            if (!is_array($attributeEntity)) {
                continue;
            }

            $attributeModel = trim((string) ($attributeEntity['attribute_model'] ?? ''));
            if ($attributeModel !== '') {
                $attributeModels[$attributeModel] = true;
            }

            $parentAttributeModel = trim((string) ($attributeEntity['parent_attribute_model'] ?? ''));
            if ($parentAttributeModel !== '') {
                $attributeModels[$parentAttributeModel] = true;
            }
        }

        return array_keys($attributeModels);
    }

    private function prefetchXtProducts(array $externalIds): array
    {
        if ($externalIds === []) {
            return [];
        }

        $fields = array_values(array_unique(array_merge(
            ['products_id', 'external_id'],
            \wela_allowed_tables()['xt_products']['write_fields']
        )));
        $productsByExternalId = [];

        foreach (array_chunk($externalIds, 250) as $chunk) {
            $placeholders = [];
            $params = [];

            foreach (array_values($chunk) as $index => $externalId) {
                $placeholder = ':external_id_' . $index;
                $placeholders[] = $placeholder;
                $params[$placeholder] = $externalId;
            }

            $sql = sprintf(
                'SELECT %s FROM `xt_products` WHERE `external_id` IN (%s)',
                implode(', ', array_map(static fn (string $field): string => "`{$field}`", $fields)),
                implode(', ', $placeholders)
            );
            $stmt = $this->pdo->prepare($sql);
            $stmt->execute($params);

            foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
                $externalId = trim((string) ($row['external_id'] ?? ''));
                if ($externalId === '') {
                    continue;
                }

                $productsByExternalId[$externalId] = $row;
            }
        }

        return $productsByExternalId;
    }

    private function prefetchXtProductSeoUrls(array $productIds): array
    {
        if ($productIds === []) {
            return [];
        }

        $seoByProductId = [];

        foreach (array_chunk($productIds, 250) as $chunk) {
            $placeholders = [];
            $params = [
                ':link_type' => 1,
            ];

            foreach (array_values($chunk) as $index => $productId) {
                $placeholder = ':link_id_' . $index;
                $placeholders[] = $placeholder;
                $params[$placeholder] = $productId;
            }

            $stmt = $this->pdo->prepare(
                sprintf(
                    'SELECT `link_type`, `link_id`, `language_code`, `store_id`, `url_text`, `url_md5`, `meta_title`, `meta_description`, `meta_keywords`
                     FROM `xt_seo_url`
                     WHERE `link_type` = :link_type
                       AND `link_id` IN (%s)',
                    implode(', ', $placeholders)
                )
            );
            $stmt->execute($params);

            foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
                $linkId = (int) ($row['link_id'] ?? 0);
                if ($linkId <= 0) {
                    continue;
                }

                $seoByProductId[$linkId] ??= [];
                $seoByProductId[$linkId][\wela_seo_identity_key([
                    'link_type' => (int) ($row['link_type'] ?? 0),
                    'link_id' => $linkId,
                    'language_code' => (string) ($row['language_code'] ?? ''),
                    'store_id' => (int) ($row['store_id'] ?? 0),
                ])] = $row;
            }
        }

        return $seoByProductId;
    }

    private function prefetchXtProductCategoryRelations(array $productIds): array
    {
        return $this->prefetchRelationRows(
            'xt_products_to_categories',
            'products_id',
            'categories_id',
            $productIds,
            ['products_id', 'categories_id', 'master_link', 'store_id']
        );
    }

    private function prefetchXtProductAttributeRelations(array $productIds): array
    {
        return $this->prefetchRelationRows(
            'xt_plg_products_to_attributes',
            'products_id',
            'attributes_id',
            $productIds,
            ['products_id', 'attributes_id', 'attributes_parent_id']
        );
    }

    private function prefetchRelationRows(string $table, string $ownerField, string $relationField, array $ownerIds, array $fields): array
    {
        if ($ownerIds === []) {
            return [];
        }

        $rowsByOwnerId = [];

        foreach (array_chunk($ownerIds, 250) as $chunk) {
            $placeholders = [];
            $params = [];

            foreach (array_values($chunk) as $index => $ownerId) {
                $placeholder = ':owner_id_' . $index;
                $placeholders[] = $placeholder;
                $params[$placeholder] = $ownerId;
            }

            $stmt = $this->pdo->prepare(
                sprintf(
                    'SELECT %s FROM `%s` WHERE `%s` IN (%s)',
                    implode(', ', array_map(static fn (string $field): string => "`{$field}`", $fields)),
                    $table,
                    $ownerField,
                    implode(', ', $placeholders)
                )
            );
            $stmt->execute($params);

            foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
                $ownerId = (int) ($row[$ownerField] ?? 0);
                $relationId = (int) ($row[$relationField] ?? 0);
                if ($ownerId <= 0 || $relationId <= 0) {
                    continue;
                }

                $rowsByOwnerId[$ownerId] ??= [];
                $rowsByOwnerId[$ownerId][$relationId] = $row;
            }
        }

        return $rowsByOwnerId;
    }

    private function prefetchXtAttributeRows(array $attributeModels): array
    {
        if ($attributeModels === []) {
            return [];
        }

        $fields = array_values(array_unique(array_merge(
            ['attributes_id', 'attributes_model'],
            \wela_allowed_tables()['xt_plg_products_attributes']['write_fields']
        )));
        $attributesByModel = [];

        foreach (array_chunk($attributeModels, 250) as $chunk) {
            $placeholders = [];
            $params = [];

            foreach (array_values($chunk) as $index => $attributeModel) {
                $placeholder = ':attribute_model_' . $index;
                $placeholders[] = $placeholder;
                $params[$placeholder] = $attributeModel;
            }

            $sql = sprintf(
                'SELECT %s FROM `xt_plg_products_attributes` WHERE `attributes_model` IN (%s)',
                implode(', ', array_map(static fn (string $field): string => "`{$field}`", $fields)),
                implode(', ', $placeholders)
            );
            $stmt = $this->pdo->prepare($sql);
            $stmt->execute($params);

            foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
                $attributeModel = trim((string) ($row['attributes_model'] ?? ''));
                if ($attributeModel === '') {
                    continue;
                }

                $attributesByModel[$this->attributeLookupKey($attributeModel, (int) ($row['attributes_parent'] ?? 0))] = $row;
            }
        }

        return $attributesByModel;
    }

    private function attributeLookupKey(string $attributeModel, int $parentId): string
    {
        return trim($attributeModel) . '|' . max(0, $parentId);
    }

    private function syncAttributeEntities(array $attributeEntities, array &$existingAttributeRows): array
    {
        $attributeIdMap = [];
        $parentRows = [];
        $childRows = [];

        foreach ($attributeEntities as $attributeEntity) {
            $attributeModel = \wela_required_non_empty_string(
                $attributeEntity['attribute_model'] ?? null,
                'Produkt-Sync-Attribut benoetigt attribute_model.'
            );
            $parentAttributeModel = trim((string) ($attributeEntity['parent_attribute_model'] ?? ''));
            $attributeColumns = \wela_allowed_field_map(
                \wela_required_array($attributeEntity['columns'] ?? null, 'Produkt-Sync-Attribut benoetigt Spalten.'),
                \wela_allowed_tables()['xt_plg_products_attributes']['write_fields']
            );

            if ($parentAttributeModel === '') {
                $parentRows[] = [
                    'attribute_model' => $attributeModel,
                    'columns' => $attributeColumns,
                ];
                continue;
            }

            $childRows[] = [
                'attribute_model' => $attributeModel,
                'parent_attribute_model' => $parentAttributeModel,
                'columns' => $attributeColumns,
            ];
        }

        if ($parentRows !== []) {
            $this->writeAttributeEntityRows($parentRows, $existingAttributeRows);
        }

        foreach ($parentRows as $row) {
            $attributeModel = $row['attribute_model'];
            $existingRow = $existingAttributeRows[$this->attributeLookupKey($attributeModel, 0)] ?? null;
            $attributeId = is_array($existingRow) ? (int) ($existingRow['attributes_id'] ?? 0) : 0;
            if ($attributeId <= 0) {
                throw new RuntimeException("Produkt-Sync konnte keine XT-Attribut-ID fuer '{$attributeModel}' ermitteln.");
            }

            $attributeIdMap[$this->attributeLookupKey($attributeModel, 0)] = $attributeId;
        }

        foreach ($childRows as $index => $row) {
            $parentAttributeModel = $row['parent_attribute_model'];

            $parentMapKey = $this->attributeLookupKey($parentAttributeModel, 0);
            if (!isset($attributeIdMap[$parentMapKey])) {
                $existingParentRow = $existingAttributeRows[$parentMapKey] ?? null;
                $existingParentId = is_array($existingParentRow) ? (int) ($existingParentRow['attributes_id'] ?? 0) : 0;

                if ($existingParentId <= 0) {
                    throw new RuntimeException("Produkt-Sync kennt kein Parent-Attribut fuer '{$parentAttributeModel}'.");
                }

                $attributeIdMap[$parentMapKey] = $existingParentId;
            }

            $childRows[$index]['columns']['attributes_parent'] = $attributeIdMap[$parentMapKey];
        }

        if ($childRows !== []) {
            $this->writeAttributeEntityRows($childRows, $existingAttributeRows);
        }

        foreach ($childRows as $row) {
            $attributeModel = $row['attribute_model'];
            $parentAttributeId = (int) ($row['columns']['attributes_parent'] ?? 0);
            $existingRow = $existingAttributeRows[$this->attributeLookupKey($attributeModel, $parentAttributeId)] ?? null;
            $attributeId = is_array($existingRow) ? (int) ($existingRow['attributes_id'] ?? 0) : 0;
            if ($attributeId <= 0) {
                throw new RuntimeException("Produkt-Sync konnte keine XT-Attribut-ID fuer '{$attributeModel}' ermitteln.");
            }

            $attributeIdMap[$this->attributeLookupKey($attributeModel, $parentAttributeId)] = $attributeId;
        }

        return $attributeIdMap;
    }

    private function writeAttributeEntityRows(array $rows, array &$existingAttributeRows): void
    {
        if ($rows === []) {
            return;
        }

        foreach ($rows as $row) {
            $attributeModel = (string) ($row['attribute_model'] ?? '');
            $columns = is_array($row['columns'] ?? null) ? $row['columns'] : [];
            if ($attributeModel === '') {
                continue;
            }

            $parentId = (int) ($columns['attributes_parent'] ?? 0);
            $lookupKey = $this->attributeLookupKey($attributeModel, $parentId);
            $existingAttributeRow = $existingAttributeRows[$lookupKey] ?? null;

            if (!is_array($existingAttributeRow)) {
                $existingAttributeRow = $this->findXtAttributeRow($attributeModel, $parentId);
            }

            $attributeResult = $this->upsertXtAttributeRow(
                $attributeModel,
                $columns,
                $existingAttributeRow
            );
            $attributeId = (int) ($attributeResult['primary_key_value'] ?? 0);

            if ($attributeId <= 0) {
                throw new RuntimeException("Produkt-Sync konnte keine XT-Attribut-ID fuer '{$attributeModel}' ermitteln.");
            }

            $existingAttributeRows[$lookupKey] = array_replace(
                is_array($existingAttributeRow) ? $existingAttributeRow : [],
                ['attributes_id' => $attributeId, 'attributes_model' => $attributeModel],
                $columns
            );
        }
    }

    private function findXtAttributeRow(string $attributeModel, int $parentId): ?array
    {
        $fields = array_values(array_unique(array_merge(
            ['attributes_id', 'attributes_model'],
            \wela_allowed_tables()['xt_plg_products_attributes']['write_fields']
        )));
        $stmt = $this->pdo->prepare(sprintf(
            'SELECT %s FROM `xt_plg_products_attributes` WHERE `attributes_model` = :attributes_model AND `attributes_parent` = :attributes_parent ORDER BY `attributes_id` ASC LIMIT 1',
            implode(', ', array_map(static fn (string $field): string => "`{$field}`", $fields))
        ));
        $stmt->execute([
            ':attributes_model' => $attributeModel,
            ':attributes_parent' => max(0, $parentId),
        ]);

        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        return is_array($row) ? $row : null;
    }

    private function upsertXtProductRow(array $identity, array $columns, ?array $existingProductRow = null): array
    {
        if ($this->schemaInspector->tableHasUniqueIndex('xt_products', ['external_id'])) {
            return \wela_upsert_row_native($this->pdo, 'xt_products', 'products_id', $identity, $columns);
        }

        if (!is_array($existingProductRow)) {
            $insertValues = array_replace($identity, $columns);
            $fields = array_keys($insertValues);
            $sql = sprintf(
                'INSERT INTO `xt_products` (%s) VALUES (%s)',
                implode(', ', array_map(static fn (string $field): string => "`{$field}`", $fields)),
                implode(', ', array_map(static fn (string $field): string => ':' . $field, $fields))
            );
            $stmt = $this->pdo->prepare($sql);
            $stmt->execute($insertValues);

            return [
                'action' => 'inserted',
                'primary_key_value' => ctype_digit((string) $this->pdo->lastInsertId()) ? (int) $this->pdo->lastInsertId() : $this->pdo->lastInsertId(),
            ];
        }

        $productId = (int) ($existingProductRow['products_id'] ?? 0);
        if ($productId <= 0) {
            throw new RuntimeException('Prefetch fuer xt_products lieferte keine gueltige products_id.');
        }

        $updates = [];

        foreach ($columns as $field => $value) {
            $currentValue = $existingProductRow[$field] ?? null;

            if (\wela_values_equal($currentValue, $value)) {
                continue;
            }

            $updates[$field] = $value;
        }

        if ($updates !== []) {
            $assignments = [];
            $params = [
                ':products_id' => $productId,
            ];

            foreach ($updates as $field => $value) {
                $assignments[] = "`{$field}` = :set_{$field}";
                $params[':set_' . $field] = $value;
            }

            $stmt = $this->pdo->prepare(
                sprintf(
                    'UPDATE `xt_products` SET %s WHERE `products_id` = :products_id',
                    implode(', ', $assignments)
                )
            );
            $stmt->execute($params);
        }

        return [
            'action' => $updates === [] ? 'unchanged' : 'updated',
            'primary_key_value' => $productId,
        ];
    }

    private function upsertXtAttributeRow(string $attributeModel, array $columns, ?array $existingAttributeRow = null): array
    {
        if (!is_array($existingAttributeRow)) {
            $insertValues = array_replace(['attributes_model' => $attributeModel], $columns);
            $fields = array_keys($insertValues);
            $sql = sprintf(
                'INSERT INTO `xt_plg_products_attributes` (%s) VALUES (%s)',
                implode(', ', array_map(static fn (string $field): string => "`{$field}`", $fields)),
                implode(', ', array_map(static fn (string $field): string => ':' . $field, $fields))
            );
            $stmt = $this->pdo->prepare($sql);
            $stmt->execute($insertValues);

            return [
                'action' => 'inserted',
                'primary_key_value' => ctype_digit((string) $this->pdo->lastInsertId()) ? (int) $this->pdo->lastInsertId() : $this->pdo->lastInsertId(),
            ];
        }

        $attributeId = (int) ($existingAttributeRow['attributes_id'] ?? 0);
        if ($attributeId <= 0) {
            throw new RuntimeException("Prefetch fuer xt_plg_products_attributes lieferte keine gueltige attributes_id fuer '{$attributeModel}'.");
        }

        $updates = [];

        foreach ($columns as $field => $value) {
            $currentValue = $existingAttributeRow[$field] ?? null;

            if (\wela_values_equal($currentValue, $value)) {
                continue;
            }

            $updates[$field] = $value;
        }

        if ($updates !== []) {
            $assignments = [];
            $params = [
                ':attributes_id' => $attributeId,
            ];

            foreach ($updates as $field => $value) {
                $assignments[] = "`{$field}` = :set_{$field}";
                $params[':set_' . $field] = $value;
            }

            $stmt = $this->pdo->prepare(
                sprintf(
                    'UPDATE `xt_plg_products_attributes` SET %s WHERE `attributes_id` = :attributes_id',
                    implode(', ', $assignments)
                )
            );
            $stmt->execute($params);
        }

        return [
            'action' => $updates === [] ? 'unchanged' : 'updated',
            'primary_key_value' => $attributeId,
        ];
    }

    private function seoRowNeedsWrite(array $seoRow, ?array $existingSeoRow): bool
    {
        if (!is_array($existingSeoRow)) {
            return true;
        }

        foreach ($seoRow as $field => $value) {
            if (!\wela_values_equal($existingSeoRow[$field] ?? null, $value)) {
                return true;
            }
        }

        return false;
    }

    private function deleteMissingRelationRows(string $table, array $fixedWhere, string $relationField, array $desiredRows, array $existingRows): void
    {
        $desiredIds = [];

        foreach ($desiredRows as $row) {
            if (!is_array($row)) {
                continue;
            }

            $relationId = (int) ($row[$relationField] ?? 0);
            if ($relationId > 0) {
                $desiredIds[$relationId] = true;
            }
        }

        $staleIds = [];

        foreach ($existingRows as $relationId => $row) {
            $normalizedRelationId = (int) $relationId;
            if ($normalizedRelationId <= 0) {
                $normalizedRelationId = (int) ($row[$relationField] ?? 0);
            }

            if ($normalizedRelationId > 0 && !isset($desiredIds[$normalizedRelationId])) {
                $staleIds[] = $normalizedRelationId;
            }
        }

        if ($staleIds === []) {
            return;
        }

        $this->deleteRowsForValues($table, $fixedWhere, $relationField, $staleIds);
    }

    private function deleteRowsForValues(string $table, array $fixedWhere, string $field, array $values): int
    {
        $normalizedValues = array_values(array_unique(array_map(
            static fn (mixed $value): int => (int) $value,
            array_values(array_filter($values, static fn (mixed $value): bool => (int) $value > 0))
        )));

        if ($normalizedValues === []) {
            return 0;
        }

        $params = \wela_sql_params($fixedWhere);
        $placeholders = [];

        foreach ($normalizedValues as $index => $value) {
            $placeholder = ':value_' . $index;
            $placeholders[] = $placeholder;
            $params[$placeholder] = $value;
        }

        $fixedWhereClause = \wela_where_clause($fixedWhere);
        $inClause = sprintf('`%s` IN (%s)', $field, implode(', ', $placeholders));
        $whereClause = $fixedWhereClause !== '' ? ($fixedWhereClause . ' AND ' . $inClause) : $inClause;

        $stmt = $this->pdo->prepare(
            sprintf(
                'DELETE FROM `%s` WHERE %s',
                $table,
                $whereClause
            )
        );
        $stmt->execute($params);

        return $stmt->rowCount();
    }
}
