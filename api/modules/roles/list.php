<?php
require_once __DIR__ . '/../../config/app.php';
require_role('admin');

$pdo = getDB();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verify();
    $label = trim($_POST['role_label'] ?? '');
    if ($label === '') {
        flash('error', 'Role name is required.');
    } else {
        $key = strtolower(preg_replace('/[^a-zA-Z0-9]+/', '_', $label));
        $key = trim($key, '_');
        if ($key === '') {
            flash('error', 'Please use a valid role name (letters/numbers).');
        } else {
            try {
                $pdo->prepare("INSERT INTO roles (role_key, role_label, is_system) VALUES (?, ?, 0)")
                    ->execute([$key, $label]);
                log_activity('role_created', "key=$key");
                flash('success', "Role '$label' created. Now set its permissions.");
            } catch (PDOException $e) {
                flash('error', $e->getCode() === '23000' ? 'A role with that name already exists.' : 'Could not create role.');
            }
        }
    }
    redirect('list.php');
}

$roles = $pdo->query(
    "SELECT r.*, (SELECT COUNT(*) FROM users u WHERE u.role = r.role_key) as user_count
     FROM roles r ORDER BY r.is_system DESC, r.role_label"
)->fetchAll();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Roles &amp; Permissions – <?php echo e(APP_NAME); ?></title>
    <link rel="stylesheet" href="<?php echo BASE_URL; ?>assets/css/style.css">
</head>
<body>
<?php include BASE_PATH . '/includes/sidebar.php'; ?>
<div class="content">
    <div class="card">
        <div class="card-header">
            <div class="card-title">🛡️ Roles &amp; Permissions</div>
            <button class="btn" onclick="document.getElementById('addForm').style.display='block'">➕ Add Role</button>
        </div>

        <?php echo flash_render(); ?>

        <form id="addForm" method="POST" style="display:none;margin-bottom:18px;display:flex;gap:10px;">
            <?php echo csrf_field(); ?>
            <input type="text" name="role_label" placeholder="e.g. Accountant, Librarian, Receptionist" required maxlength="50" style="margin-bottom:0;">
            <button type="submit" class="btn btn-success">Create Role</button>
        </form>

        <div class="table-wrap">
        <table>
            <thead><tr><th>Role</th><th>Type</th><th>Users</th><th>Actions</th></tr></thead>
            <tbody>
            <?php foreach ($roles as $r): ?>
                <tr>
                    <td><?php echo e($r['role_label']); ?></td>
                    <td><span class="badge <?php echo $r['is_system'] ? 'badge-info' : 'badge-secondary'; ?>"><?php echo $r['is_system'] ? 'System' : 'Custom'; ?></span></td>
                    <td><?php echo (int)$r['user_count']; ?></td>
                    <td>
                        <?php if ($r['role_key'] !== 'admin'): ?>
                            <a href="permissions.php?role_id=<?php echo $r['id']; ?>" class="btn btn-sm">🔐 Set Permissions</a>
                        <?php else: ?>
                            <span style="color:#aaa;font-size:12px;">Full access always</span>
                        <?php endif; ?>
                        <?php if (!$r['is_system']): ?>
                            <a href="delete.php?id=<?php echo $r['id']; ?>&csrf_token=<?php echo e(csrf_token()); ?>"
                               class="btn btn-sm btn-danger" onclick="return confirm('Delete this role? Users with this role will lose access until reassigned.');">🗑️</a>
                        <?php endif; ?>
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
        </div>
    </div>
</div>
</body>
</html>
