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

<?php if (isset($migrationsDone) && $migrationsDone !== null): ?>
    <div class="alert alert-success border-0 shadow-sm">Migrationen abgeschlossen. Ausgefuehrte Migrationen: <?= Html::escape($migrationsDone) ?>.</div>
<?php endif; ?>

<?php if (!empty($resetDone)): ?>
    <div class="alert alert-warning border-0 shadow-sm">Reset-Aktion `<?= Html::escape($resetDone) ?>` wurde ausgefuehrt und in den Logs vermerkt.</div>
<?php endif; ?>

<?php if (!empty($errorMessage)): ?>
    <div class="alert alert-danger border-0 shadow-sm"><?= Html::escape($errorMessage) ?></div>
<?php endif; ?>

<?php if (!empty($schemaIssues)): ?>
    <div class="alert alert-danger border-0 shadow-sm mb-4">
        <div class="d-flex flex-column flex-lg-row justify-content-between gap-3 align-items-lg-start">
            <div>
                <div class="fw-semibold mb-2">Schema unvollstaendig</div>
                <div class="small mb-3">Es fehlen benoetigte Tabellen oder Spalten fuer Stage-, Delta- oder Export-Funktionen. Die Admin-Oberflaeche bleibt verfuegbar, aber betroffene Funktionen koennen fehlschlagen.</div>
                <ul class="mb-0 ps-3">
                    <?php foreach ($schemaIssues as $issue): ?>
                        <li><?= Html::escape($issue['message']) ?></li>
                    <?php endforeach; ?>
                </ul>
            </div>
            <div class="d-flex flex-column align-items-lg-end gap-2">
                <div class="small text-danger-emphasis">Bitte Schema aktualisieren, bevor Delta- oder Queue-Funktionen getestet werden.</div>
                <form method="post" action="/pipeline/migrations">
                    <button class="btn btn-danger" type="submit">Run Migrations</button>
                </form>
            </div>
        </div>
    </div>
<?php endif; ?>

<?php if (!empty($consistencyReport['checks'])): ?>
    <div class="alert alert-warning border-0 shadow-sm mb-4">
        <div class="d-flex flex-column flex-lg-row justify-content-between gap-3 align-items-lg-start">
            <div>
                <div class="fw-semibold mb-2">Stage-Konsistenzhinweise</div>
                <div class="small mb-3">
                    Es wurden <?= Html::escape($consistencyReport['summary']['issues'] ?? 0) ?> relevante Konsistenzprobleme
                    mit insgesamt <?= Html::escape($consistencyReport['summary']['affected_rows'] ?? 0) ?> betroffenen Datensaetzen gefunden.
                    Die Anwendung bleibt nutzbar, aber Delta- und Exportergebnisse koennen unvollstaendig sein.
                </div>
                <div class="d-grid gap-2">
                    <?php foreach ($consistencyReport['checks'] as $check): ?>
                        <div class="border rounded-4 bg-white p-3">
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
            <div class="small text-warning-emphasis">
                Empfohlener Ablauf: Import oder Merge erneut ausfuehren, anschliessend Stage-Daten und Queue pruefen.
            </div>
        </div>
    </div>
<?php endif; ?>

<div class="row g-4 mb-4">
    <div class="col-12 col-xl-7">
        <div class="panel-card p-4 h-100">
            <div class="d-flex justify-content-between align-items-start mb-3">
                <div>
                    <h2 class="h5 mb-1">Pipeline-Steuerung</h2>
                    <div class="text-secondary small">Die Aktionen sind entlang des Pipeline-Flusses gruppiert, damit Import, Verarbeitung, Delta und Gesamtlauf sofort erkennbar sind.</div>
                </div>
                <div class="d-flex gap-2">
                    <span class="badge text-bg-secondary align-self-center">Migrations pending: <?= Html::escape($migrationSummary['pending'] ?? 0) ?></span>
                    <form method="post" action="/pipeline/migrations">
                        <button class="btn btn-sm btn-outline-secondary" type="submit">Run Migrations</button>
                    </form>
                    <a class="btn btn-sm btn-outline-secondary" href="/pipeline/state">Produkt Export State</a>
                </div>
            </div>
            <?php foreach ([
                [
                    'title' => '1. Import (AFS → RAW)',
                    'description' => 'Quellen nach RAW laden. Fuer schnelle Tests koennen einzelne Importbereiche separat gestartet werden.',
                    'jobs' => [
                        ['job' => 'import_all', 'label' => 'Run Import', 'class' => 'btn-primary', 'help' => 'Laedt alle aktuellen Importquellen in die RAW-Tabellen.'],
                        ['job' => 'import_products', 'label' => 'Run Product Import', 'class' => 'btn-outline-primary', 'help' => 'Importiert nur Produktdaten und Artikel-Uebersetzungen.'],
                        ['job' => 'import_categories', 'label' => 'Run Category Import', 'class' => 'btn-outline-primary', 'help' => 'Importiert nur Kategorien und Kategorie-Uebersetzungen.'],
                    ],
                ],
                [
                    'title' => '2. Processing (Merge / Expand)',
                    'description' => 'RAW-Daten zu Stage-Daten verdichten und anschliessend erweiterte Attributzeilen erzeugen.',
                    'jobs' => [
                        ['job' => 'merge', 'label' => 'Run Merge', 'class' => 'btn-outline-primary', 'help' => 'Fuehrt RAW-Quellen zu den Stage-Grunddaten zusammen.'],
                        ['job' => 'expand', 'label' => 'Run Expand', 'class' => 'btn-outline-secondary', 'help' => 'Erzeugt expandierte Attributzeilen und anschliessende Folgeinformationen.'],
                    ],
                ],
                [
                    'title' => '3. Delta',
                    'description' => 'Aenderungen gegen den bestaetigten Export-Stand erkennen und Queue-Eintraege bzw. Worker-Schritte ausfuehren.',
                    'jobs' => [
                        ['job' => 'delta', 'label' => 'Run Delta', 'class' => 'btn-outline-dark', 'help' => 'Berechnet Produktaenderungen und fuellt die Export Queue.'],
                        ['job' => 'export_queue_worker', 'label' => 'Run Export Worker', 'class' => 'btn-outline-dark', 'help' => 'Bestaetigt Queue-Eintraege und aktualisiert den bestaetigten Export-Status.'],
                    ],
                ],
                [
                    'title' => '4. Full Pipeline',
                    'description' => 'Gesamtlauf fuer einen kompletten Durchgang von Import bis Verarbeitung.',
                    'jobs' => [
                        ['job' => 'full_pipeline', 'label' => 'Run Full Pipeline', 'class' => 'btn-dark', 'help' => 'Startet Import, Merge und Expand in der vorgesehenen Reihenfolge.'],
                    ],
                ],
            ] as $section): ?>
                <div class="border rounded-4 p-3 p-lg-4 mb-3 bg-light-subtle">
                    <div class="fw-semibold mb-1"><?= Html::escape($section['title']) ?></div>
                    <div class="small text-secondary mb-3"><?= Html::escape($section['description']) ?></div>
                    <div class="row g-3">
                        <?php foreach ($section['jobs'] as $job): ?>
                            <div class="col-12 col-md-6">
                                <div class="border rounded-4 p-3 h-100 bg-white">
                                    <form method="post" action="/pipeline/start" class="mb-2">
                                        <input type="hidden" name="job" value="<?= Html::escape($job['job']) ?>">
                                        <button class="btn <?= Html::escape($job['class']) ?> w-100" type="submit"><?= Html::escape($job['label']) ?></button>
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
        </div>
    </div>
</div>

<div class="panel-card p-4 mb-4">
    <div class="row g-3">
        <div class="col-12 col-lg-8">
            <h2 class="h5 mb-1">Arbeitsansicht fuer Queue und Pipeline</h2>
            <div class="text-secondary small">Oben stehen Steuerung und aktueller Lauf. Unten folgt die Queue-Ansicht fuer konkrete Exporteintraege, Retries und Fehler.</div>
        </div>
        <div class="col-12 col-lg-4">
            <div class="d-flex flex-wrap gap-2 justify-content-lg-end">
                <span class="badge <?= Html::badgeClass('pending') ?>">pending</span>
                <span class="badge <?= Html::badgeClass('running') ?>">processing</span>
                <span class="badge <?= Html::badgeClass('success') ?>">done</span>
                <span class="badge <?= Html::badgeClass('error') ?>">error</span>
            </div>
            <div class="small text-secondary text-lg-end mt-2">Statusfarben zeigen sofort, ob ein Eintrag wartet, verarbeitet wird, erfolgreich war oder blockiert ist.</div>
        </div>
    </div>
</div>

<div class="panel-card p-4 mb-4">
    <div class="d-flex justify-content-between align-items-start mb-3">
        <div>
            <h2 class="h5 mb-1">Ausfuehrungsstatus</h2>
            <div class="text-secondary small">Aktueller oder letzter Pipeline-Schritt fuer manuelle Tests und Laufkontrolle. Bei laufenden Jobs wird die Seite leichtgewichtig automatisch aktualisiert.</div>
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
