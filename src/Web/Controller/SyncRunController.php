<?php

declare(strict_types=1);

namespace App\Web\Controller;

use App\Web\Core\Controller;
use App\Web\Core\Html;
use App\Web\Core\Paginator;
use App\Web\Core\Request;
use App\Web\Core\Response;
use App\Web\Repository\MonitoringRepository;
use App\Web\Repository\StageConnection;
use App\Web\Repository\SyncLauncher;

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
            'pageTitle' => 'Monitoring Laeufe',
            'pageSubtitle' => 'Laufhistorie mit Status, Mengen und Dauer.',
            'runs' => $repository->paginatedRuns($filters, $paginator),
            'filters' => $filters,
            'paginator' => $paginator,
            'started' => $request->query('started') === '1',
            'resetDone' => $request->string('reset_done'),
            'errorMessage' => $request->string('error'),
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

    public function start(Request $request): void
    {
        $job = $request->postString('job');

        try {
            (new SyncLauncher())->launch($job);
            Response::redirect(Html::buildUrl('/sync-runs', ['started' => 1]));
        } catch (\Throwable $exception) {
            Response::redirect(Html::buildUrl('/sync-runs', ['error' => $exception->getMessage()]));
        }
    }
}
