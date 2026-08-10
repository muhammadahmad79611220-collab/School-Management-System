<?php
require_once __DIR__ . '/../../config/app.php';
require_perm('view', 'fees');

$pdo = getDB();
$month = $_GET['month'] ?? date('Y-m');
if (!preg_match('/^\d{4}-\d{2}$/', $month)) $month = date('Y-m');

$stmt = $pdo->prepare(
    "SELECT s.id, s.full_name, s.roll_no, s.picture, c.class_name,
            fi.id as invoice_id, fi.amount_due, fi.amount_paid, fi.due_date, fi.fine_after_due,
            (fi.amount_due - fi.amount_paid) as balance
     FROM fee_invoices fi
     JOIN students s ON fi.student_id = s.id
     LEFT JOIN classes c ON s.class_id = c.id
     WHERE fi.billing_period = ? AND fi.status != 'Paid'
     ORDER BY s.full_name"
);
$stmt->execute([$month]);
$defaulters = $stmt->fetchAll();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Fees Defaulters – <?php echo e(APP_NAME); ?></title>
    <link rel="stylesheet" href="<?php echo BASE_URL; ?>assets/css/style.css">
    <style>
        @media print {
            .sidebar, .no-print { display: none !important; }
            .content { margin-left: 0 !important; padding: 10px !important; }
        }
        .defaulter-grid { display: flex; flex-wrap: wrap; gap: 16px; margin-top: 16px; }
        .defaulter-card { width: 220px; background: white; border-radius: 12px; padding: 18px; text-align: center; box-shadow: 0 3px 12px rgba(0,0,0,0.06); }
        .defaulter-card .avatar { width: 64px; height: 64px; border-radius: 50%; margin: 0 auto 10px; object-fit: cover; background: #f0f2f5; display:flex;align-items:center;justify-content:center;font-size:28px; }
        .defaulter-card .name { font-weight: 700; color: #1e3c72; margin-bottom: 2px; }
        .defaulter-card .meta { font-size: 12px; color: #888; margin-bottom: 8px; }
        .defaulter-card .balance { font-weight: 800; color: #c0392b; margin-bottom: 10px; }

        .print-only { display: none; }
        @media print { .print-only { display: block; } }
        .print-header { text-align: center; margin-bottom: 18px; }
        .print-header h2 { color: #1e3c72; margin-bottom: 2px; }
        .print-header p { color: #666; font-size: 13px; }
        .print-table th, .print-table td { font-size: 13px; }
        .print-table td:last-child, .print-table th:last-child { text-align: right; color: #c0392b; font-weight: 700; }
    </style>
</head>
<body>
<?php include BASE_PATH . '/includes/sidebar.php'; ?>
<div class="content">
    <div class="card no-print">
        <div class="card-header">
            <div class="card-title">⚠️ Fees Defaulters</div>
            <?php if ($defaulters): ?><button onclick="window.print()" class="btn">🖨️ Print List</button><?php endif; ?>
        </div>

        <?php echo flash_render(); ?>

        <form method="GET" style="margin-bottom:10px;">
            <label>Fees Month</label>
            <input type="month" name="month" value="<?php echo e($month); ?>" onchange="this.form.submit()">
        </form>

        <?php if (!$defaulters): ?>
            <p style="color:#27ae60;text-align:center;padding:30px;font-weight:600;">🎉 No defaulters for this month — all fees collected!</p>
        <?php else: ?>
        <div class="defaulter-grid">
            <?php foreach ($defaulters as $d): ?>
                <div class="defaulter-card">
                    <div class="avatar">
                        <?php if ($d['picture']): ?>
                            <img src="<?php echo BASE_URL . e(UPLOAD_URL . '/' . $d['picture']); ?>" style="width:100%;height:100%;border-radius:50%;object-fit:cover;">
                        <?php else: ?>👤<?php endif; ?>
                    </div>
                    <div class="name"><?php echo e($d['full_name']); ?></div>
                    <div class="meta"><?php echo e($d['roll_no']) . ' — ' . e($d['class_name'] ?? ''); ?></div>
                    <div class="balance"><?php echo money($d['balance']); ?> due</div>
                    <a href="invoice_view.php?id=<?php echo $d['invoice_id']; ?>" class="btn btn-sm btn-block">View Invoice</a>
                </div>
            <?php endforeach; ?>
        </div>
        <?php endif; ?>
    </div>

    <?php if ($defaulters): ?>
    <div class="print-only">
        <div class="print-header">
            <h2><?php echo e(get_settings()['school_name'] ?? APP_NAME); ?></h2>
            <p>Fees Defaulters List — <?php echo date('F Y', strtotime($month . '-01')); ?> &nbsp;|&nbsp; Printed on <?php echo date('d M Y'); ?></p>
        </div>
        <table class="print-table">
            <thead><tr><th>#</th><th>Name</th><th>Roll No</th><th>Class</th><th>Due Date</th><th>Balance</th></tr></thead>
            <tbody>
            <?php foreach ($defaulters as $i => $d): ?>
                <tr>
                    <td><?php echo $i + 1; ?></td>
                    <td><?php echo e($d['full_name']); ?></td>
                    <td><?php echo e($d['roll_no']); ?></td>
                    <td><?php echo e($d['class_name'] ?? '—'); ?></td>
                    <td><?php echo $d['due_date'] ? date('d M Y', strtotime($d['due_date'])) : '—'; ?></td>
                    <td><?php echo money($d['balance']); ?></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
            <tfoot>
                <tr><td colspan="5" style="text-align:right;font-weight:700;">Total Outstanding</td><td><?php echo money(array_sum(array_column($defaulters, 'balance'))); ?></td></tr>
            </tfoot>
        </table>
    </div>
    <?php endif; ?>
</div>
</body>
</html>
