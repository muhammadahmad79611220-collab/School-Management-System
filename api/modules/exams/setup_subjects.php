<?php
require_once __DIR__ . '/../../config/app.php';
require_role('admin');

$pdo = getDB();
$examId = clean_id($_GET['exam_id'] ?? null);
if (!$examId) redirect('list.php');

$stmt = $pdo->prepare("SELECT e.*, c.class_name FROM exams e LEFT JOIN classes c ON e.class_id = c.id WHERE e.id = ?");
$stmt->execute([$examId]);
$exam = $stmt->fetch();
if (!$exam) {
    flash('error', 'Exam not found.');
    redirect('list.php');
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verify();
    $courseId  = clean_id($_POST['course_id'] ?? null);
    $maxMarks  = (int)($_POST['max_marks'] ?? 100);
    $passMarks = (int)($_POST['pass_marks'] ?? 33);
    $examDate  = $_POST['exam_date'] ?? null;

    if (!$courseId || $maxMarks <= 0 || $passMarks < 0 || $passMarks > $maxMarks) {
        flash('error', 'Please provide a valid subject and marks (pass marks must be ≤ max marks).');
    } else {
        try {
            $stmt = $pdo->prepare(
                "INSERT INTO exam_subjects (exam_id, course_id, max_marks, pass_marks, exam_date) VALUES (?,?,?,?,?)"
            );
            $stmt->execute([$examId, $courseId, $maxMarks, $passMarks, $examDate ?: null]);
            flash('success', 'Subject added to exam.');
        } catch (PDOException $e) {
            flash('error', $e->getCode() === '23000' ? 'This subject is already part of the exam.' : 'Could not add subject.');
        }
    }
    redirect("setup_subjects.php?exam_id=$examId");
}

// Subjects available for this exam's class
$courses = $pdo->prepare("SELECT id, course_name FROM courses WHERE class_id = ? OR class_id IS NULL ORDER BY course_name");
$courses->execute([$exam['class_id']]);
$courses = $courses->fetchAll();

$examSubjects = $pdo->prepare(
    "SELECT es.*, c.course_name FROM exam_subjects es JOIN courses c ON es.course_id = c.id WHERE es.exam_id = ? ORDER BY c.course_name"
);
$examSubjects->execute([$examId]);
$examSubjects = $examSubjects->fetchAll();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Setup Exam Subjects – <?php echo e(APP_NAME); ?></title>
    <link rel="stylesheet" href="<?php echo BASE_URL; ?>assets/css/style.css">
</head>
<body>
<?php include BASE_PATH . '/includes/sidebar.php'; ?>
<div class="content">
    <div class="card">
        <div class="card-header">
            <div class="card-title">⚙️ Setup: <?php echo e($exam['exam_name']); ?> (<?php echo e($exam['class_name']); ?>)</div>
            <a href="list.php" class="btn btn-secondary">← Back</a>
        </div>

        <?php echo flash_render(); ?>

        <form method="POST" class="form-grid" style="margin-bottom:20px;">
            <?php echo csrf_field(); ?>
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
                <label>Max Marks</label>
                <input type="number" name="max_marks" value="100" min="1" required>
            </div>
            <div class="form-group">
                <label>Pass Marks</label>
                <input type="number" name="pass_marks" value="33" min="0" required>
            </div>
            <div class="form-group">
                <label>Exam Date (optional)</label>
                <input type="date" name="exam_date">
            </div>
            <div class="form-group" style="align-self:end;">
                <button type="submit" class="btn btn-success btn-block">➕ Add Subject</button>
            </div>
        </form>

        <div class="table-wrap">
        <table>
            <thead><tr><th>Subject</th><th>Max Marks</th><th>Pass Marks</th><th>Date</th></tr></thead>
            <tbody>
            <?php if (!$examSubjects): ?>
                <tr><td colspan="4" style="text-align:center;color:#aaa;padding:20px;">No subjects configured yet.</td></tr>
            <?php endif; ?>
            <?php foreach ($examSubjects as $es): ?>
                <tr>
                    <td><?php echo e($es['course_name']); ?></td>
                    <td><?php echo (int)$es['max_marks']; ?></td>
                    <td><?php echo (int)$es['pass_marks']; ?></td>
                    <td><?php echo $es['exam_date'] ? date('d M Y', strtotime($es['exam_date'])) : '—'; ?></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
        </div>
    </div>
</div>
</body>
</html>
