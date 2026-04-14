<?php

$projectRoot = dirname(__DIR__);
$isDocker = is_file('/.dockerenv');

$env = static function (string $name, string $default): string {
    $value = getenv($name);

    if ($value === false || $value === '') {
        return $default;
    }

    return $value;
};

$envInt = static function (string $name, int $default) use ($env): int {
    $value = $env($name, (string) $default);

    return (int) $value;
};

$detectExtraPath = static function () use ($env, $projectRoot): string {
    $explicit = getenv('EXTRA_SQLITE_PATH');
    if ($explicit !== false && $explicit !== '') {
        return $explicit;
    }

    $candidates = [
        $projectRoot . '/data/extra.sqlite',
        $projectRoot . '/data/extra.db',
        $projectRoot . '/database/zusatzdaten.db',
        $projectRoot . '/zusatzdaten.sqlite',
    ];

    foreach ($candidates as $candidate) {
        if (is_file($candidate)) {
            return $candidate;
        }
    }

    return $candidates[0];
};

return [
    'sources' => [
        'afs' => [
            'type' => 'mssql',
            'connection' => [
                'host' => $env('AFS_DB_HOST', $isDocker ? 'host.docker.internal' : '127.0.0.1'),
                'database' => $env('AFS_DB_NAME', 'AFS_2018'),
                'username' => $env('AFS_DB_USER', 'sa'),
                'password' => $env('AFS_DB_PASS', ''),
                'charset' => 'utf8',
            ],
            'entities' => [
                'articles' => [
                    'table' => 'Artikel',
                ],
                'categories' => [
                    'table' => 'Warengruppen',
                ],
            ],
        ],

        'extra' => [
            'type' => 'sqlite',
            'connection' => [
                'path' => $detectExtraPath(),
            ],
            'entities' => [
                'article_translations' => [
                    'table' => 'articles',
                ],
                'category_translations' => [
                    'table' => 'warengruppen',
                ],
            ],
        ],

        'xt' => [
            'type' => 'mysql',
            'connection' => [
                'host' => $env('XT_DB_HOST', $isDocker ? 'host.docker.internal' : '127.0.0.1'),
                'port' => $envInt('XT_DB_PORT', 3306),
                'database' => $env('XT_DB_NAME', 'shop2'),
                'username' => $env('XT_DB_USER', 'shop'),
                'password' => $env('XT_DB_PASS', ''),
                'charset' => 'utf8mb4',
            ],
        ],

        'stage' => [
            'type' => 'mysql',
            'connection' => [
                'host' => $env('STAGE_DB_HOST', $isDocker ? 'mysql' : '127.0.0.1'),
                'port' => $envInt('STAGE_DB_PORT', 3306),
                'database' => $env('STAGE_DB_NAME', 'stage_sync'),
                'username' => $env('STAGE_DB_USER', 'stage'),
                'password' => $env('STAGE_DB_PASS', 'stage'),
                'charset' => 'utf8mb4',
            ],
        ],
    ],
];
