<?php use App\Web\Core\Html; ?>

<div class="panel-card p-4 mb-4">
    <form class="row g-3" method="get" action="/sync-runs">
        <div class="col-12 col-md-4">
            <label class="form-label">Suche</label>
            <input class="form-control" type="search" name="q" value="<?= Html::escape($filters['q']) ?>" placeholder="Typ oder Nachricht">
        </div>
        <div class="col-12 col-md-3">
            <label class="form-label">Status</label>
            <select class="form-select" name="status">
                <option value="">Alle</option>
                <?php foreach (['running', 'success', 'failed', 'warning'] as $status): ?>
                    <option value="<?= Html::escape($status) ?>" <?= $filters['status'] === $status ? 'selected' : '' ?>><?= Html::escape($status) ?></option>
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
        <div class="col-12 col-md-3 d-flex align-items-end gap-2">
            <button class="btn btn-primary w-100" type="submit">Filtern</button>
            <a class="btn btn-outline-secondary" href="/sync-runs">Reset</a>
        </div>
    </form>
</div>

<div class="panel-card p-0">
    <div class="table-responsive">
        <table class="table table-hover mb-0">
            <thead><tr><th>ID</th><th>Typ</th><th>Status</th><th>Import</th><th>Merge</th><th>Fehler</th><th>Start</th><th>Ende</th><th></th></tr></thead>
            <tbody>
            <?php foreach ($runs as $run): ?>
                <tr>
                    <td>#<?= Html::escape($run['id']) ?></td>
                    <td><?= Html::escape($run['run_type']) ?></td>
                    <td><span class="badge <?= Html::badgeClass($run['status']) ?>"><?= Html::escape($run['status']) ?></span></td>
                    <td><?= Html::escape($run['imported_records'] ?? 0) ?></td>
                    <td><?= Html::escape($run['merged_records'] ?? 0) ?></td>
                    <td><?= Html::escape($run['error_count'] ?? 0) ?></td>
                    <td><?= Html::escape($run['started_at'] ?? '-') ?></td>
                    <td><?= Html::escape($run['ended_at'] ?? '-') ?></td>
                    <td><a class="btn btn-sm btn-outline-primary" href="/sync-runs/show?id=<?= Html::escape($run['id']) ?>">Detail</a></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <div class="d-flex justify-content-between align-items-center p-4">
        <div class="text-secondary small"><?= Html::escape($paginator->total) ?> Eintraege</div>
        <?php $path = '/sync-runs'; $query = ['q' => $filters['q'], 'status' => $filters['status'], 'per_page' => $paginator->perPage]; require dirname(__DIR__) . '/partials/pagination.php'; ?>
    </div>
</div>
