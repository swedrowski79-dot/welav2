<?php use App\Web\Core\Html; ?>

<div class="panel-card p-4 mb-4">
    <form class="row g-3" method="get" action="/logs">
        <div class="col-12 col-md-5">
            <label class="form-label">Suche</label>
            <input class="form-control" type="search" name="q" value="<?= Html::escape($filters['q']) ?>" placeholder="Nachricht oder Kontext">
        </div>
        <div class="col-12 col-md-3">
            <label class="form-label">Level</label>
            <select class="form-select" name="level">
                <option value="">Alle</option>
                <?php foreach (['info', 'warning', 'error'] as $level): ?>
                    <option value="<?= Html::escape($level) ?>" <?= $filters['level'] === $level ? 'selected' : '' ?>><?= Html::escape($level) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="col-12 col-md-2">
            <label class="form-label">Pro Seite</label>
            <select class="form-select" name="per_page">
                <?php foreach ([10, 20, 50, 100] as $size): ?>
                    <option value="<?= $size ?>" <?= $paginator->perPage === $size ? 'selected' : '' ?>><?= $size ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="col-12 col-md-2 d-flex align-items-end">
            <button class="btn btn-primary w-100" type="submit">Filtern</button>
        </div>
    </form>
</div>

<div class="panel-card p-0">
    <div class="table-responsive">
        <table class="table table-hover mb-0">
            <thead><tr><th>Zeit</th><th>Level</th><th>Run</th><th>Nachricht</th><th>Kontext</th></tr></thead>
            <tbody>
            <?php foreach ($logs as $log): ?>
                <tr>
                    <td><?= Html::escape($log['created_at']) ?></td>
                    <td><span class="badge <?= Html::badgeClass($log['level']) ?>"><?= Html::escape($log['level']) ?></span></td>
                    <td><?= $log['sync_run_id'] ? '<a href="/sync-runs/show?id=' . Html::escape($log['sync_run_id']) . '">#' . Html::escape($log['sync_run_id']) . '</a>' : '-' ?></td>
                    <td class="truncate-cell"><?= Html::escape($log['message']) ?></td>
                    <td class="truncate-cell"><code><?= Html::escape($log['context_json'] ?? '{}') ?></code></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <div class="d-flex justify-content-between align-items-center p-4">
        <div class="text-secondary small"><?= Html::escape($paginator->total) ?> Eintraege</div>
        <?php $path = '/logs'; $query = ['q' => $filters['q'], 'level' => $filters['level'], 'per_page' => $paginator->perPage]; require dirname(__DIR__) . '/partials/pagination.php'; ?>
    </div>
</div>
