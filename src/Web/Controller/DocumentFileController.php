<?php

declare(strict_types=1);

namespace App\Web\Controller;

use App\Web\Core\Controller;
use App\Web\Core\Html;
use App\Web\Core\Paginator;
use App\Web\Core\Request;
use App\Web\Core\Response;
use App\Web\Core\View;
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
        $filter = $this->documentFilter($request->string('filter'));
        $page = max(1, $request->int('page', 1));
        $perPage = $this->perPage($request);
        $paginator = new Paginator($page, $perPage, $repository->countRows($filter));

        return $this->render('document-files/index', [
            'pageTitle' => 'Dokument-Dateien',
            'pageSubtitle' => 'Getrennter Dokument-Scan und Datei-Upload ausserhalb der Pipeline.',
            'documentPath' => (string) ($envValues['DOCUMENTS_ROOT_PATH'] ?? ''),
            'shopTargetPath' => (string) ($envValues['XT_DOCUMENTS_TARGET_PATH'] ?? ''),
            'summary' => $repository->summary(),
            'rows' => $repository->paginatedRows($paginator, $filter),
            'paginator' => $paginator,
            'activeFilter' => $filter,
            'saved' => $request->query('saved') === '1',
            'scanDone' => $request->query('scan_done') === '1',
            'uploadDone' => $request->query('upload_done') === '1',
            'resetDone' => $request->query('reset_done') === '1',
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

    public function references(Request $request): string
    {
        $documentId = max(0, $request->int('id'));
        $repository = new DocumentFileRepository(StageConnection::make());
        $repository->ensureSchema();
        $document = $repository->findDocumentById($documentId);
        $page = max(1, $request->int('page', 1));
        $perPage = $this->perPage($request);
        $paginator = new Paginator($page, $perPage, $document !== null ? $repository->countReferenceRows($documentId) : 0);

        return View::render('document-files/references-overlay', [
            'document' => $document,
            'referenceRows' => $document !== null ? $repository->paginatedReferenceRows($documentId, $paginator) : [],
            'paginator' => $paginator,
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
        $stageDb = StageConnection::make();
        $monitor = new \SyncMonitor($stageDb);
        $runId = null;

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

            $repository = new DocumentFileRepository($stageDb);
            $runId = $monitor->start('document_upload', [
                'root_path' => $documentPath,
                'target_path' => $targetPath,
            ]);
            $monitor->log($runId, 'info', 'Dokument-Upload gestartet.', [
                'root_path' => $documentPath,
                'target_path' => $targetPath,
            ]);
            $result = $repository->uploadPending($documentPath, $client, $targetPath, $monitor, $runId);
            $status = ($result['errors'] ?? 0) > 0 ? 'warning' : 'success';
            $message = ($result['pending'] ?? 0) === 0
                ? 'Keine offenen Dokument-Dateien zum Upload gefunden.'
                : 'Dokument-Upload abgeschlossen.';
            $monitor->finish($runId, $status, [
                'error_count' => (int) ($result['errors'] ?? 0),
                'context' => $result,
            ], $message);

            Response::redirect(Html::buildUrl('/document-files', ['upload_done' => 1]));
        } catch (\Throwable $exception) {
            if ($runId !== null) {
                $monitor->error($runId, $exception->getMessage(), [
                    'source' => 'document_upload',
                    'root_path' => $documentPath ?? null,
                ]);
                $monitor->finish($runId, 'failed', [
                    'error_count' => 1,
                    'context' => [
                        'root_path' => $documentPath ?? null,
                        'target_path' => $targetPath ?? null,
                    ],
                ], $exception->getMessage());
            }
            Response::redirect(Html::buildUrl('/document-files', ['error' => $exception->getMessage()]));
        }
    }

    public function reset(Request $request): void
    {
        try {
            $repository = new DocumentFileRepository(StageConnection::make());
            $repository->resetTable();

            Response::redirect(Html::buildUrl('/document-files', ['reset_done' => 1]));
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

    private function documentFilter(string $filter): string
    {
        return in_array($filter, ['all', 'missing'], true) ? $filter : 'all';
    }
}
