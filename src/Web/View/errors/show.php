<?php use App\Web\Core\Html; ?>

<div class="panel-card p-4">
    <?php if ($error === null): ?>
        <div class="text-secondary">Der Fehlerdatensatz wurde nicht gefunden.</div>
    <?php else: ?>
        <div class="d-flex justify-content-between align-items-start mb-4">
            <div>
                <h2 class="h4 mb-1"><?= Html::escape($error['message']) ?></h2>
                <div class="text-secondary"><?= Html::escape($error['created_at']) ?></div>
            </div>
            <span class="badge <?= Html::badgeClass($error['status']) ?>"><?= Html::escape($error['status']) ?></span>
        </div>
        <dl class="row">
            <dt class="col-sm-3">Quelle</dt><dd class="col-sm-9"><?= Html::escape($error['source'] ?: '-') ?></dd>
            <dt class="col-sm-3">Datensatz</dt><dd class="col-sm-9"><?= Html::escape($error['record_identifier'] ?: '-') ?></dd>
            <dt class="col-sm-3">Sync-Run</dt><dd class="col-sm-9"><?= $error['sync_run_id'] ? '<a href="/sync-runs/show?id=' . Html::escape($error['sync_run_id']) . '">#' . Html::escape($error['sync_run_id']) . '</a>' : '-' ?></dd>
            <dt class="col-sm-3">Run-Status</dt><dd class="col-sm-9"><?= Html::escape($error['run_status'] ?: '-') ?></dd>
        </dl>
        <div class="mt-4">
            <div class="text-secondary small mb-2">Details</div>
            <pre class="bg-light border rounded-4 p-3 mb-0 text-wrap"><?= Html::escape($error['details'] ?? '') ?></pre>
        </div>
    <?php endif; ?>
</div>
