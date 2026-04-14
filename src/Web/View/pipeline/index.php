<?php use App\Web\Core\Html; ?>

<?php if (!empty($started)): ?>
    <div class="alert alert-success border-0 shadow-sm">Pipeline-Job wurde gestartet und laeuft im Hintergrund.</div>
<?php endif; ?>

<?php if (!empty($resetDone)): ?>
    <div class="alert alert-warning border-0 shadow-sm">Reset-Aktion `<?= Html::escape($resetDone) ?>` wurde ausgefuehrt und in den Logs vermerkt.</div>
<?php endif; ?>

<?php if (!empty($errorMessage)): ?>
    <div class="alert alert-danger border-0 shadow-sm"><?= Html::escape($errorMessage) ?></div>
<?php endif; ?>

<div class="row g-4 mb-4">
    <div class="col-12 col-xl-7">
        <div class="panel-card p-4 h-100">
            <div class="d-flex justify-content-between align-items-start mb-3">
                <div>
                    <h2 class="h5 mb-1">Pipeline-Steuerung</h2>
                    <div class="text-secondary small">Merge, Expand, Delta oder die komplette Pipeline direkt aus der Admin-Oberflaeche starten.</div>
                </div>
                <a class="btn btn-sm btn-outline-secondary" href="/pipeline/state">Produkt Export State</a>
            </div>
            <div class="d-flex flex-wrap gap-2">
                <?php foreach ([
                    ['job' => 'merge', 'label' => 'Run Merge', 'class' => 'btn-outline-primary'],
                    ['job' => 'expand', 'label' => 'Run Expand', 'class' => 'btn-outline-secondary'],
                    ['job' => 'delta', 'label' => 'Run Delta', 'class' => 'btn-outline-dark'],
                    ['job' => 'full_pipeline', 'label' => 'Run Full Pipeline', 'class' => 'btn-primary'],
                ] as $job): ?>
                    <form method="post" action="/pipeline/start">
                        <input type="hidden" name="job" value="<?= Html::escape($job['job']) ?>">
                        <button class="btn <?= Html::escape($job['class']) ?>" type="submit"><?= Html::escape($job['label']) ?></button>
                    </form>
                <?php endforeach; ?>
            </div>
        </div>
    </div>
    <div class="col-12 col-md-6 col-xl-3">
        <div class="metric-card p-4 h-100">
            <div class="metric-icon mb-3"><i class="bi bi-hourglass-split"></i></div>
            <div class="display-6 fw-semibold"><?= Html::escape($queueSummary['pending']) ?></div>
            <div class="text-secondary">Queue pending</div>
            <div class="small text-secondary mt-2">Done: <?= Html::escape($queueSummary['done']) ?>, Error: <?= Html::escape($queueSummary['error']) ?></div>
        </div>
    </div>
    <div class="col-12 col-md-6 col-xl-2">
        <div class="metric-card p-4 h-100">
            <div class="metric-icon mb-3"><i class="bi bi-fingerprint"></i></div>
            <div class="display-6 fw-semibold"><?= Html::escape($stateSummary['entries']) ?></div>
            <div class="text-secondary">State-Eintraege</div>
        </div>
    </div>
</div>

<div class="panel-card p-4 mb-4 border border-danger-subtle">
    <div class="d-flex flex-column flex-lg-row gap-3 justify-content-between align-items-lg-start">
        <div>
            <h2 class="h5 mb-1 text-danger">Reset-Funktionen</h2>
            <div class="text-secondary small">Achtung: Diese Aktionen entfernen Queue-, Stage- oder Delta-Daten. Vor jedem Reset ist eine Bestaetigung erforderlich.</div>
        </div>
        <div class="d-flex flex-wrap gap-2">
            <?php foreach ([
                ['action' => 'queue', 'label' => 'Reset Queue', 'warning' => 'Alle Export-Queue-Eintraege werden geloescht. Fortfahren?'],
                ['action' => 'stage', 'label' => 'Reset Stage', 'warning' => 'Alle stage_* Tabellen werden geleert. Fortfahren?'],
                ['action' => 'delta_state', 'label' => 'Reset Delta State', 'warning' => 'Der komplette Produkt-Delta-State wird geloescht. Fortfahren?'],
                ['action' => 'full', 'label' => 'Full Reset', 'warning' => 'Queue, Stage und Delta-State werden komplett zurueckgesetzt. Fortfahren?'],
            ] as $reset): ?>
                <form method="post" action="/pipeline/reset" onsubmit="return confirm('<?= Html::escape($reset['warning']) ?>');">
                    <input type="hidden" name="action" value="<?= Html::escape($reset['action']) ?>">
                    <input type="hidden" name="confirmed" value="yes">
                    <button class="btn btn-outline-danger" type="submit"><?= Html::escape($reset['label']) ?></button>
                </form>
            <?php endforeach; ?>
        </div>
    </div>
</div>

<div class="panel-card p-4 mb-4">
    <form class="row g-3" method="get" action="/pipeline">
        <div class="col-12 col-md-3">
            <label class="form-label">Entity Type</label>
            <select class="form-select" name="entity_type">
                <option value="">Alle</option>
                <option value="product" <?= $filters['entity_type'] === 'product' ? 'selected' : '' ?>>product</option>
            </select>
        </div>
        <div class="col-12 col-md-3">
            <label class="form-label">Status</label>
            <select class="form-select" name="status">
                <option value="">Alle</option>
                <?php foreach (['pending', 'done', 'error'] as $status): ?>
                    <option value="<?= Html::escape($status) ?>" <?= $filters['status'] === $status ? 'selected' : '' ?>><?= Html::escape($status) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="col-12 col-md-3">
            <label class="form-label">Action</label>
            <select class="form-select" name="action">
                <option value="">Alle</option>
                <?php foreach (['insert', 'update'] as $action): ?>
                    <option value="<?= Html::escape($action) ?>" <?= $filters['action'] === $action ? 'selected' : '' ?>><?= Html::escape($action) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="col-12 col-md-1">
            <label class="form-label">Pro Seite</label>
            <select class="form-select" name="per_page">
                <?php foreach ([10, 20, 50, 100] as $size): ?>
                    <option value="<?= $size ?>" <?= $paginator->perPage === $size ? 'selected' : '' ?>><?= $size ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="col-12 col-md-2 d-flex align-items-end gap-2">
            <button class="btn btn-primary w-100" type="submit">Filtern</button>
            <a class="btn btn-outline-secondary" href="/pipeline">Reset</a>
        </div>
    </form>
</div>

<div class="panel-card p-0">
    <div class="card-header px-4 py-3 d-flex justify-content-between align-items-center">
        <h2 class="h5 mb-0">Export Queue</h2>
        <span class="text-secondary small"><?= Html::escape($paginator->total) ?> Eintraege</span>
    </div>
    <div class="table-responsive">
        <table class="table table-hover mb-0">
            <thead><tr><th>ID</th><th>Entity</th><th>Action</th><th>Status</th><th>Payload JSON</th><th>Created</th></tr></thead>
            <tbody>
            <?php foreach ($queueEntries as $entry): ?>
                <tr>
                    <td>#<?= Html::escape($entry['id']) ?></td>
                    <td>
                        <div class="fw-semibold"><?= Html::escape($entry['entity_type']) ?></div>
                        <div class="small text-secondary">ID <?= Html::escape($entry['entity_id']) ?></div>
                    </td>
                    <td><?= Html::escape($entry['action']) ?></td>
                    <td><span class="badge <?= Html::badgeClass($entry['status']) ?>"><?= Html::escape($entry['status']) ?></span></td>
                    <td><pre class="mb-0 small text-wrap"><?= Html::escape((string) ($entry['payload'] ?? '')) ?></pre></td>
                    <td><?= Html::escape($entry['created_at'] ?? '-') ?></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <div class="d-flex justify-content-between align-items-center p-4">
        <div class="text-secondary small">Nur erlaubte Filter: entity_type, status, action.</div>
        <?php $path = '/pipeline'; $query = ['entity_type' => $filters['entity_type'], 'status' => $filters['status'], 'action' => $filters['action'], 'per_page' => $paginator->perPage]; require dirname(__DIR__) . '/partials/pagination.php'; ?>
    </div>
</div>
