<?php
require_once __DIR__ . '/../../config/app.php';
require_role('student');

$pdo = getDB();
$sid = current_student_id();
if (!$sid) redirect('../../dashboard.php');

$rows = $pdo->prepare(
    "SELECT attendance_date, status, remarks FROM attendance WHERE student_id = ? ORDER BY attendance_date DESC LIMIT 200"
);
$rows->execute([$sid]);
$rows = $rows->fetchAll();

$totals = ['Present'=>0,'Absent'=>0,'Leave'=>0,'Late'=>0];
foreach ($rows as $r) {
    if (isset($totals[$r['status']])) $totals[$r['status']]++;
}
$totalMarked = array_sum($totals);
$percent = $totalMarked ? round($totals['Present'] / $totalMarked * 100, 1) : 0;
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>My Attendance – <?php echo e(APP_NAME); ?></title>
    <link rel="stylesheet" href="<?php echo BASE_URL; ?>assets/css/style.css">
</head>
<body>
<?php include BASE_PATH . '/includes/sidebar.php'; ?>
<div class="content">
    <div class="card-header no-print" style="margin-bottom:16px;">
        <div class="card-title">✅ My Attendance</div>
        <button type="button" class="btn btn-secondary" onclick="window.print()">🖨️ Print</button>
    </div>

    <div class="card" style="display:flex;gap:24px;flex-wrap:wrap;margin-bottom:20px;">
        <div><div style="font-size:28px;font-weight:800;color:#1a6b3a;"><?php echo $percent; ?>%</div><div style="color:#888;font-size:13px;">Attendance Rate</div></div>
        <div><div style="font-size:28px;font-weight:800;color:#1a6b3a;"><?php echo $totals['Present']; ?></div><div style="color:#888;font-size:13px;">Present</div></div>
        <div><div style="font-size:28px;font-weight:800;color:#c0392b;"><?php echo $totals['Absent']; ?></div><div style="color:#888;font-size:13px;">Absent</div></div>
        <div><div style="font-size:28px;font-weight:800;color:#b8860b;"><?php echo $totals['Leave']; ?></div><div style="color:#888;font-size:13px;">Leave</div></div>
        <div><div style="font-size:28px;font-weight:800;color:#b8860b;"><?php echo $totals['Late']; ?></div><div style="color:#888;font-size:13px;">Late</div></div>
    </div>

    <div class="card">
        <div class="card-title" style="margin-bottom:14px;">Recent Attendance (last 200 records)</div>
        <div class="table-wrap">
        <table>
            <thead><tr><th>Date</th><th>Status</th><th>Remarks</th></tr></thead>
            <tbody>
            <?php if (!$rows): ?>
                <tr><td colspan="3" style="text-align:center;color:#aaa;padding:20px;">No attendance records yet.</td></tr>
            <?php endif; ?>
            <?php foreach ($rows as $r): ?>
                <?php $badgeClass = match($r['status']) {
                    'Present' => 'badge-success',
                    'Absent'  => 'badge-danger',
                    default   => 'badge-secondary',
                }; ?>
                <tr>
                    <td><?php echo date('d M Y (D)', strtotime($r['attendance_date'])); ?></td>
                    <td><span class="badge <?php echo $badgeClass; ?>"><?php echo e($r['status']); ?></span></td>
                    <td><?php echo e($r['remarks'] ?: '—'); ?></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
        </div>
    </div>
</div>
</body>
</html>
