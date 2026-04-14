<?php

declare(strict_types=1);

namespace App\Web\Core;

final class Request
{
    public function __construct(
        private array $post,
        private array $query,
        private array $server
    ) {
    }

    public static function capture(): self
    {
        return new self($_POST, $_GET, $_SERVER);
    }

    public function method(): string
    {
        return strtoupper((string) ($this->server['REQUEST_METHOD'] ?? 'GET'));
    }

    public function path(): string
    {
        $uri = $this->server['REQUEST_URI'] ?? '/';
        $path = (string) parse_url($uri, PHP_URL_PATH);

        return rtrim($path, '/') ?: '/';
    }

    public function query(string $key, mixed $default = null): mixed
    {
        return $this->query[$key] ?? $default;
    }

    public function post(string $key, mixed $default = null): mixed
    {
        return $this->post[$key] ?? $default;
    }

    public function int(string $key, int $default = 0): int
    {
        return max(0, (int) $this->query($key, $default));
    }

    public function string(string $key, string $default = ''): string
    {
        return trim((string) $this->query($key, $default));
    }

    public function postString(string $key, string $default = ''): string
    {
        return trim((string) $this->post($key, $default));
    }
}
