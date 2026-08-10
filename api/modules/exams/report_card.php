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

// Student list, scoped for teachers to their own section only.
if (is_admin()) {
    $stmt = $pdo->prepare("SELECT id, full_name, roll_no FROM students WHERE class_id = ? AND status='Active' ORDER BY full_name");
    $stmt->execute([$exam['class_id']]);
} else {
    $stmt = $pdo->prepare(
        "SELECT s.id, s.full_name, s.roll_no FROM students s
         JOIN sections sec ON sec.id = s.section_id
         WHERE s.class_id = ? AND s.status='Active' AND sec.class_teacher_id = ?
         ORDER BY s.full_name"
    );
    $stmt->execute([$exam['class_id'], current_teacher_id()]);
}
$students = $stmt->fetchAll();

$studentId = clean_id($_GET['student_id'] ?? ($students[0]['id'] ?? null));
$allowedIds = array_column($students, 'id');
if ($studentId && !in_array($studentId, $allowedIds, true)) {
    flash('error', 'You do not have access to that student\'s report card.');
    $studentId = $students[0]['id'] ?? null;
}

$results = [];
$student = null;
if ($studentId) {
    foreach ($students as $s) if ($s['id'] == $studentId) $student = $s;

    $stmt = $pdo->prepare(
        "SELECT c.course_name, es.max_marks, es.pass_marks, er.marks_obtained, er.is_absent
         FROM exam_subjects es
         JOIN courses c ON es.course_id = c.id
         LEFT JOIN exam_results er ON er.exam_subject_id = es.id AND er.student_id = ?
         WHERE es.exam_id = ?
         ORDER BY c.course_name"
    );
    $stmt->execute([$studentId, $examId]);
    $results = $stmt->fetchAll();
}

$totalMax = 0; $totalObtained = 0; $allEntered = true;
foreach ($results as $r) {
    $totalMax += $r['max_marks'];
    if ($r['is_absent']) { $allEntered = false; continue; }
    if ($r['marks_obtained'] === null) { $allEntered = false; continue; }
    $totalObtained += (float)$r['marks_obtained'];
}
$percentage = $totalMax > 0 ? round($totalObtained / $totalMax * 100, 2) : 0;

function gradeFor(float $pct): string {
    if ($pct >= 90) return 'A+';
    if ($pct >= 80) return 'A';
    if ($pct >= 70) return 'B';
    if ($pct >= 60) return 'C';
    if ($pct >= 50) return 'D';
    if ($pct >= 33) return 'E';
    return 'F';
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Report Card – <?php echo e(APP_NAME); ?></title>
    <link rel="stylesheet" href="<?php echo BASE_URL; ?>assets/css/style.css">
    <style>
        @media print {
            .sidebar, .no-print { display: none !important; }
            .content { margin-left: 0 !important; }
        }
        .report-card { max-width: 700px; margin: 0 auto; background: white; padding: 30px; border-radius: 12px; }
        .report-card h2 { text-align: center; color: #1e3c72; margin-bottom: 4px; }
        .report-card .sub { text-align: center; color: #666; margin-bottom: 20px; }
        .result-table th, .result-table td { text-align: center; }
        .result-table td:first-child, .result-table th:first-child { text-align: left; }
        .summary-box { display: flex; justify-content: space-around; margin-top: 20px; padding: 16px; background: #f8f9ff; border-radius: 10px; }
        .summary-box div { text-align: center; }
        .summary-box .val { font-size: 24px; font-weight: 800; color: #1e3c72; }
        .summary-box .lbl { font-size: 12px; color: #888; text-transform: uppercase; }
    </style>
</head>
<body>
<?php include BASE_PATH . '/includes/sidebar.php'; ?>
<div class="content">
    <div class="card no-print">
        <div class="card-header">
            <div class="card-title">📄 Report Cards: <?php echo e($exam['exam_name']); ?></div>
            <a href="list.php" class="btn btn-secondary">← Back</a>
        </div>
        <?php echo flash_render(); ?>
        <?php if ($students): ?>
        <form method="GET" style="margin-bottom:10px;">
            <input type="hidden" name="exam_id" value="<?php echo $examId; ?>">
            <label>Student</label>
            <select name="student_id" onchange="this.form.submit()">
                <?php foreach ($students as $s): ?>
                    <option value="<?php echo $s['id']; ?>" <?php echo $studentId == $s['id'] ? 'selected' : ''; ?>>
                        <?php echo e($s['full_name']) . ' (' . e($s['roll_no']) . ')'; ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </form>
        <button onclick="window.print()" class="btn">🖨️ Print This Report Card</button>
        <?php else: ?>
            <p style="color:#888;">No students available.</p>
        <?php endif; ?>
    </div>

    <?php if ($student): ?>
    <div class="report-card">
        <h2>🏫 <?php echo e(APP_NAME); ?></h2>
        <div class="sub">Report Card — <?php echo e($exam['exam_name']); ?> (<?php echo e($exam['class_name']); ?>)</div>

        <div class="form-grid" style="margin-bottom:16px;">
            <div><strong>Student:</strong> <?php echo e($student['full_name']); ?></div>
            <div><strong>Roll No:</strong> <?php echo e($student['roll_no']); ?></div>
        </div>

        <table class="result-table">
            <thead><tr><th>Subject</th><th>Max Marks</th><th>Pass Marks</th><th>Obtained</th><th>Result</th></tr></thead>
            <tbody>
            <?php foreach ($results as $r): ?>
                <tr>
                    <td><?php echo e($r['course_name']); ?></td>
                    <td><?php echo (int)$r['max_marks']; ?></td>
                    <td><?php echo (int)$r['pass_marks']; ?></td>
                    <td>
                        <?php if ($r['is_absent']): ?>
                            <span class="badge badge-warning">Absent</span>
                        <?php elseif ($r['marks_obtained'] === null): ?>
                            <span class="badge badge-secondary">Pending</span>
                        <?php else: ?>
                            <?php echo e($r['marks_obtained']); ?>
                        <?php endif; ?>
                    </td>
                    <td>
                        <?php if ($r['is_absent']): ?>
                            —
                        <?php elseif ($r['marks_obtained'] === null): ?>
                            —
                        <?php else: ?>
                            <span class="badge <?php echo $r['marks_obtained'] >= $r['pass_marks'] ? 'badge-success' : 'badge-danger'; ?>">
                                <?php echo $r['marks_obtained'] >= $r['pass_marks'] ? 'Pass' : 'Fail'; ?>
                            </span>
                        <?php endif; ?>
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>

        <div class="summary-box">
            <div><div class="val"><?php echo $totalObtained; ?>/<?php echo $totalMax; ?></div><div class="lbl">Total Marks</div></div>
            <div><div class="val"><?php echo $percentage; ?>%</div><div class="lbl">Percentage</div></div>
            <div><div class="val"><?php echo gradeFor($percentage); ?></div><div class="lbl">Grade</div></div>
        </div>
        <?php if (!$allEntered): ?>
            <p style="text-align:center;color:#d35400;margin-top:14px;font-size:13px;">⚠️ Some results are still pending or marked absent — totals shown are partial.</p>
        <?php endif; ?>
    </div>
    <?php endif; ?>
</div>
</body>
</html>
