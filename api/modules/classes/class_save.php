<?php
require_once __DIR__ . '/../../config/app.php';
require_role('admin');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') redirect('list.php');
csrf_verify();

$name = trim($_POST['class_name'] ?? '');
$sort = (int)($_POST['sort_order'] ?? 0);

if ($name === '') {
    flash('error', 'Class name is required.');
} else {
    $pdo = getDB();
    $stmt = $pdo->prepare("INSERT INTO classes (class_name, sort_order) VALUES (?, ?)");
    $stmt->execute([$name, $sort]);
    log_activity('class_created', "name=$name");
    flash('success', 'Class added.');
}
redirect('list.php');
