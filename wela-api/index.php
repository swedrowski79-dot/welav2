<?php

declare(strict_types=1);

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
            $table = wela_allowed_table($request['table'] ?? null, array_keys(wela_allowed_tables()));
            $tableConfig = wela_allowed_tables()[$table];
            $fields = wela_allowed_field_list($request['fields'] ?? null, $tableConfig['read_fields']);
            $offset = max(0, (int) ($request['offset'] ?? 0));
            $limit = min(2000, max(1, (int) ($request['limit'] ?? 500)));

            if ($fields === []) {
                $fields = $tableConfig['read_fields'];
            }

            $countStmt = $pdo->query(sprintf('SELECT COUNT(*) FROM `%s`', $table));
            $total = (int) $countStmt->fetchColumn();

            $stmt = $pdo->prepare(sprintf(
                'SELECT %s FROM `%s` ORDER BY %s LIMIT %d OFFSET %d',
                wela_select_columns($fields),
                $table,
                wela_order_clause($tableConfig['primary_key']),
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

        case 'upsert_row':
            $table = wela_allowed_table($request['table'] ?? null, ['xt_media', 'xt_media_link']);
            $tableConfig = wela_allowed_tables()[$table];
            $primaryKey = wela_allowed_field($request['primary_key'] ?? null, [$tableConfig['primary_key']]);
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
            $table = wela_allowed_table($request['table'] ?? null, ['xt_media_link']);
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

        case 'sync_product':
            $product = wela_required_array($request['product'] ?? null, 'Produkt-Sync benoetigt einen Produktblock.');
            $productIdentity = wela_allowed_field_map(
                wela_required_array($product['identity'] ?? null, 'Produkt-Sync benoetigt eine Produkt-Identitaet.'),
                ['external_id']
            );
            $productColumns = wela_allowed_field_map(
                wela_required_array($product['columns'] ?? null, 'Produkt-Sync benoetigt Produktspalten.'),
                wela_allowed_tables()['xt_products']['write_fields']
            );
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
                    $attributeColumns = wela_allowed_field_map(
                        wela_required_array($attributeEntity['columns'] ?? null, 'Produkt-Sync-Attribut benoetigt Spalten.'),
                        wela_allowed_tables()['xt_plg_products_attributes']['write_fields']
                    );
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

                    if (!isset($attributeIdMap[$attributeModel])) {
                        throw new RuntimeException("Produkt-Sync kennt kein XT-Attribut fuer '{$attributeModel}'.");
                    }

                    $relationColumns = wela_allowed_field_map(
                        wela_required_array($attributeRelation['columns'] ?? null, 'Produkt-Sync-Attribut-Link benoetigt Spalten.'),
                        wela_allowed_tables()['xt_plg_products_to_attributes']['write_fields']
                    );
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

                if ($seoUrls !== [] && !wela_seo_rows_exist($pdo, 1, $productId)) {
                    foreach ($seoUrls as $seoUrl) {
                        $languageCode = wela_allowed_language($seoUrl['language_code'] ?? null);
                        $seoColumns = wela_allowed_field_map(
                            wela_required_array($seoUrl['columns'] ?? null, 'Produkt-Sync-SEO benoetigt Spalten.'),
                            wela_allowed_tables()['xt_seo_url']['write_fields']
                        );

                        $linkType = isset($seoColumns['link_type']) ? (int) $seoColumns['link_type'] : 1;
                        $storeId = isset($seoColumns['store_id']) ? (int) $seoColumns['store_id'] : 1;

                        unset($seoColumns['link_id'], $seoColumns['link_type'], $seoColumns['language_code'], $seoColumns['store_id']);

                        wela_upsert_row(
                            $pdo,
                            'xt_seo_url',
                            ['link_type', 'link_id', 'language_code', 'store_id'],
                            [
                                'link_type' => $linkType,
                                'link_id' => $productId,
                                'language_code' => $languageCode,
                                'store_id' => $storeId,
                            ],
                            $seoColumns
                        );
                        $seoWrites++;
                    }
                }

                $pdo->commit();

                wela_respond(200, [
                    'ok' => true,
                    'data' => [
                        'product_id' => $productId,
                        'product_action' => $productResult['action'] ?? null,
                        'translations' => count($translations),
                        'category_relations' => count($categoryRelations),
                        'attribute_entities' => count($attributeEntities),
                        'attribute_descriptions' => count($attributeDescriptions),
                        'attribute_relations' => count($attributeRelations),
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
    return [
        'xt_products' => [
            'primary_key' => 'products_id',
            'read_fields' => [
                'products_id', 'external_id', 'products_model', 'products_ean', 'products_quantity',
                'products_price', 'products_weight', 'products_status', 'products_master_flag',
                'products_master_model', 'products_image', 'last_modified',
            ],
            'write_fields' => [
                'products_id', 'external_id', 'permission_id', 'products_owner', 'products_ean',
                'products_quantity', 'show_stock', 'products_average_quantity', 'products_shippingtime',
                'products_shippingtime_nostock', 'products_model', 'products_master_flag',
                'products_master_model', 'ms_open_first_slave', 'ms_show_slave_list',
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
            'write_fields' => [],
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
            'read_fields' => ['id', 'external_id', 'file', 'type', 'class', 'status', 'last_modified'],
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
            'read_fields' => ['attributes_id', 'attributes_parent', 'attributes_model', 'sort_order', 'status'],
            'write_fields' => ['attributes_id', 'attributes_parent', 'attributes_model', 'sort_order', 'status'],
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

function wela_seo_rows_exist(PDO $pdo, int $linkType, int $linkId): bool
{
    $stmt = $pdo->prepare(
        'SELECT 1 FROM `xt_seo_url` WHERE `link_type` = :link_type AND `link_id` = :link_id LIMIT 1'
    );
    $stmt->execute([
        ':link_type' => $linkType,
        ':link_id' => $linkId,
    ]);

    return (bool) $stmt->fetchColumn();
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
