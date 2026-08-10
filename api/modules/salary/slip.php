<?php
require_once __DIR__ . '/../../config/app.php';
require_role('admin');

$pdo = getDB();
$settings = get_settings();
$id = clean_id($_GET['id'] ?? null);
if (!$id) redirect('paid_slips.php');

$stmt = $pdo->prepare(
    "SELECT sp.*, t.full_name, t.teacher_code, t.qualification, u.full_name as paid_by_name
     FROM salary_payments sp
     JOIN teachers t ON sp.teacher_id = t.id
     LEFT JOIN users u ON sp.paid_by = u.id
     WHERE sp.id = ?"
);
$stmt->execute([$id]);
$payment = $stmt->fetch();
if (!$payment) {
    flash('error', 'Salary record not found.');
    redirect('paid_slips.php');
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Salary Slip – <?php echo e(APP_NAME); ?></title>
    <link rel="stylesheet" href="<?php echo BASE_URL; ?>assets/css/style.css">
    <style>
        @media print { .sidebar, .no-print { display: none !important; } .content { margin-left: 0 !important; } }
        .slip-paper { max-width: 640px; margin: 0 auto; background: white; border-radius: 16px; padding: 30px 35px; box-shadow: 0 6px 22px rgba(0,0,0,0.08); }
        .slip-top { display: flex; justify-content: space-between; align-items: center; padding-bottom: 16px; margin-bottom: 18px; border-bottom: 3px solid #1e3c72; }
        .slip-top .school-block { display: flex; align-items: center; gap: 14px; }
        .slip-top img.logo { width: 54px; height: 54px; object-fit: cover; border-radius: 50%; border: 2px solid #e0e4ef; box-shadow: 0 2px 8px rgba(0,0,0,0.08); }
        .slip-top h2 { color: #1e3c72; font-size: 19px; margin-bottom: 2px; }
        .slip-top p { color: #666; font-size: 12px; }
        .slip-top .tag { display:inline-block; background: linear-gradient(135deg,#1a6b3a,#27ae60); color:#fff; font-size: 11px; font-weight:800; letter-spacing:0.6px; padding: 5px 14px; border-radius: 20px; text-transform: uppercase; }
        .slip-meta { display: grid; grid-template-columns: 1fr 1fr; gap: 10px 20px; margin: 18px 0; font-size: 14px; background: #f8f9ff; border-radius: 10px; padding: 14px 16px; }
        .slip-meta .label { color: #888; }
        .slip-table th, .slip-table td { font-size: 14px; }
        .slip-table td:last-child, .slip-table th:last-child { text-align: right; }
        .net-row td { font-weight: 800; font-size: 19px; color: #1e3c72; border-top: 2px solid #1e3c72; background: #f0f4ff; }
        .slip-footer { display: flex; justify-content: space-between; align-items: flex-end; margin-top: 32px; }
        .slip-footer .sign-block { text-align: center; font-size: 12px; color: #888; }
        .slip-footer .sign-block img { height: 32px; object-fit: contain; display: block; margin: 0 auto 4px; }
        .slip-footer .sign-line { width: 130px; border-top: 1px solid #ccc; margin: 22px auto 4px; }
    </style>
</head>
<body>
<?php include BASE_PATH . '/includes/sidebar.php'; ?>
<div class="content">
    <div class="card-header no-print" style="margin-bottom:16px;">
        <div class="card-title">🧾 Salary Slip</div>
        <div>
            <a href="paid_slips.php" class="btn btn-secondary">← Back</a>
            <button onclick="window.print()" class="btn">🖨️ Print Detailed Receipt</button>
        </div>
    </div>

    <div class="slip-paper">
        <div class="slip-top">
            <div class="school-block">
                <?php if (!empty($settings['logo'])): ?>
                    <img src="<?php echo BASE_URL; ?>assets/uploads/branding/<?php echo e($settings['logo']); ?>" class="logo">
                <?php endif; ?>
                <div>
                    <h2><?php echo e($settings['school_name'] ?: APP_NAME); ?></h2>
                    <p>Salary Payment Slip</p>
                </div>
            </div>
            <span class="tag">💵 Salary Slip</span>
        </div>

        <div class="slip-meta">
            <div><span class="label">Employee:</span> <strong><?php echo e($payment['full_name']); ?></strong></div>
            <div><span class="label">Employee Code:</span> <?php echo e($payment['teacher_code']); ?></div>
            <div><span class="label">Salary Month:</span> <?php echo date('F Y', strtotime($payment['salary_month'] . '-01')); ?></div>
            <div><span class="label">Payment Date:</span> <?php echo date('d M Y', strtotime($payment['payment_date'])); ?></div>
            <div><span class="label">Payment Method:</span> <?php echo e($payment['payment_method']); ?></div>
            <div><span class="label">Receipt No:</span> <?php echo e($payment['receipt_no']); ?></div>
        </div>

        <div class="table-wrap">
        <table class="slip-table">
            <tbody>
                <tr><td>Fixed Salary</td><td><?php echo money($payment['fixed_salary']); ?></td></tr>
                <tr><td>Bonus</td><td>+ <?php echo money($payment['bonus_amount']); ?></td></tr>
                <tr><td>Deduction</td><td>- <?php echo money($payment['deduction_amount']); ?></td></tr>
                <tr class="net-row"><td>Net Salary Paid</td><td><?php echo money($payment['net_salary']); ?></td></tr>
            </tbody>
        </table>
        </div>

        <?php if ($payment['notes']): ?>
            <p style="margin-top:16px;font-size:13px;color:#666;">Note: <?php echo e($payment['notes']); ?></p>
        <?php endif; ?>

        <div class="slip-footer">
            <div class="sign-block">
                Processed by<br><strong style="color:#333;"><?php echo e($payment['paid_by_name'] ?? '—'); ?></strong>
            </div>
            <div class="sign-block">
                <?php if (!empty($settings['principal_signature'])): ?>
                    <img src="<?php echo BASE_URL; ?>assets/uploads/branding/<?php echo e($settings['principal_signature']); ?>">
                <?php else: ?>
                    <div class="sign-line"></div>
                <?php endif; ?>
                <div class="sign-line"></div>
                Authorized Signature
            </div>
        </div>
    </div>
</div>
</body>
</html>
