<?php
require_once __DIR__ . '/../../config/app.php';
require_perm('add', 'fees');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') redirect('list.php');
csrf_verify();

$classId = clean_id($_POST['class_id'] ?? null);
$feeType = trim($_POST['fee_type'] ?? '');
$amount  = (float)($_POST['amount'] ?? 0);
$freq    = $_POST['frequency'] ?? 'Monthly';

if (!in_array($freq, ['Monthly','Quarterly','Annually','One-Time'], true)) $freq = 'Monthly';

if (!$classId || $feeType === '' || $amount <= 0) {
    flash('error', 'Please provide a class, fee type, and amount greater than 0.');
} else {
    $pdo = getDB();
    $pdo->prepare("INSERT INTO fee_structures (class_id, fee_type, amount, frequency) VALUES (?,?,?,?)")
        ->execute([$classId, $feeType, $amount, $freq]);
    log_activity('fee_structure_created', "class_id=$classId type=$feeType");
    flash('success', 'Fee structure added.');
}
redirect('list.php');
