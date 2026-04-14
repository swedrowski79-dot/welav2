<?php

declare(strict_types=1);

namespace App\Web\Controller;

use App\Web\Core\Controller;
use App\Web\Core\Paginator;
use App\Web\Core\Request;
use App\Web\Repository\MonitoringRepository;
use App\Web\Repository\StageConnection;

final class LogController extends Controller
{
    public function __invoke(Request $request): string
    {
        $repository = new MonitoringRepository(StageConnection::make());
        $filters = [
            'level' => $request->string('level'),
            'q' => $request->string('q'),
        ];

        $page = max(1, $request->int('page', 1));
        $perPage = $this->perPage($request);
        $paginator = new Paginator($page, $perPage, $repository->countLogs($filters));

        return $this->render('logs/index', [
            'pageTitle' => 'Logs',
            'pageSubtitle' => 'Protokolleintraege aus den Sync-Laeufen.',
            'logs' => $repository->paginatedLogs($filters, $paginator),
            'filters' => $filters,
            'paginator' => $paginator,
            'currentPath' => $request->path(),
        ]);
    }
}
