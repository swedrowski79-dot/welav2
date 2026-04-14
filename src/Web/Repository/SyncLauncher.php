<?php

declare(strict_types=1);

namespace App\Web\Repository;

final class SyncLauncher
{
    private const COMMANDS = [
        'import_all' => 'php /app/run_import_all.php',
        'merge' => 'php /app/run_merge.php',
        'expand' => 'php /app/run_expand.php',
        'delta' => 'php /app/run_delta.php',
        'full_pipeline' => 'php /app/run_import_all.php && php /app/run_merge.php && php /app/run_expand.php',
    ];

    public function launch(string $job): void
    {
        $command = self::COMMANDS[$job] ?? null;

        if ($command === null) {
            throw new \InvalidArgumentException('Unbekannter Sync-Job: ' . $job);
        }

        $logFile = '/tmp/' . $job . '.log';
        $backgroundCommand = sprintf('%s > %s 2>&1 &', $command, escapeshellarg($logFile));

        exec($backgroundCommand, $output, $exitCode);

        if ($exitCode !== 0) {
            throw new \RuntimeException('Sync-Job konnte nicht gestartet werden.');
        }
    }
}
