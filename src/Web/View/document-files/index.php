<?php use App\Web\Core\Html; ?>

<?php
$tableQuery = ['per_page' => $paginator->perPage];
if (($activeFilter ?? 'all') !== 'all') {
    $tableQuery['filter'] = $activeFilter;
}
?>

<?php if (!empty($saved)): ?>
    <div class="alert alert-success border-0 shadow-sm">Dokumentenpfad gespeichert.</div>
<?php endif; ?>

<?php if (!empty($scanDone)): ?>
    <div class="alert alert-success border-0 shadow-sm">Dokumentenpfad gescannt und `documents_file` aktualisiert.</div>
<?php endif; ?>

<?php if (!empty($uploadDone)): ?>
    <div class="alert alert-success border-0 shadow-sm">Alle markierten Dokument-Dateien wurden zur API hochgeladen.</div>
<?php endif; ?>

<?php if (!empty($resetDone)): ?>
    <div class="alert alert-warning border-0 shadow-sm">`documents_file` wurde geleert.</div>
<?php endif; ?>

<?php if (!empty($errorMessage)): ?>
    <div class="alert alert-danger border-0 shadow-sm"><?= Html::escape($errorMessage) ?></div>
<?php endif; ?>

<div class="panel-card p-4 mb-4">
    <div class="d-flex flex-column flex-xl-row justify-content-between gap-4">
        <div class="flex-grow-1">
            <h2 class="h5 mb-1">Dokumentenlauf</h2>
            <div class="small text-secondary mb-3">Scan und Upload laufen separat von der Pipeline. Die Pfade werden zentral unter <a href="/status">Status</a> gepflegt.</div>
            <div class="subtle-list">
                <div>
                    <div class="small text-secondary mb-1">Lokaler Dokumentpfad</div>
                    <div class="path-chip"><?= Html::escape($documentPath !== '' ? $documentPath : 'Nicht gesetzt') ?></div>
                </div>
                <div>
                    <div class="small text-secondary mb-1">Shop-Zielpfad</div>
                    <div class="path-chip"><?= Html::escape($shopTargetPath !== '' ? $shopTargetPath : 'Standard der XT-API (media/files)') ?></div>
                </div>
            </div>
        </div>
        <div class="d-flex flex-column gap-2 align-items-stretch align-items-xl-end">
            <a class="btn btn-outline-secondary" href="/status">Pfade konfigurieren</a>
            <form method="post" action="/document-files/scan">
                <button class="btn btn-outline-primary w-100" type="submit">Dokumentenpfad scannen</button>
            </form>
            <form method="post" action="/document-files/upload">
                <button class="btn btn-primary w-100" type="submit">Offene Dokumente hochladen</button>
            </form>
            <form method="post" action="/document-files/reset" onsubmit="return confirm('Wollen Sie wirklich die Tabelle documents_file leeren? Diese Aktion kann nicht rueckgaengig gemacht werden.');">
                <button class="btn btn-outline-danger w-100" type="submit">Tabelle leeren</button>
            </form>
        </div>
    </div>
    <?php if (($documentPath ?? '') === ''): ?>
        <div class="alert alert-warning border-0 shadow-sm mt-4 mb-0">Es ist noch kein lokaler Dokumentpfad gesetzt.</div>
    <?php endif; ?>
</div>

<div class="row g-4 mb-4">
    <div class="col-12 col-md-4">
        <div class="metric-card p-4 h-100">
            <div class="metric-icon mb-3"><i class="bi bi-file-earmark-text"></i></div>
            <div class="display-6 fw-semibold"><?= Html::escape($summary['total'] ?? 0) ?></div>
            <div class="text-secondary">Titel</div>
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
                <h2 class="h5 mb-0">documents_file</h2>
                <div class="d-flex flex-wrap gap-2">
                    <a class="btn <?= ($activeFilter ?? 'all') === 'all' ? 'btn-primary' : 'btn-outline-primary' ?>" href="<?= Html::escape(Html::buildUrl('/document-files', ['per_page' => $paginator->perPage])) ?>">
                        Alle Dokumente
                    </a>
                    <a class="btn <?= ($activeFilter ?? 'all') === 'missing' ? 'btn-danger' : 'btn-outline-danger' ?>" href="<?= Html::escape(Html::buildUrl('/document-files', ['filter' => 'missing', 'per_page' => $paginator->perPage])) ?>">
                        Nicht gefundene Dokumente (<?= Html::escape($summary['missing_path'] ?? 0) ?>)
                    </a>
                </div>
            </div>
            <form class="row g-3 align-items-end" method="get" action="/document-files">
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
                        <?= Html::escape($paginator->total) ?> Dokumente
                        <span class="mx-1">|</span>
                        Seite <?= Html::escape($paginator->page) ?> von <?= Html::escape($paginator->totalPages()) ?>
                    </div>
                </div>
            </form>
            <div class="d-flex flex-column flex-md-row justify-content-between align-items-md-center gap-3 pt-2 border-top">
                <div class="text-secondary small">
                    <?= Html::escape($paginator->total) ?> Dokumente
                    <span class="mx-1">|</span>
                    Seite <?= Html::escape($paginator->page) ?> von <?= Html::escape($paginator->totalPages()) ?>
                </div>
                <?php $path = '/document-files'; $query = $tableQuery; require dirname(__DIR__) . '/partials/pagination.php'; ?>
            </div>
        </div>
    </div>
    <div class="table-responsive">
        <table class="table table-hover mb-0">
            <thead>
            <tr>
                <th>Title</th>
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
                            ? 'Aktuell sind keine fehlenden Dokumente markiert.'
                            : 'Keine Dokumente vorhanden.' ?>
                    </td>
                </tr>
            <?php endif; ?>
            <?php foreach ($rows as $row): ?>
                <tr>
                    <td><?= Html::escape($row['title'] ?? '') ?></td>
                    <td>
                        <?php if ((int) ($row['reference_count'] ?? 0) > 0): ?>
                            <button
                                class="btn btn-sm btn-outline-secondary"
                                type="button"
                                data-document-references-open
                                data-document-id="<?= Html::escape($row['id'] ?? 0) ?>"
                                data-document-title="<?= Html::escape($row['title'] ?? '') ?>"
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
            <?= Html::escape($paginator->total) ?> Dokumente
            <span class="mx-1">|</span>
            Seite <?= Html::escape($paginator->page) ?> von <?= Html::escape($paginator->totalPages()) ?>
        </div>
        <?php $path = '/document-files'; $query = $tableQuery; require dirname(__DIR__) . '/partials/pagination.php'; ?>
    </div>
</div>

<style>
    .document-references-backdrop {
        position: fixed;
        inset: 0;
        background: rgba(15, 23, 42, 0.55);
        display: none;
        align-items: center;
        justify-content: center;
        z-index: 1060;
        padding: 1.5rem;
    }
    .document-references-backdrop.is-open {
        display: flex;
    }
    .document-references-modal {
        width: min(1100px, 100%);
        max-height: min(88vh, 960px);
        display: flex;
        flex-direction: column;
        background: #fff;
        border-radius: 24px;
        box-shadow: 0 24px 70px rgba(15, 23, 42, 0.28);
        overflow: hidden;
    }
    .document-references-body {
        overflow: auto;
        padding: 1rem 1.25rem 1.25rem;
    }
    .document-references-status {
        min-height: 1.25rem;
        font-size: 0.9rem;
        color: var(--text-soft);
    }
</style>

<div class="document-references-backdrop" data-document-references-modal aria-hidden="true">
    <div class="document-references-modal">
        <div class="d-flex align-items-center justify-content-between px-4 py-3 border-bottom">
            <div>
                <h2 class="h5 mb-1" data-document-references-title>Artikel-Referenzen</h2>
                <div class="small text-secondary">Zeigt, welche Artikel dieses Dokument aktuell referenzieren.</div>
            </div>
            <button class="btn btn-outline-secondary" type="button" data-document-references-close>Schliessen</button>
        </div>
        <div class="document-references-body">
            <div class="document-references-status" data-document-references-status></div>
            <div data-document-references-content></div>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function () {
    var modal = document.querySelector('[data-document-references-modal]');
    if (!modal) {
        return;
    }

    var titleNode = modal.querySelector('[data-document-references-title]');
    var contentNode = modal.querySelector('[data-document-references-content]');
    var statusNode = modal.querySelector('[data-document-references-status]');
    var currentTitle = 'Artikel-Referenzen';

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
        currentTitle = title || 'Artikel-Referenzen';
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

    document.querySelectorAll('[data-document-references-open]').forEach(function (button) {
        button.addEventListener('click', function () {
            var documentId = button.dataset.documentId || '';
            var documentTitle = button.dataset.documentTitle || 'Artikel-Referenzen';
            if (!documentId) {
                return;
            }

            loadReferences('/document-files/references?id=' + encodeURIComponent(documentId), 'Referenzen: ' + documentTitle);
        });
    });

    modal.addEventListener('click', function (event) {
        if (event.target === modal) {
            closeModal();
            return;
        }

        var closeButton = event.target.closest('[data-document-references-close]');
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
        var form = event.target.closest('[data-document-references-form]');
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
