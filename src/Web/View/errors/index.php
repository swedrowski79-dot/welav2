<?php use App\Web\Core\Html; ?>

<?php if (!empty($resetDone)): ?>
    <div class="alert alert-warning border-0 shadow-sm">Reset-Aktion `<?= Html::escape($resetDone) ?>` wurde ausgefuehrt und in den Logs vermerkt.</div>
<?php endif; ?>

<?php if (!empty($errorMessage)): ?>
    <div class="alert alert-danger border-0 shadow-sm"><?= Html::escape($errorMessage) ?></div>
<?php endif; ?>

<div class="panel-card p-4 mb-4">
    <div class="d-flex flex-column flex-lg-row justify-content-between align-items-lg-center gap-3">
        <div>
            <h2 class="h5 mb-1">Fehler vs. Logs</h2>
            <div class="text-secondary small">Diese Ansicht zeigt nur Fehlerdatensaetze mit Details. Fuer komplette Laufprotokolle inklusive Info- und Warnmeldungen gibt es die Logansicht.</div>
        </div>
        <a class="btn btn-outline-secondary" href="/logs">Zur Logansicht</a>
    </div>
</div>

<div class="panel-card p-4 mb-4">
    <div class="d-flex flex-column flex-lg-row justify-content-between align-items-lg-start gap-3 mb-3">
        <div>
            <h2 class="h5 mb-1">XT-/Exportprobleme</h2>
            <div class="text-secondary small">Retry-faehige Export-Worker-Warnungen und Queue-Eintraege mit `last_error` werden hier vor den terminalen `sync_errors` sichtbar gemacht.</div>
        </div>
        <a class="btn btn-outline-secondary" href="/pipeline">Zur Pipeline</a>
    </div>
    <div class="row g-3 mb-3">
        <div class="col-6 col-md-3">
            <div class="border rounded-4 p-3 bg-light-subtle">
                <div class="small text-secondary">Queue-Probleme gesamt</div>
                <div class="fw-semibold"><?= Html::escape($queueIssueSummary['total'] ?? 0) ?></div>
            </div>
        </div>
        <div class="col-6 col-md-3">
            <div class="border rounded-4 p-3 bg-light-subtle">
                <div class="small text-secondary">Pending mit Fehler</div>
                <div class="fw-semibold"><?= Html::escape($queueIssueSummary['pending'] ?? 0) ?></div>
            </div>
        </div>
        <div class="col-6 col-md-3">
            <div class="border rounded-4 p-3 bg-light-subtle">
                <div class="small text-secondary">Error mit Fehler</div>
                <div class="fw-semibold"><?= Html::escape($queueIssueSummary['error'] ?? 0) ?></div>
            </div>
        </div>
        <div class="col-6 col-md-3">
            <div class="border rounded-4 p-3 bg-light-subtle">
                <div class="small text-secondary">Worker-Warnungen</div>
                <div class="fw-semibold"><?= Html::escape(count($recentExportWorkerIssues ?? [])) ?></div>
            </div>
        </div>
    </div>
    <div class="row g-4">
        <div class="col-12 col-xl-6">
            <div class="border rounded-4 p-3 h-100 bg-light-subtle">
                <div class="fw-semibold mb-2">Letzte Worker-Warnungen / Fehler</div>
                <?php if (empty($recentExportWorkerIssues)): ?>
                    <div class="small text-secondary">Keine aktuellen Export-Worker-Probleme vorhanden.</div>
                <?php else: ?>
                    <div class="d-grid gap-2">
                        <?php foreach ($recentExportWorkerIssues as $issue): ?>
                            <div class="border rounded-3 p-2 bg-white">
                                <div class="d-flex justify-content-between gap-2">
                                    <span class="badge <?= Html::badgeClass($issue['level'] ?? 'warning') ?>"><?= Html::escape($issue['level'] ?? 'warning') ?></span>
                                    <div class="small text-secondary"><?= Html::escape($issue['created_at'] ?? '-') ?></div>
                                </div>
                                <div class="small fw-semibold mt-2"><?= Html::escape($issue['message'] ?? '') ?></div>
                                <div class="small text-secondary mt-1 truncate-cell"><code><?= Html::escape($issue['context_json'] ?? '{}') ?></code></div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>
        </div>
        <div class="col-12 col-xl-6">
            <div class="border rounded-4 p-3 h-100 bg-light-subtle">
                <div class="fw-semibold mb-2">Letzte Queue-Eintraege mit `last_error`</div>
                <?php if (empty($recentQueueIssues)): ?>
                    <div class="small text-secondary">Keine Queue-Eintraege mit Fehlertext vorhanden.</div>
                <?php else: ?>
                    <div class="d-grid gap-2">
                        <?php foreach ($recentQueueIssues as $issue): ?>
                            <div class="border rounded-3 p-2 bg-white">
                                <div class="d-flex justify-content-between gap-2">
                                    <div class="small fw-semibold"><?= Html::escape(($issue['entity_type'] ?? '-') . ' #' . ($issue['entity_id'] ?? '-')) ?></div>
                                    <span class="badge <?= Html::badgeClass($issue['status'] ?? 'error') ?>"><?= Html::escape($issue['status'] ?? '-') ?></span>
                                </div>
                                <div class="small text-secondary mt-1">Action <?= Html::escape($issue['action'] ?? '-') ?> · Attempts <?= Html::escape($issue['attempt_count'] ?? 0) ?></div>
                                <div class="small text-danger mt-2"><?= Html::escape($issue['last_error'] ?? '') ?></div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<div class="panel-card p-4 mb-4 border border-danger-subtle">
    <div class="d-flex flex-column flex-lg-row gap-3 justify-content-between align-items-lg-start">
        <div>
            <h2 class="h5 mb-1 text-danger">Fehler-Reset</h2>
            <div class="text-secondary small">Achtung: Diese Aktion entfernt alle Sync-Fehlerdatensaetze. Vor dem Reset ist eine Bestaetigung erforderlich.</div>
        </div>
        <form method="post" action="/pipeline/reset" onsubmit="return confirm('Alle Sync-Fehler werden geloescht. Fortfahren?');">
            <input type="hidden" name="action" value="errors">
            <input type="hidden" name="confirmed" value="yes">
            <button class="btn btn-outline-danger" type="submit">Reset Errors</button>
        </form>
    </div>
</div>

<div class="panel-card p-4 mb-4">
    <form class="row g-3" method="get" action="/errors">
        <div class="col-12 col-md-5">
            <label class="form-label">Suche</label>
            <input class="form-control" type="search" name="q" value="<?= Html::escape($filters['q']) ?>" placeholder="Nachricht, Details oder Quelle">
        </div>
        <div class="col-12 col-md-3">
            <label class="form-label">Status</label>
            <select class="form-select" name="status">
                <option value="">Alle</option>
                <?php foreach (['open', 'resolved'] as $status): ?>
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
        <div class="col-12 col-md-2 d-flex align-items-end">
            <button class="btn btn-primary w-100" type="submit">Filtern</button>
        </div>
    </form>
</div>

<div class="panel-card p-0">
    <div class="table-responsive">
        <table class="table table-hover mb-0">
            <thead><tr><th>ID</th><th>Status</th><th>Quelle</th><th>Meldung</th><th>Zeit</th><th></th></tr></thead>
            <tbody>
            <?php foreach ($errors as $error): ?>
                <tr>
                    <td>#<?= Html::escape($error['id']) ?></td>
                    <td><span class="badge <?= Html::badgeClass($error['status']) ?>"><?= Html::escape($error['status']) ?></span></td>
                    <td><?= Html::escape($error['source'] ?: '-') ?></td>
                    <td class="truncate-cell"><?= Html::escape($error['message']) ?></td>
                    <td><?= Html::escape($error['created_at']) ?></td>
                    <td><a class="btn btn-sm btn-outline-danger" href="/errors/show?id=<?= Html::escape($error['id']) ?>">Detail</a></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <div class="d-flex justify-content-between align-items-center p-4">
        <div class="text-secondary small"><?= Html::escape($paginator->total) ?> Eintraege</div>
        <?php $path = '/errors'; $query = ['q' => $filters['q'], 'status' => $filters['status'], 'per_page' => $paginator->perPage]; require dirname(__DIR__) . '/partials/pagination.php'; ?>
    </div>
</div>
