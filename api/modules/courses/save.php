<?php
require_once __DIR__ . '/../../config/app.php';
require_role('admin');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') redirect('list.php');
csrf_verify();

$code   = trim($_POST['course_code'] ?? '');
$name   = trim($_POST['course_name'] ?? '');
$classId= clean_id($_POST['class_id'] ?? null);
$type   = $_POST['course_type'] ?? 'Compulsory';
$credits= (int)($_POST['credits'] ?? 0);

if (!in_array($type, ['Compulsory','Elective'], true)) $type = 'Compulsory';

if ($code === '' || $name === '') {
    flash('error', 'Subject code and name are required.');
} else {
    $pdo = getDB();
    try {
        $stmt = $pdo->prepare("INSERT INTO courses (course_code, course_name, course_type, class_id, credits) VALUES (?,?,?,?,?)");
        $stmt->execute([$code, $name, $type, $classId, $credits]);
        log_activity('course_created', "code=$code");
        flash('success', 'Subject added.');
    } catch (PDOException $e) {
        if ($e->getCode() === '23000') {
            flash('error', 'This subject code already exists.');
        } else {
            error_log($e->getMessage());
            flash('error', 'Could not save subject.');
        }
    }
}
redirect('list.php');
