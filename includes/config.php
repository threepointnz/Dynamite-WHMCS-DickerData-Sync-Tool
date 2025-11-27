<?php
/**
 * Configuration & Initialization
 * Loads environment, initializes database and API clients
 */

// Error reporting
error_reporting(E_ALL);
ini_set('display_errors', 1);
ini_set('max_execution_time', '300');

// Load .env file
function loadEnv($path)
{
    if (!file_exists($path)) {
        throw new Exception('.env file not found');
    }

    $lines = file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    foreach ($lines as $line) {
        if (strpos(trim($line), '#') === 0) {
            continue;
        }

        list($name, $value) = explode('=', $line, 2);
        $name = trim($name);
        $value = trim($value);

        if (preg_match('/^"(.*)"$/', $value, $matches)) {
            $value = $matches[1];
        } elseif (preg_match('/^\'(.*)\'$/', $value, $matches)) {
            $value = $matches[1];
        }

        $_ENV[$name] = $value;
        putenv(sprintf('%s=%s', $name, $value));
    }
}

// Load environment variables
loadEnv(__DIR__ . '/../.env');

// Include required classes
require_once __DIR__ . '/helpers.php';
require_once __DIR__ . '/../classes/DB.php';
require_once __DIR__ . '/../classes/WHMCS.php';
require_once __DIR__ . '/../classes/WHMCSAPI.php';
require_once __DIR__ . '/../classes/DickerAPI.php';
require_once __DIR__ . '/../classes/Mail.php';
require_once __DIR__ . '/../classes/Report.php';

// Session configuration
session_name('o365sync_session');

$secure = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
    || (!empty($_SERVER['HTTP_X_FORWARDED_PROTO']) && strtolower($_SERVER['HTTP_X_FORWARDED_PROTO']) === 'https');

session_set_cookie_params([
    'lifetime' => 3600,
    'path' => '/tools/msdd',
    'domain' => 'dynamiteit.nz',
    'secure' => $secure,
    'httponly' => true,
    'samesite' => 'Lax'
]);

ini_set('session.use_strict_mode', '1');
session_start();

// Initialize Database
$db = new DB($_ENV['DB_HOST'], $_ENV['DB_DBNAME'], $_ENV['DB_USER'], $_ENV['DB_PASS']);

// Initialize WHMCS
$whmcs = new WHMCS($db);

// Initialize Dicker API
$dicker = new DickerAPI($_ENV['DICKER_url'], $_ENV['DICKER_access_token']);
$result = $dicker->initialize(false);
if (is_array($result) && !$result['ok']) {
    error_log('Dicker init failed: ' . ($result['debug'] ?? 'no debug'));
}

// Fetch data
$dicker_data = $dicker->getSubscriptionDetails();
$whmcs_data = $whmcs->getO365Clients();
$problems = $whmcs->getProblematicClients();

// Generate report
$report_ = new Report($whmcs_data, $dicker_data);
$report = $report_->generate();
