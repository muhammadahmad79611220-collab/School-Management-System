<?php
require_once __DIR__ . '/../../config/app.php';
require_login();
header('Content-Type: application/json');

$classId = clean_id($_GET['class_id'] ?? null);
if (!$classId) {
    echo json_encode([]);
    exit;
}

$pdo = getDB();
$stmt = $pdo->prepare("SELECT id, section_name FROM sections WHERE class_id = ? ORDER BY section_name");
$stmt->execute([$classId]);
echo json_encode($stmt->fetchAll());
