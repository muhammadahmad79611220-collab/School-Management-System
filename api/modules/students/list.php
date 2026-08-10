<?php
require_once __DIR__ . '/../../config/app.php';
require_login();

$pdo = getDB();

// Filters
$search   = trim($_GET['search'] ?? '');
$classId  = clean_id($_GET['class_id'] ?? null);
$status   = $_GET['status'] ?? 'Active';

$where  = [];
$params = [];

if (!is_admin() && !can('view', 'students')) {
    require_role('admin'); // 403s with a clear message for roles without permission
}

if (current_role() === 'teacher') {
    // Teachers only see students in sections they are the class teacher of.
    $where[] = "sec.class_teacher_id = ?";
    $params[] = current_teacher_id();
}
// Other permissioned roles (e.g. Receptionist with 'students' view granted) see all students,
// same as admin — their access is controlled by the permission check above, not section scoping.

if ($search !== '') {
    $where[] = "(s.full_name LIKE ? OR s.roll_no LIKE ?)";
    $params[] = "%$search%";
    $params[] = "%$search%";
}
if ($classId) {
    $where[] = "s.class_id = ?";
    $params[] = $classId;
}
if ($status !== '' && $status !== 'All') {
    $where[] = "s.status = ?";
    $params[] = $status;
}

$whereSql = $where ? ('WHERE ' . implode(' AND ', $where)) : '';

$sql = "SELECT s.*, c.class_name, sec.section_name
        FROM students s
        LEFT JOIN classes c ON s.class_id = c.id
        LEFT JOIN sections sec ON s.section_id = sec.id
        $whereSql
        ORDER BY s.full_name ASC";
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$students = $stmt->fetchAll();

$classes = $pdo->query("SELECT id, class_name FROM classes ORDER BY sort_order")->fetchAll();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Students – <?php echo e(APP_NAME); ?></title>
    <link rel="stylesheet" href="<?php echo BASE_URL; ?>assets/css/style.css">
</head>
<body>
    <?php include BASE_PATH . '/includes/sidebar.php'; ?>
    <div class="content">
        <div class="card">
            <div class="card-header">
                <div class="card-title">👨‍🎓 Students</div>
                <div>
                    <a href="promote.php" class="btn btn-secondary">🎓 Promote Students</a>
                    <a href="form.php" class="btn">➕ Add Student</a>
                </div>
            </div>

            <?php echo flash_render(); ?>

            <form method="GET" class="form-grid" style="margin-bottom:16px;">
                <div class="form-group">
                    <input type="text" name="search" placeholder="Search by name or roll no" value="<?php echo e($search); ?>">
                </div>
                <div class="form-group">
                    <select name="class_id">
                        <option value="">All Classes</option>
                        <?php foreach ($classes as $c): ?>
                            <option value="<?php echo $c['id']; ?>" <?php echo $classId == $c['id'] ? 'selected' : ''; ?>>
                                <?php echo e($c['class_name']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="form-group">
                    <select name="status">
                        <option value="Active" <?php echo $status==='Active'?'selected':''; ?>>Active</option>
                        <option value="Inactive" <?php echo $status==='Inactive'?'selected':''; ?>>Inactive</option>
                        <option value="Graduated" <?php echo $status==='Graduated'?'selected':''; ?>>Graduated</option>
                        <option value="Transferred" <?php echo $status==='Transferred'?'selected':''; ?>>Transferred</option>
                        <option value="All" <?php echo $status==='All'?'selected':''; ?>>All Statuses</option>
                    </select>
                </div>
                <div class="form-group">
                    <button type="submit" class="btn btn-block">🔍 Filter</button>
                </div>
            </form>

            <div class="table-wrap">
            <table>
                <thead>
                    <tr>
                        <th>Photo</th><th>Roll No</th><th>Name</th><th>Class</th><th>Section</th>
                        <th>Guardian</th><th>Status</th><th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                <?php if (!$students): ?>
                    <tr><td colspan="8" style="text-align:center;color:#aaa;padding:30px;">No students found.</td></tr>
                <?php endif; ?>
                <?php foreach ($students as $s): ?>
                    <tr>
                        <td>
                            <?php if ($s['picture']): ?>
                                <img src="<?php echo BASE_URL; ?><?php echo e(UPLOAD_URL . '/' . $s['picture']); ?>" style="width:36px;height:36px;border-radius:50%;object-fit:cover;">
                            <?php else: ?>
                                <span style="font-size:24px;">👤</span>
                            <?php endif; ?>
                        </td>
                        <td><?php echo e($s['roll_no']); ?></td>
                        <td><?php echo e($s['full_name']); ?></td>
                        <td><?php echo e($s['class_name'] ?? '—'); ?></td>
                        <td><?php echo e($s['section_name'] ?? '—'); ?></td>
                        <td><?php echo e($s['guardian_name'] ?: '—'); ?></td>
                        <td>
                            <?php
                                $badgeMap = ['Active'=>'badge-success','Inactive'=>'badge-secondary','Graduated'=>'badge-info','Transferred'=>'badge-warning'];
                                $cls = $badgeMap[$s['status']] ?? 'badge-secondary';
                            ?>
                            <span class="badge <?php echo $cls; ?>"><?php echo e($s['status']); ?></span>
                        </td>
                        <td>
                            <a href="view.php?id=<?php echo $s['id']; ?>" class="btn btn-sm btn-secondary">👁️ View</a>
                            <a href="form.php?id=<?php echo $s['id']; ?>" class="btn btn-sm">✏️ Edit</a>
                            <?php if (is_admin() || can('delete', 'students')): ?>
                            <a href="delete.php?id=<?php echo $s['id']; ?>&csrf_token=<?php echo e(csrf_token()); ?>"
                               class="btn btn-sm btn-danger"
                               onclick="return confirm('Delete this student permanently? This cannot be undone.');">🗑️ Delete</a>
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
