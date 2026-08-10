<?php
require_once __DIR__ . '/../../config/app.php';
require_role('admin');

$pdo = getDB();
$teachers = $pdo->query("SELECT id, full_name, teacher_code, fixed_salary FROM teachers WHERE is_active=1 ORDER BY full_name")->fetchAll();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verify();
    $teacherId = clean_id($_POST['teacher_id'] ?? null);
    $salaryMonth = $_POST['salary_month'] ?? '';
    $fixedSalary = (float)($_POST['fixed_salary'] ?? 0);
    $bonus = (float)($_POST['bonus_amount'] ?? 0);
    $deduction = (float)($_POST['deduction_amount'] ?? 0);
    $paymentDate = $_POST['payment_date'] ?? date('Y-m-d');
    $method = trim($_POST['payment_method'] ?? 'Cash');
    $notes = trim($_POST['notes'] ?? '');

    if (!$teacherId || !preg_match('/^\d{4}-\d{2}$/', $salaryMonth) || $fixedSalary < 0) {
        flash('error', 'Please select a teacher, a valid salary month, and a fixed salary of 0 or more.');
    } elseif ($bonus < 0 || $deduction < 0) {
        flash('error', 'Bonus and deduction amounts cannot be negative.');
    } else {
        $netSalary = $fixedSalary + $bonus - $deduction;
        if ($netSalary < 0) {
            flash('error', 'Net salary cannot be negative — check the deduction amount.');
        } else {
            $existing = $pdo->prepare("SELECT id FROM salary_payments WHERE teacher_id = ? AND salary_month = ?");
            $existing->execute([$teacherId, $salaryMonth]);
            if ($existing->fetch()) {
                flash('error', 'Salary for this teacher has already been paid for this month. Check Salary Paid Slip to view it.');
            } else {
                $receiptNo = 'SAL-' . date('Ymd') . '-' . strtoupper(bin2hex(random_bytes(3)));
                $pdo->prepare(
                    "INSERT INTO salary_payments (teacher_id, salary_month, fixed_salary, bonus_amount, deduction_amount,
                        net_salary, payment_date, payment_method, receipt_no, notes, paid_by)
                     VALUES (?,?,?,?,?,?,?,?,?,?,?)"
                )->execute([$teacherId, $salaryMonth, $fixedSalary, $bonus, $deduction, $netSalary, $paymentDate, $method, $receiptNo, $notes, $_SESSION['user_id']]);

                $newId = $pdo->lastInsertId();
                log_activity('salary_paid', "teacher_id=$teacherId month=$salaryMonth net=$netSalary");
                flash('success', 'Salary payment recorded.');
                redirect("slip.php?id=$newId");
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Pay Salary – <?php echo e(APP_NAME); ?></title>
    <link rel="stylesheet" href="<?php echo BASE_URL; ?>assets/css/style.css">
</head>
<body>
<?php include BASE_PATH . '/includes/sidebar.php'; ?>
<div class="content">
    <div class="card" style="max-width:560px;">
        <div class="card-header">
            <div class="card-title">💵 Pay Employee Salary</div>
            <a href="paid_slips.php" class="btn btn-secondary">📋 Salary History</a>
        </div>

        <?php echo flash_render(); ?>

        <?php if (!$teachers): ?>
            <p style="color:#888;">No active teachers found. Add a teacher first.</p>
        <?php else: ?>
        <form method="POST">
            <?php echo csrf_field(); ?>
            <div class="form-grid">
                <div class="form-group">
                    <label>Teacher</label>
                    <select name="teacher_id" id="teacher_id" required onchange="fillSalary()">
                        <option value="">— Select —</option>
                        <?php foreach ($teachers as $t): ?>
                            <option value="<?php echo $t['id']; ?>" data-salary="<?php echo e($t['fixed_salary']); ?>" data-code="<?php echo e($t['teacher_code']); ?>">
                                <?php echo e($t['full_name'] . ' (' . $t['teacher_code'] . ')'); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="form-group">
                    <label>Salary Month</label>
                    <input type="month" name="salary_month" required value="<?php echo date('Y-m'); ?>">
                </div>
                <div class="form-group">
                    <label>Payment Date</label>
                    <input type="date" name="payment_date" value="<?php echo date('Y-m-d'); ?>">
                </div>
                <div class="form-group">
                    <label>Fixed Salary</label>
                    <input type="number" name="fixed_salary" id="fixed_salary" required min="0" step="0.01">
                </div>
                <div class="form-group">
                    <label>Any Bonus</label>
                    <input type="number" name="bonus_amount" min="0" step="0.01" value="0" placeholder="Bonus amount">
                </div>
                <div class="form-group">
                    <label>Any Deduction</label>
                    <input type="number" name="deduction_amount" min="0" step="0.01" value="0" placeholder="Deduction amount">
                </div>
                <div class="form-group">
                    <label>Payment Method</label>
                    <select name="payment_method">
                        <option value="Cash">Cash</option>
                        <option value="Bank Transfer">Bank Transfer</option>
                        <option value="Cheque">Cheque</option>
                    </select>
                </div>
            </div>
            <div class="form-group">
                <label>Notes (optional)</label>
                <textarea name="notes" rows="2"></textarea>
            </div>
            <button type="submit" class="btn">✅ Submit Salary</button>
        </form>
        <?php endif; ?>
    </div>
</div>
<script>
function fillSalary() {
    const sel = document.getElementById('teacher_id');
    const opt = sel.options[sel.selectedIndex];
    document.getElementById('fixed_salary').value = opt ? opt.dataset.salary : '';
}
</script>
</body>
</html>
