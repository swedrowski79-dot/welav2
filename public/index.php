<?php

declare(strict_types=1);

use App\Web\Controller\DashboardController;
use App\Web\Controller\DocumentFileController;
use App\Web\Controller\ErrorController;
use App\Web\Controller\LogController;
use App\Web\Controller\PipelineController;
use App\Web\Controller\StageBrowserController;
use App\Web\Controller\StatusController;
use App\Web\Controller\SyncRunController;
use App\Web\Core\Request;
use App\Web\Core\Router;

require dirname(__DIR__) . '/src/Web/bootstrap.php';

$request = Request::capture();
$router = new Router();
$syncRunController = new SyncRunController();
$errorController = new ErrorController();
$stageBrowserController = new StageBrowserController();
$pipelineController = new PipelineController();
$documentFileController = new DocumentFileController();

$router->get('/', new DashboardController());
$router->get('/pipeline', [$pipelineController, 'index']);
$router->get('/document-files', [$documentFileController, 'index']);
$router->get('/document-files/browse', [$documentFileController, 'browse']);
$router->get('/document-files/browse-tree', [$documentFileController, 'browseTree']);
$router->post('/document-files/path', [$documentFileController, 'savePath']);
$router->post('/document-files/scan', [$documentFileController, 'scan']);
$router->post('/document-files/upload', [$documentFileController, 'upload']);
$router->get('/pipeline/state', [$pipelineController, 'state']);
$router->post('/pipeline/start', [$pipelineController, 'start']);
$router->post('/pipeline/reset', [$pipelineController, 'reset']);
$router->get('/sync-runs', [$syncRunController, 'index']);
$router->get('/sync-runs/show', [$syncRunController, 'show']);
$router->post('/sync-runs/start', [$syncRunController, 'start']);
$router->get('/logs', new LogController());
$router->get('/errors', [$errorController, 'index']);
$router->get('/errors/show', [$errorController, 'show']);
$router->get('/stage-browser', [$stageBrowserController, 'index']);
$router->get('/stage-browser/show', [$stageBrowserController, 'show']);
$router->post('/stage-browser/update', [$stageBrowserController, 'update']);
$router->get('/status', new StatusController());
$router->get('/status/browse-api', [new StatusController(), 'browseApiDirectories']);
$router->get('/status/browse-api-tree', [new StatusController(), 'browseApiTree']);
$router->post('/status/save', [new StatusController(), 'save']);
$router->post('/status/migrations', [new StatusController(), 'runMigrations']);

$router->dispatch($request);
