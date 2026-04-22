<?php

$projectRoot = dirname(__DIR__);
$isDocker = is_file('/.dockerenv');
$envFile = $projectRoot . '/.env';

$readEnvFile = static function (string $path): array {
    if (!is_file($path)) {
        return [];
    }

    $values = [];

    foreach (file($path, FILE_IGNORE_NEW_LINES) ?: [] as $line) {
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

        $first = $value[0] ?? '';
        $last = $value[strlen($value) - 1] ?? '';
        if (($first === '"' && $last === '"') || ($first === "'" && $last === "'")) {
            $value = substr($value, 1, -1);
            $value = stripcslashes($value);
        }

        $values[$name] = $value;
    }

    return $values;
};

$fileEnv = $readEnvFile($envFile);

$env = static function (string $name, string $default) use ($fileEnv): string {
    if (array_key_exists($name, $fileEnv) && $fileEnv[$name] !== '') {
        return $fileEnv[$name];
    }

    $value = getenv($name);

    if ($value === false || $value === '') {
        return $default;
    }

    return $value;
};

$envInt = static function (string $name, int $default) use ($env): int {
    return (int) $env($name, (string) $default);
};

$qualifiedAfsTable = static function (string $tableName) use ($env): string {
    $schema = trim($env('AFS_DB_SCHEMA', 'dbo'));
    $table = trim($tableName);

    if ($table === '') {
        return $table;
    }

    if ($schema === '' || str_contains($table, '.')) {
        return $table;
    }

    return $schema . '.' . $table;
};

$afsArticlesTable = $qualifiedAfsTable($env('AFS_ARTICLES_TABLE', 'Artikel'));
$afsCategoriesTable = $qualifiedAfsTable($env('AFS_CATEGORIES_TABLE', 'Warengruppe'));
$afsDocumentsTable = $qualifiedAfsTable($env('AFS_DOCUMENTS_TABLE', 'Dokument'));
$extraArticlesTable = $env('EXTRA_ARTICLES_TABLE', 'article_translations');
$extraAttributeTranslationsTable = $env('EXTRA_ATTRIBUTE_TRANSLATIONS_TABLE', 'attribute_translations');
$extraCategoriesTable = $env('EXTRA_CATEGORIES_TABLE', 'category_translations');

$detectExtraPath = static function () use ($projectRoot): string {
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

$detectMissingTranslationsPath = static function () use ($projectRoot): string {
    $explicit = getenv('MISSING_TRANSLATIONS_SQLITE_PATH');
    if ($explicit !== false && $explicit !== '') {
        return $explicit;
    }

    return $projectRoot . '/data/missing_translations.sqlite';
};

return [
    'sources' => [
        'afs' => [
            'type' => 'mssql',
            'write_batch_size' => 250,
            'connection' => [
                'host' => $env('AFS_DB_HOST', $isDocker ? 'host.docker.internal' : '127.0.0.1'),
                'port' => $envInt('AFS_DB_PORT', 1435),
                'database' => $env('AFS_DB_NAME', 'AFS_2018'),
                'username' => $env('AFS_DB_USER', 'sa'),
                'password' => $env('AFS_DB_PASS', ''),
                'charset' => 'utf8',
                'encrypt' => true,
                'trust_server_certificate' => true,
            ],
                'entities' => [
                'articles' => [
                    'table' => $afsArticlesTable,
                    'columns' => [
                        'Artikel',
                        'Art',
                        'Mandant',
                        'Artikelnummer',
                        'Bezeichnung',
                        'EANNummer',
                        'Bestand',
                        'Bild1',
                        'Bild2',
                        'Bild3',
                        'Bild4',
                        'Bild5',
                        'Bild6',
                        'Bild7',
                        'Bild8',
                        'Bild9',
                        'Bild10',
                        'VK3',
                        'Warengruppe',
                        'Warengruppen',
                        'Umsatzsteuer',
                        'Zusatzfeld01',
                        'Zusatzfeld03',
                        'Zusatzfeld04',
                        'Zusatzfeld05',
                        'Zusatzfeld06',
                        'Zusatzfeld15',
                        'Zusatzfeld16',
                        'Zusatzfeld17',
                        'Zusatzfeld18',
                        'Zusatzfeld07',
                        'Bruttogewicht',
                        'Internet',
                        'Einheit',
                        'Langtext',
                        'Werbetext1',
                        'Bemerkung',
                        'Hinweis',
                    ],
                    'where' => [
                        'Internet = 1',
                        'Art < 255',
                        'Mandant = 1',
                    ],
                ],
                'categories' => [
                    'table' => $afsCategoriesTable,
                    'columns' => [
                        'Warengruppe',
                        'Art',
                        'Anhang',
                        'Ebene',
                        'Bezeichnung',
                        'Internet',
                        'Bild',
                        'Bild_gross',
                        'Beschreibung',
                    ],
                    'where' => [
                        'Internet = 0',
                    ],
                ],
                'documents' => [
                    'table' => $afsDocumentsTable,
                    'columns' => [
                        'Zaehler',
                        'Artikel',
                        'Titel',
                        'Dateiname',
                        'Art',
                    ],
                    'where' => [
                        "Artikel IN (SELECT Artikel FROM {$afsArticlesTable} WHERE Internet = 1 AND Art < 255 AND Mandant = 1)",
                    ],
                ],
            ],
        ],

        'extra_sqlite_bootstrap' => [
            'type' => 'sqlite',
            'write_batch_size' => 250,
            'connection' => [
                'path' => $detectExtraPath(),
            ],
            'entities' => [
                'article_translations' => [
                    'table' => 'articles',
                    'columns' => [
                        'id',
                        'artikel_id',
                        'article_number',
                        'master_article_number',
                        'language',
                        'article_name',
                        'intro_text',
                        'description',
                        'technical_data_html',
                        'attribute_name1',
                        'attribute_name2',
                        'attribute_name3',
                        'attribute_name4',
                        'attribute_value1',
                        'attribute_value2',
                        'attribute_value3',
                        'attribute_value4',
                        'meta_title',
                        'meta_description',
                        'is_master',
                        'source_directory',
                    ],
                ],
                'category_translations' => [
                    'table' => 'warengruppen',
                    'columns' => [
                        'id',
                        'warengruppen_id',
                        'original_name',
                        'language',
                        'translated_name',
                        'meta_description',
                        'meta_title',
                    ],
                ],
            ],
        ],

        'extra' => [
            'type' => 'mysql',
            'write_batch_size' => 250,
            'connection' => [
                'host' => $env('EXTRA_DB_HOST', $isDocker ? 'mysql' : '127.0.0.1'),
                'port' => $envInt('EXTRA_DB_PORT', 3306),
                'database' => $env('EXTRA_DB_NAME', 'afs_extras'),
                'username' => $env('EXTRA_DB_USER', 'stage'),
                'password' => $env('EXTRA_DB_PASS', 'stage'),
                'charset' => 'utf8mb4',
            ],
            'entities' => [
                'article_translations' => [
                    'table' => $extraArticlesTable,
                    'columns' => [
                        'id',
                        'artikel_id',
                        'article_number',
                        'master_article_number',
                        'language',
                        'article_name',
                        'intro_text',
                        'description',
                        'technical_data_html',
                        'attribute_name1',
                        'attribute_name2',
                        'attribute_name3',
                        'attribute_name4',
                        'attribute_value1',
                        'attribute_value2',
                        'attribute_value3',
                        'attribute_value4',
                        'meta_title',
                        'meta_description',
                        'is_master',
                        'source_directory',
                    ],
                ],
                'attribute_translations' => [
                    'table' => $extraAttributeTranslationsTable,
                    'columns' => [
                        'id',
                        'article_id',
                        'article_number',
                        'sort_order',
                        'language',
                        'attribute_name',
                        'attribute_value',
                        'source_directory',
                    ],
                ],
                'category_translations' => [
                    'table' => $extraCategoriesTable,
                    'columns' => [
                        'id',
                        'warengruppen_id',
                        'original_name',
                        'language',
                        'translated_name',
                        'meta_description',
                        'meta_title',
                    ],
                ],
            ],
        ],

        'xt' => [
            'type' => 'xt_api',
            'connection' => [
                'url' => $env('XT_API_URL', 'http://10.0.1.104/wela-api'),
                'key' => $env('XT_API_KEY', ''),
                'request_timeout_seconds' => $envInt('XT_API_TIMEOUT_SECONDS', 300),
                'product_batch_request_size' => $envInt('XT_PRODUCT_BATCH_REQUEST_SIZE', 1000),
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

        'missing_translations' => [
            'type' => 'mysql',
            'connection' => [
                'host' => $env('EXTRA_DB_HOST', $isDocker ? 'mysql' : '127.0.0.1'),
                'port' => $envInt('EXTRA_DB_PORT', 3306),
                'database' => $env('EXTRA_DB_NAME', 'afs_extras'),
                'username' => $env('EXTRA_DB_USER', 'stage'),
                'password' => $env('EXTRA_DB_PASS', 'stage'),
                'charset' => 'utf8mb4',
            ],
        ],
    ],
];
