<?php
require_once __DIR__ . '/../../config/app.php';
require_role('admin');

$pdo = getDB();
$id = clean_id($_GET['id'] ?? $_POST['id'] ?? null);
if (!$id) redirect('list.php');

$stmt = $pdo->prepare("SELECT * FROM students WHERE id = ?");
$stmt->execute([$id]);
$student = $stmt->fetch();
if (!$student) {
    flash('error', 'Student not found.');
    redirect('list.php');
}

$userStmt = $pdo->prepare("SELECT * FROM users WHERE student_id = ? LIMIT 1");
$userStmt->execute([$id]);
$user = $userStmt->fetch();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verify();
    $newPassword = bin2hex(random_bytes(4));
    $hash = password_hash($newPassword, PASSWORD_DEFAULT);

    if ($user) {
        $pdo->prepare("UPDATE users SET password = ?, must_change_password = 1, failed_attempts = 0, locked_until = NULL WHERE id = ?")
            ->execute([$hash, $user['id']]);
        $username = $user['username'];
    } else {
        // No login existed yet for this student — create one now.
        $username = strtolower(preg_replace('/\s+/', '', $student['roll_no']));
        $dup = $pdo->prepare("SELECT id FROM users WHERE username = ?");
        $dup->execute([$username]);
        if ($username === '' || $dup->fetch()) $username = 'std' . $id;
        $pdo->prepare(
            "INSERT INTO users (username, password, role, full_name, student_id, must_change_password) VALUES (?,?,?,?,?,1)"
        )->execute([$username, $hash, 'student', $student['full_name'], $id]);
    }

    log_activity('student_password_reset', "student_id=$id username=$username");

    $_SESSION['new_login_card'] = [
        'student_id' => $id,
        'full_name'  => $student['full_name'],
        'roll_no'    => $student['roll_no'],
        'username'   => $username,
        'password'   => $newPassword,
    ];
    flash('success', 'Password reset. New login credentials are ready below.');
}

redirect('view.php?id=' . $id);
