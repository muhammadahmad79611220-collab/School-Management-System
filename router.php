<?php
/**
 * api/router.php
 *
 * Why this exists: Vercel's Hobby (free) plan allows a maximum of 12
 * Serverless Functions per deployment. This app has 65+ PHP page files
 * (dashboard, modules/students/*, modules/fees/*, etc.) — registering each
 * one as its own Vercel Function would blow way past that limit and the
 * deployment would fail.
 *
 * Instead, this ONE file is the only registered Vercel Function. Every
 * request gets rewritten to this file (see vercel.json), which then loads
 * the real target page internally. To the browser, URLs look completely
 * unchanged (/dashboard.php, /modules/students/list.php, etc.) — only the
 * routing happens behind the scenes.
 */

$path = isset($_GET['__path']) ? $_GET['__path'] : 'index.php';

// Sanitize: no path traversal, must resolve to a .php file inside this folder.
$path = str_replace(['\\', '..'], '', $path);
$path = ltrim($path, '/');
if ($path === '' || substr($path, -4) !== '.php') {
    $path = 'index.php';
}

// Never allow directly executing internal-only files through routing.
$blocked = ['config/', 'includes/'];
foreach ($blocked as $prefix) {
    if (strpos($path, $prefix) === 0) {
        http_response_code(404);
        echo 'Not found';
        exit;
    }
}

$target = __DIR__ . '/' . $path;

if (!is_file($target)) {
    http_response_code(404);
    echo '404 - Page not found';
    exit;
}

// Make the target page behave exactly as if it had been requested directly
// — needed because config/app.php calculates BASE_URL from SCRIPT_NAME.
$_SERVER['SCRIPT_NAME'] = '/' . $path;
$_SERVER['SCRIPT_FILENAME'] = $target;
$_SERVER['PHP_SELF'] = '/' . $path;

chdir(__DIR__);
require $target;
