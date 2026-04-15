<?php

declare(strict_types=1);

require_once __DIR__ . '/../Database/ConnectionFactory.php';
require_once __DIR__ . '/../Service/PipelineConfig.php';

spl_autoload_register(static function (string $class): void {
    $prefix = 'App\\Web\\';

    if (!str_starts_with($class, $prefix)) {
        return;
    }

    $relative = substr($class, strlen($prefix));
    $path = __DIR__ . '/' . str_replace('\\', '/', $relative) . '.php';

    if (is_file($path)) {
        require_once $path;
    }
});

function web_config(string $name): array
{
    static $cache = [];

    if (!isset($cache[$name])) {
        $cache[$name] = require dirname(__DIR__, 2) . '/config/' . $name . '.php';
    }

    return $cache[$name];
}
