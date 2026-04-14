<?php

declare(strict_types=1);

namespace App\Web\Core;

final class Html
{
    public static function escape(mixed $value): string
    {
        return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
    }

    public static function badgeClass(string $status): string
    {
        return match ($status) {
            'success', 'reachable', 'resolved', 'info' => 'text-bg-success',
            'running', 'warning' => 'text-bg-warning',
            'failed', 'error', 'unreachable', 'open' => 'text-bg-danger',
            default => 'text-bg-secondary',
        };
    }

    public static function buildUrl(string $path, array $query = []): string
    {
        $qs = http_build_query(array_filter($query, static fn ($value) => $value !== '' && $value !== null));

        return $qs === '' ? $path : $path . '?' . $qs;
    }
}
