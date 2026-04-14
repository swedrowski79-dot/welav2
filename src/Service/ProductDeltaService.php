<?php

class ProductDeltaService
{
    private string $entityType;
    private string $stageTable;
    private string $translationTable;
    private string $attributeTable;
    private string $stateTable;
    private string $identityField;
    private string $hashField;
    private string $stateHashField;
    private string $stateLastSeenField;
    private string $offlineHash;
    private array $hashFields;

    public function __construct(
        private PDO $stageDb,
        array $deltaConfig,
        private ?SyncMonitor $monitor = null,
        private ?int $runId = null
    ) {
        $config = $deltaConfig['product_export_queue'] ?? [];

        $this->entityType = (string) ($config['entity_type'] ?? 'product');
        $this->stageTable = (string) ($config['stage_table'] ?? 'stage_products');
        $this->translationTable = (string) ($config['translation_table'] ?? 'stage_product_translations');
        $this->attributeTable = (string) ($config['attribute_table'] ?? 'stage_attribute_translations');
        $this->stateTable = (string) ($config['state_table'] ?? 'product_export_state');
        $this->identityField = (string) ($config['identity_field'] ?? 'afs_artikel_id');
        $this->hashField = (string) ($config['hash_field'] ?? 'hash');
        $this->stateHashField = (string) ($config['state_hash_field'] ?? 'last_exported_hash');
        $this->stateLastSeenField = (string) ($config['state_last_seen_field'] ?? 'last_seen_at');
        $this->offlineHash = hash('sha256', (string) ($config['offline_hash_value'] ?? 'offline'));
        $this->hashFields = is_array($config['hash_fields'] ?? null) ? $config['hash_fields'] : [];
    }

    public function run(): array
    {
        if ($this->hashFields === []) {
            throw new RuntimeException('Delta config for product export queue is missing hash fields.');
        }

        $runTimestamp = (new DateTimeImmutable('now', new DateTimeZone('UTC')))->format('Y-m-d H:i:s');
        $products = $this->fetchProducts();
        $translations = $this->fetchTranslations();
        $attributes = $this->fetchAttributes();
        $states = $this->fetchStates();

        $stats = [
            'processed' => 0,
            'insert' => 0,
            'update' => 0,
            'offline' => 0,
            'unchanged' => 0,
            'errors' => 0,
        ];

        $currentEntityIds = [];
        $updateHashStmt = $this->stageDb->prepare(
            "UPDATE `{$this->stageTable}` SET `{$this->hashField}` = :hash WHERE `{$this->identityField}` = :entity_id"
        );
        $upsertStateStmt = $this->stageDb->prepare(
            "INSERT INTO `{$this->stateTable}` (`product_id`, `{$this->stateHashField}`, `{$this->stateLastSeenField}`)
             VALUES (:product_id, :hash, :last_seen_at)
             ON DUPLICATE KEY UPDATE
                `{$this->stateHashField}` = VALUES(`{$this->stateHashField}`),
                `{$this->stateLastSeenField}` = VALUES(`{$this->stateLastSeenField}`)"
        );
        $markOfflineStateStmt = $this->stageDb->prepare(
            "UPDATE `{$this->stateTable}`
             SET `{$this->stateHashField}` = :hash
             WHERE `product_id` = :product_id"
        );

        foreach ($products as $product) {
            $entityId = (int) ($product[$this->identityField] ?? 0);
            if ($entityId <= 0) {
                continue;
            }

            $currentEntityIds[$entityId] = true;
            $stats['processed']++;

            try {
                $payloadData = $this->buildPayloadData(
                    $product,
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
                    $this->enqueue($entityId, 'insert', $payloadData, $hash);
                    $stats['insert']++;
                } elseif (($state[$this->stateHashField] ?? null) !== $hash) {
                    $this->enqueue($entityId, 'update', $payloadData, $hash);
                    $stats['update']++;
                } else {
                    $stats['unchanged']++;
                }

                $upsertStateStmt->execute([
                    ':product_id' => $entityId,
                    ':hash' => $hash,
                    ':last_seen_at' => $runTimestamp,
                ]);
            } catch (Throwable $exception) {
                $stats['errors']++;
                $this->logRecordError(
                    $entityId,
                    'Produkt-Delta konnte nicht berechnet werden.',
                    $exception
                );
            }
        }

        foreach ($states as $entityId => $state) {
            if (isset($currentEntityIds[$entityId])) {
                continue;
            }

            if (($state[$this->stateHashField] ?? null) === $this->offlineHash) {
                continue;
            }

            try {
                $this->enqueueOfflineUpdate($entityId);
                $markOfflineStateStmt->execute([
                    ':hash' => $this->offlineHash,
                    ':product_id' => $entityId,
                ]);
                $stats['offline']++;
            } catch (Throwable $exception) {
                $stats['errors']++;
                $this->logRecordError(
                    $entityId,
                    'Produkt-Deaktivierung konnte nicht in die Export Queue geschrieben werden.',
                    $exception
                );
            }
        }

        $stats['changed'] = $stats['insert'] + $stats['update'] + $stats['offline'];

        if ($this->monitor !== null) {
            $this->monitor->log($this->runId, 'info', 'Produkt-Delta berechnet.', [
                'processed' => $stats['processed'],
                'changed' => $stats['changed'],
                'insert' => $stats['insert'],
                'update' => $stats['update'],
                'offline' => $stats['offline'],
                'errors' => $stats['errors'],
            ]);
        }

        return $stats;
    }

    private function fetchProducts(): array
    {
        $stmt = $this->stageDb->query("SELECT * FROM `{$this->stageTable}` ORDER BY `{$this->identityField}` ASC");
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    private function fetchTranslations(): array
    {
        $stmt = $this->stageDb->query(
            "SELECT * FROM `{$this->translationTable}` ORDER BY `{$this->identityField}` ASC, `language_code` ASC"
        );

        $translations = [];
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $entityId = (int) ($row[$this->identityField] ?? 0);
            if ($entityId <= 0) {
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
        $stmt = $this->stageDb->query(
            "SELECT * FROM `{$this->attributeTable}` ORDER BY `{$this->identityField}` ASC, `language_code` ASC, `sort_order` ASC"
        );

        $attributes = [];
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $entityId = (int) ($row[$this->identityField] ?? 0);
            if ($entityId <= 0) {
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
            "SELECT `product_id`, `{$this->stateHashField}`, `{$this->stateLastSeenField}`
             FROM `{$this->stateTable}`
             ORDER BY `product_id` ASC"
        );

        $states = [];
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $entityId = (int) ($row['product_id'] ?? 0);
            if ($entityId <= 0) {
                continue;
            }

            $states[$entityId] = $row;
        }

        return $states;
    }

    private function buildPayloadData(array $product, array $translations, array $attributes): array
    {
        $productPayload = [];
        foreach ($this->hashFields as $field) {
            $productPayload[$field] = $this->normalizeScalar($product[$field] ?? null);
        }

        return [
            'product' => $productPayload,
            'translations' => $translations,
            'attributes' => $attributes,
        ];
    }

    private function buildHash(array $payload): string
    {
        $json = json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        if ($json === false) {
            throw new RuntimeException('Produkt-Payload konnte nicht serialisiert werden.');
        }

        return hash('sha256', $json);
    }

    private function enqueue(int $entityId, string $action, array $payloadData, string $hash): void
    {
        $payload = [
            'hash' => $hash,
            'data' => $payloadData,
        ];

        $stmt = $this->stageDb->prepare(
            'INSERT INTO export_queue (entity_type, entity_id, action, payload, status, created_at)
             VALUES (:entity_type, :entity_id, :action, :payload, :status, NOW())'
        );
        $stmt->execute([
            ':entity_type' => $this->entityType,
            ':entity_id' => $entityId,
            ':action' => $action,
            ':payload' => json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
            ':status' => 'pending',
        ]);
    }

    private function enqueueOfflineUpdate(int $entityId): void
    {
        $stmt = $this->stageDb->prepare(
            'INSERT INTO export_queue (entity_type, entity_id, action, payload, status, created_at)
             VALUES (:entity_type, :entity_id, :action, :payload, :status, NOW())'
        );
        $stmt->execute([
            ':entity_type' => $this->entityType,
            ':entity_id' => $entityId,
            ':action' => 'update',
            ':payload' => '{"online":0}',
            ':status' => 'pending',
        ]);
    }

    private function logRecordError(int $entityId, string $message, Throwable $exception): void
    {
        if ($this->monitor === null) {
            return;
        }

        $context = [
            'source' => 'delta_products',
            'record_identifier' => (string) $entityId,
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
}
