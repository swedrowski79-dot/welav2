<?php

declare(strict_types=1);

namespace App\Web\Controller;

use App\Web\Core\Controller;
use App\Web\Core\Paginator;
use App\Web\Core\Request;
use App\Web\Repository\StageBrowserRepository;
use App\Web\Repository\StageConnection;

final class StageBrowserController extends Controller
{
    public function index(Request $request): string
    {
        $config = \web_config('admin');
        $repository = new StageBrowserRepository(StageConnection::make(), $config['stage_tables']);
        $defaultTable = array_key_first($config['stage_tables']);
        $table = $request->string('table', $defaultTable);
        if (!isset($config['stage_tables'][$table])) {
            $table = $defaultTable;
        }
        $search = $request->string('q');
        $page = max(1, $request->int('page', 1));
        $perPage = $this->perPage($request);
        $paginator = new Paginator($page, $perPage, $repository->countRows($table, $search));

        return $this->render('stage-browser/index', [
            'pageTitle' => 'Stage-DB-Browser',
            'pageSubtitle' => 'Whitelisted Tabellen, dynamische Spalten und sichere Detailansichten.',
            'tables' => $repository->tables(),
            'currentTable' => $table,
            'columns' => $repository->schema($table),
            'rows' => $repository->paginatedRows($table, $search, $paginator),
            'search' => $search,
            'primaryKey' => $repository->primaryKey($table),
            'paginator' => $paginator,
            'currentPath' => $request->path(),
        ]);
    }

    public function show(Request $request): string
    {
        $config = \web_config('admin');
        $repository = new StageBrowserRepository(StageConnection::make(), $config['stage_tables']);
        $defaultTable = array_key_first($config['stage_tables']);
        $table = $request->string('table', $defaultTable);
        if (!isset($config['stage_tables'][$table])) {
            $table = $defaultTable;
        }

        return $this->render('stage-browser/show', [
            'pageTitle' => 'Datensatzdetail',
            'pageSubtitle' => 'Einzelansicht eines Stage-Datensatzes.',
            'tables' => $repository->tables(),
            'table' => $table,
            'row' => $repository->findRow($table, $request->string('id')),
            'primaryKey' => $repository->primaryKey($table),
            'currentPath' => '/stage-browser',
        ]);
    }
}
