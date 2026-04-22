<?php use App\Web\Core\Html; ?>

<?php if (!empty($saved)): ?>
    <div class="alert alert-success border-0 shadow-sm">Dokumentenpfad gespeichert.</div>
<?php endif; ?>

<?php if (!empty($scanDone)): ?>
    <div class="alert alert-success border-0 shadow-sm">Dokumentenpfad gescannt und `documents_file` aktualisiert.</div>
<?php endif; ?>

<?php if (!empty($uploadDone)): ?>
    <div class="alert alert-success border-0 shadow-sm">Alle markierten Dokument-Dateien wurden zur API hochgeladen.</div>
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
        <h2 class="h5 mb-0">documents_file</h2>
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
            <?php foreach ($rows as $row): ?>
                <tr>
                    <td><?= Html::escape($row['title'] ?? '') ?></td>
                    <td><?= Html::escape($row['reference_count'] ?? 0) ?></td>
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
</div>
