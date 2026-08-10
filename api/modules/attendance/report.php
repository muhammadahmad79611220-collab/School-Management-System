<?php
require_once __DIR__ . '/../../config/app.php';
require_login();

$pdo = getDB();

if (is_admin()) {
    $sections = $pdo->query(
        "SELECT sec.id, sec.section_name, c.class_name
         FROM sections sec LEFT JOIN classes c ON sec.class_id = c.id
         ORDER BY c.sort_order, sec.section_name"
    )->fetchAll();
} else {
    $stmt = $pdo->prepare(
        "SELECT sec.id, sec.section_name, c.class_name
         FROM sections sec LEFT JOIN classes c ON sec.class_id = c.id
         WHERE sec.class_teacher_id = ?
         ORDER BY c.sort_order, sec.section_name"
    );
    $stmt->execute([current_teacher_id()]);
    $sections = $stmt->fetchAll();
}

$allowedIds = array_column($sections, 'id');
$sectionId = clean_id($_GET['section_id'] ?? ($sections[0]['id'] ?? null));
if ($sectionId && !in_array($sectionId, $allowedIds, true)) {
    $sectionId = $sections[0]['id'] ?? null;
}

$month = $_GET['month'] ?? date('Y-m');
if (!preg_match('/^\d{4}-\d{2}$/', $month)) $month = date('Y-m');

$report = [];
if ($sectionId) {
    $stmt = $pdo->prepare(
        "SELECT s.id, s.full_name, s.roll_no,
            SUM(a.status='Present') as present_count,
            SUM(a.status='Absent') as absent_count,
            SUM(a.status='Leave') as leave_count,
            SUM(a.status='Late') as late_count,
            COUNT(a.id) as total_marked
         FROM students s
         LEFT JOIN attendance a ON a.student_id = s.id AND DATE_FORMAT(a.attendance_date, '%Y-%m') = ?
         WHERE s.section_id = ? AND s.status='Active'
         GROUP BY s.id
         ORDER BY s.full_name"
    );
    $stmt->execute([$month, $sectionId]);
    $report = $stmt->fetchAll();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Attendance Report – <?php echo e(APP_NAME); ?></title>
    <link rel="stylesheet" href="<?php echo BASE_URL; ?>assets/css/style.css">
</head>
<body>
<?php include BASE_PATH . '/includes/sidebar.php'; ?>
<div class="content">
    <div class="card">
        <div class="card-header">
            <div class="card-title">📊 Monthly Attendance Report</div>
            <a href="mark.php" class="btn btn-secondary">← Mark Attendance</a>
        </div>

        <?php if (!$sections): ?>
            <p style="color:#888;">No sections assigned to you yet.</p>
        <?php else: ?>

        <form method="GET" style="display:flex; gap:10px; margin-bottom:20px; flex-wrap:wrap; align-items:flex-end;">
            <div>
                <label>Section</label>
                <select name="section_id" onchange="this.form.submit()">
                    <?php foreach ($sections as $s): ?>
                        <option value="<?php echo $s['id']; ?>" <?php echo $sectionId == $s['id'] ? 'selected' : ''; ?>>
                            <?php echo e($s['class_name'] . ' - ' . $s['section_name']); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div>
                <label>Month</label>
                <input type="month" name="month" value="<?php echo e($month); ?>" onchange="this.form.submit()">
            </div>
        </form>

        <div class="table-wrap">
        <table>
            <thead><tr><th>Roll No</th><th>Name</th><th>Present</th><th>Absent</th><th>Leave</th><th>Late</th><th>Attendance %</th></tr></thead>
            <tbody>
            <?php if (!$report): ?>
                <tr><td colspan="7" style="text-align:center;color:#aaa;padding:20px;">No data for this period.</td></tr>
            <?php endif; ?>
            <?php foreach ($report as $r): ?>
                <?php $pct = $r['total_marked'] > 0 ? round(($r['present_count'] + $r['late_count']) / $r['total_marked'] * 100, 1) : null; ?>
                <tr>
                    <td><?php echo e($r['roll_no']); ?></td>
                    <td><?php echo e($r['full_name']); ?></td>
                    <td><?php echo (int)$r['present_count']; ?></td>
                    <td><?php echo (int)$r['absent_count']; ?></td>
                    <td><?php echo (int)$r['leave_count']; ?></td>
                    <td><?php echo (int)$r['late_count']; ?></td>
                    <td>
                        <?php if ($pct === null): ?>
                            —
                        <?php else: ?>
                            <span class="badge <?php echo $pct >= 75 ? 'badge-success' : ($pct >= 50 ? 'badge-warning' : 'badge-danger'); ?>">
                                <?php echo $pct; ?>%
                            </span>
                        <?php endif; ?>
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
        </div>

        <?php endif; ?>
    </div>
</div>
</body>
</html>
