<?php
require_once __DIR__ . '/../../config/app.php';
require_role('admin');

$pdo = getDB();
$teachers = $pdo->query("SELECT id, full_name FROM teachers WHERE is_active=1 ORDER BY full_name")->fetchAll();
$courses = $pdo->query("SELECT id, course_name FROM courses ORDER BY course_name")->fetchAll();
$sections = $pdo->query(
    "SELECT sec.id, sec.section_name, c.class_name FROM sections sec LEFT JOIN classes c ON sec.class_id = c.id ORDER BY c.sort_order, sec.section_name"
)->fetchAll();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verify();
    $teacherId = clean_id($_POST['teacher_id'] ?? null);
    $courseId  = clean_id($_POST['course_id'] ?? null);
    $sectionId = clean_id($_POST['section_id'] ?? null);

    if (!$teacherId || !$courseId || !$sectionId) {
        flash('error', 'Please select a teacher, subject, and section.');
    } else {
        try {
            $pdo->prepare("INSERT INTO teacher_subjects (teacher_id, course_id, section_id) VALUES (?,?,?)")
                ->execute([$teacherId, $courseId, $sectionId]);
            flash('success', 'Assignment saved.');
        } catch (PDOException $e) {
            flash('error', $e->getCode() === '23000' ? 'This assignment already exists.' : 'Could not save assignment.');
        }
    }
    redirect('teacher_subjects.php');
}

$assignments = $pdo->query(
    "SELECT ts.id, t.full_name as teacher_name, c.course_name, sec.section_name, cl.class_name
     FROM teacher_subjects ts
     JOIN teachers t ON ts.teacher_id = t.id
     JOIN courses c ON ts.course_id = c.id
     JOIN sections sec ON ts.section_id = sec.id
     LEFT JOIN classes cl ON sec.class_id = cl.id
     ORDER BY t.full_name, c.course_name"
)->fetchAll();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Teacher Subject Assignments – <?php echo e(APP_NAME); ?></title>
    <link rel="stylesheet" href="<?php echo BASE_URL; ?>assets/css/style.css">
</head>
<body>
<?php include BASE_PATH . '/includes/sidebar.php'; ?>
<div class="content">
    <div class="card">
        <div class="card-header">
            <div class="card-title">🔗 Assign Teachers to Subjects &amp; Sections</div>
            <a href="list.php" class="btn btn-secondary">← Back to Exams</a>
        </div>
        <p style="color:#888;font-size:13px;margin-bottom:16px;">
            This controls which subjects a teacher can enter marks for during exams.
        </p>

        <?php echo flash_render(); ?>

        <form method="POST" class="form-grid" style="margin-bottom:20px;">
            <?php echo csrf_field(); ?>
            <div class="form-group">
                <label>Teacher</label>
                <select name="teacher_id" required>
                    <option value="">— Select —</option>
                    <?php foreach ($teachers as $t): ?>
                        <option value="<?php echo $t['id']; ?>"><?php echo e($t['full_name']); ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="form-group">
                <label>Subject</label>
                <select name="course_id" required>
                    <option value="">— Select —</option>
                    <?php foreach ($courses as $c): ?>
                        <option value="<?php echo $c['id']; ?>"><?php echo e($c['course_name']); ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="form-group">
                <label>Section</label>
                <select name="section_id" required>
                    <option value="">— Select —</option>
                    <?php foreach ($sections as $s): ?>
                        <option value="<?php echo $s['id']; ?>"><?php echo e(($s['class_name'] ?? '') . ' - ' . $s['section_name']); ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="form-group" style="align-self:end;">
                <button type="submit" class="btn btn-success btn-block">➕ Assign</button>
            </div>
        </form>

        <div class="table-wrap">
        <table>
            <thead><tr><th>Teacher</th><th>Subject</th><th>Class - Section</th></tr></thead>
            <tbody>
            <?php if (!$assignments): ?>
                <tr><td colspan="3" style="text-align:center;color:#aaa;padding:20px;">No assignments yet.</td></tr>
            <?php endif; ?>
            <?php foreach ($assignments as $a): ?>
                <tr>
                    <td><?php echo e($a['teacher_name']); ?></td>
                    <td><?php echo e($a['course_name']); ?></td>
                    <td><?php echo e(($a['class_name'] ?? '') . ' - ' . $a['section_name']); ?></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
        </div>
    </div>
</div>
</body>
</html>
