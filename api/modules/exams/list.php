<?php
require_once __DIR__ . '/../../config/app.php';
require_login();

$pdo = getDB();
$classes = $pdo->query("SELECT id, class_name FROM classes ORDER BY sort_order")->fetchAll();

$exams = $pdo->query(
    "SELECT e.*, c.class_name,
        (SELECT COUNT(*) FROM exam_subjects es WHERE es.exam_id = e.id) as subject_count
     FROM exams e LEFT JOIN classes c ON e.class_id = c.id
     ORDER BY e.exam_date DESC, e.id DESC"
)->fetchAll();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Exams – <?php echo e(APP_NAME); ?></title>
    <link rel="stylesheet" href="<?php echo BASE_URL; ?>assets/css/style.css">
</head>
<body>
<?php include BASE_PATH . '/includes/sidebar.php'; ?>
<div class="content">
    <div class="card">
        <div class="card-header">
            <div class="card-title">📝 Exams</div>
            <?php if (is_admin()): ?>
                <button class="btn" onclick="document.getElementById('addForm').style.display='block'">➕ Create Exam</button>
            <?php endif; ?>
        </div>

        <?php echo flash_render(); ?>

        <?php if (is_admin()): ?>
        <form id="addForm" method="POST" action="save_exam.php" style="display:none;margin-bottom:18px;" class="form-grid">
            <?php echo csrf_field(); ?>
            <div class="form-group">
                <label>Exam Name</label>
                <input type="text" name="exam_name" required maxlength="100" placeholder="e.g. Mid Term 2026">
            </div>
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
                <label>Exam Date</label>
                <input type="date" name="exam_date">
            </div>
            <div class="form-group">
                <label>Academic Year</label>
                <input type="text" name="academic_year" placeholder="e.g. 2025-2026" maxlength="20">
            </div>
            <div class="form-group" style="align-self:end;">
                <button type="submit" class="btn btn-success btn-block">Create Exam</button>
            </div>
        </form>
        <?php endif; ?>

        <div class="table-wrap">
        <table>
            <thead><tr><th>Exam Name</th><th>Class</th><th>Date</th><th>Year</th><th>Subjects</th><th>Actions</th></tr></thead>
            <tbody>
            <?php if (!$exams): ?>
                <tr><td colspan="6" style="text-align:center;color:#aaa;padding:20px;">No exams yet.</td></tr>
            <?php endif; ?>
            <?php foreach ($exams as $ex): ?>
                <tr>
                    <td><?php echo e($ex['exam_name']); ?></td>
                    <td><?php echo e($ex['class_name'] ?? '—'); ?></td>
                    <td><?php echo $ex['exam_date'] ? date('d M Y', strtotime($ex['exam_date'])) : '—'; ?></td>
                    <td><?php echo e($ex['academic_year'] ?: '—'); ?></td>
                    <td><?php echo (int)$ex['subject_count']; ?></td>
                    <td>
                        <?php if (is_admin()): ?>
                            <a href="setup_subjects.php?exam_id=<?php echo $ex['id']; ?>" class="btn btn-sm">⚙️ Setup</a>
                        <?php endif; ?>
                        <a href="marks_entry.php?exam_id=<?php echo $ex['id']; ?>" class="btn btn-sm btn-success">✏️ Enter Marks</a>
                        <a href="report_card.php?exam_id=<?php echo $ex['id']; ?>" class="btn btn-sm btn-secondary">📄 Report Cards</a>
                        <?php if (is_admin()): ?>
                        <a href="delete_exam.php?id=<?php echo $ex['id']; ?>&csrf_token=<?php echo e(csrf_token()); ?>"
                           class="btn btn-sm btn-danger" onclick="return confirm('Delete this exam and all its results?');">🗑️</a>
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
