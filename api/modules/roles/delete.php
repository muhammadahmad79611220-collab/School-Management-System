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
    $stmt = $pdo->prepare("SELECT is_system FROM roles WHERE id = ?");
    $stmt->execute([$id]);
    $row = $stmt->fetch();
    if ($row && !$row['is_system']) {
        $pdo->prepare("DELETE FROM roles WHERE id = ?")->execute([$id]);
        log_activity('role_deleted', "id=$id");
        flash('success', 'Role deleted.');
    } else {
        flash('error', 'System roles cannot be deleted.');
    }
}
redirect('list.php');
