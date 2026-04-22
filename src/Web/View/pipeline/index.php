<?php use App\Web\Core\Html; ?>

<?php if (!empty($refreshSeconds)): ?>
    <script>
        window.setTimeout(function () {
            window.location.reload();
        }, <?= (int) $refreshSeconds * 1000 ?>);
    </script>
<?php endif; ?>

<?php if (!empty($started)): ?>
    <div class="alert alert-success border-0 shadow-sm">Pipeline-Job wurde gestartet und laeuft im Hintergrund.</div>
<?php endif; ?>

<?php if (!empty($resetDone)): ?>
    <div class="alert alert-warning border-0 shadow-sm">Reset-Aktion `<?= Html::escape($resetDone) ?>` wurde ausgefuehrt und in den Logs vermerkt.</div>
<?php endif; ?>

<?php if (!empty($errorMessage)): ?>
    <div class="alert alert-danger border-0 shadow-sm"><?= Html::escape($errorMessage) ?></div>
<?php endif; ?>

<?php if (!empty($schemaIssues)): ?>
    <details class="panel-card details-card mb-4" open>
        <summary>
            <div>
                <div class="fw-semibold">Schema unvollstaendig</div>
                <div class="small text-secondary mt-1"><?= Html::escape(count($schemaIssues)) ?> Hinweis(e) zu Tabellen oder Spalten</div>
            </div>
        </summary>
        <div class="p-4">
            <div class="small mb-3">Es fehlen benoetigte Tabellen oder Spalten fuer Stage-, Delta- oder Export-Funktionen.</div>
            <ul class="mb-3 ps-3">
                <?php foreach ($schemaIssues as $issue): ?>
                    <li><?= Html::escape($issue['message']) ?></li>
                <?php endforeach; ?>
            </ul>
            <a class="btn btn-danger" href="/status">Zu Konfiguration/Status</a>
        </div>
    </details>
<?php endif; ?>

<?php if (!empty($consistencyReport['checks'])): ?>
    <details class="panel-card details-card mb-4">
        <summary>
            <div>
                <div class="fw-semibold">Stage-Konsistenzhinweise</div>
                <div class="small text-secondary mt-1">
                    <?= Html::escape($consistencyReport['summary']['issues'] ?? 0) ?> Problemtyp(en),
                    <?= Html::escape($consistencyReport['summary']['affected_rows'] ?? 0) ?> betroffene Datensaetze
                </div>
            </div>
        </summary>
        <div class="p-4">
            <div class="small text-secondary mb-3">Empfohlener Ablauf: Import oder Merge erneut ausfuehren, anschliessend Stage-Daten und Queue pruefen.</div>
            <div class="d-grid gap-2">
                <?php foreach ($consistencyReport['checks'] as $check): ?>
                    <div class="border rounded-4 bg-light-subtle p-3">
                        <div class="d-flex flex-column flex-lg-row justify-content-between gap-2">
                            <div class="fw-semibold"><?= Html::escape($check['name']) ?></div>
                            <span class="badge <?= Html::badgeClass($check['severity']) ?>"><?= Html::escape($check['count']) ?> betroffen</span>
                        </div>
                        <div class="small text-secondary mt-2"><?= Html::escape($check['description']) ?></div>
                        <?php if (!empty($check['examples'])): ?>
                            <div class="small mt-2">
                                <span class="text-secondary">Beispiele:</span>
                                <?= Html::escape(implode(', ', $check['examples'])) ?>
                            </div>
                        <?php endif; ?>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
    </details>
<?php endif; ?>

<div class="row g-4 mb-4">
    <div class="col-12 col-xl-7">
        <div class="panel-card p-4 h-100">
            <div class="d-flex justify-content-between align-items-start mb-3">
                <div>
                    <h2 class="h5 mb-1">Pipeline-Steuerung</h2>
                    <div class="text-secondary small">Die Aktionen sind entlang des aktuellen Pipeline-Flusses gruppiert: Import, Merge, XT-Mirror, Expand inklusive Delta und anschliessender Export-Worker.</div>
                </div>
                <div class="d-flex gap-2">
                    <a class="btn btn-sm btn-outline-secondary" href="/pipeline/state">Delta-State</a>
                </div>
            </div>
            <?php foreach ($pipelineSections as $section): ?>
                <div class="border rounded-4 p-3 p-lg-4 mb-3 bg-light-subtle">
                    <div class="fw-semibold mb-1"><?= Html::escape($section['title']) ?></div>
                    <div class="small text-secondary mb-3"><?= Html::escape($section['description']) ?></div>
                    <div class="row g-3">
                        <?php foreach ($section['jobs'] as $job): ?>
                            <div class="col-12 col-md-6">
                                <div class="border rounded-4 p-3 h-100 bg-white">
                                    <form method="post" action="/pipeline/start" class="mb-2">
                                        <input type="hidden" name="job" value="<?= Html::escape($job['name']) ?>">
                                        <?php if (in_array(($job['name'] ?? ''), ['export_queue_worker', 'full_pipeline'], true)): ?>
                                            <label class="form-label small text-secondary" for="batch_size_<?= Html::escape($job['name']) ?>">Export-Worker Batchgroesse</label>
                                            <input
                                                class="form-control mb-2"
                                                id="batch_size_<?= Html::escape($job['name']) ?>"
                                                name="batch_size"
                                                type="number"
                                                min="1"
                                                step="1"
                                                value="<?= Html::escape($exportWorkerBatchSize ?? '') ?>"
                                                placeholder="persistenter Wert"
                                            >
                                        <?php endif; ?>
                                        <button class="btn <?= Html::escape($job['button_class']) ?> w-100" type="submit"><?= Html::escape($job['label']) ?></button>
                                    </form>
                                    <div class="small text-secondary"><?= Html::escape($job['help']) ?></div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    </div>
    <div class="col-12 col-md-6 col-xl-3">
        <div class="metric-card p-4 h-100">
            <div class="metric-icon mb-3"><i class="bi bi-hourglass-split"></i></div>
            <div class="display-6 fw-semibold"><?= Html::escape($queueSummary['pending']) ?></div>
            <div class="text-secondary">Queue pending</div>
            <div class="small text-secondary mt-2">Processing: <?= Html::escape($queueSummary['processing'] ?? 0) ?> · Done: <?= Html::escape($queueSummary['done']) ?> · Error: <?= Html::escape($queueSummary['error']) ?></div>
        </div>
    </div>
    <div class="col-12 col-md-6 col-xl-2">
        <div class="metric-card p-4 h-100">
            <div class="metric-icon mb-3"><i class="bi bi-fingerprint"></i></div>
            <div class="display-6 fw-semibold"><?= Html::escape($stateSummary['entries']) ?></div>
            <div class="text-secondary">State-Eintraege</div>
            <?php if (!empty($stateSummary['entities']) && is_array($stateSummary['entities'])): ?>
                <div class="small text-secondary mt-2">
                    <?php
                    $parts = [];
                    foreach ($stateSummary['entities'] as $entityType => $count) {
                        $parts[] = $entityType . ': ' . $count;
                    }
                    echo Html::escape(implode(' · ', $parts));
                    ?>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<div class="row g-4 mb-4">
    <div class="col-12 col-xl-6">
        <div class="panel-card p-4 h-100">
            <h2 class="h5 mb-1">Delta-Sichtbarkeit</h2>
            <div class="small text-secondary mb-3">Zeigt, ob Delta neue Queue-Eintraege geschrieben hat oder ob keine Aenderungen bzw. nur bereits aktive Queue-Eintraege vorlagen.</div>
            <?php if (!empty($latestDeltaVisibility)): ?>
                <?php $delta = $latestDeltaVisibility['context'] ?? []; ?>
                <div class="fw-semibold mb-2"><?= Html::escape($latestDeltaVisibility['reason'] ?? '-') ?></div>
                <div class="small text-secondary mb-3">
                    Letzter Lauf: <?= Html::escape($latestDeltaVisibility['run']['run_type'] ?? '-') ?>
                    · Status <?= Html::escape($latestDeltaVisibility['run']['status'] ?? '-') ?>
                    · Ende <?= Html::escape($latestDeltaVisibility['run']['ended_at'] ?? '-') ?>
                </div>
                <div class="row g-3">
                    <div class="col-6 col-md-3">
                        <div class="border rounded-4 p-3 bg-light-subtle">
                            <div class="small text-secondary">Neu geschrieben</div>
                            <div class="fw-semibold"><?= Html::escape($delta['queue_created'] ?? $delta['changed'] ?? 0) ?></div>
                        </div>
                    </div>
                    <div class="col-6 col-md-3">
                        <div class="border rounded-4 p-3 bg-light-subtle">
                            <div class="small text-secondary">Unveraendert</div>
                            <div class="fw-semibold"><?= Html::escape($delta['unchanged'] ?? 0) ?></div>
                        </div>
                    </div>
                    <div class="col-6 col-md-3">
                        <div class="border rounded-4 p-3 bg-light-subtle">
                            <div class="small text-secondary">Dedupliziert</div>
                            <div class="fw-semibold"><?= Html::escape($delta['deduplicated'] ?? 0) ?></div>
                        </div>
                    </div>
                    <div class="col-6 col-md-3">
                        <div class="border rounded-4 p-3 bg-light-subtle">
                            <div class="small text-secondary">Fehler</div>
                            <div class="fw-semibold"><?= Html::escape($delta['errors'] ?? 0) ?></div>
                        </div>
                    </div>
                    <div class="col-6 col-md-3">
                        <div class="border rounded-4 p-3 bg-light-subtle">
                            <div class="small text-secondary">Pending vorher</div>
                            <div class="fw-semibold"><?= Html::escape($delta['pending_before'] ?? 0) ?></div>
                        </div>
                    </div>
                    <div class="col-6 col-md-3">
                        <div class="border rounded-4 p-3 bg-light-subtle">
                            <div class="small text-secondary">Pending nachher</div>
                            <div class="fw-semibold"><?= Html::escape($delta['pending_after'] ?? 0) ?></div>
                        </div>
                    </div>
                </div>
            <?php else: ?>
                <div class="text-secondary small">Noch kein Delta-Lauf vorhanden.</div>
            <?php endif; ?>
        </div>
    </div>
    <div class="col-12 col-xl-6">
        <div class="panel-card p-4 h-100">
            <h2 class="h5 mb-1">Worker-Sichtbarkeit</h2>
            <div class="small text-secondary mb-3">Zeigt, ob claimbare pending Queue-Eintraege vorhanden waren und warum der Worker gegebenenfalls `0` Eintraege verarbeitet hat.</div>
            <?php if (!empty($latestWorkerVisibility)): ?>
                <?php $worker = $latestWorkerVisibility['context'] ?? []; ?>
                <div class="fw-semibold mb-2"><?= Html::escape($latestWorkerVisibility['reason'] ?? '-') ?></div>
                <div class="small text-secondary mb-3">
                    Letzter Lauf: <?= Html::escape($latestWorkerVisibility['run']['run_type'] ?? '-') ?>
                    · Status <?= Html::escape($latestWorkerVisibility['run']['status'] ?? '-') ?>
                    · Ende <?= Html::escape($latestWorkerVisibility['run']['ended_at'] ?? '-') ?>
                </div>
                <div class="row g-3">
                    <div class="col-6 col-md-3">
                        <div class="border rounded-4 p-3 bg-light-subtle">
                            <div class="small text-secondary">Claimed</div>
                            <div class="fw-semibold"><?= Html::escape($worker['claimed'] ?? 0) ?></div>
                        </div>
                    </div>
                    <div class="col-6 col-md-3">
                        <div class="border rounded-4 p-3 bg-light-subtle">
                            <div class="small text-secondary">Processed</div>
                            <div class="fw-semibold"><?= Html::escape($worker['processed'] ?? 0) ?></div>
                        </div>
                    </div>
                    <div class="col-6 col-md-3">
                        <div class="border rounded-4 p-3 bg-light-subtle">
                            <div class="small text-secondary">Done</div>
                            <div class="fw-semibold"><?= Html::escape($worker['done'] ?? 0) ?></div>
                        </div>
                    </div>
                    <div class="col-6 col-md-3">
                        <div class="border rounded-4 p-3 bg-light-subtle">
                            <div class="small text-secondary">Retry/Error</div>
                            <div class="fw-semibold"><?= Html::escape(($worker['retried'] ?? 0) . ' / ' . ($worker['error'] ?? 0)) ?></div>
                        </div>
                    </div>
                </div>
            <?php else: ?>
                <div class="text-secondary small">Noch kein Export-Worker-Lauf vorhanden.</div>
            <?php endif; ?>
        </div>
    </div>
</div>

<?php if (!empty($queueSummaryByEntity)): ?>
    <div class="panel-card p-4 mb-4">
        <h2 class="h5 mb-1">Queue nach Entity-Typ</h2>
        <div class="small text-secondary mb-3">Schneller Ueberblick, ob Delta fuer die konfigurierten Entity-Typen pending Eintraege erzeugt hat und wie weit der Worker je Entity-Typ gekommen ist.</div>
        <div class="table-responsive">
            <table class="table table-hover mb-0">
                <thead><tr><th>Entity</th><th>Pending</th><th>Processing</th><th>Done</th><th>Error</th></tr></thead>
                <tbody>
                <?php foreach ($queueSummaryByEntity as $entityType => $summary): ?>
                    <tr>
                        <td class="fw-semibold"><?= Html::escape($entityType) ?></td>
                        <td><?= Html::escape($summary['pending'] ?? 0) ?></td>
                        <td><?= Html::escape($summary['processing'] ?? 0) ?></td>
                        <td><?= Html::escape($summary['done'] ?? 0) ?></td>
                        <td><?= Html::escape($summary['error'] ?? 0) ?></td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
<?php endif; ?>

<details class="panel-card details-card mb-4" <?= (($queueIssueSummary['total'] ?? 0) > 0 || !empty($recentExportWorkerIssues) || !empty($recentQueueIssues)) ? 'open' : '' ?>>
    <summary>
        <div>
            <div class="fw-semibold">XT-/Exportprobleme</div>
            <div class="small text-secondary mt-1">Queue-Probleme <?= Html::escape($queueIssueSummary['total'] ?? 0) ?> · Worker-Warnungen <?= Html::escape(count($recentExportWorkerIssues ?? [])) ?></div>
        </div>
    </summary>
    <div class="p-4">
        <div class="d-flex justify-content-end mb-3">
            <a class="btn btn-sm btn-outline-danger" href="/errors">Zur Fehleransicht</a>
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
                    <div class="fw-semibold mb-2">Letzte Worker-Probleme</div>
                    <?php if (empty($recentExportWorkerIssues)): ?>
                        <div class="small text-secondary">Keine aktuellen Export-Worker-Warnungen oder -Fehler.</div>
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
                    <div class="fw-semibold mb-2">Letzte Queue-Eintraege mit Fehler</div>
                    <?php if (empty($recentQueueIssues)): ?>
                        <div class="small text-secondary">Keine Queue-Eintraege mit `last_error` vorhanden.</div>
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
</details>

<div class="panel-card p-4 mb-4">
    <div class="d-flex justify-content-between align-items-start mb-3">
        <div>
            <h2 class="h5 mb-1">Ausfuehrungsstatus</h2>
            <div class="text-secondary small">Aktueller oder letzter Pipeline-Schritt fuer manuelle Tests und Laufkontrolle. Expand steht dabei fuer Expand inklusive Delta; die Full Pipeline endet erst nach dem Export Worker.</div>
        </div>
        <?php $statusLabel = $runningRun ? 'running' : 'idle'; ?>
        <span class="badge <?= Html::badgeClass($runningRun ? 'running' : 'info') ?>"><?= Html::escape($statusLabel) ?></span>
    </div>
    <div class="border rounded-4 p-3 p-lg-4 bg-light-subtle mb-3">
        <div class="d-flex flex-column flex-lg-row justify-content-between gap-3">
            <div>
                <div class="small text-secondary mb-1">Fortschrittsbild</div>
                <div class="fw-semibold mb-1"><?= Html::escape($progressSummary['headline'] ?? 'Kein Lauf vorhanden.') ?></div>
                <div class="text-secondary small"><?= Html::escape($progressSummary['detail'] ?? 'Kein Fortschrittslog verfuegbar.') ?></div>
            </div>
            <div class="d-flex flex-column align-items-lg-end">
                <div class="small text-secondary">Laufdauer</div>
                <div class="fw-semibold"><?= Html::escape($progressSummary['duration_label'] ?? '00:00:00') ?></div>
                <div class="small text-secondary mt-1">Letztes Update: <?= Html::escape($progressSummary['last_update'] ?? '-') ?></div>
                <?php if (!empty($progressSummary['refresh_hint'])): ?>
                    <div class="small text-primary mt-2"><?= Html::escape($progressSummary['refresh_hint']) ?></div>
                <?php endif; ?>
            </div>
        </div>
    </div>
    <div class="row g-3">
        <div class="col-12 col-md-6 col-xl-3">
            <div class="border rounded-4 p-3 h-100 bg-light-subtle">
                <div class="small text-secondary mb-1">Aktiver / letzter Schritt</div>
                <div class="fw-semibold"><?= Html::escape(($runningRun['run_type'] ?? null) ?: ($latestRun['run_type'] ?? 'Kein Lauf')) ?></div>
            </div>
        </div>
        <div class="col-12 col-md-6 col-xl-3">
            <div class="border rounded-4 p-3 h-100 bg-light-subtle">
                <div class="small text-secondary mb-1">Letzter Start</div>
                <div class="fw-semibold"><?= Html::escape(($runningRun['started_at'] ?? null) ?: ($latestRun['started_at'] ?? '-')) ?></div>
            </div>
        </div>
        <div class="col-12 col-md-6 col-xl-3">
            <div class="border rounded-4 p-3 h-100 bg-light-subtle">
                <div class="small text-secondary mb-1">Letztes Ende</div>
                <div class="fw-semibold"><?= Html::escape($latestRun['ended_at'] ?? '-') ?></div>
            </div>
        </div>
        <div class="col-12 col-md-6 col-xl-3">
            <div class="border rounded-4 p-3 h-100 bg-light-subtle">
                <div class="small text-secondary mb-1">Run-ID / Logzeilen</div>
                <div class="fw-semibold">#<?= Html::escape($latestRun['id'] ?? '-') ?></div>
                <div class="small text-secondary mt-1"><?= Html::escape($runLogCount ?? 0) ?> Logzeilen im Lauf</div>
            </div>
        </div>
        <div class="col-12 col-md-6 col-xl-3">
            <div class="border rounded-4 p-3 h-100 bg-light-subtle">
                <div class="small text-secondary mb-1">Letzter Fehlerstatus</div>
                <?php if ($latestError): ?>
                    <div class="fw-semibold text-danger"><?= Html::escape($latestError['message']) ?></div>
                    <div class="small text-secondary mt-1"><?= Html::escape($latestError['created_at'] ?? '-') ?></div>
                <?php else: ?>
                    <div class="fw-semibold text-success">Kein Fehler protokolliert</div>
                <?php endif; ?>
            </div>
        </div>
        <div class="col-12">
            <div class="border rounded-4 p-3 bg-light-subtle">
                <div class="small text-secondary mb-1">Aktueller / letzter Fortschritt</div>
                <?php if (!empty($activeLog)): ?>
                    <div class="fw-semibold"><?= Html::escape($activeLog['message'] ?? '-') ?></div>
                    <div class="small text-secondary mt-1">
                        <?= Html::escape($activeLog['created_at'] ?? '-') ?>
                        <?php if (!empty($activeLog['run_type'])): ?>
                            · <?= Html::escape($activeLog['run_type']) ?>
                        <?php endif; ?>
                        <?php if (!empty($activeLog['level'])): ?>
                            · <?= Html::escape($activeLog['level']) ?>
                        <?php endif; ?>
                    </div>
                <?php else: ?>
                    <div class="fw-semibold text-secondary">Kein Fortschrittslog verfuegbar</div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<div class="panel-card p-0 mb-4">
    <div class="card-header px-4 py-3 d-flex justify-content-between align-items-center">
        <div>
            <h2 class="h5 mb-0">Fortschritts-Timeline</h2>
            <div class="small text-secondary mt-1">Die letzten 5 Statuswechsel oder Logmeldungen des aktiven bzw. letzten Laufs.</div>
        </div>
        <?php if (!empty($runningRun)): ?>
            <span class="badge <?= Html::badgeClass('running') ?>">Auto-Refresh aktiv</span>
        <?php endif; ?>
    </div>
    <div class="p-4">
        <?php if (empty($progressLogs)): ?>
            <div class="text-secondary small">Keine Fortschrittsdaten verfuegbar.</div>
        <?php else: ?>
            <div class="d-grid gap-3">
                <?php foreach ($progressLogs as $index => $log): ?>
                    <div class="border rounded-4 p-3 bg-light-subtle">
                        <div class="d-flex flex-column flex-lg-row justify-content-between gap-2">
                            <div class="fw-semibold"><?= Html::escape($log['message'] ?? '') ?></div>
                            <div class="small text-secondary"><?= Html::escape($log['created_at'] ?? '-') ?></div>
                        </div>
                        <div class="small text-secondary mt-2">
                            Schritt <?= Html::escape($index + 1) ?>
                            <?php if (!empty($log['run_type'])): ?>
                                · <?= Html::escape($log['run_type']) ?>
                            <?php endif; ?>
                            <?php if (!empty($log['level'])): ?>
                                · <?= Html::escape($log['level']) ?>
                            <?php endif; ?>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>
</div>

<div class="panel-card p-0 mb-4">
    <div class="card-header px-4 py-3 d-flex justify-content-between align-items-center">
        <div>
            <h2 class="h5 mb-0">Letzte Log-Eintraege</h2>
            <div class="small text-secondary mt-1">Die letzten 10 Logzeilen des aktiven oder zuletzt ausgefuehrten Pipeline-Laufs.</div>
        </div>
        <?php if (!empty($latestRun['id'])): ?>
            <a class="btn btn-sm btn-outline-secondary" href="/sync-runs/show?id=<?= Html::escape($latestRun['id']) ?>">Laufdetail</a>
        <?php endif; ?>
    </div>
    <div class="table-responsive">
        <table class="table table-hover mb-0">
            <thead><tr><th>Zeit</th><th>Level</th><th>Schritt</th><th>Nachricht</th></tr></thead>
            <tbody>
            <?php if ($recentLogs === []): ?>
                <tr><td colspan="4" class="text-secondary px-4 py-3">Keine Log-Eintraege vorhanden.</td></tr>
            <?php else: ?>
                <?php foreach ($recentLogs as $log): ?>
                    <tr>
                        <td><?= Html::escape($log['created_at'] ?? '-') ?></td>
                        <td><span class="badge <?= Html::badgeClass($log['level'] ?? 'info') ?>"><?= Html::escape($log['level'] ?? 'info') ?></span></td>
                        <td><?= Html::escape($log['run_type'] ?? '-') ?></td>
                        <td class="truncate-cell"><?= Html::escape($log['message'] ?? '') ?></td>
                    </tr>
                <?php endforeach; ?>
            <?php endif; ?>
            </tbody>
        </table>
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
                ['action' => 'mirror', 'label' => 'Reset Mirror', 'warning' => 'Alle xt_mirror_* Tabellen werden geleert. Fortfahren?'],
                ['action' => 'delta_state', 'label' => 'Reset Delta State', 'warning' => 'Der komplette Delta-State wird geloescht. Fortfahren?'],
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
                <?php foreach ($entityTypes as $entityType): ?>
                    <option value="<?= Html::escape($entityType) ?>" <?= $filters['entity_type'] === $entityType ? 'selected' : '' ?>><?= Html::escape($entityType) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="col-12 col-md-3">
            <label class="form-label">Status</label>
            <select class="form-select" name="status">
                <option value="">Alle</option>
                <?php foreach (['pending', 'processing', 'done', 'error'] as $status): ?>
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
        <div>
            <h2 class="h5 mb-0">Export Queue</h2>
            <div class="small text-secondary mt-1">Jede Zeile zeigt den Exportstatus, Retry-Zustand und die zugehoerigen Nutzdaten kompakt an.</div>
        </div>
        <span class="text-secondary small"><?= Html::escape($paginator->total) ?> Eintraege</span>
    </div>
    <div class="table-responsive">
        <table class="table table-hover mb-0">
            <thead><tr><th>ID</th><th>Entity</th><th>Action</th><th>Status</th><th>Retry</th><th>Zeitfenster</th><th>Details</th></tr></thead>
            <tbody>
            <?php foreach ($queueEntries as $entry): ?>
                <tr>
                    <td>#<?= Html::escape($entry['id']) ?></td>
                    <td>
                        <div class="fw-semibold"><?= Html::escape($entry['entity_type']) ?></div>
                        <div class="small text-secondary">ID <?= Html::escape($entry['entity_id']) ?></div>
                    </td>
                    <td>
                        <span class="badge text-bg-light border"><?= Html::escape($entry['action']) ?></span>
                    </td>
                    <td>
                        <span class="badge <?= Html::badgeClass($entry['status']) ?>"><?= Html::escape($entry['status']) ?></span>
                        <?php if (($entry['status'] ?? '') === 'error' && !empty($entry['last_error'])): ?>
                            <div class="small text-danger mt-1 truncate-cell"><?= Html::escape($entry['last_error']) ?></div>
                        <?php endif; ?>
                    </td>
                    <td>
                        <div class="fw-semibold"><?= Html::escape($entry['attempt_count'] ?? 0) ?></div>
                        <div class="small text-secondary">Versuche</div>
                    </td>
                    <td>
                        <div class="small"><span class="text-secondary">Created:</span> <?= Html::escape($entry['created_at'] ?? '-') ?></div>
                        <div class="small"><span class="text-secondary">Available:</span> <?= Html::escape($entry['available_at'] ?? '-') ?></div>
                        <div class="small"><span class="text-secondary">Claimed:</span> <?= Html::escape($entry['claimed_at'] ?? '-') ?></div>
                        <div class="small"><span class="text-secondary">Processed:</span> <?= Html::escape($entry['processed_at'] ?? '-') ?></div>
                    </td>
                    <td>
                        <details>
                            <summary class="small text-primary">Payload und Fehler anzeigen</summary>
                            <pre class="mb-0 mt-2 small text-wrap"><?= Html::escape((string) ($entry['payload'] ?? '')) ?></pre>
                            <?php if (!empty($entry['last_error'])): ?>
                                <div class="small text-danger mt-2"><?= Html::escape($entry['last_error']) ?></div>
                            <?php endif; ?>
                        </details>
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <div class="d-flex justify-content-between align-items-center p-4">
        <div class="text-secondary small">Filter helfen vor allem bei `processing`- und `error`-Eintraegen mit mehreren Retry-Versuchen.</div>
        <?php $path = '/pipeline'; $query = ['entity_type' => $filters['entity_type'], 'status' => $filters['status'], 'action' => $filters['action'], 'per_page' => $paginator->perPage]; require dirname(__DIR__) . '/partials/pagination.php'; ?>
    </div>
</div>
