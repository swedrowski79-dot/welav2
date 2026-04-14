<?php use App\Web\Core\Html; ?>

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
