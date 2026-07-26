<?php use App\Web\Core\Html; ?>

<?php if (!empty($retryDone)): ?>
    <div class="alert alert-success border-0 shadow-sm">
        <?= Html::escape((int) ($retryCount ?? 0)) ?> Queue-Eintraege wurden auf `pending` zurueckgesetzt.
    </div>
<?php endif; ?>

<?php if (!empty($errorMessage)): ?>
    <div class="alert alert-danger border-0 shadow-sm"><?= Html::escape($errorMessage) ?></div>
<?php endif; ?>

<div class="row g-4 mb-4">
    <?php foreach (['pending' => 'Wartend', 'processing' => 'In Bearbeitung', 'done' => 'Erledigt', 'error' => 'Fehler'] as $status => $label): ?>
        <div class="col-6 col-xl-3">
            <div class="metric-card p-3 h-100">
                <div class="small text-secondary mb-1"><?= Html::escape($label) ?></div>
                <div class="h3 mb-0"><?= Html::escape($queueSummary[$status] ?? 0) ?></div>
            </div>
        </div>
    <?php endforeach; ?>
</div>

<div class="row g-4 mb-4">
    <div class="col-12 col-xl-6">
        <div class="panel-card p-4 h-100">
            <h2 class="h5 mb-2">Letztes Delta</h2>
            <?php if (!empty($latestDeltaVisibility)): ?>
                <div class="fw-semibold"><?= Html::escape($latestDeltaVisibility['reason'] ?? '-') ?></div>
                <div class="small text-secondary mt-2">
                    Queue erstellt: <?= Html::escape($latestDeltaVisibility['context']['queue_created'] ?? 0) ?>
                    · Fehler: <?= Html::escape($latestDeltaVisibility['context']['errors'] ?? 0) ?>
                </div>
            <?php else: ?>
                <div class="small text-secondary">Noch kein Delta-Lauf vorhanden.</div>
            <?php endif; ?>
        </div>
    </div>
    <div class="col-12 col-xl-6">
        <div class="panel-card p-4 h-100">
            <h2 class="h5 mb-2">Letzter Export Worker</h2>
            <?php if (!empty($latestWorkerVisibility)): ?>
                <div class="fw-semibold"><?= Html::escape($latestWorkerVisibility['reason'] ?? '-') ?></div>
                <div class="small text-secondary mt-2">
                    Verarbeitet: <?= Html::escape($latestWorkerVisibility['context']['processed'] ?? 0) ?>
                    · Done: <?= Html::escape($latestWorkerVisibility['context']['done'] ?? 0) ?>
                    · Fehler: <?= Html::escape($latestWorkerVisibility['context']['error'] ?? 0) ?>
                </div>
            <?php else: ?>
                <div class="small text-secondary">Noch kein Export-Worker-Lauf vorhanden.</div>
            <?php endif; ?>
        </div>
    </div>
</div>

<?php if (!empty($queueSummaryByEntity)): ?>
    <div class="panel-card p-0 mb-4">
        <div class="card-header px-4 py-3 d-flex justify-content-between align-items-center">
            <h2 class="h5 mb-0">Queue nach Datentyp</h2>
            <?php if (($queueIssueSummary['total'] ?? 0) > 0): ?>
                <span class="badge text-bg-warning"><?= Html::escape($queueIssueSummary['total']) ?> mit Fehlerhinweis</span>
            <?php endif; ?>
        </div>
        <div class="table-responsive">
            <table class="table table-hover mb-0">
                <thead><tr><th>Datentyp</th><th>Wartend</th><th>In Bearbeitung</th><th>Erledigt</th><th>Fehler</th><th></th></tr></thead>
                <tbody>
                <?php foreach ($queueSummaryByEntity as $entityType => $summary): ?>
                    <tr>
                        <td class="fw-semibold"><?= Html::escape($entityType) ?></td>
                        <td><?= Html::escape($summary['pending'] ?? 0) ?></td>
                        <td><?= Html::escape($summary['processing'] ?? 0) ?></td>
                        <td><?= Html::escape($summary['done'] ?? 0) ?></td>
                        <td><?= Html::escape($summary['error'] ?? 0) ?></td>
                        <td class="text-end">
                            <?php if ((int) ($summary['error'] ?? 0) > 0): ?>
                                <form method="post" action="/pipeline/retry" onsubmit="return confirm('Fehlgeschlagene <?= Html::escape($entityType) ?>-Eintraege erneut einreihen?');">
                                    <input type="hidden" name="scope" value="entity_type">
                                    <input type="hidden" name="entity_type" value="<?= Html::escape($entityType) ?>">
                                    <button class="btn btn-sm btn-outline-warning" type="submit">Fehler erneut einreihen</button>
                                </form>
                            <?php endif; ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
<?php endif; ?>

<div class="panel-card p-4 mb-4">
    <form class="row g-3" method="get" action="/pipeline/queue">
        <div class="col-12 col-md-3">
            <label class="form-label" for="queue_entity_type">Datentyp</label>
            <select class="form-select" id="queue_entity_type" name="entity_type"><option value="">Alle</option><?php foreach ($entityTypes as $entityType): ?><option value="<?= Html::escape($entityType) ?>" <?= $filters['entity_type'] === $entityType ? 'selected' : '' ?>><?= Html::escape($entityType) ?></option><?php endforeach; ?></select>
        </div>
        <div class="col-12 col-md-3">
            <label class="form-label" for="queue_status">Status</label>
            <select class="form-select" id="queue_status" name="status"><option value="">Alle</option><?php foreach (['pending', 'processing', 'done', 'error'] as $status): ?><option value="<?= Html::escape($status) ?>" <?= $filters['status'] === $status ? 'selected' : '' ?>><?= Html::escape($status) ?></option><?php endforeach; ?></select>
        </div>
        <div class="col-12 col-md-2">
            <label class="form-label" for="queue_action">Aktion</label>
            <select class="form-select" id="queue_action" name="action"><option value="">Alle</option><option value="insert" <?= $filters['action'] === 'insert' ? 'selected' : '' ?>>insert</option><option value="update" <?= $filters['action'] === 'update' ? 'selected' : '' ?>>update</option></select>
        </div>
        <div class="col-12 col-md-2">
            <label class="form-label" for="queue_per_page">Pro Seite</label>
            <select class="form-select" id="queue_per_page" name="per_page"><?php foreach ([10, 20, 50, 100] as $size): ?><option value="<?= $size ?>" <?= $paginator->perPage === $size ? 'selected' : '' ?>><?= $size ?></option><?php endforeach; ?></select>
        </div>
        <div class="col-12 col-md-2 d-flex align-items-end gap-2"><button class="btn btn-primary w-100" type="submit">Filtern</button><a class="btn btn-outline-secondary" href="/pipeline/queue">Reset</a></div>
    </form>
</div>

<div class="panel-card p-0">
    <div class="card-header px-4 py-3 d-flex justify-content-between align-items-center"><h2 class="h5 mb-0">Eintraege</h2><span class="small text-secondary"><?= Html::escape($paginator->total) ?> Eintraege</span></div>
    <div class="table-responsive">
        <table class="table table-hover align-middle mb-0">
            <thead><tr><th>ID</th><th>Datentyp</th><th>Aktion</th><th>Status</th><th>Versuche</th><th>Letzte Verarbeitung</th><th></th></tr></thead>
            <tbody>
            <?php foreach ($queueEntries as $entry): ?>
                <tr>
                    <td>#<?= Html::escape($entry['id']) ?></td>
                    <td><div class="fw-semibold"><?= Html::escape($entry['entity_type']) ?></div><div class="small text-secondary">ID <?= Html::escape($entry['entity_id']) ?></div></td>
                    <td><?= Html::escape($entry['action']) ?></td>
                    <td><span class="badge <?= Html::badgeClass($entry['status']) ?>"><?= Html::escape($entry['status']) ?></span><?php if (!empty($entry['last_error'])): ?><div class="small text-danger mt-1 truncate-cell" title="<?= Html::escape($entry['last_error']) ?>"><?= Html::escape($entry['last_error']) ?></div><?php endif; ?></td>
                    <td><?= Html::escape($entry['attempt_count'] ?? 0) ?></td>
                    <td class="small"><?= Html::escape($entry['processed_at'] ?? $entry['claimed_at'] ?? $entry['available_at'] ?? '-') ?></td>
                    <td class="text-end"><?php if (($entry['status'] ?? '') === 'error'): ?><form method="post" action="/pipeline/retry"><input type="hidden" name="scope" value="entry"><input type="hidden" name="queue_id" value="<?= Html::escape($entry['id']) ?>"><button class="btn btn-sm btn-outline-warning" type="submit">Erneut einreihen</button></form><?php endif; ?></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <div class="d-flex justify-content-end p-4"><?php $path = '/pipeline/queue'; $query = ['entity_type' => $filters['entity_type'], 'status' => $filters['status'], 'action' => $filters['action'], 'per_page' => $paginator->perPage]; require dirname(__DIR__) . '/partials/pagination.php'; ?></div>
</div>
