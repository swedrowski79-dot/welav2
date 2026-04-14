<?php

use App\Web\Core\Html;

$adminConfig = web_config('admin');
$appName = $adminConfig['app_name'];
$navigation = [
    '/' => 'Dashboard',
    '/pipeline' => 'Pipeline',
    '/sync-runs' => 'Monitoring Laeufe',
    '/logs' => 'Monitoring Logs',
    '/errors' => 'Monitoring Fehler',
    '/stage-browser' => 'Stage-Browser',
    '/status' => 'Konfiguration/Status',
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
        .truncate-cell {
            max-width: 280px;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
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
</body>
</html>
