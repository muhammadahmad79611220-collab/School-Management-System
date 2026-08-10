<?php
require_once __DIR__ . '/../../config/app.php';
require_login();

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

// Subjects configured for this exam. Teachers only see subjects they actually teach.
if (is_admin()) {
    $subjStmt = $pdo->prepare(
        "SELECT es.*, c.course_name FROM exam_subjects es JOIN courses c ON es.course_id = c.id WHERE es.exam_id = ? ORDER BY c.course_name"
    );
    $subjStmt->execute([$examId]);
} else {
    $subjStmt = $pdo->prepare(
        "SELECT es.*, c.course_name FROM exam_subjects es
         JOIN courses c ON es.course_id = c.id
         JOIN teacher_subjects ts ON ts.course_id = es.course_id
         WHERE es.exam_id = ? AND ts.teacher_id = ?
         GROUP BY es.id
         ORDER BY c.course_name"
    );
    $subjStmt->execute([$examId, current_teacher_id()]);
}
$examSubjects = $subjStmt->fetchAll();
$allowedSubjectIds = array_column($examSubjects, 'id');

$examSubjectId = clean_id($_GET['exam_subject_id'] ?? ($examSubjects[0]['id'] ?? null));
if ($examSubjectId && !in_array($examSubjectId, $allowedSubjectIds, true)) {
    flash('error', 'You are not assigned to enter marks for that subject.');
    $examSubjectId = $examSubjects[0]['id'] ?? null;
}

$currentSubject = null;
foreach ($examSubjects as $es) {
    if ($es['id'] == $examSubjectId) { $currentSubject = $es; break; }
}

$students = [];
$existingMarks = [];
if ($currentSubject) {
    $stmt = $pdo->prepare("SELECT id, full_name, roll_no FROM students WHERE class_id = ? AND status='Active' ORDER BY full_name");
    $stmt->execute([$exam['class_id']]);
    $students = $stmt->fetchAll();

    $stmt2 = $pdo->prepare("SELECT student_id, marks_obtained, is_absent FROM exam_results WHERE exam_subject_id = ?");
    $stmt2->execute([$examSubjectId]);
    foreach ($stmt2->fetchAll() as $row) {
        $existingMarks[$row['student_id']] = $row;
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verify();
    $postExamSubjectId = clean_id($_POST['exam_subject_id'] ?? null);

    if (!in_array($postExamSubjectId, $allowedSubjectIds, true)) {
        flash('error', 'You are not authorized to enter marks for that subject.');
        redirect("marks_entry.php?exam_id=$examId");
    }

    $maxMarks = 0;
    foreach ($examSubjects as $es) if ($es['id'] == $postExamSubjectId) $maxMarks = $es['max_marks'];

    $marks = $_POST['marks'] ?? [];
    $absent = $_POST['absent'] ?? [];

    $stmtUp = $pdo->prepare(
        "INSERT INTO exam_results (exam_subject_id, student_id, marks_obtained, is_absent, entered_by)
         VALUES (?,?,?,?,?)
         ON DUPLICATE KEY UPDATE marks_obtained=VALUES(marks_obtained), is_absent=VALUES(is_absent), entered_by=VALUES(entered_by)"
    );

    $count = 0;
    $errors = [];
    foreach ($marks as $studentId => $mark) {
        $studentId = clean_id($studentId);
        if (!$studentId) continue;
        $isAbsent = isset($absent[$studentId]) ? 1 : 0;
        $markVal = null;
        if (!$isAbsent) {
            if ($mark === '' || $mark === null) continue;
            if (!is_numeric($mark) || $mark < 0 || $mark > $maxMarks) {
                $errors[] = "Invalid marks for student ID $studentId (must be 0–$maxMarks).";
                continue;
            }
            $markVal = (float)$mark;
        }
        $stmtUp->execute([$postExamSubjectId, $studentId, $markVal, $isAbsent, $_SESSION['user_id']]);
        $count++;
    }

    foreach ($errors as $err) flash('error', $err);
    log_activity('marks_entered', "exam_subject_id=$postExamSubjectId count=$count");
    flash('success', "Marks saved for $count student(s).");
    redirect("marks_entry.php?exam_id=$examId&exam_subject_id=$postExamSubjectId");
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Enter Marks – <?php echo e(APP_NAME); ?></title>
    <link rel="stylesheet" href="<?php echo BASE_URL; ?>assets/css/style.css">
</head>
<body>
<?php include BASE_PATH . '/includes/sidebar.php'; ?>
<div class="content">
    <div class="card">
        <div class="card-header">
            <div class="card-title">✏️ Enter Marks: <?php echo e($exam['exam_name']); ?></div>
            <a href="list.php" class="btn btn-secondary">← Back</a>
        </div>

        <?php echo flash_render(); ?>

        <?php if (!$examSubjects): ?>
            <p style="color:#888;">No subjects are available for you to enter marks for in this exam. <?php echo is_admin() ? 'Set up exam subjects first.' : 'Ask the administrator to assign you to a subject.'; ?></p>
        <?php else: ?>

        <form method="GET" style="margin-bottom:20px;">
            <input type="hidden" name="exam_id" value="<?php echo $examId; ?>">
            <label>Subject</label>
            <select name="exam_subject_id" onchange="this.form.submit()">
                <?php foreach ($examSubjects as $es): ?>
                    <option value="<?php echo $es['id']; ?>" <?php echo $examSubjectId == $es['id'] ? 'selected' : ''; ?>>
                        <?php echo e($es['course_name']); ?> (Max: <?php echo $es['max_marks']; ?>)
                    </option>
                <?php endforeach; ?>
            </select>
        </form>

        <?php if ($currentSubject && $students): ?>
        <form method="POST">
            <?php echo csrf_field(); ?>
            <input type="hidden" name="exam_subject_id" value="<?php echo $examSubjectId; ?>">

            <div class="table-wrap">
            <table>
                <thead><tr><th>Roll No</th><th>Name</th><th>Marks (out of <?php echo $currentSubject['max_marks']; ?>)</th><th>Absent</th></tr></thead>
                <tbody>
                <?php foreach ($students as $st): ?>
                    <?php $ex = $existingMarks[$st['id']] ?? null; ?>
                    <tr>
                        <td><?php echo e($st['roll_no']); ?></td>
                        <td><?php echo e($st['full_name']); ?></td>
                        <td>
                            <input type="number" name="marks[<?php echo $st['id']; ?>]" min="0" max="<?php echo $currentSubject['max_marks']; ?>" step="0.5"
                                   value="<?php echo $ex && !$ex['is_absent'] ? e($ex['marks_obtained']) : ''; ?>" style="margin-bottom:0;">
                        </td>
                        <td style="text-align:center;">
                            <input type="checkbox" name="absent[<?php echo $st['id']; ?>]" style="width:auto;margin:0;" <?php echo ($ex && $ex['is_absent']) ? 'checked' : ''; ?>>
                        </td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
            </div>
            <button type="submit" class="btn" style="margin-top:16px;">💾 Save Marks</button>
        </form>
        <?php endif; ?>

        <?php endif; ?>
    </div>
</div>
</body>
</html>
