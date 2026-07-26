<?php

declare(strict_types=1);

require dirname(__DIR__) . '/src/Database/ConnectionFactory.php';
require dirname(__DIR__) . '/src/Service/WelaApiClient.php';
require dirname(__DIR__) . '/src/Service/XtQueueWriter.php';
require dirname(__DIR__) . '/src/Service/XtBatchQueueWriter.php';
require dirname(__DIR__) . '/src/Service/AbstractXtWriter.php';
require dirname(__DIR__) . '/src/Service/StageCategoryMap.php';
require dirname(__DIR__) . '/src/Service/XtProductWriter.php';

$configSources = require dirname(__DIR__) . '/config/sources.php';
$configXtWrite = require dirname(__DIR__) . '/config/xt_write.php';
$sizes = [];

foreach (array_slice($argv, 1) as $arg) {
    $size = (int) $arg;
    if ($size > 0) {
        $sizes[] = $size;
    }
}

if ($sizes === []) {
    $sizes = [2, 10];
}

$maxSize = max($sizes);
$stageDb = ConnectionFactory::create($configSources['sources']['stage']);
$stmt = $stageDb->prepare(
    'SELECT id, entity_id, payload
     FROM export_queue
     WHERE entity_type = :entity_type
       AND status = :status
     ORDER BY id ASC
     LIMIT :limit'
);
$stmt->bindValue(':entity_type', 'product');
$stmt->bindValue(':status', 'pending');
$stmt->bindValue(':limit', $maxSize, PDO::PARAM_INT);
$stmt->execute();
$entries = $stmt->fetchAll(PDO::FETCH_ASSOC);

if ($entries === []) {
    fwrite(STDERR, "Keine passenden Produkt-Queue-Eintraege gefunden.\n");
    exit(1);
}

$writer = new XtProductWriter($configSources, $configXtWrite);
$prepareMethod = new ReflectionMethod($writer, 'prepareProductSyncPayload');
$prepareMethod->setAccessible(true);
$client = new WelaApiClient(
    (string) ($configSources['sources']['xt']['connection']['url'] ?? ''),
    (string) ($configSources['sources']['xt']['connection']['key'] ?? ''),
    (int) ($configSources['sources']['xt']['connection']['request_timeout_seconds'] ?? 30)
);

$preparedItems = [];

foreach ($entries as $entry) {
    $payload = json_decode((string) ($entry['payload'] ?? ''), true);
    if (!is_array($payload)) {
        continue;
    }

    $prepared = $prepareMethod->invoke($writer, $entry, $payload);
    $batchSyncPayload = is_array($prepared['batch_sync_payload'] ?? null)
        ? $prepared['batch_sync_payload']
        : null;

    if (!is_array($batchSyncPayload)) {
        continue;
    }

    $preparedItems[] = [
        'queue_id' => (int) ($entry['id'] ?? 0),
        'entity_id' => (string) ($entry['entity_id'] ?? ''),
        'batch_sync_payload' => $batchSyncPayload,
    ];
}

if ($preparedItems === []) {
    fwrite(STDERR, "Es konnten keine gueltigen Benchmark-Payloads vorbereitet werden.\n");
    exit(1);
}

$variants = [
    'normal' => static fn (array $item): array => $item,
    'no_seo' => static function (array $item): array {
        $item['batch_sync_payload']['seo_urls'] = [];
        return $item;
    },
    'no_categories' => static function (array $item): array {
        $item['batch_sync_payload']['category_relations'] = [];
        return $item;
    },
];

foreach ($sizes as $size) {
    $subset = array_slice($preparedItems, 0, $size);

    foreach ($variants as $variantName => $transform) {
        $items = array_map($transform, $subset);
        $startedAt = microtime(true);
        $response = $client->syncProductsBatchWithMeta($items);
        $elapsed = microtime(true) - $startedAt;

        echo json_encode([
            'size' => $size,
            'variant' => $variantName,
            'elapsed_seconds' => round($elapsed, 4),
            'http_duration_seconds' => round((float) (($response['meta']['duration_seconds'] ?? 0.0)), 4),
            'payload_bytes' => (int) ($response['meta']['payload_bytes'] ?? 0),
            'response_bytes' => (int) ($response['meta']['response_bytes'] ?? 0),
            'server_duration_seconds' => round((float) (($response['data']['duration_seconds'] ?? 0.0)), 4),
            'success_count' => (int) ($response['data']['success_count'] ?? 0),
            'error_count' => (int) ($response['data']['error_count'] ?? 0),
        ], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) . PHP_EOL;
    }
}
