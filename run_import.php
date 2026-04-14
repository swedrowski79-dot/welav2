<?php
$configSources = require 'config/sources.php';
$configNormalize = require 'config/normalize.php';

require 'src/Database/ConnectionFactory.php';
require 'src/Service/Normalizer.php';
require 'src/Importer/AfsImporter.php';

$afsDb = ConnectionFactory::create($configSources['sources']['afs']);
$stageDb = ConnectionFactory::create($configSources['sources']['stage']);

$normalizer = new Normalizer($configNormalize);

$importer = new AfsImporter($afsDb, $stageDb, $normalizer);

$importer->importArticles();
$importer->importCategories();

echo "AFS Import fertig\n";
