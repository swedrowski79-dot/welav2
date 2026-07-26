<?php

declare(strict_types=1);

/*
 * Minimaler xt:Commerce-Bootstrap fuer die wela-api.
 *
 * Absichtlich wird NICHT xtCore/main.php geladen. Der normale Shop-Bootstrap
 * startet Plugin-, Session-, Cookie- und Frontend-Logik, die fuer die reine
 * Bildgenerierung nicht benoetigt wird und im API-Kontext fehlschlagen kann.
 */

if (defined('WELA_XT_IMAGE_BOOTSTRAPPED')) {
    return;
}

$xtCommerceRoot = getenv('XT_COMMERCE_ROOT');
if (!is_string($xtCommerceRoot) || trim($xtCommerceRoot) === '') {
    throw new RuntimeException('XT_COMMERCE_ROOT ist nicht konfiguriert.');
}

$xtCommerceRoot = rtrim(trim($xtCommerceRoot), '/\\') . DIRECTORY_SEPARATOR;

$requiredFiles = [
    'conf/config.php',
    'conf/config_charsets.php',
    'conf/paths.php',
    'conf/database.php',
    'conf/config_search.php',
    'conf/config_caches.php',
    'xtFramework/autoload.php',
    'xtFramework/function_handler.php',
    'xtFramework/library/vendor/adodb/adodb-php/adodb.inc.php',
    'xtFramework/library/adodb-xt/xtcommerce-errorhandler.inc.php',
];

foreach ($requiredFiles as $relativeFile) {
    $absoluteFile = $xtCommerceRoot . str_replace('/', DIRECTORY_SEPARATOR, $relativeFile);
    if (!is_file($absoluteFile) || !is_readable($absoluteFile)) {
        throw new RuntimeException('Erforderliche xt:Commerce-Datei fehlt oder ist nicht lesbar: ' . $absoluteFile);
    }
}

if (!defined('_VALID_CALL')) {
    define('_VALID_CALL', 'true');
}
if (!defined('USER_POSITION')) {
    define('USER_POSITION', 'store');
}
if (!defined('_SYSTEM_SQLLOG')) {
    define('_SYSTEM_SQLLOG', 'false');
}
if (!defined('_SRV_WEBROOT')) {
    define('_SRV_WEBROOT', $xtCommerceRoot);
}
if (!defined('_SRV_WEB')) {
    define('_SRV_WEB', '/');
}

/* Grundkonfiguration und Pfade laden. */
include_once _SRV_WEBROOT . 'conf/config.php';
include_once _SRV_WEBROOT . 'conf/config_charsets.php';
include_once _SRV_WEBROOT . 'conf/paths.php';
include_once _SRV_WEBROOT . 'conf/database.php';
require_once _SRV_WEBROOT . 'conf/config_search.php';
require_once _SRV_WEBROOT . _SRV_WEB_FRAMEWORK . 'autoload.php';
require_once _SRV_WEBROOT . _SRV_WEB_FRAMEWORK . 'library/vendor/autoload.php';
include_once _SRV_WEBROOT . _SRV_WEB_FRAMEWORK . 'function_handler.php';
include_once _SRV_WEBROOT . 'conf/config_caches.php';

/*
 * MediaImages ruft PluginCode() auf. Fuer die isolierte Bildverarbeitung
 * genuegt ein bewusst neutraler Hook-Provider. Dadurch werden keine Plugins
 * ausgefuehrt, waehrend die originalen xt:Commerce-Bildklassen unveraendert
 * bleiben.
 */
if (!class_exists('WelaXtNullPlugin', false)) {
    final class WelaXtNullPlugin
    {
        public array $active_modules = [];
        public array $active_modules_id = [];

        public function PluginCode(string $hook, string $pluginCode = ''): false
        {
            return false;
        }
    }
}

/* ADOdb-Verbindung bereitstellen, wie sie MediaImages erwartet. */
global $db, $xtPlugin;

if (!isset($db) || !is_object($db)) {
    include_once _SRV_WEBROOT . _SRV_WEB_FRAMEWORK . 'library/vendor/adodb/adodb-php/adodb.inc.php';
    include_once _SRV_WEBROOT . _SRV_WEB_FRAMEWORK . 'library/adodb-xt/xtcommerce-errorhandler.inc.php';

    $db = ADONewConnection('mysqli');
    $db->setFetchMode(ADODB_FETCH_ASSOC);

    if (!defined('_SYSTEM_DATABASE_HOST')) {
        throw new RuntimeException('xt:Commerce-Datenbankkonfiguration wurde nicht geladen.');
    }

    $connected = $db->Connect(
        _SYSTEM_DATABASE_HOST,
        _SYSTEM_DATABASE_USER,
        _SYSTEM_DATABASE_PWD,
        _SYSTEM_DATABASE_DATABASE
    );

    if ($connected === false) {
        throw new RuntimeException('Verbindung zur xt:Commerce-Datenbank fehlgeschlagen.');
    }

    if (defined('_SYSTEM_DB_CHARSET')) {
        $charset = (string) _SYSTEM_DB_CHARSET;
        $db->Execute('SET NAMES ' . $charset);
        $db->Execute('SET CHARACTER_SET_CLIENT=' . $charset);
        $db->Execute('SET CHARACTER_SET_RESULTS=' . $charset);
    }

    $db->Execute("SET SESSION sql_mode='NO_ENGINE_SUBSTITUTION'");
}

$xtPlugin = new WelaXtNullPlugin();

/* In der DB gespeicherte Shop-Konfiguration als Konstanten laden. */
if (function_exists('_buildDefine') && defined('TABLE_CONFIGURATION')) {
    _buildDefine($db, TABLE_CONFIGURATION);
}

/* Defaults, die der MediaImages-Konstruktor im API-Kontext benoetigt. */
if (!defined('_SYSTEM_BASE_HTTP')) {
    define('_SYSTEM_BASE_HTTP', '');
}
if (!defined('_SYSTEM_IMG_QUALITY')) {
    define('_SYSTEM_IMG_QUALITY', 90);
}

if (!class_exists('MediaImages')) {
    $mediaImagesClass = _SRV_WEBROOT . _SRV_WEB_FRAMEWORK . 'classes/class.MediaImages.php';
    if (!is_file($mediaImagesClass)) {
        throw new RuntimeException('class.MediaImages.php wurde nicht gefunden: ' . $mediaImagesClass);
    }
    require_once $mediaImagesClass;
}

if (!class_exists('MediaImages')) {
    throw new RuntimeException('Die xt:Commerce-Klasse MediaImages ist nicht verfuegbar.');
}

define('WELA_XT_IMAGE_BOOTSTRAPPED', true);
