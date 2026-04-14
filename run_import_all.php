<?php

require __DIR__ . '/src/Database/ConnectionFactory.php';
require __DIR__ . '/src/Monitoring/SyncMonitor.php';
require __DIR__ . '/src/Service/Normalizer.php';
require __DIR__ . '/src/Service/StageWriter.php';
require __DIR__ . '/src/Service/ImportWorkflow.php';
require __DIR__ . '/src/Importer/AfsImporter.php';
require __DIR__ . '/src/Importer/ExtraImporter.php';

$configSources = require __DIR__ . '/config/sources.php';
$configNormalize = require __DIR__ . '/config/normalize.php';

(new ImportWorkflow($configSources, $configNormalize))->runAll();
