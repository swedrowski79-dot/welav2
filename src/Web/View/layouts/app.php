<?php

use App\Web\Core\Html;

$adminConfig = web_config('admin');
$appName = $adminConfig['app_name'];
$navigation = [
    '/' => 'Dashboard',
    '/pipeline' => 'Pipeline',
    '/document-files' => 'Dokument-Dateien',
    '/sync-runs' => 'Laeufe',
    '/logs' => 'Logs',
    '/errors' => 'Fehler',
    '/stage-browser' => 'Stage',
    '/status' => 'Status',
];
?>
<!DOCTYPE html>
<html lang="de">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= Html::escape($pageTitle ?? $appName) ?></title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Manrope:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
    <style>
        :root {
            --app-bg: #f3f5f9;
            --panel-bg: #ffffff;
            --brand: #0f4c81;
            --brand-dark: #0b3254;
            --text-main: #172033;
            --text-soft: #64748b;
            --border-soft: rgba(15, 23, 42, 0.08);
            --shadow-soft: 0 16px 40px rgba(15, 23, 42, 0.08);
        }
        body {
            font-family: 'Manrope', sans-serif;
            background:
                radial-gradient(circle at top right, rgba(15, 76, 129, 0.12), transparent 22rem),
                linear-gradient(180deg, #f7f9fc 0%, var(--app-bg) 100%);
            color: var(--text-main);
        }
        .sidebar {
            background: linear-gradient(180deg, var(--brand-dark), #102c4b);
            color: #fff;
            min-height: 100vh;
            box-shadow: var(--shadow-soft);
        }
        .sidebar .nav-link {
            color: rgba(255, 255, 255, 0.78);
            border-radius: 14px;
            padding: 0.85rem 1rem;
            margin-bottom: 0.35rem;
        }
        .sidebar .nav-link.active,
        .sidebar .nav-link:hover {
            color: #fff;
            background: rgba(255, 255, 255, 0.12);
        }
        .content-header,
        .panel-card,
        .metric-card {
            background: var(--panel-bg);
            border: 1px solid var(--border-soft);
            box-shadow: var(--shadow-soft);
            border-radius: 22px;
        }
        .metric-icon {
            width: 48px;
            height: 48px;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            border-radius: 14px;
            background: rgba(15, 76, 129, 0.1);
            color: var(--brand);
        }
        .table thead th {
            font-size: 0.8rem;
            text-transform: uppercase;
            letter-spacing: 0.04em;
            color: var(--text-soft);
            background: #f8fafc;
        }
        .table-responsive {
            border-radius: 18px;
        }
        .panel-card .card-header {
            background: transparent;
            border-bottom: 1px solid var(--border-soft);
        }
        .details-card {
            overflow: hidden;
        }
        .details-card summary {
            list-style: none;
            cursor: pointer;
            padding: 1rem 1.25rem;
            border-bottom: 1px solid transparent;
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 1rem;
        }
        .details-card summary::-webkit-details-marker {
            display: none;
        }
        .details-card[open] summary {
            border-bottom-color: var(--border-soft);
        }
        .details-card summary::after {
            content: '+';
            color: var(--brand);
            font-weight: 800;
            font-size: 1.15rem;
        }
        .details-card[open] summary::after {
            content: '−';
        }
        .inline-metric {
            display: inline-flex;
            align-items: center;
            gap: 0.45rem;
            padding: 0.55rem 0.8rem;
            border-radius: 999px;
            background: rgba(15, 76, 129, 0.08);
            color: var(--brand-dark);
            font-size: 0.875rem;
            font-weight: 600;
        }
        .subtle-list {
            display: grid;
            gap: 0.75rem;
        }
        .path-chip {
            display: inline-flex;
            align-items: center;
            gap: 0.4rem;
            padding: 0.5rem 0.7rem;
            border-radius: 14px;
            background: #f8fafc;
            border: 1px solid var(--border-soft);
            font-size: 0.875rem;
            color: var(--text-soft);
            overflow-wrap: anywhere;
        }
        .truncate-cell {
            max-width: 280px;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }
        .editable-cell {
            position: relative;
            cursor: pointer;
            transition: background-color 0.15s ease;
        }
        .editable-cell:hover {
            background: rgba(15, 76, 129, 0.06);
        }
        .editable-cell.editing {
            background: rgba(15, 76, 129, 0.1);
            box-shadow: inset 0 0 0 2px rgba(15, 76, 129, 0.18);
        }
        .editable-cell.saving {
            opacity: 0.65;
        }
        .editable-cell .cell-display {
            display: block;
            min-height: 1.5rem;
        }
        .editable-cell .cell-hint {
            display: none;
            position: absolute;
            top: 0.35rem;
            right: 0.5rem;
            font-size: 0.7rem;
            color: var(--text-soft);
        }
        .editable-cell:hover .cell-hint,
        .editable-cell.editing .cell-hint {
            display: inline;
        }
        .editable-input {
            width: 100%;
            min-height: 2.5rem;
            border: 1px solid rgba(15, 76, 129, 0.2);
            border-radius: 12px;
            padding: 0.6rem 0.75rem;
            font: inherit;
            color: inherit;
            background: #fff;
        }
        .table-toolbar {
            display: flex;
            flex-direction: column;
            gap: 1rem;
        }
        .table-status {
            font-size: 0.875rem;
            color: var(--text-soft);
        }
        .table-status[data-state="success"] {
            color: #0f766e;
        }
        .table-status[data-state="error"] {
            color: #b91c1c;
        }
        .folder-browser-backdrop {
            position: fixed;
            inset: 0;
            background: rgba(15, 23, 42, 0.45);
            display: none;
            align-items: center;
            justify-content: center;
            z-index: 1050;
            padding: 1.5rem;
        }
        .folder-browser-backdrop.is-open {
            display: flex;
        }
        .folder-browser-modal {
            width: min(920px, 100%);
            max-height: min(85vh, 900px);
            display: flex;
            flex-direction: column;
            background: #fff;
            border-radius: 24px;
            box-shadow: 0 24px 70px rgba(15, 23, 42, 0.28);
            overflow: hidden;
        }
        .folder-browser-tree {
            overflow: auto;
            padding: 1rem 1.25rem 0.75rem;
            min-height: 320px;
        }
        .folder-browser-list {
            list-style: none;
            margin: 0;
            padding-left: 0;
        }
        .folder-browser-children {
            list-style: none;
            margin: 0;
            padding-left: 1.5rem;
        }
        .folder-browser-node {
            margin-bottom: 0.2rem;
        }
        .folder-browser-row {
            display: flex;
            align-items: center;
            gap: 0.4rem;
            padding: 0.35rem 0.5rem;
            border-radius: 12px;
            cursor: pointer;
        }
        .folder-browser-row:hover {
            background: rgba(15, 76, 129, 0.08);
        }
        .folder-browser-row.is-selected {
            background: rgba(15, 76, 129, 0.14);
            box-shadow: inset 0 0 0 1px rgba(15, 76, 129, 0.22);
        }
        .folder-browser-toggle {
            width: 1.75rem;
            height: 1.75rem;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            border: 1px solid rgba(15, 23, 42, 0.12);
            background: #fff;
            border-radius: 10px;
            color: var(--brand);
            font-weight: 700;
            flex: 0 0 auto;
        }
        .folder-browser-toggle[disabled] {
            opacity: 0.35;
            cursor: default;
        }
        .folder-browser-path {
            font-size: 0.85rem;
            color: var(--text-soft);
            overflow-wrap: anywhere;
        }
        .folder-browser-footer {
            border-top: 1px solid var(--border-soft);
            padding: 1rem 1.25rem 1.25rem;
            display: flex;
            flex-direction: column;
            gap: 0.75rem;
        }
        .folder-browser-status {
            min-height: 1.25rem;
            font-size: 0.9rem;
            color: var(--text-soft);
        }
        @media (max-width: 991.98px) {
            .sidebar {
                min-height: auto;
                border-radius: 0 0 24px 24px;
            }
        }
    </style>
</head>
<body>
<div class="container-fluid">
    <div class="row">
        <aside class="col-12 col-lg-3 col-xl-2 sidebar px-3 px-lg-4 py-4">
            <div class="d-flex align-items-center gap-3 mb-4">
                <div class="metric-icon bg-white text-primary"><i class="bi bi-diagram-3"></i></div>
                <div>
                    <div class="fw-bold"><?= Html::escape($appName) ?></div>
                    <div class="small text-white-50">AFS / Extra / Stage</div>
                </div>
            </div>
            <nav class="nav flex-column">
                <?php foreach ($navigation as $path => $label): ?>
                    <a class="nav-link <?= ($currentPath ?? '/') === $path ? 'active' : '' ?>" href="<?= Html::escape($path) ?>">
                        <?= Html::escape($label) ?>
                    </a>
                <?php endforeach; ?>
            </nav>
        </aside>
        <main class="col-12 col-lg-9 col-xl-10 px-3 px-md-4 py-4">
            <section class="content-header p-4 mb-4">
                <div class="d-flex flex-column flex-md-row justify-content-between align-items-md-center gap-3">
                    <div>
                        <div class="text-uppercase small fw-semibold text-secondary mb-2">Admin Dashboard</div>
                        <h1 class="h3 mb-1"><?= Html::escape($pageTitle ?? 'Uebersicht') ?></h1>
                        <p class="text-secondary mb-0"><?= Html::escape($pageSubtitle ?? '') ?></p>
                    </div>
                    <div class="text-secondary small"><?= Html::escape(date('d.m.Y H:i')) ?></div>
                </div>
            </section>
            <?= $content ?>
        </main>
    </div>
</div>
<div class="folder-browser-backdrop" data-folder-browser-modal aria-hidden="true">
    <div class="folder-browser-modal">
        <div class="d-flex align-items-center justify-content-between px-4 py-3 border-bottom">
            <div>
                <h2 class="h5 mb-1" data-folder-browser-title>Ordner waehlen</h2>
                <div class="small text-secondary">Unterordner werden erst bei Klick auf <strong>+</strong> geladen.</div>
            </div>
            <button class="btn btn-outline-secondary" type="button" data-folder-browser-close>Schliessen</button>
        </div>
        <div class="folder-browser-tree" data-folder-browser-tree></div>
        <div class="folder-browser-footer">
            <div>
                <div class="small text-secondary mb-1">Ausgewaehlter Ordner</div>
                <div class="fw-semibold" data-folder-browser-selection>-</div>
            </div>
            <div class="folder-browser-status" data-folder-browser-status></div>
            <div class="d-flex justify-content-end gap-2">
                <button class="btn btn-outline-secondary" type="button" data-folder-browser-close>Abbrechen</button>
                <button class="btn btn-primary" type="button" data-folder-browser-choose disabled>Ordner auswaehlen</button>
            </div>
        </div>
    </div>
</div>
<script>
document.addEventListener('DOMContentLoaded', function () {
    var browser = document.querySelector('[data-stage-browser]');
    if (!browser) {
        return;
    }

    var statusNode = browser.querySelector('[data-stage-status]');

    function setStatus(message, state) {
        if (!statusNode) {
            return;
        }

        statusNode.textContent = message || '';
        statusNode.dataset.state = state || '';
    }

    function buildEditor(cell, value) {
        var multiline = (value || '').length > 80 || (value || '').indexOf('\n') !== -1;
        var editor = multiline ? document.createElement('textarea') : document.createElement('input');
        editor.className = 'editable-input';

        if (!multiline) {
            editor.type = 'text';
        }

        editor.value = value || '';
        return editor;
    }

    async function saveCell(cell, nextValue) {
        var params = new URLSearchParams();
        params.set('table', cell.dataset.table || '');
        params.set('id', cell.dataset.id || '');
        params.set('field', cell.dataset.field || '');
        params.set('value', nextValue);

        cell.classList.add('saving');
        setStatus('Speichere ' + (cell.dataset.field || '') + ' ...', '');

        try {
            var response = await fetch('/stage-browser/update', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8'
                },
                body: params.toString()
            });
            var payload;

            try {
                payload = await response.json();
            } catch (jsonError) {
                throw new Error('Die Serverantwort konnte nicht verarbeitet werden.');
            }

            if (!response.ok || !payload.ok) {
                throw new Error(payload.message || 'Speichern fehlgeschlagen.');
            }

            return {
                value: payload.value === null ? '' : String(payload.value),
                isNull: payload.isNull === true
            };
        } finally {
            cell.classList.remove('saving');
        }
    }

    function cancelEdit(cell) {
        var display = cell.querySelector('.cell-display');
        var input = cell.querySelector('.editable-input');
        if (display && input) {
            display.hidden = false;
            input.remove();
        }
        cell.classList.remove('editing');
    }

    browser.querySelectorAll('[data-editable="true"]').forEach(function (cell) {
        cell.addEventListener('dblclick', function () {
            if (cell.classList.contains('editing') || cell.classList.contains('saving')) {
                return;
            }

            var display = cell.querySelector('.cell-display');
            if (!display) {
                return;
            }

            var originalValue = cell.dataset.value || '';
            var input = buildEditor(cell, originalValue);
            display.hidden = true;
            cell.classList.add('editing');
            cell.appendChild(input);
            input.focus();
            input.select();

            var finalize = async function (commit) {
                if (!cell.classList.contains('editing')) {
                    return;
                }

                if (!commit) {
                    cancelEdit(cell);
                    setStatus('Bearbeitung abgebrochen.', '');
                    return;
                }

                var nextValue = input.value;
                if (nextValue === originalValue) {
                    cancelEdit(cell);
                    setStatus('Keine Aenderung gespeichert.', '');
                    return;
                }

                try {
                    var result = await saveCell(cell, nextValue);
                    var storedValue = result.value;
                    var isNull = result.isNull === true;
                    cell.dataset.value = isNull ? '' : storedValue;
                    cell.dataset.isNull = isNull ? 'true' : 'false';
                    display.textContent = isNull ? 'NULL' : storedValue;
                    cell.classList.toggle('text-secondary', isNull);
                    cell.classList.toggle('fst-italic', isNull);
                    cancelEdit(cell);
                    setStatus('Feld gespeichert.', 'success');
                } catch (error) {
                    cancelEdit(cell);
                    var message = error && error.message ? error.message : 'Speichern fehlgeschlagen.';
                    if (message === 'The string did not match the expected pattern.') {
                        message = 'Die Eingabe konnte nicht gespeichert werden. Bitte den Feldwert pruefen und erneut versuchen.';
                    }
                    setStatus(message, 'error');
                }
            };

            input.addEventListener('keydown', function (event) {
                if (event.key === 'Escape') {
                    event.preventDefault();
                    finalize(false);
                    return;
                }

                if (event.key === 'Enter' && !event.shiftKey && input.tagName !== 'TEXTAREA') {
                    event.preventDefault();
                    finalize(true);
                    return;
                }

                if ((event.ctrlKey || event.metaKey) && event.key === 'Enter') {
                    event.preventDefault();
                    finalize(true);
                }
            });

            input.addEventListener('blur', function () {
                finalize(true);
            }, { once: true });
        });
    });
});
</script>
<script>
document.addEventListener('DOMContentLoaded', function () {
    var modal = document.querySelector('[data-folder-browser-modal]');
    if (!modal) {
        return;
    }

    var titleNode = modal.querySelector('[data-folder-browser-title]');
    var treeNode = modal.querySelector('[data-folder-browser-tree]');
    var selectionNode = modal.querySelector('[data-folder-browser-selection]');
    var statusNode = modal.querySelector('[data-folder-browser-status]');
    var chooseButton = modal.querySelector('[data-folder-browser-choose]');
    var state = {
        endpoint: '',
        inputId: '',
        selectedPath: '',
        title: '',
        rootPath: ''
    };

    function setStatus(message) {
        if (statusNode) {
            statusNode.textContent = message || '';
        }
    }

    function setSelectedPath(path) {
        state.selectedPath = path || '';
        if (selectionNode) {
            selectionNode.textContent = state.selectedPath || '-';
        }
        if (chooseButton) {
            chooseButton.disabled = state.selectedPath === '';
        }
        treeNode.querySelectorAll('.folder-browser-row').forEach(function (row) {
            row.classList.toggle('is-selected', row.dataset.path === state.selectedPath);
        });
    }

    function closeModal() {
        modal.classList.remove('is-open');
        modal.setAttribute('aria-hidden', 'true');
        document.body.style.overflow = '';
    }

    function openModal() {
        modal.classList.add('is-open');
        modal.setAttribute('aria-hidden', 'false');
        document.body.style.overflow = 'hidden';
    }

    async function fetchNode(path) {
        var url = new URL(state.endpoint, window.location.origin);
        if (path) {
            url.searchParams.set('path', path);
        }

        var response = await fetch(url.toString(), {
            headers: {
                'Accept': 'application/json'
            }
        });
        var payload = await response.json();
        if (!response.ok || !payload.ok) {
            throw new Error(payload.error || 'Ordner konnten nicht geladen werden.');
        }
        return payload.data || {};
    }

    function makeToggle(hasChildren, expanded) {
        var button = document.createElement('button');
        button.type = 'button';
        button.className = 'folder-browser-toggle';
        button.textContent = hasChildren ? (expanded ? '-' : '+') : '•';
        if (!hasChildren) {
            button.disabled = true;
        }
        return button;
    }

    function renderChildren(container, directories) {
        directories.forEach(function (directory) {
            container.appendChild(createNode({
                name: directory.name || directory.path || '',
                path: directory.path || '',
                has_children: directory.has_children === true,
                directories: null
            }));
        });
    }

    function createNode(nodeData) {
        var item = document.createElement('li');
        item.className = 'folder-browser-node';

        var row = document.createElement('div');
        row.className = 'folder-browser-row';
        row.dataset.path = nodeData.path || '';

        var toggle = makeToggle(nodeData.has_children === true, false);
        var icon = document.createElement('span');
        icon.className = 'text-warning';
        icon.innerHTML = '<i class="bi bi-folder-fill"></i>';

        var textWrap = document.createElement('div');
        textWrap.className = 'd-flex flex-column min-w-0';

        var nameNode = document.createElement('span');
        nameNode.className = 'fw-semibold';
        nameNode.textContent = nodeData.name || nodeData.path || '';

        var pathNode = document.createElement('span');
        pathNode.className = 'folder-browser-path';
        pathNode.textContent = nodeData.path || '';

        textWrap.appendChild(nameNode);
        textWrap.appendChild(pathNode);
        row.appendChild(toggle);
        row.appendChild(icon);
        row.appendChild(textWrap);
        item.appendChild(row);

        var children = document.createElement('ul');
        children.className = 'folder-browser-children';
        children.hidden = true;
        item.appendChild(children);

        row.addEventListener('click', function (event) {
            if (event.target === toggle && !toggle.disabled) {
                return;
            }
            setSelectedPath(nodeData.path || '');
        });

        toggle.addEventListener('click', async function () {
            if (toggle.disabled) {
                return;
            }

            if (children.hidden) {
                if (children.dataset.loaded !== 'true') {
                    toggle.disabled = true;
                    toggle.textContent = '...';
                    setStatus('Lade Unterordner ...');

                    try {
                        var payload = nodeData.directories
                            ? { directories: nodeData.directories }
                            : await fetchNode(nodeData.path || '');
                        renderChildren(children, payload.directories || []);
                        children.dataset.loaded = 'true';
                        nodeData.directories = null;
                        setStatus('');
                    } catch (error) {
                        setStatus(error && error.message ? error.message : 'Unterordner konnten nicht geladen werden.');
                        toggle.textContent = '+';
                        toggle.disabled = false;
                        return;
                    }
                }

                children.hidden = false;
                toggle.textContent = '-';
                toggle.disabled = false;
                return;
            }

            children.hidden = true;
            toggle.textContent = '+';
        });

        return item;
    }

    async function loadTree(initialPath) {
        treeNode.innerHTML = '';
        setStatus('Lade Ordner ...');
        setSelectedPath('');

        try {
            var payload = await fetchNode(initialPath);
            var rootList = document.createElement('ul');
            rootList.className = 'folder-browser-list';
            var rootPath = payload.current_path || initialPath || '';
            var rootName = rootPath;
            if (rootPath !== '/' && rootPath !== '') {
                var parts = rootPath.replace(/\/+$/, '').split(/[\\/]/);
                rootName = parts[parts.length - 1] || rootPath;
            }

            rootList.appendChild(createNode({
                name: rootName,
                path: rootPath,
                has_children: (payload.directories || []).length > 0,
                directories: payload.directories || []
            }));
            treeNode.appendChild(rootList);
            setSelectedPath(rootPath);
            setStatus('Ordner markieren und mit "Ordner auswaehlen" uebernehmen.');
        } catch (error) {
            treeNode.innerHTML = '';
            setStatus(error && error.message ? error.message : 'Ordner konnten nicht geladen werden.');
        }
    }

    document.querySelectorAll('[data-folder-browser-trigger]').forEach(function (button) {
        button.addEventListener('click', function () {
            state.endpoint = button.dataset.browserEndpoint || '';
            state.inputId = button.dataset.browserInput || '';
            state.title = button.dataset.browserTitle || 'Ordner waehlen';
            state.rootPath = button.dataset.browserRoot || '';

            var input = state.inputId ? document.getElementById(state.inputId) : null;
            var initialPath = input && input.value ? input.value : state.rootPath;

            if (titleNode) {
                titleNode.textContent = state.title;
            }

            openModal();
            loadTree(initialPath);
        });
    });

    modal.querySelectorAll('[data-folder-browser-close]').forEach(function (button) {
        button.addEventListener('click', closeModal);
    });

    modal.addEventListener('click', function (event) {
        if (event.target === modal) {
            closeModal();
        }
    });

    document.addEventListener('keydown', function (event) {
        if (event.key === 'Escape' && modal.classList.contains('is-open')) {
            closeModal();
        }
    });

    if (chooseButton) {
        chooseButton.addEventListener('click', function () {
            if (!state.inputId || !state.selectedPath) {
                return;
            }

            var input = document.getElementById(state.inputId);
            if (!input) {
                return;
            }

            input.value = state.selectedPath;
            input.dispatchEvent(new Event('input', { bubbles: true }));
            input.dispatchEvent(new Event('change', { bubbles: true }));
            closeModal();
        });
    }
});
</script>
</body>
</html>
