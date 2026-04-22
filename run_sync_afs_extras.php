<?php

require __DIR__ . '/src/Database/ConnectionFactory.php';
require __DIR__ . '/src/Service/StageWriter.php';
require __DIR__ . '/src/Service/AfsExtrasBootstrapService.php';

$configSources = require __DIR__ . '/config/sources.php';

$sqliteSource = $configSources['sources']['extra_sqlite_bootstrap'] ?? null;
$extraTarget = $configSources['sources']['extra'] ?? null;

if (!is_array($sqliteSource) || !is_array($extraTarget)) {
    throw new RuntimeException('AFS Extras bootstrap sources are not configured.');
}

$sqliteDb = ConnectionFactory::create($sqliteSource);
$extraDb = ConnectionFactory::create($extraTarget);

(new AfsExtrasBootstrapService($sqliteDb, $extraDb, $sqliteSource, $extraTarget))->syncAll();

echo "AFS Extras Bootstrap abgeschlossen.\n";
