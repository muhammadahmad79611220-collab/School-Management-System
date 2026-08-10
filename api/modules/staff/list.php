<?php
require_once __DIR__ . '/../../config/app.php';
require_role('admin');

$pdo = getDB();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verify();
    $username = trim($_POST['username'] ?? '');
    $fullName = trim($_POST['full_name'] ?? '');
    $roleKey = trim($_POST['role'] ?? '');

    $validRoles = $pdo->query("SELECT role_key FROM roles WHERE role_key != 'admin' AND role_key != 'teacher'")->fetchAll(PDO::FETCH_COLUMN);

    if ($username === '' || $fullName === '' || !in_array($roleKey, $validRoles, true)) {
        flash('error', 'Please fill in all fields with a valid role.');
    } else {
        $dup = $pdo->prepare("SELECT id FROM users WHERE username = ?");
        $dup->execute([$username]);
        if ($dup->fetch()) {
            flash('error', 'That username is already taken.');
        } else {
            $tempPassword = bin2hex(random_bytes(6));
            $hash = password_hash($tempPassword, PASSWORD_DEFAULT);
            $pdo->prepare(
                "INSERT INTO users (username, password, role, full_name, must_change_password) VALUES (?,?,?,?,1)"
            )->execute([$username, $hash, $roleKey, $fullName]);

            log_activity('staff_account_created', "username=$username role=$roleKey");
            flash('success', "Account created! Username: {$username} — Temporary password: {$tempPassword} (share securely; must be changed on first login).");
            redirect('list.php');
        }
    }
}

$staff = $pdo->query(
    "SELECT u.*, r.role_label FROM users u
     LEFT JOIN roles r ON r.role_key = u.role
     WHERE u.role NOT IN ('admin','teacher')
     ORDER BY u.full_name"
)->fetchAll();

$customRoles = $pdo->query("SELECT role_key, role_label FROM roles WHERE role_key NOT IN ('admin','teacher') ORDER BY role_label")->fetchAll();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Staff Accounts – <?php echo e(APP_NAME); ?></title>
    <link rel="stylesheet" href="<?php echo BASE_URL; ?>assets/css/style.css">
</head>
<body>
<?php include BASE_PATH . '/includes/sidebar.php'; ?>
<div class="content">
    <div class="card">
        <div class="card-header">
            <div class="card-title">👥 Staff Accounts</div>
            <button class="btn" onclick="document.getElementById('addForm').style.display='block'">➕ Add Staff Account</button>
        </div>

        <?php echo flash_render(); ?>

        <?php if (!$customRoles): ?>
            <p style="color:#888;">No custom roles available yet. Create one first under <a href="<?php echo BASE_URL; ?>modules/roles/list.php">Roles &amp; Permissions</a>.</p>
        <?php else: ?>
        <form id="addForm" method="POST" style="display:none;margin-bottom:18px;" class="form-grid">
            <?php echo csrf_field(); ?>
            <div class="form-group">
                <label>Full Name</label>
                <input type="text" name="full_name" required maxlength="100">
            </div>
            <div class="form-group">
                <label>Username</label>
                <input type="text" name="username" required maxlength="50">
            </div>
            <div class="form-group">
                <label>Role</label>
                <select name="role" required>
                    <option value="">— Select —</option>
                    <?php foreach ($customRoles as $r): ?>
                        <option value="<?php echo e($r['role_key']); ?>"><?php echo e($r['role_label']); ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="form-group" style="align-self:end;">
                <button type="submit" class="btn btn-success btn-block">Create Account</button>
            </div>
        </form>
        <?php endif; ?>

        <div class="table-wrap">
        <table>
            <thead><tr><th>Name</th><th>Username</th><th>Role</th><th>Status</th><th>Last Login</th></tr></thead>
            <tbody>
            <?php if (!$staff): ?>
                <tr><td colspan="5" style="text-align:center;color:#aaa;padding:20px;">No staff accounts yet.</td></tr>
            <?php endif; ?>
            <?php foreach ($staff as $s): ?>
                <tr>
                    <td><?php echo e($s['full_name']); ?></td>
                    <td><?php echo e($s['username']); ?></td>
                    <td><span class="badge badge-info"><?php echo e($s['role_label'] ?? $s['role']); ?></span></td>
                    <td><span class="badge <?php echo $s['is_active'] ? 'badge-success' : 'badge-secondary'; ?>"><?php echo $s['is_active'] ? 'Active' : 'Inactive'; ?></span></td>
                    <td><?php echo $s['last_login'] ? date('d M Y, H:i', strtotime($s['last_login'])) : 'Never'; ?></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
        </div>
    </div>
</div>
</body>
</html>
