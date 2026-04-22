<?php

declare(strict_types=1);

namespace App\Web\Controller;

use App\Web\Core\Controller;
use App\Web\Core\Html;
use App\Web\Core\Request;
use App\Web\Core\Response;
use App\Web\Repository\DocumentFileRepository;
use App\Web\Repository\EnvFileRepository;
use App\Web\Repository\StageConnection;

final class DocumentFileController extends Controller
{
    public function index(Request $request): string
    {
        $envRepository = new EnvFileRepository();
        $envValues = $envRepository->load();
        $repository = new DocumentFileRepository(StageConnection::make());
        $repository->ensureSchema();

        return $this->render('document-files/index', [
            'pageTitle' => 'Dokument-Dateien',
            'pageSubtitle' => 'Getrennter Dokument-Scan und Datei-Upload ausserhalb der Pipeline.',
            'documentPath' => (string) ($envValues['DOCUMENTS_ROOT_PATH'] ?? ''),
            'shopTargetPath' => (string) ($envValues['XT_DOCUMENTS_TARGET_PATH'] ?? ''),
            'summary' => $repository->summary(),
            'rows' => $repository->paginatedRows(200),
            'saved' => $request->query('saved') === '1',
            'scanDone' => $request->query('scan_done') === '1',
            'uploadDone' => $request->query('upload_done') === '1',
            'errorMessage' => $request->string('error'),
            'currentPath' => $request->path(),
        ]);
    }

    public function savePath(Request $request): void
    {
        try {
            (new EnvFileRepository())->save([
                'DOCUMENTS_ROOT_PATH' => $request->postString('DOCUMENTS_ROOT_PATH'),
            ]);

            Response::redirect(Html::buildUrl('/document-files', ['saved' => 1]));
        } catch (\Throwable $exception) {
            Response::redirect(Html::buildUrl('/document-files', ['error' => $exception->getMessage()]));
        }
    }

    public function browse(Request $request): string
    {
        $envValues = (new EnvFileRepository())->load();
        $configuredPath = (string) ($envValues['DOCUMENTS_ROOT_PATH'] ?? '/');
        $path = $request->string('path', $configuredPath !== '' ? $configuredPath : '/');
        $repository = new DocumentFileRepository(StageConnection::make());
        $browser = $repository->browseDirectories($path);

        return $this->render('document-files/browse', [
            'pageTitle' => 'Dokumentenpfad waehlen',
            'pageSubtitle' => 'Verzeichnis fuer den separaten Dokument-Scan auswaehlen.',
            'browser' => $browser,
            'currentPath' => '/document-files',
        ]);
    }

    public function browseTree(Request $request): void
    {
        try {
            $envValues = (new EnvFileRepository())->load();
            $configuredPath = (string) ($envValues['DOCUMENTS_ROOT_PATH'] ?? '/');
            $path = $request->string('path', $configuredPath !== '' ? $configuredPath : '/');
            $browser = (new DocumentFileRepository(StageConnection::make()))->browseDirectories($path);

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

    public function scan(Request $request): void
    {
        try {
            $documentPath = $this->documentPath();
            $repository = new DocumentFileRepository(StageConnection::make());
            $repository->scanDirectory($documentPath);

            Response::redirect(Html::buildUrl('/document-files', ['scan_done' => 1]));
        } catch (\Throwable $exception) {
            Response::redirect(Html::buildUrl('/document-files', ['error' => $exception->getMessage()]));
        }
    }

    public function upload(Request $request): void
    {
        try {
            $documentPath = $this->documentPath();
            $sources = \web_config('sources');
            $connection = $sources['sources']['xt']['connection'] ?? [];
            $client = new \WelaApiClient(
                (string) ($connection['url'] ?? ''),
                (string) ($connection['key'] ?? ''),
                max(1, (int) ($connection['request_timeout_seconds'] ?? 30))
            );
            $targetPath = trim((string) ((new EnvFileRepository())->load()['XT_DOCUMENTS_TARGET_PATH'] ?? ''));

            $repository = new DocumentFileRepository(StageConnection::make());
            $repository->uploadPending($documentPath, $client, $targetPath);

            Response::redirect(Html::buildUrl('/document-files', ['upload_done' => 1]));
        } catch (\Throwable $exception) {
            Response::redirect(Html::buildUrl('/document-files', ['error' => $exception->getMessage()]));
        }
    }

    private function documentPath(): string
    {
        $envValues = (new EnvFileRepository())->load();
        $path = trim((string) ($envValues['DOCUMENTS_ROOT_PATH'] ?? ''));

        if ($path === '') {
            throw new \RuntimeException('DOCUMENTS_ROOT_PATH ist nicht gesetzt.');
        }

        return $path;
    }
}
