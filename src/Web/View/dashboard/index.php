<?php

use App\Web\Core\Html;

$metricCards = [
    ['label' => 'Produkte in Stage', 'value' => $metrics['products'], 'icon' => 'box-seam'],
    ['label' => 'Kategorien in Stage', 'value' => $metrics['categories'], 'icon' => 'collection'],
    ['label' => 'Produkt-Uebersetzungen', 'value' => $metrics['translations'], 'icon' => 'translate'],
    ['label' => 'Attribut-Uebersetzungen', 'value' => $metrics['attribute_translations'], 'icon' => 'tags'],
    ['label' => 'Offene Fehler', 'value' => $metrics['open_errors'], 'icon' => 'exclamation-octagon'],
];
?>
<div class="row g-4 mb-4">
    <?php foreach ($metricCards as $metric): ?>
        <div class="col-12 col-md-6 col-xl">
            <div class="metric-card p-4 h-100">
                <div class="d-flex justify-content-between align-items-start mb-3">
                    <div class="metric-icon"><i class="bi bi-<?= Html::escape($metric['icon']) ?>"></i></div>
                    <span class="badge text-bg-light">Live</span>
                </div>
                <div class="display-6 fw-semibold"><?= Html::escape($metric['value']) ?></div>
                <div class="text-secondary"><?= Html::escape($metric['label']) ?></div>
            </div>
        </div>
    <?php endforeach; ?>
</div>

<div class="row g-4 mb-4">
    <div class="col-12 col-xl-8">
        <div class="panel-card p-0 h-100">
            <div class="card-header px-4 py-3 d-flex justify-content-between align-items-center">
                <h2 class="h5 mb-0">Letzte Sync-Laeufe</h2>
                <a class="btn btn-sm btn-outline-primary" href="/sync-runs">Alle anzeigen</a>
            </div>
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <thead><tr><th>ID</th><th>Typ</th><th>Status</th><th>Start</th><th>Ende</th><th>Mengen</th></tr></thead>
                    <tbody>
                    <?php foreach ($runs as $run): ?>
                        <tr>
                            <td><a href="/sync-runs/show?id=<?= Html::escape($run['id']) ?>">#<?= Html::escape($run['id']) ?></a></td>
                            <td><?= Html::escape($run['run_type']) ?></td>
                            <td><span class="badge <?= Html::badgeClass($run['status']) ?>"><?= Html::escape($run['status']) ?></span></td>
                            <td><?= Html::escape($run['started_at'] ?? '-') ?></td>
                            <td><?= Html::escape($run['ended_at'] ?? '-') ?></td>
                            <td><?= Html::escape(($run['imported_records'] ?? 0) . ' / ' . ($run['merged_records'] ?? 0)) ?></td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
    <div class="col-12 col-xl-4">
        <div class="panel-card p-4 mb-4">
            <h2 class="h5 mb-3">Letzter erfolgreicher Lauf</h2>
            <?php if ($lastSuccessfulRun): ?>
                <div class="fw-semibold mb-1"><?= Html::escape($lastSuccessfulRun['run_type']) ?></div>
                <div class="text-secondary mb-2"><?= Html::escape($lastSuccessfulRun['ended_at'] ?? '-') ?></div>
                <span class="badge <?= Html::badgeClass($lastSuccessfulRun['status']) ?>"><?= Html::escape($lastSuccessfulRun['status']) ?></span>
            <?php else: ?>
                <div class="text-secondary">Noch kein erfolgreicher Lauf vorhanden.</div>
            <?php endif; ?>
        </div>
        <div class="panel-card p-4">
            <h2 class="h5 mb-3">Letzter Fehler</h2>
            <?php if ($lastError): ?>
                <div class="fw-semibold mb-2"><?= Html::escape($lastError['message']) ?></div>
                <div class="text-secondary small mb-2"><?= Html::escape($lastError['created_at']) ?></div>
                <a class="btn btn-sm btn-outline-danger" href="/errors/show?id=<?= Html::escape($lastError['id']) ?>">Detail</a>
            <?php else: ?>
                <div class="text-secondary">Keine Fehler protokolliert.</div>
            <?php endif; ?>
        </div>
    </div>
</div>

<div class="panel-card p-4">
    <div class="d-flex justify-content-between align-items-center mb-3">
        <h2 class="h5 mb-0">Status der Datenquellen</h2>
        <a class="btn btn-sm btn-outline-secondary" href="/status">Mehr Details</a>
    </div>
    <div class="row g-3">
        <?php foreach ($sourceStatuses as $source): ?>
            <div class="col-12 col-md-4">
                <div class="border rounded-4 p-3 h-100 bg-light-subtle">
                    <div class="d-flex justify-content-between align-items-start mb-2">
                        <div class="fw-semibold"><?= Html::escape($source['label']) ?></div>
                        <span class="badge <?= Html::badgeClass($source['status']) ?>"><?= Html::escape($source['status']) ?></span>
                    </div>
                    <div class="small text-secondary"><?= Html::escape($source['message']) ?></div>
                </div>
            </div>
        <?php endforeach; ?>
    </div>
</div>
