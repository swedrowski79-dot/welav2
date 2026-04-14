<?php

declare(strict_types=1);

namespace App\Web\Repository;

final class EnvFileRepository
{
    public function __construct(private ?string $path = null)
    {
        $this->path ??= dirname(__DIR__, 3) . '/.env';
    }

    public function load(): array
    {
        if (!is_file($this->path)) {
            return [];
        }

        $values = [];

        foreach (file($this->path, FILE_IGNORE_NEW_LINES) ?: [] as $line) {
            $trimmed = trim($line);

            if ($trimmed === '' || str_starts_with($trimmed, '#')) {
                continue;
            }

            $parts = explode('=', $trimmed, 2);
            if (count($parts) !== 2) {
                continue;
            }

            [$name, $value] = $parts;
            $name = trim($name);
            $value = trim($value);

            if ($name === '') {
                continue;
            }

            $values[$name] = $this->decodeValue($value);
        }

        return $values;
    }

    public function save(array $updates): void
    {
        $current = $this->load();
        $merged = array_merge($current, $updates);
        ksort($merged);

        $lines = [];
        foreach ($merged as $name => $value) {
            $lines[] = $name . '=' . $this->encodeValue((string) $value);
        }

        file_put_contents($this->path, implode(PHP_EOL, $lines) . PHP_EOL, LOCK_EX);
    }

    private function decodeValue(string $value): string
    {
        $first = $value[0] ?? '';
        $last = $value[strlen($value) - 1] ?? '';

        if (($first === '"' && $last === '"') || ($first === "'" && $last === "'")) {
            return stripcslashes(substr($value, 1, -1));
        }

        return $value;
    }

    private function encodeValue(string $value): string
    {
        return '"' . addcslashes($value, "\\\"\n\r\t") . '"';
    }
}
