<?php
require_once __DIR__ . '/../../config/app.php';
require_perm('edit', 'fees');

$pdo = getDB();
$invoiceId = clean_id($_GET['invoice_id'] ?? $_POST['invoice_id'] ?? null);
if (!$invoiceId) redirect('list.php');

$stmt = $pdo->prepare(
    "SELECT fi.*, s.full_name, s.roll_no FROM fee_invoices fi JOIN students s ON fi.student_id = s.id WHERE fi.id = ?"
);
$stmt->execute([$invoiceId]);
$invoice = $stmt->fetch();
if (!$invoice) {
    flash('error', 'Invoice not found.');
    redirect('list.php');
}

$balance = $invoice['amount_due'] - $invoice['amount_paid'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verify();
    $amount = (float)($_POST['amount'] ?? 0);
    $method = trim($_POST['payment_method'] ?? 'Cash');
    $paymentDate = $_POST['payment_date'] ?? date('Y-m-d');

    if ($amount <= 0 || $amount > $balance + 0.01) {
        flash('error', 'Payment amount must be greater than 0 and not exceed the outstanding balance (' . money($balance) . ').');
    } else {
        $pdo->beginTransaction();
        try {
            $receiptNo = 'RCPT-' . date('Ymd') . '-' . strtoupper(bin2hex(random_bytes(3)));
            $pdo->prepare(
                "INSERT INTO fee_payments (invoice_id, amount, payment_date, payment_method, receipt_no, received_by)
                 VALUES (?,?,?,?,?,?)"
            )->execute([$invoiceId, $amount, $paymentDate, $method, $receiptNo, $_SESSION['user_id']]);

            $newPaid = $invoice['amount_paid'] + $amount;
            $newStatus = $newPaid >= $invoice['amount_due'] ? 'Paid' : 'Partial';
            $pdo->prepare("UPDATE fee_invoices SET amount_paid = ?, status = ? WHERE id = ?")
                ->execute([$newPaid, $newStatus, $invoiceId]);

            $pdo->commit();
            log_activity('payment_recorded', "invoice_id=$invoiceId amount=$amount receipt=$receiptNo");
            flash('success', "Payment of " . money($amount) . " recorded. Receipt: $receiptNo");
            redirect("invoice_view.php?id=$invoiceId");
        } catch (Throwable $e) {
            $pdo->rollBack();
            error_log($e->getMessage());
            flash('error', 'Could not record payment. Please try again.');
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Record Payment – <?php echo e(APP_NAME); ?></title>
    <link rel="stylesheet" href="<?php echo BASE_URL; ?>assets/css/style.css">
</head>
<body>
<?php include BASE_PATH . '/includes/sidebar.php'; ?>
<div class="content">
    <div class="card" style="max-width:480px;">
        <div class="card-title" style="margin-bottom:18px;">💰 Record Payment</div>
        <p style="color:#555;margin-bottom:16px;">
            <?php echo e($invoice['full_name']); ?> (<?php echo e($invoice['roll_no']); ?>)<br>
            <?php echo e($invoice['description']); ?><br>
            Outstanding balance: <strong><?php echo money($balance); ?></strong>
        </p>
        <?php echo flash_render(); ?>
        <form method="POST">
            <?php echo csrf_field(); ?>
            <input type="hidden" name="invoice_id" value="<?php echo $invoiceId; ?>">
            <label>Amount</label>
            <input type="number" name="amount" required min="0.01" max="<?php echo $balance; ?>" step="0.01" value="<?php echo $balance; ?>">
            <label>Payment Date</label>
            <input type="date" name="payment_date" value="<?php echo date('Y-m-d'); ?>">
            <label>Payment Method</label>
            <select name="payment_method">
                <option value="Cash">Cash</option>
                <option value="Bank Transfer">Bank Transfer</option>
                <option value="Cheque">Cheque</option>
                <option value="Online">Online</option>
            </select>
            <button type="submit" class="btn">💾 Record Payment</button>
            <a href="invoice_view.php?id=<?php echo $invoiceId; ?>" class="btn btn-secondary">Cancel</a>
        </form>
    </div>
</div>
</body>
</html>
