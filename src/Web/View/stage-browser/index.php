<?php use App\Web\Core\Html; ?>

<div class="panel-card p-4 mb-4">
    <form class="row g-3" method="get" action="/stage-browser">
        <div class="col-12 col-md-4">
            <label class="form-label">Tabelle</label>
            <select class="form-select" name="table" onchange="this.form.submit()">
                <?php foreach ($tables as $tableName => $label): ?>
                    <option value="<?= Html::escape($tableName) ?>" <?= $currentTable === $tableName ? 'selected' : '' ?>><?= Html::escape($label) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="col-12 col-md-4">
            <label class="form-label">Suche</label>
            <input class="form-control" type="search" name="q" value="<?= Html::escape($search) ?>" placeholder="Volltext ueber die Zeile">
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
            <button class="btn btn-primary w-100" type="submit">Anwenden</button>
        </div>
    </form>
</div>

<div class="panel-card p-0">
    <div class="table-responsive">
        <table class="table table-hover mb-0">
            <thead>
            <tr>
                <?php foreach ($columns as $column): ?>
                    <th><?= Html::escape($column['Field']) ?></th>
                <?php endforeach; ?>
                <th></th>
            </tr>
            </thead>
            <tbody>
            <?php foreach ($rows as $row): ?>
                <tr>
                    <?php foreach ($columns as $column): $field = $column['Field']; ?>
                        <td class="truncate-cell"><?= Html::escape($row[$field] ?? '') ?></td>
                    <?php endforeach; ?>
                    <td><a class="btn btn-sm btn-outline-primary" href="/stage-browser/show?table=<?= Html::escape($currentTable) ?>&id=<?= Html::escape($row[$primaryKey]) ?>">Detail</a></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <div class="d-flex justify-content-between align-items-center p-4">
        <div class="text-secondary small"><?= Html::escape($paginator->total) ?> Zeilen</div>
        <?php $path = '/stage-browser'; $query = ['table' => $currentTable, 'q' => $search, 'per_page' => $paginator->perPage]; require dirname(__DIR__) . '/partials/pagination.php'; ?>
    </div>
</div>
