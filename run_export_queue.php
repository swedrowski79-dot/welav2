<?php

require __DIR__ . '/src/Database/ConnectionFactory.php';
require __DIR__ . '/src/Monitoring/SyncMonitor.php';
require __DIR__ . '/src/Service/ExportQueueWorker.php';
require __DIR__ . '/src/Service/WelaApiClient.php';
require __DIR__ . '/src/Service/XtQueueWriter.php';
require __DIR__ . '/src/Service/XtBatchQueueWriter.php';
require __DIR__ . '/src/Service/AbstractXtWriter.php';
require __DIR__ . '/src/Service/StageCategoryMap.php';
require __DIR__ . '/src/Service/XtCompositeWriter.php';
require __DIR__ . '/src/Service/XtCategoryWriter.php';
require __DIR__ . '/src/Service/XtProductWriter.php';
require __DIR__ . '/src/Service/XtMediaDocumentWriter.php';
require __DIR__ . '/src/Web/Repository/EnvFileRepository.php';

$configSources = require __DIR__ . '/config/sources.php';
$configDelta = require __DIR__ . '/config/delta.php';
$configXtWrite = require __DIR__ . '/config/xt_write.php';
$envValues = (new App\Web\Repository\EnvFileRepository(__DIR__ . '/.env'))->load();
$configuredBatchSize = max(0, (int) ($envValues['EXPORT_WORKER_BATCH_SIZE'] ?? 0));
$configuredWorkerCount = max(1, (int) ($envValues['EXPORT_WORKER_COUNT'] ?? 1));
$childMode = in_array('--child', $argv, true);
$workerIndex = 0;
$requestedEntityType = null;
$requestedBatchSize = null;
foreach ($argv as $arg) {
    if (is_string($arg) && str_starts_with($arg, '--worker-index=')) {
        $workerIndex = max(0, (int) substr($arg, strlen('--worker-index=')));
    }

    if (is_string($arg) && str_starts_with($arg, '--entity=')) {
        $requestedEntityType = trim(substr($arg, strlen('--entity=')));
    }

    if (is_string($arg) && ctype_digit($arg)) {
        $requestedBatchSize = max(0, (int) $arg);
    }
}
$limit = $requestedBatchSize ?? $configuredBatchSize;
$limit = $limit > 0 ? $limit : null;

if ($requestedEntityType !== null && $requestedEntityType !== '') {
    $matchingConfigKeys = array_values(array_filter(
        (array) ($configDelta['export_queue_entities'] ?? []),
        static function (mixed $configKey) use ($configDelta, $requestedEntityType): bool {
            return is_string($configKey)
                && (($configDelta[$configKey]['entity_type'] ?? null) === $requestedEntityType);
        }
    ));

    if ($matchingConfigKeys === []) {
        throw new InvalidArgumentException("Unbekannter Export-Entity-Filter '{$requestedEntityType}'.");
    }

    $configDelta['export_queue_entities'] = $matchingConfigKeys;
}

if (!$childMode && $configuredWorkerCount > 1) {
    $childProcesses = [];
    $batchArgument = $limit !== null ? ' ' . escapeshellarg((string) $limit) : '';
    $entityArgument = $requestedEntityType !== null && $requestedEntityType !== ''
        ? ' ' . escapeshellarg('--entity=' . $requestedEntityType)
        : '';

    for ($index = 1; $index <= $configuredWorkerCount; $index++) {
        $logFile = sprintf('/tmp/export_queue_worker_%02d.log', $index);
        $command = sprintf(
            '%s %s%s --child %s%s',
            escapeshellarg(PHP_BINARY),
            escapeshellarg(__FILE__),
            $batchArgument,
            escapeshellarg('--worker-index=' . $index),
            $entityArgument
        );

        $process = proc_open($command, [
            0 => ['file', '/dev/null', 'r'],
            1 => ['file', $logFile, 'a'],
            2 => ['file', $logFile, 'a'],
        ], $pipes, __DIR__);

        if (!is_resource($process)) {
            throw new RuntimeException('Export Queue Child-Worker konnte nicht gestartet werden.');
        }

        $childProcesses[] = [
            'index' => $index,
            'process' => $process,
        ];
    }

    $failedWorkers = [];

    foreach ($childProcesses as $childProcess) {
        $exitCode = proc_close($childProcess['process']);
        if ($exitCode !== 0) {
            $failedWorkers[] = '#' . $childProcess['index'] . ' (' . $exitCode . ')';
        }
    }

    if ($failedWorkers !== []) {
        throw new RuntimeException(
            'Mindestens ein Export-Worker ist fehlgeschlagen: ' . implode(', ', $failedWorkers)
        );
    }

    echo "Export Queue Worker abgeschlossen.\n";

    return;
}

$stageDb = ConnectionFactory::create($configSources['sources']['stage']);
$monitor = new SyncMonitor($stageDb);
$runId = $monitor->start('export_queue_worker', [
    'script' => 'run_export_queue.php',
    'batch_size' => $limit,
    'worker_index' => $workerIndex,
    'allow_parallel' => $childMode,
    'entity_type_filter' => $requestedEntityType,
]);

try {
    $monitor->log($runId, 'info', 'Export Queue Worker gestartet.');
    $writerPerformanceLogger = static function (string $message, array $context = []) use ($monitor, $runId): void {
        $monitor->log($runId, 'info', $message, $context);
    };
    $xtWriter = new XtCompositeWriter([
        new XtCategoryWriter($configSources, $configXtWrite),
        new XtProductWriter($configSources, $configXtWrite, $writerPerformanceLogger),
        new XtMediaDocumentWriter($configSources, $configXtWrite, $writerPerformanceLogger, $stageDb),
    ]);
    $stats = (new ExportQueueWorker($stageDb, $configDelta, $monitor, $runId, $xtWriter))->run($limit);
    $monitor->finish($runId, 'success', [
        'merged_records' => (int) ($stats['done'] ?? 0),
        'error_count' => (int) ($stats['error'] ?? 0),
        'context' => $stats,
    ], 'Export Queue Worker abgeschlossen.');

    echo "Export Queue Worker abgeschlossen.\n";
} catch (Throwable $exception) {
    $monitor->log($runId, 'error', 'Export Queue Worker fehlgeschlagen.', [
        'exception' => $exception->getMessage(),
    ]);
    $monitor->error($runId, $exception->getMessage(), [
        'source' => 'export_queue_worker',
        'trace' => $exception->getTraceAsString(),
    ]);
    $monitor->finish($runId, 'failed', [
        'error_count' => 1,
    ], 'Export Queue Worker fehlgeschlagen.');

    throw $exception;
}
