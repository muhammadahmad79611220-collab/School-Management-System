<?php
require_once __DIR__ . '/../../config/app.php';
require_login();
header('Content-Type: application/json');

function out(array $data, int $code = 200): void {
    http_response_code($code);
    echo json_encode($data);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    out(['ok' => false, 'message' => 'Invalid request method.'], 405);
}

$postedToken = $_POST['csrf_token'] ?? '';
if (!$postedToken || !hash_equals($_SESSION['csrf_token'] ?? '', $postedToken)) {
    out(['ok' => false, 'message' => 'Your session expired. Please reload this page and try again.'], 403);
}

$pdo = getDB();
$token = trim($_POST['token'] ?? '');
$status = $_POST['status'] ?? 'Present';
if (!in_array($status, ['Present', 'Late'], true)) $status = 'Present';

if ($token === '') {
    out(['ok' => false, 'message' => 'No code detected.']);
}

// Look up the student behind this token.
$stmt = $pdo->prepare(
    "SELECT s.id, s.full_name, s.roll_no, s.picture, s.class_id, s.section_id, s.status,
            c.class_name, sec.section_name
     FROM students s
     LEFT JOIN classes c ON s.class_id = c.id
     LEFT JOIN sections sec ON s.section_id = sec.id
     WHERE s.scan_token = ?"
);
$stmt->execute([$token]);
$student = $stmt->fetch();

if (!$student) {
    out(['ok' => false, 'message' => 'Unrecognized ID card. Please re-print this student\'s card.']);
}
if ($student['status'] !== 'Active') {
    out(['ok' => false, 'message' => $student['full_name'] . ' is not an active student (' . $student['status'] . ').']);
}

// Authorization: admins can mark any section; teachers only their own assigned sections.
if (!is_admin()) {
    $allowed = $pdo->prepare("SELECT id FROM sections WHERE id = ? AND class_teacher_id = ?");
    $allowed->execute([$student['section_id'], current_teacher_id()]);
    if (!$allowed->fetch() && !can('add', 'attendance')) {
        out(['ok' => false, 'message' => 'You are not authorized to mark attendance for this student\'s section.'], 403);
    }
}

$today = date('Y-m-d');

// Has this student already been scanned today?
$existing = $pdo->prepare("SELECT id, status FROM attendance WHERE student_id = ? AND attendance_date = ?");
$existing->execute([$student['id'], $today]);
$row = $existing->fetch();

if ($row) {
    out([
        'ok' => true,
        'duplicate' => true,
        'message' => $student['full_name'] . ' was already marked "' . $row['status'] . '" today.',
        'student' => [
            'name' => $student['full_name'],
            'roll_no' => $student['roll_no'],
            'class' => trim(($student['class_name'] ?? '') . ' ' . ($student['section_name'] ?? '')),
            'picture' => !empty($student['picture']) ? BASE_URL . UPLOAD_URL . '/' . $student['picture'] : null,
            'status' => $row['status'],
        ],
    ]);
}

$pdo->prepare(
    "INSERT INTO attendance (student_id, section_id, attendance_date, status, marked_by, remarks) VALUES (?,?,?,?,?,?)"
)->execute([$student['id'], $student['section_id'], $today, $status, $_SESSION['user_id'] ?? null, 'Marked via QR scanner']);

log_activity('attendance_scanned', "student_id={$student['id']} status=$status");

out([
    'ok' => true,
    'duplicate' => false,
    'message' => $student['full_name'] . ' marked ' . $status . '.',
    'student' => [
        'name' => $student['full_name'],
        'roll_no' => $student['roll_no'],
        'class' => trim(($student['class_name'] ?? '') . ' ' . ($student['section_name'] ?? '')),
        'picture' => !empty($student['picture']) ? BASE_URL . UPLOAD_URL . '/' . $student['picture'] : null,
        'status' => $status,
    ],
]);
