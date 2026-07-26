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
    ?string $text = null,
    array $context = []
): array {
    $urlText = wela_generate_auto_seo_url(
        $pdo,
        $entityClass,
        $linkType,
        $linkId,
        $languageCode,
        $storeId,
        $text,
        $context
    );

    $columns['url_text'] = $urlText;
    $columns['url_md5'] = md5($urlText);

    return $columns;
}

function wela_apply_auto_generated_seo_update(PDO $pdo, array $identity, array $columns, ?array $existingSeoRow = null): array
{
    $existing = is_array($existingSeoRow) ? $existingSeoRow : wela_fetch_existing_seo_url($pdo, $identity);
    $newUrl = trim((string) ($columns['url_text'] ?? ''), '/');

    if (!is_array($existing) || $newUrl === '') {
        return $columns;
    }

    $oldUrl = trim((string) ($existing['url_text'] ?? ''), '/');

    if ($oldUrl === '' || $oldUrl === $newUrl) {
        return $columns;
    }

    wela_queue_seo_redirect(
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
    ?string $text = null,
    array $context = []
): string {
    $entityClass = trim($entityClass);

    $url = match ($entityClass) {
        'product' => wela_generate_product_seo_url($pdo, $linkId, $languageCode, $storeId, $text, $context),
        'category' => wela_generate_category_seo_url($pdo, $linkId, $languageCode, $storeId, $context),
        default => wela_generate_generic_seo_url($pdo, $entityClass, $linkId, $languageCode, (string) ($text ?? '')),
    };

    $existingSeoUrl = wela_reuse_existing_generated_seo_url($context, $url, $linkType, $linkId, $languageCode, $storeId);
    if ($existingSeoUrl !== null) {
        return $existingSeoUrl;
    }

    return wela_validate_seo_db_key_link($pdo, $url, $linkType, $linkId, $languageCode, $storeId);
}

function wela_reuse_existing_generated_seo_url(
    array $context,
    string $generatedUrl,
    int $linkType,
    int $linkId,
    string $languageCode,
    int $storeId
): ?string {
    $generatedUrl = trim($generatedUrl, '/');
    if ($generatedUrl === '') {
        return null;
    }

    $existingSeoRow = $context['existing_seo_row'] ?? null;
    if (!is_array($existingSeoRow)) {
        return null;
    }

    if ((int) ($existingSeoRow['link_type'] ?? 0) !== $linkType
        || (int) ($existingSeoRow['link_id'] ?? 0) !== $linkId
        || (string) ($existingSeoRow['language_code'] ?? '') !== $languageCode
        || (int) ($existingSeoRow['store_id'] ?? 0) !== $storeId
    ) {
        return null;
    }

    $existingUrl = trim((string) ($existingSeoRow['url_text'] ?? ''), '/');
    if ($existingUrl === '') {
        return null;
    }

    if ($existingUrl === $generatedUrl) {
        return $existingUrl;
    }

    if (!str_starts_with($existingUrl, $generatedUrl)) {
        return null;
    }

    $suffix = substr($existingUrl, strlen($generatedUrl));
    if ($suffix === '' || ctype_digit($suffix)) {
        return $existingUrl;
    }

    return null;
}

function wela_generate_product_seo_url(
    PDO $pdo,
    int $productId,
    string $languageCode,
    int $storeId,
    ?string $preferredText = null,
    array $context = []
): string
{
    $productName = trim((string) ($preferredText ?? ''));
    if ($productName === '') {
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
    }

    $productSlug = wela_filter_auto_url_text($pdo, $productName, $languageCode, 'product', $productId);
    $url = $productSlug;

    if (wela_xt_config_is_true($pdo, '_SYSTEM_SEO_PRODUCTS_CATEGORIES')) {
        $categoryId = isset($context['product_master_category_id']) ? (int) $context['product_master_category_id'] : 0;
        if ($categoryId <= 0) {
            $categoryId = wela_fetch_product_master_category_id($pdo, $productId);
        }

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

function wela_generate_category_seo_url(PDO $pdo, int $categoryId, string $languageCode, int $storeId, array $context = []): string
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
    static $cache = [];

    $cacheKey = implode('|', [$table, $idField, $idValue, $textField, $languageField, $languageCode, $storeField, $storeId]);
    if (isset($cache[$cacheKey])) {
        return $cache[$cacheKey];
    }

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

    return $cache[$cacheKey] = trim((string) $stmt->fetchColumn());
}

function wela_fetch_product_master_category_id(PDO $pdo, int $productId): int
{
    static $cache = [];

    if (isset($cache[$productId])) {
        return $cache[$productId];
    }

    $stmt = $pdo->prepare(
        'SELECT `categories_id`
         FROM `xt_products_to_categories`
         WHERE `products_id` = :products_id
           AND `master_link` = 1
         ORDER BY `categories_id` ASC
         LIMIT 1'
    );
    $stmt->execute([':products_id' => $productId]);

    return $cache[$productId] = (int) ($stmt->fetchColumn() ?: 0);
}

function wela_fetch_category_parent_id(PDO $pdo, int $categoryId): int
{
    static $cache = [];

    if (isset($cache[$categoryId])) {
        return $cache[$categoryId];
    }

    $stmt = $pdo->prepare(
        'SELECT `parent_id`
         FROM `xt_categories`
         WHERE `categories_id` = :categories_id
         LIMIT 1'
    );
    $stmt->execute([':categories_id' => $categoryId]);

    return $cache[$categoryId] = (int) ($stmt->fetchColumn() ?: 0);
}

function wela_fetch_seo_url_text(PDO $pdo, int $linkType, int $linkId, string $languageCode, int $storeId): string
{
    static $cache = [];

    $cacheKey = implode('|', [$linkType, $linkId, $languageCode, $storeId]);
    if (isset($cache[$cacheKey])) {
        return $cache[$cacheKey];
    }

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

    return $cache[$cacheKey] = trim((string) $stmt->fetchColumn(), '/');
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
    if ($baseUrl === '') {
        return $baseUrl;
    }

    $existingCandidates = wela_prefetch_seo_url_candidates($pdo, $baseUrl, $languageCode, $storeId);
    $reservedCandidates = &wela_reserved_seo_url_candidates($pdo, $languageCode, $storeId);
    $counter = 0;

    while (true) {
        $candidate = $counter === 0 ? $baseUrl : ($baseUrl . $counter);
        $reserved = $reservedCandidates[$candidate] ?? null;
        if (is_array($reserved)) {
            if ((int) ($reserved['link_type'] ?? 0) === $linkType && (int) ($reserved['link_id'] ?? 0) === $linkId) {
                return $candidate;
            }

            $counter++;
            continue;
        }

        $row = $existingCandidates[$candidate] ?? null;
        if (!is_array($row)) {
            $reservedCandidates[$candidate] = [
                'link_type' => $linkType,
                'link_id' => $linkId,
            ];
            return $candidate;
        }

        if ((int) ($row['link_type'] ?? 0) === $linkType && (int) ($row['link_id'] ?? 0) === $linkId) {
            $reservedCandidates[$candidate] = [
                'link_type' => $linkType,
                'link_id' => $linkId,
            ];
            return $candidate;
        }

        $counter++;
    }
}

function wela_prefetch_seo_url_candidates(PDO $pdo, string $baseUrl, string $languageCode, int $storeId): array
{
    $cache = &wela_seo_url_candidate_cache($pdo);

    $cacheKey = implode('|', [$languageCode, $storeId, $baseUrl]);
    if (isset($cache[$cacheKey])) {
        return $cache[$cacheKey];
    }

    $stmt = $pdo->prepare(
        'SELECT `url_text`, `link_type`, `link_id`
         FROM `xt_seo_url`
         WHERE `store_id` = :store_id
           AND `language_code` = :language_code
           AND (`url_text` = :url_text OR `url_text` LIKE :url_like)'
    );
    $stmt->execute([
        ':store_id' => $storeId,
        ':language_code' => $languageCode,
        ':url_text' => $baseUrl,
        ':url_like' => $baseUrl . '%',
    ]);

    $matches = [];
    foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
        $candidate = trim((string) ($row['url_text'] ?? ''), '/');
        if ($candidate === '') {
            continue;
        }

        if ($candidate !== $baseUrl) {
            if (!str_starts_with($candidate, $baseUrl)) {
                continue;
            }

            $suffix = substr($candidate, strlen($baseUrl));
            if ($suffix === '' || !ctype_digit($suffix)) {
                continue;
            }
        }

        $matches[$candidate] = [
            'link_type' => (int) ($row['link_type'] ?? 0),
            'link_id' => (int) ($row['link_id'] ?? 0),
        ];
    }

    return $cache[$cacheKey] = $matches;
}

function wela_prefetch_seo_url_candidates_bulk(PDO $pdo, array $groups): void
{
    if ($groups === []) {
        return;
    }

    foreach ($groups as $group) {
        $languageCode = trim((string) ($group['language_code'] ?? ''));
        $storeId = (int) ($group['store_id'] ?? 0);
        $baseUrls = array_values(array_unique(array_filter(
            is_array($group['base_urls'] ?? null) ? $group['base_urls'] : [],
            static fn (mixed $value): bool => is_string($value) && trim($value) !== ''
        )));

        if ($languageCode === '' || $storeId <= 0 || $baseUrls === []) {
            continue;
        }

        foreach (array_chunk($baseUrls, 100) as $baseUrlChunk) {
            wela_prefetch_seo_url_candidates_bulk_chunk($pdo, $languageCode, $storeId, $baseUrlChunk);
        }
    }
}

function wela_prefetch_seo_url_candidates_bulk_chunk(PDO $pdo, string $languageCode, int $storeId, array $baseUrls): void
{
    $normalizedBaseUrls = array_values(array_unique(array_map(
        static fn (string $baseUrl): string => trim($baseUrl, '/'),
        array_values(array_filter($baseUrls, static fn (mixed $baseUrl): bool => is_string($baseUrl) && trim((string) $baseUrl) !== ''))
    )));

    if ($normalizedBaseUrls === []) {
        return;
    }

    $cache = &wela_seo_url_candidate_cache($pdo);
    $missingBaseUrls = [];

    foreach ($normalizedBaseUrls as $baseUrl) {
        $cacheKey = implode('|', [$languageCode, $storeId, $baseUrl]);
        if (!isset($cache[$cacheKey])) {
            $missingBaseUrls[] = $baseUrl;
        }
    }

    if ($missingBaseUrls === []) {
        return;
    }

    $matchesByBaseUrl = [];
    foreach ($missingBaseUrls as $baseUrl) {
        $matchesByBaseUrl[$baseUrl] = [];
    }

    $patterns = array_map(
        static fn (string $baseUrl): string => preg_quote($baseUrl, '/'),
        $missingBaseUrls
    );
    $regexp = '^(?:' . implode('|', $patterns) . ')(?:[0-9]+)?$';

    $stmt = $pdo->prepare(
        'SELECT `url_text`, `link_type`, `link_id`
         FROM `xt_seo_url`
         WHERE `store_id` = :store_id
           AND `language_code` = :language_code
           AND `url_text` REGEXP :url_regexp'
    );
    $stmt->execute([
        ':store_id' => $storeId,
        ':language_code' => $languageCode,
        ':url_regexp' => $regexp,
    ]);

    foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
        $candidate = trim((string) ($row['url_text'] ?? ''), '/');
        if ($candidate === '') {
            continue;
        }

        foreach ($missingBaseUrls as $baseUrl) {
            if ($candidate !== $baseUrl) {
                if (!str_starts_with($candidate, $baseUrl)) {
                    continue;
                }

                $suffix = substr($candidate, strlen($baseUrl));
                if ($suffix === '' || !ctype_digit($suffix)) {
                    continue;
                }
            }

            $matchesByBaseUrl[$baseUrl][$candidate] = [
                'link_type' => (int) ($row['link_type'] ?? 0),
                'link_id' => (int) ($row['link_id'] ?? 0),
            ];
        }
    }

    foreach ($missingBaseUrls as $baseUrl) {
        $cacheKey = implode('|', [$languageCode, $storeId, $baseUrl]);
        $cache[$cacheKey] = $matchesByBaseUrl[$baseUrl] ?? [];
    }
}

function &wela_seo_url_candidate_cache(PDO $pdo): array
{
    static $caches = [];

    $key = spl_object_id($pdo);
    if (!isset($caches[$key])) {
        $caches[$key] = [];
    }

    return $caches[$key];
}

function &wela_reserved_seo_url_candidates(PDO $pdo, string $languageCode, int $storeId): array
{
    static $buffers = [];

    $key = implode('|', [spl_object_id($pdo), $languageCode, $storeId]);
    if (!isset($buffers[$key])) {
        $buffers[$key] = [];
    }

    return $buffers[$key];
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

function wela_seo_identity_key(array $identity): string
{
    return implode('|', [
        (string) ((int) ($identity['link_type'] ?? 0)),
        (string) ((int) ($identity['link_id'] ?? 0)),
        trim((string) ($identity['language_code'] ?? '')),
        (string) ((int) ($identity['store_id'] ?? 0)),
    ]);
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
    wela_queue_seo_redirect($pdo, $oldUrl, $newUrl, $languageCode, $linkType, $linkId, $storeId);
    wela_flush_seo_redirect_buffer($pdo);
}

function wela_queue_seo_redirect(
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

    $oldUrl = trim($oldUrl, '/');
    $newUrl = trim($newUrl, '/');
    $languageCode = trim($languageCode);

    if ($oldUrl === '' || $newUrl === '' || $oldUrl === $newUrl || $languageCode === '') {
        return;
    }

    $key = wela_seo_redirect_key(
        $oldUrl,
        $newUrl,
        $languageCode,
        $storeId
    );

    $buffer = &wela_seo_redirect_buffer($pdo);

    $buffer[$key] = [
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
}

function wela_flush_seo_redirect_buffer(PDO $pdo): void
{
    $buffer = &wela_seo_redirect_buffer($pdo);
    if ($buffer === []) {
        return;
    }

    $pending = array_values($buffer);
    $buffer = [];
    $existingKeys = wela_prefetch_existing_seo_redirect_keys($pdo, $pending);
    $rowsToInsert = [];

    foreach ($pending as $row) {
        $key = wela_seo_redirect_key(
            (string) ($row['url_text'] ?? ''),
            (string) ($row['url_text_redirect'] ?? ''),
            (string) ($row['language_code'] ?? ''),
            (int) ($row['store_id'] ?? 0)
        );

        if (isset($existingKeys[$key])) {
            continue;
        }

        $masterKeyValue = wela_next_redirect_master_key_if_required($pdo);
        if ($masterKeyValue !== null) {
            $row['master_key'] = $masterKeyValue;
        }

        $rowsToInsert[] = $row;
        $existingKeys[$key] = true;
    }

    if ($rowsToInsert === []) {
        return;
    }

    wela_insert_seo_redirect_rows($pdo, $rowsToInsert);
}

function wela_clear_seo_redirect_buffer(PDO $pdo): void
{
    $buffer = &wela_seo_redirect_buffer($pdo);
    $buffer = [];
}

function &wela_seo_redirect_buffer(PDO $pdo): array
{
    static $buffers = [];

    $key = spl_object_id($pdo);
    if (!isset($buffers[$key])) {
        $buffers[$key] = [];
    }

    return $buffers[$key];
}

function wela_seo_redirect_key(string $oldUrl, string $newUrl, string $languageCode, int $storeId): string
{
    return implode('|', [
        trim($oldUrl, '/'),
        trim($newUrl, '/'),
        trim($languageCode),
        (string) $storeId,
    ]);
}

function wela_prefetch_existing_seo_redirect_keys(PDO $pdo, array $rows): array
{
    if ($rows === []) {
        return [];
    }

    $existingKeys = [];

    foreach (array_chunk($rows, 100) as $chunk) {
        $conditions = [];
        $params = [];

        foreach ($chunk as $index => $row) {
            $conditions[] = sprintf(
                '(`url_text` = :url_text_%1$d AND `language_code` = :language_code_%1$d AND `store_id` = :store_id_%1$d AND `url_text_redirect` = :url_text_redirect_%1$d AND `is_deleted` = 0)',
                $index
            );
            $params[':url_text_' . $index] = (string) ($row['url_text'] ?? '');
            $params[':language_code_' . $index] = (string) ($row['language_code'] ?? '');
            $params[':store_id_' . $index] = (int) ($row['store_id'] ?? 0);
            $params[':url_text_redirect_' . $index] = (string) ($row['url_text_redirect'] ?? '');
        }

        $stmt = $pdo->prepare(
            sprintf(
                'SELECT `url_text`, `language_code`, `store_id`, `url_text_redirect`
                 FROM `xt_seo_url_redirect`
                 WHERE %s',
                implode(' OR ', $conditions)
            )
        );
        $stmt->execute($params);

        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
            $existingKeys[wela_seo_redirect_key(
                (string) ($row['url_text'] ?? ''),
                (string) ($row['url_text_redirect'] ?? ''),
                (string) ($row['language_code'] ?? ''),
                (int) ($row['store_id'] ?? 0)
            )] = true;
        }
    }

    return $existingKeys;
}

function wela_insert_seo_redirect_rows(PDO $pdo, array $rows): void
{
    $fieldNames = array_keys($rows[0]);
    $valueGroups = [];
    $params = [];

    foreach (array_values($rows) as $rowIndex => $row) {
        $placeholders = [];

        foreach ($fieldNames as $fieldName) {
            $placeholder = ':' . $fieldName . '_' . $rowIndex;
            $placeholders[] = $placeholder;
            $params[$placeholder] = $row[$fieldName] ?? null;
        }

        $valueGroups[] = '(' . implode(', ', $placeholders) . ')';
    }

    $stmt = $pdo->prepare(
        sprintf(
            'INSERT INTO `xt_seo_url_redirect` (%s) VALUES %s',
            implode(', ', array_map(static fn (string $field): string => "`{$field}`", $fieldNames)),
            implode(', ', $valueGroups)
        )
    );
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
