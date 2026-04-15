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
            $table = wela_allowed_table($request['table'] ?? null, ['xt_products', 'xt_media']);
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
            'read_fields' => ['products_id', 'external_id'],
            'write_fields' => [],
        ],
        'xt_media' => [
            'primary_key' => 'id',
            'read_fields' => ['id', 'external_id'],
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

function wela_upsert_row(PDO $pdo, string $table, string $primaryKey, array $identity, array $columns): array
{
    $selectFields = array_values(array_unique(array_merge([$primaryKey], array_keys($identity), array_keys($columns))));
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

        $primaryKeyValue = $insertValues[$primaryKey] ?? $pdo->lastInsertId();

        return [
            'action' => 'inserted',
            'primary_key_value' => ctype_digit((string) $primaryKeyValue) ? (int) $primaryKeyValue : $primaryKeyValue,
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
        'primary_key_value' => ctype_digit((string) ($existing[$primaryKey] ?? '')) ? (int) $existing[$primaryKey] : ($existing[$primaryKey] ?? null),
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
