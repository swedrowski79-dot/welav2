<?php

declare(strict_types=1);

namespace App\Web\Controller;

use App\Web\Core\Controller;
use App\Web\Core\Html;
use App\Web\Core\Paginator;
use App\Web\Core\Request;
use App\Web\Core\Response;
use App\Web\Repository\EnvFileRepository;
use App\Web\Repository\MonitoringRepository;
use App\Web\Repository\PipelineAdminRepository;
use App\Web\Repository\SchemaHealthRepository;
use App\Web\Repository\StageConsistencyRepository;
use App\Web\Repository\StageConnection;
use App\Web\Repository\SyncLauncher;

final class PipelineController extends Controller
{
    public function index(Request $request): string
    {
        $stageDb = StageConnection::make();
        $repository = new PipelineAdminRepository($stageDb, \web_config('admin'), \web_config('delta'));
        $monitoringRepository = new MonitoringRepository($stageDb);
        $schemaHealth = new SchemaHealthRepository(\web_config('delta'));
        $consistencyRepository = new StageConsistencyRepository(\web_config('delta'));
        $filters = [
            'entity_type' => $request->string('entity_type'),
            'status' => $request->string('status'),
            'action' => $request->string('action'),
        ];

        $page = max(1, $request->int('page', 1));
        $perPage = $this->perPage($request);
        $paginator = new Paginator($page, $perPage, $repository->countQueueEntries($filters));
        $focusRun = $monitoringRepository->latestRunningRun();
        $latestRun = $focusRun ?? $monitoringRepository->latestRun();
        $activeLog = $monitoringRepository->latestLogForRun((int) ($latestRun['id'] ?? 0));
        $recentLogs = $monitoringRepository->recentPipelineLogs((int) ($latestRun['id'] ?? 0), 10);
        $progressLogs = array_slice($recentLogs, 0, 5);
        $progressSummary = $this->progressSummary($focusRun, $latestRun, $activeLog, $progressLogs);
        $pipelineSections = \PipelineConfig::sections('pipeline');
        $latestDeltaRun = $monitoringRepository->latestRunByTypes(['delta', 'expand']);
        $latestWorkerRun = $monitoringRepository->latestRunByTypes(['export_queue_worker']);
        $envValues = (new EnvFileRepository())->load();
        $deltaConfig = \web_config('delta');
        $defaultExportWorkerBatchSize = (int) (($deltaConfig['product_export_queue']['worker_batch_size'] ?? 1000));
        $exportWorkerBatchSize = (string) ($envValues['EXPORT_WORKER_BATCH_SIZE'] ?? (string) $defaultExportWorkerBatchSize);

        return $this->render('pipeline/index', [
            'pageTitle' => 'Pipeline & Export Queue',
            'pageSubtitle' => 'Pipeline starten, Export Queue ueberwachen und Reset-Aktionen kontrolliert ausfuehren.',
            'filters' => $filters,
            'paginator' => $paginator,
            'queueEntries' => $repository->paginatedQueueEntries($filters, $paginator),
            'queueSummary' => $repository->queueSummary(),
            'queueSummaryByEntity' => $repository->queueSummaryByEntity(),
            'queueIssueSummary' => $repository->queueIssueSummary(),
            'recentQueueIssues' => $repository->recentQueueIssues(10),
            'stateSummary' => $repository->stateSummary(),
            'schemaIssues' => $schemaHealth->issues($stageDb),
            'consistencyReport' => $consistencyRepository->report($stageDb),
            'runningRun' => $focusRun,
            'latestRun' => $latestRun,
            'activeLog' => $activeLog,
            'progressLogs' => $progressLogs,
            'progressSummary' => $progressSummary,
            'pipelineSections' => $pipelineSections,
            'refreshSeconds' => $focusRun ? 10 : null,
            'entityTypes' => $repository->entityTypes(),
            'runLogCount' => $monitoringRepository->countLogsForRun((int) ($latestRun['id'] ?? 0)),
            'latestError' => $monitoringRepository->latestPipelineError(),
            'recentLogs' => $recentLogs,
            'latestDeltaVisibility' => $this->deltaVisibility($latestDeltaRun),
            'latestWorkerVisibility' => $this->workerVisibility($latestWorkerRun),
            'recentExportWorkerIssues' => $monitoringRepository->recentExportWorkerIssues(10),
            'exportWorkerBatchSize' => $exportWorkerBatchSize,
            'started' => $request->query('started') === '1',
            'migrationsDone' => $request->int('migrations_done'),
            'resetDone' => $request->string('reset_done'),
            'errorMessage' => $request->string('error'),
            'currentPath' => $request->path(),
        ]);
    }

    public function state(Request $request): string
    {
        $repository = new PipelineAdminRepository(StageConnection::make(), \web_config('admin'), \web_config('delta'));
        $filters = [
            'q' => $request->string('q'),
            'entity_type' => $request->string('entity_type'),
        ];
        $page = max(1, $request->int('page', 1));
        $perPage = $this->perPage($request);
        $paginator = new Paginator($page, $perPage, $repository->countStateEntriesWithFilters($filters));

        return $this->render('pipeline/state', [
            'pageTitle' => 'Export States',
            'pageSubtitle' => 'Persistenter Delta-Zustand fuer die konfigurierten Export-Entity-Typen mit letztem Hash und letzter Sichtung.',
            'entries' => $repository->paginatedStateEntries($filters, $paginator),
            'filters' => $filters,
            'entityTypes' => $repository->entityTypes(),
            'paginator' => $paginator,
            'currentPath' => '/pipeline',
        ]);
    }

    public function start(Request $request): void
    {
        $job = $request->postString('job');
        $batchSizeRaw = $request->postString('batch_size');
        $batchSize = max(0, (int) $batchSizeRaw);

        try {
            $options = [];

            if (in_array($job, ['export_queue_worker', 'full_pipeline'], true) && $batchSizeRaw !== '') {
                if ($batchSize < 1) {
                    throw new \InvalidArgumentException('Batchgroesse muss groesser als 0 sein.');
                }

                (new EnvFileRepository())->save([
                    'EXPORT_WORKER_BATCH_SIZE' => (string) $batchSize,
                ]);
            }

            if ($job === 'export_queue_worker' && $batchSize > 0) {
                $options['batch_size'] = $batchSize;
            }

            (new SyncLauncher())->launch($job, $options);
            Response::redirect(Html::buildUrl('/pipeline', ['started' => 1]));
        } catch (\Throwable $exception) {
            Response::redirect(Html::buildUrl('/pipeline', ['error' => $exception->getMessage()]));
        }
    }

    public function reset(Request $request): void
    {
        $action = $request->postString('action');
        $confirmed = $request->postString('confirmed') === 'yes';
        $redirectPath = match ($action) {
            'logs' => '/logs',
            'errors' => '/errors',
            'runs' => '/sync-runs',
            default => '/pipeline',
        };

        if (!$confirmed) {
            Response::redirect(Html::buildUrl($redirectPath, ['error' => 'Reset nicht bestaetigt.']));
            return;
        }

        try {
            $repository = new PipelineAdminRepository(StageConnection::make(), \web_config('admin'), \web_config('delta'));

            match ($action) {
                'queue' => $repository->resetQueue(),
                'stage' => $repository->resetStageTables(),
                'delta_state' => $repository->resetDeltaState(),
                'mirror' => $repository->resetMirrorTables(),
                'logs' => $repository->resetLogs(),
                'errors' => $repository->resetErrors(),
                'runs' => $repository->resetRunsHistory(),
                'full' => $repository->fullReset(),
                default => throw new \InvalidArgumentException('Unbekannte Reset-Aktion: ' . $action),
            };

            Response::redirect(Html::buildUrl($redirectPath, ['reset_done' => $action]));
        } catch (\Throwable $exception) {
            Response::redirect(Html::buildUrl($redirectPath, ['error' => $exception->getMessage()]));
        }
    }

    /**
     * @param list<array<string,mixed>> $progressLogs
     * @return array{headline:string, detail:string, duration_label:string, refresh_hint:?string, last_update:string}
     */
    private function progressSummary(?array $runningRun, ?array $latestRun, ?array $activeLog, array $progressLogs): array
    {
        $run = $runningRun ?? $latestRun;
        $isRunning = $runningRun !== null;
        $runType = (string) ($run['run_type'] ?? 'pipeline');
        $durationSeconds = max(0, (int) ($run['duration_seconds'] ?? 0));
        $headline = $isRunning
            ? sprintf('%s laeuft gerade.', \PipelineConfig::labelForRunType($runType))
            : sprintf('Letzter Schritt: %s.', \PipelineConfig::labelForRunType($runType));
        $detail = 'Kein Fortschrittslog verfuegbar.';

        if (!empty($activeLog['message'])) {
            $detail = (string) $activeLog['message'];
        } elseif ($progressLogs !== []) {
            $detail = (string) ($progressLogs[0]['message'] ?? $detail);
        }

        return [
            'headline' => $headline,
            'detail' => $detail,
            'duration_label' => $this->formatDuration($durationSeconds),
            'refresh_hint' => $isRunning ? 'Seite aktualisiert sich automatisch alle 10 Sekunden waehrend des aktiven Laufs.' : null,
            'last_update' => (string) (($activeLog['created_at'] ?? null) ?: ($run['started_at'] ?? '-')),
        ];
    }

    private function deltaVisibility(?array $run): ?array
    {
        if ($run === null) {
            return null;
        }

        $context = json_decode((string) ($run['context_json'] ?? '{}'), true);
        if (!is_array($context)) {
            $context = [];
        }

        $delta = ($run['run_type'] ?? '') === 'expand' && isset($context['delta']) && is_array($context['delta'])
            ? $context['delta']
            : $context;

        $reason = match ((string) ($delta['result_reason'] ?? '')) {
            'queue_entries_created' => 'Delta hat neue Queue-Eintraege geschrieben.',
            'existing_pending_or_processing_entries' => 'Delta hat keine neuen Queue-Eintraege geschrieben, weil bereits aktive pending/processing-Eintraege existierten.',
            'errors_detected' => 'Delta hat Fehler protokolliert.',
            default => 'Delta hat keine Aenderungen erkannt.',
        };

        return [
            'run' => $run,
            'context' => $delta,
            'reason' => $reason,
        ];
    }

    private function workerVisibility(?array $run): ?array
    {
        if ($run === null) {
            return null;
        }

        $context = json_decode((string) ($run['context_json'] ?? '{}'), true);
        if (!is_array($context)) {
            $context = [];
        }

        $reason = 'Export Queue Worker hat Eintraege verarbeitet.';
        if ((int) ($context['processed'] ?? 0) === 0) {
            $reason = match ((string) ($this->firstNoWorkReason($context['entities'] ?? []) ?? '')) {
                'pending_items_waiting_for_available_at' => 'Es gab pending Queue-Eintraege, aber sie waren noch nicht ueber available_at freigegeben.',
                'items_already_processing' => 'Es gab keine claimbaren pending Eintraege, weil Eintraege bereits verarbeitet wurden.',
                'claim_conflict_or_concurrent_processing' => 'Es gab claimbare Eintraege, aber sie wurden parallel nicht mehr von diesem Worker geclaimt.',
                default => 'Es gab keine claimbaren pending Queue-Eintraege.',
            };
        }

        return [
            'run' => $run,
            'context' => $context,
            'reason' => $reason,
        ];
    }

    private function firstNoWorkReason(array $entities): ?string
    {
        foreach ($entities as $entityStats) {
            if (!is_array($entityStats)) {
                continue;
            }

            $reason = $entityStats['no_work_reason'] ?? null;
            if (is_string($reason) && $reason !== '') {
                return $reason;
            }
        }

        return null;
    }

    private function formatDuration(int $seconds): string
    {
        $hours = intdiv($seconds, 3600);
        $minutes = intdiv($seconds % 3600, 60);
        $remainingSeconds = $seconds % 60;

        return sprintf('%02d:%02d:%02d', $hours, $minutes, $remainingSeconds);
    }
}
