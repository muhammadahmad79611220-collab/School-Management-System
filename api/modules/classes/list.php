<?php
require_once __DIR__ . '/../../config/app.php';
require_role('admin');

$pdo = getDB();

$classes = $pdo->query(
    "SELECT c.*,
        (SELECT COUNT(*) FROM students s WHERE s.class_id = c.id AND s.status='Active') as student_count,
        (SELECT COUNT(*) FROM sections sec WHERE sec.class_id = c.id) as section_count
     FROM classes c ORDER BY c.sort_order"
)->fetchAll();

$sections = $pdo->query(
    "SELECT sec.*, c.class_name, t.full_name as teacher_name,
        (SELECT COUNT(*) FROM students s WHERE s.section_id = sec.id AND s.status='Active') as student_count
     FROM sections sec
     LEFT JOIN classes c ON sec.class_id = c.id
     LEFT JOIN teachers t ON sec.class_teacher_id = t.id
     ORDER BY c.sort_order, sec.section_name"
)->fetchAll();

$teachers = $pdo->query("SELECT id, full_name FROM teachers WHERE is_active=1 ORDER BY full_name")->fetchAll();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Classes &amp; Sections – <?php echo e(APP_NAME); ?></title>
    <link rel="stylesheet" href="<?php echo BASE_URL; ?>assets/css/style.css">
</head>
<body>
<?php include BASE_PATH . '/includes/sidebar.php'; ?>
<div class="content">

    <div class="card">
        <div class="card-header">
            <div class="card-title">🏷️ Classes</div>
            <button class="btn" onclick="document.getElementById('addClassForm').style.display='block'">➕ Add Class</button>
        </div>
        <?php echo flash_render(); ?>

        <form id="addClassForm" method="POST" action="class_save.php" style="display:none; margin-bottom:18px;" class="form-grid">
            <?php echo csrf_field(); ?>
            <div class="form-group">
                <label>Class Name</label>
                <input type="text" name="class_name" required maxlength="50" placeholder="e.g. Class 9">
            </div>
            <div class="form-group">
                <label>Sort Order</label>
                <input type="number" name="sort_order" value="0">
            </div>
            <div class="form-group" style="align-self:end;">
                <button type="submit" class="btn btn-success btn-block">Save Class</button>
            </div>
        </form>

        <div class="table-wrap">
        <table>
            <thead><tr><th>Class</th><th>Sections</th><th>Active Students</th><th>Actions</th></tr></thead>
            <tbody>
            <?php foreach ($classes as $c): ?>
                <tr>
                    <td><?php echo e($c['class_name']); ?></td>
                    <td><?php echo (int)$c['section_count']; ?></td>
                    <td><?php echo (int)$c['student_count']; ?></td>
                    <td>
                        <a href="class_delete.php?id=<?php echo $c['id']; ?>&csrf_token=<?php echo e(csrf_token()); ?>"
                           class="btn btn-sm btn-danger"
                           onclick="return confirm('Delete this class? Students/sections referencing it will be detached, not deleted.');">🗑️ Delete</a>
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
        </div>
    </div>

    <div class="card">
        <div class="card-header">
            <div class="card-title">📚 Sections</div>
            <button class="btn" onclick="document.getElementById('addSectionForm').style.display='block'">➕ Add Section</button>
        </div>

        <form id="addSectionForm" method="POST" action="section_save.php" style="display:none; margin-bottom:18px;" class="form-grid">
            <?php echo csrf_field(); ?>
            <div class="form-group">
                <label>Class</label>
                <select name="class_id" required>
                    <option value="">— Select —</option>
                    <?php foreach ($classes as $c): ?>
                        <option value="<?php echo $c['id']; ?>"><?php echo e($c['class_name']); ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="form-group">
                <label>Section Name</label>
                <input type="text" name="section_name" required maxlength="20" placeholder="e.g. A">
            </div>
            <div class="form-group">
                <label>Class Teacher</label>
                <select name="class_teacher_id">
                    <option value="">— None —</option>
                    <?php foreach ($teachers as $t): ?>
                        <option value="<?php echo $t['id']; ?>"><?php echo e($t['full_name']); ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="form-group" style="align-self:end;">
                <button type="submit" class="btn btn-success btn-block">Save Section</button>
            </div>
        </form>

        <div class="table-wrap">
        <table>
            <thead><tr><th>Class</th><th>Section</th><th>Class Teacher</th><th>Active Students</th><th>Actions</th></tr></thead>
            <tbody>
            <?php if (!$sections): ?>
                <tr><td colspan="5" style="text-align:center;color:#aaa;padding:20px;">No sections yet.</td></tr>
            <?php endif; ?>
            <?php foreach ($sections as $s): ?>
                <tr>
                    <td><?php echo e($s['class_name'] ?? '—'); ?></td>
                    <td><?php echo e($s['section_name']); ?></td>
                    <td><?php echo e($s['teacher_name'] ?? '— Unassigned —'); ?></td>
                    <td><?php echo (int)$s['student_count']; ?></td>
                    <td>
                        <a href="section_delete.php?id=<?php echo $s['id']; ?>&csrf_token=<?php echo e(csrf_token()); ?>"
                           class="btn btn-sm btn-danger"
                           onclick="return confirm('Delete this section? Students in it will need reassignment.');">🗑️ Delete</a>
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
