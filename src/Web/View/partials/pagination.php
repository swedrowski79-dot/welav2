<?php

use App\Web\Core\Html;

if (!$paginator->hasPages()) {
    return;
}
?>
<nav aria-label="Pagination">
    <ul class="pagination pagination-sm mb-0">
        <?php for ($pageNumber = 1; $pageNumber <= $paginator->totalPages(); $pageNumber++): ?>
            <li class="page-item <?= $pageNumber === $paginator->page ? 'active' : '' ?>">
                <a class="page-link" href="<?= Html::escape(Html::buildUrl($path, array_merge($query, ['page' => $pageNumber]))) ?>">
                    <?= Html::escape($pageNumber) ?>
                </a>
            </li>
        <?php endfor; ?>
    </ul>
</nav>
