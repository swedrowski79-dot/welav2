<?php

declare(strict_types=1);

namespace App\Web\Controller;

use App\Web\Core\Controller;
use App\Web\Core\Request;
use App\Web\Repository\DashboardRepository;
use App\Web\Repository\SourceStatusRepository;
use App\Web\Repository\StageConnection;

final class DashboardController extends Controller
{
    public function __invoke(Request $request): string
    {
        $stageDb = StageConnection::make();
        $dashboard = new DashboardRepository($stageDb);
        $sources = new SourceStatusRepository();

        return $this->render('dashboard/index', [
            'pageTitle' => 'Dashboard',
            'pageSubtitle' => 'Status, Kennzahlen und letzte Aktivitaeten der Sync-Schnittstelle.',
            'metrics' => $dashboard->metrics(),
            'runs' => $dashboard->latestRuns(),
            'lastSuccessfulRun' => $dashboard->lastSuccessfulRun(),
            'lastError' => $dashboard->lastError(),
            'sourceStatuses' => $sources->statuses(),
            'currentPath' => $request->path(),
        ]);
    }
}
