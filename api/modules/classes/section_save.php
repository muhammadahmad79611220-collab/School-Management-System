<?php
require_once __DIR__ . '/../../config/app.php';
require_role('admin');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') redirect('list.php');
csrf_verify();

$classId = clean_id($_POST['class_id'] ?? null);
$name    = trim($_POST['section_name'] ?? '');
$teacherId = clean_id($_POST['class_teacher_id'] ?? null);

if (!$classId || $name === '') {
    flash('error', 'Class and section name are required.');
} else {
    $pdo = getDB();
    try {
        $stmt = $pdo->prepare("INSERT INTO sections (class_id, section_name, class_teacher_id) VALUES (?, ?, ?)");
        $stmt->execute([$classId, $name, $teacherId]);
        log_activity('section_created', "class_id=$classId section=$name");
        flash('success', 'Section added.');
    } catch (PDOException $e) {
        if ($e->getCode() === '23000') {
            flash('error', 'This section already exists for the selected class.');
        } else {
            error_log($e->getMessage());
            flash('error', 'Could not save section.');
        }
    }
}
redirect('list.php');
