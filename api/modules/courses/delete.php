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
    $pdo->prepare("DELETE FROM courses WHERE id = ?")->execute([$id]);
    log_activity('course_deleted', "id=$id");
    flash('success', 'Subject deleted.');
}
redirect('list.php');
