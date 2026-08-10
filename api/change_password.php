<?php
require_once __DIR__ . '/config/app.php';
require_login();

$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verify();
    $current = $_POST['current_password'] ?? '';
    $new = $_POST['new_password'] ?? '';
    $confirm = $_POST['confirm_password'] ?? '';

    $pdo = getDB();
    $stmt = $pdo->prepare("SELECT password FROM users WHERE id = ?");
    $stmt->execute([$_SESSION['user_id']]);
    $row = $stmt->fetch();

    if (!$row || !password_verify($current, $row['password'])) {
        flash('error', 'Current password is incorrect.');
    } elseif (strlen($new) < 8) {
        flash('error', 'New password must be at least 8 characters.');
    } elseif ($new !== $confirm) {
        flash('error', 'New password and confirmation do not match.');
    } elseif ($new === $current) {
        flash('error', 'New password must be different from the current password.');
    } else {
        $hash = password_hash($new, PASSWORD_DEFAULT);
        $pdo->prepare("UPDATE users SET password = ?, must_change_password = 0 WHERE id = ?")
            ->execute([$hash, $_SESSION['user_id']]);
        $_SESSION['must_change_password'] = false;
        log_activity('password_changed');
        flash('success', 'Password updated successfully.');
        redirect('dashboard.php');
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Change Password – <?php echo e(APP_NAME); ?></title>
    <link rel="stylesheet" href="assets/css/style.css">
</head>
<body class="auth-body">
    <div class="login-card">
        <h2>🔑 Change Password</h2>
        <div class="subtitle">
            <?php if (!empty($_SESSION['must_change_password'])): ?>
                For security, you must set a new password before continuing.
            <?php else: ?>
                Update your account password.
            <?php endif; ?>
        </div>

        <?php echo flash_render(); ?>

        <form method="POST" autocomplete="off">
            <?php echo csrf_field(); ?>
            <input type="password" name="current_password" placeholder="Current Password" required>
            <input type="password" name="new_password" placeholder="New Password (min 8 characters)" required minlength="8">
            <input type="password" name="confirm_password" placeholder="Confirm New Password" required minlength="8">
            <button type="submit" class="btn btn-block">Update Password</button>
        </form>
        <?php if (empty($_SESSION['must_change_password'])): ?>
            <div class="info"><a href="dashboard.php">← Back to dashboard</a></div>
        <?php endif; ?>
    </div>
</body>
</html>
