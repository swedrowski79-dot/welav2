<?php

declare(strict_types=1);

namespace App\Web\Controller;

use App\Web\Core\Controller;
use App\Web\Core\Html;
use App\Web\Core\Paginator;
use App\Web\Core\Request;
use App\Web\Core\Response;
use App\Web\Core\View;
use App\Web\Repository\EnvFileRepository;
use App\Web\Repository\ImageFileRepository;
use App\Web\Repository\StageConnection;

final class ImageFileController extends Controller
{
    public function index(Request $request): string
    {
        $envRepository = new EnvFileRepository();
        $envValues = $envRepository->load();
        $repository = new ImageFileRepository(StageConnection::make());
        $repository->ensureSchema();
        $filter = $this->imageFilter($request->string('filter'));
        $page = max(1, $request->int('page', 1));
        $perPage = $this->perPage($request);
        $paginator = new Paginator($page, $perPage, $repository->countRows($filter));

        return $this->render('image-files/index', [
            'pageTitle' => 'Bild-Dateien',
            'pageSubtitle' => 'Getrennter Bild-Scan und Datei-Upload ausserhalb der Pipeline.',
            'imagePath' => (string) ($envValues['IMAGES_ROOT_PATH'] ?? ''),
            'shopTargetPath' => (string) ($envValues['XT_IMAGES_TARGET_PATH'] ?? ''),
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
                'IMAGES_ROOT_PATH' => $request->postString('IMAGES_ROOT_PATH'),
            ]);

            Response::redirect(Html::buildUrl('/image-files', ['saved' => 1]));
        } catch (\Throwable $exception) {
            Response::redirect(Html::buildUrl('/image-files', ['error' => $exception->getMessage()]));
        }
    }

    public function browse(Request $request): string
    {
        $envValues = (new EnvFileRepository())->load();
        $configuredPath = (string) ($envValues['IMAGES_ROOT_PATH'] ?? '/');
        $path = $request->string('path', $configuredPath !== '' ? $configuredPath : '/');
        $repository = new ImageFileRepository(StageConnection::make());
        $browser = $repository->browseDirectories($path);

        return $this->render('image-files/browse', [
            'pageTitle' => 'Bildpfad waehlen',
            'pageSubtitle' => 'Verzeichnis fuer den separaten Bild-Scan auswaehlen.',
            'browser' => $browser,
            'currentPath' => '/image-files',
        ]);
    }

    public function references(Request $request): string
    {
        $imageId = max(0, $request->int('id'));
        $repository = new ImageFileRepository(StageConnection::make());
        $repository->ensureSchema();
        $image = $repository->findImageById($imageId);
        $page = max(1, $request->int('page', 1));
        $perPage = $this->perPage($request);
        $paginator = new Paginator($page, $perPage, $image !== null ? $repository->countReferenceRows($imageId) : 0);

        return View::render('image-files/references-overlay', [
            'image' => $image,
            'referenceRows' => $image !== null ? $repository->paginatedReferenceRows($imageId, $paginator) : [],
            'paginator' => $paginator,
        ]);
    }

    public function browseTree(Request $request): void
    {
        try {
            $envValues = (new EnvFileRepository())->load();
            $configuredPath = (string) ($envValues['IMAGES_ROOT_PATH'] ?? '/');
            $path = $request->string('path', $configuredPath !== '' ? $configuredPath : '/');
            $browser = (new ImageFileRepository(StageConnection::make()))->browseDirectories($path);

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
            $imagePath = $this->imagePath();
            $repository = new ImageFileRepository(StageConnection::make());
            $repository->scanDirectory($imagePath);

            Response::redirect(Html::buildUrl('/image-files', ['scan_done' => 1]));
        } catch (\Throwable $exception) {
            Response::redirect(Html::buildUrl('/image-files', ['error' => $exception->getMessage()]));
        }
    }

    public function upload(Request $request): void
    {
        $stageDb = StageConnection::make();
        $monitor = new \SyncMonitor($stageDb);
        $runId = null;

        try {
            $imagePath = $this->imagePath();
            $sources = \web_config('sources');
            $connection = $sources['sources']['xt']['connection'] ?? [];
            $client = new \WelaApiClient(
                (string) ($connection['url'] ?? ''),
                (string) ($connection['key'] ?? ''),
                max(1, (int) ($connection['request_timeout_seconds'] ?? 30))
            );
            $targetPath = trim((string) ((new EnvFileRepository())->load()['XT_IMAGES_TARGET_PATH'] ?? ''));

            $repository = new ImageFileRepository($stageDb);
            $runId = $monitor->start('image_upload', [
                'root_path' => $imagePath,
                'target_path' => $targetPath,
            ]);
            $monitor->log($runId, 'info', 'Bild-Upload gestartet.', [
                'root_path' => $imagePath,
                'target_path' => $targetPath,
            ]);
            $result = $repository->uploadPending($imagePath, $client, $targetPath, $monitor, $runId);
            $status = ($result['errors'] ?? 0) > 0 ? 'warning' : 'success';
            $message = ($result['pending'] ?? 0) === 0
                ? 'Keine offenen Bild-Dateien zum Upload gefunden.'
                : 'Bild-Upload abgeschlossen.';
            $monitor->finish($runId, $status, [
                'error_count' => (int) ($result['errors'] ?? 0),
                'context' => $result,
            ], $message);

            Response::redirect(Html::buildUrl('/image-files', ['upload_done' => 1]));
        } catch (\Throwable $exception) {
            if ($runId !== null) {
                $monitor->error($runId, $exception->getMessage(), [
                    'source' => 'image_upload',
                    'root_path' => $imagePath ?? null,
                ]);
                $monitor->finish($runId, 'failed', [
                    'error_count' => 1,
                    'context' => [
                        'root_path' => $imagePath ?? null,
                        'target_path' => $targetPath ?? null,
                    ],
                ], $exception->getMessage());
            }
            Response::redirect(Html::buildUrl('/image-files', ['error' => $exception->getMessage()]));
        }
    }

    public function reset(Request $request): void
    {
        try {
            $repository = new ImageFileRepository(StageConnection::make());
            $repository->resetTable();

            Response::redirect(Html::buildUrl('/image-files', ['reset_done' => 1]));
        } catch (\Throwable $exception) {
            Response::redirect(Html::buildUrl('/image-files', ['error' => $exception->getMessage()]));
        }
    }

    private function imagePath(): string
    {
        $envValues = (new EnvFileRepository())->load();
        $path = trim((string) ($envValues['IMAGES_ROOT_PATH'] ?? ''));

        if ($path === '') {
            throw new \RuntimeException('IMAGES_ROOT_PATH ist nicht gesetzt.');
        }

        return $path;
    }

    private function imageFilter(string $filter): string
    {
        return in_array($filter, ['all', 'missing'], true) ? $filter : 'all';
    }
}
