<?php

declare(strict_types=1);

function wela_auto_generate_seo_columns(
    PDO $pdo,
    string $entityClass,
    int $linkType,
    int $linkId,
    string $languageCode,
    int $storeId,
    array $columns,
    ?string $text = null
): array {
    $urlText = wela_generate_auto_seo_url(
        $pdo,
        $entityClass,
        $linkType,
        $linkId,
        $languageCode,
        $storeId,
        $text
    );

    $columns['url_text'] = $urlText;
    $columns['url_md5'] = md5($urlText);

    return $columns;
}

function wela_apply_auto_generated_seo_update(PDO $pdo, array $identity, array $columns): array
{
    $existing = wela_fetch_existing_seo_url($pdo, $identity);
    $newUrl = trim((string) ($columns['url_text'] ?? ''), '/');

    if (!is_array($existing) || $newUrl === '') {
        return $columns;
    }

    $oldUrl = trim((string) ($existing['url_text'] ?? ''), '/');

    if ($oldUrl === '' || $oldUrl === $newUrl) {
        return $columns;
    }

    wela_insert_seo_redirect(
        $pdo,
        $oldUrl,
        $newUrl,
        (string) ($identity['language_code'] ?? ''),
        (int) ($identity['link_type'] ?? 0),
        (int) ($identity['link_id'] ?? 0),
        (int) ($identity['store_id'] ?? 0)
    );

    return $columns;
}

function wela_generate_auto_seo_url(
    PDO $pdo,
    string $entityClass,
    int $linkType,
    int $linkId,
    string $languageCode,
    int $storeId,
    ?string $text = null
): string {
    $entityClass = trim($entityClass);

    $url = match ($entityClass) {
        'product' => wela_generate_product_seo_url($pdo, $linkId, $languageCode, $storeId),
        'category' => wela_generate_category_seo_url($pdo, $linkId, $languageCode, $storeId),
        default => wela_generate_generic_seo_url($pdo, $entityClass, $linkId, $languageCode, (string) ($text ?? '')),
    };

    return wela_validate_seo_db_key_link($pdo, $url, $linkType, $linkId, $languageCode, $storeId);
}

function wela_generate_product_seo_url(PDO $pdo, int $productId, string $languageCode, int $storeId): string
{
    $productName = wela_fetch_seo_text(
        $pdo,
        'xt_products_description',
        'products_id',
        $productId,
        'products_name',
        'language_code',
        $languageCode,
        'products_store_id',
        $storeId
    );

    $productSlug = wela_filter_auto_url_text($pdo, $productName, $languageCode, 'product', $productId);
    $url = $productSlug;

    if (wela_xt_config_is_true($pdo, '_SYSTEM_SEO_PRODUCTS_CATEGORIES')) {
        $categoryId = wela_fetch_product_master_category_id($pdo, $productId);
        if ($categoryId > 0) {
            $parentUrl = wela_fetch_seo_url_text($pdo, 2, $categoryId, $languageCode, $storeId);
            $parentUrl = wela_strip_seo_language_prefix($parentUrl, $languageCode);

            if ($parentUrl !== '') {
                $url = $parentUrl . '/' . $productSlug;
            }
        }
    }

    return wela_finalize_auto_seo_url($pdo, $url, $languageCode);
}

function wela_generate_category_seo_url(PDO $pdo, int $categoryId, string $languageCode, int $storeId): string
{
    $categoryName = wela_fetch_seo_text(
        $pdo,
        'xt_categories_description',
        'categories_id',
        $categoryId,
        'categories_name',
        'language_code',
        $languageCode,
        'categories_store_id',
        $storeId
    );

    $categorySlug = wela_filter_auto_url_text($pdo, $categoryName, $languageCode, 'category', $categoryId);
    $url = $categorySlug;

    $parentId = wela_fetch_category_parent_id($pdo, $categoryId);
    if ($parentId > 0) {
        $parentUrl = wela_fetch_seo_url_text($pdo, 2, $parentId, $languageCode, $storeId);
        $parentUrl = wela_strip_seo_language_prefix($parentUrl, $languageCode);

        if ($parentUrl !== '') {
            $url = $parentUrl . '/' . $categorySlug;
        }
    }

    return wela_finalize_auto_seo_url($pdo, $url, $languageCode);
}

function wela_generate_generic_seo_url(
    PDO $pdo,
    string $entityClass,
    int $linkId,
    string $languageCode,
    string $text
): string {
    $className = $entityClass !== '' ? $entityClass : 'item';
    $slug = wela_filter_auto_url_text($pdo, $text, $languageCode, $className, $linkId);
    
    return wela_finalize_auto_seo_url($pdo, $slug, $languageCode);
}

function wela_finalize_auto_seo_url(PDO $pdo, string $url, string $languageCode): string
{
    $url = trim($url, '/');

    if (wela_xt_config_is_true($pdo, '_SYSTEM_SEO_URL_LANG_BASED')) {
        $url = $languageCode . '/' . ltrim($url, '/');
    }

    return strtolower(trim($url, '/'));
}

function wela_filter_auto_url_text(PDO $pdo, string $input, string $languageCode, string $className, int $entityId): string
{
    $filtered = trim($input);
    $filtered = str_replace('/', '-', $filtered);

    $words = preg_split("/[\s,.]+/", $filtered) ?: [];
    $words = array_values(array_filter(array_map(
        static fn (string $word): string => trim($word),
        array_values(array_filter($words, static fn (mixed $word): bool => is_string($word)))
    ), static fn (string $word): bool => $word !== ''));

    $rules = wela_load_seo_stop_words($pdo, $languageCode);
    $stopWords = $rules['stopwords'];
    $replaceRules = $rules['replace_rules'];

    if (count($words) > 1 && $stopWords !== []) {
        $words = array_values(array_filter(
            $words,
            static fn (string $word): bool => !in_array($word, $stopWords, true)
        ));
    }

    $filtered = implode('-', $words);

    foreach ($replaceRules as $rule) {
        $lookup = (string) ($rule['lookup'] ?? '');
        if ($lookup === '') {
            continue;
        }

        $replacement = (string) ($rule['replacement'] ?? '');
        $pattern = '/' . preg_quote($lookup, '/') . '/u';
        $filtered = preg_replace($pattern, $replacement, $filtered) ?? '';
    }

    $filtered = preg_replace('/[^a-zA-Z0-9\-\/\.\_]/u', '', $filtered) ?? '';
    $filtered = preg_replace('/-+/', '-', $filtered) ?? '';
    $filtered = preg_replace('/-$/', '', $filtered) ?? '';

    if ($filtered === '') {
        $filtered = $className . '-' . $entityId . '-empty';
    }

    return $filtered;
}

function wela_load_seo_stop_words(PDO $pdo, string $languageCode): array
{
    static $cache = [];

    if (isset($cache[$languageCode])) {
        return $cache[$languageCode];
    }

    $stmt = $pdo->prepare(
        'SELECT `stopword_lookup`, `stopword_replacement`, `replace_word`
         FROM `xt_seo_stop_words`
         WHERE `language_code` IN (\'ALL\', :language_code)
         ORDER BY `stop_word_id` ASC'
    );
    $stmt->execute([':language_code' => $languageCode]);

    $stopWords = [];
    $replaceRules = [];

    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        $lookup = trim((string) ($row['stopword_lookup'] ?? ''));
        if ($lookup === '') {
            continue;
        }

        if ((int) ($row['replace_word'] ?? 0) === 1) {
            $replaceRules[] = [
                'lookup' => $lookup,
                'replacement' => (string) ($row['stopword_replacement'] ?? ''),
            ];

            continue;
        }

        $stopWords[] = $lookup;
    }

    return $cache[$languageCode] = [
        'stopwords' => array_values(array_unique($stopWords)),
        'replace_rules' => $replaceRules,
    ];
}

function wela_xt_config_is_true(PDO $pdo, string $configKey): bool
{
    static $cache = [];

    if (array_key_exists($configKey, $cache)) {
        return $cache[$configKey];
    }

    $stmt = $pdo->prepare(
        'SELECT `config_value`
         FROM `xt_config`
         WHERE `config_key` = :config_key
         LIMIT 1'
    );
    $stmt->execute([':config_key' => $configKey]);
    $value = strtolower(trim((string) $stmt->fetchColumn()));

    return $cache[$configKey] = ($value === 'true' || $value === '1' || $value === 'yes');
}

function wela_fetch_seo_text(
    PDO $pdo,
    string $table,
    string $idField,
    int $idValue,
    string $textField,
    string $languageField,
    string $languageCode,
    string $storeField,
    int $storeId
): string {
    $stmt = $pdo->prepare(
        sprintf(
            'SELECT `%s`
             FROM `%s`
             WHERE `%s` = :id_value
               AND `%s` = :language_code
               AND `%s` = :store_id
             LIMIT 1',
            $textField,
            $table,
            $idField,
            $languageField,
            $storeField
        )
    );
    $stmt->execute([
        ':id_value' => $idValue,
        ':language_code' => $languageCode,
        ':store_id' => $storeId,
    ]);

    return trim((string) $stmt->fetchColumn());
}

function wela_fetch_product_master_category_id(PDO $pdo, int $productId): int
{
    $stmt = $pdo->prepare(
        'SELECT `categories_id`
         FROM `xt_products_to_categories`
         WHERE `products_id` = :products_id
           AND `master_link` = 1
         ORDER BY `categories_id` ASC
         LIMIT 1'
    );
    $stmt->execute([':products_id' => $productId]);

    return (int) ($stmt->fetchColumn() ?: 0);
}

function wela_fetch_category_parent_id(PDO $pdo, int $categoryId): int
{
    $stmt = $pdo->prepare(
        'SELECT `parent_id`
         FROM `xt_categories`
         WHERE `categories_id` = :categories_id
         LIMIT 1'
    );
    $stmt->execute([':categories_id' => $categoryId]);

    return (int) ($stmt->fetchColumn() ?: 0);
}

function wela_fetch_seo_url_text(PDO $pdo, int $linkType, int $linkId, string $languageCode, int $storeId): string
{
    $stmt = $pdo->prepare(
        'SELECT `url_text`
         FROM `xt_seo_url`
         WHERE `link_type` = :link_type
           AND `link_id` = :link_id
           AND `language_code` = :language_code
           AND `store_id` = :store_id
         LIMIT 1'
    );
    $stmt->execute([
        ':link_type' => $linkType,
        ':link_id' => $linkId,
        ':language_code' => $languageCode,
        ':store_id' => $storeId,
    ]);

    return trim((string) $stmt->fetchColumn(), '/');
}

function wela_strip_seo_language_prefix(string $url, string $languageCode): string
{
    $url = trim($url, '/');
    $prefix = strtolower($languageCode) . '/';

    if (str_starts_with(strtolower($url), $prefix)) {
        return substr($url, strlen($prefix));
    }

    return $url;
}

function wela_validate_seo_db_key_link(
    PDO $pdo,
    string $urlText,
    int $linkType,
    int $linkId,
    string $languageCode,
    int $storeId
): string {
    $baseUrl = trim($urlText, '/');
    $counter = '';

    while (true) {
        $candidate = $baseUrl . $counter;
        $stmt = $pdo->prepare(
            'SELECT `link_type`, `link_id`
             FROM `xt_seo_url`
             WHERE `url_md5` = :url_md5
               AND `store_id` = :store_id
               AND `language_code` = :language_code
             LIMIT 1'
        );
        $stmt->execute([
            ':url_md5' => md5($candidate),
            ':store_id' => $storeId,
            ':language_code' => $languageCode,
        ]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($row === false) {
            return $candidate;
        }

        if ((int) ($row['link_type'] ?? 0) === $linkType && (int) ($row['link_id'] ?? 0) === $linkId) {
            return $candidate;
        }

        $counter = $counter === '' ? '1' : (string) (((int) $counter) + 1);
    }
}

function wela_fetch_existing_seo_url(PDO $pdo, array $identity): ?array
{
    $stmt = $pdo->prepare(
        'SELECT `url_text`, `url_md5`, `language_code`, `link_type`, `link_id`, `store_id`
         FROM `xt_seo_url`
         WHERE `link_type` = :link_type
           AND `link_id` = :link_id
           AND `language_code` = :language_code
           AND `store_id` = :store_id
         LIMIT 1'
    );
    $stmt->execute([
        ':link_type' => (int) ($identity['link_type'] ?? 0),
        ':link_id' => (int) ($identity['link_id'] ?? 0),
        ':language_code' => (string) ($identity['language_code'] ?? ''),
        ':store_id' => (int) ($identity['store_id'] ?? 0),
    ]);

    $row = $stmt->fetch(PDO::FETCH_ASSOC);

    return is_array($row) ? $row : null;
}

function wela_insert_seo_redirect(
    PDO $pdo,
    string $oldUrl,
    string $newUrl,
    string $languageCode,
    int $linkType,
    int $linkId,
    int $storeId
): void {
    if (!wela_table_exists($pdo, 'xt_seo_url_redirect')) {
        return;
    }

    $existingRedirect = wela_fetch_existing_seo_redirect(
        $pdo,
        $oldUrl,
        $languageCode,
        $storeId,
        $newUrl
    );

    if ($existingRedirect !== null) {
        return;
    }

    $columns = [
        'url_md5' => md5($oldUrl),
        'url_text' => $oldUrl,
        'language_code' => $languageCode,
        'link_type' => $linkType,
        'link_id' => $linkId,
        'store_id' => $storeId,
        'url_text_redirect' => $newUrl,
        'url_md5_redirect' => md5($newUrl),
        'is_deleted' => 0,
        'total_count' => 0,
        'count_day_last_access' => 0,
        'last_access' => gmdate('Y-m-d H:i:s'),
    ];

    $masterKeyValue = wela_next_redirect_master_key_if_required($pdo);
    if ($masterKeyValue !== null) {
        $columns['master_key'] = $masterKeyValue;
    }

    $fieldNames = array_keys($columns);
    $sql = sprintf(
        'INSERT INTO `xt_seo_url_redirect` (%s) VALUES (%s)',
        implode(', ', array_map(static fn (string $field): string => "`{$field}`", $fieldNames)),
        implode(', ', array_map(static fn (string $field): string => ':' . $field, $fieldNames))
    );

    $stmt = $pdo->prepare($sql);
    $params = [];
    foreach ($columns as $field => $value) {
        $params[':' . $field] = $value;
    }
    $stmt->execute($params);
}

function wela_fetch_existing_seo_redirect(
    PDO $pdo,
    string $oldUrl,
    string $languageCode,
    int $storeId,
    string $newUrl
): ?array {
    $stmt = $pdo->prepare(
        'SELECT `master_key`
         FROM `xt_seo_url_redirect`
         WHERE `url_text` = :url_text
           AND `language_code` = :language_code
           AND `store_id` = :store_id
           AND `url_text_redirect` = :url_text_redirect
           AND `is_deleted` = 0
         LIMIT 1'
    );
    $stmt->execute([
        ':url_text' => $oldUrl,
        ':language_code' => $languageCode,
        ':store_id' => $storeId,
        ':url_text_redirect' => $newUrl,
    ]);

    $row = $stmt->fetch(PDO::FETCH_ASSOC);

    return is_array($row) ? $row : null;
}

function wela_next_redirect_master_key_if_required(PDO $pdo): ?int
{
    static $cache = null;

    if ($cache === null) {
        $cache = [
            'requires_manual_key' => false,
            'next_key' => null,
        ];

        if (wela_table_exists($pdo, 'xt_seo_url_redirect')) {
            $stmt = $pdo->query('SHOW COLUMNS FROM `xt_seo_url_redirect` LIKE \'master_key\'');
            $column = $stmt->fetch(PDO::FETCH_ASSOC);

            if (is_array($column)) {
                $extra = strtolower((string) ($column['Extra'] ?? ''));
                $default = $column['Default'] ?? null;
                $nullable = strtoupper((string) ($column['Null'] ?? 'YES'));

                $cache['requires_manual_key'] = !str_contains($extra, 'auto_increment')
                    && $nullable === 'NO'
                    && $default === null;
            }
        }
    }

    if (($cache['requires_manual_key'] ?? false) !== true) {
        return null;
    }

    if (!is_int($cache['next_key'] ?? null)) {
        $stmt = $pdo->query('SELECT COALESCE(MAX(`master_key`), 0) + 1 FROM `xt_seo_url_redirect`');
        $cache['next_key'] = (int) $stmt->fetchColumn();
    }

    $nextKey = (int) $cache['next_key'];
    $cache['next_key'] = $nextKey + 1;

    return $nextKey;
}

function wela_table_exists(PDO $pdo, string $table): bool
{
    static $cache = [];

    if (array_key_exists($table, $cache)) {
        return $cache[$table];
    }

    $stmt = $pdo->prepare('SHOW TABLES LIKE :table');
    $stmt->execute([':table' => $table]);

    return $cache[$table] = ($stmt->fetchColumn() !== false);
}
