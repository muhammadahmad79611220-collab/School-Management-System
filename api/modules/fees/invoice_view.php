<?php
require_once __DIR__ . '/../../config/app.php';
require_perm('view', 'fees');

$pdo = getDB();
$settings = get_settings();
$id = clean_id($_GET['id'] ?? null);
if (!$id) redirect('list.php');

$stmt = $pdo->prepare(
    "SELECT fi.*, s.full_name, s.roll_no, s.guardian_name, c.class_name, sec.section_name
     FROM fee_invoices fi
     JOIN students s ON fi.student_id = s.id
     LEFT JOIN classes c ON s.class_id = c.id
     LEFT JOIN sections sec ON s.section_id = sec.id
     WHERE fi.id = ?"
);
$stmt->execute([$id]);
$invoice = $stmt->fetch();
if (!$invoice) {
    flash('error', 'Invoice not found.');
    redirect('list.php');
}

$payments = $pdo->prepare(
    "SELECT fp.*, u.full_name as received_by_name FROM fee_payments fp
     LEFT JOIN users u ON fp.received_by = u.id
     WHERE fp.invoice_id = ? ORDER BY fp.payment_date DESC, fp.id DESC"
);
$payments->execute([$id]);
$payments = $payments->fetchAll();

$balance = $invoice['amount_due'] - $invoice['amount_paid'];
$isOverdue = $invoice['due_date'] && strtotime($invoice['due_date']) < time() && $balance > 0;
$payableAfterDue = $invoice['amount_due'] + ($isOverdue ? $invoice['fine_after_due'] : 0);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Invoice #<?php echo $id; ?> – <?php echo e(APP_NAME); ?></title>
    <link rel="stylesheet" href="<?php echo BASE_URL; ?>assets/css/style.css">
    <style>
        @media print { .sidebar, .no-print { display: none !important; } .content { margin-left: 0 !important; } }
        .invoice-paper { max-width: 720px; margin: 0 auto; background: white; border-radius: 14px; padding: 30px 35px; box-shadow: 0 4px 16px rgba(0,0,0,0.06); }
        .invoice-top { display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px; padding-bottom: 16px; border-bottom: 3px solid #1e3c72; }
        .invoice-top .school-block { display: flex; align-items: center; gap: 14px; }
        .invoice-top img.logo { width: 56px; height: 56px; object-fit: cover; border-radius: 50%; border: 2px solid #e0e4ef; box-shadow: 0 2px 8px rgba(0,0,0,0.08); }
        .invoice-top h2 { color: #1e3c72; font-size: 21px; margin-bottom: 2px; letter-spacing: 0.3px; }
        .invoice-top p { font-size: 12px; color: #666; }
        .invoice-top .receipt-tag { text-align: right; }
        .invoice-top .receipt-tag .tag { display:inline-block; background: linear-gradient(135deg,#1e3c72,#2a5298); color:#fff; font-size: 11px; font-weight:800; letter-spacing:0.6px; padding: 5px 14px; border-radius: 20px; text-transform: uppercase; }
        .invoice-meta { display: grid; grid-template-columns: 1fr 1fr; gap: 8px 24px; margin-bottom: 20px; font-size: 14px; }
        .invoice-meta .label { color: #888; display: inline-block; width: 120px; }
        .bank-box { border: 1px solid #ddd; border-radius: 8px; padding: 12px 16px; margin-bottom: 20px; font-size: 13px; background: #f8f9ff; }
        .bank-box strong { color: #1e3c72; }
        .particulars-table th, .particulars-table td { font-size: 13px; }
        .particulars-table td:last-child, .particulars-table th:last-child { text-align: right; }
        .totals-box { margin-top: 16px; }
        .totals-box .row { display: flex; justify-content: space-between; padding: 6px 0; font-size: 14px; }
        .totals-box .row.total { font-weight: 800; font-size: 17px; color: #1e3c72; border-top: 2px solid #1e3c72; margin-top: 6px; padding-top: 10px; }
        .totals-box .row.payable { color: #c0392b; font-weight: 700; }
    </style>
</head>
<body>
<?php include BASE_PATH . '/includes/sidebar.php'; ?>
<div class="content">
    <div class="card-header no-print" style="margin-bottom:16px;">
        <div class="card-title">🧾 Fees Invoice</div>
        <div>
            <a href="list.php" class="btn btn-secondary">← Back</a>
            <button onclick="window.print()" class="btn">🖨️ Print Fees Invoice</button>
        </div>
    </div>

    <?php echo flash_render(); ?>

    <div class="invoice-paper">
        <div class="invoice-top">
            <div class="school-block">
                <?php if (!empty($settings['logo'])): ?>
                    <img src="<?php echo BASE_URL; ?>assets/uploads/branding/<?php echo e($settings['logo']); ?>" class="logo">
                <?php endif; ?>
                <div>
                    <h2><?php echo e($settings['school_name']); ?></h2>
                    <?php if (!empty($settings['phone'])): ?><p><?php echo e($settings['phone']); ?></p><?php endif; ?>
                    <?php if (!empty($settings['address'])): ?><p><?php echo e($settings['address']); ?></p><?php endif; ?>
                </div>
            </div>
            <div class="receipt-tag">
                <span class="tag">🧾 Fee Receipt</span>
                <p style="margin-top:6px;">Invoice #<?php echo $id; ?></p>
            </div>
        </div>

        <div class="invoice-meta">
            <div><span class="label">Student ID:</span> <?php echo e($invoice['roll_no']); ?></div>
            <div><span class="label">Sr. Particulars</span></div>
            <div><span class="label">Student Name:</span> <strong><?php echo e($invoice['full_name']); ?></strong></div>
            <div></div>
            <div><span class="label">Guardian:</span> <?php echo e($invoice['guardian_name'] ?: '—'); ?></div>
            <div></div>
            <div><span class="label">Class:</span> <?php echo e(($invoice['class_name'] ?? '—') . ' ' . ($invoice['section_name'] ?? '')); ?></div>
            <div></div>
            <div><span class="label">Fee Month:</span> <?php echo e($invoice['billing_month'] ?: '—'); ?></div>
            <div></div>
            <div><span class="label">Due Date:</span> <?php echo $invoice['due_date'] ? date('M j, Y', strtotime($invoice['due_date'])) : '—'; ?></div>
            <div></div>
        </div>

        <?php if (!empty($invoice['bank_name'])): ?>
        <div class="bank-box">
            <strong>Bank Copy</strong><br>
            Bank Name: <?php echo e($invoice['bank_name']); ?><br>
            <?php if (!empty($settings['bank_address'])): ?>Address: <?php echo e($settings['bank_address']); ?><br><?php endif; ?>
            Account#: <?php echo e($invoice['bank_account']); ?>
        </div>
        <?php endif; ?>

        <div class="table-wrap">
        <table class="particulars-table">
            <thead><tr><th>Description</th><th>Amount</th></tr></thead>
            <tbody>
                <tr><td><?php echo e($invoice['description']); ?></td><td><?php echo money($invoice['amount_due'] + $invoice['discount_amount']); ?></td></tr>
                <?php if ($invoice['discount_amount'] > 0): ?>
                <tr><td>Discount in Fee (<?php echo e($invoice['discount_percent']); ?>%)</td><td>- <?php echo money($invoice['discount_amount']); ?></td></tr>
                <?php endif; ?>
            </tbody>
        </table>
        </div>

        <div class="totals-box">
            <div class="row"><span>Total</span><span><?php echo money($invoice['amount_due']); ?></span></div>
            <div class="row"><span>Amount Paid</span><span><?php echo money($invoice['amount_paid']); ?></span></div>
            <div class="row total"><span>Balance Due</span><span><?php echo money($balance); ?></span></div>
            <?php if ($invoice['fine_after_due'] > 0): ?>
            <div class="row payable">
                <span><?php echo $isOverdue ? 'Payable After Due Date (fine applied)' : 'Payable After Due Date (if late)'; ?></span>
                <span><?php echo money($balance + $invoice['fine_after_due']); ?></span>
            </div>
            <?php endif; ?>
        </div>

        <?php if ($balance > 0 && (is_admin() || can('edit','fees'))): ?>
            <a href="record_payment.php?invoice_id=<?php echo $id; ?>" class="btn btn-success no-print" style="margin-top:18px;display:block;text-align:center;">💰 Record Payment</a>
        <?php endif; ?>
    </div>

    <div class="card" style="max-width:720px;margin:20px auto 0;">
        <div class="card-title" style="margin-bottom:14px;">Payment History</div>
        <div class="table-wrap">
        <table>
            <thead><tr><th>Date</th><th>Amount</th><th>Method</th><th>Receipt No</th><th>Received By</th></tr></thead>
            <tbody>
            <?php if (!$payments): ?>
                <tr><td colspan="5" style="text-align:center;color:#aaa;padding:16px;">No payments recorded yet.</td></tr>
            <?php endif; ?>
            <?php foreach ($payments as $p): ?>
                <tr>
                    <td><?php echo date('d M Y', strtotime($p['payment_date'])); ?></td>
                    <td><?php echo money($p['amount']); ?></td>
                    <td><?php echo e($p['payment_method']); ?></td>
                    <td><?php echo e($p['receipt_no']); ?></td>
                    <td><?php echo e($p['received_by_name'] ?? '—'); ?></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
        </div>
    </div>
</div>
</body>
</html>
