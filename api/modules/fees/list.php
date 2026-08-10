<?php
require_once __DIR__ . '/../../config/app.php';
require_perm('view', 'fees');

$pdo = getDB();
$classes = $pdo->query("SELECT id, class_name FROM classes ORDER BY sort_order")->fetchAll();

$structures = $pdo->query(
    "SELECT fs.*, c.class_name FROM fee_structures fs LEFT JOIN classes c ON fs.class_id = c.id ORDER BY c.sort_order, fs.fee_type"
)->fetchAll();

// Filters for invoices
$status = $_GET['status'] ?? '';
$search = trim($_GET['search'] ?? '');

$where = [];
$params = [];
if ($status !== '' && in_array($status, ['Unpaid','Partial','Paid','Overdue'], true)) {
    $where[] = "fi.status = ?";
    $params[] = $status;
}
if ($search !== '') {
    $where[] = "(s.full_name LIKE ? OR s.roll_no LIKE ?)";
    $params[] = "%$search%";
    $params[] = "%$search%";
}
$whereSql = $where ? 'WHERE ' . implode(' AND ', $where) : '';

$stmt = $pdo->prepare(
    "SELECT fi.*, s.full_name, s.roll_no, c.class_name
     FROM fee_invoices fi
     JOIN students s ON fi.student_id = s.id
     LEFT JOIN classes c ON s.class_id = c.id
     $whereSql
     ORDER BY fi.due_date DESC, fi.id DESC
     LIMIT 200"
);
$stmt->execute($params);
$invoices = $stmt->fetchAll();

// Quick totals
$totals = $pdo->query(
    "SELECT
        SUM(amount_due) as total_due,
        SUM(amount_paid) as total_paid,
        SUM(amount_due - amount_paid) as total_outstanding
     FROM fee_invoices"
)->fetch();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Fee Management – <?php echo e(APP_NAME); ?></title>
    <link rel="stylesheet" href="<?php echo BASE_URL; ?>assets/css/style.css">
</head>
<body>
<?php include BASE_PATH . '/includes/sidebar.php'; ?>
<div class="content">

    <div class="dashboard-stats" style="margin-bottom:22px;">
        <div class="stat-card"><h3><?php echo money($totals['total_due'] ?? 0); ?></h3><p>Total Billed</p></div>
        <div class="stat-card"><h3><?php echo money($totals['total_paid'] ?? 0); ?></h3><p>Total Collected</p></div>
        <div class="stat-card"><h3><?php echo money($totals['total_outstanding'] ?? 0); ?></h3><p>Outstanding</p></div>
    </div>

    <div class="card">
        <div class="card-header">
            <div class="card-title">💵 Fee Structures (per class)</div>
            <?php if (is_admin() || can('add','fees')): ?>
            <button class="btn" onclick="document.getElementById('structForm').style.display='block'">➕ Add Fee Type</button>
            <?php endif; ?>
        </div>

        <?php echo flash_render(); ?>

        <form id="structForm" method="POST" action="save_structure.php" style="display:none;margin-bottom:18px;" class="form-grid">
            <?php echo csrf_field(); ?>
            <div class="form-group">
                <label>Class</label>
                <select name="class_id" required>
                    <option value="">— Select —</option>
                    <?php foreach ($classes as $c): ?>
                        <option value="<?php echo $c['id']; ?>"><?php echo e($c['class_name']); ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="form-group">
                <label>Fee Type</label>
                <input type="text" name="fee_type" required maxlength="50" placeholder="e.g. Tuition, Transport">
            </div>
            <div class="form-group">
                <label>Amount</label>
                <input type="number" name="amount" required min="0" step="0.01">
            </div>
            <div class="form-group">
                <label>Frequency</label>
                <select name="frequency">
                    <option value="Monthly">Monthly</option>
                    <option value="Quarterly">Quarterly</option>
                    <option value="Annually">Annually</option>
                    <option value="One-Time">One-Time</option>
                </select>
            </div>
            <div class="form-group" style="align-self:end;">
                <button type="submit" class="btn btn-success btn-block">Save Fee Type</button>
            </div>
        </form>

        <div class="table-wrap">
        <table>
            <thead><tr><th>Class</th><th>Fee Type</th><th>Amount</th><th>Frequency</th><th>Actions</th></tr></thead>
            <tbody>
            <?php if (!$structures): ?>
                <tr><td colspan="5" style="text-align:center;color:#aaa;padding:20px;">No fee structures yet.</td></tr>
            <?php endif; ?>
            <?php foreach ($structures as $s): ?>
                <tr>
                    <td><?php echo e($s['class_name'] ?? '—'); ?></td>
                    <td><?php echo e($s['fee_type']); ?></td>
                    <td><?php echo money($s['amount']); ?></td>
                    <td><?php echo e($s['frequency']); ?></td>
                    <td>
                        <?php if (is_admin() || can('add','fees')): ?>
                        <a href="generate_invoices.php?structure_id=<?php echo $s['id']; ?>" class="btn btn-sm btn-success"
                           onclick="return confirm('Generate invoices for all active students in &quot;<?php echo e($s['class_name'] ?? 'this class'); ?>&quot; for this fee type?');">📄 Generate Invoices for Class</a>
                        <?php endif; ?>
                        <?php if (is_admin() || can('delete','fees')): ?>
                        <a href="delete_structure.php?id=<?php echo $s['id']; ?>&csrf_token=<?php echo e(csrf_token()); ?>"
                           class="btn btn-sm btn-danger" onclick="return confirm('Delete this fee structure?');">🗑️</a>
                        <?php endif; ?>
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
        </div>
    </div>

    <div class="card">
        <div class="card-header">
            <div class="card-title">🧾 Invoices</div>
            <div>
                <a href="defaulters.php" class="btn btn-secondary">⚠️ Fees Defaulters</a>
                <?php if (is_admin() || can('add','fees')): ?><a href="invoice_new.php" class="btn">➕ Manual Invoice</a><?php endif; ?>
            </div>
        </div>

        <form method="GET" style="display:flex;gap:10px;margin-bottom:16px;flex-wrap:wrap;">
            <input type="text" name="search" placeholder="Search student name/roll no" value="<?php echo e($search); ?>" style="margin-bottom:0;max-width:240px;">
            <select name="status" style="margin-bottom:0;max-width:160px;">
                <option value="">All Statuses</option>
                <?php foreach (['Unpaid','Partial','Paid','Overdue'] as $st): ?>
                    <option value="<?php echo $st; ?>" <?php echo $status===$st?'selected':''; ?>><?php echo $st; ?></option>
                <?php endforeach; ?>
            </select>
            <button type="submit" class="btn">🔍 Filter</button>
        </form>

        <div class="table-wrap">
        <table>
            <thead><tr><th>Student</th><th>Class</th><th>Description</th><th>Due</th><th>Paid</th><th>Balance</th><th>Status</th><th>Actions</th></tr></thead>
            <tbody>
            <?php if (!$invoices): ?>
                <tr><td colspan="8" style="text-align:center;color:#aaa;padding:20px;">No invoices found.</td></tr>
            <?php endif; ?>
            <?php foreach ($invoices as $inv): ?>
                <?php $balance = $inv['amount_due'] - $inv['amount_paid']; ?>
                <tr>
                    <td><?php echo e($inv['full_name']); ?> <small style="color:#999;">(<?php echo e($inv['roll_no']); ?>)</small></td>
                    <td><?php echo e($inv['class_name'] ?? '—'); ?></td>
                    <td><?php echo e($inv['description']); ?></td>
                    <td><?php echo money($inv['amount_due']); ?></td>
                    <td><?php echo money($inv['amount_paid']); ?></td>
                    <td><?php echo money($balance); ?></td>
                    <td>
                        <?php
                            $map = ['Paid'=>'badge-success','Partial'=>'badge-warning','Unpaid'=>'badge-secondary','Overdue'=>'badge-danger'];
                        ?>
                        <span class="badge <?php echo $map[$inv['status']] ?? 'badge-secondary'; ?>"><?php echo e($inv['status']); ?></span>
                    </td>
                    <td>
                        <?php if ($balance > 0 && (is_admin() || can('edit','fees'))): ?>
                            <a href="record_payment.php?invoice_id=<?php echo $inv['id']; ?>" class="btn btn-sm btn-success">💰 Record Payment</a>
                        <?php endif; ?>
                        <a href="invoice_view.php?id=<?php echo $inv['id']; ?>" class="btn btn-sm">🧾 View</a>
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
        </div>
    </div>

</div>
</body>
</html>
