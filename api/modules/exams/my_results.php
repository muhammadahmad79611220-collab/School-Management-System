<?php
require_once __DIR__ . '/../../config/app.php';
require_role('student');

$pdo = getDB();
$sid = current_student_id();
if (!$sid) redirect('../../dashboard.php');

$exams = $pdo->prepare(
    "SELECT DISTINCT e.id, e.exam_name, e.exam_date, e.academic_year
     FROM exams e
     JOIN exam_subjects es ON es.exam_id = e.id
     JOIN exam_results er ON er.exam_subject_id = es.id
     WHERE er.student_id = ?
     ORDER BY e.exam_date DESC"
);
$exams->execute([$sid]);
$exams = $exams->fetchAll();

$examId = clean_id($_GET['exam_id'] ?? ($exams[0]['id'] ?? null));

$results = [];
$totalObtained = 0; $totalMax = 0;
if ($examId) {
    $stmt = $pdo->prepare(
        "SELECT c.course_name, es.max_marks, es.pass_marks, er.marks_obtained, er.is_absent
         FROM exam_subjects es
         JOIN courses c ON es.course_id = c.id
         LEFT JOIN exam_results er ON er.exam_subject_id = es.id AND er.student_id = ?
         WHERE es.exam_id = ?
         ORDER BY c.course_name"
    );
    $stmt->execute([$sid, $examId]);
    $results = $stmt->fetchAll();
    foreach ($results as $r) {
        if (!$r['is_absent'] && $r['marks_obtained'] !== null) {
            $totalObtained += (float)$r['marks_obtained'];
            $totalMax += (int)$r['max_marks'];
        }
    }
}
$overallPercent = $totalMax ? round($totalObtained / $totalMax * 100, 1) : 0;
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>My Results – <?php echo e(APP_NAME); ?></title>
    <link rel="stylesheet" href="<?php echo BASE_URL; ?>assets/css/style.css">
</head>
<body>
<?php include BASE_PATH . '/includes/sidebar.php'; ?>
<div class="content">
    <div class="card-header no-print" style="margin-bottom:16px;">
        <div class="card-title">📝 My Results</div>
        <button type="button" class="btn btn-secondary" onclick="window.print()">🖨️ Print</button>
    </div>

    <?php if (!$exams): ?>
        <div class="card"><p style="color:#888;">No results have been published yet.</p></div>
    <?php else: ?>
        <form method="GET" class="form-grid no-print" style="margin-bottom:16px;max-width:400px;">
            <div class="form-group">
                <label>Select Exam</label>
                <select name="exam_id" onchange="this.form.submit()">
                    <?php foreach ($exams as $ex): ?>
                        <option value="<?php echo $ex['id']; ?>" <?php echo $ex['id']==$examId?'selected':''; ?>>
                            <?php echo e($ex['exam_name']); ?><?php echo $ex['academic_year'] ? ' (' . e($ex['academic_year']) . ')' : ''; ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
        </form>

        <div class="card" style="display:flex;gap:24px;flex-wrap:wrap;margin-bottom:20px;">
            <div><div style="font-size:28px;font-weight:800;color:#1e3c72;"><?php echo $totalObtained; ?> / <?php echo $totalMax; ?></div><div style="color:#888;font-size:13px;">Total Marks</div></div>
            <div><div style="font-size:28px;font-weight:800;color:<?php echo $overallPercent>=33?'#1a6b3a':'#c0392b'; ?>;"><?php echo $overallPercent; ?>%</div><div style="color:#888;font-size:13px;">Percentage</div></div>
        </div>

        <div class="card">
            <div class="table-wrap">
            <table>
                <thead><tr><th>Subject</th><th>Marks Obtained</th><th>Max Marks</th><th>Pass Marks</th><th>Result</th></tr></thead>
                <tbody>
                <?php foreach ($results as $r): ?>
                    <?php
                        $pass = !$r['is_absent'] && $r['marks_obtained'] !== null && (float)$r['marks_obtained'] >= (int)$r['pass_marks'];
                        $shown = $r['is_absent'] ? 'Absent' : ($r['marks_obtained'] !== null ? $r['marks_obtained'] : '—');
                    ?>
                    <tr>
                        <td><?php echo e($r['course_name']); ?></td>
                        <td><?php echo e($shown); ?></td>
                        <td><?php echo e($r['max_marks']); ?></td>
                        <td><?php echo e($r['pass_marks']); ?></td>
                        <td>
                            <?php if ($r['is_absent']): ?>
                                <span class="badge badge-secondary">Absent</span>
                            <?php elseif ($r['marks_obtained'] === null): ?>
                                <span class="badge badge-secondary">Pending</span>
                            <?php else: ?>
                                <span class="badge <?php echo $pass ? 'badge-success' : 'badge-danger'; ?>"><?php echo $pass ? 'Pass' : 'Fail'; ?></span>
                            <?php endif; ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
            </div>
        </div>
    <?php endif; ?>
</div>
</body>
</html>
