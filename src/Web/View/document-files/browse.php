<?php use App\Web\Core\Html; ?>

<div class="panel-card p-4 mb-4">
    <div class="d-flex flex-column flex-lg-row justify-content-between gap-3 align-items-lg-start">
        <div>
            <h2 class="h5 mb-1">Dokumentenpfad waehlen</h2>
            <div class="small text-secondary">Waehle ein Verzeichnis fuer den separaten Dokument-Scan.</div>
        </div>
        <a class="btn btn-outline-secondary" href="/document-files">Zurueck</a>
    </div>
</div>

<div class="panel-card p-4 mb-4">
    <div class="small text-secondary mb-2">Aktueller Pfad</div>
    <div class="fw-semibold mb-3"><?= Html::escape($browser['current_path'] ?? '') ?></div>
    <form method="post" action="/document-files/path" class="mb-3">
        <input type="hidden" name="DOCUMENTS_ROOT_PATH" value="<?= Html::escape($browser['current_path'] ?? '') ?>">
        <button class="btn btn-primary" type="submit">Diesen Pfad verwenden</button>
    </form>
    <?php if (!empty($browser['parent_path'])): ?>
        <a class="btn btn-outline-secondary btn-sm" href="/document-files/browse?path=<?= urlencode((string) $browser['parent_path']) ?>">Eine Ebene nach oben</a>
    <?php endif; ?>
</div>

<div class="panel-card p-0">
    <div class="card-header px-4 py-3">
        <h2 class="h5 mb-0">Unterverzeichnisse</h2>
    </div>
    <div class="table-responsive">
        <table class="table table-hover mb-0">
            <thead>
            <tr>
                <th>Name</th>
                <th>Pfad</th>
                <th></th>
            </tr>
            </thead>
            <tbody>
            <?php foreach (($browser['directories'] ?? []) as $directory): ?>
                <tr>
                    <td><?= Html::escape($directory['name'] ?? '') ?></td>
                    <td class="truncate-cell" title="<?= Html::escape($directory['path'] ?? '') ?>"><?= Html::escape($directory['path'] ?? '') ?></td>
                    <td class="text-end">
                        <a class="btn btn-sm btn-outline-primary" href="/document-files/browse?path=<?= urlencode((string) ($directory['path'] ?? '')) ?>">Oeffnen</a>
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>
