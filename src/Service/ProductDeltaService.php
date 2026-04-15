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
    private ?string $snapshotTable = null;
    private ?string $snapshotIdentityField = null;
    private array $snapshotCompareFields = [];
    private ?string $snapshotTranslationHashField = null;
    private ?string $snapshotAttributeHashField = null;
    private ?string $snapshotSeoHashField = null;
    private bool $snapshotRequireSuccessRun = true;
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
        $snapshotContext = $this->fetchSnapshots();
        $snapshots = $snapshotContext['rows'];
        $snapshotEnabled = (bool) ($snapshotContext['enabled'] ?? false);
        $initialQueueCounts = $this->fetchQueueCounts();
        $this->pendingQueueEntities = $this->fetchExistingQueueEntities();
        $this->queuedEntries = [];

        $stats = [
            'processed' => 0,
            'insert' => 0,
            'update' => 0,
            'removed' => 0,
            'unchanged' => 0,
            'deduplicated' => 0,
            'errors' => 0,
            'snapshot_matched' => 0,
            'snapshot_missing' => 0,
            'snapshot_mismatched' => 0,
            'snapshot_removed_skipped' => 0,
            'snapshot_enabled' => $snapshotEnabled,
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
                $snapshotDecision = $this->snapshotDecision($entityId, $payloadData, $snapshots[$entityId] ?? null, $snapshotEnabled);

                if ($snapshotEnabled) {
                    if ($snapshotDecision['matched']) {
                        $stats['snapshot_matched']++;
                    } elseif ($snapshotDecision['exists']) {
                        $stats['snapshot_mismatched']++;
                    } else {
                        $stats['snapshot_missing']++;
                    }
                }

                if ($snapshotDecision['matched']) {
                    $stats['unchanged']++;
                    $syncStateStmt->execute([
                        ':state_id' => $entityId,
                        ':hash' => $hash,
                        ':last_seen_at' => $runTimestamp,
                    ]);
                } else {
                    $action = $this->nextAction($state, $hash, $snapshotDecision);

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

            if ($snapshotEnabled && !isset($snapshots[$entityId])) {
                $syncStateStmt->execute([
                    ':state_id' => $entityId,
                    ':hash' => $this->removedHash,
                    ':last_seen_at' => $runTimestamp,
                ]);
                $stats['snapshot_removed_skipped']++;
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
                'snapshot_enabled' => $stats['snapshot_enabled'],
                'snapshot_matched' => $stats['snapshot_matched'],
                'snapshot_missing' => $stats['snapshot_missing'],
                'snapshot_mismatched' => $stats['snapshot_mismatched'],
                'snapshot_removed_skipped' => $stats['snapshot_removed_skipped'],
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
                'snapshot_enabled' => $stats['snapshot_enabled'],
                'snapshot_matched' => $stats['snapshot_matched'],
                'snapshot_missing' => $stats['snapshot_missing'],
                'snapshot_mismatched' => $stats['snapshot_mismatched'],
                'snapshot_removed_skipped' => $stats['snapshot_removed_skipped'],
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
        $this->snapshotTable = $this->normalizeTableName($config['snapshot_table'] ?? null);
        $this->snapshotIdentityField = is_string($config['snapshot_identity_field'] ?? null) ? trim((string) $config['snapshot_identity_field']) : null;
        $this->snapshotCompareFields = is_array($config['snapshot_compare_fields'] ?? null) ? $config['snapshot_compare_fields'] : [];
        $this->snapshotTranslationHashField = $this->normalizeFieldName($config['snapshot_translation_hash_field'] ?? null);
        $this->snapshotAttributeHashField = $this->normalizeFieldName($config['snapshot_attribute_hash_field'] ?? null);
        $this->snapshotSeoHashField = $this->normalizeFieldName($config['snapshot_seo_hash_field'] ?? null);
        $this->snapshotRequireSuccessRun = !array_key_exists('snapshot_require_success_run', $config)
            || (bool) $config['snapshot_require_success_run'];
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
        $stmt = $this->stageDb->query("SELECT * FROM `{$this->stageTable}` ORDER BY `{$this->identityField}` ASC");
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

    private function fetchSnapshots(): array
    {
        if ($this->snapshotTable === null || $this->snapshotIdentityField === null) {
            return ['enabled' => false, 'rows' => []];
        }

        if ($this->snapshotRequireSuccessRun && !$this->hasSuccessfulSnapshotRun()) {
            return ['enabled' => false, 'rows' => []];
        }

        try {
            $stmt = $this->stageDb->query(
                "SELECT * FROM `{$this->snapshotTable}` ORDER BY `{$this->snapshotIdentityField}` ASC"
            );
        } catch (Throwable $exception) {
            if ($this->monitor !== null) {
                $this->monitor->log($this->runId, 'warning', "{$this->entityLabel}-Snapshot konnte nicht gelesen werden.", [
                    'entity_type' => $this->entityType,
                    'table' => $this->snapshotTable,
                    'exception' => $exception->getMessage(),
                ]);
            }

            return ['enabled' => false, 'rows' => []];
        }

        $rows = [];
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $entityId = $this->normalizeEntityId($row[$this->snapshotIdentityField] ?? null);
            if ($entityId === null) {
                continue;
            }

            $rows[$entityId] = $row;
        }

        return ['enabled' => true, 'rows' => $rows];
    }

    private function hasSuccessfulSnapshotRun(): bool
    {
        $stmt = $this->stageDb->prepare(
            "SELECT 1
             FROM `sync_runs`
             WHERE run_type = :run_type
               AND status = :status
             ORDER BY id DESC
             LIMIT 1"
        );
        $stmt->execute([
            ':run_type' => 'xt_snapshot',
            ':status' => 'success',
        ]);

        return (bool) $stmt->fetchColumn();
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

        return [
            'product' => $entityPayload,
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

    private function snapshotDecision(string $entityId, array $payloadData, ?array $snapshotRow, bool $snapshotEnabled): array
    {
        if (!$snapshotEnabled) {
            return ['enabled' => false, 'matched' => false, 'exists' => false];
        }

        if ($snapshotRow === null) {
            return ['enabled' => true, 'matched' => false, 'exists' => false];
        }

        foreach ($this->snapshotCompareFields as $payloadPath => $snapshotField) {
            if (!is_string($payloadPath) || !is_string($snapshotField)) {
                continue;
            }

            $payloadValue = $this->normalizeScalar($this->extractPayloadPath($payloadData, $payloadPath));
            $snapshotValue = $this->normalizeScalar($snapshotRow[$snapshotField] ?? null);

            if (!$this->valuesEqual($payloadValue, $snapshotValue)) {
                return ['enabled' => true, 'matched' => false, 'exists' => true];
            }
        }

        if ($this->snapshotTranslationHashField !== null) {
            if (!$this->valuesEqual(
                $this->buildStageTranslationHash($payloadData['translations'] ?? []),
                $this->normalizeScalar($snapshotRow[$this->snapshotTranslationHashField] ?? null)
            )) {
                return ['enabled' => true, 'matched' => false, 'exists' => true];
            }
        }

        if ($this->snapshotAttributeHashField !== null) {
            if (!$this->valuesEqual(
                $this->buildStageAttributeHash($payloadData['attributes'] ?? []),
                $this->normalizeScalar($snapshotRow[$this->snapshotAttributeHashField] ?? null)
            )) {
                return ['enabled' => true, 'matched' => false, 'exists' => true];
            }
        }

        if ($this->snapshotSeoHashField !== null) {
            if (!$this->valuesEqual(
                $this->buildStageSeoHash($payloadData['translations'] ?? []),
                $this->normalizeScalar($snapshotRow[$this->snapshotSeoHashField] ?? null)
            )) {
                return ['enabled' => true, 'matched' => false, 'exists' => true];
            }
        }

        return ['enabled' => true, 'matched' => true, 'exists' => true];
    }

    private function nextAction(?array $state, string $hash, array $snapshotDecision): ?string
    {
        if (!(bool) ($snapshotDecision['enabled'] ?? false)) {
            if ($state === null) {
                return 'insert';
            }

            if (($state[$this->stateHashField] ?? null) !== $hash) {
                return 'update';
            }

            return null;
        }

        if (!$snapshotDecision['exists']) {
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
                'description' => $this->normalizeScalar($translation['description'] ?? null),
                'short_description' => $this->normalizeScalar($translation['short_description'] ?? null),
            ];
        }

        return $this->hashComparableRows($normalized);
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
