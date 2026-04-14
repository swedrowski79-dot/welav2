<?php use App\Web\Core\Html; ?>

<?php if (!empty($started)): ?>
    <div class="alert alert-success border-0 shadow-sm">Sync-Job wurde gestartet und laeuft im Hintergrund.</div>
<?php endif; ?>

<?php if (!empty($errorMessage)): ?>
    <div class="alert alert-danger border-0 shadow-sm"><?= Html::escape($errorMessage) ?></div>
<?php endif; ?>

<div class="panel-card p-4 mb-4">
    <div class="d-flex flex-column flex-lg-row gap-3 justify-content-between align-items-lg-end">
        <div>
            <h2 class="h5 mb-1">Sync starten</h2>
            <div class="text-secondary small">Import, Merge, Expand, Delta und die komplette Pipeline koennen direkt aus der Oberflaeche gestartet werden.</div>
        </div>
        <div class="d-flex flex-wrap gap-2">
            <form method="post" action="/sync-runs/start">
                <input type="hidden" name="job" value="import_all">
                <button class="btn btn-primary" type="submit">Import starten</button>
            </form>
            <form method="post" action="/sync-runs/start">
                <input type="hidden" name="job" value="import_products">
                <button class="btn btn-outline-primary" type="submit">Produkt-Import</button>
            </form>
            <form method="post" action="/sync-runs/start">
                <input type="hidden" name="job" value="import_categories">
                <button class="btn btn-outline-primary" type="submit">Kategorie-Import</button>
            </form>
            <form method="post" action="/sync-runs/start">
                <input type="hidden" name="job" value="merge">
                <button class="btn btn-outline-primary" type="submit">Merge starten</button>
            </form>
            <form method="post" action="/sync-runs/start">
                <input type="hidden" name="job" value="expand">
                <button class="btn btn-outline-secondary" type="submit">Expand starten</button>
            </form>
            <form method="post" action="/sync-runs/start">
                <input type="hidden" name="job" value="delta">
                <button class="btn btn-outline-dark" type="submit">Delta starten</button>
            </form>
            <form method="post" action="/sync-runs/start">
                <input type="hidden" name="job" value="full_pipeline">
                <button class="btn btn-dark" type="submit">Full Pipeline</button>
            </form>
        </div>
    </div>
</div>

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
