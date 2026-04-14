<?php

declare(strict_types=1);

use App\Web\Controller\DashboardController;
use App\Web\Controller\ErrorController;
use App\Web\Controller\LogController;
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

$router->get('/', new DashboardController());
$router->get('/sync-runs', [$syncRunController, 'index']);
$router->get('/sync-runs/show', [$syncRunController, 'show']);
$router->get('/logs', new LogController());
$router->get('/errors', [$errorController, 'index']);
$router->get('/errors/show', [$errorController, 'show']);
$router->get('/stage-browser', [$stageBrowserController, 'index']);
$router->get('/stage-browser/show', [$stageBrowserController, 'show']);
$router->get('/status', new StatusController());

$router->dispatch($request);
