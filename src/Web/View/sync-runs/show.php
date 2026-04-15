<?php use App\Web\Core\Html; ?>

<?php
$runContext = json_decode((string) ($run['context_json'] ?? '{}'), true);
if (!is_array($runContext)) {
    $runContext = [];
}

$expandContext = [];
if (isset($runContext['expand']) && is_array($runContext['expand'])) {
    $expandContext = $runContext['expand'];
}

$deltaContext = [];
if (isset($runContext['delta']) && is_array($runContext['delta'])) {
    $deltaContext = $runContext['delta'];
} elseif (isset($runContext['processed']) || isset($runContext['entities'])) {
    $deltaContext = $runContext;
}

$formatDuration = static function (mixed $value): string {
    if ($value === null || $value === '') {
        return '-';
    }

    return number_format((float) $value, 3, '.', '') . ' s';
};
?>

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
                    <div class="col-6 col-md-3"><div class="text-secondary small">Gesamtdauer</div><div class="fw-semibold"><?= Html::escape(gmdate('H:i:s', max(0, (int) ($run['duration_seconds'] ?? 0)))) ?></div></div>
                    <?php if (($run['run_type'] ?? '') === 'export_queue_worker'): ?>
                        <div class="col-6 col-md-3"><div class="text-secondary small">Retries</div><div class="fw-semibold"><?= Html::escape($runContext['retried'] ?? 0) ?></div></div>
                        <div class="col-6 col-md-3"><div class="text-secondary small">Terminale Fehler</div><div class="fw-semibold"><?= Html::escape($runContext['permanent_error'] ?? 0) ?></div></div>
                    <?php endif; ?>
                    <?php if (($run['run_type'] ?? '') === 'expand'): ?>
                        <div class="col-6 col-md-3"><div class="text-secondary small">Expand Laufzeit</div><div class="fw-semibold"><?= Html::escape($formatDuration($expandContext['duration_seconds'] ?? null)) ?></div></div>
                        <div class="col-6 col-md-3"><div class="text-secondary small">Delta Laufzeit</div><div class="fw-semibold"><?= Html::escape($formatDuration($deltaContext['duration_seconds'] ?? null)) ?></div></div>
                    <?php elseif (($run['run_type'] ?? '') === 'delta'): ?>
                        <div class="col-6 col-md-3"><div class="text-secondary small">Delta Laufzeit</div><div class="fw-semibold"><?= Html::escape($formatDuration($deltaContext['duration_seconds'] ?? null)) ?></div></div>
                    <?php endif; ?>
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
        <?php if (($run['run_type'] ?? '') === 'expand' && !empty($expandContext['definitions']) && is_array($expandContext['definitions'])): ?>
            <div class="col-12">
                <div class="panel-card p-0">
                    <div class="card-header px-4 py-3">
                        <h2 class="h5 mb-1">Expand-Diagnostik</h2>
                        <div class="small text-secondary">
                            <?= Html::escape((string) ($expandContext['source_rows'] ?? 0)) ?> gelesene Quellzeilen
                            · <?= Html::escape((string) ($expandContext['written_rows'] ?? 0)) ?> geschriebene Zielzeilen
                            · <?= Html::escape((string) ($expandContext['insert_batches'] ?? 0)) ?> Insert-Batches
                        </div>
                    </div>
                    <div class="table-responsive">
                        <table class="table table-sm mb-0">
                            <thead>
                            <tr>
                                <th>Definition</th>
                                <th>Modus</th>
                                <th>Quelle</th>
                                <th>Ziel</th>
                                <th>Quellzeilen</th>
                                <th>Zielzeilen</th>
                                <th>Batches</th>
                                <th>Laufzeit</th>
                            </tr>
                            </thead>
                            <tbody>
                            <?php foreach ($expandContext['definitions'] as $definitionName => $definitionStats): ?>
                                <tr>
                                    <td><?= Html::escape((string) $definitionName) ?></td>
                                    <td><?= Html::escape($definitionStats['mode'] ?? '-') ?></td>
                                    <td><?= Html::escape($definitionStats['source_table'] ?? '-') ?></td>
                                    <td><?= Html::escape($definitionStats['target_table'] ?? '-') ?></td>
                                    <td><?= Html::escape($definitionStats['source_rows'] ?? 0) ?></td>
                                    <td><?= Html::escape($definitionStats['written_rows'] ?? 0) ?></td>
                                    <td><?= Html::escape($definitionStats['insert_batches'] ?? 0) ?></td>
                                    <td><?= Html::escape($formatDuration($definitionStats['duration_seconds'] ?? null)) ?></td>
                                </tr>
                            <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        <?php endif; ?>

        <?php if (!empty($deltaContext)): ?>
            <div class="col-12">
                <div class="panel-card p-0">
                    <div class="card-header px-4 py-3">
                        <h2 class="h5 mb-1">Delta-Diagnostik</h2>
                        <div class="small text-secondary">
                            Laufzeit <?= Html::escape($formatDuration($deltaContext['duration_seconds'] ?? null)) ?>
                            · <?= Html::escape((string) ($deltaContext['processed'] ?? 0)) ?> verarbeitet
                            · <?= Html::escape((string) ($deltaContext['changed'] ?? 0)) ?> geaendert
                            · <?= Html::escape((string) ($deltaContext['errors'] ?? 0)) ?> Fehler
                        </div>
                    </div>
                    <?php if (!empty($deltaContext['entities']) && is_array($deltaContext['entities'])): ?>
                        <div class="table-responsive">
                            <table class="table table-sm mb-0">
                                <thead>
                                <tr>
                                    <th>Entity</th>
                                    <th>Verarbeitet</th>
                                    <th>Geaendert</th>
                                    <th>Insert</th>
                                    <th>Update</th>
                                    <th>Removed</th>
                                    <th>Deduplicated</th>
                                    <th>Fehler</th>
                                </tr>
                                </thead>
                                <tbody>
                                <?php foreach ($deltaContext['entities'] as $entityType => $entityStats): ?>
                                    <tr>
                                        <td><?= Html::escape((string) $entityType) ?></td>
                                        <td><?= Html::escape($entityStats['processed'] ?? 0) ?></td>
                                        <td><?= Html::escape($entityStats['changed'] ?? 0) ?></td>
                                        <td><?= Html::escape($entityStats['insert'] ?? 0) ?></td>
                                        <td><?= Html::escape($entityStats['update'] ?? 0) ?></td>
                                        <td><?= Html::escape($entityStats['removed'] ?? 0) ?></td>
                                        <td><?= Html::escape($entityStats['deduplicated'] ?? 0) ?></td>
                                        <td><?= Html::escape($entityStats['errors'] ?? 0) ?></td>
                                    </tr>
                                <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        <?php endif; ?>

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
