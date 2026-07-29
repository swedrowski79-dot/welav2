<?php

declare(strict_types=1);

$xtWriteDependencyMapPath = dirname(__DIR__) . '/src/Service/XtWriteDependencyMap.php';
if (is_file($xtWriteDependencyMapPath)) {
    require_once $xtWriteDependencyMapPath;
}

$seoHelpersPath = __DIR__ . '/seo_helpers.php';
if (is_file($seoHelpersPath)) {
    require_once $seoHelpersPath;
}

$xtImageHelpersPath = __DIR__ . '/xt_image_helpers.php';
if (is_file($xtImageHelpersPath)) {
    require_once $xtImageHelpersPath;
}

$welaApiSchemaInspectorPath = __DIR__ . '/src/SchemaInspector.php';
if (is_file($welaApiSchemaInspectorPath)) {
    require_once $welaApiSchemaInspectorPath;
}

$welaApiProductSyncServicePath = __DIR__ . '/src/ProductSyncService.php';
if (is_file($welaApiProductSyncServicePath)) {
    require_once $welaApiProductSyncServicePath;
}

$welaApiCategorySyncServicePath = __DIR__ . '/src/CategorySyncService.php';
if (is_file($welaApiCategorySyncServicePath)) {
    require_once $welaApiCategorySyncServicePath;
}

$welaApiFileTransferServicePath = __DIR__ . '/src/FileTransferService.php';
if (is_file($welaApiFileTransferServicePath)) {
    require_once $welaApiFileTransferServicePath;
}

$welaApiShopMaintenanceServicePath = __DIR__ . '/src/ShopMaintenanceService.php';
if (is_file($welaApiShopMaintenanceServicePath)) {
    require_once $welaApiShopMaintenanceServicePath;
}

$welaConfigFile = __DIR__ . '/config.php';

if (!is_file($welaConfigFile)) {
    wela_respond(500, [
        'ok' => false,
        'error' => 'Konfigurationsdatei fehlt. Bitte config.php aus config.php.example erzeugen.',
    ]);
}

$welaConfig = require $welaConfigFile;
if (!is_array($welaConfig)) {
    wela_respond(500, [
        'ok' => false,
        'error' => 'config.php muss ein PHP-Array zurueckgeben.',
    ]);
}
$GLOBALS['wela_api_config'] = $welaConfig;
$GLOBALS['wela_api_request_id'] = bin2hex(random_bytes(8));
$GLOBALS['wela_api_runtime_version'] = '2026-07-29-translated-seo-path-fix-2';

$earlyLog = static function (string $level, string $message, array $context = []) use ($welaConfig): void {
    $enabled = ($welaConfig['logging'] ?? false) === true
        || (is_array($welaConfig['logging'] ?? null) && (($welaConfig['logging']['enabled'] ?? false) === true));

    if (!$enabled) {
        return;
    }

    $logFile = null;
    if (is_string($welaConfig['log_file'] ?? null) && trim((string) $welaConfig['log_file']) !== '') {
        $logFile = trim((string) $welaConfig['log_file']);
    } elseif (is_array($welaConfig['logging'] ?? null) && is_string($welaConfig['logging']['file'] ?? null) && trim((string) $welaConfig['logging']['file']) !== '') {
        $logFile = trim((string) $welaConfig['logging']['file']);
    }

    $logFile ??= __DIR__ . '/wela-api.log';

    $entry = [
        'timestamp' => gmdate('c'),
        'level' => $level,
        'request_id' => $GLOBALS['wela_api_request_id'] ?? null,
        'action' => $_GET['action'] ?? null,
        'message' => $message,
        'context' => $context,
    ];

    $json = json_encode($entry, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    if (is_string($json)) {
        @file_put_contents($logFile, $json . PHP_EOL, FILE_APPEND | LOCK_EX);
    }
};

if (is_string($welaConfig['xt_commerce_root'] ?? null) && trim((string) $welaConfig['xt_commerce_root']) !== '') {
    putenv('XT_COMMERCE_ROOT=' . trim((string) $welaConfig['xt_commerce_root']));
}

$headers = function_exists('getallheaders') ? getallheaders() : [];
if (!is_array($headers)) {
    $headers = [];
}

$normalizedHeaders = [];
foreach ($headers as $headerName => $headerValue) {
    if (!is_string($headerName)) {
        continue;
    }
    if (is_array($headerValue)) {
        $headerValue = implode(',', array_map('strval', $headerValue));
    } elseif (!is_scalar($headerValue) && $headerValue !== null) {
        continue;
    }
    $normalizedHeaders[strtolower($headerName)] = trim((string) $headerValue);
}

// Apache/FastCGI fallback, falls getallheaders() benutzerdefinierte Header nicht liefert.
$serverHeaderMap = [
    'x-wela-key' => 'HTTP_X_WELA_KEY',
    'x-wela-timestamp' => 'HTTP_X_WELA_TIMESTAMP',
    'x-wela-signature' => 'HTTP_X_WELA_SIGNATURE',
];
foreach ($serverHeaderMap as $normalizedName => $serverKey) {
    if (!isset($normalizedHeaders[$normalizedName]) && isset($_SERVER[$serverKey])) {
        $normalizedHeaders[$normalizedName] = trim((string) $_SERVER[$serverKey]);
    }
}

$providedKey = $normalizedHeaders['x-wela-key'] ?? '';
$providedTimestamp = $normalizedHeaders['x-wela-timestamp'] ?? '';
$providedSignature = $normalizedHeaders['x-wela-signature'] ?? '';
$rawBodyValue = file_get_contents('php://input');
$rawBody = is_string($rawBodyValue) && $rawBodyValue !== '' ? $rawBodyValue : '{}';
$action = (string) ($_GET['action'] ?? 'health');
$GLOBALS['wela_api_action'] = $action;

$requestPreview = json_decode($rawBody, true);
wela_log('info', 'API request received.', [
    'action' => $action,
    'method' => $_SERVER['REQUEST_METHOD'] ?? 'CLI',
    'runtime_version' => $GLOBALS['wela_api_runtime_version'] ?? null,
    'request' => is_array($requestPreview) ? wela_log_sanitize($requestPreview) : $rawBody,
]);

try {
    wela_log('info', 'API authentication started.', [
        'action' => $action,
        'has_api_key_header' => $providedKey !== '',
        'has_timestamp_header' => $providedTimestamp !== '',
        'has_signature_header' => $providedSignature !== '',
    ]);

    $welaConfiguredApiKey = isset($welaConfig['api_key']) && is_scalar($welaConfig['api_key'])
        ? (string) $welaConfig['api_key']
        : '';

    if ($welaConfiguredApiKey === '' || !hash_equals($welaConfiguredApiKey, (string) $providedKey)) {
        wela_log('warning', 'API request rejected: invalid API key.', [
            'action' => $action,
            'configured_key_present' => $welaConfiguredApiKey !== '',
            'provided_key_present' => $providedKey !== '',
        ]);
        wela_respond(401, [
            'ok' => false,
            'error' => 'Ungueltiger API-Key.',
        ]);
    }

    if (!wela_is_valid_timestamp($providedTimestamp)) {
    wela_log('warning', 'API request rejected: invalid timestamp.', [
        'action' => $action,
        'timestamp' => $providedTimestamp,
    ]);
    wela_respond(401, [
        'ok' => false,
        'error' => 'Ungueltiger oder abgelaufener Timestamp.',
    ]);
}

$expectedSignature = hash_hmac('sha256', $providedTimestamp . '.' . $rawBody, (string) ($welaConfig['api_key'] ?? ''));
    if (!hash_equals($expectedSignature, (string) $providedSignature)) {
        wela_log('warning', 'API request rejected: invalid signature.', [
            'action' => $action,
            'provided_signature_present' => $providedSignature !== '',
        ]);
        wela_respond(401, [
            'ok' => false,
            'error' => 'Ungueltige Signatur.',
        ]);
    }

    wela_log('info', 'API authentication completed.', [
        'action' => $action,
    ]);
} catch (Throwable $authenticationException) {
    wela_log('error', 'API authentication crashed.', [
        'action' => $action,
        'exception' => $authenticationException->getMessage(),
        'trace' => $authenticationException->getTraceAsString(),
    ]);
    wela_respond(500, [
        'ok' => false,
        'error' => 'Authentifizierungspruefung fehlgeschlagen: ' . $authenticationException->getMessage(),
    ]);
}

$request = json_decode($rawBody, true);

if ($action !== 'health' && !is_array($request)) {
    wela_log('warning', 'API request rejected: invalid JSON body.', [
        'action' => $action,
    ]);
    wela_respond(400, [
        'ok' => false,
        'error' => 'Request-Body muss gueltiges JSON sein.',
    ]);
}

/*
 * Datei-Uploads benötigen keine PDO-Verbindung. Sie werden bewusst vor der
 * Datenbankinitialisierung verarbeitet, damit ein DB-Problem den Upload nach
 * media/images/org nicht verhindern kann.
 */
if ($action === 'upload_document_file') {
    try {
        wela_log('info', 'Upload action dispatch started before database initialization.', [
            'action' => $action,
            'runtime_version' => $GLOBALS['wela_api_runtime_version'] ?? null,
        ]);

        $fileName = wela_required_non_empty_string(
            $request['file_name'] ?? null,
            'Dokument-Upload benoetigt file_name.'
        );
        $contentBase64 = wela_required_non_empty_string(
            $request['content_base64'] ?? null,
            'Dokument-Upload benoetigt content_base64.'
        );
        $targetPath = wela_optional_non_empty_string($request['target_path'] ?? null);
        $imageClass = wela_optional_non_empty_string($request['image_class'] ?? null) ?? 'product';
        $stored = wela_store_document_file($welaConfig, $fileName, $contentBase64, $targetPath, $imageClass);

        wela_respond(200, [
            'ok' => true,
            'data' => $stored,
        ]);
    } catch (Throwable $exception) {
        wela_log('error', 'Upload action failed.', [
            'action' => $action,
            'exception' => $exception->getMessage(),
            'trace' => $exception->getTraceAsString(),
        ]);

        wela_respond(500, [
            'ok' => false,
            'error' => $exception->getMessage(),
        ]);
    }
}

try {
    $pdo = wela_pdo($welaConfig['db'] ?? []);
    $GLOBALS['wela_xt_pdo'] = $pdo;
    wela_log('info', 'API action dispatch started.', [
        'action' => $action,
        'runtime_version' => $GLOBALS['wela_api_runtime_version'] ?? null,
    ]);

    switch ($action) {
        case 'health':
            $stmt = $pdo->query('SELECT 1');
            $stmt->fetchColumn();

            wela_respond(200, [
                'ok' => true,
                'message' => 'XT-API und Datenbank erreichbar.',
                'timestamp' => date(DATE_ATOM),
                'runtime_version' => $GLOBALS['wela_api_runtime_version'] ?? null,
            ]);
            break;

        case 'lookup_map':
            $table = wela_allowed_table($request['table'] ?? null, ['xt_products', 'xt_categories', 'xt_media', 'xt_plg_products_attributes']);
            $tableConfig = wela_allowed_tables()[$table];
            $keyField = wela_allowed_field($request['key_field'] ?? null, $tableConfig['read_fields']);
            $valueField = wela_allowed_field($request['value_field'] ?? null, $tableConfig['read_fields']);

            $stmt = $pdo->query(sprintf(
                'SELECT `%s`, `%s` FROM `%s`',
                $keyField,
                $valueField,
                $table
            ));

            $map = [];

            while ($row = $stmt->fetch()) {
                $key = $row[$keyField] ?? null;

                if ($key === null || $key === '') {
                    continue;
                }

                $map[(string) $key] = $row[$valueField] ?? null;
            }

            wela_respond(200, [
                'ok' => true,
                'data' => $map,
            ]);
            break;

        case 'fetch_rows':
            $table = wela_existing_table($pdo, $request['table'] ?? null);
            $fields = wela_existing_field_list($pdo, $table, $request['fields'] ?? null);
            $offset = max(0, (int) ($request['offset'] ?? 0));
            $limit = min(2000, max(1, (int) ($request['limit'] ?? 500)));

            $countStmt = $pdo->query(sprintf('SELECT COUNT(*) FROM `%s`', $table));
            $total = (int) $countStmt->fetchColumn();

            $stmt = $pdo->prepare(sprintf(
                'SELECT %s FROM `%s` ORDER BY %s LIMIT %d OFFSET %d',
                wela_select_columns($fields),
                $table,
                wela_order_clause(wela_table_primary_key($pdo, $table)),
                $limit,
                $offset
            ));
            $stmt->execute();
            $rows = $stmt->fetchAll();
            $nextOffset = ($offset + count($rows)) < $total ? ($offset + count($rows)) : null;

            wela_respond(200, [
                'ok' => true,
                'data' => [
                    'rows' => $rows,
                    'offset' => $offset,
                    'limit' => $limit,
                    'total' => $total,
                    'next_offset' => $nextOffset,
                ],
            ]);
            break;

        case 'upload_document_file':
            $fileName = wela_required_non_empty_string($request['file_name'] ?? null, 'Dokument-Upload benoetigt file_name.');
            $contentBase64 = wela_required_non_empty_string($request['content_base64'] ?? null, 'Dokument-Upload benoetigt content_base64.');
            $targetPath = wela_optional_non_empty_string($request['target_path'] ?? null);
            $imageClass = wela_optional_non_empty_string($request['image_class'] ?? null) ?? 'product';
            $stored = wela_store_document_file($welaConfig, $fileName, $contentBase64, $targetPath, $imageClass);

            wela_respond(200, [
                'ok' => true,
                'data' => $stored,
            ]);
            break;

        case 'browse_server_directories':
            $path = wela_optional_non_empty_string($request['path'] ?? null);
            $browser = wela_browse_server_directories($welaConfig, $path);

            wela_respond(200, [
                'ok' => true,
                'data' => $browser,
            ]);
            break;

        case 'upsert_row':
            $table = wela_allowed_table($request['table'] ?? null, [
                'xt_media',
                'xt_media_link',
                'xt_plg_products_attributes',
                'xt_plg_products_attributes_description',
                'xt_plg_products_to_attributes',
            ]);
            $tableConfig = wela_allowed_tables()[$table];
            $requestPrimaryKey = $request['primary_key'] ?? null;
            if (is_array($tableConfig['primary_key'] ?? null)) {
                $primaryKey = wela_allowed_field_list($requestPrimaryKey, (array) $tableConfig['primary_key']);
            } else {
                $primaryKey = wela_allowed_field($requestPrimaryKey, [(string) $tableConfig['primary_key']]);
            }
            $identity = wela_allowed_field_map($request['identity'] ?? null, $tableConfig['write_fields']);
            $columns = wela_allowed_field_map($request['columns'] ?? null, $tableConfig['write_fields']);

            if ($identity === []) {
                wela_respond(400, [
                    'ok' => false,
                    'error' => 'Upsert benoetigt mindestens ein Identity-Feld.',
                ]);
            }

            $result = wela_upsert_row($pdo, $table, $primaryKey, $identity, $columns);

            wela_respond(200, [
                'ok' => true,
                'data' => $result,
            ]);
            break;

        case 'delete_rows':
            $table = wela_allowed_table($request['table'] ?? null, ['xt_media_link', 'xt_plg_products_to_attributes']);
            $tableConfig = wela_allowed_tables()[$table];
            $where = wela_allowed_field_map($request['where'] ?? null, $tableConfig['write_fields']);

            if ($where === []) {
                wela_respond(400, [
                    'ok' => false,
                    'error' => 'Delete benoetigt mindestens eine WHERE-Bedingung.',
                ]);
            }

            $stmt = $pdo->prepare(
                sprintf(
                    'DELETE FROM `%s` WHERE %s',
                    $table,
                    wela_where_clause($where)
                )
            );
            $stmt->execute(wela_sql_params($where));

            wela_respond(200, [
                'ok' => true,
                'data' => [
                    'deleted' => $stmt->rowCount(),
                ],
            ]);
            break;

        case 'sync_products_batch':
            $items = wela_optional_array_list($request['items'] ?? null, 'Produkt-Batch benoetigt eine Liste von Items.');
            $includeResultsData = ($request['include_results_data'] ?? true) !== false;
            if ($items === []) {
                wela_respond(400, [
                    'ok' => false,
                    'error' => 'Produkt-Batch benoetigt mindestens ein Item.',
                ]);
            }

            $batchStartedAt = microtime(true);
            $results = [];
            $successCount = 0;
            $errorCount = 0;
            $batchContext = wela_prepare_product_batch_context($pdo, $items);
            wela_prefetch_product_batch_seo_candidates($pdo, $items, $batchContext);

            foreach ($items as $item) {
                $queueId = (int) ($item['queue_id'] ?? 0);
                $entityId = trim((string) ($item['entity_id'] ?? ''));
                $batchPayload = wela_required_array($item['batch_sync_payload'] ?? null, 'Produkt-Batch-Item benoetigt batch_sync_payload.');

                try {
                    $data = wela_sync_product_request($pdo, $batchPayload, wela_product_batch_item_context($batchContext, $batchPayload));
                    $result = [
                        'queue_id' => $queueId,
                        'entity_id' => $entityId,
                        'ok' => true,
                    ];
                    if ($includeResultsData) {
                        $result['data'] = $data;
                    }
                    $results[] = $result;
                    $successCount++;
                } catch (Throwable $exception) {
                    $results[] = [
                        'queue_id' => $queueId,
                        'entity_id' => $entityId,
                        'ok' => false,
                        'error' => $exception->getMessage(),
                    ];
                    $errorCount++;
                }
            }

            wela_respond(200, [
                'ok' => true,
                'data' => [
                    'results' => $results,
                    'success_count' => $successCount,
                    'error_count' => $errorCount,
                    'duration_seconds' => round(microtime(true) - $batchStartedAt, 4),
                ],
            ]);
            break;

        case 'sync_product':
            wela_respond(200, [
                'ok' => true,
                'data' => wela_sync_product_request($pdo, $request),
            ]);
            break;

        case 'sync_categories_batch':
            wela_respond(200, [
                'ok' => true,
                'data' => wela_sync_categories_batch_request($pdo, $request),
            ]);
            break;

        case 'sync_category':
            wela_respond(200, [
                'ok' => true,
                'data' => wela_sync_category_request($pdo, $request),
            ]);
            break;

        case 'refresh_shop_state':
            wela_respond(200, [
                'ok' => true,
                'data' => wela_refresh_shop_state(),
            ]);
            break;

        default:
            wela_respond(404, [
                'ok' => false,
                'error' => 'Unbekannte Aktion.',
            ]);
    }
} catch (Throwable $exception) {
    wela_log('error', 'API action failed.', [
        'action' => $action,
        'exception' => $exception->getMessage(),
        'trace' => $exception->getTraceAsString(),
    ]);
    wela_respond(500, [
        'ok' => false,
        'error' => $exception->getMessage(),
    ]);
}

function wela_pdo(array $db): PDO
{
    $host = (string) ($db['host'] ?? '127.0.0.1');
    $port = (int) ($db['port'] ?? 3306);
    $database = (string) ($db['database'] ?? '');
    $username = (string) ($db['username'] ?? '');
    $password = (string) ($db['password'] ?? '');
    $charset = (string) ($db['charset'] ?? 'utf8mb4');

    $dsn = "mysql:host={$host};port={$port};dbname={$database};charset={$charset}";
    $pdo = new PDO($dsn, $username, $password, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    ]);

    return $pdo;
}

function wela_respond(int $status, array $payload): never
{
    wela_log($status >= 400 ? 'error' : 'info', 'API response sent.', [
        'status' => $status,
        'payload' => wela_log_sanitize($payload),
    ]);
    http_response_code($status);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($payload, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
    exit;
}

function wela_is_valid_timestamp(mixed $timestamp): bool
{
    if (!is_string($timestamp) || !ctype_digit($timestamp)) {
        return false;
    }

    return abs(time() - (int) $timestamp) <= 300;
}

function wela_allowed_tables(): array
{
    $tables = [
        'xt_products' => [
            'primary_key' => 'products_id',
            'read_fields' => [
                'products_id', 'external_id', 'products_model', 'products_ean', 'products_quantity',
                'products_price', 'products_weight', 'products_status', 'products_master_flag',
                'products_master_model', 'products_master_slave_order', 'products_image', 'last_modified',
            ],
            'write_fields' => [
                'products_id', 'external_id', 'permission_id', 'products_owner', 'products_ean',
                'products_quantity', 'show_stock', 'products_average_quantity', 'products_shippingtime',
                'products_shippingtime_nostock', 'products_model', 'products_master_flag',
                'products_master_model', 'products_master_slave_order', 'ms_open_first_slave', 'ms_show_slave_list',
                'ms_filter_slave_list', 'ms_filter_slave_list_hide_on_product',
                'products_image_from_master', 'ms_load_masters_free_downloads',
                'ms_load_masters_main_img', 'products_image', 'products_price', 'date_added', 'last_modified',
                'products_weight', 'products_status', 'products_tax_class_id', 'products_unit',
                'products_average_rating', 'products_rating_count', 'products_digital',
                'flag_has_specials', 'products_serials', 'total_downloads',
                'group_discount_allowed', 'google_product_cat', 'products_canonical_master',
            ],
        ],
        'xt_categories' => [
            'primary_key' => 'categories_id',
            'read_fields' => [
                'categories_id', 'external_id', 'parent_id', 'categories_level',
                'categories_image', 'categories_master_image', 'categories_status', 'last_modified',
            ],
            'write_fields' => [
                'categories_id', 'external_id', 'permission_id', 'categories_owner',
                'categories_image', 'categories_left', 'categories_right', 'categories_level',
                'parent_id', 'categories_status', 'categories_template', 'listing_template',
                'sort_order', 'products_sorting', 'products_sorting2', 'top_category',
                'start_page_category', 'date_added', 'last_modified', 'category_custom_link',
                'category_custom_link_type', 'category_custom_link_id', 'google_product_cat',
                'categories_master_image',
            ],
        ],
        'xt_categories_description' => [
            'primary_key' => ['categories_id', 'language_code'],
            'read_fields' => [
                'categories_id', 'language_code', 'categories_name',
                'categories_heading_title', 'categories_description', 'categories_store_id',
            ],
            'write_fields' => [
                'categories_id', 'language_code', 'categories_name', 'categories_heading_title',
                'categories_description', 'categories_description_bottom', 'categories_store_id',
            ],
        ],
        'xt_products_description' => [
            'primary_key' => ['products_id', 'language_code'],
            'read_fields' => [
                'products_id', 'language_code', 'products_name',
                'products_description', 'products_short_description', 'products_store_id',
            ],
            'write_fields' => [
                'products_id', 'language_code', 'reload_st', 'products_name',
                'products_description', 'products_short_description', 'products_keywords',
                'products_url', 'products_store_id',
            ],
        ],
        'xt_products_to_categories' => [
            'primary_key' => ['products_id', 'categories_id'],
            'read_fields' => ['products_id', 'categories_id', 'master_link', 'store_id'],
            'write_fields' => ['products_id', 'categories_id', 'master_link', 'store_id'],
        ],
        'xt_media' => [
            'primary_key' => 'id',
            'read_fields' => ['id', 'external_id', 'file', 'type', 'class', 'status', 'date_added', 'last_modified'],
            'write_fields' => [
                'id', 'file', 'type', 'class', 'download_status', 'status', 'owner',
                'date_added', 'last_modified', 'max_dl_count', 'max_dl_days',
                'total_downloads', 'copyright_holder', 'external_id',
            ],
        ],
        'xt_media_link' => [
            'primary_key' => 'ml_id',
            'read_fields' => ['ml_id', 'm_id', 'link_id', 'class', 'type', 'sort_order'],
            'write_fields' => ['ml_id', 'm_id', 'link_id', 'class', 'type', 'sort_order'],
        ],
        'xt_plg_products_attributes' => [
            'primary_key' => 'attributes_id',
            'read_fields' => ['attributes_id', 'attributes_parent', 'attributes_model', 'attributes_templates_id', 'sort_order', 'status'],
            'write_fields' => ['attributes_id', 'attributes_parent', 'attributes_model', 'attributes_templates_id', 'sort_order', 'status'],
        ],
        'xt_plg_products_attributes_description' => [
            'primary_key' => ['attributes_id', 'language_code'],
            'read_fields' => ['attributes_id', 'language_code', 'attributes_name', 'attributes_desc'],
            'write_fields' => ['attributes_id', 'language_code', 'attributes_name', 'attributes_desc'],
        ],
        'xt_plg_products_to_attributes' => [
            'primary_key' => ['products_id', 'attributes_id'],
            'read_fields' => ['products_id', 'attributes_id', 'attributes_parent_id'],
            'write_fields' => ['products_id', 'attributes_id', 'attributes_parent_id'],
        ],
        'xt_seo_url' => [
            'primary_key' => ['link_type', 'link_id', 'language_code', 'store_id'],
            'read_fields' => ['link_type', 'link_id', 'language_code', 'store_id', 'url_text', 'url_md5'],
            'write_fields' => [
                'url_md5', 'url_text', 'language_code', 'link_type', 'link_id',
                'meta_title', 'meta_description', 'meta_keywords', 'store_id',
            ],
        ],
    ];

    $xtWriteConfigPath = dirname(__DIR__) . '/config/xt_write.php';

    if (class_exists('XtWriteDependencyMap') && is_file($xtWriteConfigPath)) {
        $xtWriteConfig = require $xtWriteConfigPath;

        foreach (XtWriteDependencyMap::tableDefinitions($xtWriteConfig) as $table => $definition) {
            $tables[$table] ??= [
                'primary_key' => $definition['primary_key'],
                'read_fields' => [],
                'write_fields' => [],
            ];

            if (($tables[$table]['primary_key'] ?? null) === [] || ($tables[$table]['primary_key'] ?? null) === null) {
                $tables[$table]['primary_key'] = $definition['primary_key'];
            }

            $tables[$table]['read_fields'] = array_values(array_unique(array_merge(
                is_array($tables[$table]['read_fields'] ?? null) ? $tables[$table]['read_fields'] : [],
                is_array($definition['fields'] ?? null) ? $definition['fields'] : []
            )));
        }
    }

    return $tables;
}

function wela_allowed_table(mixed $table, array $allowedTables): string
{
    if (!is_string($table) || !in_array($table, $allowedTables, true)) {
        wela_respond(400, [
            'ok' => false,
            'error' => 'Unzulaessige XT-Tabelle.',
        ]);
    }

    return $table;
}

function wela_existing_table(PDO $pdo, mixed $table): string
{
    $table = wela_safe_identifier($table, 'XT-Tabelle');
    $stmt = $pdo->prepare('SHOW TABLES LIKE :table');
    $stmt->execute([':table' => $table]);

    if (!$stmt->fetchColumn()) {
        wela_respond(400, [
            'ok' => false,
            'error' => 'XT-Tabelle existiert nicht.',
        ]);
    }

    return $table;
}

function wela_allowed_field(mixed $field, array $allowedFields): string
{
    if (!is_string($field) || !in_array($field, $allowedFields, true)) {
        wela_respond(400, [
            'ok' => false,
            'error' => 'Unzulaessiges XT-Feld.',
        ]);
    }

    return $field;
}

function wela_safe_identifier(mixed $value, string $label = 'XT-Identifier'): string
{
    if (!is_string($value) || !preg_match('/^[A-Za-z0-9_]+$/', $value)) {
        wela_respond(400, [
            'ok' => false,
            'error' => $label . ' ist ungueltig.',
        ]);
    }

    return $value;
}

function wela_existing_field_list(PDO $pdo, string $table, mixed $fields): array
{
    $existingFields = wela_table_columns($pdo, $table);

    if ($fields === null) {
        return $existingFields;
    }

    if (!is_array($fields)) {
        wela_respond(400, [
            'ok' => false,
            'error' => 'Ungueltige XT-Feldliste.',
        ]);
    }

    $validated = [];

    foreach ($fields as $field) {
        $field = wela_safe_identifier($field, 'XT-Feld');

        if (!in_array($field, $existingFields, true)) {
            wela_respond(400, [
                'ok' => false,
                'error' => 'XT-Feld existiert nicht.',
            ]);
        }

        $validated[] = $field;
    }

    return $validated === [] ? $existingFields : array_values(array_unique($validated));
}

function wela_allowed_field_list(mixed $fields, array $allowedFields): array
{
    if ($fields === null) {
        return [];
    }

    if (!is_array($fields)) {
        wela_respond(400, [
            'ok' => false,
            'error' => 'Ungueltige XT-Feldliste.',
        ]);
    }

    $validated = [];

    foreach ($fields as $field) {
        $validated[] = wela_allowed_field($field, $allowedFields);
    }

    return array_values(array_unique($validated));
}

function wela_schema_inspector(PDO $pdo): WelaApi\SchemaInspector
{
    static $instances = [];

    $key = spl_object_id($pdo);
    if (!isset($instances[$key])) {
        $instances[$key] = new WelaApi\SchemaInspector($pdo);
    }

    return $instances[$key];
}

function wela_table_columns(PDO $pdo, string $table): array
{
    return wela_schema_inspector($pdo)->tableColumns($table);
}

function wela_table_primary_key(PDO $pdo, string $table): string|array
{
    return wela_schema_inspector($pdo)->tablePrimaryKey($table);
}

function wela_table_has_unique_index(PDO $pdo, string $table, array $fields): bool
{
    return wela_schema_inspector($pdo)->tableHasUniqueIndex($table, $fields);
}

function wela_allowed_field_map(mixed $values, array $allowedFields): array
{
    if (!is_array($values)) {
        wela_respond(400, [
            'ok' => false,
            'error' => 'Feldwerte muessen als JSON-Objekt uebergeben werden.',
        ]);
    }

    $sanitized = [];

    foreach ($values as $field => $value) {
        if (!is_string($field) || !in_array($field, $allowedFields, true)) {
            wela_respond(400, [
                'ok' => false,
                'error' => 'Unzulaessige XT-Feldbelegung.',
            ]);
        }

        if (is_bool($value)) {
            $value = $value ? 1 : 0;
        }

        if (!is_null($value) && !is_int($value) && !is_float($value) && !is_string($value)) {
            wela_respond(400, [
                'ok' => false,
                'error' => 'XT-Feldwerte duerfen nur Skalarwerte oder null enthalten.',
            ]);
        }

        $sanitized[$field] = $value;
    }

    return $sanitized;
}

function wela_select_columns(array $fields): string
{
    return implode(', ', array_map(
        static fn (string $field): string => sprintf('`%s`', $field),
        $fields
    ));
}

function wela_order_clause(string|array $primaryKey): string
{
    $fields = is_array($primaryKey) ? $primaryKey : [$primaryKey];

    return implode(', ', array_map(
        static fn (string $field): string => sprintf('`%s` ASC', $field),
        $fields
    ));
}

function wela_upsert_row(PDO $pdo, string $table, string|array $primaryKey, array $identity, array $columns): array
{
    if (wela_supports_native_upsert($table, $identity)) {
        return wela_upsert_row_native($pdo, $table, $primaryKey, $identity, $columns);
    }

    $primaryKeys = is_array($primaryKey) ? array_values($primaryKey) : [$primaryKey];
    $selectFields = array_values(array_unique(array_merge($primaryKeys, array_keys($identity), array_keys($columns))));
    $selectSql = sprintf(
        'SELECT %s FROM `%s` WHERE %s LIMIT 1',
        implode(', ', array_map(static fn (string $field): string => "`{$field}`", $selectFields)),
        $table,
        wela_where_clause($identity)
    );
    $selectStmt = $pdo->prepare($selectSql);
    $selectStmt->execute(wela_sql_params($identity));
    $existing = $selectStmt->fetch();

    if ($existing === false) {
        $insertValues = array_replace($identity, $columns);
        $fields = array_keys($insertValues);
        $sql = sprintf(
            'INSERT INTO `%s` (%s) VALUES (%s)',
            $table,
            implode(', ', array_map(static fn (string $field): string => "`{$field}`", $fields)),
            implode(', ', array_map(static fn (string $field): string => ':' . $field, $fields))
        );
        $stmt = $pdo->prepare($sql);
        $stmt->execute($insertValues);

        $primaryKeyValue = wela_extract_primary_key_value($existing, $insertValues, $primaryKey, $pdo);

        return [
            'action' => 'inserted',
            'primary_key_value' => $primaryKeyValue,
        ];
    }

    $updates = [];

    foreach ($columns as $field => $value) {
        $currentValue = $existing[$field] ?? null;

        if (wela_values_equal($currentValue, $value)) {
            continue;
        }

        $updates[$field] = $value;
    }

    if ($updates !== []) {
        $assignments = [];
        $params = [];

        foreach ($updates as $field => $value) {
            $assignments[] = "`{$field}` = :set_{$field}";
            $params[':set_' . $field] = $value;
        }

        foreach ($identity as $field => $value) {
            $params[':where_' . $field] = $value;
        }

        $sql = sprintf(
            'UPDATE `%s` SET %s WHERE %s',
            $table,
            implode(', ', $assignments),
            wela_where_clause($identity, 'where_')
        );
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
    }

    return [
        'action' => $updates === [] ? 'unchanged' : 'updated',
        'primary_key_value' => wela_extract_primary_key_value($existing, $existing, $primaryKey, $pdo),
    ];
}

function wela_supports_native_upsert(string $table, array $identity): bool
{
    static $supportedIdentityKeys = [
        'xt_categories_description' => ['categories_id', 'language_code'],
        'xt_products_description' => ['products_id', 'language_code'],
        'xt_products_to_categories' => ['products_id', 'categories_id'],
        'xt_plg_products_attributes' => ['attributes_model'],
        'xt_plg_products_attributes_description' => ['attributes_id', 'language_code'],
        'xt_plg_products_to_attributes' => ['products_id', 'attributes_id'],
        'xt_seo_url' => ['link_type', 'link_id', 'language_code', 'store_id'],
    ];

    if (!isset($supportedIdentityKeys[$table])) {
        return false;
    }

    $identityKeys = array_keys($identity);
    sort($identityKeys);
    $expectedKeys = $supportedIdentityKeys[$table];
    sort($expectedKeys);

    return $identityKeys === $expectedKeys;
}

function wela_upsert_row_native(PDO $pdo, string $table, string|array $primaryKey, array $identity, array $columns): array
{
    $insertValues = array_replace($identity, $columns);
    if ($insertValues === []) {
        throw new RuntimeException("Native Upsert fuer '{$table}' benoetigt mindestens ein Feld.");
    }

    $fields = array_keys($insertValues);
    $assignments = [];

    if (is_string($primaryKey) && !array_key_exists($primaryKey, $insertValues)) {
        $assignments[] = sprintf('`%1$s` = LAST_INSERT_ID(`%1$s`)', $primaryKey);
    }

    foreach ($columns as $field => $value) {
        $assignments[] = sprintf('`%1$s` = VALUES(`%1$s`)', $field);
    }

    if ($assignments === []) {
        foreach ($identity as $field => $value) {
            $assignments[] = sprintf('`%1$s` = `%1$s`', $field);
            break;
        }
    }

    $sql = sprintf(
        'INSERT INTO `%s` (%s) VALUES (%s) ON DUPLICATE KEY UPDATE %s',
        $table,
        implode(', ', array_map(static fn (string $field): string => "`{$field}`", $fields)),
        implode(', ', array_map(static fn (string $field): string => ':' . $field, $fields)),
        implode(', ', $assignments)
    );

    $stmt = $pdo->prepare($sql);
    $stmt->execute($insertValues);

    $rowCount = $stmt->rowCount();
    $action = match ($rowCount) {
        1 => 'inserted',
        2 => 'updated',
        default => 'unchanged',
    };

    return [
        'action' => $action,
        'primary_key_value' => wela_extract_primary_key_value(false, $insertValues, $primaryKey, $pdo),
    ];
}

function wela_batch_upsert_rows(PDO $pdo, string $table, array $rows, array $identityFields): void
{
    if ($rows === []) {
        return;
    }

    $groupedRows = [];

    foreach ($rows as $row) {
        if (!is_array($row) || $row === []) {
            continue;
        }

        $fields = array_keys($row);
        sort($fields);
        $groupedRows[implode('|', $fields)][] = $row;
    }

    foreach ($groupedRows as $groupRows) {
        wela_batch_upsert_rows_group($pdo, $table, $groupRows, $identityFields);
    }
}

function wela_batch_upsert_rows_group(PDO $pdo, string $table, array $rows, array $identityFields): void
{
    if ($rows === []) {
        return;
    }

    $fields = array_keys($rows[0]);
    $updateFields = array_values(array_filter(
        $fields,
        static fn (string $field): bool => !in_array($field, $identityFields, true)
    ));
    $valueGroups = [];
    $params = [];

    foreach (array_values($rows) as $rowIndex => $row) {
        $placeholders = [];

        foreach ($fields as $field) {
            $placeholder = ':' . $field . '_' . $rowIndex;
            $placeholders[] = $placeholder;
            $params[$placeholder] = $row[$field] ?? null;
        }

        $valueGroups[] = '(' . implode(', ', $placeholders) . ')';
    }

    $assignments = [];
    foreach ($updateFields as $field) {
        $assignments[] = sprintf('`%1$s` = VALUES(`%1$s`)', $field);
    }

    if ($assignments === []) {
        $assignments[] = sprintf('`%1$s` = `%1$s`', $identityFields[0] ?? $fields[0]);
    }

    $stmt = $pdo->prepare(
        sprintf(
            'INSERT INTO `%s` (%s) VALUES %s ON DUPLICATE KEY UPDATE %s',
            $table,
            implode(', ', array_map(static fn (string $field): string => "`{$field}`", $fields)),
            implode(', ', $valueGroups),
            implode(', ', $assignments)
        )
    );
    $stmt->execute($params);
}

function wela_delete_rows(PDO $pdo, string $table, array $where): int
{
    $stmt = $pdo->prepare(
        sprintf(
            'DELETE FROM `%s` WHERE %s',
            $table,
            wela_where_clause($where)
        )
    );
    $stmt->execute(wela_sql_params($where));

    return $stmt->rowCount();
}

function wela_product_sync_service(PDO $pdo): WelaApi\ProductSyncService
{
    static $instances = [];

    $key = spl_object_id($pdo);
    if (!isset($instances[$key])) {
        $instances[$key] = new WelaApi\ProductSyncService($pdo, wela_schema_inspector($pdo));
    }

    return $instances[$key];
}

function wela_sync_product_request(PDO $pdo, array $request, array $context = []): array
{
    return wela_product_sync_service($pdo)->syncProductRequest($request, $context);
}

function wela_prepare_product_batch_context(PDO $pdo, array $items): array
{
    return wela_product_sync_service($pdo)->prepareProductBatchContext($items);
}

function wela_product_batch_item_context(array $batchContext, array $request): array
{
    return WelaApi\ProductSyncService::productBatchItemContext($batchContext, $request);
}

function wela_prefetch_product_batch_seo_candidates(PDO $pdo, array $items, array $batchContext): void
{
    $baseUrlsByGroup = [];

    foreach ($items as $item) {
        if (!is_array($item)) {
            continue;
        }

        $batchPayload = is_array($item['batch_sync_payload'] ?? null) ? $item['batch_sync_payload'] : null;
        if (!is_array($batchPayload)) {
            continue;
        }

        $itemContext = wela_product_batch_item_context($batchContext, $batchPayload);
        $existingProductRow = is_array($itemContext['existing_product_row'] ?? null) ? $itemContext['existing_product_row'] : null;
        $productId = is_array($existingProductRow) ? (int) ($existingProductRow['products_id'] ?? 0) : 0;
        if ($productId <= 0) {
            continue;
        }

        $categoryRelations = wela_optional_array_list($batchPayload['category_relations'] ?? null, 'Produkt-Sync-Kategorien muessen eine Liste sein.');
        $masterCategoryIdFromPayload = 0;
        foreach ($categoryRelations as $relation) {
            if (!is_array($relation)) {
                continue;
            }

            $columns = is_array($relation['columns'] ?? null) ? $relation['columns'] : [];
            $categoryId = (int) ($columns['categories_id'] ?? 0);
            if ($categoryId <= 0) {
                continue;
            }

            if ($masterCategoryIdFromPayload <= 0 && (int) ($columns['master_link'] ?? 0) === 1) {
                $masterCategoryIdFromPayload = $categoryId;
            }
        }

        if ($masterCategoryIdFromPayload <= 0 && isset($categoryRelations[0]['columns']['categories_id'])) {
            $masterCategoryIdFromPayload = (int) ($categoryRelations[0]['columns']['categories_id'] ?? 0);
        }

        $seoUrls = wela_optional_array_list($batchPayload['seo_urls'] ?? null, 'Produkt-Sync-SEO-URLs muessen eine Liste sein.');
        foreach ($seoUrls as $seoUrl) {
            if (!is_array($seoUrl) || ($seoUrl['auto_generate'] ?? false) !== true) {
                continue;
            }

            $columns = is_array($seoUrl['columns'] ?? null) ? $seoUrl['columns'] : [];
            $languageCode = wela_allowed_language($seoUrl['language_code'] ?? null);
            $storeId = (int) ($columns['store_id'] ?? 1);
            $autoGenerateClass = is_string($seoUrl['auto_generate_class'] ?? null) ? (string) $seoUrl['auto_generate_class'] : 'product';

            if ($autoGenerateClass !== 'product') {
                continue;
            }

            $baseUrl = wela_generate_product_seo_url(
                $pdo,
                $productId,
                $languageCode,
                $storeId,
                is_string($seoUrl['auto_generate_text'] ?? null) ? (string) $seoUrl['auto_generate_text'] : null,
                [
                    'product_master_category_id' => $masterCategoryIdFromPayload,
                ]
            );
            $baseUrl = trim($baseUrl, '/');

            if ($baseUrl === '') {
                continue;
            }

            $groupKey = $languageCode . '|' . $storeId;
            $baseUrlsByGroup[$groupKey]['language_code'] = $languageCode;
            $baseUrlsByGroup[$groupKey]['store_id'] = $storeId;
            $baseUrlsByGroup[$groupKey]['base_urls'][$baseUrl] = $baseUrl;
        }
    }

    if ($baseUrlsByGroup === []) {
        return;
    }

    wela_prefetch_seo_url_candidates_bulk($pdo, array_values(array_map(
        static function (array $group): array {
            $group['base_urls'] = array_values($group['base_urls'] ?? []);
            return $group;
        },
        $baseUrlsByGroup
    )));
}

function wela_category_sync_service(PDO $pdo): WelaApi\CategorySyncService
{
    static $instances = [];

    $key = spl_object_id($pdo);
    if (!isset($instances[$key])) {
        $instances[$key] = new WelaApi\CategorySyncService($pdo);
    }

    return $instances[$key];
}

function wela_sync_category_request(PDO $pdo, array $request): array
{
    return wela_category_sync_service($pdo)->syncCategoryRequest($request);
}

function wela_sync_categories_batch_request(PDO $pdo, array $request): array
{
    return wela_category_sync_service($pdo)->syncCategoriesBatchRequest($request);
}

function wela_prepare_product_columns(PDO $pdo, array $identity, array $columns, ?array $existingProductRow = null): array
{
    if (array_key_exists('products_master_slave_order', $columns)) {
        return $columns;
    }

    if (is_array($existingProductRow)) {
        return $columns;
    }

    $columns['products_master_slave_order'] = 0;

    return $columns;
}

function wela_preserve_existing_seo_url_columns(PDO $pdo, array $identity, array $columns, ?array $existingSeoRow = null): array
{
    $existingSeoRow = is_array($existingSeoRow) ? $existingSeoRow : wela_fetch_existing_seo_url($pdo, $identity);

    if (!is_array($existingSeoRow)) {
        return $columns;
    }

    unset($columns['url_text'], $columns['url_md5']);

    return $columns;
}

function wela_file_transfer_service(array $welaConfig): WelaApi\FileTransferService
{
    static $instances = [];

    $key = md5(serialize($welaConfig));
    if (!isset($instances[$key])) {
        $instances[$key] = new WelaApi\FileTransferService($welaConfig);
    }

    return $instances[$key];
}

function wela_store_document_file(array $welaConfig, string $fileName, string $contentBase64, ?string $targetPath = null, string $imageClass = 'product'): array
{
    return wela_file_transfer_service($welaConfig)->storeDocumentFile($fileName, $contentBase64, $targetPath, $imageClass);
}

function wela_browse_server_directories(array $welaConfig, ?string $path = null): array
{
    return wela_file_transfer_service($welaConfig)->browseServerDirectories($path);
}

function wela_shop_maintenance_service(): WelaApi\ShopMaintenanceService
{
    static $instance = null;

    if (!$instance instanceof WelaApi\ShopMaintenanceService) {
        $instance = new WelaApi\ShopMaintenanceService();
    }

    return $instance;
}

function wela_refresh_shop_state(): array
{
    return wela_shop_maintenance_service()->refreshShopState();
}

function wela_where_clause(array $values, string $prefix = ''): string
{
    $parts = [];

    foreach ($values as $field => $value) {
        if ($value === null) {
            $parts[] = "`{$field}` IS NULL";
            continue;
        }

        $parts[] = "`{$field}` = :" . $prefix . $field;
    }

    return implode(' AND ', $parts);
}

function wela_sql_params(array $values, string $prefix = ''): array
{
    $params = [];

    foreach ($values as $field => $value) {
        if ($value === null) {
            continue;
        }

        $params[':' . $prefix . $field] = $value;
    }

    return $params;
}

function wela_values_equal(mixed $left, mixed $right): bool
{
    if ($left === null || $right === null) {
        return $left === $right;
    }

    return (string) $left === (string) $right;
}

function wela_required_array(mixed $value, string $errorMessage): array
{
    if (!is_array($value)) {
        wela_respond(400, [
            'ok' => false,
            'error' => $errorMessage,
        ]);
    }

    return $value;
}

function wela_optional_array_list(mixed $value, string $errorMessage): array
{
    if ($value === null) {
        return [];
    }

    if (!is_array($value)) {
        wela_respond(400, [
            'ok' => false,
            'error' => $errorMessage,
        ]);
    }

    return array_values($value);
}

function wela_optional_non_empty_string(mixed $value): ?string
{
    if (!is_string($value)) {
        return null;
    }

    $trimmed = trim($value);

    return $trimmed === '' ? null : $trimmed;
}

function wela_required_non_empty_string(mixed $value, string $errorMessage): string
{
    if (!is_string($value) || trim($value) === '') {
        wela_respond(400, [
            'ok' => false,
            'error' => $errorMessage,
        ]);
    }

    return trim($value);
}

function wela_log(string $level, string $message, array $context = []): void
{
    $welaConfig = $GLOBALS['wela_api_config'] ?? [];
    $enabled = false;

    if (is_array($welaConfig)) {
        $enabled = ($welaConfig['logging'] ?? false) === true
            || (is_array($welaConfig['logging'] ?? null) && (($welaConfig['logging']['enabled'] ?? false) === true));
    }

    if (!$enabled) {
        return;
    }

    $logFile = null;
    if (is_array($welaConfig) && is_string($welaConfig['log_file'] ?? null) && trim((string) $welaConfig['log_file']) !== '') {
        $logFile = trim((string) $welaConfig['log_file']);
    } elseif (is_array($welaConfig['logging'] ?? null) && is_string($welaConfig['logging']['file'] ?? null) && trim((string) $welaConfig['logging']['file']) !== '') {
        $logFile = trim((string) $welaConfig['logging']['file']);
    }

    $logFile ??= __DIR__ . '/wela-api.log';

    $entry = [
        'timestamp' => gmdate('c'),
        'level' => $level,
        'request_id' => $GLOBALS['wela_api_request_id'] ?? null,
        'action' => $GLOBALS['wela_api_action'] ?? null,
        'message' => $message,
        'context' => wela_log_sanitize($context),
    ];

    $json = json_encode($entry, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    if (!is_string($json)) {
        return;
    }

    @file_put_contents($logFile, $json . PHP_EOL, FILE_APPEND | LOCK_EX);
}

function wela_log_sanitize(mixed $value): mixed
{
    if (is_array($value)) {
        $result = [];
        foreach ($value as $key => $item) {
            $normalizedKey = is_string($key) ? $key : (string) $key;
            if ($normalizedKey === 'content_base64') {
                $result[$normalizedKey] = '[base64 omitted]';
                continue;
            }

            $result[$normalizedKey] = wela_log_sanitize($item);
        }

        return $result;
    }

    if (is_string($value) && strlen($value) > 2000) {
        return substr($value, 0, 2000) . '...[truncated]';
    }

    return $value;
}

function wela_allowed_language(mixed $languageCode): string
{
    return wela_allowed_field($languageCode, ['de', 'en', 'fr', 'nl']);
}

function wela_extract_primary_key_value(array|false $existing, array $values, string|array $primaryKey, PDO $pdo): mixed
{
    if (is_array($primaryKey)) {
        $result = [];

        foreach ($primaryKey as $field) {
            if (is_array($existing) && array_key_exists($field, $existing)) {
                $result[$field] = ctype_digit((string) $existing[$field]) ? (int) $existing[$field] : $existing[$field];
                continue;
            }

            $result[$field] = $values[$field] ?? null;
        }

        return $result;
    }

    $primaryKeyValue = $values[$primaryKey] ?? $pdo->lastInsertId();

    return ctype_digit((string) $primaryKeyValue) ? (int) $primaryKeyValue : $primaryKeyValue;
}
