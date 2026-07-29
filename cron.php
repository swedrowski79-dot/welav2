<?php

declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    echo "cron.php darf nur ueber die Kommandozeile ausgefuehrt werden.\n";
    exit(1);
}

require_once __DIR__ . '/src/Database/ConnectionFactory.php';
require_once __DIR__ . '/src/Service/CronScheduleService.php';

$config = require __DIR__ . '/config/cron.php';
$lockFile = trim((string) ($config['lock_file'] ?? ''));
$scheduleConfig = is_array($config['schedule'] ?? null) ? $config['schedule'] : [];
$steps = is_array($config['steps'] ?? null) ? $config['steps'] : [];
$dryRun = in_array('--dry-run', $argv, true);

$log = static function (string $message, bool $error = false): void {
    $line = sprintf('[%s] %s%s', gmdate('Y-m-d H:i:s'), $message, PHP_EOL);
    fwrite($error ? STDERR : STDOUT, $line);
};

if ($lockFile === '') {
    $log('Cron-Konfiguration enthaelt keine Lock-Datei.', true);
    exit(1);
}

if ($steps === []) {
    $log('Cron-Konfiguration enthaelt keine Schritte.', true);
    exit(1);
}

$resolvedSteps = [];
foreach ($steps as $stepName => $script) {
    if (!is_string($stepName) || trim($stepName) === '') {
        $log('Cron-Konfiguration enthaelt einen Schritt ohne Namen.', true);
        exit(1);
    }

    if (!is_string($script) || trim($script) === '') {
        $log("Cron-Schritt '{$stepName}' enthaelt kein Script.", true);
        exit(1);
    }

    $scriptPath = realpath(__DIR__ . '/' . ltrim($script, '/'));
    if ($scriptPath === false || !is_file($scriptPath)) {
        $log("Cron-Script fuer '{$stepName}' wurde nicht gefunden: {$script}", true);
        exit(1);
    }

    $projectPrefix = rtrim(__DIR__, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR;
    if (!str_starts_with($scriptPath, $projectPrefix)) {
        $log("Cron-Script fuer '{$stepName}' liegt ausserhalb des Projekts.", true);
        exit(1);
    }

    $resolvedSteps[$stepName] = $scriptPath;
}

$lockHandle = fopen($lockFile, 'c+');
if ($lockHandle === false) {
    $log("Cron-Lock konnte nicht geoeffnet werden: {$lockFile}", true);
    exit(1);
}

if (!flock($lockHandle, LOCK_EX | LOCK_NB)) {
    rewind($lockHandle);
    $holder = trim((string) stream_get_contents($lockHandle));
    $suffix = $holder !== '' ? " ({$holder})" : '';
    $log("Cronlauf uebersprungen: Ein vorheriger Lauf ist noch aktiv{$suffix}.");
    fclose($lockHandle);
    exit(0);
}

ftruncate($lockHandle, 0);
rewind($lockHandle);
fwrite($lockHandle, sprintf('pid=%d started_at=%s', getmypid(), gmdate(DATE_ATOM)));
fflush($lockHandle);

$startedAt = microtime(true);
$scheduleService = null;
$scheduleClaimed = false;

try {
    $shouldRun = true;

    if (!$dryRun) {
        $sources = require __DIR__ . '/config/sources.php';
        $stageConfig = $sources['sources']['stage'] ?? null;

        if (!is_array($stageConfig)) {
            throw new RuntimeException('Stage-Datenbank ist nicht konfiguriert.');
        }

        $scheduleService = new CronScheduleService(
            ConnectionFactory::create($stageConfig),
            $scheduleConfig
        );
        $claim = $scheduleService->claimIfDue();
        $scheduleClaimed = (bool) ($claim['due'] ?? false);
        $shouldRun = $scheduleClaimed;

        if (!$shouldRun) {
            $settings = is_array($claim['settings'] ?? null) ? $claim['settings'] : [];

            if (($claim['reason'] ?? '') === 'disabled') {
                $log('Cronlauf uebersprungen: Der Zeitplan ist im Webinterface deaktiviert.');
            } else {
                $nextRunAt = trim((string) ($settings['next_run_at'] ?? ''));
                $suffix = $nextRunAt !== '' ? " Naechster Lauf ab {$nextRunAt} UTC." : '';
                $log('Cronlauf uebersprungen: Das eingestellte Intervall ist noch nicht erreicht.' . $suffix);
            }
        }
    }

    if ($shouldRun) {
        $log($dryRun ? 'Cron-Dry-run gestartet.' : 'Cronlauf gestartet.');

        foreach ($resolvedSteps as $stepName => $scriptPath) {
            if ($dryRun) {
                $log("Dry-run: {$stepName} -> {$scriptPath}");
                continue;
            }

            $log("Cron-Schritt gestartet: {$stepName}");
            $command = sprintf(
                '%s %s 2>&1',
                escapeshellarg(PHP_BINARY),
                escapeshellarg($scriptPath)
            );

            passthru($command, $exitCode);

            if ($exitCode !== 0) {
                throw new RuntimeException(
                    "Cron-Schritt '{$stepName}' ist mit Exit-Code {$exitCode} fehlgeschlagen."
                );
            }

            $log("Cron-Schritt abgeschlossen: {$stepName}");
        }

        $duration = round(microtime(true) - $startedAt, 2);
        $message = sprintf(
            '%s nach %.2f Sekunden abgeschlossen.',
            $dryRun ? 'Cron-Dry-run' : 'Cronlauf',
            $duration
        );

        if ($scheduleClaimed && $scheduleService instanceof CronScheduleService) {
            $scheduleService->markFinished(true, $message);
        }

        $log($message);
    }
} catch (Throwable $exception) {
    if ($scheduleClaimed && $scheduleService instanceof CronScheduleService) {
        try {
            $scheduleService->markFinished(false, $exception->getMessage());
        } catch (Throwable $statusException) {
            $log('Cronstatus konnte nicht gespeichert werden: ' . $statusException->getMessage(), true);
        }
    }

    $log('Cronlauf abgebrochen: ' . $exception->getMessage(), true);
    $exitCode = 1;
} finally {
    flock($lockHandle, LOCK_UN);
    fclose($lockHandle);
}

exit($exitCode ?? 0);
