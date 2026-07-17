<?php use App\Web\Core\Html; ?>

<?php
$tableQuery = ['per_page' => $paginator->perPage];
if (($activeFilter ?? 'all') !== 'all') {
    $tableQuery['filter'] = $activeFilter;
}
?>

<?php if (!empty($saved)): ?>
    <div class="alert alert-success border-0 shadow-sm">Bildpfad gespeichert.</div>
<?php endif; ?>

<?php if (!empty($scanDone)): ?>
    <div class="alert alert-success border-0 shadow-sm">Bildpfad gescannt und `images_file` mit Produkt- und Kategoriebildern aktualisiert.</div>
<?php endif; ?>

<?php if (!empty($uploadDone)): ?>
    <div class="alert alert-success border-0 shadow-sm">Alle markierten Bild-Dateien wurden zur API hochgeladen.</div>
<?php endif; ?>

<?php if (!empty($resetDone)): ?>
    <div class="alert alert-warning border-0 shadow-sm">`images_file` wurde geleert.</div>
<?php endif; ?>

<?php if (!empty($errorMessage)): ?>
    <div class="alert alert-danger border-0 shadow-sm"><?= Html::escape($errorMessage) ?></div>
<?php endif; ?>

<div class="panel-card p-4 mb-4">
    <div class="d-flex flex-column flex-xl-row justify-content-between gap-4">
        <div class="flex-grow-1">
            <h2 class="h5 mb-1">Bildlauf</h2>
            <div class="small text-secondary mb-3">Scan und Upload laufen separat von der Pipeline. Erfasst werden Produkt- und Kategoriebilder. Die Pfade werden zentral unter <a href="/status">Status</a> gepflegt.</div>
            <div class="subtle-list">
                <div>
                    <div class="small text-secondary mb-1">Lokaler Bildpfad</div>
                    <div class="path-chip"><?= Html::escape($imagePath !== '' ? $imagePath : 'Nicht gesetzt') ?></div>
                </div>
                <div>
                    <div class="small text-secondary mb-1">Shop-Zielpfad</div>
                    <div class="path-chip"><?= Html::escape($shopTargetPath !== '' ? $shopTargetPath : 'Standard der XT-API (media/files)') ?></div>
                </div>
            </div>
        </div>
        <div class="d-flex flex-column gap-2 align-items-stretch align-items-xl-end">
            <a class="btn btn-outline-secondary" href="/status">Pfade konfigurieren</a>
            <form method="post" action="/image-files/scan">
                <button class="btn btn-outline-primary w-100" type="submit">Bildpfad scannen</button>
            </form>
            <form method="post" action="/image-files/upload">
                <button class="btn btn-primary w-100" type="submit">Offene Bilder hochladen</button>
            </form>
            <form method="post" action="/image-files/reset" onsubmit="return confirm('Wollen Sie wirklich die Tabelle images_file leeren? Diese Aktion kann nicht rueckgaengig gemacht werden.');">
                <button class="btn btn-outline-danger w-100" type="submit">Tabelle leeren</button>
            </form>
        </div>
    </div>
    <?php if (($imagePath ?? '') === ''): ?>
        <div class="alert alert-warning border-0 shadow-sm mt-4 mb-0">Es ist noch kein lokaler Bildpfad gesetzt.</div>
    <?php endif; ?>
</div>

<div class="row g-4 mb-4">
    <div class="col-12 col-md-4">
        <div class="metric-card p-4 h-100">
            <div class="metric-icon mb-3"><i class="bi bi-image"></i></div>
            <div class="display-6 fw-semibold"><?= Html::escape($summary['total'] ?? 0) ?></div>
            <div class="text-secondary">Dateien</div>
        </div>
    </div>
    <div class="col-12 col-md-4">
        <div class="metric-card p-4 h-100">
            <div class="metric-icon mb-3"><i class="bi bi-cloud-arrow-up"></i></div>
            <div class="display-6 fw-semibold"><?= Html::escape($summary['pending_upload'] ?? 0) ?></div>
            <div class="text-secondary">Upload offen</div>
        </div>
    </div>
    <div class="col-12 col-md-4">
        <div class="metric-card p-4 h-100">
            <div class="metric-icon mb-3"><i class="bi bi-exclamation-triangle"></i></div>
            <div class="display-6 fw-semibold"><?= Html::escape($summary['missing_path'] ?? 0) ?></div>
            <div class="text-secondary">Nicht gefunden</div>
            <div class="small text-secondary mt-2">Bereits hochgeladen: <?= Html::escape($summary['uploaded'] ?? 0) ?></div>
        </div>
    </div>
</div>

<div class="panel-card p-0">
    <div class="card-header px-4 py-3">
        <div class="d-flex flex-column gap-3">
            <div class="d-flex flex-column flex-md-row justify-content-between align-items-md-center gap-3">
                <h2 class="h5 mb-0">images_file</h2>
                <div class="d-flex flex-wrap gap-2">
                    <a class="btn <?= ($activeFilter ?? 'all') === 'all' ? 'btn-primary' : 'btn-outline-primary' ?>" href="<?= Html::escape(Html::buildUrl('/image-files', ['per_page' => $paginator->perPage])) ?>">
                        Alle Bilder
                    </a>
                    <a class="btn <?= ($activeFilter ?? 'all') === 'missing' ? 'btn-danger' : 'btn-outline-danger' ?>" href="<?= Html::escape(Html::buildUrl('/image-files', ['filter' => 'missing', 'per_page' => $paginator->perPage])) ?>">
                        Nicht gefundene Bilder (<?= Html::escape($summary['missing_path'] ?? 0) ?>)
                    </a>
                </div>
            </div>
            <form class="row g-3 align-items-end" method="get" action="/image-files">
                <?php if (($activeFilter ?? 'all') !== 'all'): ?>
                    <input type="hidden" name="filter" value="<?= Html::escape($activeFilter) ?>">
                <?php endif; ?>
                <div class="col-12 col-md-3">
                    <label class="form-label">Pro Seite</label>
                    <select class="form-select" name="per_page">
                        <?php foreach ([10, 20, 50, 100] as $size): ?>
                            <option value="<?= $size ?>" <?= $paginator->perPage === $size ? 'selected' : '' ?>><?= $size ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-12 col-md-auto">
                    <button class="btn btn-outline-primary" type="submit">Ansicht aktualisieren</button>
                </div>
                <div class="col-12 col-md">
                    <div class="text-secondary small">
                        <?= Html::escape($paginator->total) ?> Bilder
                        <span class="mx-1">|</span>
                        Seite <?= Html::escape($paginator->page) ?> von <?= Html::escape($paginator->totalPages()) ?>
                    </div>
                </div>
            </form>
            <div class="d-flex flex-column flex-md-row justify-content-between align-items-md-center gap-3 pt-2 border-top">
                <div class="text-secondary small">
                    <?= Html::escape($paginator->total) ?> Bilder
                    <span class="mx-1">|</span>
                    Seite <?= Html::escape($paginator->page) ?> von <?= Html::escape($paginator->totalPages()) ?>
                </div>
                <?php $path = '/image-files'; $query = $tableQuery; require dirname(__DIR__) . '/partials/pagination.php'; ?>
            </div>
        </div>
    </div>
    <div class="table-responsive">
        <table class="table table-hover mb-0">
            <thead>
            <tr>
                <th>Dateiname</th>
                <th>Refs</th>
                <th>Upload</th>
                <th>Lokaler Pfad</th>
                <th>Hash</th>
                <th>Shop-Pfad</th>
                <th>Fehler</th>
            </tr>
            </thead>
            <tbody>
            <?php if ($rows === []): ?>
                <tr>
                    <td colspan="7" class="text-center text-secondary py-4">
                        <?= ($activeFilter ?? 'all') === 'missing'
                            ? 'Aktuell sind keine fehlenden Produkt- oder Kategoriebilder markiert.'
                            : 'Keine Bilder vorhanden.' ?>
                    </td>
                </tr>
            <?php endif; ?>
            <?php foreach ($rows as $row): ?>
                <tr>
                    <td><?= Html::escape($row['file_name'] ?? '') ?></td>
                    <td>
                        <?php if ((int) ($row['reference_count'] ?? 0) > 0): ?>
                            <button
                                class="btn btn-sm btn-outline-secondary"
                                type="button"
                                data-image-references-open
                                data-image-id="<?= Html::escape($row['id'] ?? 0) ?>"
                                data-image-name="<?= Html::escape($row['file_name'] ?? '') ?>"
                            >
                                <?= Html::escape($row['reference_count'] ?? 0) ?>
                            </button>
                        <?php else: ?>
                            <?= Html::escape($row['reference_count'] ?? 0) ?>
                        <?php endif; ?>
                    </td>
                    <td>
                        <span class="badge <?= (int) ($row['upload'] ?? 0) === 1 ? 'text-bg-warning' : 'text-bg-success' ?>">
                            <?= (int) ($row['upload'] ?? 0) === 1 ? 'ja' : 'nein' ?>
                        </span>
                    </td>
                    <td class="truncate-cell" title="<?= Html::escape($row['local_path'] ?? '') ?>"><?= Html::escape($row['local_path'] ?? '') ?></td>
                    <td><code><?= Html::escape($row['file_hash'] ?? '') ?></code></td>
                    <td class="truncate-cell" title="<?= Html::escape($row['shop_server_path'] ?? '') ?>"><?= Html::escape($row['shop_server_path'] ?? '') ?></td>
                    <td class="truncate-cell" title="<?= Html::escape($row['last_error'] ?? '') ?>"><?= Html::escape($row['last_error'] ?? '') ?></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <div class="d-flex flex-column flex-md-row justify-content-between align-items-md-center gap-3 p-4 border-top">
        <div class="text-secondary small">
            <?= Html::escape($paginator->total) ?> Bilder
            <span class="mx-1">|</span>
            Seite <?= Html::escape($paginator->page) ?> von <?= Html::escape($paginator->totalPages()) ?>
        </div>
        <?php $path = '/image-files'; $query = $tableQuery; require dirname(__DIR__) . '/partials/pagination.php'; ?>
    </div>
</div>

<style>
    .image-references-backdrop {
        position: fixed;
        inset: 0;
        background: rgba(15, 23, 42, 0.55);
        display: none;
        align-items: center;
        justify-content: center;
        z-index: 1060;
        padding: 1.5rem;
    }
    .image-references-backdrop.is-open {
        display: flex;
    }
    .image-references-modal {
        width: min(1100px, 100%);
        max-height: min(88vh, 960px);
        display: flex;
        flex-direction: column;
        background: #fff;
        border-radius: 24px;
        box-shadow: 0 24px 70px rgba(15, 23, 42, 0.28);
        overflow: hidden;
    }
    .image-references-body {
        overflow: auto;
        padding: 1rem 1.25rem 1.25rem;
    }
    .image-references-status {
        min-height: 1.25rem;
        font-size: 0.9rem;
        color: var(--text-soft);
    }
</style>

<div class="image-references-backdrop" data-image-references-modal aria-hidden="true">
    <div class="image-references-modal">
        <div class="d-flex align-items-center justify-content-between px-4 py-3 border-bottom">
            <div>
                <h2 class="h5 mb-1" data-image-references-title>Bild-Referenzen</h2>
                <div class="small text-secondary">Zeigt, welche Produkte oder Kategorien dieses Bild aktuell referenzieren.</div>
            </div>
            <button class="btn btn-outline-secondary" type="button" data-image-references-close>Schliessen</button>
        </div>
        <div class="image-references-body">
            <div class="image-references-status" data-image-references-status></div>
            <div data-image-references-content></div>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function () {
    var modal = document.querySelector('[data-image-references-modal]');
    if (!modal) {
        return;
    }

    var titleNode = modal.querySelector('[data-image-references-title]');
    var contentNode = modal.querySelector('[data-image-references-content]');
    var statusNode = modal.querySelector('[data-image-references-status]');
    var currentTitle = 'Bild-Referenzen';

    function setStatus(message) {
        if (statusNode) {
            statusNode.textContent = message || '';
        }
    }

    function closeModal() {
        modal.classList.remove('is-open');
        modal.setAttribute('aria-hidden', 'true');
        setStatus('');
    }

    function openModal(title) {
        currentTitle = title || 'Bild-Referenzen';
        if (titleNode) {
            titleNode.textContent = currentTitle;
        }
        modal.classList.add('is-open');
        modal.setAttribute('aria-hidden', 'false');
    }

    async function loadReferences(url, title) {
        openModal(title || currentTitle);
        setStatus('Referenzen werden geladen ...');

        try {
            var response = await fetch(url, {
                headers: {
                    'X-Requested-With': 'XMLHttpRequest'
                }
            });

            if (!response.ok) {
                throw new Error('Referenzen konnten nicht geladen werden.');
            }

            contentNode.innerHTML = await response.text();
            setStatus('');
        } catch (error) {
            contentNode.innerHTML = '<div class="alert alert-danger border-0 shadow-sm mb-0">' + String(error.message || error) + '</div>';
            setStatus('Laden fehlgeschlagen.');
        }
    }

    document.querySelectorAll('[data-image-references-open]').forEach(function (button) {
        button.addEventListener('click', function () {
            var imageId = button.dataset.imageId || '';
            var imageName = button.dataset.imageName || 'Bild-Referenzen';
            if (!imageId) {
                return;
            }

            loadReferences('/image-files/references?id=' + encodeURIComponent(imageId), 'Referenzen: ' + imageName);
        });
    });

    modal.addEventListener('click', function (event) {
        if (event.target === modal) {
            closeModal();
            return;
        }

        var closeButton = event.target.closest('[data-image-references-close]');
        if (closeButton) {
            closeModal();
            return;
        }

        var pageLink = event.target.closest('.page-link');
        if (pageLink && contentNode.contains(pageLink)) {
            event.preventDefault();
            loadReferences(pageLink.getAttribute('href'), currentTitle);
        }
    });

    modal.addEventListener('submit', function (event) {
        var form = event.target.closest('[data-image-references-form]');
        if (!form) {
            return;
        }

        event.preventDefault();
        var formData = new FormData(form);
        var params = new URLSearchParams();
        formData.forEach(function (value, key) {
            params.set(key, String(value));
        });
        params.delete('page');

        loadReferences(form.getAttribute('action') + '?' + params.toString(), currentTitle);
    });

    document.addEventListener('keydown', function (event) {
        if (event.key === 'Escape' && modal.classList.contains('is-open')) {
            closeModal();
        }
    });
});
</script>
