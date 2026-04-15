<?php

declare(strict_types=1);

final class WelaApiClient
{
    public function __construct(
        private string $baseUrl,
        private string $apiKey
    ) {
        $this->baseUrl = rtrim(trim($this->baseUrl), '/');
    }

    public function isConfigured(): bool
    {
        return $this->baseUrl !== '' && $this->apiKey !== '';
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

    public function upsertRow(string $table, array $identity, array $columns, string $primaryKey): array
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
                'timeout' => 30,
                'header' => implode("\r\n", [
                    'Content-Type: application/json',
                    'X-Wela-Key: ' . $this->apiKey,
                    'X-Wela-Timestamp: ' . $timestamp,
                    'X-Wela-Signature: ' . $signature,
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
        $separator = str_contains($this->baseUrl, '?') ? '&' : '?';

        return $this->baseUrl . $separator . 'action=' . rawurlencode($action);
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
