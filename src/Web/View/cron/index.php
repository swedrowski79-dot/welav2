<?php

use App\Web\Core\Html;

$enabled = (bool) ($settings['enabled'] ?? false);
$intervalMinutes = (int) ($settings['interval_minutes'] ?? 5);
$lastStatus = (string) ($settings['last_status'] ?? 'never');
$lastStartedAt = trim((string) ($settings['last_started_at'] ?? ''));
$lastFinishedAt = trim((string) ($settings['last_finished_at'] ?? ''));
$nextRunAt = trim((string) ($settings['next_run_at'] ?? ''));
$lastMessage = trim((string) ($settings['last_message'] ?? ''));
$nextRunIsDue = $enabled && ($lastStartedAt === '' || ($nextRunAt !== '' && strtotime($nextRunAt . ' UTC') <= time()));
?>

<?php if (!empty($saved)): ?>
    <div class="alert alert-success border-0 shadow-sm">
        Cron-Zeitplan gespeichert. Die Aenderung wird ohne Container-Neustart bei der naechsten minuetlichen Pruefung verwendet.
    </div>
<?php endif; ?>

<?php if (!empty($errorMessage)): ?>
    <div class="alert alert-danger border-0 shadow-sm"><?= Html::escape($errorMessage) ?></div>
<?php endif; ?>

<div class="row g-4 mb-4">
    <div class="col-12 col-md-4">
        <div class="metric-card p-4 h-100">
            <div class="metric-icon mb-3"><i class="bi bi-power"></i></div>
            <div class="h3 fw-semibold mb-1"><?= $enabled ? 'Aktiv' : 'Inaktiv' ?></div>
            <div class="text-secondary">Automatischer Zeitplan</div>
        </div>
    </div>
    <div class="col-12 col-md-4">
        <div class="metric-card p-4 h-100">
            <div class="metric-icon mb-3"><i class="bi bi-arrow-repeat"></i></div>
            <div class="h3 fw-semibold mb-1"><?= Html::escape($intervalMinutes) ?> Minuten</div>
            <div class="text-secondary">Intervall zwischen Laufstarts</div>
        </div>
    </div>
    <div class="col-12 col-md-4">
        <div class="metric-card p-4 h-100">
            <div class="metric-icon mb-3"><i class="bi bi-activity"></i></div>
            <div class="h3 fw-semibold mb-1">
                <span class="badge <?= Html::badgeClass($lastStatus) ?>"><?= Html::escape($lastStatus) ?></span>
            </div>
            <div class="text-secondary">Letzter Cronstatus</div>
        </div>
    </div>
</div>

<div class="row g-4">
    <div class="col-12 col-xl-6">
        <div class="panel-card p-4 h-100">
            <h2 class="h5 mb-1">Zeitplan einstellen</h2>
            <div class="small text-secondary mb-4">
                Der Docker-Cron prueft jede Minute, ob das hier gespeicherte Intervall erreicht ist.
            </div>

            <form method="post" action="/cron/save">
                <div class="form-check form-switch mb-4">
                    <input
                        class="form-check-input"
                        id="cron-enabled"
                        name="enabled"
                        type="checkbox"
                        value="1"
                        <?= $enabled ? 'checked' : '' ?>
                    >
                    <label class="form-check-label fw-semibold" for="cron-enabled">
                        Automatische Pipeline-Laeufe aktivieren
                    </label>
                    <div class="form-text">Deaktivieren verhindert neue automatische Laeufe, beendet aber keinen bereits laufenden Prozess.</div>
                </div>

                <div class="mb-4">
                    <label class="form-label fw-semibold" for="interval-minutes">Intervall in Minuten</label>
                    <input
                        class="form-control"
                        id="interval-minutes"
                        name="interval_minutes"
                        type="number"
                        min="<?= Html::escape($minimumInterval) ?>"
                        max="<?= Html::escape($maximumInterval) ?>"
                        step="1"
                        value="<?= Html::escape($intervalMinutes) ?>"
                        required
                    >
                    <div class="form-text">
                        Erlaubt sind <?= Html::escape($minimumInterval) ?> bis <?= Html::escape($maximumInterval) ?> Minuten.
                    </div>
                </div>

                <button class="btn btn-primary" type="submit">Cron-Zeitplan speichern</button>
            </form>
        </div>
    </div>

    <div class="col-12 col-xl-6">
        <div class="panel-card p-4 h-100">
            <h2 class="h5 mb-4">Laufstatus</h2>
            <div class="subtle-list">
                <div class="border rounded-4 p-3 bg-light-subtle">
                    <div class="small text-secondary mb-1">Letzter Start</div>
                    <div class="fw-semibold"><?= Html::escape($lastStartedAt !== '' ? $lastStartedAt . ' UTC' : 'Noch kein automatischer Lauf') ?></div>
                </div>
                <div class="border rounded-4 p-3 bg-light-subtle">
                    <div class="small text-secondary mb-1">Letztes Ende</div>
                    <div class="fw-semibold"><?= Html::escape($lastFinishedAt !== '' ? $lastFinishedAt . ' UTC' : 'Noch kein abgeschlossener Lauf') ?></div>
                </div>
                <div class="border rounded-4 p-3 bg-light-subtle">
                    <div class="small text-secondary mb-1">Naechster Lauf</div>
                    <div class="fw-semibold">
                        <?php if (!$enabled): ?>
                            Zeitplan ist deaktiviert
                        <?php elseif ($nextRunIsDue): ?>
                            Bei der naechsten minuetlichen Pruefung
                        <?php else: ?>
                            <?= Html::escape($nextRunAt . ' UTC') ?>
                        <?php endif; ?>
                    </div>
                </div>
                <?php if ($lastMessage !== ''): ?>
                    <div class="border rounded-4 p-3 bg-light-subtle">
                        <div class="small text-secondary mb-1">Letzte Meldung</div>
                        <div class="fw-semibold"><?= Html::escape($lastMessage) ?></div>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<div class="panel-card p-4 mt-4">
    <h2 class="h5 mb-3">Automatische Schritte</h2>
    <div class="pipeline-flow">
        <?php foreach ($steps as $index => $step): ?>
            <span class="pipeline-flow-step">
                <span class="pipeline-flow-number"><?= Html::escape($index + 1) ?></span>
                <?= Html::escape($step) ?>
            </span>
        <?php endforeach; ?>
    </div>
    <div class="small text-secondary mt-3">
        Ein Lock verhindert parallele Cronlaeufe. Wenn ein vorheriger Lauf noch aktiv ist, wird die neue Pruefung uebersprungen.
    </div>
</div>
