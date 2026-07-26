<?php

use App\Web\Core\Html;

if (!$paginator->hasPages()) {
    return;
}

$pageParam = $pageParam ?? 'page';
$fragment = isset($fragment) && $fragment !== '' ? '#' . ltrim((string) $fragment, '#') : '';
$buildPageUrl = static function (int $page) use ($path, $query, $pageParam, $fragment): string {
    return Html::buildUrl($path, array_merge($query, [$pageParam => $page])) . $fragment;
};
?>
<nav aria-label="Pagination">
    <ul class="pagination pagination-sm mb-0">
        <?php if ($paginator->previousPage() !== null): ?>
            <li class="page-item">
                <a class="page-link" href="<?= Html::escape($buildPageUrl(1)) ?>">Erste</a>
            </li>
            <li class="page-item">
                <a class="page-link" href="<?= Html::escape($buildPageUrl($paginator->previousPage())) ?>">Zurueck</a>
            </li>
        <?php else: ?>
            <li class="page-item disabled"><span class="page-link">Erste</span></li>
            <li class="page-item disabled"><span class="page-link">Zurueck</span></li>
        <?php endif; ?>

        <?php foreach ($paginator->compactPages() as $item): ?>
            <?php if ($item === 'ellipsis'): ?>
                <li class="page-item disabled"><span class="page-link">&hellip;</span></li>
            <?php else: ?>
                <li class="page-item <?= $item === $paginator->page ? 'active' : '' ?>">
                    <a class="page-link" href="<?= Html::escape($buildPageUrl($item)) ?>">
                        <?= Html::escape((string) $item) ?>
                    </a>
                </li>
            <?php endif; ?>
        <?php endforeach; ?>

        <?php if ($paginator->nextPage() !== null): ?>
            <li class="page-item">
                <a class="page-link" href="<?= Html::escape($buildPageUrl($paginator->nextPage())) ?>">Weiter</a>
            </li>
            <li class="page-item">
                <a class="page-link" href="<?= Html::escape($buildPageUrl($paginator->totalPages())) ?>">Letzte</a>
            </li>
        <?php else: ?>
            <li class="page-item disabled"><span class="page-link">Weiter</span></li>
            <li class="page-item disabled"><span class="page-link">Letzte</span></li>
        <?php endif; ?>
    </ul>
</nav>
