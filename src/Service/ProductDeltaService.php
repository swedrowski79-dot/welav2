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
        $markRemovedStateStmt = $this->stageDb->prepare(
            "UPDATE `{$this->stateTable}`
             SET `{$this->stateHashField}` = :hash
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
                if ($state === null) {
                    if ($this->enqueue($entityId, 'insert', $payloadData, $hash)) {
                        $stats['insert']++;
                    } else {
                        $stats['deduplicated']++;
                    }
                } elseif (($state[$this->stateHashField] ?? null) !== $hash) {
                    if ($this->enqueue($entityId, 'update', $payloadData, $hash)) {
                        $stats['update']++;
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

            try {
                if ($this->enqueueRemovedUpdate($entityId)) {
                    $markRemovedStateStmt->execute([
                        ':hash' => $this->removedHash,
                        ':state_id' => $entityId,
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
        $stats['changed'] = $stats['insert'] + $stats['update'] + $stats['removed'];
        $stats['entity_type'] = $this->entityType;
        $stats['config_key'] = $this->configKey;

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
    }

    private function normalizeTableName(mixed $value): ?string
    {
        if (!is_string($value)) {
            return null;
        }

        $table = trim($value);

        return $table === '' ? null : $table;
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
