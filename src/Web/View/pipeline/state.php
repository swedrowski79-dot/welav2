<?php use App\Web\Core\Html; ?>

<div class="panel-card p-4 mb-4">
    <div class="d-flex flex-column flex-lg-row gap-3 justify-content-between align-items-lg-end">
        <div>
            <h2 class="h5 mb-1">Produkt Export State</h2>
            <div class="text-secondary small">Persistenter Delta-Zustand mit Produkt-ID, letztem Hash und letzter Sichtung.</div>
        </div>
        <a class="btn btn-outline-secondary" href="/pipeline">Zurueck zu Pipeline & Queue</a>
    </div>
</div>

<div class="row g-4 mb-4">
    <div class="col-12 col-md-6">
        <div class="metric-card p-4 h-100">
            <div class="metric-icon mb-3"><i class="bi bi-fingerprint"></i></div>
            <div class="display-6 fw-semibold"><?= Html::escape($paginator->total) ?></div>
            <div class="text-secondary">State-Eintraege</div>
        </div>
    </div>
    <div class="col-12 col-md-6">
        <div class="metric-card p-4 h-100">
            <div class="metric-icon mb-3"><i class="bi bi-clock-history"></i></div>
            <div class="display-6 fw-semibold"><?= Html::escape($entries[0]['last_seen_at'] ?? '-') ?></div>
            <div class="text-secondary">Neueste Sichtung auf dieser Seite</div>
        </div>
    </div>
</div>

<div class="panel-card p-4 mb-4">
    <form class="row g-3" method="get" action="/pipeline/state">
        <div class="col-12 col-md-8">
            <label class="form-label">Suche</label>
            <input class="form-control" type="search" name="q" value="<?= Html::escape($search) ?>" placeholder="Produkt-ID oder Hash">
        </div>
        <div class="col-12 col-md-2">
            <label class="form-label">Pro Seite</label>
            <select class="form-select" name="per_page">
                <?php foreach ([10, 20, 50, 100] as $size): ?>
                    <option value="<?= $size ?>" <?= $paginator->perPage === $size ? 'selected' : '' ?>><?= $size ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="col-12 col-md-2 d-flex align-items-end gap-2">
            <button class="btn btn-primary w-100" type="submit">Filtern</button>
        </div>
    </form>
</div>

<div class="panel-card p-0">
    <div class="table-responsive">
        <table class="table table-hover mb-0">
            <thead><tr><th>Product ID</th><th>Hash</th><th>Last Seen</th></tr></thead>
            <tbody>
            <?php foreach ($entries as $entry): ?>
                <tr>
                    <td><span class="fw-semibold"><?= Html::escape($entry['product_id']) ?></span></td>
                    <td>
                        <?php $hash = (string) ($entry['last_exported_hash'] ?? ''); ?>
                        <?php if ($hash === ''): ?>
                            <span class="text-secondary">kein Hash</span>
                        <?php else: ?>
                            <code title="<?= Html::escape($hash) ?>"><?= Html::escape(strlen($hash) > 16 ? substr($hash, 0, 16) . '…' : $hash) ?></code>
                        <?php endif; ?>
                    </td>
                    <td><?= Html::escape($entry['last_seen_at'] ?? '-') ?></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <div class="d-flex justify-content-between align-items-center p-4">
        <div class="text-secondary small"><?= Html::escape($paginator->total) ?> Eintraege</div>
        <?php $path = '/pipeline/state'; $query = ['q' => $search, 'per_page' => $paginator->perPage]; require dirname(__DIR__) . '/partials/pagination.php'; ?>
    </div>
</div>
