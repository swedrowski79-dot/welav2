<?php

declare(strict_types=1);

final class XtSnapshotService
{
    private array $sourceConfig;
    private int $pageSize;
    private int $writeBatchSize;
    private StageWriter $stageWriter;

    public function __construct(
        private PDO $stageDb,
        private WelaApiClient $xtApiClient,
        array $config,
        private ?SyncMonitor $monitor = null,
        private ?int $runId = null
    ) {
        $snapshotConfig = $config['snapshot'] ?? [];
        $this->sourceConfig = is_array($snapshotConfig['sources'] ?? null) ? $snapshotConfig['sources'] : [];
        $this->pageSize = max(1, (int) ($snapshotConfig['page_size'] ?? 500));
        $this->writeBatchSize = max(1, (int) ($snapshotConfig['write_batch_size'] ?? 500));
        $this->stageWriter = new StageWriter($this->stageDb);
    }

    public function run(): array
    {
        if (!$this->xtApiClient->isConfigured()) {
            throw new RuntimeException('XT-API ist fuer den Snapshot-Import nicht konfiguriert.');
        }

        $this->xtApiClient->health();
        $this->log('info', 'XT-API fuer Snapshot-Import erreichbar.');

        $categoriesSource = $this->fetchAll('categories');
        $productCategoryLinksSource = $this->fetchAll('products_to_categories');
        $productDescriptionsSource = $this->fetchAll('products_description');
        $productAttributesSource = $this->fetchAll('product_attributes');
        $productAttributeDescriptionsSource = $this->fetchAll('product_attribute_descriptions');
        $productToAttributesSource = $this->fetchAll('products_to_attributes');
        $seoUrlsSource = $this->fetchAll('seo_urls');
        $productsSource = $this->fetchAll('products');
        $mediaSource = $this->fetchAll('media');
        $mediaLinksSource = $this->fetchAll('media_links');
        $importedAt = date('Y-m-d H:i:s');

        [$categorySnapshots, $categoryStats, $categoryExternalById] = $this->buildCategorySnapshots($categoriesSource['rows'], $importedAt);
        $productCategoryMap = $this->buildProductCategoryMap($productCategoryLinksSource['rows'], $categoryExternalById);
        $productTranslationHashes = $this->buildProductTranslationHashes($productDescriptionsSource['rows']);
        $productAttributeHashes = $this->buildProductAttributeHashes(
            $productAttributesSource['rows'],
            $productAttributeDescriptionsSource['rows'],
            $productToAttributesSource['rows']
        );
        $productSeoHashes = $this->buildProductSeoHashes($seoUrlsSource['rows']);
        [$productSnapshots, $productIdMap, $productStats] = $this->buildProductSnapshots(
            $productsSource['rows'],
            $productCategoryMap,
            $productTranslationHashes,
            $productAttributeHashes,
            $productSeoHashes,
            $importedAt
        );
        [$mediaSnapshots, $documentSnapshots, $mediaStats] = $this->buildMediaAndDocumentSnapshots(
            $mediaSource['rows'],
            $mediaLinksSource['rows'],
            $productIdMap,
            $importedAt
        );

        $this->stageDb->beginTransaction();

        try {
            $this->clearSnapshotTables();

            $this->insertBatches('xt_products_snapshot', $productSnapshots);
            $this->insertBatches('xt_categories_snapshot', $categorySnapshots);
            $this->insertBatches('xt_media_snapshot', $mediaSnapshots);
            $this->insertBatches('xt_documents_snapshot', $documentSnapshots);

            $this->stageDb->commit();
        } catch (Throwable $exception) {
            if ($this->stageDb->inTransaction()) {
                $this->stageDb->rollBack();
            }

            throw $exception;
        }

        $stats = [
            'products' => count($productSnapshots),
            'categories' => count($categorySnapshots),
            'media' => count($mediaSnapshots),
            'documents' => count($documentSnapshots),
            'source_counts' => [
                'xt_products' => $productsSource['total'],
                'xt_categories' => $categoriesSource['total'],
                'xt_products_to_categories' => $productCategoryLinksSource['total'],
                'xt_products_description' => $productDescriptionsSource['total'],
                'xt_media' => $mediaSource['total'],
                'xt_media_link' => $mediaLinksSource['total'],
                'xt_plg_products_attributes' => $productAttributesSource['total'],
                'xt_plg_products_attributes_description' => $productAttributeDescriptionsSource['total'],
                'xt_plg_products_to_attributes' => $productToAttributesSource['total'],
                'xt_seo_url' => $seoUrlsSource['total'],
            ],
            'skipped' => [
                'products_without_external_id' => $productStats['without_external_id'],
                'categories_without_external_id' => $categoryStats['without_external_id'],
                'media_without_external_id' => $mediaStats['media_without_external_id'],
                'media_without_product_mapping' => $mediaStats['media_without_product_mapping'],
                'documents_without_external_id' => $mediaStats['documents_without_external_id'],
                'documents_without_product_mapping' => $mediaStats['documents_without_product_mapping'],
                'unsupported_media_links' => $mediaStats['unsupported_links'],
            ],
        ];

        $this->log('info', 'XT-Snapshot-Tabellen aktualisiert.', $stats);

        return $stats;
    }

    private function clearSnapshotTables(): void
    {
        foreach ([
            'xt_products_snapshot',
            'xt_categories_snapshot',
            'xt_media_snapshot',
            'xt_documents_snapshot',
        ] as $table) {
            $this->stageDb->exec("DELETE FROM `{$table}`");
        }
    }

    private function fetchAll(string $key): array
    {
        $source = $this->sourceConfig[$key] ?? null;
        if (!is_array($source)) {
            throw new RuntimeException("XT-Snapshot-Quelle '{$key}' ist nicht konfiguriert.");
        }

        $table = (string) ($source['table'] ?? '');
        $fields = $source['fields'] ?? [];

        if ($table === '' || !is_array($fields) || $fields === []) {
            throw new RuntimeException("XT-Snapshot-Quelle '{$key}' ist unvollstaendig konfiguriert.");
        }

        $rows = [];
        $offset = 0;
        $total = 0;

        do {
            $page = $this->xtApiClient->fetchRows($table, $fields, $offset, $this->pageSize);
            $pageRows = $page['rows'] ?? [];
            $total = max($total, (int) ($page['total'] ?? 0));

            foreach ($pageRows as $row) {
                if (is_array($row)) {
                    $rows[] = $row;
                }
            }

            $nextOffset = $page['next_offset'] ?? null;
            if (!is_int($nextOffset) || $nextOffset <= $offset) {
                break;
            }

            $offset = $nextOffset;
        } while (true);

        $this->log('info', 'XT-Snapshot-Quelle geladen.', [
            'source' => $key,
            'table' => $table,
            'rows' => count($rows),
            'total' => $total,
        ]);

        return [
            'rows' => $rows,
            'total' => $total > 0 ? $total : count($rows),
        ];
    }

    private function buildProductSnapshots(
        array $rows,
        array $productCategoryMap,
        array $productTranslationHashes,
        array $productAttributeHashes,
        array $productSeoHashes,
        string $importedAt
    ): array
    {
        $snapshots = [];
        $productIdMap = [];
        $withoutExternalId = 0;

        foreach ($rows as $row) {
            if (!is_array($row)) {
                continue;
            }

            $productId = $this->nullableInt($row['products_id'] ?? null);
            $externalId = $this->trimString($row['external_id'] ?? null);
            if ($externalId === null) {
                $withoutExternalId++;
                continue;
            }

            $snapshot = [
                'xt_products_id' => $this->nullableInt($row['products_id'] ?? null),
                'external_id' => $externalId,
                'afs_artikel_id' => $this->nullableInt($externalId),
                'category_afs_id' => $productCategoryMap[$productId ?? -1] ?? null,
                'sku' => $this->trimString($row['products_model'] ?? null),
                'ean' => $this->trimString($row['products_ean'] ?? null),
                'stock' => $this->nullableNumeric($row['products_quantity'] ?? null),
                'price' => $this->nullableNumeric($row['products_price'] ?? null),
                'weight' => $this->nullableNumeric($row['products_weight'] ?? null),
                'online_flag' => $this->nullableInt($row['products_status'] ?? null),
                'is_master' => $this->nullableInt($row['products_master_flag'] ?? null),
                'master_sku' => $this->trimString($row['products_master_model'] ?? null),
                'image' => $this->trimString($row['products_image'] ?? null),
                'translation_hash' => $productTranslationHashes[$productId ?? -1] ?? null,
                'attribute_hash' => $productAttributeHashes[$productId ?? -1] ?? null,
                'seo_hash' => $productSeoHashes[$productId ?? -1] ?? null,
                'last_modified' => $this->trimString($row['last_modified'] ?? null),
                'imported_at' => $importedAt,
            ];
            $snapshot['snapshot_hash'] = $this->hashSnapshot($snapshot, ['snapshot_hash']);

            $snapshots[] = $snapshot;

            if ($productId !== null) {
                $productIdMap[$productId] = $externalId;
            }
        }

        return [$snapshots, $productIdMap, ['without_external_id' => $withoutExternalId]];
    }

    private function buildCategorySnapshots(array $rows, string $importedAt): array
    {
        $categoryExternalById = [];
        foreach ($rows as $row) {
            if (!is_array($row)) {
                continue;
            }

            $categoryId = $this->nullableInt($row['categories_id'] ?? null);
            $externalId = $this->trimString($row['external_id'] ?? null);

            if ($categoryId !== null && $externalId !== null) {
                $categoryExternalById[$categoryId] = $externalId;
            }
        }

        $snapshots = [];
        $withoutExternalId = 0;

        foreach ($rows as $row) {
            if (!is_array($row)) {
                continue;
            }

            $externalId = $this->trimString($row['external_id'] ?? null);
            if ($externalId === null) {
                $withoutExternalId++;
                continue;
            }

            $parentXtId = $this->nullableInt($row['parent_id'] ?? null);
            $parentExternalId = $parentXtId !== null ? ($categoryExternalById[$parentXtId] ?? null) : null;
            $snapshot = [
                'xt_categories_id' => $this->nullableInt($row['categories_id'] ?? null),
                'external_id' => $externalId,
                'afs_wg_id' => $this->nullableInt($externalId),
                'parent_xt_id' => $parentXtId,
                'parent_external_id' => $parentExternalId,
                'parent_afs_id' => $this->nullableInt($parentExternalId),
                'level' => $this->nullableInt($row['categories_level'] ?? null),
                'image' => $this->trimString($row['categories_image'] ?? null),
                'header_image' => $this->trimString($row['categories_master_image'] ?? null),
                'online_flag' => $this->nullableInt($row['categories_status'] ?? null),
                'last_modified' => $this->trimString($row['last_modified'] ?? null),
                'imported_at' => $importedAt,
            ];
            $snapshot['snapshot_hash'] = $this->hashSnapshot($snapshot, ['snapshot_hash']);

            $snapshots[] = $snapshot;
        }

        return [$snapshots, ['without_external_id' => $withoutExternalId], $categoryExternalById];
    }

    private function buildMediaAndDocumentSnapshots(array $mediaRows, array $linkRows, array $productIdMap, string $importedAt): array
    {
        $mediaById = [];
        foreach ($mediaRows as $row) {
            if (!is_array($row)) {
                continue;
            }

            $mediaId = $this->nullableInt($row['id'] ?? null);
            if ($mediaId === null) {
                continue;
            }

            $mediaById[$mediaId] = $row;
        }

        $mediaSnapshots = [];
        $documentSnapshots = [];
        $stats = [
            'media_without_external_id' => 0,
            'media_without_product_mapping' => 0,
            'documents_without_external_id' => 0,
            'documents_without_product_mapping' => 0,
            'unsupported_links' => 0,
        ];

        foreach ($linkRows as $row) {
            if (!is_array($row)) {
                continue;
            }

            $type = $this->trimString($row['type'] ?? null);
            $class = $this->trimString($row['class'] ?? null);
            if ($class !== null && $class !== 'product') {
                continue;
            }

            if ($type !== 'images' && $type !== 'files') {
                $stats['unsupported_links']++;
                continue;
            }

            $mediaId = $this->nullableInt($row['m_id'] ?? null);
            if ($mediaId === null || !isset($mediaById[$mediaId])) {
                continue;
            }

            $media = $mediaById[$mediaId];
            $externalId = $this->trimString($media['external_id'] ?? null);
            $productExternalId = $productIdMap[$this->nullableInt($row['link_id'] ?? null) ?? -1] ?? null;

            if ($externalId === null) {
                $stats[$type === 'images' ? 'media_without_external_id' : 'documents_without_external_id']++;
                continue;
            }

            if ($productExternalId === null) {
                $stats[$type === 'images' ? 'media_without_product_mapping' : 'documents_without_product_mapping']++;
                continue;
            }

            $baseSnapshot = [
                'xt_media_id' => $mediaId,
                'xt_products_id' => $this->nullableInt($row['link_id'] ?? null),
                'afs_artikel_id' => $this->nullableInt($productExternalId),
                'file_name' => $this->trimString($media['file'] ?? null),
                'class' => $this->trimString($media['class'] ?? $class),
                'sort_order' => $this->nullableInt($row['sort_order'] ?? null),
                'status' => $this->nullableInt($media['status'] ?? null),
                'last_modified' => $this->trimString($media['last_modified'] ?? null),
                'imported_at' => $importedAt,
            ];

            if ($type === 'images') {
                $snapshot = $baseSnapshot + [
                    'media_external_id' => $externalId,
                    'media_type' => $this->trimString($media['type'] ?? $type),
                ];
                $snapshot['snapshot_hash'] = $this->hashSnapshot($snapshot, ['snapshot_hash']);
                $mediaSnapshots[] = $snapshot;
                continue;
            }

            $snapshot = $baseSnapshot + [
                'document_external_id' => $externalId,
                'afs_document_id' => $this->nullableInt($externalId),
                'document_type' => $this->trimString($media['type'] ?? $type),
            ];
            $snapshot['snapshot_hash'] = $this->hashSnapshot($snapshot, ['snapshot_hash']);
            $documentSnapshots[] = $snapshot;
        }

        return [$mediaSnapshots, $documentSnapshots, $stats];
    }

    private function buildProductCategoryMap(array $rows, array $categoryExternalById): array
    {
        $map = [];

        foreach ($rows as $row) {
            if (!is_array($row)) {
                continue;
            }

            $productId = $this->nullableInt($row['products_id'] ?? null);
            $categoryId = $this->nullableInt($row['categories_id'] ?? null);
            if ($productId === null || $categoryId === null || isset($map[$productId])) {
                continue;
            }

            $categoryExternalId = $categoryExternalById[$categoryId] ?? null;
            $map[$productId] = $this->nullableInt($categoryExternalId);
        }

        return $map;
    }

    private function buildProductTranslationHashes(array $rows): array
    {
        $grouped = [];

        foreach ($rows as $row) {
            if (!is_array($row)) {
                continue;
            }

            $productId = $this->nullableInt($row['products_id'] ?? null);
            $languageCode = $this->trimString($row['language_code'] ?? null);

            if ($productId === null || $languageCode === null || !in_array($languageCode, ['de', 'en', 'fr', 'nl'], true)) {
                continue;
            }

            $grouped[$productId][] = [
                'language_code' => $languageCode,
                'name' => $this->trimString($row['products_name'] ?? null),
                'description' => $this->trimString($row['products_description'] ?? null),
                'short_description' => $this->trimString($row['products_short_description'] ?? null),
            ];
        }

        return $this->hashGroupedRows($grouped, ['language_code', 'name', 'description', 'short_description']);
    }

    private function buildProductSeoHashes(array $rows): array
    {
        $grouped = [];

        foreach ($rows as $row) {
            if (!is_array($row)) {
                continue;
            }

            if ((int) ($row['link_type'] ?? 0) !== 1) {
                continue;
            }

            $productId = $this->nullableInt($row['link_id'] ?? null);
            $languageCode = $this->trimString($row['language_code'] ?? null);

            if ($productId === null || $languageCode === null || !in_array($languageCode, ['de', 'en', 'fr', 'nl'], true)) {
                continue;
            }

            $grouped[$productId][] = [
                'language_code' => $languageCode,
                'meta_title' => $this->trimString($row['meta_title'] ?? null),
                'meta_description' => $this->trimString($row['meta_description'] ?? null),
            ];
        }

        return $this->hashGroupedRows($grouped, ['language_code', 'meta_title', 'meta_description']);
    }

    private function buildProductAttributeHashes(array $attributeRows, array $descriptionRows, array $relationRows): array
    {
        $attributeEntities = [];
        foreach ($attributeRows as $row) {
            if (!is_array($row)) {
                continue;
            }

            $attributeId = $this->nullableInt($row['attributes_id'] ?? null);
            if ($attributeId === null) {
                continue;
            }

            $attributeEntities[$attributeId] = [
                'sort_order' => $this->nullableInt($row['sort_order'] ?? null),
            ];
        }

        $attributeDescriptions = [];
        foreach ($descriptionRows as $row) {
            if (!is_array($row)) {
                continue;
            }

            $attributeId = $this->nullableInt($row['attributes_id'] ?? null);
            $languageCode = $this->trimString($row['language_code'] ?? null);

            if ($attributeId === null || $languageCode === null || !in_array($languageCode, ['de', 'en', 'fr', 'nl'], true)) {
                continue;
            }

            $attributeDescriptions[$attributeId][$languageCode] = [
                'attribute_name' => $this->trimString($row['attributes_name'] ?? null),
                'attribute_value' => $this->trimString($row['attributes_desc'] ?? null),
            ];
        }

        $grouped = [];
        foreach ($relationRows as $row) {
            if (!is_array($row)) {
                continue;
            }

            $productId = $this->nullableInt($row['products_id'] ?? null);
            $attributeId = $this->nullableInt($row['attributes_id'] ?? null);

            if ($productId === null || $attributeId === null || !isset($attributeEntities[$attributeId])) {
                continue;
            }

            foreach ($attributeDescriptions[$attributeId] ?? [] as $languageCode => $description) {
                if (($description['attribute_name'] ?? null) === null && ($description['attribute_value'] ?? null) === null) {
                    continue;
                }

                $grouped[$productId][] = [
                    'language_code' => $languageCode,
                    'sort_order' => $attributeEntities[$attributeId]['sort_order'],
                    'attribute_name' => $description['attribute_name'] ?? null,
                    'attribute_value' => $description['attribute_value'] ?? null,
                ];
            }
        }

        return $this->hashGroupedRows($grouped, ['language_code', 'sort_order', 'attribute_name', 'attribute_value']);
    }

    private function hashGroupedRows(array $groupedRows, array $fields): array
    {
        $hashes = [];

        foreach ($groupedRows as $entityId => $rows) {
            if (!is_array($rows) || $rows === []) {
                continue;
            }

            $normalizedRows = [];
            foreach ($rows as $row) {
                if (!is_array($row)) {
                    continue;
                }

                $normalized = [];
                foreach ($fields as $field) {
                    $normalized[$field] = $row[$field] ?? null;
                }

                $normalizedRows[] = $normalized;
            }

            usort($normalizedRows, static function (array $left, array $right): int {
                return strcmp(
                    json_encode($left, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?: '',
                    json_encode($right, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?: ''
                );
            });

            $hashes[(int) $entityId] = hash(
                'sha256',
                json_encode($normalizedRows, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?: '[]'
            );
        }

        return $hashes;
    }

    private function insertBatches(string $table, array $rows): void
    {
        if ($rows === []) {
            return;
        }

        foreach (array_chunk($rows, $this->writeBatchSize) as $chunk) {
            $this->stageWriter->insertMany($table, $chunk);
        }
    }

    private function hashSnapshot(array $row, array $exclude = []): string
    {
        foreach ($exclude as $key) {
            unset($row[$key]);
        }

        ksort($row);

        return hash('sha256', json_encode($row, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) ?: '{}');
    }

    private function log(string $level, string $message, array $context = []): void
    {
        if ($this->monitor === null) {
            return;
        }

        $this->monitor->log($this->runId, $level, $message, $context);
    }

    private function trimString(mixed $value): ?string
    {
        if ($value === null) {
            return null;
        }

        $normalized = trim((string) $value);

        return $normalized === '' ? null : $normalized;
    }

    private function nullableInt(mixed $value): ?int
    {
        if ($value === null || $value === '') {
            return null;
        }

        $normalized = trim((string) $value);
        if ($normalized === '' || !preg_match('/^-?\\d+$/', $normalized)) {
            return null;
        }

        return (int) $normalized;
    }

    private function nullableNumeric(mixed $value): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }

        $normalized = trim((string) $value);

        return $normalized === '' ? null : $normalized;
    }
}
