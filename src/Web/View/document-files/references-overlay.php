<?php use App\Web\Core\Html; ?>

<?php if ($document === null): ?>
    <div class="p-4">
        <div class="alert alert-danger border-0 shadow-sm mb-0">Dokumenteintrag wurde nicht gefunden.</div>
    </div>
    <?php return; ?>
<?php endif; ?>

<?php $query = ['id' => $document['id'] ?? 0, 'per_page' => $paginator->perPage]; ?>

<div class="d-flex flex-column gap-3">
    <div class="d-flex flex-column flex-lg-row justify-content-between align-items-lg-start gap-3">
        <div>
            <div class="small text-secondary mb-1">Dokumenttitel</div>
            <div class="fw-semibold"><?= Html::escape($document['title'] ?? '') ?></div>
            <div class="small text-secondary mt-2">
                <?= Html::escape($paginator->total) ?> Referenzen
                <span class="mx-1">|</span>
                Seite <?= Html::escape($paginator->page) ?> von <?= Html::escape($paginator->totalPages()) ?>
            </div>
        </div>
        <form class="row g-2 align-items-end" method="get" action="/document-files/references" data-document-references-form>
            <input type="hidden" name="id" value="<?= Html::escape($document['id'] ?? 0) ?>">
            <div class="col-auto">
                <label class="form-label small mb-1">Pro Seite</label>
                <select class="form-select form-select-sm" name="per_page">
                    <?php foreach ([10, 20, 50, 100] as $size): ?>
                        <option value="<?= $size ?>" <?= $paginator->perPage === $size ? 'selected' : '' ?>><?= $size ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-auto">
                <button class="btn btn-sm btn-outline-primary" type="submit">Aktualisieren</button>
            </div>
        </form>
    </div>

    <div class="d-flex justify-content-end">
        <?php $path = '/document-files/references'; require dirname(__DIR__) . '/partials/pagination.php'; ?>
    </div>

    <div class="table-responsive border rounded-4">
        <table class="table table-hover mb-0">
            <thead>
            <tr>
                <th>Artikelnummer</th>
                <th>AFS Artikel-ID</th>
                <th>Produktname</th>
                <th>Dateiname</th>
                <th>Typ</th>
                <th>Sort</th>
            </tr>
            </thead>
            <tbody>
            <?php if ($referenceRows === []): ?>
                <tr>
                    <td colspan="6" class="text-center text-secondary py-4">Keine Referenzen vorhanden.</td>
                </tr>
            <?php endif; ?>
            <?php foreach ($referenceRows as $row): ?>
                <tr>
                    <td><?= Html::escape($row['sku'] ?? '') ?></td>
                    <td><?= Html::escape($row['afs_artikel_id'] ?? '') ?></td>
                    <td class="truncate-cell" title="<?= Html::escape($row['product_name'] ?? '') ?>"><?= Html::escape($row['product_name'] ?? '') ?></td>
                    <td class="truncate-cell" title="<?= Html::escape($row['file_name'] ?? '') ?>"><?= Html::escape($row['file_name'] ?? '') ?></td>
                    <td><?= Html::escape($row['document_type'] ?? '') ?></td>
                    <td><?= Html::escape($row['sort_order'] ?? '') ?></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>

    <div class="d-flex flex-column flex-md-row justify-content-between align-items-md-center gap-3">
        <div class="text-secondary small">
            <?= Html::escape($paginator->total) ?> Referenzen
            <span class="mx-1">|</span>
            Seite <?= Html::escape($paginator->page) ?> von <?= Html::escape($paginator->totalPages()) ?>
        </div>
        <?php $path = '/document-files/references'; require dirname(__DIR__) . '/partials/pagination.php'; ?>
    </div>
</div>
