<?php

declare(strict_types=1);

namespace App\Web\Controller;

use App\Web\Core\Controller;
use App\Web\Core\Html;
use App\Web\Core\Paginator;
use App\Web\Core\Request;
use App\Web\Core\Response;
use App\Web\Repository\PipelineAdminRepository;
use App\Web\Repository\StageConnection;
use App\Web\Repository\SyncLauncher;

final class PipelineController extends Controller
{
    public function index(Request $request): string
    {
        $repository = new PipelineAdminRepository(StageConnection::make(), \web_config('admin'));
        $filters = [
            'entity_type' => $request->string('entity_type'),
            'status' => $request->string('status'),
            'action' => $request->string('action'),
        ];

        $page = max(1, $request->int('page', 1));
        $perPage = $this->perPage($request);
        $paginator = new Paginator($page, $perPage, $repository->countQueueEntries($filters));

        return $this->render('pipeline/index', [
            'pageTitle' => 'Pipeline & Export Queue',
            'pageSubtitle' => 'Pipeline starten, Export Queue ueberwachen und Reset-Aktionen kontrolliert ausfuehren.',
            'filters' => $filters,
            'paginator' => $paginator,
            'queueEntries' => $repository->paginatedQueueEntries($filters, $paginator),
            'queueSummary' => $repository->queueSummary(),
            'stateSummary' => $repository->stateSummary(),
            'started' => $request->query('started') === '1',
            'resetDone' => $request->string('reset_done'),
            'errorMessage' => $request->string('error'),
            'currentPath' => $request->path(),
        ]);
    }

    public function state(Request $request): string
    {
        $repository = new PipelineAdminRepository(StageConnection::make(), \web_config('admin'));
        $search = $request->string('q');
        $page = max(1, $request->int('page', 1));
        $perPage = $this->perPage($request);
        $paginator = new Paginator($page, $perPage, $repository->countStateEntries($search));

        return $this->render('pipeline/state', [
            'pageTitle' => 'Produkt Export State',
            'pageSubtitle' => 'Persistenter Delta-Zustand fuer Produkte mit letztem Hash und letzter Sichtung.',
            'entries' => $repository->paginatedStateEntries($search, $paginator),
            'search' => $search,
            'paginator' => $paginator,
            'currentPath' => '/pipeline',
        ]);
    }

    public function start(Request $request): void
    {
        $job = $request->postString('job');

        try {
            (new SyncLauncher())->launch($job);
            Response::redirect(Html::buildUrl('/pipeline', ['started' => 1]));
        } catch (\Throwable $exception) {
            Response::redirect(Html::buildUrl('/pipeline', ['error' => $exception->getMessage()]));
        }
    }

    public function reset(Request $request): void
    {
        $action = $request->postString('action');
        $confirmed = $request->postString('confirmed') === 'yes';

        if (!$confirmed) {
            Response::redirect(Html::buildUrl('/pipeline', ['error' => 'Reset nicht bestaetigt.']));
            return;
        }

        try {
            $repository = new PipelineAdminRepository(StageConnection::make(), \web_config('admin'));

            match ($action) {
                'queue' => $repository->resetQueue(),
                'stage' => $repository->resetStageTables(),
                'delta_state' => $repository->resetDeltaState(),
                'full' => $repository->fullReset(),
                default => throw new \InvalidArgumentException('Unbekannte Reset-Aktion: ' . $action),
            };

            Response::redirect(Html::buildUrl('/pipeline', ['reset_done' => $action]));
        } catch (\Throwable $exception) {
            Response::redirect(Html::buildUrl('/pipeline', ['error' => $exception->getMessage()]));
        }
    }
}
