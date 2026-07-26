<?php use App\Web\Core\Html; ?>

<?php
$languageLabels = [
    'de' => 'DE',
    'en' => 'EN',
    'fr' => 'FR',
    'nl' => 'NL',
];

$metricCards = [
    [
        'entity_type' => 'product',
        'label' => 'Artikel',
        'total' => $summary['products_total'] ?? 0,
        'translated' => $summary['products_translated'] ?? 0,
        'missing' => $summary['products_missing'] ?? 0,
        'languages' => $summary['products_by_language'] ?? [],
    ],
    [
        'entity_type' => 'category',
        'label' => 'Kategorien',
        'total' => $summary['categories_total'] ?? 0,
        'translated' => $summary['categories_translated'] ?? 0,
        'missing' => $summary['categories_missing'] ?? 0,
        'languages' => $summary['categories_by_language'] ?? [],
    ],
    [
        'entity_type' => 'attribute',
        'label' => 'Attribute',
        'total' => $summary['attribute_rows_total'] ?? 0,
        'translated' => $summary['attribute_rows_translated'] ?? 0,
        'missing' => $summary['attribute_rows_missing'] ?? 0,
        'languages' => $summary['attribute_rows_by_language'] ?? [],
    ],
];

$detailQuery = [
    'entity_type' => $filters['entity_type'] ?? 'product',
    'coverage' => $filters['coverage'] ?? 'translated',
    'language_code' => $filters['language_code'] ?? '',
    'per_page' => $paginator->perPage,
];

$entityLabels = [
    'product' => 'Artikel',
    'category' => 'Kategorien',
    'attribute' => 'Attribute',
];

$defaultCoverageLabels = [
    'translated' => 'Mit Uebersetzung',
    'missing' => 'Ohne Uebersetzung',
    'orphan' => 'Verwaiste Uebersetzungen',
];

$currentEntity = $filters['entity_type'] ?? 'product';
$currentCoverage = $filters['coverage'] ?? 'translated';
$coverageLabels = $defaultCoverageLabels;
if ($currentEntity === 'attribute') {
    $coverageLabels['translated'] = 'Vollstaendig uebersetzt';
    $coverageLabels['missing'] = 'Mit fehlender Uebersetzung';
    $coverageLabels['orphan'] = 'Inaktive Begriffe';
}

$visibleFrom = $paginator->total > 0 ? (($paginator->page - 1) * $paginator->perPage) + 1 : 0;
$visibleTo = min($paginator->total, $paginator->page * $paginator->perPage);
$hasLargeResultSet = $paginator->total > $paginator->perPage;
$bulkDeleteEnabled = ($detailRows ?? []) !== [] && $currentCoverage !== 'missing';

$tableColumns = match ($currentEntity) {
    'category' => ['ID', 'Name', 'Sprache', 'Sprachen'],
    'attribute' => ['ID', 'Source', 'Key', 'DE', 'EN', 'FR', 'NL', 'Quelle', 'Aktiv', 'Sprachen'],
    default => ['ID', 'SKU', 'Name', 'Sprache', 'Sprachen'],
};

$attributeEditableFields = $dictionaryEditableColumns ?? [];

$renderRow = static function (array $row, string $entityType): array {
    return match ($entityType) {
        'category' => [
            $row['entity_id'] ?? '',
            $row['entity_name'] ?? '',
            $row['language_code'] ?? '',
            $row['languages'] ?? '',
        ],
        'attribute' => [
            $row['dictionary_id'] ?? $row['entity_id'] ?? '',
            $row['source_text'] ?? $row['entity_name'] ?? '',
            $row['entity_code'] ?? '',
            $row['de'] ?? '',
            $row['en'] ?? '',
            $row['fr'] ?? '',
            $row['nl'] ?? '',
            $row['source_directory'] ?? '',
            $row['is_active'] ?? '',
            $row['languages'] ?? '',
        ],
        default => [
            $row['entity_id'] ?? '',
            $row['entity_code'] ?? '',
            $row['entity_name'] ?? '',
            $row['language_code'] ?? '',
            $row['languages'] ?? '',
        ],
    };
};
?>

<div class="row g-4 mb-4">
    <?php foreach ($metricCards as $metric): ?>
        <div class="col-12 col-xl-4">
            <div class="metric-card p-4 h-100">
                <div class="d-flex justify-content-between align-items-start mb-3 gap-3">
                    <div>
                        <div class="small text-secondary mb-1"><?= Html::escape($metric['label']) ?></div>
                        <div class="h4 mb-0">
                            <a class="text-decoration-none" href="<?= Html::escape(Html::buildUrl('/translations', ['entity_type' => $metric['entity_type'], 'coverage' => 'translated'])) ?>#detail-table">
                                <?= Html::escape($metric['translated']) ?>
                            </a>
                            /
                            <?= Html::escape($metric['total']) ?>
                        </div>
                    </div>
                    <a class="badge text-bg-danger text-decoration-none" href="<?= Html::escape(Html::buildUrl('/translations', ['entity_type' => $metric['entity_type'], 'coverage' => 'missing'])) ?>#detail-table">
                        Ohne: <?= Html::escape($metric['missing']) ?>
                    </a>
                </div>
                <div class="row g-2">
                    <?php foreach ($languageLabels as $languageCode => $label): ?>
                        <div class="col-6 col-md-3">
                            <a
                                class="border rounded-4 p-3 bg-light-subtle h-100 d-block text-decoration-none text-reset"
                                href="<?= Html::escape(Html::buildUrl('/translations', ['entity_type' => $metric['entity_type'], 'coverage' => 'translated', 'language_code' => $languageCode])) ?>#detail-table"
                            >
                                <div class="small text-secondary"><?= Html::escape($label) ?></div>
                                <div class="fw-semibold"><?= Html::escape($metric['languages'][$languageCode] ?? 0) ?></div>
                            </a>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>
    <?php endforeach; ?>
</div>

<div class="row g-4 mb-4">
    <div class="col-12 col-xl-6">
        <div class="panel-card p-4 h-100 border border-danger-subtle">
            <h2 class="h5 mb-1">Fehlende Uebersetzungen</h2>
            <div class="small text-secondary mb-3">Direkter Ueberblick ueber fehlende Produkt-, Kategorie- und Attribut-Uebersetzungen.</div>
            <div class="row g-3">
                <?php foreach ($metricCards as $metric): ?>
                    <div class="col-12 col-md-4">
                        <a
                            class="border rounded-4 p-3 bg-light-subtle d-block text-decoration-none text-reset h-100"
                            href="<?= Html::escape(Html::buildUrl('/translations', ['entity_type' => $metric['entity_type'], 'coverage' => 'missing'])) ?>#detail-table"
                        >
                            <div class="small text-secondary"><?= Html::escape($metric['label']) ?> ohne Uebersetzung</div>
                            <div class="fw-semibold text-danger"><?= Html::escape($metric['missing']) ?></div>
                        </a>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
    </div>
    <div class="col-12 col-xl-6">
        <div class="panel-card p-4 h-100">
            <h2 class="h5 mb-1">Pruefungen</h2>
            <div class="small text-secondary mb-3">Unten gibt es jetzt eine filterbare Detailtabelle. Klicks auf die Boxen und Sprachcontainer oeffnen direkt die passende Sicht.</div>
            <div class="row g-3">
                <div class="col-12 col-md-6">
                    <a
                        class="border rounded-4 p-3 bg-light-subtle d-block text-decoration-none text-reset h-100"
                        href="/translations?entity_type=product&amp;coverage=orphan#detail-table"
                    >
                        <div class="small text-secondary">Produkt-Uebersetzungen ohne Produkt</div>
                        <div class="fw-semibold">Filter oeffnen</div>
                    </a>
                </div>
                <div class="col-12 col-md-6">
                    <a
                        class="border rounded-4 p-3 bg-light-subtle d-block text-decoration-none text-reset h-100"
                        href="/translations?entity_type=category&amp;coverage=orphan#detail-table"
                    >
                        <div class="small text-secondary">Kategorie-Uebersetzungen ohne Kategorie</div>
                        <div class="fw-semibold">Filter oeffnen</div>
                    </a>
                </div>
                <div class="col-12 col-md-6">
                    <a
                        class="border rounded-4 p-3 bg-light-subtle d-block text-decoration-none text-reset h-100"
                        href="/translations?entity_type=attribute&amp;coverage=orphan#detail-table"
                    >
                        <div class="small text-secondary">Inaktive Attribut-Begriffe</div>
                        <div class="fw-semibold">Filter oeffnen</div>
                    </a>
                </div>
            </div>
        </div>
    </div>
</div>

<div
    class="panel-card p-4 mb-4"
    id="detail-table"
    <?= $currentEntity === 'attribute' ? 'data-stage-browser data-update-endpoint="/attribute-dictionary/update"' : '' ?>
>
    <div class="d-flex flex-column flex-lg-row justify-content-between align-items-lg-start gap-3 mb-3">
        <div>
            <h2 class="h5 mb-1">Detailtabelle</h2>
            <div class="small text-secondary">
                Aktive Sicht:
                <?= Html::escape($entityLabels[$currentEntity] ?? $currentEntity) ?>
                ·
                <?= Html::escape($coverageLabels[$currentCoverage] ?? $currentCoverage) ?>
                <?php if (($filters['language_code'] ?? '') !== ''): ?>
                    · <?= Html::escape(strtoupper((string) $filters['language_code'])) ?>
                <?php endif; ?>
            </div>
            <?php if ($currentEntity === 'attribute'): ?>
                <div class="table-status mt-2" data-stage-status>`de` und `source_text` kommen aus AFS. Bearbeitbar sind `en`, `fr`, `nl`, `source_directory` und `is_active`.</div>
            <?php endif; ?>
        </div>
        <div class="text-secondary small">
            <?= Html::escape($paginator->total) ?> Eintraege
            <span class="mx-1">|</span>
            Seite <?= Html::escape($paginator->page) ?> von <?= Html::escape($paginator->totalPages()) ?>
        </div>
    </div>

    <?php if ($hasLargeResultSet): ?>
        <div class="alert alert-info border-0 rounded-4 mb-4" role="alert">
            Es gibt zu viele Treffer fuer eine Gesamttabelle. Geladen werden nur
            <?= Html::escape($visibleFrom) ?> bis <?= Html::escape($visibleTo) ?>
            von <?= Html::escape($paginator->total) ?> Eintraegen.
            Bitte Filter oder Pagination benutzen.
        </div>
    <?php endif; ?>

    <?php if (($bulkMessage ?? '') !== ''): ?>
        <div class="alert <?= ((int) ($bulkDeleted ?? 0)) > 0 ? 'alert-success' : 'alert-warning' ?> border-0 rounded-4 mb-4" role="alert">
            <?= Html::escape((string) $bulkMessage) ?>
        </div>
    <?php endif; ?>

    <form class="row g-3 mb-4" method="get" action="/translations">
        <div class="col-12 col-md-3">
            <label class="form-label">Bereich</label>
            <select class="form-select" name="entity_type">
                <?php foreach ($entityLabels as $value => $label): ?>
                    <option value="<?= Html::escape($value) ?>" <?= ($filters['entity_type'] ?? '') === $value ? 'selected' : '' ?>><?= Html::escape($label) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="col-12 col-md-3">
            <label class="form-label">Status</label>
            <select class="form-select" name="coverage">
                <?php foreach ($coverageLabels as $value => $label): ?>
                    <option value="<?= Html::escape($value) ?>" <?= ($filters['coverage'] ?? '') === $value ? 'selected' : '' ?>><?= Html::escape($label) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="col-12 col-md-2">
            <label class="form-label">Sprache</label>
            <select class="form-select" name="language_code">
                <option value="">Alle</option>
                <?php foreach ($languageLabels as $value => $label): ?>
                    <option value="<?= Html::escape($value) ?>" <?= ($filters['language_code'] ?? '') === $value ? 'selected' : '' ?>><?= Html::escape($label) ?></option>
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
        <div class="col-12 col-md-2 d-flex align-items-end gap-2">
            <button class="btn btn-primary w-100" type="submit">Filtern</button>
            <a class="btn btn-outline-secondary" href="/translations#detail-table">Reset</a>
        </div>
    </form>

    <form method="post" action="/translations/delete" data-translation-bulk-form>
        <input type="hidden" name="entity_type" value="<?= Html::escape($currentEntity) ?>">
        <input type="hidden" name="coverage" value="<?= Html::escape($currentCoverage) ?>">
        <input type="hidden" name="language_code" value="<?= Html::escape((string) ($filters['language_code'] ?? '')) ?>">
        <input type="hidden" name="page" value="<?= Html::escape((string) $paginator->page) ?>">
        <input type="hidden" name="per_page" value="<?= Html::escape((string) $paginator->perPage) ?>">

        <div class="d-flex flex-column flex-md-row justify-content-between align-items-md-center gap-3 mb-3">
            <div class="form-check">
                <input class="form-check-input" type="checkbox" value="1" id="translations-select-all" data-translation-select-all <?= $bulkDeleteEnabled ? '' : 'disabled' ?>>
                <label class="form-check-label small text-secondary" for="translations-select-all">
                    Alle sichtbaren markieren
                </label>
            </div>
            <div class="d-flex align-items-center gap-2">
                <div class="small text-secondary" data-translation-selection-count>0 markiert</div>
                <button class="btn btn-outline-danger" type="submit" data-translation-delete-button <?= $bulkDeleteEnabled ? 'disabled' : 'disabled' ?>>
                    Markierte loeschen
                </button>
            </div>
        </div>

        <div class="table-responsive">
            <table class="table table-hover mb-0">
                <thead>
                <tr>
                    <th style="width: 52px;">#</th>
                    <?php foreach ($tableColumns as $column): ?>
                        <th><?= Html::escape($column) ?></th>
                    <?php endforeach; ?>
                </tr>
                </thead>
                <tbody>
                <?php if (($detailRows ?? []) === []): ?>
                    <tr><td colspan="<?= count($tableColumns) + 1 ?>" class="text-secondary">Keine Eintraege fuer diesen Filter.</td></tr>
                <?php else: ?>
                    <?php foreach (($detailRows ?? []) as $row): ?>
                        <?php $selectionId = (string) ($currentEntity === 'attribute' ? ($row['dictionary_id'] ?? $row['entity_id'] ?? '') : ($row['entity_id'] ?? '')); ?>
                        <tr>
                            <td>
                                <input
                                    class="form-check-input"
                                    type="checkbox"
                                    name="selected_ids[]"
                                    value="<?= Html::escape($selectionId) ?>"
                                    data-translation-select-row
                                    <?= $bulkDeleteEnabled ? '' : 'disabled' ?>
                                >
                            </td>
                            <?php if ($currentEntity === 'attribute'): ?>
                                <?php foreach (['dictionary_id', 'source_text', 'entity_code', 'de', 'en', 'fr', 'nl', 'source_directory', 'is_active', 'languages'] as $field): ?>
                                    <?php
                                    $fieldMap = ['dictionary_id' => 'id', 'entity_code' => 'normalized_key'];
                                    $lookupField = $fieldMap[$field] ?? $field;
                                    $rawValue = $row[$field] ?? null;
                                    $displayValue = $rawValue === null || $rawValue === '' ? ($rawValue === null ? 'NULL' : '') : (string) $rawValue;
                                    $isEditable = in_array($lookupField, $attributeEditableFields, true);
                                    ?>
                                    <td
                                        class="truncate-cell <?= $isEditable ? 'editable-cell' : '' ?> <?= $rawValue === null ? 'text-secondary fst-italic' : '' ?>"
                                        <?= $isEditable ? 'data-editable="true"' : '' ?>
                                        data-table="attribute-dictionary"
                                        data-id="<?= Html::escape((string) ($row['dictionary_id'] ?? '')) ?>"
                                        data-field="<?= Html::escape($lookupField) ?>"
                                        data-value="<?= Html::escape($rawValue === null ? '' : (string) $rawValue) ?>"
                                        data-is-null="<?= $rawValue === null ? 'true' : 'false' ?>"
                                        title="<?= $isEditable ? 'Doppelklick zum Bearbeiten' : '' ?>"
                                    >
                                        <span class="cell-display"><?= Html::escape($displayValue) ?></span>
                                        <?php if ($isEditable): ?><span class="cell-hint">Doppelklick</span><?php endif; ?>
                                    </td>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <?php foreach ($renderRow($row, $currentEntity) as $value): ?>
                                    <td><?= Html::escape($value) ?></td>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
                </tbody>
            </table>
        </div>
    </form>

    <div class="d-flex justify-content-between align-items-center pt-4">
        <div class="text-secondary small">
            <?= Html::escape($paginator->total) ?> Eintraege
            <span class="mx-1">|</span>
            Anzeige <?= Html::escape($visibleFrom) ?> bis <?= Html::escape($visibleTo) ?>
            <span class="mx-1">|</span>
            Seite <?= Html::escape($paginator->page) ?> von <?= Html::escape($paginator->totalPages()) ?>
        </div>
        <?php $path = '/translations'; $query = $detailQuery; require dirname(__DIR__) . '/partials/pagination.php'; ?>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function () {
    var form = document.querySelector('[data-translation-bulk-form]');
    if (!form) {
        return;
    }

    var selectAll = form.querySelector('[data-translation-select-all]');
    var rowCheckboxes = Array.prototype.slice.call(form.querySelectorAll('[data-translation-select-row]'));
    var deleteButton = form.querySelector('[data-translation-delete-button]');
    var counter = form.querySelector('[data-translation-selection-count]');

    function updateSelectionState() {
        var enabledRows = rowCheckboxes.filter(function (checkbox) {
            return !checkbox.disabled;
        });
        var selectedRows = enabledRows.filter(function (checkbox) {
            return checkbox.checked;
        });

        if (counter) {
            counter.textContent = selectedRows.length + ' markiert';
        }

        if (deleteButton) {
            deleteButton.disabled = selectedRows.length === 0;
        }

        if (selectAll) {
            selectAll.checked = enabledRows.length > 0 && selectedRows.length === enabledRows.length;
            selectAll.indeterminate = selectedRows.length > 0 && selectedRows.length < enabledRows.length;
        }
    }

    if (selectAll) {
        selectAll.addEventListener('change', function () {
            rowCheckboxes.forEach(function (checkbox) {
                if (!checkbox.disabled) {
                    checkbox.checked = selectAll.checked;
                }
            });

            updateSelectionState();
        });
    }

    rowCheckboxes.forEach(function (checkbox) {
        checkbox.addEventListener('change', updateSelectionState);
    });

    form.addEventListener('submit', function (event) {
        var selectedCount = rowCheckboxes.filter(function (checkbox) {
            return checkbox.checked && !checkbox.disabled;
        }).length;

        if (selectedCount === 0) {
            event.preventDefault();
            return;
        }

        if (!window.confirm('Wirklich ' + selectedCount + ' sichtbare Eintraege loeschen?')) {
            event.preventDefault();
        }
    });

    updateSelectionState();
});
</script>
