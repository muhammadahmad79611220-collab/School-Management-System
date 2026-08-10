<?php
require_once __DIR__ . '/../../config/app.php';
require_perm('add', 'fees');

$pdo = getDB();
$settings = get_settings();
$structureId = clean_id($_GET['structure_id'] ?? null);
if (!$structureId) redirect('list.php');

$stmt = $pdo->prepare("SELECT * FROM fee_structures WHERE id = ?");
$stmt->execute([$structureId]);
$structure = $stmt->fetch();
if (!$structure) {
    flash('error', 'Fee structure not found.');
    redirect('list.php');
}

$billingPeriod = match ($structure['frequency']) {
    'Monthly' => date('Y-m'),
    'Quarterly' => date('Y') . '-Q' . ceil(date('n') / 3),
    'Annually' => date('Y'),
    default => 'one-time',
};
$billingMonthLabel = date('F Y');

$dueDate = date('Y-m-d', strtotime('+15 days'));
$defaultFine = (float)($settings['default_fine_after_due'] ?? 0);

$students = $pdo->prepare("SELECT id, discount_percent FROM students WHERE class_id = ? AND status = 'Active'");
$students->execute([$structure['class_id']]);
$students = $students->fetchAll();

// Avoid duplicate invoices for the same structure + billing period.
$checkStmt = $pdo->prepare("SELECT id FROM fee_invoices WHERE student_id = ? AND fee_structure_id = ? AND billing_period = ?");
$insertStmt = $pdo->prepare(
    "INSERT INTO fee_invoices (student_id, fee_structure_id, description, billing_month, amount_due,
        discount_percent, discount_amount, fine_after_due, due_date, billing_period, bank_name, bank_account, status)
     VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'Unpaid')"
);

$created = 0;
$skipped = 0;
foreach ($students as $s) {
    $checkStmt->execute([$s['id'], $structureId, $billingPeriod]);
    if ($checkStmt->fetch()) { $skipped++; continue; }

    $desc = $structure['fee_type'] . ' — ' . $billingPeriod;
    $discountPercent = (float)($s['discount_percent'] ?? 0);
    $discountAmount = round($structure['amount'] * $discountPercent / 100, 2);
    $netAmount = $structure['amount'] - $discountAmount;

    $insertStmt->execute([
        $s['id'], $structureId, $desc, $billingMonthLabel, $netAmount,
        $discountPercent, $discountAmount, $defaultFine, $dueDate, $billingPeriod,
        $settings['bank_name'] ?? '', $settings['bank_account'] ?? ''
    ]);
    $created++;
}

log_activity('invoices_generated', "structure_id=$structureId created=$created skipped=$skipped");
flash('success', "Generated $created invoice(s). Skipped $skipped (already existed for this period).");
redirect('list.php');
