<?php
require_once __DIR__ . '/../../config/app.php';
require_login();
if (!is_admin() && !can('delete', 'students')) {
    require_role('admin'); // 403s with a clear message
}

// This is a state-changing GET link with a token appended (?id=&csrf_token=)
// to keep the "click to delete" UX from the original app, but still verify
// the token matches the current session before doing anything destructive.
$token = $_GET['csrf_token'] ?? '';
if (!hash_equals($_SESSION['csrf_token'] ?? '', $token)) {
    http_response_code(403);
    die('Security check failed. Please go back and try again.');
}

$id = clean_id($_GET['id'] ?? null);
if ($id) {
    $pdo = getDB();
    $stmt = $pdo->prepare("DELETE FROM students WHERE id = ?");
    $stmt->execute([$id]);
    log_activity('student_deleted', "id=$id");
    flash('success', 'Student deleted.');
} else {
    flash('error', 'Invalid student ID.');
}

redirect('list.php');
