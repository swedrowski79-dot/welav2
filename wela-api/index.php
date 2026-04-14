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
