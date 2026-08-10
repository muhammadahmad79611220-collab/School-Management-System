<?php
require_once __DIR__ . '/../../config/app.php';
require_role('admin');

$pdo = getDB();
$month = $_GET['month'] ?? '';
$where = '';
$params = [];
if ($month !== '' && preg_match('/^\d{4}-\d{2}$/', $month)) {
    $where = 'WHERE sp.salary_month = ?';
    $params[] = $month;
}

$stmt = $pdo->prepare(
    "SELECT sp.*, t.full_name, t.teacher_code
     FROM salary_payments sp
     JOIN teachers t ON sp.teacher_id = t.id
     $where
     ORDER BY sp.payment_date DESC, sp.id DESC"
);
$stmt->execute($params);
$payments = $stmt->fetchAll();

$totalPaid = array_sum(array_column($payments, 'net_salary'));
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Salary History – <?php echo e(APP_NAME); ?></title>
    <link rel="stylesheet" href="<?php echo BASE_URL; ?>assets/css/style.css">
</head>
<body>
<?php include BASE_PATH . '/includes/sidebar.php'; ?>
<div class="content">
    <div class="card">
        <div class="card-header">
            <div class="card-title">📋 Salary Paid Slips</div>
            <a href="pay.php" class="btn">💵 Pay Salary</a>
        </div>

        <?php echo flash_render(); ?>

        <form method="GET" style="margin-bottom:16px;display:flex;gap:10px;align-items:flex-end;">
            <div>
                <label>Filter by Month</label>
                <input type="month" name="month" value="<?php echo e($month); ?>">
            </div>
            <button type="submit" class="btn">🔍 Filter</button>
            <?php if ($month): ?><a href="paid_slips.php" class="btn btn-secondary">Clear</a><?php endif; ?>
        </form>

        <?php if ($payments): ?>
            <p style="color:#666;margin-bottom:14px;">Total paid<?php echo $month ? ' for ' . date('F Y', strtotime($month.'-01')) : ''; ?>: <strong><?php echo money($totalPaid); ?></strong></p>
        <?php endif; ?>

        <div class="table-wrap">
        <table>
            <thead><tr><th>Teacher</th><th>Month</th><th>Fixed</th><th>Bonus</th><th>Deduction</th><th>Net Paid</th><th>Date</th><th>Receipt</th><th></th></tr></thead>
            <tbody>
            <?php if (!$payments): ?>
                <tr><td colspan="9" style="text-align:center;color:#aaa;padding:20px;">No salary record.</td></tr>
            <?php endif; ?>
            <?php foreach ($payments as $p): ?>
                <tr>
                    <td><?php echo e($p['full_name']); ?> <small style="color:#999;">(<?php echo e($p['teacher_code']); ?>)</small></td>
                    <td><?php echo date('M Y', strtotime($p['salary_month'] . '-01')); ?></td>
                    <td><?php echo money($p['fixed_salary']); ?></td>
                    <td><?php echo money($p['bonus_amount']); ?></td>
                    <td><?php echo money($p['deduction_amount']); ?></td>
                    <td><strong><?php echo money($p['net_salary']); ?></strong></td>
                    <td><?php echo date('d M Y', strtotime($p['payment_date'])); ?></td>
                    <td><?php echo e($p['receipt_no']); ?></td>
                    <td><a href="slip.php?id=<?php echo $p['id']; ?>" class="btn btn-sm">🧾 View</a></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
        </div>
    </div>
</div>
</body>
</html>
