<?php

require __DIR__ . '/src/Database/ConnectionFactory.php';
require __DIR__ . '/src/Monitoring/SyncMonitor.php';

$configSources = require __DIR__ . '/config/sources.php';

$stageDb = ConnectionFactory::create($configSources['sources']['stage']);
$monitor = new SyncMonitor($stageDb);
$steps = [
    'import_all' => __DIR__ . '/run_import_all.php',
    'merge' => __DIR__ . '/run_merge.php',
    'expand' => __DIR__ . '/run_expand.php',
    'export_queue_worker' => __DIR__ . '/run_export_queue.php',
];

$runId = $monitor->start('full_pipeline', [
    'script' => 'run_full_pipeline.php',
    'steps' => array_keys($steps),
]);

$stats = [
    'steps_total' => count($steps),
    'steps_completed' => 0,
    'current_step' => null,
    'completed_steps' => [],
];

$runStep = static function (string $script): int {
    $command = sprintf('php %s 2>&1', escapeshellarg($script));
    passthru($command, $exitCode);

    return (int) $exitCode;
};

try {
    $monitor->log($runId, 'info', 'Full Pipeline gestartet.', [
        'steps' => array_keys($steps),
    ]);

    foreach ($steps as $stepName => $script) {
        $stats['current_step'] = $stepName;

        $monitor->log($runId, 'info', 'Pipeline-Schritt gestartet.', [
            'step' => $stepName,
            'script' => basename($script),
        ]);

        $exitCode = $runStep($script);

        if ($exitCode !== 0) {
            throw new RuntimeException(sprintf(
                'Pipeline-Schritt %s ist mit Exit-Code %d fehlgeschlagen.',
                $stepName,
                $exitCode
            ));
        }

        $stats['steps_completed']++;
        $stats['completed_steps'][] = $stepName;

        $monitor->log($runId, 'info', 'Pipeline-Schritt abgeschlossen.', [
            'step' => $stepName,
            'script' => basename($script),
            'steps_completed' => $stats['steps_completed'],
            'steps_total' => $stats['steps_total'],
        ]);
    }

    $monitor->finish($runId, 'success', [
        'merged_records' => $stats['steps_completed'],
        'context' => $stats,
    ], 'Full Pipeline abgeschlossen.');

    echo "Full Pipeline abgeschlossen.\n";
} catch (Throwable $exception) {
    $monitor->log($runId, 'error', 'Full Pipeline fehlgeschlagen.', [
        'current_step' => $stats['current_step'],
        'steps_completed' => $stats['steps_completed'],
        'exception' => $exception->getMessage(),
    ]);
    $monitor->error($runId, $exception->getMessage(), [
        'source' => 'full_pipeline',
        'record_identifier' => (string) ($stats['current_step'] ?? ''),
        'steps_completed' => $stats['steps_completed'],
        'steps_total' => $stats['steps_total'],
        'trace' => $exception->getTraceAsString(),
    ]);
    $monitor->finish($runId, 'failed', [
        'error_count' => 1,
        'context' => $stats,
    ], 'Full Pipeline fehlgeschlagen.');

    throw $exception;
}
