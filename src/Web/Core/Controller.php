<?php

declare(strict_types=1);

namespace App\Web\Core;

abstract class Controller
{
    protected function render(string $view, array $data = []): string
    {
        $content = View::render($view, $data);

        return View::render('layouts/app', array_merge($data, [
            'content' => $content,
        ]));
    }

    protected function perPage(Request $request): int
    {
        $config = \web_config('admin');
        $perPage = $request->int('per_page', $config['default_per_page']);

        return min(max(10, $perPage), $config['max_per_page']);
    }
}
