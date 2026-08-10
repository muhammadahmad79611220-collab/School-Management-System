<?php
require_once __DIR__ . '/../../config/app.php';
require_role('admin');

$token = $_GET['csrf_token'] ?? '';
if (!hash_equals($_SESSION['csrf_token'] ?? '', $token)) {
    http_response_code(403);
    die('Security check failed. Please go back and try again.');
}

$id = clean_id($_GET['id'] ?? null);
if ($id) {
    $pdo = getDB();
    $pdo->prepare("DELETE FROM sections WHERE id = ?")->execute([$id]);
    log_activity('section_deleted', "id=$id");
    flash('success', 'Section deleted.');
}
redirect('list.php');
