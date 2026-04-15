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

        $productsSource = $this->fetchAll('products');
        $categoriesSource = $this->fetchAll('categories');
        $mediaSource = $this->fetchAll('media');
        $mediaLinksSource = $this->fetchAll('media_links');
        $importedAt = date('Y-m-d H:i:s');

        [$productSnapshots, $productIdMap, $productStats] = $this->buildProductSnapshots($productsSource['rows'], $importedAt);
        [$categorySnapshots, $categoryStats] = $this->buildCategorySnapshots($categoriesSource['rows'], $importedAt);
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
                'xt_media' => $mediaSource['total'],
                'xt_media_link' => $mediaLinksSource['total'],
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

    private function buildProductSnapshots(array $rows, string $importedAt): array
    {
        $snapshots = [];
        $productIdMap = [];
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

            $snapshot = [
                'xt_products_id' => $this->nullableInt($row['products_id'] ?? null),
                'external_id' => $externalId,
                'afs_artikel_id' => $this->nullableInt($externalId),
                'sku' => $this->trimString($row['products_model'] ?? null),
                'ean' => $this->trimString($row['products_ean'] ?? null),
                'stock' => $this->nullableNumeric($row['products_quantity'] ?? null),
                'price' => $this->nullableNumeric($row['products_price'] ?? null),
                'weight' => $this->nullableNumeric($row['products_weight'] ?? null),
                'online_flag' => $this->nullableInt($row['products_status'] ?? null),
                'is_master' => $this->nullableInt($row['products_master_flag'] ?? null),
                'master_sku' => $this->trimString($row['products_master_model'] ?? null),
                'image' => $this->trimString($row['products_image'] ?? null),
                'last_modified' => $this->trimString($row['last_modified'] ?? null),
                'imported_at' => $importedAt,
            ];
            $snapshot['snapshot_hash'] = $this->hashSnapshot($snapshot, ['snapshot_hash']);

            $snapshots[] = $snapshot;

            $productId = $this->nullableInt($row['products_id'] ?? null);
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

        return [$snapshots, ['without_external_id' => $withoutExternalId]];
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
