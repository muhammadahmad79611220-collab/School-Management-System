<?php
require_once __DIR__ . '/../../config/app.php';
require_perm('view', 'teachers');

$pdo = getDB();
$search = trim($_GET['search'] ?? '');

$where = [];
$params = [];
if ($search !== '') {
    $where[] = "(t.full_name LIKE ? OR t.teacher_code LIKE ? OR t.email LIKE ?)";
    $params = ["%$search%", "%$search%", "%$search%"];
}
$whereSql = $where ? 'WHERE ' . implode(' AND ', $where) : '';

$stmt = $pdo->prepare(
    "SELECT t.*,
        (SELECT COUNT(*) FROM sections sec WHERE sec.class_teacher_id = t.id) as section_count,
        u.username, u.is_active as account_active
     FROM teachers t
     LEFT JOIN users u ON u.teacher_id = t.id
     $whereSql
     ORDER BY t.full_name"
);
$stmt->execute($params);
$teachers = $stmt->fetchAll();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Teachers – <?php echo e(APP_NAME); ?></title>
    <link rel="stylesheet" href="<?php echo BASE_URL; ?>assets/css/style.css">
</head>
<body>
<?php include BASE_PATH . '/includes/sidebar.php'; ?>
<div class="content">
    <div class="card">
        <div class="card-header">
            <div class="card-title">👩‍🏫 Teaching Staff</div>
            <?php if (is_admin() || can('add','teachers')): ?><a href="form.php" class="btn">➕ Add Teacher</a><?php endif; ?>
        </div>

        <?php echo flash_render(); ?>

        <form method="GET" style="margin-bottom:16px; display:flex; gap:10px;">
            <input type="text" name="search" placeholder="Search by name, code, or email" value="<?php echo e($search); ?>" style="margin-bottom:0;">
            <button type="submit" class="btn">🔍</button>
        </form>

        <div class="table-wrap">
        <table>
            <thead><tr><th>Photo</th><th>Code</th><th>Name</th><th>Email</th><th>Phone</th><th>Login Account</th><th>Status</th><th>Actions</th></tr></thead>
            <tbody>
            <?php if (!$teachers): ?>
                <tr><td colspan="8" style="text-align:center;color:#aaa;padding:30px;">No teachers found.</td></tr>
            <?php endif; ?>
            <?php foreach ($teachers as $t): ?>
                <tr>
                    <td>
                        <?php if ($t['picture']): ?>
                            <img src="<?php echo BASE_URL; ?>assets/uploads/teachers/<?php echo e($t['picture']); ?>" style="width:36px;height:36px;border-radius:50%;object-fit:cover;">
                        <?php else: ?>
                            <span style="font-size:24px;">👤</span>
                        <?php endif; ?>
                    </td>
                    <td><?php echo e($t['teacher_code']); ?></td>
                    <td><?php echo e($t['full_name']); ?></td>
                    <td><?php echo e($t['email'] ?: '—'); ?></td>
                    <td><?php echo e($t['phone'] ?: '—'); ?></td>
                    <td>
                        <?php if ($t['username']): ?>
                            <span class="badge badge-info"><?php echo e($t['username']); ?></span>
                        <?php else: ?>
                            <a href="create_login.php?id=<?php echo $t['id']; ?>" class="btn btn-sm btn-warning">Create Login</a>
                        <?php endif; ?>
                    </td>
                    <td>
                        <span class="badge <?php echo $t['is_active'] ? 'badge-success' : 'badge-secondary'; ?>">
                            <?php echo $t['is_active'] ? 'Active' : 'Inactive'; ?>
                        </span>
                    </td>
                    <td>
                        <?php if (is_admin() || can('edit','teachers')): ?>
                            <a href="form.php?id=<?php echo $t['id']; ?>" class="btn btn-sm">✏️ Edit</a>
                        <?php endif; ?>
                        <?php if (is_admin() || can('delete','teachers')): ?>
                            <a href="delete.php?id=<?php echo $t['id']; ?>&csrf_token=<?php echo e(csrf_token()); ?>"
                               class="btn btn-sm btn-danger"
                               onclick="return confirm('Delete this teacher? Their login account will also be removed.');">🗑️</a>
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
