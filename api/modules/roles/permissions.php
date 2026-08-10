<?php
require_once __DIR__ . '/../../config/app.php';
require_role('admin');

$pdo = getDB();
$roleId = clean_id($_GET['role_id'] ?? null);
if (!$roleId) redirect('list.php');

$stmt = $pdo->prepare("SELECT * FROM roles WHERE id = ?");
$stmt->execute([$roleId]);
$role = $stmt->fetch();
if (!$role || $role['role_key'] === 'admin') {
    flash('error', 'Invalid role.');
    redirect('list.php');
}

// The modules available in this system that permissions can be scoped to.
$modules = [
    'students'  => 'Students',
    'teachers'  => 'Teachers',
    'classes'   => 'Classes & Sections',
    'courses'   => 'Subjects',
    'attendance'=> 'Attendance',
    'exams'     => 'Exams & Results',
    'fees'      => 'Fee Management',
    'timetable' => 'Timetable',
    'notices'   => 'Notices',
    'certificates' => 'Certificates',
    'idcards'   => 'ID Cards',
    'reports'   => 'Reports & Analytics',
    'salary'    => 'Salary (view-only; payments remain admin-only)',
];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verify();
    $perms = $_POST['perm'] ?? [];

    $stmtUp = $pdo->prepare(
        "INSERT INTO role_permissions (role_id, module_key, can_view, can_add, can_edit, can_delete)
         VALUES (?,?,?,?,?,?)
         ON DUPLICATE KEY UPDATE can_view=VALUES(can_view), can_add=VALUES(can_add), can_edit=VALUES(can_edit), can_delete=VALUES(can_delete)"
    );

    foreach ($modules as $key => $label) {
        $p = $perms[$key] ?? [];
        $stmtUp->execute([
            $roleId, $key,
            isset($p['view']) ? 1 : 0,
            isset($p['add']) ? 1 : 0,
            isset($p['edit']) ? 1 : 0,
            isset($p['delete']) ? 1 : 0,
        ]);
    }

    log_activity('permissions_updated', "role_id=$roleId");
    flash('success', 'Permissions updated for ' . $role['role_label'] . '.');
    redirect("permissions.php?role_id=$roleId");
}

$existing = $pdo->prepare("SELECT * FROM role_permissions WHERE role_id = ?");
$existing->execute([$roleId]);
$existingMap = [];
foreach ($existing->fetchAll() as $row) {
    $existingMap[$row['module_key']] = $row;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Permissions: <?php echo e($role['role_label']); ?> – <?php echo e(APP_NAME); ?></title>
    <link rel="stylesheet" href="<?php echo BASE_URL; ?>assets/css/style.css">
    <style>
        .perm-table th, .perm-table td { text-align: center; }
        .perm-table td:first-child, .perm-table th:first-child { text-align: left; }
        .perm-table input[type=checkbox] { width: auto; margin: 0; }
    </style>
</head>
<body>
<?php include BASE_PATH . '/includes/sidebar.php'; ?>
<div class="content">
    <div class="card">
        <div class="card-header">
            <div class="card-title">🔐 Permissions: <?php echo e($role['role_label']); ?></div>
            <a href="list.php" class="btn btn-secondary">← Back to Roles</a>
        </div>

        <?php echo flash_render(); ?>

        <form method="POST">
            <?php echo csrf_field(); ?>
            <div class="table-wrap">
            <table class="perm-table">
                <thead><tr><th>Module</th><th>View</th><th>Add</th><th>Edit</th><th>Delete</th></tr></thead>
                <tbody>
                <?php foreach ($modules as $key => $label): ?>
                    <?php $ex = $existingMap[$key] ?? ['can_view'=>0,'can_add'=>0,'can_edit'=>0,'can_delete'=>0]; ?>
                    <tr>
                        <td><?php echo e($label); ?></td>
                        <td><input type="checkbox" name="perm[<?php echo $key; ?>][view]" <?php echo $ex['can_view'] ? 'checked' : ''; ?>></td>
                        <td><input type="checkbox" name="perm[<?php echo $key; ?>][add]" <?php echo $ex['can_add'] ? 'checked' : ''; ?>></td>
                        <td><input type="checkbox" name="perm[<?php echo $key; ?>][edit]" <?php echo $ex['can_edit'] ? 'checked' : ''; ?>></td>
                        <td><input type="checkbox" name="perm[<?php echo $key; ?>][delete]" <?php echo $ex['can_delete'] ? 'checked' : ''; ?>></td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
            </div>
            <p style="font-size:13px;color:#888;margin:14px 0;">
                Note: "View" is required for Add/Edit/Delete to take effect in most module pages — make sure to check View alongside any other permission.
            </p>
            <button type="submit" class="btn" style="margin-top:10px;">💾 Save Permissions</button>
        </form>
    </div>
</div>
</body>
</html>
