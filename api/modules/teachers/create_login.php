<?php
require_once __DIR__ . '/../../config/app.php';
require_role('admin');

$pdo = getDB();
$id = clean_id($_GET['id'] ?? null);
if (!$id) redirect('list.php');

$stmt = $pdo->prepare("SELECT * FROM teachers WHERE id = ?");
$stmt->execute([$id]);
$teacher = $stmt->fetch();
if (!$teacher) {
    flash('error', 'Teacher not found.');
    redirect('list.php');
}

// Already has an account?
$existing = $pdo->prepare("SELECT id FROM users WHERE teacher_id = ?");
$existing->execute([$id]);
if ($existing->fetch()) {
    flash('error', 'This teacher already has a login account.');
    redirect('list.php');
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verify();
    $username = trim($_POST['username'] ?? '');

    if ($username === '') {
        flash('error', 'Username is required.');
    } else {
        $dup = $pdo->prepare("SELECT id FROM users WHERE username = ?");
        $dup->execute([$username]);
        if ($dup->fetch()) {
            flash('error', 'That username is already taken.');
        } else {
            // Generate a random temporary password and force a change on first login.
            $tempPassword = bin2hex(random_bytes(6)); // 12 hex chars
            $hash = password_hash($tempPassword, PASSWORD_DEFAULT);
            $pdo->prepare(
                "INSERT INTO users (username, password, role, full_name, teacher_id, must_change_password)
                 VALUES (?, ?, 'teacher', ?, ?, 1)"
            )->execute([$username, $hash, $teacher['full_name'], $id]);

            log_activity('teacher_login_created', "teacher_id=$id username=$username");
            flash('success', "Login created! Username: {$username} — Temporary password: {$tempPassword} (share this securely; they must change it on first login).");
            redirect('list.php');
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Create Login – <?php echo e(APP_NAME); ?></title>
    <link rel="stylesheet" href="<?php echo BASE_URL; ?>assets/css/style.css">
</head>
<body>
<?php include BASE_PATH . '/includes/sidebar.php'; ?>
<div class="content">
    <div class="card" style="max-width:480px;">
        <div class="card-title" style="margin-bottom:18px;">🔑 Create Login for <?php echo e($teacher['full_name']); ?></div>
        <?php echo flash_render(); ?>
        <form method="POST">
            <?php echo csrf_field(); ?>
            <label>Username</label>
            <input type="text" name="username" required maxlength="50" value="<?php echo e(strtolower(str_replace(' ', '.', $teacher['full_name']))); ?>">
            <p style="font-size:13px;color:#888;margin-bottom:14px;">A secure random temporary password will be generated and shown once. The teacher must change it on first login.</p>
            <button type="submit" class="btn">Create Account</button>
            <a href="list.php" class="btn btn-secondary">Cancel</a>
        </form>
    </div>
</div>
</body>
</html>
