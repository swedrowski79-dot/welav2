<?php

declare(strict_types=1);

namespace App\Web\Repository;

final class XtApiClient
{
    public function __construct(
        private string $baseUrl,
        private string $apiKey
    ) {
    }

    public function health(): array
    {
        return $this->request('health');
    }

    private function request(string $action, array $payload = []): array
    {
        $url = rtrim($this->baseUrl, '/') . '/?action=' . rawurlencode($action);
        $body = json_encode($payload, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) ?: '{}';
        $timestamp = (string) time();
        $signature = hash_hmac('sha256', $timestamp . '.' . $body, $this->apiKey);

        $context = stream_context_create([
            'http' => [
                'method' => 'POST',
                'header' => implode("\r\n", [
                    'Content-Type: application/json',
                    'Accept: application/json',
                    'X-Wela-Key: ' . $this->apiKey,
                    'X-Wela-Timestamp: ' . $timestamp,
                    'X-Wela-Signature: ' . $signature,
                    'Content-Length: ' . strlen($body),
                ]),
                'content' => $body,
                'ignore_errors' => true,
                'timeout' => 10,
            ],
        ]);

        $response = @file_get_contents($url, false, $context);
        $statusLine = $http_response_header[0] ?? '';

        if ($response === false) {
            throw new \RuntimeException('XT-API nicht erreichbar.');
        }

        if (!preg_match('/\s(\d{3})\s/', $statusLine, $matches)) {
            throw new \RuntimeException('XT-API Antwort konnte nicht interpretiert werden.');
        }

        $statusCode = (int) $matches[1];
        $decoded = json_decode($response, true);

        if (!is_array($decoded)) {
            throw new \RuntimeException('XT-API liefert kein gueltiges JSON.');
        }

        if ($statusCode >= 400 || !($decoded['ok'] ?? false)) {
            $message = $decoded['error'] ?? ('XT-API Fehler HTTP ' . $statusCode);
            throw new \RuntimeException((string) $message);
        }

        return $decoded;
    }
}
