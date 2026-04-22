<?php

declare(strict_types=1);

final class WelaApiClient
{
    public function __construct(
        private string $baseUrl,
        private string $apiKey,
        private int $timeoutSeconds = 30
    ) {
        $this->baseUrl = rtrim(trim($this->baseUrl), '/');
        $this->timeoutSeconds = max(1, $this->timeoutSeconds);
    }

    public function isConfigured(): bool
    {
        return $this->baseUrl !== '' && $this->apiKey !== '';
    }

    public function health(): array
    {
        return $this->request('health', []);
    }

    public function lookupMap(string $table, string $keyField, string $valueField): array
    {
        $response = $this->request('lookup_map', [
            'table' => $table,
            'key_field' => $keyField,
            'value_field' => $valueField,
        ]);

        $data = $response['data'] ?? null;

        if (!is_array($data)) {
            throw new RuntimeException('XT-API lieferte keine gueltige Lookup-Map.');
        }

        return $data;
    }

    public function fetchRows(string $table, array $fields, int $offset = 0, int $limit = 500): array
    {
        $response = $this->request('fetch_rows', [
            'table' => $table,
            'fields' => array_values($fields),
            'offset' => max(0, $offset),
            'limit' => max(1, $limit),
        ]);
        $data = $response['data'] ?? null;

        if (!is_array($data)) {
            throw new RuntimeException('XT-API lieferte kein gueltiges Snapshot-Ergebnis.');
        }

        $rows = $data['rows'] ?? null;
        if (!is_array($rows)) {
            throw new RuntimeException('XT-API Snapshot-Antwort enthaelt keine gueltigen Zeilen.');
        }

        return [
            'rows' => $rows,
            'total' => (int) ($data['total'] ?? 0),
            'next_offset' => isset($data['next_offset']) ? (int) $data['next_offset'] : null,
            'limit' => (int) ($data['limit'] ?? $limit),
        ];
    }

    public function upsertRow(string $table, array $identity, array $columns, string|array $primaryKey): array
    {
        $response = $this->request('upsert_row', [
            'table' => $table,
            'identity' => $identity,
            'columns' => $columns,
            'primary_key' => $primaryKey,
        ]);

        $data = $response['data'] ?? null;

        if (!is_array($data)) {
            throw new RuntimeException('XT-API lieferte kein gueltiges Upsert-Ergebnis.');
        }

        return $data;
    }

    public function deleteRows(string $table, array $where): int
    {
        $response = $this->request('delete_rows', [
            'table' => $table,
            'where' => $where,
        ]);

        $data = $response['data'] ?? null;

        if (!is_array($data)) {
            throw new RuntimeException('XT-API lieferte kein gueltiges Delete-Ergebnis.');
        }

        return (int) ($data['deleted'] ?? 0);
    }

    public function syncProduct(array $payload): array
    {
        $response = $this->request('sync_product', $payload);
        $data = $response['data'] ?? null;

        if (!is_array($data)) {
            throw new RuntimeException('XT-API lieferte kein gueltiges Produkt-Sync-Ergebnis.');
        }

        return $data;
    }

    public function syncProductsBatch(array $items): array
    {
        $response = $this->request('sync_products_batch', [
            'items' => array_values($items),
        ]);
        $data = $response['data'] ?? null;

        if (!is_array($data)) {
            throw new RuntimeException('XT-API lieferte kein gueltiges Produkt-Batch-Ergebnis.');
        }

        return $data;
    }

    public function syncCategory(array $payload): array
    {
        $response = $this->request('sync_category', $payload);
        $data = $response['data'] ?? null;

        if (!is_array($data)) {
            throw new RuntimeException('XT-API lieferte kein gueltiges Kategorie-Sync-Ergebnis.');
        }

        return $data;
    }

    public function refreshShopState(): array
    {
        $response = $this->request('refresh_shop_state', []);
        $data = $response['data'] ?? null;

        if (!is_array($data)) {
            throw new RuntimeException('XT-API lieferte kein gueltiges Shop-Refresh-Ergebnis.');
        }

        return $data;
    }

    public function uploadDocumentFile(string $fileName, string $contentBase64): array
    {
        return $this->uploadDocumentFileToPath($fileName, $contentBase64, null);
    }

    public function uploadDocumentFileToPath(string $fileName, string $contentBase64, ?string $targetPath): array
    {
        $payload = [
            'file_name' => $fileName,
            'content_base64' => $contentBase64,
        ];

        if (is_string($targetPath) && trim($targetPath) !== '') {
            $payload['target_path'] = trim($targetPath);
        }

        $response = $this->request('upload_document_file', $payload);
        $data = $response['data'] ?? null;

        if (!is_array($data)) {
            throw new RuntimeException('XT-API lieferte kein gueltiges Dokument-Upload-Ergebnis.');
        }

        return $data;
    }

    public function browseServerDirectories(?string $path = null): array
    {
        $payload = [];

        if (is_string($path) && trim($path) !== '') {
            $payload['path'] = trim($path);
        }

        $response = $this->request('browse_server_directories', $payload);
        $data = $response['data'] ?? null;

        if (!is_array($data)) {
            throw new RuntimeException('XT-API lieferte kein gueltiges Browse-Ergebnis.');
        }

        return $data;
    }

    private function request(string $action, array $body): array
    {
        if (!$this->isConfigured()) {
            throw new RuntimeException('XT-API ist nicht konfiguriert.');
        }

        $encodedBody = json_encode($body, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        if (!is_string($encodedBody)) {
            throw new RuntimeException('XT-Request konnte nicht serialisiert werden.');
        }

        $timestamp = (string) time();
        $signature = hash_hmac('sha256', $timestamp . '.' . $encodedBody, $this->apiKey);

        $context = stream_context_create([
            'http' => [
                'method' => 'POST',
                'ignore_errors' => true,
                'timeout' => $this->timeoutSeconds,
                'header' => implode("\r\n", [
                    'Content-Type: application/json',
                    'Accept: application/json',
                    'X-Wela-Key: ' . $this->apiKey,
                    'X-Wela-Timestamp: ' . $timestamp,
                    'X-Wela-Signature: ' . $signature,
                    'Content-Length: ' . strlen($encodedBody),
                ]),
                'content' => $encodedBody,
            ],
        ]);

        $responseBody = @file_get_contents($this->actionUrl($action), false, $context);
        $statusCode = $this->responseStatusCode($http_response_header ?? []);

        if ($responseBody === false) {
            $message = error_get_last()['message'] ?? 'Unbekannter HTTP-Fehler';
            throw new RuntimeException('XT-API Request fehlgeschlagen: ' . $message);
        }

        $decoded = json_decode($responseBody, true);
        if (!is_array($decoded)) {
            throw new RuntimeException('XT-API lieferte kein gueltiges JSON.');
        }

        if ($statusCode >= 400 || !($decoded['ok'] ?? false)) {
            $message = $decoded['error'] ?? ('XT-API Fehler mit HTTP-Status ' . $statusCode);
            throw new RuntimeException((string) $message);
        }

        return $decoded;
    }

    private function actionUrl(string $action): string
    {
        if (str_contains($this->baseUrl, '?')) {
            return $this->baseUrl . '&action=' . rawurlencode($action);
        }

        return rtrim($this->baseUrl, '/') . '/?action=' . rawurlencode($action);
    }

    private function responseStatusCode(array $headers): int
    {
        foreach ($headers as $header) {
            if (!is_string($header) || !preg_match('/^HTTP\/\S+\s+(\d{3})\b/', $header, $matches)) {
                continue;
            }

            return (int) $matches[1];
        }

        return 0;
    }
}
