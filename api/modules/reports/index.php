<?php
require_once __DIR__ . '/../../config/app.php';
require_login();
if (!is_admin() && !can('view', 'reports')) {
    require_role('admin');
}

$pdo = getDB();

// Students per class
$studentsPerClass = $pdo->query(
    "SELECT c.class_name, COUNT(s.id) as cnt FROM classes c
     LEFT JOIN students s ON s.class_id = c.id AND s.status='Active'
     GROUP BY c.id ORDER BY c.sort_order"
)->fetchAll();

// Gender split
$genderSplit = $pdo->query(
    "SELECT COALESCE(gender,'Unspecified') as gender, COUNT(*) as cnt FROM students WHERE status='Active' GROUP BY gender"
)->fetchAll();

// Attendance trend, last 14 days
$attendanceTrend = $pdo->query(
    "SELECT attendance_date, SUM(status='Present') as present, SUM(status='Absent') as absent
     FROM attendance WHERE attendance_date >= DATE_SUB(CURDATE(), INTERVAL 14 DAY)
     GROUP BY attendance_date ORDER BY attendance_date"
)->fetchAll();

// Fee collection by month (last 6 months)
$feeCollection = $pdo->query(
    "SELECT DATE_FORMAT(payment_date, '%Y-%m') as ym, SUM(amount) as total
     FROM fee_payments WHERE payment_date >= DATE_SUB(CURDATE(), INTERVAL 6 MONTH)
     GROUP BY ym ORDER BY ym"
)->fetchAll();

// Outstanding fees by class
$outstandingByClass = $pdo->query(
    "SELECT c.class_name, SUM(fi.amount_due - fi.amount_paid) as outstanding
     FROM fee_invoices fi
     JOIN students s ON fi.student_id = s.id
     LEFT JOIN classes c ON s.class_id = c.id
     WHERE fi.status != 'Paid'
     GROUP BY c.id ORDER BY outstanding DESC"
)->fetchAll();

// Top-level counts
$counts = [
    'students' => $pdo->query("SELECT COUNT(*) c FROM students WHERE status='Active'")->fetch()['c'],
    'teachers' => $pdo->query("SELECT COUNT(*) c FROM teachers WHERE is_active=1")->fetch()['c'],
    'outstanding' => $pdo->query("SELECT COALESCE(SUM(amount_due-amount_paid),0) c FROM fee_invoices")->fetch()['c'],
    'collected_this_month' => $pdo->query("SELECT COALESCE(SUM(amount),0) c FROM fee_payments WHERE DATE_FORMAT(payment_date,'%Y-%m') = DATE_FORMAT(CURDATE(),'%Y-%m')")->fetch()['c'],
];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Reports &amp; Analytics – <?php echo e(APP_NAME); ?></title>
    <link rel="stylesheet" href="<?php echo BASE_URL; ?>assets/css/style.css">
    <script src="https://cdnjs.cloudflare.com/ajax/libs/Chart.js/4.4.0/chart.umd.min.js"></script>
    <style>
        .charts-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 20px; }
        @media (max-width: 900px) { .charts-grid { grid-template-columns: 1fr; } }
        .chart-box canvas { max-height: 280px; }
    </style>
</head>
<body>
<?php include BASE_PATH . '/includes/sidebar.php'; ?>
<div class="content">

    <div class="card-header no-print" style="margin-bottom:16px;">
        <div class="card-title">📊 Reports &amp; Analytics</div>
        <div>
            <a href="export_students.php" class="btn btn-secondary">⬇️ Export Students CSV</a>
            <a href="export_fees.php" class="btn btn-secondary">⬇️ Export Fees CSV</a>
        </div>
    </div>

    <div class="dashboard-stats" style="margin-bottom:22px;">
        <div class="stat-card"><h3><?php echo (int)$counts['students']; ?></h3><p>Active Students</p></div>
        <div class="stat-card"><h3><?php echo (int)$counts['teachers']; ?></h3><p>Active Teachers</p></div>
        <div class="stat-card"><h3><?php echo money($counts['outstanding']); ?></h3><p>Outstanding Fees</p></div>
        <div class="stat-card"><h3><?php echo money($counts['collected_this_month']); ?></h3><p>Collected This Month</p></div>
    </div>

    <div class="charts-grid">
        <div class="card chart-box">
            <div class="card-title" style="margin-bottom:14px;">Students per Class</div>
            <canvas id="chartStudentsPerClass"></canvas>
        </div>
        <div class="card chart-box">
            <div class="card-title" style="margin-bottom:14px;">Gender Distribution</div>
            <canvas id="chartGender"></canvas>
        </div>
        <div class="card chart-box">
            <div class="card-title" style="margin-bottom:14px;">Attendance Trend (Last 14 Days)</div>
            <canvas id="chartAttendance"></canvas>
        </div>
        <div class="card chart-box">
            <div class="card-title" style="margin-bottom:14px;">Fee Collection (Last 6 Months)</div>
            <canvas id="chartFees"></canvas>
        </div>
    </div>

    <div class="card" style="margin-top:20px;">
        <div class="card-title" style="margin-bottom:14px;">Outstanding Fees by Class</div>
        <div class="table-wrap">
        <table>
            <thead><tr><th>Class</th><th>Outstanding Amount</th></tr></thead>
            <tbody>
            <?php if (!$outstandingByClass): ?>
                <tr><td colspan="2" style="text-align:center;color:#aaa;padding:16px;">No outstanding fees. 🎉</td></tr>
            <?php endif; ?>
            <?php foreach ($outstandingByClass as $row): ?>
                <tr>
                    <td><?php echo e($row['class_name'] ?? '—'); ?></td>
                    <td><?php echo money($row['outstanding']); ?></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
        </div>
    </div>

</div>

<script>
const palette = ['#667eea','#764ba2','#11998e','#38ef7d','#f7971e','#ffd200','#e74c3c','#0984e3'];

new Chart(document.getElementById('chartStudentsPerClass'), {
    type: 'bar',
    data: {
        labels: <?php echo json_encode(array_column($studentsPerClass, 'class_name')); ?>,
        datasets: [{ label: 'Students', data: <?php echo json_encode(array_map('intval', array_column($studentsPerClass, 'cnt'))); ?>, backgroundColor: '#2a5298' }]
    },
    options: { plugins: { legend: { display: false } }, scales: { y: { beginAtZero: true } } }
});

new Chart(document.getElementById('chartGender'), {
    type: 'doughnut',
    data: {
        labels: <?php echo json_encode(array_column($genderSplit, 'gender')); ?>,
        datasets: [{ data: <?php echo json_encode(array_map('intval', array_column($genderSplit, 'cnt'))); ?>, backgroundColor: palette }]
    }
});

new Chart(document.getElementById('chartAttendance'), {
    type: 'line',
    data: {
        labels: <?php echo json_encode(array_map(fn($r) => date('d M', strtotime($r['attendance_date'])), $attendanceTrend)); ?>,
        datasets: [
            { label: 'Present', data: <?php echo json_encode(array_map('intval', array_column($attendanceTrend, 'present'))); ?>, borderColor: '#27ae60', tension: 0.3 },
            { label: 'Absent', data: <?php echo json_encode(array_map('intval', array_column($attendanceTrend, 'absent'))); ?>, borderColor: '#e74c3c', tension: 0.3 }
        ]
    },
    options: { scales: { y: { beginAtZero: true } } }
});

new Chart(document.getElementById('chartFees'), {
    type: 'bar',
    data: {
        labels: <?php echo json_encode(array_column($feeCollection, 'ym')); ?>,
        datasets: [{ label: 'Collected', data: <?php echo json_encode(array_map('floatval', array_column($feeCollection, 'total'))); ?>, backgroundColor: '#27ae60' }]
    },
    options: { plugins: { legend: { display: false } }, scales: { y: { beginAtZero: true } } }
});
</script>
</body>
</html>
