<?php
require_once __DIR__ . '/../../config/app.php';
require_login();
if (!is_admin() && !can('view', 'reports')) {
    require_role('admin');
}

$pdo = getDB();
$invoices = $pdo->query(
    "SELECT s.roll_no, s.full_name, c.class_name, fi.description, fi.amount_due, fi.amount_paid,
            (fi.amount_due - fi.amount_paid) as balance, fi.status, fi.due_date
     FROM fee_invoices fi
     JOIN students s ON fi.student_id = s.id
     LEFT JOIN classes c ON s.class_id = c.id
     ORDER BY fi.due_date DESC"
)->fetchAll();

header('Content-Type: text/csv; charset=utf-8');
header('Content-Disposition: attachment; filename="fees_export_' . date('Y-m-d') . '.csv"');

$out = fopen('php://output', 'w');
fputcsv($out, ['Roll No', 'Student Name', 'Class', 'Description', 'Amount Due', 'Amount Paid', 'Balance', 'Status', 'Due Date']);
foreach ($invoices as $i) {
    fputcsv($out, [
        $i['roll_no'], $i['full_name'], $i['class_name'], $i['description'],
        $i['amount_due'], $i['amount_paid'], $i['balance'], $i['status'], $i['due_date']
    ]);
}
fclose($out);
exit;
