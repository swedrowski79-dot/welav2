<?php

declare(strict_types=1);

namespace App\Web\Controller;

use App\Web\Core\Controller;
use App\Web\Core\Paginator;
use App\Web\Core\Request;
use App\Web\Repository\MonitoringRepository;
use App\Web\Repository\StageConnection;

final class SyncRunController extends Controller
{
    public function index(Request $request): string
    {
        $repository = new MonitoringRepository(StageConnection::make());
        $filters = [
            'status' => $request->string('status'),
            'q' => $request->string('q'),
        ];

        $page = max(1, $request->int('page', 1));
        $perPage = $this->perPage($request);
        $paginator = new Paginator($page, $perPage, $repository->countRuns($filters));

        return $this->render('sync-runs/index', [
            'pageTitle' => 'Sync-Laeufe',
            'pageSubtitle' => 'Laufhistorie mit Status, Mengen und Dauer.',
            'runs' => $repository->paginatedRuns($filters, $paginator),
            'filters' => $filters,
            'paginator' => $paginator,
            'currentPath' => $request->path(),
        ]);
    }

    public function show(Request $request): string
    {
        $repository = new MonitoringRepository(StageConnection::make());
        $id = $request->int('id');
        $run = $repository->findRun($id);

        return $this->render('sync-runs/show', [
            'pageTitle' => $run ? 'Sync-Lauf #' . $run['id'] : 'Sync-Lauf nicht gefunden',
            'pageSubtitle' => 'Detailansicht mit Logs, Fehlern und Metriken.',
            'run' => $run,
            'logs' => $run ? $repository->runLogs($id) : [],
            'errors' => $run ? $repository->runErrors($id) : [],
            'currentPath' => '/sync-runs',
        ]);
    }
}
