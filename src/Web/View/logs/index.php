<?php use App\Web\Core\Html; ?>

<?php if (!empty($resetDone)): ?>
    <div class="alert alert-warning border-0 shadow-sm">Reset-Aktion `<?= Html::escape($resetDone) ?>` wurde ausgefuehrt und in den Logs vermerkt.</div>
<?php endif; ?>

<?php if (!empty($errorMessage)): ?>
    <div class="alert alert-danger border-0 shadow-sm"><?= Html::escape($errorMessage) ?></div>
<?php endif; ?>

<div class="panel-card p-4 mb-4 border border-danger-subtle">
    <div class="d-flex flex-column flex-lg-row gap-3 justify-content-between align-items-lg-start">
        <div>
            <h2 class="h5 mb-1 text-danger">Monitoring-Resets</h2>
            <div class="text-secondary small">Achtung: Diese Aktionen entfernen Monitoring-Daten. Vor jedem Reset ist eine Bestaetigung erforderlich.</div>
        </div>
        <div class="d-flex flex-wrap gap-2">
            <?php foreach ([
                ['action' => 'logs', 'label' => 'Reset Logs', 'warning' => 'Alle Sync-Logs werden geloescht. Fortfahren?'],
                ['action' => 'errors', 'label' => 'Reset Errors', 'warning' => 'Alle Sync-Fehler werden geloescht. Fortfahren?'],
                ['action' => 'runs', 'label' => 'Reset Runs', 'warning' => 'Die komplette Sync-Laufhistorie wird geloescht. Fortfahren?'],
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
