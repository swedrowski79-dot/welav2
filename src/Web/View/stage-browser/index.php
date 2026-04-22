<?php use App\Web\Core\Html; ?>

<?php
$tableQuery = ['table' => $currentTable, 'per_page' => $paginator->perPage];
if ($search !== '') {
    $tableQuery['q'] = $search;
}
?>

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
        <div class="col-12 col-md-5">
            <label class="form-label">Suche in <?= Html::escape($tables[$currentTable] ?? $currentTable) ?></label>
            <input class="form-control" type="search" name="q" value="<?= Html::escape($search) ?>" placeholder="Volltext ueber alle Spalten der aktiven Tabelle">
        </div>
        <div class="col-12 col-md-2">
            <label class="form-label">Pro Seite</label>
            <select class="form-select" name="per_page">
                <?php foreach ([10, 20, 50, 100] as $size): ?>
                    <option value="<?= $size ?>" <?= $paginator->perPage === $size ? 'selected' : '' ?>><?= $size ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="col-12 col-md-1 d-flex align-items-end">
            <button class="btn btn-primary w-100" type="submit">OK</button>
        </div>
    </form>
</div>

<div class="panel-card p-0" data-stage-browser>
    <div class="d-flex flex-column flex-md-row justify-content-between align-items-md-center gap-3 p-4 border-bottom">
        <div class="table-toolbar">
            <div class="text-secondary small">
                <?= Html::escape($paginator->total) ?> Zeilen
                <span class="mx-1">|</span>
                Seite <?= Html::escape($paginator->page) ?> von <?= Html::escape($paginator->totalPages()) ?>
            </div>
            <div class="table-status" data-stage-status>Doppelklick auf ein Feld, um den Wert direkt in der Tabelle zu bearbeiten.</div>
        </div>
        <?php $path = '/stage-browser'; $query = $tableQuery; require dirname(__DIR__) . '/partials/pagination.php'; ?>
    </div>
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
                    <?php foreach ($columns as $column): $field = $column['Field']; $rawValue = $row[$field] ?? null; $displayValue = $rawValue === null ? 'NULL' : (string) $rawValue; $isEditable = in_array($field, $editableColumns, true); ?>
                        <td
                            class="truncate-cell <?= $isEditable ? 'editable-cell' : '' ?> <?= $rawValue === null ? 'text-secondary fst-italic' : '' ?>"
                            <?= $isEditable ? 'data-editable="true"' : '' ?>
                            data-table="<?= Html::escape($currentTable) ?>"
                            data-id="<?= Html::escape((string) $row[$primaryKey]) ?>"
                            data-field="<?= Html::escape($field) ?>"
                            data-value="<?= Html::escape($rawValue === null ? '' : (string) $rawValue) ?>"
                            data-is-null="<?= $rawValue === null ? 'true' : 'false' ?>"
                            title="<?= $isEditable ? 'Doppelklick zum Bearbeiten' : '' ?>"
                        >
                            <span class="cell-display"><?= Html::escape($displayValue) ?></span>
                            <?php if ($isEditable): ?><span class="cell-hint">Doppelklick</span><?php endif; ?>
                        </td>
                    <?php endforeach; ?>
                    <td><a class="btn btn-sm btn-outline-primary" href="/stage-browser/show?table=<?= Html::escape($currentTable) ?>&id=<?= Html::escape($row[$primaryKey]) ?>">Detail</a></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <div class="d-flex flex-column flex-md-row justify-content-between align-items-md-center gap-3 p-4">
        <div class="text-secondary small">
            <?= Html::escape($paginator->total) ?> Zeilen
            <span class="mx-1">|</span>
            Seite <?= Html::escape($paginator->page) ?> von <?= Html::escape($paginator->totalPages()) ?>
        </div>
        <?php $path = '/stage-browser'; $query = $tableQuery; require dirname(__DIR__) . '/partials/pagination.php'; ?>
    </div>
</div>
