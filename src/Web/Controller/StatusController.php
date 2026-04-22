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
        $adminConfig = \web_config('admin');
        $statusRepository = new StatusRepository($stageDb, $adminConfig);
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
            'adminConfig' => $adminConfig,
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
                if (!$request->hasPost($field)) {
                    continue;
                }

                $updates[$field] = $request->postString($field);
            }

            (new EnvFileRepository())->save($updates);
            Response::redirect(Html::buildUrl('/status', ['saved' => 1]));
        } catch (\Throwable $exception) {
            Response::redirect(Html::buildUrl('/status', ['error' => $exception->getMessage()]));
        }
    }

    public function browseApiDirectories(Request $request): string
    {
        try {
            $envValues = (new EnvFileRepository())->load();
            $configuredPath = trim((string) ($envValues['XT_DOCUMENTS_TARGET_PATH'] ?? ''));
            $path = $request->string('path', $configuredPath);
            $browser = $this->xtApiClient()->browseServerDirectories($path !== '' ? $path : null);

            return $this->render('status/browse-api', [
                'pageTitle' => 'Shop-Dokumentpfad waehlen',
                'pageSubtitle' => 'Verzeichnis auf dem Shop-Server ueber die XT-API auswaehlen.',
                'browser' => $browser,
                'currentPath' => '/status',
            ]);
        } catch (\Throwable $exception) {
            Response::redirect(Html::buildUrl('/status', ['error' => $exception->getMessage()]));
            return '';
        }
    }

    public function browseApiTree(Request $request): void
    {
        try {
            $envValues = (new EnvFileRepository())->load();
            $configuredPath = trim((string) ($envValues['XT_DOCUMENTS_TARGET_PATH'] ?? ''));
            $path = $request->string('path', $configuredPath);
            $browser = $this->xtApiClient()->browseServerDirectories($path !== '' ? $path : null);

            Response::json([
                'ok' => true,
                'data' => $browser,
            ]);
        } catch (\Throwable $exception) {
            Response::json([
                'ok' => false,
                'error' => $exception->getMessage(),
            ], 400);
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
            'EXTRA_DB_HOST',
            'EXTRA_DB_PORT',
            'EXTRA_DB_NAME',
            'EXTRA_DB_USER',
            'EXTRA_DB_PASS',
            'EXTRA_SQLITE_PATH',
            'DOCUMENTS_ROOT_PATH',
            'XT_API_URL',
            'XT_API_KEY',
            'XT_DOCUMENTS_TARGET_PATH',
            'STAGE_DB_HOST',
            'STAGE_DB_PORT',
            'STAGE_DB_NAME',
            'STAGE_DB_USER',
            'STAGE_DB_PASS',
        ];
    }

    private function xtApiClient(): \WelaApiClient
    {
        $config = \web_config('sources');
        $connection = $config['sources']['xt']['connection'] ?? [];

        return new \WelaApiClient(
            (string) ($connection['url'] ?? ''),
            (string) ($connection['key'] ?? ''),
            max(1, (int) ($connection['request_timeout_seconds'] ?? 30))
        );
    }
}
