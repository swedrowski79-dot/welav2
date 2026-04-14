<?php

declare(strict_types=1);

namespace App\Web\Controller;

use App\Web\Core\Controller;
use App\Web\Core\Paginator;
use App\Web\Core\Request;
use App\Web\Repository\MonitoringRepository;
use App\Web\Repository\StageConnection;

final class ErrorController extends Controller
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
        $paginator = new Paginator($page, $perPage, $repository->countErrors($filters));

        return $this->render('errors/index', [
            'pageTitle' => 'Fehler',
            'pageSubtitle' => 'Offene und historische Fehler mit Detailansicht.',
            'errors' => $repository->paginatedErrors($filters, $paginator),
            'filters' => $filters,
            'paginator' => $paginator,
            'currentPath' => $request->path(),
        ]);
    }

    public function show(Request $request): string
    {
        $repository = new MonitoringRepository(StageConnection::make());
        $error = $repository->findError($request->int('id'));

        return $this->render('errors/show', [
            'pageTitle' => $error ? 'Fehler #' . $error['id'] : 'Fehler nicht gefunden',
            'pageSubtitle' => 'Einzelansicht des Fehlerdatensatzes.',
            'error' => $error,
            'currentPath' => '/errors',
        ]);
    }
}
