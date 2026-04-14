<?php

declare(strict_types=1);

namespace App\Web\Controller;

use App\Web\Core\Controller;
use App\Web\Core\Html;
use App\Web\Core\Request;
use App\Web\Core\Response;
use App\Web\Repository\EnvFileRepository;
use App\Web\Repository\MigrationRepository;
use App\Web\Repository\SourceStatusRepository;
use App\Web\Repository\StageConnection;
use App\Web\Repository\StatusRepository;

final class StatusController extends Controller
{
    public function __invoke(Request $request): string
    {
        $stageDb = StageConnection::make();
        $statusRepository = new StatusRepository($stageDb);
        $sourceRepository = new SourceStatusRepository();
        $config = \web_config('sources');
        $envRepository = new EnvFileRepository();
        $migrationRepository = new MigrationRepository($stageDb, dirname(__DIR__, 3) . '/migrations');

        return $this->render('status/index', [
            'pageTitle' => 'Konfiguration & Status',
            'pageSubtitle' => 'Verbindungsstatus, zentrale ENV-Konfiguration und Tabellenmengen.',
            'sources' => $sourceRepository->statuses(),
            'stageCounts' => $statusRepository->tableCounts(),
            'migrationSummary' => $migrationRepository->summary(),
            'migrationLastResult' => $migrationRepository->lastResult(),
            'config' => $config['sources'],
            'saved' => $request->query('saved') === '1',
            'migrationsDone' => $request->int('migrations_done'),
            'errorMessage' => $request->string('error'),
            'envValues' => $envRepository->load(),
            'currentPath' => $request->path(),
        ]);
    }

    public function save(Request $request): void
    {
        try {
            $updates = [];

            foreach ($this->editableFields() as $field) {
                $updates[$field] = $request->postString($field);
            }

            (new EnvFileRepository())->save($updates);
            Response::redirect(Html::buildUrl('/status', ['saved' => 1]));
        } catch (\Throwable $exception) {
            Response::redirect(Html::buildUrl('/status', ['error' => $exception->getMessage()]));
        }
    }

    public function runMigrations(Request $request): void
    {
        try {
            $repository = new MigrationRepository(StageConnection::make(), dirname(__DIR__, 3) . '/migrations');
            $executed = $repository->runPending();
            Response::redirect(Html::buildUrl('/status', ['migrations_done' => count($executed)]));
        } catch (\Throwable $exception) {
            Response::redirect(Html::buildUrl('/status', ['error' => $exception->getMessage()]));
        }
    }

    private function editableFields(): array
    {
        return [
            'AFS_DB_HOST',
            'AFS_DB_PORT',
            'AFS_DB_NAME',
            'AFS_DB_USER',
            'AFS_DB_PASS',
            'XT_API_URL',
            'XT_API_KEY',
            'STAGE_DB_HOST',
            'STAGE_DB_PORT',
            'STAGE_DB_NAME',
            'STAGE_DB_USER',
            'STAGE_DB_PASS',
            'EXTRA_SQLITE_PATH',
        ];
    }
}
