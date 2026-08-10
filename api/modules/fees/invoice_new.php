<?php
require_once __DIR__ . '/../../config/app.php';
require_perm('add', 'fees');

$pdo = getDB();
$settings = get_settings();
$students = $pdo->query(
    "SELECT s.id, s.full_name, s.roll_no, s.discount_percent, c.class_name
     FROM students s LEFT JOIN classes c ON s.class_id = c.id WHERE s.status='Active' ORDER BY s.full_name"
)->fetchAll();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verify();
    $studentId = clean_id($_POST['student_id'] ?? null);
    $description = trim($_POST['description'] ?? '');
    $billingMonth = trim($_POST['billing_month'] ?? '');
    $amount = (float)($_POST['amount_due'] ?? 0);
    $discountPercent = (float)($_POST['discount_percent'] ?? 0);
    $fineAfterDue = (float)($_POST['fine_after_due'] ?? $settings['default_fine_after_due'] ?? 0);
    $dueDate = $_POST['due_date'] ?? date('Y-m-d', strtotime('+15 days'));
    $bankName = trim($_POST['bank_name'] ?? $settings['bank_name'] ?? '');
    $bankAccount = trim($_POST['bank_account'] ?? $settings['bank_account'] ?? '');

    if (!$studentId || $description === '' || $amount <= 0) {
        flash('error', 'Please select a student, description, and an amount greater than 0.');
    } elseif ($discountPercent < 0 || $discountPercent > 100) {
        flash('error', 'Discount must be between 0 and 100%.');
    } else {
        $discountAmount = round($amount * $discountPercent / 100, 2);
        $netAmount = $amount - $discountAmount;

        $pdo->prepare(
            "INSERT INTO fee_invoices (student_id, description, billing_month, amount_due, discount_percent, discount_amount,
                fine_after_due, due_date, billing_period, bank_name, bank_account, status)
             VALUES (?,?,?,?,?,?,?,?,?,?,?, 'Unpaid')"
        )->execute([
            $studentId, $description, $billingMonth, $netAmount, $discountPercent, $discountAmount,
            $fineAfterDue, $dueDate, date('Y-m'), $bankName, $bankAccount
        ]);

        $newId = $pdo->lastInsertId();
        log_activity('invoice_created_manual', "student_id=$studentId amount=$netAmount");
        flash('success', 'Invoice created.');
        redirect("invoice_view.php?id=$newId");
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>New Invoice – <?php echo e(APP_NAME); ?></title>
    <link rel="stylesheet" href="<?php echo BASE_URL; ?>assets/css/style.css">
</head>
<body>
<?php include BASE_PATH . '/includes/sidebar.php'; ?>
<div class="content">
    <div class="card" style="max-width:640px;">
        <div class="card-title" style="margin-bottom:18px;">➕ Generate Fees Invoice</div>
        <?php echo flash_render(); ?>
        <form method="POST">
            <?php echo csrf_field(); ?>
            <div class="form-grid">
                <div class="form-group">
                    <label>Student</label>
                    <select name="student_id" id="student_id" required onchange="applyDiscount()">
                        <option value="">— Select —</option>
                        <?php foreach ($students as $s): ?>
                            <option value="<?php echo $s['id']; ?>" data-discount="<?php echo e($s['discount_percent']); ?>">
                                <?php echo e($s['full_name'] . ' (' . $s['roll_no'] . ') - ' . ($s['class_name'] ?? '')); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="form-group">
                    <label>Billing Month</label>
                    <input type="text" name="billing_month" placeholder="e.g. June 2026" value="<?php echo date('F Y'); ?>">
                </div>
                <div class="form-group">
                    <label>Description</label>
                    <input type="text" name="description" required maxlength="150" placeholder="e.g. Monthly Fee">
                </div>
                <div class="form-group">
                    <label>Amount Due (before discount)</label>
                    <input type="number" name="amount_due" id="amount_due" required min="0.01" step="0.01">
                </div>
                <div class="form-group">
                    <label>Discount (%)</label>
                    <input type="number" name="discount_percent" id="discount_percent" min="0" max="100" step="0.01" value="0">
                </div>
                <div class="form-group">
                    <label>Fine After Due Date</label>
                    <input type="number" name="fine_after_due" min="0" step="0.01" value="<?php echo e($settings['default_fine_after_due'] ?? '0'); ?>">
                </div>
                <div class="form-group">
                    <label>Due Date</label>
                    <input type="date" name="due_date" value="<?php echo date('Y-m-d', strtotime('+15 days')); ?>">
                </div>
                <div class="form-group">
                    <label>Bank Name</label>
                    <input type="text" name="bank_name" value="<?php echo e($settings['bank_name'] ?? ''); ?>">
                </div>
                <div class="form-group">
                    <label>Account Number</label>
                    <input type="text" name="bank_account" value="<?php echo e($settings['bank_account'] ?? ''); ?>">
                </div>
            </div>
            <button type="submit" class="btn">💾 Generate Invoice</button>
            <a href="list.php" class="btn btn-secondary">Cancel</a>
        </form>
    </div>
</div>
<script>
function applyDiscount() {
    const sel = document.getElementById('student_id');
    const opt = sel.options[sel.selectedIndex];
    const discount = opt ? opt.dataset.discount : '0';
    if (discount && parseFloat(discount) > 0) {
        document.getElementById('discount_percent').value = discount;
    }
}
</script>
</body>
</html>
