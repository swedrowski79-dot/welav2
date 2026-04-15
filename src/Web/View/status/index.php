<?php

use App\Web\Core\Html;

$fieldGroups = [
    'AFS' => [
        'source' => 'afs',
        'fields' => [
        'AFS_DB_HOST' => 'Host',
        'AFS_DB_PORT' => 'Port',
        'AFS_DB_NAME' => 'Datenbank',
        'AFS_DB_USER' => 'Benutzer',
        'AFS_DB_PASS' => 'Passwort',
        ],
    ],
    'XT' => [
        'source' => 'xt',
        'fields' => [
        'XT_API_URL' => 'API-URL',
        'XT_API_KEY' => 'API-Key',
        ],
    ],
    'Stage' => [
        'source' => 'stage',
        'fields' => [
        'STAGE_DB_HOST' => 'Host',
        'STAGE_DB_PORT' => 'Port',
        'STAGE_DB_NAME' => 'Datenbank',
        'STAGE_DB_USER' => 'Benutzer',
        'STAGE_DB_PASS' => 'Passwort',
        ],
    ],
    'Extra SQLite' => [
        'source' => 'extra',
        'fields' => [
        'EXTRA_SQLITE_PATH' => 'Dateipfad',
        ],
    ],
];
?>

<?php if (!empty($saved)): ?>
    <div class="alert alert-success border-0 shadow-sm">Konfiguration gespeichert. Neue Werte wirken sofort in der Weboberflaeche.</div>
<?php endif; ?>

<?php if (isset($migrationsDone) && $migrationsDone !== null): ?>
    <div class="alert alert-success border-0 shadow-sm">Migrationen abgeschlossen. Ausgefuehrte Migrationen: <?= Html::escape($migrationsDone) ?>.</div>
<?php endif; ?>

<?php if (!empty($errorMessage)): ?>
    <div class="alert alert-danger border-0 shadow-sm"><?= Html::escape($errorMessage) ?></div>
<?php endif; ?>

<div class="row g-4 mb-4">
    <?php foreach ($sources as $source): ?>
        <div class="col-12 col-md-6 col-xl-3">
            <div class="panel-card p-4 h-100">
                <div class="d-flex justify-content-between align-items-start mb-3">
                    <h2 class="h5 mb-0"><?= Html::escape($source['label']) ?></h2>
                    <span class="badge <?= Html::badgeClass($source['status']) ?>"><?= Html::escape($source['status']) ?></span>
                </div>
                <div class="text-secondary small"><?= Html::escape($source['message']) ?></div>
            </div>
        </div>
    <?php endforeach; ?>
</div>

<div class="row g-4">
    <div class="col-12">
        <div class="panel-card p-4">
            <div class="d-flex flex-column flex-lg-row justify-content-between gap-3 align-items-lg-start">
                <div>
                    <h2 class="h5 mb-1">Migrationen & Systemzustand</h2>
                    <div class="text-secondary small">Migrationen werden hier ausgefuehrt, damit die Pipeline-Ansicht operativ und schema-resistent bleibt.</div>
                </div>
                <form method="post" action="/status/migrations">
                    <button class="btn btn-outline-secondary" type="submit">Run Migrations</button>
                </form>
            </div>
            <div class="row g-3 mt-1">
                <div class="col-12 col-md-4">
                    <div class="border rounded-4 p-3 bg-light-subtle h-100">
                        <div class="small text-secondary mb-1">Pending Migrationen</div>
                        <div class="fw-semibold"><?= Html::escape($migrationSummary['pending'] ?? 0) ?></div>
                    </div>
                </div>
                <div class="col-12 col-md-4">
                    <div class="border rounded-4 p-3 bg-light-subtle h-100">
                        <div class="small text-secondary mb-1">Migrationen gesamt</div>
                        <div class="fw-semibold"><?= Html::escape($migrationSummary['total'] ?? 0) ?></div>
                    </div>
                </div>
                <div class="col-12 col-md-4">
                    <div class="border rounded-4 p-3 bg-light-subtle h-100">
                        <div class="small text-secondary mb-1">Bereits angewendet</div>
                        <div class="fw-semibold"><?= Html::escape($migrationSummary['applied'] ?? 0) ?></div>
                    </div>
                </div>
                <div class="col-12">
                    <div class="border rounded-4 p-3 bg-light-subtle">
                        <div class="small text-secondary mb-1">Letztes Migrationsergebnis</div>
                        <?php if (empty($migrationLastResult)): ?>
                            <div class="fw-semibold">Noch kein Migrationsergebnis protokolliert.</div>
                        <?php else: ?>
                            <div class="d-flex flex-column flex-lg-row justify-content-between gap-2">
                                <div>
                                    <span class="badge <?= Html::badgeClass($migrationLastResult['status'] ?? 'info') ?>"><?= Html::escape($migrationLastResult['status'] ?? 'info') ?></span>
                                    <span class="fw-semibold ms-2"><?= Html::escape($migrationLastResult['message'] ?? '-') ?></span>
                                </div>
                                <div class="small text-secondary"><?= Html::escape($migrationLastResult['created_at'] ?? '-') ?></div>
                            </div>
                            <?php if (($migrationLastResult['status'] ?? '') === 'success'): ?>
                                <div class="small text-secondary mt-2">
                                    Ausgefuehrte Migrationen: <?= Html::escape($migrationLastResult['executed_count'] ?? 0) ?>
                                    <?php if (!empty($migrationLastResult['executed'])): ?>
                                        · <?= Html::escape(implode(', ', $migrationLastResult['executed'])) ?>
                                    <?php endif; ?>
                                </div>
                            <?php endif; ?>
                            <?php if (($migrationLastResult['status'] ?? '') === 'error' && !empty($migrationLastResult['error'])): ?>
                                <div class="small text-danger mt-2"><?= Html::escape($migrationLastResult['error']) ?></div>
                            <?php endif; ?>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <div class="col-12 col-xl-6">
        <div class="panel-card p-4 h-100">
            <div class="d-flex justify-content-between align-items-center mb-3">
                <h2 class="h5 mb-0">Verbindungsdaten bearbeiten</h2>
                <span class="text-secondary small">Speichert in `.env`</span>
            </div>
            <form method="post" action="/status/save">
                <div class="row g-4">
                    <?php foreach ($fieldGroups as $groupLabel => $group): ?>
                        <div class="col-12">
                            <div class="border rounded-4 p-3">
                                <h3 class="h6 mb-3"><?= Html::escape($groupLabel) ?></h3>
                                <div class="row g-3">
                                    <?php foreach ($group['fields'] as $field => $label): ?>
                                        <?php
                                        $value = $envValues[$field] ?? ($config[$group['source']]['connection'][match ($field) {
                                            'AFS_DB_HOST', 'XT_DB_HOST', 'STAGE_DB_HOST' => 'host',
                                            'AFS_DB_PORT', 'XT_DB_PORT', 'STAGE_DB_PORT' => 'port',
                                            'AFS_DB_NAME', 'XT_DB_NAME', 'STAGE_DB_NAME' => 'database',
                                            'AFS_DB_USER', 'XT_DB_USER', 'STAGE_DB_USER' => 'username',
                                            'AFS_DB_PASS', 'XT_DB_PASS', 'STAGE_DB_PASS' => 'password',
                                            'XT_API_URL' => 'url',
                                            'XT_API_KEY' => 'key',
                                            'EXTRA_SQLITE_PATH' => 'path',
                                        }] ?? '');
                                        $type = str_ends_with($field, '_PASS') || str_ends_with($field, '_KEY') ? 'password' : 'text';
                                        ?>
                                        <div class="col-12 col-md-6">
                                            <label class="form-label" for="<?= Html::escape($field) ?>"><?= Html::escape($label) ?></label>
                                            <input
                                                class="form-control"
                                                id="<?= Html::escape($field) ?>"
                                                name="<?= Html::escape($field) ?>"
                                                type="<?= Html::escape($type) ?>"
                                                value="<?= Html::escape((string) $value) ?>"
                                            >
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
                <div class="mt-4 d-flex justify-content-end">
                    <button class="btn btn-primary" type="submit">Konfiguration speichern</button>
                </div>
            </form>
        </div>
    </div>
    <div class="col-12 col-xl-6">
        <div class="panel-card p-0 h-100">
            <div class="card-header px-4 py-3"><h2 class="h5 mb-0">Freigegebene Admin-Tabellen</h2></div>
            <div class="table-responsive">
                <table class="table table-hover mb-0">
                    <thead><tr><th>Label</th><th>Tabelle</th><th>Anzahl</th></tr></thead>
                    <tbody>
                    <?php foreach ($stageCounts as $table): ?>
                        <tr>
                            <td><?= Html::escape($table['label']) ?></td>
                            <td><code><?= Html::escape($table['table']) ?></code></td>
                            <td><?= Html::escape($table['count']) ?></td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>
