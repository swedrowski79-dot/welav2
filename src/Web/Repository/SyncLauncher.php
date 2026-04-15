<?php

declare(strict_types=1);

namespace App\Web\Repository;

final class SyncLauncher
{
    public function launch(string $job): void
    {
        $command = \PipelineConfig::command($job);

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
