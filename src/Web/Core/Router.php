<?php

declare(strict_types=1);

namespace App\Web\Core;

final class Router
{
    /** @var array<string, array<string, callable>> */
    private array $routes = [];

    public function get(string $path, callable $handler): void
    {
        $this->routes['GET'][$path] = $handler;
    }

    public function post(string $path, callable $handler): void
    {
        $this->routes['POST'][$path] = $handler;
    }

    public function dispatch(Request $request): void
    {
        $handler = $this->routes[$request->method()][$request->path()] ?? null;

        if ($handler === null) {
            Response::html('<h1>404</h1><p>Seite nicht gefunden.</p>', 404);
            return;
        }

        $result = $handler($request);

        if (is_string($result)) {
            Response::html($result);
        }
    }
}
