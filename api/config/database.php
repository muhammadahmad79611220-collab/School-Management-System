<?php
/**
 * config/database.php
 * Central database connection using PDO (prepared statements throughout the app).
 * Edit the constants below to match your environment.
 */

// On Vercel, set these as Environment Variables in the project dashboard
// (Settings → Environment Variables). Locally on XAMPP, nothing needs to
// change — it falls back to the values after the ?: on each line.
define('DB_HOST', getenv('DB_HOST') ?: '127.0.0.1');
define('DB_PORT', getenv('DB_PORT') ?: '4306');
define('DB_NAME', getenv('DB_NAME') ?: 'sms_db');
define('DB_USER', getenv('DB_USER') ?: 'root');
define('DB_PASS', getenv('DB_PASS') ?: '');

function getDB(): PDO {
    static $pdo = null;
    if ($pdo === null) {
        $dsn = "mysql:host=" . DB_HOST . ";port=" . DB_PORT . ";dbname=" . DB_NAME . ";charset=utf8mb4";
        try {
            $pdo = new PDO($dsn, DB_USER, DB_PASS, [
                PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES   => false,
            ]);
        } catch (PDOException $e) {
            // Never leak DB credentials or raw exception details to the browser.
            error_log('DB connection failed: ' . $e->getMessage());
            http_response_code(500);
            die('A database connection error occurred. Please contact the system administrator.');
        }
    }
    return $pdo;
}
