<?php use App\Web\Core\Html; ?>

<div class="row g-4 mb-4">
    <?php foreach ($sources as $source): ?>
        <div class="col-12 col-md-4">
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
    <div class="col-12 col-xl-6">
        <div class="panel-card p-0 h-100">
            <div class="card-header px-4 py-3"><h2 class="h5 mb-0">Stage-Tabellen</h2></div>
            <div class="table-responsive">
                <table class="table table-hover mb-0">
                    <thead><tr><th>Tabelle</th><th>Anzahl</th></tr></thead>
                    <tbody>
                    <?php foreach ($stageCounts as $table => $count): ?>
                        <tr><td><?= Html::escape($table) ?></td><td><?= Html::escape($count) ?></td></tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
    <div class="col-12 col-xl-6">
        <div class="panel-card p-0 h-100">
            <div class="card-header px-4 py-3"><h2 class="h5 mb-0">Konfigurationswerte</h2></div>
            <div class="table-responsive">
                <table class="table table-sm mb-0">
                    <thead><tr><th>Quelle</th><th>Werte</th></tr></thead>
                    <tbody>
                    <?php foreach ($config as $sourceName => $sourceConfig): ?>
                        <tr>
                            <td><?= Html::escape($sourceName) ?></td>
                            <td><pre class="mb-0 text-wrap small"><?= Html::escape(json_encode($sourceConfig['connection'], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES)) ?></pre></td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>
