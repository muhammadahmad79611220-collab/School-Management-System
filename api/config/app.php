<?php
/**
 * config/app.php
 * Application bootstrap: secure session config, error display, constants.
 * This file MUST be included before any output (sets session/cookie params).
 */

// ── Error handling: never display raw errors to users in production ──
error_reporting(E_ALL);
ini_set('display_errors', '0');     // set to '1' only for local debugging
ini_set('log_errors', '1');

define('BASE_PATH', dirname(__DIR__));

// Database must be available before session_start() because sessions are
// stored in the DB (required for Vercel — see includes/db_session_handler.php).
require_once __DIR__ . '/database.php';

// ── Secure session configuration (must run before session_start) ──
if (session_status() === PHP_SESSION_NONE) {
    ini_set('session.use_strict_mode', '1');
    ini_set('session.cookie_httponly', '1');
    ini_set('session.cookie_samesite', 'Lax');
    // Enable the line below automatically once served over HTTPS.
    if (!empty($_SERVER['HTTPS'])) {
        ini_set('session.cookie_secure', '1');
    }

    require_once BASE_PATH . '/includes/db_session_handler.php';
    session_set_save_handler(new DbSessionHandler(getDB()), true);

    session_start();
}

// ── Session timeout: auto-logout after 30 minutes idle ──
define('SESSION_TIMEOUT', 1800);
if (isset($_SESSION['last_activity']) && (time() - $_SESSION['last_activity'] > SESSION_TIMEOUT)) {
    $_SESSION = [];
    session_destroy();
    session_start();
}
$_SESSION['last_activity'] = time();

// ── App constants ──
define('APP_NAME', 'PAK GRAMMAR SCHOOL PATTOKI');
// On Vercel only /tmp is writable, and it does NOT persist between requests
// or deployments — uploaded photos WILL be lost. This is fine for a quick
// demo/test deploy but not for real student/teacher photo uploads in
// production. See the note in the chat about moving uploads to Cloudinary/S3.
define('UPLOAD_PATH', (getenv('VERCEL') ? '/tmp' : dirname(BASE_PATH) . '/assets/uploads/students'));
define('UPLOAD_URL', 'assets/uploads/students');
define('MAX_LOGIN_ATTEMPTS', 5);
define('LOCKOUT_MINUTES', 15);

// BASE_URL: relative path back to the app root, auto-detected from how deep
// the current script is nested under modules/, so sidebar links always work
// whether included from root-level pages or modules/<name>/<page>.php.
if (!defined('BASE_URL')) {
    $__scriptDir = str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME'] ?? ''));
    $__depth = (strpos($__scriptDir, '/modules/') !== false) ? 2 : 0;
    define('BASE_URL', str_repeat('../', $__depth));
}

require_once BASE_PATH . '/includes/functions.php';
require_once BASE_PATH . '/includes/auth.php';
