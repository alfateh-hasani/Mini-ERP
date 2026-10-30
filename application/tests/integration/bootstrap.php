<?php
if (PHP_SAPI !== 'cli') { exit('CLI only.'); }

$projectRoot = dirname(__DIR__, 3) . DIRECTORY_SEPARATOR;
define('FCPATH', $projectRoot);
define('APPPATH', $projectRoot . 'application/');
define('BASEPATH', $projectRoot . 'system/');
define('ENVIRONMENT', 'testing');

foreach (is_file($projectRoot . '.env') ? file($projectRoot . '.env', FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) : [] as $line) {
    if (strpos(ltrim($line), '#') === 0 || strpos($line, '=') === false) { continue; }
    [$key, $value] = explode('=', $line, 2);
    $key = trim($key);
    if ($key !== '' && getenv($key) === false) { putenv($key . '=' . trim(trim($value), "\"'")); }
}
require_once BASEPATH . 'core/Common.php';
require_once BASEPATH . 'database/DB.php';
require_once $projectRoot . 'vendor/autoload.php';

function integration_database(?string $name = null)
{
    return DB([
        'hostname' => getenv('DB_HOST') ?: 'localhost',
        'username' => getenv('DB_USERNAME') ?: '',
        'password' => getenv('DB_PASSWORD') ?: '',
        'database' => $name ?? (getenv('DB_DATABASE') ?: 'mini_erp_smart_life'),
        'dbdriver' => 'mysqli', 'dbprefix' => '', 'pconnect' => false,
        'db_debug' => false, 'cache_on' => false, 'char_set' => 'utf8mb4',
        'dbcollat' => 'utf8mb4_unicode_ci', 'stricton' => true, 'save_queries' => false,
    ], true);
}
