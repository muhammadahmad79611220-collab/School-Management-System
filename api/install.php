<?php
/**
 * install.php
 * ONE-TIME setup script. Run this once, then DELETE this file from your server.
 * It will refuse to run again automatically if an admin user already exists,
 * but you should still delete it — leaving installers on a live server is a
 * common way real school sites get breached.
 */

// Same env-var-first approach as config/database.php, so this works
// correctly whether you're on XAMPP (falls back to local defaults) or on
// Vercel (reads the DB_HOST/DB_PORT/DB_NAME/DB_USER/DB_PASS values you set
// in the project's Environment Variables).
define('DB_HOST', getenv('DB_HOST') ?: '127.0.0.1');
define('DB_PORT', getenv('DB_PORT') ?: '4306');
define('DB_NAME', getenv('DB_NAME') ?: 'sms_db');
define('DB_USER', getenv('DB_USER') ?: 'root');
define('DB_PASS', getenv('DB_PASS') ?: '');

// A one-time setup key. Change this before running, then nobody can trigger
// the installer just by visiting the URL.
define('INSTALL_KEY', 'change-this-key-before-running-12345');

$providedKey = $_GET['key'] ?? $_POST['key'] ?? '';
$keyOk = hash_equals(INSTALL_KEY, $providedKey);

$step = $_POST['step'] ?? '';
$message = '';
$messageType = 'info';

function pdo_connect_noselect() {
    return new PDO("mysql:host=" . DB_HOST . ";port=" . DB_PORT . ";charset=utf8mb4", DB_USER, DB_PASS, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
    ]);
}

/**
 * Split a .sql file into individual executable statements.
 * Strips full-line "--" comments first (so they can never desync the
 * split), then splits on semicolons that end a statement. This is more
 * reliable than splitting on ";\n" alone, which breaks if a statement's
 * closing semicolon isn't immediately followed by a newline with no
 * trailing whitespace/comment — exactly what caused tables to be skipped.
 */
function split_sql_statements(string $sql): array {
    $lines = explode("\n", $sql);
    $cleaned = [];
    foreach ($lines as $line) {
        $trimmed = ltrim($line);
        if (str_starts_with($trimmed, '--')) {
            continue; // drop full-line comments entirely
        }
        $cleaned[] = $line;
    }
    $sql = implode("\n", $cleaned);

    $statements = [];
    foreach (explode(';', $sql) as $piece) {
        $piece = trim($piece);
        if ($piece !== '') {
            $statements[] = $piece;
        }
    }
    return $statements;
}


if ($keyOk && $step === 'install' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $pdo = pdo_connect_noselect();
        $pdo->exec("CREATE DATABASE IF NOT EXISTS `" . DB_NAME . "` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
        $pdo->exec("USE `" . DB_NAME . "`");

        // Refuse to re-run if an admin already exists.
        $exists = false;
        try {
            $check = $pdo->query("SELECT COUNT(*) c FROM users WHERE role='admin'");
            $exists = $check && (int)$check->fetch()['c'] > 0;
        } catch (Throwable $e) {
            $exists = false; // table doesn't exist yet — fine, first run
        }

        if ($exists) {
            $message = 'Setup has already been completed (an admin account exists). For safety, delete install.php now.';
            $messageType = 'error';
        } else {
            $sql = file_get_contents(__DIR__ . '/database.sql');
            foreach (split_sql_statements($sql) as $stmt) {
                $pdo->exec($stmt);
            }

            $adminUser = trim($_POST['admin_username'] ?? 'admin');
            $adminName = trim($_POST['admin_name'] ?? 'Administrator');
            $adminPass = $_POST['admin_password'] ?? '';

            if (strlen($adminPass) < 8) {
                throw new Exception('Admin password must be at least 8 characters.');
            }

            $hash = password_hash($adminPass, PASSWORD_DEFAULT);
            $stmt = $pdo->prepare(
                "INSERT INTO users (username, password, role, full_name, must_change_password) VALUES (?, ?, 'admin', ?, 0)"
            );
            $stmt->execute([$adminUser, $hash, $adminName]);

            $message = "✅ Setup complete! Admin account '{$adminUser}' created. DELETE install.php right now, then go to the login page.";
            $messageType = 'success';
        }
    } catch (Throwable $e) {
        $message = 'Setup failed: ' . $e->getMessage();
        $messageType = 'error';
    }
}
?>
<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>SMS Installer</title>
    <style>
        body { font-family: 'Segoe UI', sans-serif; max-width: 560px; margin: 60px auto; padding: 0 20px; color: #222; }
        .box { background: #f8f9fa; border-radius: 12px; padding: 28px; box-shadow: 0 4px 16px rgba(0,0,0,0.08); }
        input { width: 100%; padding: 10px; margin-bottom: 12px; border: 1px solid #ccc; border-radius: 6px; box-sizing: border-box; }
        button { padding: 10px 20px; background: #1e3c72; color: white; border: none; border-radius: 6px; cursor: pointer; }
        .msg-success { background: #e8f5e9; color: #1b5e20; padding: 12px; border-radius: 8px; margin-bottom: 16px; }
        .msg-error { background: #ffebee; color: #b71c1c; padding: 12px; border-radius: 8px; margin-bottom: 16px; }
        .warn { background: #fff3cd; color: #664d03; padding: 12px; border-radius: 8px; margin-bottom: 16px; font-size: 14px; }
    </style>
</head>
<body>
<h2>🏫 School Management System — Installer</h2>

<?php if ($message): ?>
    <div class="msg-<?php echo $messageType; ?>"><?php echo htmlspecialchars($message); ?></div>
<?php endif; ?>

<?php if (!$keyOk): ?>
    <div class="warn">
        Add <code>?key=YOUR_INSTALL_KEY</code> to the URL, matching the <code>INSTALL_KEY</code>
        constant you set at the top of this file, to proceed. This prevents random visitors
        from running the installer.
    </div>
<?php else: ?>
    <div class="box">
        <p class="warn">⚠️ This creates the database and your admin account. Run it once, then delete this file.</p>
        <form method="POST">
            <input type="hidden" name="step" value="install">
            <input type="hidden" name="key" value="<?php echo htmlspecialchars($providedKey); ?>">
            <label>Admin username</label>
            <input type="text" name="admin_username" value="admin" required>
            <label>Admin full name</label>
            <input type="text" name="admin_name" value="Administrator" required>
            <label>Admin password (min 8 characters, choose a strong one)</label>
            <input type="password" name="admin_password" required minlength="8">
            <button type="submit">Run Setup</button>
        </form>
    </div>
<?php endif; ?>
</body>
</html>
