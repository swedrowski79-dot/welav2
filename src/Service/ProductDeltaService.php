<?php

class ProductDeltaService
{
    private string $configKey;
    private string $entityLabel;
    private string $entityType;
    private string $stageTable;
    private ?string $translationTable;
    private ?string $attributeTable;
    private string $stateTable;
    private string $identityField;
    private string $stateIdentityField;
    private string $hashField;
    private string $stateHashField;
    private string $stateLastSeenField;
    private string $removedHash;
    private string $queueTable;
    private int $queueInsertBatchSize;
    private array $hashFields;
    private array $payloadFields;
    private array $removedPayload;
    private string $monitorSource;
    private array $mirrorCompareFields = [];
    private ?string $mirrorTranslationHashField = null;
    private ?string $mirrorAttributeHashField = null;
    private ?string $mirrorSeoHashField = null;
    private bool $mirrorRequireSuccessRun = true;
    private array $entityOrderBy = [];
    private array $pendingQueueEntities = [];
    private array $queuedEntries = [];

    public function __construct(
        private PDO $stageDb,
        private array $deltaConfig,
        private ?SyncMonitor $monitor = null,
        private ?int $runId = null,
        string $configKey = 'product_export_queue'
    ) {
        $this->configKey = $configKey;
        $this->applyConfig($configKey);
    }

    public function run(): array
    {
        if ($this->hashFields === [] && $this->payloadFields === []) {
            throw new RuntimeException(
                "Delta config '{$this->configKey}' must define hash_fields or payload_fields."
            );
        }

        $runTimestamp = (new DateTimeImmutable('now', new DateTimeZone('UTC')))->format('Y-m-d H:i:s');
        $entities = $this->fetchEntities();
        $translations = $this->fetchTranslations();
        $attributes = $this->fetchAttributes();
        $states = $this->fetchStates();
        $mirrorContext = $this->fetchMirrorRows();
        $mirrorRows = $mirrorContext['rows'];
        $mirrorEnabled = (bool) ($mirrorContext['enabled'] ?? false);
        $initialQueueCounts = $this->fetchQueueCounts();
        $this->pendingQueueEntities = $this->fetchExistingQueueEntities();
        $this->queuedEntries = [];

        $stats = [
            'processed' => 0,
            'insert' => 0,
            'update' => 0,
            'removed' => 0,
            'mirror_live_removed' => 0,
            'unchanged' => 0,
            'deduplicated' => 0,
            'errors' => 0,
            'mirror_matched' => 0,
            'mirror_missing' => 0,
            'mirror_mismatched' => 0,
            'mirror_removed_skipped' => 0,
            'mirror_enabled' => $mirrorEnabled,
        ];

        $currentEntityIds = [];
        $updateHashStmt = $this->stageDb->prepare(
            "UPDATE `{$this->stageTable}` SET `{$this->hashField}` = :hash WHERE `{$this->identityField}` = :entity_id"
        );
        $upsertStateStmt = $this->stageDb->prepare(
            "INSERT INTO `{$this->stateTable}` (`{$this->stateIdentityField}`, `{$this->stateLastSeenField}`)
             VALUES (:state_id, :last_seen_at)
             ON DUPLICATE KEY UPDATE
                `{$this->stateLastSeenField}` = VALUES(`{$this->stateLastSeenField}`)"
        );
        $syncStateStmt = $this->stageDb->prepare(
            "INSERT INTO `{$this->stateTable}` (`{$this->stateIdentityField}`, `{$this->stateHashField}`, `{$this->stateLastSeenField}`)
             VALUES (:state_id, :hash, :last_seen_at)
             ON DUPLICATE KEY UPDATE
                `{$this->stateHashField}` = VALUES(`{$this->stateHashField}`),
                `{$this->stateLastSeenField}` = VALUES(`{$this->stateLastSeenField}`)"
        );
        $markRemovedStateStmt = $this->stageDb->prepare(
            "UPDATE `{$this->stateTable}`
             SET `{$this->stateHashField}` = :hash,
                 `{$this->stateLastSeenField}` = :last_seen_at
             WHERE `{$this->stateIdentityField}` = :state_id"
        );

        foreach ($entities as $entity) {
            $entityId = $this->normalizeEntityId($entity[$this->identityField] ?? null);
            if ($entityId === null) {
                continue;
            }

            $currentEntityIds[$entityId] = true;
            $sku = $this->normalizeString($entity['sku'] ?? null);
            $stats['processed']++;

            try {
                $payloadData = $this->buildPayloadData(
                    $entity,
                    $translations[$entityId] ?? [],
                    $attributes[$entityId] ?? []
                );
                $hash = $this->buildHash($payloadData);

                $updateHashStmt->execute([
                    ':hash' => $hash,
                    ':entity_id' => $entityId,
                ]);

                $state = $states[$entityId] ?? null;
                $mirrorDecision = $this->mirrorDecision($payloadData, $mirrorRows[$entityId] ?? null, $mirrorEnabled);

                if ($mirrorEnabled) {
                    if ($mirrorDecision['matched']) {
                        $stats['mirror_matched']++;
                    } elseif ($mirrorDecision['exists']) {
                        $stats['mirror_mismatched']++;
                    } else {
                        $stats['mirror_missing']++;
                    }
                }

                if ($mirrorDecision['matched']) {
                    $stats['unchanged']++;
                    $syncStateStmt->execute([
                        ':state_id' => $entityId,
                        ':hash' => $hash,
                        ':last_seen_at' => $runTimestamp,
                    ]);
                } else {
                    $action = $this->nextAction($state, $hash, $mirrorDecision);

                    if ($action !== null) {
                        if ($this->enqueue($entityId, $action, $payloadData, $hash)) {
                            $stats[$action]++;
                        } else {
                            $stats['deduplicated']++;
                        }
                    } else {
                        $stats['unchanged']++;
                    }

                    $upsertStateStmt->execute([
                        ':state_id' => $entityId,
                        ':last_seen_at' => $runTimestamp,
                    ]);
                }
            } catch (Throwable $exception) {
                $stats['errors']++;
                $this->logRecordError(
                    $entityId,
                    "{$this->entityLabel}-Delta konnte nicht berechnet werden.",
                    $exception
                );
            }
        }

        foreach ($states as $entityId => $state) {
            if (isset($currentEntityIds[$entityId])) {
                continue;
            }

            if (($state[$this->stateHashField] ?? null) === $this->removedHash) {
                continue;
            }

            if ($mirrorEnabled && !isset($mirrorRows[$entityId])) {
                $syncStateStmt->execute([
                    ':state_id' => $entityId,
                    ':hash' => $this->removedHash,
                    ':last_seen_at' => $runTimestamp,
                ]);
                $stats['mirror_removed_skipped']++;
                $stats['unchanged']++;
                continue;
            }

            try {
                if ($this->enqueueRemovedUpdate($entityId)) {
                    $markRemovedStateStmt->execute([
                        ':hash' => $this->removedHash,
                        ':state_id' => $entityId,
                        ':last_seen_at' => $runTimestamp,
                    ]);
                    $stats['removed']++;
                } else {
                    $stats['deduplicated']++;
                }
            } catch (Throwable $exception) {
                $stats['errors']++;
                $this->logRecordError(
                    $entityId,
                    "{$this->entityLabel}-Entfernung konnte nicht in die Export Queue geschrieben werden.",
                    $exception
                );
            }
        }

        if (in_array($this->entityType, ['product', 'category'], true) && $mirrorEnabled) {
            foreach ($this->findMirrorEntityRemovals(
                $mirrorRows,
                $currentEntityIds,
                $states
            ) as $entityId) {
                try {
                    if ($this->enqueueRemovedUpdate($entityId)) {
                        $syncStateStmt->execute([
                            ':state_id' => $entityId,
                            ':hash' => $this->removedHash,
                            ':last_seen_at' => $runTimestamp,
                        ]);
                        $stats['removed']++;
                        $stats['mirror_live_removed']++;
                    } else {
                        $stats['deduplicated']++;
                    }
                } catch (Throwable $exception) {
                    $stats['errors']++;
                    $this->logRecordError(
                        $entityId,
                        "{$this->entityLabel}-Mirror-Entfernung konnte nicht in die Export Queue geschrieben werden.",
                        $exception
                    );
                }
            }
        }

        $this->flushQueuedEntries();
        $finalQueueCounts = $this->fetchQueueCounts();
        $stats['changed'] = $stats['insert'] + $stats['update'] + $stats['removed'];
        $stats['entity_type'] = $this->entityType;
        $stats['config_key'] = $this->configKey;
        $stats['pending_before'] = (int) ($initialQueueCounts['pending'] ?? 0);
        $stats['processing_before'] = (int) ($initialQueueCounts['processing'] ?? 0);
        $stats['pending_after'] = (int) ($finalQueueCounts['pending'] ?? 0);
        $stats['processing_after'] = (int) ($finalQueueCounts['processing'] ?? 0);
        $stats['queue_created'] = $stats['changed'];
        $stats['result_reason'] = $this->deltaResultReason($stats);

        if ($this->monitor !== null) {
            $this->monitor->log($this->runId, 'info', "{$this->entityLabel}-Delta berechnet.", [
                'entity_type' => $this->entityType,
                'processed' => $stats['processed'],
                'changed' => $stats['changed'],
                'insert' => $stats['insert'],
                'update' => $stats['update'],
                'removed' => $stats['removed'],
                'deduplicated' => $stats['deduplicated'],
                'errors' => $stats['errors'],
                'mirror_enabled' => $stats['mirror_enabled'],
                'mirror_matched' => $stats['mirror_matched'],
                'mirror_missing' => $stats['mirror_missing'],
                'mirror_mismatched' => $stats['mirror_mismatched'],
                'mirror_removed_skipped' => $stats['mirror_removed_skipped'],
                'mirror_live_removed' => $stats['mirror_live_removed'],
                'pending_before' => $stats['pending_before'],
                'processing_before' => $stats['processing_before'],
                'pending_after' => $stats['pending_after'],
                'processing_after' => $stats['processing_after'],
                'queue_created' => $stats['queue_created'],
                'result_reason' => $stats['result_reason'],
            ]);

            $this->monitor->log($this->runId, 'info', "{$this->entityLabel}-Queue-Sichtbarkeit aktualisiert.", [
                'entity_type' => $this->entityType,
                'queue_created' => $stats['queue_created'],
                'pending_before' => $stats['pending_before'],
                'processing_before' => $stats['processing_before'],
                'pending_after' => $stats['pending_after'],
                'processing_after' => $stats['processing_after'],
                'deduplicated' => $stats['deduplicated'],
                'mirror_enabled' => $stats['mirror_enabled'],
                'mirror_matched' => $stats['mirror_matched'],
                'mirror_missing' => $stats['mirror_missing'],
                'mirror_mismatched' => $stats['mirror_mismatched'],
                'mirror_removed_skipped' => $stats['mirror_removed_skipped'],
                'mirror_live_removed' => $stats['mirror_live_removed'],
                'result_reason' => $stats['result_reason'],
            ]);
        }

        return $stats;
    }

    private function applyConfig(string $configKey): void
    {
        $config = $this->deltaConfig[$configKey] ?? null;
        if (!is_array($config)) {
            throw new RuntimeException("Delta config '{$configKey}' not found.");
        }

        $this->entityLabel = (string) ($config['label'] ?? ucfirst((string) ($config['entity_type'] ?? 'entity')));
        $this->entityType = (string) ($config['entity_type'] ?? 'product');
        $this->stageTable = (string) ($config['stage_table'] ?? 'stage_products');
        $this->translationTable = $this->normalizeTableName($config['translation_table'] ?? null);
        $this->attributeTable = $this->normalizeTableName($config['attribute_table'] ?? null);
        $this->stateTable = (string) ($config['state_table'] ?? 'product_export_state');
        $this->identityField = (string) ($config['identity_field'] ?? 'afs_artikel_id');
        $this->stateIdentityField = (string) ($config['state_identity_field'] ?? 'product_id');
        $this->hashField = (string) ($config['hash_field'] ?? 'hash');
        $this->stateHashField = (string) ($config['state_hash_field'] ?? 'last_exported_hash');
        $this->stateLastSeenField = (string) ($config['state_last_seen_field'] ?? 'last_seen_at');
        $this->removedHash = hash('sha256', (string) ($config['removed_hash_value'] ?? $config['offline_hash_value'] ?? 'offline'));
        $this->queueTable = (string) ($config['queue_table'] ?? 'export_queue');
        $this->queueInsertBatchSize = max(1, (int) ($config['queue_insert_batch_size'] ?? 200));
        $this->hashFields = is_array($config['hash_fields'] ?? null) ? $config['hash_fields'] : [];
        $this->payloadFields = is_array($config['payload_fields'] ?? null) ? $config['payload_fields'] : [];
        $this->removedPayload = is_array($config['removed_payload'] ?? null) ? $config['removed_payload'] : ['online' => 0];
        $this->monitorSource = (string) ($config['monitor_source'] ?? 'delta_' . $this->entityType);
        $this->mirrorCompareFields = is_array($config['mirror_compare_fields'] ?? null) ? $config['mirror_compare_fields'] : [];
        $this->mirrorTranslationHashField = $this->normalizeFieldName($config['mirror_translation_hash_field'] ?? null);
        $this->mirrorAttributeHashField = $this->normalizeFieldName($config['mirror_attribute_hash_field'] ?? null);
        $this->mirrorSeoHashField = $this->normalizeFieldName($config['mirror_seo_hash_field'] ?? null);
        $this->mirrorRequireSuccessRun = !array_key_exists('mirror_require_success_run', $config)
            || (bool) $config['mirror_require_success_run'];
        $this->entityOrderBy = $this->normalizeOrderBy($config['entity_order_by'] ?? null);
    }

    private function normalizeTableName(mixed $value): ?string
    {
        if (!is_string($value)) {
            return null;
        }

        $table = trim($value);

        return $table === '' ? null : $table;
    }

    private function normalizeFieldName(mixed $value): ?string
    {
        if (!is_string($value)) {
            return null;
        }

        $field = trim($value);

        return $field === '' ? null : $field;
    }

    private function fetchEntities(): array
    {
        $stmt = $this->stageDb->query("SELECT * FROM `{$this->stageTable}` ORDER BY {$this->entityOrderBySql()}");
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    private function fetchTranslations(): array
    {
        if ($this->translationTable === null) {
            return [];
        }

        $stmt = $this->stageDb->query(
            "SELECT * FROM `{$this->translationTable}` ORDER BY `{$this->identityField}` ASC, `language_code` ASC"
        );

        $translations = [];
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $entityId = $this->normalizeEntityId($row[$this->identityField] ?? null);
            if ($entityId === null) {
                continue;
            }

            $translations[$entityId][] = [
                'language_code' => $this->normalizeString($row['language_code'] ?? null),
                'name' => $this->normalizeString($row['name'] ?? null),
                'description' => $this->normalizeString($row['description'] ?? null),
                'technical_data_html' => $this->normalizeString($row['technical_data_html'] ?? null),
                'short_description' => $this->normalizeString($row['short_description'] ?? null),
                'meta_title' => $this->normalizeString($row['meta_title'] ?? null),
                'meta_description' => $this->normalizeString($row['meta_description'] ?? null),
                'product_type' => $this->normalizeString($row['product_type'] ?? null),
                'attribute_name1' => $this->normalizeString($row['attribute_name1'] ?? null),
                'attribute_name2' => $this->normalizeString($row['attribute_name2'] ?? null),
                'attribute_name3' => $this->normalizeString($row['attribute_name3'] ?? null),
                'attribute_name4' => $this->normalizeString($row['attribute_name4'] ?? null),
                'attribute_value1' => $this->normalizeString($row['attribute_value1'] ?? null),
                'attribute_value2' => $this->normalizeString($row['attribute_value2'] ?? null),
                'attribute_value3' => $this->normalizeString($row['attribute_value3'] ?? null),
                'attribute_value4' => $this->normalizeString($row['attribute_value4'] ?? null),
                'source_directory' => $this->normalizeString($row['source_directory'] ?? null),
            ];
        }

        return $translations;
    }

    private function fetchAttributes(): array
    {
        if ($this->attributeTable === null) {
            return [];
        }

        $stmt = $this->stageDb->query(
            "SELECT * FROM `{$this->attributeTable}` ORDER BY `{$this->identityField}` ASC, `language_code` ASC, `sort_order` ASC"
        );

        $attributes = [];
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $entityId = $this->normalizeEntityId($row[$this->identityField] ?? null);
            if ($entityId === null) {
                continue;
            }

            $attributes[$entityId][] = [
                'language_code' => $this->normalizeString($row['language_code'] ?? null),
                'sort_order' => isset($row['sort_order']) ? (int) $row['sort_order'] : null,
                'attribute_name' => $this->normalizeString($row['attribute_name'] ?? null),
                'attribute_value' => $this->normalizeString($row['attribute_value'] ?? null),
                'source_directory' => $this->normalizeString($row['source_directory'] ?? null),
            ];
        }

        return $attributes;
    }

    private function fetchStates(): array
    {
        $stmt = $this->stageDb->query(
            "SELECT `{$this->stateIdentityField}`, `{$this->stateHashField}`, `{$this->stateLastSeenField}`
             FROM `{$this->stateTable}`
             ORDER BY `{$this->stateIdentityField}` ASC"
        );

        $states = [];
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $entityId = $this->normalizeEntityId($row[$this->stateIdentityField] ?? null);
            if ($entityId === null) {
                continue;
            }

            $states[$entityId] = $row;
        }

        return $states;
    }

    private function fetchMirrorRows(): array
    {
        if (!$this->supportsMirrorComparison()) {
            return ['enabled' => false, 'rows' => []];
        }

        if ($this->mirrorRequireSuccessRun && !$this->hasSuccessfulMirrorRefreshRun()) {
            return ['enabled' => false, 'rows' => []];
        }

        try {
            $rows = match ($this->entityType) {
                'category' => $this->fetchCategoryMirrorRows(),
                'product' => $this->fetchProductMirrorRows(),
                'media' => $this->fetchMediaMirrorRows(),
                'document' => $this->fetchDocumentMirrorRows(),
                default => [],
            };
        } catch (Throwable $exception) {
            if ($this->monitor !== null) {
                $this->monitor->log($this->runId, 'warning', "{$this->entityLabel}-Mirror konnte nicht gelesen werden.", [
                    'entity_type' => $this->entityType,
                    'exception' => $exception->getMessage(),
                ]);
            }

            return ['enabled' => false, 'rows' => []];
        }

        return ['enabled' => true, 'rows' => $rows];
    }

    private function supportsMirrorComparison(): bool
    {
        if (!in_array($this->entityType, ['category', 'product', 'media', 'document'], true)) {
            return false;
        }

        return $this->mirrorCompareFields !== []
            || $this->mirrorTranslationHashField !== null
            || $this->mirrorAttributeHashField !== null
            || $this->mirrorSeoHashField !== null;
    }

    private function hasSuccessfulMirrorRefreshRun(): bool
    {
        // Accept the old run type so existing successful mirror runs remain valid.
        $stmt = $this->stageDb->prepare(
            "SELECT 1
             FROM `sync_runs`
             WHERE run_type IN ('xt_mirror', 'xt_snapshot')
               AND status = :status
             ORDER BY id DESC
             LIMIT 1"
        );
        $stmt->execute([
            ':status' => 'success',
        ]);

        return (bool) $stmt->fetchColumn();
    }

    private function fetchProductMirrorRows(): array
    {
        $categoryMap = $this->fetchProductMirrorCategoryMap();
        $translationHashes = $this->fetchProductMirrorTranslationHashes();
        $attributeHashes = $this->fetchProductMirrorAttributeHashes();
        $seoHashes = $this->fetchProductMirrorSeoHashes();

        $stmt = $this->stageDb->query(
            "SELECT
                p.external_id AS entity_id,
                p.products_id,
                p.products_model AS sku,
                p.products_ean AS ean,
                p.products_quantity AS stock,
                p.products_price AS price,
                p.products_weight AS weight,
                p.products_status AS online_flag,
                p.products_master_flag AS is_master,
                p.products_master_model AS master_sku
             FROM `xt_mirror_products` p
             WHERE p.external_id IS NOT NULL
             ORDER BY p.external_id ASC"
        );

        $rows = [];
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $entityId = $this->normalizeEntityId($row['entity_id'] ?? null);
            if ($entityId === null) {
                continue;
            }

            $productId = $this->normalizeEntityId($row['products_id'] ?? null);
            $rows[$entityId] = [
                'sku' => $this->normalizeScalar($row['sku'] ?? null),
                'ean' => $this->normalizeScalar($row['ean'] ?? null),
                'stock' => $this->normalizeDecimalValue($row['stock'] ?? null),
                'price' => $this->normalizeDecimalValue($row['price'] ?? null),
                'weight' => $this->normalizeDecimalValue($row['weight'] ?? null),
                'online_flag' => $this->normalizeScalar($row['online_flag'] ?? null),
                'is_master' => $this->normalizeScalar($row['is_master'] ?? null),
                'master_sku' => $this->normalizeScalar($row['master_sku'] ?? null),
                'category_afs_id' => $productId !== null ? ($categoryMap[$productId] ?? null) : null,
                'translation_hash' => $productId !== null ? ($translationHashes[$productId] ?? null) : null,
                'attribute_hash' => $productId !== null ? ($attributeHashes[$productId] ?? null) : null,
                'seo_hash' => $productId !== null ? ($seoHashes[$productId] ?? null) : null,
            ];
        }

        return $rows;
    }

    private function fetchCategoryMirrorRows(): array
    {
        $translationHashes = $this->fetchCategoryMirrorTranslationHashes();
        $seoHashes = $this->fetchCategoryMirrorSeoHashes();

        $stmt = $this->stageDb->query(
            "SELECT categories_id, external_id, parent_id, categories_image, categories_master_image, categories_status
             FROM `xt_mirror_categories`
             WHERE external_id IS NOT NULL
             ORDER BY categories_id ASC"
        );

        $categories = [];
        $externalByXtId = [];

        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $categoryId = $this->normalizeEntityId($row['categories_id'] ?? null);
            $externalId = $this->normalizeEntityId($row['external_id'] ?? null);

            if ($categoryId === null || $externalId === null) {
                continue;
            }

            $externalByXtId[$categoryId] = $externalId;
            $categories[$externalId] = [
                'xt_category_id' => $categoryId,
                'parent_xt_id' => $this->normalizeEntityId($row['parent_id'] ?? null),
                'external_id' => $this->normalizeScalar($row['external_id'] ?? null),
                'image' => $this->normalizeScalar($row['categories_image'] ?? null),
                'header_image' => $this->normalizeScalar($row['categories_master_image'] ?? null),
                'online_flag' => $this->normalizeScalar($row['categories_status'] ?? null),
                'translation_hash' => $translationHashes[$categoryId] ?? null,
                'seo_hash' => $seoHashes[$categoryId] ?? null,
            ];
        }

        foreach ($categories as &$row) {
            $parentXtId = $row['parent_xt_id'] ?? null;
            $row['parent_afs_id'] = $parentXtId !== null ? ($externalByXtId[$parentXtId] ?? null) : null;
            unset($row['parent_xt_id'], $row['xt_category_id']);
        }
        unset($row);

        return $categories;
    }

    private function fetchProductMirrorCategoryMap(): array
    {
        $categoryStmt = $this->stageDb->query(
            "SELECT categories_id, external_id
             FROM `xt_mirror_categories`
             WHERE categories_id IS NOT NULL"
        );

        $categoryExternalIds = [];
        while ($row = $categoryStmt->fetch(PDO::FETCH_ASSOC)) {
            $categoryId = $this->normalizeEntityId($row['categories_id'] ?? null);
            if ($categoryId === null) {
                continue;
            }

            $categoryExternalIds[$categoryId] = $this->normalizeScalar($row['external_id'] ?? null);
        }

        $linkStmt = $this->stageDb->query(
            "SELECT products_id, categories_id
             FROM `xt_mirror_products_to_categories`
             ORDER BY row_id ASC"
        );

        $categoryMap = [];
        while ($row = $linkStmt->fetch(PDO::FETCH_ASSOC)) {
            $productId = $this->normalizeEntityId($row['products_id'] ?? null);
            $categoryId = $this->normalizeEntityId($row['categories_id'] ?? null);
            if ($productId === null || $categoryId === null || array_key_exists($productId, $categoryMap)) {
                continue;
            }

            $categoryMap[$productId] = $categoryExternalIds[$categoryId] ?? null;
        }

        return $categoryMap;
    }

    private function fetchProductMirrorTranslationHashes(): array
    {
        $stmt = $this->stageDb->query(
            "SELECT products_id, language_code, products_name, products_description, products_short_description
             FROM `xt_mirror_products_description`
             ORDER BY products_id ASC, language_code ASC"
        );

        $grouped = [];
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $productId = $this->normalizeEntityId($row['products_id'] ?? null);
            $languageCode = $this->normalizeString($row['language_code'] ?? null);
            if ($productId === null || $languageCode === null || !in_array($languageCode, ['de', 'en', 'fr', 'nl'], true)) {
                continue;
            }

            $grouped[$productId][] = [
                'language_code' => $languageCode,
                'name' => $this->normalizeScalar($row['products_name'] ?? null),
                'description' => $this->normalizeScalar($row['products_description'] ?? null),
                'short_description' => $this->normalizeScalar($row['products_short_description'] ?? null),
            ];
        }

        return $this->hashGroupedComparableRows($grouped);
    }

    private function fetchProductMirrorSeoHashes(): array
    {
        $stmt = $this->stageDb->query(
            "SELECT link_id, language_code, meta_title, meta_description
             FROM `xt_mirror_seo_url`
             WHERE link_type = 1
             ORDER BY link_id ASC, language_code ASC"
        );

        $grouped = [];
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $productId = $this->normalizeEntityId($row['link_id'] ?? null);
            $languageCode = $this->normalizeString($row['language_code'] ?? null);
            if ($productId === null || $languageCode === null || !in_array($languageCode, ['de', 'en', 'fr', 'nl'], true)) {
                continue;
            }

            $grouped[$productId][] = [
                'language_code' => $languageCode,
                'meta_title' => $this->normalizeScalar($row['meta_title'] ?? null),
                'meta_description' => $this->normalizeScalar($row['meta_description'] ?? null),
            ];
        }

        return $this->hashGroupedComparableRows($grouped);
    }

    private function fetchCategoryMirrorTranslationHashes(): array
    {
        $stmt = $this->stageDb->query(
            "SELECT categories_id, language_code, categories_name, categories_description
             FROM `xt_mirror_categories_description`
             ORDER BY categories_id ASC, language_code ASC"
        );

        $grouped = [];
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $categoryId = $this->normalizeEntityId($row['categories_id'] ?? null);
            $languageCode = $this->normalizeString($row['language_code'] ?? null);
            if ($categoryId === null || $languageCode === null || !in_array($languageCode, ['de', 'en', 'fr', 'nl'], true)) {
                continue;
            }

            $grouped[$categoryId][] = [
                'language_code' => $languageCode,
                'name' => $this->normalizeScalar($row['categories_name'] ?? null),
                'description' => $this->normalizeScalar($row['categories_description'] ?? null),
                'short_description' => null,
            ];
        }

        return $this->hashGroupedComparableRows($grouped);
    }

    private function fetchCategoryMirrorSeoHashes(): array
    {
        $stmt = $this->stageDb->query(
            "SELECT link_id, language_code, meta_title, meta_description
             FROM `xt_mirror_seo_url`
             WHERE link_type = 2
             ORDER BY link_id ASC, language_code ASC"
        );

        $grouped = [];
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $categoryId = $this->normalizeEntityId($row['link_id'] ?? null);
            $languageCode = $this->normalizeString($row['language_code'] ?? null);
            if ($categoryId === null || $languageCode === null || !in_array($languageCode, ['de', 'en', 'fr', 'nl'], true)) {
                continue;
            }

            $grouped[$categoryId][] = [
                'language_code' => $languageCode,
                'meta_title' => $this->normalizeScalar($row['meta_title'] ?? null),
                'meta_description' => $this->normalizeScalar($row['meta_description'] ?? null),
            ];
        }

        return $this->hashGroupedComparableRows($grouped);
    }

    private function fetchProductMirrorAttributeHashes(): array
    {
        $stmt = $this->stageDb->query(
            "SELECT
                relation.products_id,
                child_description.language_code,
                COALESCE(parent_attribute.sort_order, attribute.sort_order) AS sort_order,
                parent_description.attributes_name AS parent_attributes_name,
                child_description.attributes_name AS child_attributes_name,
                child_description.attributes_desc AS child_attributes_desc
             FROM `xt_mirror_plg_products_to_attributes` relation
             INNER JOIN `xt_mirror_plg_products_attributes` attribute
                ON attribute.attributes_id = relation.attributes_id
             INNER JOIN `xt_mirror_plg_products_attributes_description` child_description
                ON child_description.attributes_id = relation.attributes_id
             LEFT JOIN `xt_mirror_plg_products_attributes` parent_attribute
                ON parent_attribute.attributes_id = COALESCE(NULLIF(relation.attributes_parent_id, 0), attribute.attributes_parent)
             LEFT JOIN `xt_mirror_plg_products_attributes_description` parent_description
                ON parent_description.attributes_id = parent_attribute.attributes_id
               AND parent_description.language_code = child_description.language_code
             ORDER BY relation.products_id ASC, child_description.language_code ASC, COALESCE(parent_attribute.sort_order, attribute.sort_order) ASC, relation.attributes_id ASC"
        );

        $grouped = [];
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $productId = $this->normalizeEntityId($row['products_id'] ?? null);
            $languageCode = $this->normalizeString($row['language_code'] ?? null);
            if ($productId === null || $languageCode === null || !in_array($languageCode, ['de', 'en', 'fr', 'nl'], true)) {
                continue;
            }

            $attributeName = $this->normalizeScalar($row['parent_attributes_name'] ?? null);
            $attributeValue = $this->normalizeScalar($row['child_attributes_name'] ?? null);

            if ($attributeName === null && $attributeValue === null) {
                $attributeName = $this->normalizeScalar($row['child_attributes_name'] ?? null);
                $attributeValue = $this->normalizeScalar($row['child_attributes_desc'] ?? null);
            }

            if ($attributeName === null && $attributeValue === null) {
                continue;
            }

            $grouped[$productId][] = [
                'language_code' => $languageCode,
                'sort_order' => isset($row['sort_order']) ? (int) $row['sort_order'] : null,
                'attribute_name' => $attributeName,
                'attribute_value' => $attributeValue,
            ];
        }

        return $this->hashGroupedComparableRows($grouped);
    }

    private function fetchMediaMirrorRows(): array
    {
        $stmt = $this->stageDb->query(
            "SELECT
                media.external_id AS entity_id,
                product.external_id AS afs_artikel_id,
                media.file AS file_name,
                COALESCE(media.type, link.type) AS media_type,
                link.sort_order
             FROM `xt_mirror_media_link` link
             INNER JOIN `xt_mirror_media` media ON media.id = link.m_id
             LEFT JOIN `xt_mirror_products` product ON product.products_id = link.link_id
             WHERE (link.class IS NULL OR link.class = 'product')
               AND link.type = 'images'
               AND media.external_id IS NOT NULL
               AND product.external_id IS NOT NULL
             ORDER BY media.external_id ASC"
        );

        $rows = [];
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $entityId = $this->normalizeEntityId($row['entity_id'] ?? null);
            if ($entityId === null) {
                continue;
            }

            $rows[$entityId] = [
                'media_external_id' => $this->normalizeScalar($row['entity_id'] ?? null),
                'afs_artikel_id' => $this->normalizeScalar($row['afs_artikel_id'] ?? null),
                'file_name' => $this->normalizeScalar($row['file_name'] ?? null),
                'media_type' => $this->normalizeScalar($row['media_type'] ?? null),
                'sort_order' => $this->normalizeScalar($row['sort_order'] ?? null),
            ];
        }

        return $rows;
    }

    private function fetchDocumentMirrorRows(): array
    {
        $stmt = $this->stageDb->query(
            "SELECT
                media.external_id AS entity_id,
                product.external_id AS afs_artikel_id,
                media.file AS file_name,
                COALESCE(media.type, link.type) AS document_type,
                link.sort_order
             FROM `xt_mirror_media_link` link
             INNER JOIN `xt_mirror_media` media ON media.id = link.m_id
             LEFT JOIN `xt_mirror_products` product ON product.products_id = link.link_id
             WHERE (link.class IS NULL OR link.class = 'product')
               AND link.type = 'files'
               AND media.external_id IS NOT NULL
               AND product.external_id IS NOT NULL
             ORDER BY media.external_id ASC"
        );

        $rows = [];
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $entityId = $this->normalizeEntityId($row['entity_id'] ?? null);
            if ($entityId === null) {
                continue;
            }

            $rows[$entityId] = [
                'afs_document_id' => $this->normalizeScalar($row['entity_id'] ?? null),
                'afs_artikel_id' => $this->normalizeScalar($row['afs_artikel_id'] ?? null),
                'file_name' => $this->normalizeScalar($row['file_name'] ?? null),
                'document_type' => $this->normalizeScalar($row['document_type'] ?? null),
                'sort_order' => $this->normalizeScalar($row['sort_order'] ?? null),
            ];
        }

        return $rows;
    }

    private function buildPayloadData(array $entity, array $translations, array $attributes): array
    {
        if ($this->payloadFields !== []) {
            $payload = [];

            foreach ($this->payloadFields as $field) {
                $payload[$field] = $this->normalizeScalar($entity[$field] ?? null);
            }

            return $payload;
        }

        $entityPayload = [];
        foreach ($this->hashFields as $field) {
            $entityPayload[$field] = $this->normalizeScalar($entity[$field] ?? null);
        }

        $entityPayloadKey = $this->entityType === 'product' ? 'product' : $this->entityType;

        return [
            $entityPayloadKey => $entityPayload,
            'translations' => $translations,
            'attributes' => $attributes,
        ];
    }

    private function buildHash(array $payload): string
    {
        $json = json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        if ($json === false) {
            throw new RuntimeException("{$this->entityLabel}-Payload konnte nicht serialisiert werden.");
        }

        return hash('sha256', $json);
    }

    private function mirrorDecision(array $payloadData, ?array $mirrorRow, bool $mirrorEnabled): array
    {
        if (!$mirrorEnabled) {
            return ['enabled' => false, 'matched' => false, 'exists' => false];
        }

        if ($mirrorRow === null) {
            return ['enabled' => true, 'matched' => false, 'exists' => false];
        }

        foreach ($this->mirrorCompareFields as $payloadPath => $mirrorField) {
            if (!is_string($payloadPath) || !is_string($mirrorField)) {
                continue;
            }

            $payloadValue = $this->normalizeScalar($this->extractPayloadPath($payloadData, $payloadPath));
            $mirrorValue = $this->normalizeScalar($mirrorRow[$mirrorField] ?? null);

            if (!$this->valuesEqual($payloadValue, $mirrorValue)) {
                return ['enabled' => true, 'matched' => false, 'exists' => true];
            }
        }

        if ($this->mirrorTranslationHashField !== null) {
            if (!$this->valuesEqual(
                $this->buildStageTranslationHash($payloadData['translations'] ?? []),
                $this->normalizeScalar($mirrorRow[$this->mirrorTranslationHashField] ?? null)
            )) {
                return ['enabled' => true, 'matched' => false, 'exists' => true];
            }
        }

        if ($this->mirrorAttributeHashField !== null) {
            if (!$this->valuesEqual(
                $this->buildStageAttributeHash($payloadData['attributes'] ?? []),
                $this->normalizeScalar($mirrorRow[$this->mirrorAttributeHashField] ?? null)
            )) {
                return ['enabled' => true, 'matched' => false, 'exists' => true];
            }
        }

        if ($this->mirrorSeoHashField !== null) {
            if (!$this->valuesEqual(
                $this->buildStageSeoHash($payloadData['translations'] ?? []),
                $this->normalizeScalar($mirrorRow[$this->mirrorSeoHashField] ?? null)
            )) {
                return ['enabled' => true, 'matched' => false, 'exists' => true];
            }
        }

        return ['enabled' => true, 'matched' => true, 'exists' => true];
    }

    private function nextAction(?array $state, string $hash, array $mirrorDecision): ?string
    {
        if (!(bool) ($mirrorDecision['enabled'] ?? false)) {
            if ($state === null) {
                return 'insert';
            }

            if (($state[$this->stateHashField] ?? null) !== $hash) {
                return 'update';
            }

            return null;
        }

        if (!(bool) ($mirrorDecision['exists'] ?? false)) {
            return 'insert';
        }

        if ($state === null) {
            return 'update';
        }

        if (($state[$this->stateHashField] ?? null) !== $hash) {
            return 'update';
        }

        return 'update';
    }

    private function enqueue(string $entityId, string $action, array $payloadData, string $hash): bool
    {
        $payload = [
            'hash' => $hash,
            'data' => $payloadData,
        ];

        return $this->scheduleQueueEntry($entityId, $action, $payload);
    }

    private function enqueueRemovedUpdate(string $entityId): bool
    {
        $payload = [
            'hash' => $this->removedHash,
            'data' => $this->removedPayload,
        ];

        return $this->scheduleQueueEntry($entityId, 'update', $payload);
    }

    private function findMirrorEntityRemovals(
        array $mirrorRows,
        array $currentEntityIds,
        array $states
    ): array {
        $removals = [];

        foreach ($mirrorRows as $entityId => $mirrorRow) {
            if (isset($currentEntityIds[$entityId]) || isset($states[$entityId])) {
                continue;
            }

            $removals[] = (string) $entityId;
        }

        return $removals;
    }

    private function fetchExistingQueueEntities(): array
    {
        $restoreBufferedQuery = null;

        if (defined('PDO::MYSQL_ATTR_USE_BUFFERED_QUERY')) {
            $restoreBufferedQuery = (bool) $this->stageDb->getAttribute(PDO::MYSQL_ATTR_USE_BUFFERED_QUERY);
            $this->stageDb->setAttribute(PDO::MYSQL_ATTR_USE_BUFFERED_QUERY, false);
        }

        $stmt = $this->stageDb->prepare(
            "SELECT entity_id
             FROM `{$this->queueTable}`
             WHERE entity_type = :entity_type
               AND status IN ('pending', 'processing')"
        );
        try {
            $stmt->execute([
                ':entity_type' => $this->entityType,
            ]);

            $entities = [];

            while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
                $entityId = $this->normalizeEntityId($row['entity_id'] ?? null);
                if ($entityId === null) {
                    continue;
                }

                $entities[$entityId] = true;
            }
        } finally {
            $stmt->closeCursor();

            if ($restoreBufferedQuery !== null) {
                $this->stageDb->setAttribute(PDO::MYSQL_ATTR_USE_BUFFERED_QUERY, $restoreBufferedQuery);
            }
        }

        return $entities;
    }

    private function fetchQueueCounts(): array
    {
        $stmt = $this->stageDb->prepare(
            "SELECT status, COUNT(*) AS item_count
             FROM `{$this->queueTable}`
             WHERE entity_type = :entity_type
             GROUP BY status"
        );
        $stmt->execute([
            ':entity_type' => $this->entityType,
        ]);

        $counts = [
            'pending' => 0,
            'processing' => 0,
            'done' => 0,
            'error' => 0,
        ];

        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $status = (string) ($row['status'] ?? '');
            if (!array_key_exists($status, $counts)) {
                continue;
            }

            $counts[$status] = (int) ($row['item_count'] ?? 0);
        }

        return $counts;
    }

    private function deltaResultReason(array $stats): string
    {
        if ((int) ($stats['errors'] ?? 0) > 0) {
            return 'errors_detected';
        }

        if ((int) ($stats['queue_created'] ?? 0) > 0) {
            return 'queue_entries_created';
        }

        if ((int) ($stats['deduplicated'] ?? 0) > 0) {
            return 'existing_pending_or_processing_entries';
        }

        return 'no_changes_detected';
    }

    private function scheduleQueueEntry(string $entityId, string $action, array $payload): bool
    {
        $payloadJson = json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        if ($payloadJson === false) {
            throw new RuntimeException('Export-Queue-Payload konnte nicht serialisiert werden.');
        }

        if (isset($this->pendingQueueEntities[$entityId])) {
            return false;
        }

        $this->pendingQueueEntities[$entityId] = true;
        $this->queuedEntries[] = [
            'entity_id' => $entityId,
            'action' => $action,
            'payload' => $payloadJson,
        ];

        if (count($this->queuedEntries) >= $this->queueInsertBatchSize) {
            $this->flushQueuedEntries();
        }

        return true;
    }

    private function flushQueuedEntries(): void
    {
        if ($this->queuedEntries === []) {
            return;
        }

        $entries = $this->queuedEntries;
        $this->queuedEntries = [];

        try {
            $this->insertQueuedEntriesBatch($entries);
        } catch (Throwable) {
            foreach ($entries as $entry) {
                $this->insertQueuedEntry($entry);
            }
        }
    }

    private function insertQueuedEntriesBatch(array $entries): void
    {
        $valueSql = [];
        $params = [];

        foreach ($entries as $index => $entry) {
            $valueSql[] = "(:entity_type_{$index}, :entity_id_{$index}, :action_{$index}, :payload_{$index}, :status_{$index}, NOW())";
            $params[":entity_type_{$index}"] = $this->entityType;
            $params[":entity_id_{$index}"] = (string) $entry['entity_id'];
            $params[":action_{$index}"] = (string) $entry['action'];
            $params[":payload_{$index}"] = (string) $entry['payload'];
            $params[":status_{$index}"] = 'pending';
        }

        $sql = sprintf(
            'INSERT INTO `%s` (entity_type, entity_id, action, payload, status, created_at) VALUES %s',
            $this->queueTable,
            implode(', ', $valueSql)
        );

        $stmt = $this->stageDb->prepare($sql);
        $stmt->execute($params);
    }

    private function insertQueuedEntry(array $entry): void
    {
        $stmt = $this->stageDb->prepare(
            "INSERT INTO `{$this->queueTable}` (entity_type, entity_id, action, payload, status, created_at)
             VALUES (:entity_type, :entity_id, :action, :payload, :status, NOW())"
        );
        $stmt->execute([
            ':entity_type' => $this->entityType,
            ':entity_id' => (string) $entry['entity_id'],
            ':action' => (string) $entry['action'],
            ':payload' => (string) $entry['payload'],
            ':status' => 'pending',
        ]);
    }

    private function logRecordError(string $entityId, string $message, Throwable $exception): void
    {
        if ($this->monitor === null) {
            return;
        }

        $context = [
            'source' => $this->monitorSource,
            'record_identifier' => $entityId,
            'exception' => $exception->getMessage(),
        ];

        $this->monitor->log($this->runId, 'error', $message, $context);
        $this->monitor->error($this->runId, $message, $context);
    }

    private function normalizeScalar(mixed $value): mixed
    {
        if ($value === null) {
            return null;
        }

        if (is_bool($value)) {
            return $value;
        }

        if (is_int($value) || is_float($value)) {
            return $value + 0;
        }

        $stringValue = trim((string) $value);
        return $stringValue === '' ? null : $stringValue;
    }

    private function extractPayloadPath(array $payload, string $path): mixed
    {
        $segments = array_values(array_filter(explode('.', $path), static fn (string $segment): bool => $segment !== ''));
        $current = $payload;

        foreach ($segments as $segment) {
            if (!is_array($current) || !array_key_exists($segment, $current)) {
                return null;
            }

            $current = $current[$segment];
        }

        return $current;
    }

    private function buildStageTranslationHash(mixed $translations): ?string
    {
        if (!is_array($translations) || $translations === []) {
            return null;
        }

        $normalized = [];
        foreach ($translations as $translation) {
            if (!is_array($translation)) {
                continue;
            }

            $languageCode = $this->normalizeString($translation['language_code'] ?? null);
            if ($languageCode === null || !in_array($languageCode, ['de', 'en', 'fr', 'nl'], true)) {
                continue;
            }

            $normalized[] = [
                'language_code' => $languageCode,
                'name' => $this->normalizeScalar($translation['name'] ?? null),
                'description' => $this->normalizeScalar($this->combineTranslationDescription(
                    $translation['description'] ?? null,
                    $translation['technical_data_html'] ?? null
                )),
                'short_description' => $this->normalizeScalar($translation['short_description'] ?? null),
            ];
        }

        return $this->hashComparableRows($normalized);
    }

    private function combineTranslationDescription(mixed $description, mixed $technicalDataHtml): ?string
    {
        $parts = [];

        foreach ([$description, $technicalDataHtml] as $value) {
            $normalized = $this->normalizeScalar($value);
            if ($normalized === null || $normalized === '') {
                continue;
            }

            $parts[] = $normalized;
        }

        if ($parts === []) {
            return null;
        }

        return implode("\n\n", $parts);
    }

    private function buildStageSeoHash(mixed $translations): ?string
    {
        if (!is_array($translations) || $translations === []) {
            return null;
        }

        $normalized = [];
        foreach ($translations as $translation) {
            if (!is_array($translation)) {
                continue;
            }

            $languageCode = $this->normalizeString($translation['language_code'] ?? null);
            if ($languageCode === null || !in_array($languageCode, ['de', 'en', 'fr', 'nl'], true)) {
                continue;
            }

            $normalized[] = [
                'language_code' => $languageCode,
                'meta_title' => $this->normalizeScalar($translation['meta_title'] ?? null),
                'meta_description' => $this->normalizeScalar($translation['meta_description'] ?? null),
            ];
        }

        return $this->hashComparableRows($normalized);
    }

    private function buildStageAttributeHash(mixed $attributes): ?string
    {
        if (!is_array($attributes) || $attributes === []) {
            return null;
        }

        $normalized = [];
        foreach ($attributes as $attribute) {
            if (!is_array($attribute)) {
                continue;
            }

            $languageCode = $this->normalizeString($attribute['language_code'] ?? null);
            if ($languageCode === null || !in_array($languageCode, ['de', 'en', 'fr', 'nl'], true)) {
                continue;
            }

            $normalized[] = [
                'language_code' => $languageCode,
                'sort_order' => isset($attribute['sort_order']) ? (int) $attribute['sort_order'] : null,
                'attribute_name' => $this->normalizeScalar($attribute['attribute_name'] ?? null),
                'attribute_value' => $this->normalizeScalar($attribute['attribute_value'] ?? null),
            ];
        }

        return $this->hashComparableRows($normalized);
    }

    private function hashComparableRows(array $rows): ?string
    {
        if ($rows === []) {
            return null;
        }

        usort($rows, static function (array $left, array $right): int {
            return strcmp(
                json_encode($left, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?: '',
                json_encode($right, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?: ''
            );
        });

        return hash('sha256', json_encode($rows, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?: '[]');
    }

    private function hashGroupedComparableRows(array $groupedRows): array
    {
        $hashes = [];

        foreach ($groupedRows as $entityId => $rows) {
            if (!is_array($rows) || $rows === []) {
                continue;
            }

            $hash = $this->hashComparableRows($rows);
            if ($hash === null) {
                continue;
            }

            $hashes[(string) $entityId] = $hash;
        }

        return $hashes;
    }

    private function valuesEqual(mixed $left, mixed $right): bool
    {
        if ($left === null || $right === null) {
            return $left === $right;
        }

        return (string) $left === (string) $right;
    }

    private function normalizeString(mixed $value): ?string
    {
        $normalized = $this->normalizeScalar($value);
        if ($normalized === null) {
            return null;
        }

        return (string) $normalized;
    }

    private function normalizeOrderBy(mixed $value): array
    {
        $items = is_array($value) ? $value : [$value];
        $normalized = [];

        foreach ($items as $item) {
            if (!is_string($item)) {
                continue;
            }

            $item = trim($item);
            if ($item === '') {
                continue;
            }

            if (preg_match('/^(?<field>[a-z0-9_]+)(?:\s+(?<direction>ASC|DESC))?$/i', $item, $matches) !== 1) {
                continue;
            }

            $direction = strtoupper((string) ($matches['direction'] ?? 'ASC'));
            $normalized[] = sprintf('`%s` %s', $matches['field'], $direction);
        }

        return $normalized;
    }

    private function entityOrderBySql(): string
    {
        if ($this->entityOrderBy !== []) {
            return implode(', ', $this->entityOrderBy);
        }

        return sprintf('`%s` ASC', $this->identityField);
    }

    private function normalizeDecimalValue(mixed $value, int $scale = 4): ?string
    {
        $normalized = $this->normalizeScalar($value);
        if ($normalized === null) {
            return null;
        }

        if (!is_numeric((string) $normalized)) {
            return (string) $normalized;
        }

        return number_format((float) $normalized, $scale, '.', '');
    }

    private function normalizeEntityId(mixed $value): ?string
    {
        $normalized = $this->normalizeScalar($value);
        if ($normalized === null) {
            return null;
        }

        $entityId = trim((string) $normalized);

        return $entityId === '' ? null : $entityId;
    }
}
