<?php
require_once __DIR__ . '/../../config/app.php';
require_role('admin');

$pdo = getDB();
$courses = $pdo->query(
    "SELECT c.*, cl.class_name
     FROM courses c LEFT JOIN classes cl ON c.class_id = cl.id
     ORDER BY cl.sort_order, c.course_name"
)->fetchAll();
$classes = $pdo->query("SELECT id, class_name FROM classes ORDER BY sort_order")->fetchAll();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Subjects – <?php echo e(APP_NAME); ?></title>
    <link rel="stylesheet" href="<?php echo BASE_URL; ?>assets/css/style.css">
</head>
<body>
<?php include BASE_PATH . '/includes/sidebar.php'; ?>
<div class="content">
    <div class="card">
        <div class="card-header">
            <div class="card-title">📖 Subjects</div>
            <button class="btn" onclick="document.getElementById('addForm').style.display='block'">➕ Add Subject</button>
        </div>

        <?php echo flash_render(); ?>

        <form id="addForm" method="POST" action="save.php" style="display:none;margin-bottom:18px;" class="form-grid">
            <?php echo csrf_field(); ?>
            <div class="form-group">
                <label>Subject Code</label>
                <input type="text" name="course_code" required maxlength="20" placeholder="e.g. MATH9">
            </div>
            <div class="form-group">
                <label>Subject Name</label>
                <input type="text" name="course_name" required maxlength="100" placeholder="e.g. Mathematics">
            </div>
            <div class="form-group">
                <label>Class</label>
                <select name="class_id">
                    <option value="">— Any / All Classes —</option>
                    <?php foreach ($classes as $c): ?>
                        <option value="<?php echo $c['id']; ?>"><?php echo e($c['class_name']); ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="form-group">
                <label>Type</label>
                <select name="course_type">
                    <option value="Compulsory">Compulsory</option>
                    <option value="Elective">Elective</option>
                </select>
            </div>
            <div class="form-group">
                <label>Credits</label>
                <input type="number" name="credits" value="0" min="0">
            </div>
            <div class="form-group" style="align-self:end;">
                <button type="submit" class="btn btn-success btn-block">Save Subject</button>
            </div>
        </form>

        <div class="table-wrap">
        <table>
            <thead><tr><th>Code</th><th>Name</th><th>Class</th><th>Type</th><th>Credits</th><th>Actions</th></tr></thead>
            <tbody>
            <?php if (!$courses): ?>
                <tr><td colspan="6" style="text-align:center;color:#aaa;padding:20px;">No subjects yet.</td></tr>
            <?php endif; ?>
            <?php foreach ($courses as $c): ?>
                <tr>
                    <td><?php echo e($c['course_code']); ?></td>
                    <td><?php echo e($c['course_name']); ?></td>
                    <td><?php echo e($c['class_name'] ?? 'All Classes'); ?></td>
                    <td><span class="badge <?php echo $c['course_type']==='Elective'?'badge-warning':'badge-info'; ?>"><?php echo e($c['course_type']); ?></span></td>
                    <td><?php echo (int)$c['credits']; ?></td>
                    <td>
                        <a href="delete.php?id=<?php echo $c['id']; ?>&csrf_token=<?php echo e(csrf_token()); ?>"
                           class="btn btn-sm btn-danger"
                           onclick="return confirm('Delete this subject?');">🗑️</a>
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
