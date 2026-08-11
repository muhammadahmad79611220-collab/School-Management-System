<?php
require_once __DIR__ . '/../../config/app.php';
require_role('student');

$pdo = getDB();
$sid = current_student_id();
if (!$sid) redirect('../../dashboard.php');

$invoices = $pdo->prepare(
    "SELECT * FROM fee_invoices WHERE student_id = ? ORDER BY due_date DESC, created_at DESC"
);
$invoices->execute([$sid]);
$invoices = $invoices->fetchAll();

$totalDue = 0; $totalPaid = 0;
foreach ($invoices as $inv) {
    $totalDue += (float)$inv['amount_due'] - (float)$inv['discount_amount'];
    $totalPaid += (float)$inv['amount_paid'];
}
$outstanding = max(0, $totalDue - $totalPaid);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>My Fee Status – <?php echo e(APP_NAME); ?></title>
    <link rel="stylesheet" href="<?php echo BASE_URL; ?>assets/css/style.css">
</head>
<body>
<?php include BASE_PATH . '/includes/sidebar.php'; ?>
<div class="content">
    <div class="card-header no-print" style="margin-bottom:16px;">
        <div class="card-title">💰 My Fee Status</div>
        <button type="button" class="btn btn-secondary" onclick="window.print()">🖨️ Print</button>
    </div>

    <div class="card" style="display:flex;gap:24px;flex-wrap:wrap;margin-bottom:20px;">
        <div><div style="font-size:28px;font-weight:800;color:#1e3c72;">Rs. <?php echo number_format($totalDue, 0); ?></div><div style="color:#888;font-size:13px;">Total Billed</div></div>
        <div><div style="font-size:28px;font-weight:800;color:#1a6b3a;">Rs. <?php echo number_format($totalPaid, 0); ?></div><div style="color:#888;font-size:13px;">Total Paid</div></div>
        <div><div style="font-size:28px;font-weight:800;color:<?php echo $outstanding>0?'#c0392b':'#1a6b3a'; ?>;">Rs. <?php echo number_format($outstanding, 0); ?></div><div style="color:#888;font-size:13px;">Outstanding</div></div>
    </div>

    <div class="card">
        <div class="table-wrap">
        <table>
            <thead><tr><th>Description</th><th>Billing Period</th><th>Amount Due</th><th>Paid</th><th>Due Date</th><th>Status</th></tr></thead>
            <tbody>
            <?php if (!$invoices): ?>
                <tr><td colspan="6" style="text-align:center;color:#aaa;padding:20px;">No fee invoices yet.</td></tr>
            <?php endif; ?>
            <?php foreach ($invoices as $inv): ?>
                <?php $badgeClass = match($inv['status']) {
                    'Paid' => 'badge-success',
                    'Partial' => 'badge-warning',
                    'Overdue' => 'badge-danger',
                    default => 'badge-secondary',
                }; ?>
                <tr>
                    <td><?php echo e($inv['description'] ?: $inv['billing_month']); ?></td>
                    <td><?php echo e($inv['billing_month']); ?></td>
                    <td>Rs. <?php echo number_format($inv['amount_due'] - $inv['discount_amount'], 0); ?></td>
                    <td>Rs. <?php echo number_format($inv['amount_paid'], 0); ?></td>
                    <td><?php echo $inv['due_date'] ? date('d M Y', strtotime($inv['due_date'])) : '—'; ?></td>
                    <td><span class="badge <?php echo $badgeClass; ?>"><?php echo e($inv['status']); ?></span></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
        </div>
    </div>
</div>
</body>
</html>
