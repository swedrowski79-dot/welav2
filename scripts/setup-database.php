<?php

declare(strict_types=1);

$host = getenv('STAGE_DB_HOST') ?: 'mysql';
$port = getenv('STAGE_DB_PORT') ?: '3306';
$dbName = getenv('STAGE_DB_NAME') ?: 'stage_sync';
$user = getenv('STAGE_DB_USER') ?: 'stage';
$pass = getenv('STAGE_DB_PASS') ?: 'stage';
$basePath = dirname(__DIR__);

try {
    $pdo = connectWithRetry($host, $port, $dbName, $user, $pass);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $pdo->setAttribute(PDO::ATTR_EMULATE_PREPARES, true);

    // Das aktuelle Vollschema legt neue Tabellen an. Bereits vorhandene Tabellen bleiben erhalten.
    runSqlFile($pdo, $basePath . '/database.sql');

    // Alte/teilweise installierte Datenbanken gezielt auf den aktuellen Stand bringen.
    repairLegacySchema($pdo);

    echo "[db-setup] Datenbankschema geprüft und repariert.\n";
    exit(0);
} catch (Throwable $e) {
    fwrite(STDERR, "[db-setup] FEHLER: {$e->getMessage()}\n");
    // Der Compose-Startbefehl startet den Webserver trotzdem.
    exit(1);
}

function repairLegacySchema(PDO $pdo): void
{
    $columns = [
        'stage_products' => [
            'hash' => 'VARCHAR(64) NULL',
            'master_afs_artikel_id' => 'INT NULL AFTER `master_sku`',
        ],
        'export_queue' => [
            'claim_token' => 'VARCHAR(64) NULL AFTER `status`',
            'claimed_at' => 'DATETIME NULL AFTER `claim_token`',
            'last_error' => 'TEXT NULL AFTER `claimed_at`',
            'next_retry_at' => 'DATETIME NULL AFTER `last_error`',
        ],
        'raw_afs_documents' => [
            'title' => 'VARCHAR(255) NULL AFTER `sku`',
        ],
        'stage_product_documents' => [
            'title' => 'VARCHAR(255) NULL AFTER `afs_artikel_id`',
            'source_path' => 'VARCHAR(255) NULL AFTER `path`',
            'hash' => 'VARCHAR(64) NULL AFTER `position`',
        ],
        'stage_product_media' => [
            'hash' => 'VARCHAR(64) NULL AFTER `position`',
        ],
        'raw_afs_articles' => array_merge(
            array_combine(array_map(fn($i) => "image_{$i}", range(1, 10)), array_map(fn($i) => 'VARCHAR(255) NULL', range(1, 10))),
            [
                'attribute_name1' => 'VARCHAR(255) NULL', 'attribute_name2' => 'VARCHAR(255) NULL',
                'attribute_name3' => 'VARCHAR(255) NULL', 'attribute_name4' => 'VARCHAR(255) NULL',
                'attribute_value1' => 'VARCHAR(255) NULL', 'attribute_value2' => 'VARCHAR(255) NULL',
                'attribute_value3' => 'VARCHAR(255) NULL', 'attribute_value4' => 'VARCHAR(255) NULL',
            ]
        ),
        'xt_products_snapshot' => [
            'category_afs_id' => 'INT NULL AFTER `afs_artikel_id`',
            'translation_hash' => 'VARCHAR(64) NULL AFTER `image`',
            'attribute_hash' => 'VARCHAR(64) NULL AFTER `translation_hash`',
            'seo_hash' => 'VARCHAR(64) NULL AFTER `attribute_hash`',
        ],
        'stage_categories' => ['hash' => 'VARCHAR(64) NULL AFTER `online_flag`'],
        'raw_extra_attribute_translations' => [
            'afs_artikel_id' => 'INT NULL AFTER `row_id`',
            'sku' => 'VARCHAR(255) NULL AFTER `afs_artikel_id`',
            'sort_order' => 'INT NULL AFTER `sku`',
            'attribute_value' => 'VARCHAR(255) NULL AFTER `attribute_name`',
            'source_directory' => 'VARCHAR(255) NULL AFTER `language_code_normalized`',
            'translated_value' => 'VARCHAR(255) NULL AFTER `translated_name`',
        ],
        'raw_extra_article_translations' => ['intro_text' => 'MEDIUMTEXT NULL AFTER `name`'],
        'raw_extra_category_translations' => ['description' => 'MEDIUMTEXT NULL AFTER `name`'],
    ];

    foreach ($columns as $table => $definitions) {
        if (!tableExists($pdo, $table)) {
            echo "[db-setup] Hinweis: Tabelle {$table} fehlt; Vollschema sollte sie anlegen.\n";
            continue;
        }
        foreach ($definitions as $column => $definition) {
            ensureColumn($pdo, $table, $column, $definition);
        }
    }

    if (tableExists($pdo, 'export_queue')) {
        $type = columnType($pdo, 'export_queue', 'entity_id');
        if ($type !== null && stripos($type, 'varchar') === false) {
            executeSafe($pdo, 'ALTER TABLE `export_queue` MODIFY COLUMN `entity_id` VARCHAR(255) NOT NULL', 'export_queue.entity_id auf VARCHAR ändern');
        }
    }

    $indexes = [
        'stage_products' => ['idx_stage_products_hash' => ['hash'], 'idx_stage_products_master_afs_artikel_id' => ['master_afs_artikel_id']],
        'export_queue' => ['idx_export_queue_claim_token' => ['claim_token'], 'idx_export_queue_claimed_at' => ['claimed_at']],
        'stage_product_media' => ['idx_stage_product_media_hash' => ['hash']],
        'stage_product_documents' => ['idx_stage_product_documents_hash' => ['hash']],
        'stage_categories' => ['idx_stage_categories_hash' => ['hash']],
        'raw_extra_attribute_translations' => [
            'idx_raw_extra_attribute_translations_afs_artikel_id' => ['afs_artikel_id'],
            'idx_raw_extra_attribute_translations_sku' => ['sku'],
            'idx_raw_extra_attribute_translations_name_lang' => ['attribute_name', 'attribute_value', 'language_code_normalized'],
        ],
    ];

    foreach ($indexes as $table => $defs) {
        foreach ($defs as $name => $cols) {
            ensureIndex($pdo, $table, $name, $cols);
        }
    }
}

function ensureColumn(PDO $pdo, string $table, string $column, string $definition): void
{
    if (columnExists($pdo, $table, $column)) {
        echo "[db-setup] OK Spalte vorhanden: {$table}.{$column}\n";
        return;
    }
    executeSafe($pdo, "ALTER TABLE `{$table}` ADD COLUMN `{$column}` {$definition}", "Spalte {$table}.{$column} anlegen");
}

function ensureIndex(PDO $pdo, string $table, string $index, array $columns): void
{
    if (!tableExists($pdo, $table) || indexExists($pdo, $table, $index)) {
        return;
    }
    foreach ($columns as $column) {
        if (!columnExists($pdo, $table, $column)) {
            echo "[db-setup] WARNUNG: Index {$index} nicht angelegt, Spalte {$column} fehlt.\n";
            return;
        }
    }
    $quoted = implode(', ', array_map(fn($c) => "`{$c}`", $columns));
    executeSafe($pdo, "ALTER TABLE `{$table}` ADD KEY `{$index}` ({$quoted})", "Index {$index} anlegen");
}

function executeSafe(PDO $pdo, string $sql, string $label): void
{
    try {
        $pdo->exec($sql);
        echo "[db-setup] OK: {$label}\n";
    } catch (PDOException $e) {
        $code = (int)($e->errorInfo[1] ?? 0);
        if (in_array($code, [1050, 1060, 1061, 1091, 1826], true)) {
            echo "[db-setup] Hinweis ({$code}): {$label} war bereits erledigt.\n";
            return;
        }
        echo "[db-setup] WARNUNG ({$code}): {$label}: {$e->getMessage()}\n";
    }
}

function tableExists(PDO $pdo, string $table): bool
{
    $s = $pdo->prepare('SELECT 1 FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ?');
    $s->execute([$table]);
    return (bool)$s->fetchColumn();
}
function columnExists(PDO $pdo, string $table, string $column): bool
{
    $s = $pdo->prepare('SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND COLUMN_NAME = ?');
    $s->execute([$table, $column]);
    return (bool)$s->fetchColumn();
}
function columnType(PDO $pdo, string $table, string $column): ?string
{
    $s = $pdo->prepare('SELECT COLUMN_TYPE FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND COLUMN_NAME = ?');
    $s->execute([$table, $column]);
    $v = $s->fetchColumn();
    return $v === false ? null : (string)$v;
}
function indexExists(PDO $pdo, string $table, string $index): bool
{
    $s = $pdo->prepare('SELECT 1 FROM information_schema.STATISTICS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND INDEX_NAME = ?');
    $s->execute([$table, $index]);
    return (bool)$s->fetchColumn();
}

function connectWithRetry(string $host, string $port, string $dbName, string $user, string $pass): PDO
{
    $last = null;
    for ($i = 1; $i <= 60; $i++) {
        try {
            return new PDO("mysql:host={$host};port={$port};dbname={$dbName};charset=utf8mb4", $user, $pass, [PDO::ATTR_TIMEOUT => 5]);
        } catch (PDOException $e) {
            $last = $e;
            echo "[db-setup] Warte auf MySQL ({$i}/60) ...\n";
            sleep(2);
        }
    }
    throw new RuntimeException('MySQL nicht erreichbar: ' . ($last?->getMessage() ?? 'unbekannter Fehler'));
}

function runSqlFile(PDO $pdo, string $path): void
{
    if (!is_file($path)) throw new RuntimeException("SQL-Datei fehlt: {$path}");
    $sql = file_get_contents($path);
    if ($sql === false) throw new RuntimeException("SQL-Datei nicht lesbar: {$path}");
    foreach (splitSql($sql) as $statement) {
        $statement = trim($statement);
        if ($statement === '') continue;
        try { $pdo->exec($statement); }
        catch (PDOException $e) {
            $code = (int)($e->errorInfo[1] ?? 0);
            if (in_array($code, [1007, 1050, 1060, 1061, 1091, 1826], true)) {
                echo "[db-setup] Hinweis ({$code}): Schemaobjekt bereits vorhanden.\n";
                continue;
            }
            echo "[db-setup] WARNUNG ({$code}) in database.sql: {$e->getMessage()}\n";
        }
    }
}

function splitSql(string $sql): array
{
    $sql = preg_replace('/\/\*.*?\*\//s', '', $sql) ?? $sql;
    $sql = preg_replace('/^\s*--.*$/m', '', $sql) ?? $sql;
    $parts=[]; $buffer=''; $quote=null; $length=strlen($sql);
    for($i=0;$i<$length;$i++){
        $char=$sql[$i]; $prev=$i>0?$sql[$i-1]:'';
        if(($char==="'"||$char==='"'||$char==='`')&&$prev!=='\\') $quote=$quote===$char?null:($quote??$char);
        if($char===';'&&$quote===null){$parts[]=$buffer;$buffer='';}else{$buffer.=$char;}
    }
    if(trim($buffer)!=='')$parts[]=$buffer;
    return $parts;
}
