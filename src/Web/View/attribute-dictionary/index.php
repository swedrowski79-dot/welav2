<?php use App\Web\Core\Html; ?>

<?php
$query = [
    'q' => $search,
    'status' => $statusFilter,
    'per_page' => $paginator->perPage,
];
?>

<div class="row g-4 mb-4">
    <div class="col-12 col-md-4">
        <div class="metric-card p-4 h-100">
            <div class="small text-secondary mb-1">Gesamt</div>
            <div class="h4 mb-0"><?= Html::escape($metrics['total'] ?? 0) ?></div>
        </div>
    </div>
    <div class="col-12 col-md-4">
        <div class="metric-card p-4 h-100">
            <div class="small text-secondary mb-1">Aktiv</div>
            <div class="h4 mb-0"><?= Html::escape($metrics['active'] ?? 0) ?></div>
        </div>
    </div>
    <div class="col-12 col-md-4">
        <div class="metric-card p-4 h-100">
            <div class="small text-secondary mb-1">Inaktiv</div>
            <div class="h4 mb-0"><?= Html::escape($metrics['inactive'] ?? 0) ?></div>
        </div>
    </div>
</div>

<div class="panel-card p-4 mb-4">
    <form class="row g-3" method="get" action="/attribute-dictionary">
        <div class="col-12 col-md-5">
            <label class="form-label">Suche</label>
            <input class="form-control" type="search" name="q" value="<?= Html::escape($search) ?>" placeholder="Begriff, Key oder Uebersetzung">
        </div>
        <div class="col-12 col-md-3">
            <label class="form-label">Status</label>
            <select class="form-select" name="status">
                <option value="" <?= $statusFilter === '' ? 'selected' : '' ?>>Alle</option>
                <option value="1" <?= $statusFilter === '1' ? 'selected' : '' ?>>Aktiv</option>
                <option value="0" <?= $statusFilter === '0' ? 'selected' : '' ?>>Inaktiv</option>
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
        <div class="col-12 col-md-2 d-flex align-items-end gap-2">
            <button class="btn btn-primary w-100" type="submit">Filtern</button>
            <a class="btn btn-outline-secondary" href="/attribute-dictionary">Reset</a>
        </div>
    </form>
</div>

<div class="panel-card p-0" data-stage-browser data-update-endpoint="/attribute-dictionary/update">
    <div class="d-flex flex-column flex-md-row justify-content-between align-items-md-center gap-3 p-4 border-bottom">
        <div class="table-toolbar">
            <div class="text-secondary small">
                <?= Html::escape($paginator->total) ?> Begriffe
                <span class="mx-1">|</span>
                Seite <?= Html::escape($paginator->page) ?> von <?= Html::escape($paginator->totalPages()) ?>
            </div>
            <div class="table-status" data-stage-status>
                `de` und `source_text` kommen aus AFS. Bearbeitbar sind `en`, `fr`, `nl`, `source_directory` und `is_active`.
            </div>
        </div>
        <?php $path = '/attribute-dictionary'; require dirname(__DIR__) . '/partials/pagination.php'; ?>
    </div>
    <div class="table-responsive">
        <table class="table table-hover mb-0">
            <thead>
            <tr>
                <th>ID</th>
                <th>Source</th>
                <th>Key</th>
                <th>DE</th>
                <th>EN</th>
                <th>FR</th>
                <th>NL</th>
                <th>Quelle</th>
                <th>Aktiv</th>
            </tr>
            </thead>
            <tbody>
            <?php if (($rows ?? []) === []): ?>
                <tr><td colspan="9" class="text-secondary">Keine Eintraege fuer diesen Filter.</td></tr>
            <?php else: ?>
                <?php foreach ($rows as $row): ?>
                    <tr>
                        <?php foreach (['id', 'source_text', 'normalized_key', 'de', 'en', 'fr', 'nl', 'source_directory', 'is_active'] as $field): ?>
                            <?php
                            $rawValue = $row[$field] ?? null;
                            $displayValue = $rawValue === null ? 'NULL' : (string) $rawValue;
                            $isEditable = in_array($field, $editableColumns, true);
                            ?>
                            <td
                                class="truncate-cell <?= $isEditable ? 'editable-cell' : '' ?> <?= $rawValue === null ? 'text-secondary fst-italic' : '' ?>"
                                <?= $isEditable ? 'data-editable="true"' : '' ?>
                                data-table="attribute-dictionary"
                                data-id="<?= Html::escape((string) ($row['id'] ?? '')) ?>"
                                data-field="<?= Html::escape($field) ?>"
                                data-value="<?= Html::escape($rawValue === null ? '' : (string) $rawValue) ?>"
                                data-is-null="<?= $rawValue === null ? 'true' : 'false' ?>"
                                title="<?= $isEditable ? 'Doppelklick zum Bearbeiten' : '' ?>"
                            >
                                <span class="cell-display"><?= Html::escape($displayValue) ?></span>
                                <?php if ($isEditable): ?><span class="cell-hint">Doppelklick</span><?php endif; ?>
                            </td>
                        <?php endforeach; ?>
                    </tr>
                <?php endforeach; ?>
            <?php endif; ?>
            </tbody>
        </table>
    </div>
    <div class="d-flex flex-column flex-md-row justify-content-between align-items-md-center gap-3 p-4">
        <div class="text-secondary small">
            <?= Html::escape($paginator->total) ?> Begriffe
            <span class="mx-1">|</span>
            Seite <?= Html::escape($paginator->page) ?> von <?= Html::escape($paginator->totalPages()) ?>
        </div>
        <?php $path = '/attribute-dictionary'; require dirname(__DIR__) . '/partials/pagination.php'; ?>
    </div>
</div>
