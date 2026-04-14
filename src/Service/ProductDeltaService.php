<?php

class ProductDeltaService
{
    private string $entityType;
    private string $stageTable;
    private string $translationTable;
    private string $attributeTable;
    private string $identityField;
    private string $hashField;
    private string $lastExportedHashField;
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
        $this->identityField = (string) ($config['identity_field'] ?? 'afs_artikel_id');
        $this->hashField = (string) ($config['hash_field'] ?? 'hash');
        $this->lastExportedHashField = (string) ($config['last_exported_hash_field'] ?? 'last_exported_hash');
        $this->hashFields = is_array($config['hash_fields'] ?? null) ? $config['hash_fields'] : [];
    }

    public function run(): array
    {
        if ($this->hashFields === []) {
            throw new RuntimeException('Delta config for product export queue is missing hash fields.');
        }

        $products = $this->fetchProducts();
        $translations = $this->fetchTranslations();
        $attributes = $this->fetchAttributes();
        $pendingEntries = $this->fetchLatestPendingEntries();
        $knownExportStates = $this->fetchKnownExportStates();

        $stats = [
            'processed' => 0,
            'insert' => 0,
            'update' => 0,
            'delete' => 0,
            'unchanged' => 0,
            'errors' => 0,
        ];

        $currentEntityIds = [];
        $updateHashStmt = $this->stageDb->prepare(
            "UPDATE `{$this->stageTable}` SET `{$this->hashField}` = :hash WHERE `{$this->identityField}` = :entity_id"
        );

        foreach ($products as $product) {
            $entityId = (int) ($product[$this->identityField] ?? 0);
            if ($entityId <= 0) {
                continue;
            }

            $currentEntityIds[$entityId] = true;
            $stats['processed']++;

            try {
                $payload = $this->buildPayload(
                    $product,
                    $translations[$entityId] ?? [],
                    $attributes[$entityId] ?? []
                );
                $hash = $this->buildHash($payload);

                $updateHashStmt->execute([
                    ':hash' => $hash,
                    ':entity_id' => $entityId,
                ]);

                $lastExportedHash = $this->normalizeString($product[$this->lastExportedHashField] ?? null);
                if ($hash === $lastExportedHash) {
                    $stats['unchanged']++;
                    continue;
                }

                $action = $lastExportedHash === null ? 'insert' : 'update';

                if ($this->hasMatchingPendingEntry($pendingEntries[$entityId] ?? null, $action, $hash)) {
                    $stats['unchanged']++;
                    continue;
                }

                $this->enqueue($entityId, $action, [
                    'entity_type' => $this->entityType,
                    'entity_id' => $entityId,
                    'hash' => $hash,
                    'last_exported_hash' => $lastExportedHash,
                    'data' => $payload,
                ]);
                $pendingEntries[$entityId] = [
                    'action' => $action,
                    'hash' => $hash,
                ];
                $stats[$action]++;
            } catch (Throwable $exception) {
                $stats['errors']++;
                $this->logRecordError(
                    $entityId,
                    'Produkt-Delta konnte nicht berechnet werden.',
                    $exception
                );
            }
        }

        foreach ($knownExportStates as $entityId => $state) {
            if (isset($currentEntityIds[$entityId])) {
                continue;
            }

            if (($state['action'] ?? null) === 'delete') {
                continue;
            }

            try {
                if ($this->hasMatchingPendingEntry($pendingEntries[$entityId] ?? null, 'delete', null)) {
                    continue;
                }

                $this->enqueue($entityId, 'delete', [
                    'entity_type' => $this->entityType,
                    'entity_id' => $entityId,
                    'hash' => null,
                    'last_exported_hash' => $state['hash'] ?? null,
                    'data' => null,
                ]);
                $stats['delete']++;
            } catch (Throwable $exception) {
                $stats['errors']++;
                $this->logRecordError(
                    $entityId,
                    'Produkt-Delete konnte nicht in die Export Queue geschrieben werden.',
                    $exception
                );
            }
        }

        $stats['changed'] = $stats['insert'] + $stats['update'] + $stats['delete'];

        if ($this->monitor !== null) {
            $this->monitor->log($this->runId, 'info', 'Produkt-Delta berechnet.', [
                'processed' => $stats['processed'],
                'changed' => $stats['changed'],
                'insert' => $stats['insert'],
                'update' => $stats['update'],
                'delete' => $stats['delete'],
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

    private function fetchLatestPendingEntries(): array
    {
        $stmt = $this->stageDb->prepare(
            'SELECT entity_id, action, payload
             FROM export_queue
             WHERE entity_type = :entity_type
               AND status = :status
             ORDER BY id ASC'
        );
        $stmt->execute([
            ':entity_type' => $this->entityType,
            ':status' => 'pending',
        ]);

        $entries = [];
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $entityId = (int) ($row['entity_id'] ?? 0);
            if ($entityId <= 0) {
                continue;
            }

            $payload = $this->decodePayload($row['payload'] ?? null);
            $entries[$entityId] = [
                'action' => $row['action'] ?? null,
                'hash' => $payload['hash'] ?? null,
            ];
        }

        return $entries;
    }

    private function fetchKnownExportStates(): array
    {
        $stmt = $this->stageDb->prepare(
            'SELECT entity_id, action, payload
             FROM export_queue
             WHERE entity_type = :entity_type
               AND status = :status
             ORDER BY id ASC'
        );
        $stmt->execute([
            ':entity_type' => $this->entityType,
            ':status' => 'done',
        ]);

        $states = [];
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $entityId = (int) ($row['entity_id'] ?? 0);
            if ($entityId <= 0) {
                continue;
            }

            $payload = $this->decodePayload($row['payload'] ?? null);
            $states[$entityId] = [
                'action' => $row['action'] ?? null,
                'hash' => $payload['hash'] ?? null,
            ];
        }

        return $states;
    }

    private function buildPayload(array $product, array $translations, array $attributes): array
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

    private function enqueue(int $entityId, string $action, array $payload): void
    {
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

    private function hasMatchingPendingEntry(?array $pendingEntry, string $action, ?string $hash): bool
    {
        if ($pendingEntry === null) {
            return false;
        }

        return ($pendingEntry['action'] ?? null) === $action
            && ($pendingEntry['hash'] ?? null) === $hash;
    }

    private function decodePayload(mixed $payload): array
    {
        if (!is_string($payload) || $payload === '') {
            return [];
        }

        $decoded = json_decode($payload, true);
        return is_array($decoded) ? $decoded : [];
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
