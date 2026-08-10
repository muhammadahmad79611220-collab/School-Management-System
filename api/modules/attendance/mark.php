<?php
require_once __DIR__ . '/../../config/app.php';
require_login();

$pdo = getDB();

// Which sections can this user mark attendance for?
if (is_admin()) {
    $sections = $pdo->query(
        "SELECT sec.id, sec.section_name, c.class_name
         FROM sections sec LEFT JOIN classes c ON sec.class_id = c.id
         ORDER BY c.sort_order, sec.section_name"
    )->fetchAll();
} else {
    $stmt = $pdo->prepare(
        "SELECT sec.id, sec.section_name, c.class_name
         FROM sections sec LEFT JOIN classes c ON sec.class_id = c.id
         WHERE sec.class_teacher_id = ?
         ORDER BY c.sort_order, sec.section_name"
    );
    $stmt->execute([current_teacher_id()]);
    $sections = $stmt->fetchAll();
}

$sectionId = clean_id($_GET['section_id'] ?? ($sections[0]['id'] ?? null));
$date = $_GET['date'] ?? date('Y-m-d');
if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) $date = date('Y-m-d');

// Authorization: confirm this teacher actually owns the requested section.
$allowedIds = array_column($sections, 'id');
if ($sectionId && !in_array($sectionId, $allowedIds, true)) {
    flash('error', 'You do not have access to that section.');
    $sectionId = $sections[0]['id'] ?? null;
}

$students = [];
$existingAttendance = [];
if ($sectionId) {
    $stmt = $pdo->prepare("SELECT id, full_name, roll_no FROM students WHERE section_id = ? AND status='Active' ORDER BY full_name");
    $stmt->execute([$sectionId]);
    $students = $stmt->fetchAll();

    $stmt2 = $pdo->prepare("SELECT student_id, status FROM attendance WHERE section_id = ? AND attendance_date = ?");
    $stmt2->execute([$sectionId, $date]);
    foreach ($stmt2->fetchAll() as $row) {
        $existingAttendance[$row['student_id']] = $row['status'];
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verify();
    $postSectionId = clean_id($_POST['section_id'] ?? null);
    $postDate = $_POST['attendance_date'] ?? '';

    if (!in_array($postSectionId, $allowedIds, true)) {
        flash('error', 'You do not have permission to mark attendance for that section.');
        redirect('mark.php');
    }
    if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $postDate)) {
        flash('error', 'Invalid date.');
        redirect('mark.php?section_id=' . $postSectionId);
    }

    $statuses = $_POST['status'] ?? [];
    $stmtIns = $pdo->prepare(
        "INSERT INTO attendance (student_id, section_id, attendance_date, status, marked_by)
         VALUES (?, ?, ?, ?, ?)
         ON DUPLICATE KEY UPDATE status = VALUES(status), marked_by = VALUES(marked_by)"
    );

    $count = 0;
    foreach ($statuses as $studentId => $status) {
        $studentId = clean_id($studentId);
        if (!$studentId) continue;
        if (!in_array($status, ['Present','Absent','Leave','Late'], true)) continue;
        $stmtIns->execute([$studentId, $postSectionId, $postDate, $status, $_SESSION['user_id']]);
        $count++;
    }

    log_activity('attendance_marked', "section_id=$postSectionId date=$postDate count=$count");
    flash('success', "Attendance saved for $count student(s).");
    redirect("mark.php?section_id=$postSectionId&date=$postDate");
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Mark Attendance – <?php echo e(APP_NAME); ?></title>
    <link rel="stylesheet" href="<?php echo BASE_URL; ?>assets/css/style.css">
</head>
<body>
<?php include BASE_PATH . '/includes/sidebar.php'; ?>
<div class="content">
    <div class="card">
        <div class="card-header">
            <div class="card-title">✅ Mark Attendance</div>
            <a href="report.php" class="btn btn-secondary">📊 View Reports</a>
        </div>

        <?php echo flash_render(); ?>

        <?php if (!$sections): ?>
            <p style="color:#888;">You are not assigned as a class teacher for any section yet. Contact the administrator.</p>
        <?php else: ?>

        <form method="GET" style="display:flex; gap:10px; margin-bottom:20px; flex-wrap:wrap; align-items:flex-end;">
            <div>
                <label>Section</label>
                <select name="section_id" onchange="this.form.submit()">
                    <?php foreach ($sections as $s): ?>
                        <option value="<?php echo $s['id']; ?>" <?php echo $sectionId == $s['id'] ? 'selected' : ''; ?>>
                            <?php echo e($s['class_name'] . ' - ' . $s['section_name']); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div>
                <label>Date</label>
                <input type="date" name="date" value="<?php echo e($date); ?>" max="<?php echo date('Y-m-d'); ?>" onchange="this.form.submit()">
            </div>
        </form>

        <?php if ($students): ?>
        <form method="POST">
            <?php echo csrf_field(); ?>
            <input type="hidden" name="section_id" value="<?php echo $sectionId; ?>">
            <input type="hidden" name="attendance_date" value="<?php echo e($date); ?>">

            <div class="table-wrap">
            <table>
                <thead><tr><th>Roll No</th><th>Name</th><th>Present</th><th>Absent</th><th>Leave</th><th>Late</th></tr></thead>
                <tbody>
                <?php foreach ($students as $st): ?>
                    <?php $current = $existingAttendance[$st['id']] ?? 'Present'; ?>
                    <tr>
                        <td><?php echo e($st['roll_no']); ?></td>
                        <td><?php echo e($st['full_name']); ?></td>
                        <?php foreach (['Present','Absent','Leave','Late'] as $opt): ?>
                            <td style="text-align:center;">
                                <input type="radio" name="status[<?php echo $st['id']; ?>]" value="<?php echo $opt; ?>"
                                    <?php echo $current === $opt ? 'checked' : ''; ?> style="width:auto;margin:0;">
                            </td>
                        <?php endforeach; ?>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
            </div>
            <button type="submit" class="btn" style="margin-top:16px;">💾 Save Attendance</button>
        </form>
        <?php else: ?>
            <p style="color:#aaa;text-align:center;padding:30px;">No active students in this section.</p>
        <?php endif; ?>

        <?php endif; ?>
    </div>
</div>
</body>
</html>
