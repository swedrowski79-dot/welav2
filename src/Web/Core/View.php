<?php

declare(strict_types=1);

namespace App\Web\Core;

final class View
{
    public static function render(string $template, array $data = []): string
    {
        $viewFile = dirname(__DIR__) . '/View/' . $template . '.php';

        if (!is_file($viewFile)) {
            throw new \RuntimeException("View not found: {$template}");
        }

        extract($data, EXTR_SKIP);

        ob_start();
        require $viewFile;

        return (string) ob_get_clean();
    }
}
