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

$configFile = __DIR__ . '/config.php';

if (!is_file($configFile)) {
    wela_respond(500, [
        'ok' => false,
        'error' => 'Konfigurationsdatei fehlt. Bitte config.php aus config.php.example erzeugen.',
    ]);
}

$config = require $configFile;
$headers = function_exists('getallheaders') ? getallheaders() : [];
$providedKey = $headers['X-Wela-Key'] ?? $headers['x-wela-key'] ?? '';
$providedTimestamp = $headers['X-Wela-Timestamp'] ?? $headers['x-wela-timestamp'] ?? '';
$providedSignature = $headers['X-Wela-Signature'] ?? $headers['x-wela-signature'] ?? '';
$rawBody = file_get_contents('php://input') ?: '{}';

if (!is_string($providedKey) || !hash_equals((string) ($config['api_key'] ?? ''), $providedKey)) {
    wela_respond(401, [
        'ok' => false,
        'error' => 'Ungueltiger API-Key.',
    ]);
}

if (!wela_is_valid_timestamp($providedTimestamp)) {
    wela_respond(401, [
        'ok' => false,
        'error' => 'Ungueltiger oder abgelaufener Timestamp.',
    ]);
}

$expectedSignature = hash_hmac('sha256', $providedTimestamp . '.' . $rawBody, (string) ($config['api_key'] ?? ''));
if (!is_string($providedSignature) || !hash_equals($expectedSignature, $providedSignature)) {
    wela_respond(401, [
        'ok' => false,
        'error' => 'Ungueltige Signatur.',
    ]);
}

$action = (string) ($_GET['action'] ?? 'health');
$request = json_decode($rawBody, true);

if ($action !== 'health' && !is_array($request)) {
    wela_respond(400, [
        'ok' => false,
        'error' => 'Request-Body muss gueltiges JSON sein.',
    ]);
}

try {
    $pdo = wela_pdo($config['db'] ?? []);

    switch ($action) {
        case 'health':
            $stmt = $pdo->query('SELECT 1');
            $stmt->fetchColumn();

            wela_respond(200, [
                'ok' => true,
                'message' => 'XT-API und Datenbank erreichbar.',
                'timestamp' => date(DATE_ATOM),
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
            $stored = wela_store_document_file($config, $fileName, $contentBase64, $targetPath);

            wela_respond(200, [
                'ok' => true,
                'data' => $stored,
            ]);
            break;

        case 'browse_server_directories':
            $path = wela_optional_non_empty_string($request['path'] ?? null);
            $browser = wela_browse_server_directories($config, $path);

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
            if ($items === []) {
                wela_respond(400, [
                    'ok' => false,
                    'error' => 'Produkt-Batch benoetigt mindestens ein Item.',
                ]);
            }

            $results = [];
            $successCount = 0;
            $errorCount = 0;

            foreach ($items as $item) {
                $queueId = (int) ($item['queue_id'] ?? 0);
                $entityId = trim((string) ($item['entity_id'] ?? ''));
                $batchPayload = wela_required_array($item['batch_sync_payload'] ?? null, 'Produkt-Batch-Item benoetigt batch_sync_payload.');

                try {
                    $data = wela_sync_product_request($pdo, $batchPayload);
                    $results[] = [
                        'queue_id' => $queueId,
                        'entity_id' => $entityId,
                        'ok' => true,
                        'data' => $data,
                    ];
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
                ],
            ]);
            break;

        case 'sync_product':
            wela_respond(200, [
                'ok' => true,
                'data' => wela_sync_product_request($pdo, $request),
            ]);
            break;

        case 'sync_category':
            $category = wela_required_array($request['category'] ?? null, 'Kategorie-Sync benoetigt einen Kategorieblock.');
            $categoryIdentity = wela_allowed_field_map(
                wela_required_array($category['identity'] ?? null, 'Kategorie-Sync benoetigt eine Kategorie-Identitaet.'),
                ['external_id']
            );
            $categoryColumns = wela_allowed_field_map(
                wela_required_array($category['columns'] ?? null, 'Kategorie-Sync benoetigt Kategoriespalten.'),
                wela_allowed_tables()['xt_categories']['write_fields']
            );
            $translations = wela_optional_array_list($request['translations'] ?? null, 'Kategorie-Sync-Uebersetzungen muessen eine Liste sein.');
            $seoUrls = wela_optional_array_list($request['seo_urls'] ?? null, 'Kategorie-Sync-SEO-URLs muessen eine Liste sein.');

            $pdo->beginTransaction();

            try {
                $categoryResult = wela_upsert_row($pdo, 'xt_categories', 'categories_id', $categoryIdentity, $categoryColumns);
                $categoryId = (int) ($categoryResult['primary_key_value'] ?? 0);

                if ($categoryId <= 0) {
                    throw new RuntimeException('Kategorie-Sync konnte keine gueltige XT-Kategorie-ID ermitteln.');
                }

                foreach ($translations as $translation) {
                    $languageCode = wela_allowed_language($translation['language_code'] ?? null);
                    $translationColumns = wela_allowed_field_map(
                        wela_required_array($translation['columns'] ?? null, 'Kategorie-Sync-Uebersetzung benoetigt Spalten.'),
                        wela_allowed_tables()['xt_categories_description']['write_fields']
                    );
                    unset($translationColumns['categories_id']);

                    wela_upsert_row(
                        $pdo,
                        'xt_categories_description',
                        ['categories_id', 'language_code'],
                        [
                            'categories_id' => $categoryId,
                            'language_code' => $languageCode,
                        ],
                        $translationColumns
                    );
                }

                $seoWrites = 0;

                foreach ($seoUrls as $seoUrl) {
                    $languageCode = wela_allowed_language($seoUrl['language_code'] ?? null);
                    $seoColumns = wela_allowed_field_map(
                        wela_required_array($seoUrl['columns'] ?? null, 'Kategorie-Sync-SEO benoetigt Spalten.'),
                        wela_allowed_tables()['xt_seo_url']['write_fields']
                    );

                    $linkType = isset($seoColumns['link_type']) ? (int) $seoColumns['link_type'] : 2;
                    $storeId = isset($seoColumns['store_id']) ? (int) $seoColumns['store_id'] : 1;
                    $seoIdentity = [
                        'link_type' => $linkType,
                        'link_id' => $categoryId,
                        'language_code' => $languageCode,
                        'store_id' => $storeId,
                    ];

                    unset($seoColumns['link_id'], $seoColumns['link_type'], $seoColumns['language_code'], $seoColumns['store_id']);

                    if (($seoUrl['auto_generate'] ?? false) === true) {
                        $seoColumns = wela_auto_generate_seo_columns(
                            $pdo,
                            is_string($seoUrl['auto_generate_class'] ?? null) ? (string) $seoUrl['auto_generate_class'] : 'category',
                            $linkType,
                            $categoryId,
                            $languageCode,
                            $storeId,
                            $seoColumns,
                            is_string($seoUrl['auto_generate_text'] ?? null) ? (string) $seoUrl['auto_generate_text'] : null
                        );
                        $seoColumns = wela_apply_auto_generated_seo_update($pdo, $seoIdentity, $seoColumns);
                    } else {
                        $seoColumns = wela_preserve_existing_seo_url_columns($pdo, $seoIdentity, $seoColumns);
                    }

                    wela_upsert_row(
                        $pdo,
                        'xt_seo_url',
                        ['link_type', 'link_id', 'language_code', 'store_id'],
                        $seoIdentity,
                        $seoColumns
                    );
                    $seoWrites++;
                }

                $pdo->commit();

                wela_respond(200, [
                    'ok' => true,
                    'data' => [
                        'category_id' => $categoryId,
                        'category_action' => $categoryResult['action'] ?? null,
                        'translations' => count($translations),
                        'seo_urls' => $seoWrites,
                    ],
                ]);
            } catch (Throwable $exception) {
                if ($pdo->inTransaction()) {
                    $pdo->rollBack();
                }

                throw $exception;
            }
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
                'ms_load_masters_main_img', 'products_price', 'date_added', 'last_modified',
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

function wela_table_columns(PDO $pdo, string $table): array
{
    static $cache = [];

    if (isset($cache[$table])) {
        return $cache[$table];
    }

    $stmt = $pdo->query(sprintf('SHOW COLUMNS FROM `%s`', $table));
    $columns = $stmt->fetchAll(PDO::FETCH_COLUMN);
    $columns = array_values(array_filter($columns, static fn (mixed $column): bool => is_string($column) && $column !== ''));

    if ($columns === []) {
        wela_respond(400, [
            'ok' => false,
            'error' => 'XT-Tabelle enthaelt keine lesbaren Spalten.',
        ]);
    }

    $cache[$table] = $columns;

    return $cache[$table];
}

function wela_table_primary_key(PDO $pdo, string $table): string|array
{
    static $cache = [];

    if (isset($cache[$table])) {
        return $cache[$table];
    }

    $stmt = $pdo->query(sprintf("SHOW INDEX FROM `%s` WHERE Key_name = 'PRIMARY'", $table));
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

    if ($rows !== []) {
        usort($rows, static fn (array $left, array $right): int => ((int) ($left['Seq_in_index'] ?? 0)) <=> ((int) ($right['Seq_in_index'] ?? 0)));
        $fields = array_values(array_filter(array_map(
            static fn (array $row): ?string => is_string($row['Column_name'] ?? null) ? $row['Column_name'] : null,
            $rows
        )));

        if ($fields !== []) {
            $cache[$table] = count($fields) === 1 ? $fields[0] : $fields;

            return $cache[$table];
        }
    }

    $columns = wela_table_columns($pdo, $table);
    $cache[$table] = $columns[0];

    return $cache[$table];
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

function wela_sync_product_request(PDO $pdo, array $request): array
{
    $product = wela_required_array($request['product'] ?? null, 'Produkt-Sync benoetigt einen Produktblock.');
    $productIdentity = wela_allowed_field_map(
        wela_required_array($product['identity'] ?? null, 'Produkt-Sync benoetigt eine Produkt-Identitaet.'),
        ['external_id']
    );
    $productColumns = wela_allowed_field_map(
        wela_required_array($product['columns'] ?? null, 'Produkt-Sync benoetigt Produktspalten.'),
        wela_allowed_tables()['xt_products']['write_fields']
    );
    $productColumns = wela_prepare_product_columns($pdo, $productIdentity, $productColumns);
    $translations = wela_optional_array_list($request['translations'] ?? null, 'Produkt-Sync-Uebersetzungen muessen eine Liste sein.');
    $categoryRelations = wela_optional_array_list($request['category_relations'] ?? null, 'Produkt-Sync-Kategorien muessen eine Liste sein.');
    $attributeEntities = wela_optional_array_list($request['attribute_entities'] ?? null, 'Produkt-Sync-Attribute muessen eine Liste sein.');
    $attributeDescriptions = wela_optional_array_list($request['attribute_descriptions'] ?? null, 'Produkt-Sync-Attribut-Uebersetzungen muessen eine Liste sein.');
    $attributeRelations = wela_optional_array_list($request['attribute_relations'] ?? null, 'Produkt-Sync-Attribut-Links muessen eine Liste sein.');
    $seoUrls = wela_optional_array_list($request['seo_urls'] ?? null, 'Produkt-Sync-SEO-URLs muessen eine Liste sein.');
    $replaceCategories = (bool) ($request['replace_categories'] ?? false);
    $replaceAttributes = (bool) ($request['replace_attributes'] ?? false);

    $pdo->beginTransaction();

    try {
        $productResult = wela_upsert_row($pdo, 'xt_products', 'products_id', $productIdentity, $productColumns);
        $productId = (int) ($productResult['primary_key_value'] ?? 0);

        if ($productId <= 0) {
            throw new RuntimeException('Produkt-Sync konnte keine gueltige XT-Produkt-ID ermitteln.');
        }

        foreach ($translations as $translation) {
            $languageCode = wela_allowed_language($translation['language_code'] ?? null);
            $translationColumns = wela_allowed_field_map(
                wela_required_array($translation['columns'] ?? null, 'Produkt-Sync-Uebersetzung benoetigt Spalten.'),
                wela_allowed_tables()['xt_products_description']['write_fields']
            );
            unset($translationColumns['products_id']);

            wela_upsert_row(
                $pdo,
                'xt_products_description',
                ['products_id', 'language_code'],
                [
                    'products_id' => $productId,
                    'language_code' => $languageCode,
                ],
                $translationColumns
            );
        }

        if ($replaceCategories) {
            wela_delete_rows($pdo, 'xt_products_to_categories', [
                'products_id' => $productId,
            ]);
        }

        foreach ($categoryRelations as $relation) {
            $relationColumns = wela_allowed_field_map(
                wela_required_array($relation['columns'] ?? null, 'Produkt-Sync-Kategorie benoetigt Spalten.'),
                wela_allowed_tables()['xt_products_to_categories']['write_fields']
            );
            $categoryId = isset($relationColumns['categories_id']) ? (int) $relationColumns['categories_id'] : 0;

            if ($categoryId <= 0) {
                throw new RuntimeException('Produkt-Sync-Kategorie enthaelt keine gueltige categories_id.');
            }

            unset($relationColumns['products_id'], $relationColumns['categories_id']);

            wela_upsert_row(
                $pdo,
                'xt_products_to_categories',
                ['products_id', 'categories_id'],
                [
                    'products_id' => $productId,
                    'categories_id' => $categoryId,
                ],
                $relationColumns
            );
        }

        $attributeIdMap = [];

        foreach ($attributeEntities as $attributeEntity) {
            $attributeModel = wela_required_non_empty_string(
                $attributeEntity['attribute_model'] ?? null,
                'Produkt-Sync-Attribut benoetigt attribute_model.'
            );
            $parentAttributeModel = trim((string) ($attributeEntity['parent_attribute_model'] ?? ''));
            $attributeColumns = wela_allowed_field_map(
                wela_required_array($attributeEntity['columns'] ?? null, 'Produkt-Sync-Attribut benoetigt Spalten.'),
                wela_allowed_tables()['xt_plg_products_attributes']['write_fields']
            );

            if ($parentAttributeModel !== '') {
                if (!isset($attributeIdMap[$parentAttributeModel])) {
                    throw new RuntimeException("Produkt-Sync kennt kein Parent-Attribut fuer '{$parentAttributeModel}'.");
                }

                $attributeColumns['attributes_parent'] = $attributeIdMap[$parentAttributeModel];
            }

            $attributeResult = wela_upsert_row(
                $pdo,
                'xt_plg_products_attributes',
                'attributes_id',
                ['attributes_model' => $attributeModel],
                $attributeColumns
            );
            $attributeId = (int) ($attributeResult['primary_key_value'] ?? 0);

            if ($attributeId <= 0) {
                throw new RuntimeException("Produkt-Sync konnte keine XT-Attribut-ID fuer '{$attributeModel}' ermitteln.");
            }

            $attributeIdMap[$attributeModel] = $attributeId;
        }

        foreach ($attributeDescriptions as $attributeDescription) {
            $attributeModel = wela_required_non_empty_string(
                $attributeDescription['attribute_model'] ?? null,
                'Produkt-Sync-Attribut-Uebersetzung benoetigt attribute_model.'
            );
            $languageCode = wela_allowed_language($attributeDescription['language_code'] ?? null);

            if (!isset($attributeIdMap[$attributeModel])) {
                throw new RuntimeException("Produkt-Sync kennt kein XT-Attribut fuer '{$attributeModel}'.");
            }

            $descriptionColumns = wela_allowed_field_map(
                wela_required_array($attributeDescription['columns'] ?? null, 'Produkt-Sync-Attribut-Uebersetzung benoetigt Spalten.'),
                wela_allowed_tables()['xt_plg_products_attributes_description']['write_fields']
            );
            unset($descriptionColumns['attributes_id']);

            wela_upsert_row(
                $pdo,
                'xt_plg_products_attributes_description',
                ['attributes_id', 'language_code'],
                [
                    'attributes_id' => $attributeIdMap[$attributeModel],
                    'language_code' => $languageCode,
                ],
                $descriptionColumns
            );
        }

        if ($replaceAttributes) {
            wela_delete_rows($pdo, 'xt_plg_products_to_attributes', [
                'products_id' => $productId,
            ]);
        }

        foreach ($attributeRelations as $attributeRelation) {
            $attributeModel = wela_required_non_empty_string(
                $attributeRelation['attribute_model'] ?? null,
                'Produkt-Sync-Attribut-Link benoetigt attribute_model.'
            );
            $parentAttributeModel = trim((string) ($attributeRelation['parent_attribute_model'] ?? ''));

            if (!isset($attributeIdMap[$attributeModel])) {
                throw new RuntimeException("Produkt-Sync kennt kein XT-Attribut fuer '{$attributeModel}'.");
            }

            $relationColumns = wela_allowed_field_map(
                wela_required_array($attributeRelation['columns'] ?? null, 'Produkt-Sync-Attribut-Link benoetigt Spalten.'),
                wela_allowed_tables()['xt_plg_products_to_attributes']['write_fields']
            );

            if ($parentAttributeModel !== '') {
                if (!isset($attributeIdMap[$parentAttributeModel])) {
                    throw new RuntimeException("Produkt-Sync kennt kein Parent-Attribut fuer '{$parentAttributeModel}'.");
                }

                $relationColumns['attributes_parent_id'] = $attributeIdMap[$parentAttributeModel];
            }

            unset($relationColumns['products_id'], $relationColumns['attributes_id']);

            wela_upsert_row(
                $pdo,
                'xt_plg_products_to_attributes',
                ['products_id', 'attributes_id'],
                [
                    'products_id' => $productId,
                    'attributes_id' => $attributeIdMap[$attributeModel],
                ],
                $relationColumns
            );
        }

        $seoWrites = 0;

        foreach ($seoUrls as $seoUrl) {
            $languageCode = wela_allowed_language($seoUrl['language_code'] ?? null);
            $seoColumns = wela_allowed_field_map(
                wela_required_array($seoUrl['columns'] ?? null, 'Produkt-Sync-SEO benoetigt Spalten.'),
                wela_allowed_tables()['xt_seo_url']['write_fields']
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
                $seoColumns = wela_auto_generate_seo_columns(
                    $pdo,
                    is_string($seoUrl['auto_generate_class'] ?? null) ? (string) $seoUrl['auto_generate_class'] : 'product',
                    $linkType,
                    $productId,
                    $languageCode,
                    $storeId,
                    $seoColumns,
                    is_string($seoUrl['auto_generate_text'] ?? null) ? (string) $seoUrl['auto_generate_text'] : null
                );
                $seoColumns = wela_apply_auto_generated_seo_update($pdo, $seoIdentity, $seoColumns);
            } else {
                $seoColumns = wela_preserve_existing_seo_url_columns($pdo, $seoIdentity, $seoColumns);
            }

            wela_upsert_row(
                $pdo,
                'xt_seo_url',
                ['link_type', 'link_id', 'language_code', 'store_id'],
                $seoIdentity,
                $seoColumns
            );
            $seoWrites++;
        }

        $pdo->commit();

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
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }

        throw $exception;
    }
}

function wela_prepare_product_columns(PDO $pdo, array $identity, array $columns): array
{
    if (array_key_exists('products_master_slave_order', $columns)) {
        return $columns;
    }

    $stmt = $pdo->prepare('SELECT products_id FROM `xt_products` WHERE `external_id` = :external_id LIMIT 1');
    $stmt->execute([
        ':external_id' => $identity['external_id'] ?? null,
    ]);

    if ($stmt->fetchColumn() !== false) {
        return $columns;
    }

    $columns['products_master_slave_order'] = 0;

    return $columns;
}

function wela_preserve_existing_seo_url_columns(PDO $pdo, array $identity, array $columns): array
{
    $stmt = $pdo->prepare(
        sprintf(
            'SELECT 1 FROM `xt_seo_url` WHERE %s LIMIT 1',
            wela_where_clause($identity)
        )
    );
    $stmt->execute(wela_sql_params($identity));

    if (!$stmt->fetchColumn()) {
        return $columns;
    }

    unset($columns['url_text'], $columns['url_md5']);

    return $columns;
}

function wela_store_document_file(array $config, string $fileName, string $contentBase64, ?string $targetPath = null): array
{
    $safeFileName = basename(str_replace('\\', '/', $fileName));

    if ($safeFileName === '' || $safeFileName === '.' || $safeFileName === '..') {
        throw new RuntimeException('Dokument-Dateiname ist ungueltig.');
    }

    $binary = base64_decode($contentBase64, true);
    if (!is_string($binary)) {
        throw new RuntimeException('Dokument-Inhalt ist kein gueltiges Base64.');
    }

    $targetDir = wela_resolve_document_target_directory($config, $targetPath, true);
    $targetPath = $targetDir . DIRECTORY_SEPARATOR . $safeFileName;

    if (file_put_contents($targetPath, $binary, LOCK_EX) === false) {
        throw new RuntimeException("Dokument konnte nicht nach '{$targetPath}' geschrieben werden.");
    }

    return [
        'file_name' => $safeFileName,
        'stored_path' => $targetPath,
        'target_directory' => $targetDir,
        'bytes_written' => strlen($binary),
    ];
}

function wela_browse_server_directories(array $config, ?string $path = null): array
{
    $resolved = wela_resolve_document_target_directory($config, $path, false);
    $entries = scandir($resolved);

    if (!is_array($entries)) {
        throw new RuntimeException("Verzeichnis '{$resolved}' konnte nicht gelesen werden.");
    }

    $directories = [];

    foreach ($entries as $entry) {
        if ($entry === '.' || $entry === '..') {
            continue;
        }

        $fullPath = $resolved . DIRECTORY_SEPARATOR . $entry;
        if (!is_dir($fullPath)) {
            continue;
        }

        $normalizedPath = realpath($fullPath);
        if (!is_string($normalizedPath) || $normalizedPath === '') {
            continue;
        }

        $directories[] = [
            'name' => $entry,
            'path' => $normalizedPath,
            'has_children' => wela_directory_has_children($normalizedPath),
        ];
    }

    usort($directories, static fn (array $left, array $right): int => strcmp((string) ($left['name'] ?? ''), (string) ($right['name'] ?? '')));

    return [
        'current_path' => $resolved,
        'parent_path' => dirname($resolved) !== $resolved ? dirname($resolved) : null,
        'directories' => $directories,
    ];
}

function wela_resolve_document_target_directory(array $config, ?string $requestedPath = null, bool $createIfMissing = false): string
{
    $candidate = $requestedPath;

    if ($candidate === null || trim($candidate) === '') {
        $configuredPath = $config['document_upload_path'] ?? null;
        $candidate = is_string($configuredPath) && trim($configuredPath) !== ''
            ? trim($configuredPath)
            : dirname(__DIR__) . DIRECTORY_SEPARATOR . 'media' . DIRECTORY_SEPARATOR . 'files';
    }

    $absolute = wela_absolute_shop_path($candidate);

    if ($createIfMissing) {
        if (!is_dir($absolute) && !mkdir($absolute, 0775, true) && !is_dir($absolute)) {
            throw new RuntimeException("Zielverzeichnis '{$absolute}' konnte nicht erstellt werden.");
        }
    }

    return wela_existing_or_parent_directory($absolute);
}

function wela_absolute_shop_path(string $path): string
{
    $path = trim(str_replace('\\', DIRECTORY_SEPARATOR, $path));
    if ($path === '') {
        throw new RuntimeException('Dokumentpfad ist leer.');
    }

    if ($path[0] === DIRECTORY_SEPARATOR || preg_match('/^[A-Za-z]:[\/\\\\]/', $path) === 1) {
        return $path;
    }

    return dirname(__DIR__) . DIRECTORY_SEPARATOR . ltrim($path, DIRECTORY_SEPARATOR);
}

function wela_existing_or_parent_directory(string $path): string
{
    $candidate = $path;

    while ($candidate !== '' && !is_dir($candidate)) {
        $parent = dirname($candidate);
        if ($parent === $candidate) {
            break;
        }
        $candidate = $parent;
    }

    if ($candidate === '' || !is_dir($candidate)) {
        throw new RuntimeException("Dokumentpfad '{$path}' existiert nicht.");
    }

    $resolved = realpath($candidate);
    if (!is_string($resolved) || $resolved === '') {
        throw new RuntimeException("Dokumentpfad '{$candidate}' konnte nicht aufgeloest werden.");
    }

    return $resolved;
}

function wela_directory_has_children(string $path): bool
{
    $entries = scandir($path);
    if (!is_array($entries)) {
        return false;
    }

    foreach ($entries as $entry) {
        if ($entry === '.' || $entry === '..') {
            continue;
        }

        if (is_dir($path . DIRECTORY_SEPARATOR . $entry)) {
            return true;
        }
    }

    return false;
}

function wela_refresh_shop_state(): array
{
    $shopRoot = dirname(__DIR__);
    $targets = [
        'cache',
        'templates_c',
    ];
    $results = [];
    $totalRemovedFiles = 0;
    $totalRemovedDirectories = 0;

    foreach ($targets as $relativePath) {
        $path = $shopRoot . DIRECTORY_SEPARATOR . $relativePath;
        $stats = wela_clear_directory_contents($path);
        $results[$relativePath] = $stats;
        $totalRemovedFiles += (int) ($stats['removed_files'] ?? 0);
        $totalRemovedDirectories += (int) ($stats['removed_directories'] ?? 0);
    }

    clearstatcache(true);

    return [
        'shop_root' => $shopRoot,
        'targets' => $results,
        'removed_files' => $totalRemovedFiles,
        'removed_directories' => $totalRemovedDirectories,
    ];
}

function wela_clear_directory_contents(string $path): array
{
    if (!is_dir($path)) {
        return [
            'path' => $path,
            'exists' => false,
            'removed_files' => 0,
            'removed_directories' => 0,
        ];
    }

    $removedFiles = 0;
    $removedDirectories = 0;
    $entries = scandir($path);

    if (!is_array($entries)) {
        throw new RuntimeException("Cache-Verzeichnis '{$path}' konnte nicht gelesen werden.");
    }

    foreach ($entries as $entry) {
        if ($entry === '.' || $entry === '..' || in_array($entry, ['.htaccess', 'index.html', 'index.htm', '.gitignore'], true)) {
            continue;
        }

        $entryPath = $path . DIRECTORY_SEPARATOR . $entry;
        $stats = wela_remove_path_recursive($entryPath);
        $removedFiles += (int) ($stats['removed_files'] ?? 0);
        $removedDirectories += (int) ($stats['removed_directories'] ?? 0);
    }

    return [
        'path' => $path,
        'exists' => true,
        'removed_files' => $removedFiles,
        'removed_directories' => $removedDirectories,
    ];
}

function wela_remove_path_recursive(string $path): array
{
    if (!file_exists($path) && !is_link($path)) {
        return ['removed_files' => 0, 'removed_directories' => 0];
    }

    if (is_link($path) || is_file($path)) {
        if (!unlink($path)) {
            throw new RuntimeException("Cache-Datei '{$path}' konnte nicht geloescht werden.");
        }

        return ['removed_files' => 1, 'removed_directories' => 0];
    }

    if (!is_dir($path)) {
        return ['removed_files' => 0, 'removed_directories' => 0];
    }

    $removedFiles = 0;
    $removedDirectories = 0;
    $entries = scandir($path);

    if (!is_array($entries)) {
        throw new RuntimeException("Cache-Pfad '{$path}' konnte nicht gelesen werden.");
    }

    foreach ($entries as $entry) {
        if ($entry === '.' || $entry === '..') {
            continue;
        }

        $stats = wela_remove_path_recursive($path . DIRECTORY_SEPARATOR . $entry);
        $removedFiles += (int) ($stats['removed_files'] ?? 0);
        $removedDirectories += (int) ($stats['removed_directories'] ?? 0);
    }

    if (!rmdir($path)) {
        throw new RuntimeException("Cache-Verzeichnis '{$path}' konnte nicht geloescht werden.");
    }

    return [
        'removed_files' => $removedFiles,
        'removed_directories' => $removedDirectories + 1,
    ];
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
