<?php

declare(strict_types=1);

namespace App\Web\Repository;

final class SyncLauncher
{
    public function launch(string $job, array $options = []): void
    {
        $command = \PipelineConfig::command($job);

        if ($job === 'export_queue_worker') {
            $batchSize = max(0, (int) ($options['batch_size'] ?? 0));

            if ($batchSize > 0) {
                $command .= ' ' . escapeshellarg((string) $batchSize);
            }
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
