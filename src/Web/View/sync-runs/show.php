<?php use App\Web\Core\Html; ?>

<?php if ($run === null): ?>
    <div class="panel-card p-4"><div class="text-secondary">Der Lauf konnte nicht geladen werden.</div></div>
<?php else: ?>
    <div class="row g-4 mb-4">
        <div class="col-12 col-lg-8">
            <div class="panel-card p-4 h-100">
                <div class="row g-3">
                    <div class="col-6 col-md-3"><div class="text-secondary small">Status</div><span class="badge <?= Html::badgeClass($run['status']) ?>"><?= Html::escape($run['status']) ?></span></div>
                    <div class="col-6 col-md-3"><div class="text-secondary small">Importiert</div><div class="fw-semibold"><?= Html::escape($run['imported_records'] ?? 0) ?></div></div>
                    <div class="col-6 col-md-3"><div class="text-secondary small">Gemergt</div><div class="fw-semibold"><?= Html::escape($run['merged_records'] ?? 0) ?></div></div>
                    <div class="col-6 col-md-3"><div class="text-secondary small">Fehler</div><div class="fw-semibold"><?= Html::escape($run['error_count'] ?? 0) ?></div></div>
                </div>
                <hr>
                <dl class="row mb-0">
                    <dt class="col-sm-3">Typ</dt><dd class="col-sm-9"><?= Html::escape($run['run_type']) ?></dd>
                    <dt class="col-sm-3">Start</dt><dd class="col-sm-9"><?= Html::escape($run['started_at'] ?? '-') ?></dd>
                    <dt class="col-sm-3">Ende</dt><dd class="col-sm-9"><?= Html::escape($run['ended_at'] ?? '-') ?></dd>
                    <dt class="col-sm-3">Nachricht</dt><dd class="col-sm-9"><?= Html::escape($run['message'] ?? '-') ?></dd>
                </dl>
            </div>
        </div>
        <div class="col-12 col-lg-4">
            <div class="panel-card p-4 h-100">
                <h2 class="h6 mb-3">Kontext</h2>
                <pre class="small mb-0 text-wrap"><?= Html::escape($run['context_json'] ?? '{}') ?></pre>
            </div>
        </div>
    </div>
    <div class="row g-4">
        <div class="col-12 col-xl-6">
            <div class="panel-card p-0 h-100">
                <div class="card-header px-4 py-3"><h2 class="h5 mb-0">Logs</h2></div>
                <div class="table-responsive">
                    <table class="table table-sm mb-0">
                        <thead><tr><th>Zeit</th><th>Level</th><th>Nachricht</th></tr></thead>
                        <tbody>
                        <?php foreach ($logs as $log): ?>
                            <tr>
                                <td><?= Html::escape($log['created_at']) ?></td>
                                <td><span class="badge <?= Html::badgeClass($log['level']) ?>"><?= Html::escape($log['level']) ?></span></td>
                                <td><?= Html::escape($log['message']) ?></td>
                            </tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
        <div class="col-12 col-xl-6">
            <div class="panel-card p-0 h-100">
                <div class="card-header px-4 py-3"><h2 class="h5 mb-0">Fehler</h2></div>
                <div class="table-responsive">
                    <table class="table table-sm mb-0">
                        <thead><tr><th>Zeit</th><th>Status</th><th>Meldung</th></tr></thead>
                        <tbody>
                        <?php foreach ($errors as $error): ?>
                            <tr>
                                <td><?= Html::escape($error['created_at']) ?></td>
                                <td><span class="badge <?= Html::badgeClass($error['status']) ?>"><?= Html::escape($error['status']) ?></span></td>
                                <td><a href="/errors/show?id=<?= Html::escape($error['id']) ?>"><?= Html::escape($error['message']) ?></a></td>
                            </tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
<?php endif; ?>
