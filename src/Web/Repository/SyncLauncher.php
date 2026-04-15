<?php

declare(strict_types=1);

namespace App\Web\Repository;

final class SyncLauncher
{
    private const COMMANDS = [
        'import_all' => 'php /app/run_import_all.php',
        'import_products' => 'php /app/run_import_products.php',
        'import_categories' => 'php /app/run_import_categories.php',
        'merge' => 'php /app/run_merge.php',
        'expand' => 'php /app/run_expand.php',
        'delta' => 'php /app/run_delta.php',
        'export_queue_worker' => 'php /app/run_export_queue.php',
        'full_pipeline' => 'php /app/run_full_pipeline.php',
    ];

    public function launch(string $job): void
    {
        $command = self::COMMANDS[$job] ?? null;

        if ($command === null) {
            throw new \InvalidArgumentException('Unbekannter Sync-Job: ' . $job);
        }

        $logFile = '/tmp/' . $job . '.log';
        $shellCommand = sprintf(
            'nohup /bin/sh -lc %s > %s 2>&1 < /dev/null & echo $!',
            escapeshellarg($command),
            escapeshellarg($logFile)
        );

        exec($shellCommand, $output, $exitCode);
        $pid = trim((string) ($output[0] ?? ''));

        if ($exitCode !== 0 || $pid === '' || !ctype_digit($pid)) {
            throw new \RuntimeException('Sync-Job konnte nicht gestartet werden.');
        }
    }
}
