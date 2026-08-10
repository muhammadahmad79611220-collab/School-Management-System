<?php
require_once __DIR__ . '/config/app.php';

if (is_logged_in()) {
    redirect('dashboard.php');
}

$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verify();
    $username = trim($_POST['username'] ?? '');
    $password = (string)($_POST['password'] ?? '');

    if ($username === '' || $password === '') {
        flash('error', 'Please enter both username and password.');
    } elseif (attempt_login($username, $password)) {
        if (!empty($_SESSION['must_change_password'])) {
            redirect('change_password.php');
        }
        redirect('dashboard.php');
    }
}
?>
<?php
$settings = function_exists('get_settings') ? get_settings() : [];
$brandName = !empty($settings['school_name']) ? $settings['school_name'] : APP_NAME;
$brandLogo = !empty($settings['logo']) ? 'assets/uploads/branding/' . $settings['logo'] : null;
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login – <?php echo e($brandName); ?></title>
    <link rel="manifest" href="manifest.json">
    <meta name="theme-color" content="#1e3c72">
    <link rel="apple-touch-icon" href="assets/icons/icon-192.png">
    <link rel="icon" type="image/png" href="assets/icons/icon-192.png">
    <link rel="stylesheet" href="assets/css/style.css">
    <style>
        .auth-body { background: radial-gradient(circle at 20% 20%, #7b8ff5 0%, #667eea 35%, #5a3f9e 70%, #4b2e83 100%); position: relative; overflow: hidden; }
        .auth-body::before, .auth-body::after {
            content: ''; position: absolute; border-radius: 50%; background: rgba(255,255,255,0.07);
        }
        .auth-body::before { width: 420px; height: 420px; top: -120px; left: -120px; }
        .auth-body::after { width: 320px; height: 320px; bottom: -100px; right: -80px; }
        .login-card { position: relative; z-index: 1; }
        .login-logo {
            width: 78px; height: 78px; margin: 0 auto 14px; border-radius: 50%;
            background: linear-gradient(135deg,#1e3c72,#2a5298); display: flex; align-items: center;
            justify-content: center; font-size: 36px; color: white; overflow: hidden;
            box-shadow: 0 8px 22px rgba(30,60,114,0.35);
        }
        .login-logo img { width: 100%; height: 100%; object-fit: cover; }
    </style>
</head>
<body class="auth-body">
    <div class="login-card">
        <div class="login-logo">
            <?php if ($brandLogo): ?>
                <img src="<?php echo e($brandLogo); ?>" alt="logo">
            <?php else: ?>
                🏫
            <?php endif; ?>
        </div>
        <h2 style="text-align:center;"><?php echo e($brandName); ?></h2>
        <div class="subtitle">School Management System — Login</div>

        <?php echo flash_render(); ?>

        <form method="POST" autocomplete="off">
            <?php echo csrf_field(); ?>
            <input type="text" name="username" placeholder="Username" required autofocus maxlength="50">
            <input type="password" name="password" placeholder="Password" required maxlength="100">
            <button type="submit" class="btn btn-block">Login</button>
        </form>

        <div class="info">&copy; <?php echo date('Y'); ?> <?php echo e($brandName); ?></div>
    </div>
    <script>
        if ('serviceWorker' in navigator) {
            window.addEventListener('load', function () {
                navigator.serviceWorker.register('service-worker.js').catch(function (err) {
                    console.warn('Service worker registration failed:', err);
                });
            });
        }
    </script>
</body>
</html>
