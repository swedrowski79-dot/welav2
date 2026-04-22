<?php

declare(strict_types=1);

final class StageCategoryMap
{
    private array $categories = [];
    private array $translations = [];
    private array $positions = [];
    private array $children = [];

    public function __construct(array $sourcesConfig)
    {
        $stageConfig = $sourcesConfig['sources']['stage'] ?? null;
        if (!is_array($stageConfig)) {
            throw new RuntimeException('Stage-Verbindung fuer Kategoriepfade fehlt.');
        }

        $stageDb = ConnectionFactory::create($stageConfig);
        $this->loadCategories($stageDb);
        $this->loadTranslations($stageDb);
        $this->buildTreePositions();
    }

    public function pathSegments(string|int|null $categoryId, string $languageCode, array $fallbackChain = ['de']): array
    {
        $normalizedId = $this->normalizeId($categoryId);
        if ($normalizedId === null) {
            return [];
        }

        $segments = [];
        $visited = [];
        $currentId = $normalizedId;

        while ($currentId !== null && isset($this->categories[$currentId]) && !isset($visited[$currentId])) {
            $visited[$currentId] = true;
            $name = $this->translationValue($currentId, $languageCode, $fallbackChain, 'name')
                ?? $this->normalizeString($this->categories[$currentId]['name_default'] ?? null);

            if ($name !== null) {
                array_unshift($segments, $name);
            }

            $currentId = $this->normalizeId($this->categories[$currentId]['parent_afs_id'] ?? null);
        }

        return $segments;
    }

    public function pathSegmentsLeafFirst(string|int|null $categoryId, string $languageCode, array $fallbackChain = ['de']): array
    {
        return array_reverse($this->pathSegments($categoryId, $languageCode, $fallbackChain));
    }

    public function depth(string|int|null $categoryId): int
    {
        $normalizedId = $this->normalizeId($categoryId);
        if ($normalizedId === null) {
            return 0;
        }

        return (int) ($this->positions[$normalizedId]['depth'] ?? 0);
    }

    public function hasCategory(string|int|null $categoryId): bool
    {
        $normalizedId = $this->normalizeId($categoryId);

        return $normalizedId !== null && isset($this->categories[$normalizedId]);
    }

    public function leftRight(string|int|null $categoryId): array
    {
        $normalizedId = $this->normalizeId($categoryId);
        if ($normalizedId === null) {
            return ['left' => 0, 'right' => 0];
        }

        return $this->positions[$normalizedId] ?? ['left' => 0, 'right' => 0, 'depth' => 0];
    }

    public function isTopCategory(string|int|null $categoryId): bool
    {
        $normalizedId = $this->normalizeId($categoryId);
        if ($normalizedId === null || !isset($this->categories[$normalizedId])) {
            return false;
        }

        return $this->normalizeId($this->categories[$normalizedId]['parent_afs_id'] ?? null) === null;
    }

    public function parentId(string|int|null $categoryId): ?string
    {
        $normalizedId = $this->normalizeId($categoryId);
        if ($normalizedId === null || !isset($this->categories[$normalizedId])) {
            return null;
        }

        return $this->normalizeId($this->categories[$normalizedId]['parent_afs_id'] ?? null);
    }

    private function loadCategories(PDO $stageDb): void
    {
        $stmt = $stageDb->query(
            'SELECT afs_wg_id, parent_afs_id, name_default, description_default, image, header_image, online_flag
             FROM `stage_categories`
             WHERE afs_wg_id IS NOT NULL
             ORDER BY afs_wg_id ASC'
        );

        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $categoryId = $this->normalizeId($row['afs_wg_id'] ?? null);
            if ($categoryId === null) {
                continue;
            }

            $this->categories[$categoryId] = $row;
        }
    }

    private function loadTranslations(PDO $stageDb): void
    {
        $stmt = $stageDb->query(
            'SELECT afs_wg_id, language_code, name, description, meta_title, meta_description
             FROM `stage_category_translations`
             WHERE afs_wg_id IS NOT NULL
             ORDER BY afs_wg_id ASC, language_code ASC'
        );

        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $categoryId = $this->normalizeId($row['afs_wg_id'] ?? null);
            $languageCode = $this->normalizeString($row['language_code'] ?? null);

            if ($categoryId === null || $languageCode === null) {
                continue;
            }

            $this->translations[$categoryId][$languageCode] = $row;
        }
    }

    private function buildTreePositions(): void
    {
        $roots = [];

        foreach ($this->categories as $categoryId => $row) {
            $parentId = $this->normalizeId($row['parent_afs_id'] ?? null);

            if ($parentId !== null && $parentId !== $categoryId && isset($this->categories[$parentId])) {
                $this->children[$parentId][] = $categoryId;
                continue;
            }

            $roots[] = $categoryId;
        }

        sort($roots, SORT_NATURAL);

        foreach ($this->children as &$children) {
            sort($children, SORT_NATURAL);
        }
        unset($children);

        $counter = 1;
        $visited = [];

        foreach ($roots as $rootId) {
            $this->walkTree($rootId, 0, $counter, $visited);
        }

        foreach (array_keys($this->categories) as $categoryId) {
            if (isset($visited[$categoryId])) {
                continue;
            }

            $this->walkTree($categoryId, 0, $counter, $visited);
        }
    }

    private function walkTree(string|int $categoryId, int $depth, int &$counter, array &$visited): void
    {
        $categoryId = (string) $categoryId;

        if (isset($visited[$categoryId])) {
            return;
        }

        $visited[$categoryId] = true;
        $left = $counter++;

        foreach ($this->children[$categoryId] ?? [] as $childId) {
            $this->walkTree($childId, $depth + 1, $counter, $visited);
        }

        $right = $counter++;
        $this->positions[$categoryId] = [
            'left' => $left,
            'right' => $right,
            'depth' => $depth,
        ];
    }

    private function translationValue(string $categoryId, string $languageCode, array $fallbackChain, string $field): ?string
    {
        $languageCodes = array_values(array_unique(array_filter(
            array_merge([$languageCode], $fallbackChain, ['de']),
            static fn (mixed $code): bool => is_string($code) && $code !== ''
        )));

        foreach ($languageCodes as $code) {
            $row = $this->translations[$categoryId][$code] ?? null;
            if (!is_array($row)) {
                continue;
            }

            $value = $this->normalizeString($row[$field] ?? null);
            if ($value !== null) {
                return $value;
            }
        }

        if ($field === 'description') {
            return $this->normalizeString($this->categories[$categoryId]['description_default'] ?? null);
        }

        if ($field === 'meta_title') {
            return $this->normalizeString($this->categories[$categoryId]['name_default'] ?? null);
        }

        if ($field === 'meta_description') {
            return $this->normalizeString($this->categories[$categoryId]['description_default'] ?? null);
        }

        return $this->normalizeString($this->categories[$categoryId]['name_default'] ?? null);
    }

    private function normalizeId(string|int|null $value): ?string
    {
        if ($value === null) {
            return null;
        }

        $normalized = trim((string) $value);
        if ($normalized === '' || $normalized === '0') {
            return null;
        }

        return $normalized;
    }

    private function normalizeString(mixed $value): ?string
    {
        if ($value === null) {
            return null;
        }

        $normalized = trim((string) $value);

        return $normalized === '' ? null : $normalized;
    }
}
