<?php
require_once __DIR__ . '/../../config/app.php';
require_role('admin');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') redirect('list.php');
csrf_verify();

$name = trim($_POST['exam_name'] ?? '');
$classId = clean_id($_POST['class_id'] ?? null);
$examDate = $_POST['exam_date'] ?? null;
$year = trim($_POST['academic_year'] ?? '');

if ($name === '' || !$classId) {
    flash('error', 'Exam name and class are required.');
} else {
    $pdo = getDB();
    $stmt = $pdo->prepare("INSERT INTO exams (exam_name, class_id, exam_date, academic_year) VALUES (?,?,?,?)");
    $stmt->execute([$name, $classId, $examDate ?: null, $year]);
    $examId = $pdo->lastInsertId();
    log_activity('exam_created', "name=$name");
    flash('success', 'Exam created. Now set up subjects and max marks for it.');
    redirect("setup_subjects.php?exam_id=$examId");
}
redirect('list.php');
