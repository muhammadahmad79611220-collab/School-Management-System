<?php
require_once __DIR__ . '/../../config/app.php';
require_perm('edit', 'timetable');

$pdo = getDB();
$sectionId = clean_id($_GET['section_id'] ?? null);

$sections = $pdo->query(
    "SELECT sec.id, sec.section_name, c.class_name FROM sections sec LEFT JOIN classes c ON sec.class_id = c.id ORDER BY c.sort_order, sec.section_name"
)->fetchAll();

if (!$sectionId) $sectionId = $sections[0]['id'] ?? null;

$section = null;
foreach ($sections as $s) if ($s['id'] == $sectionId) $section = $s;
if (!$section) {
    flash('error', 'Please select a valid section.');
    redirect('view.php');
}

// Subjects taught in this section (from teacher_subjects) plus all subjects for the class, so admin has full flexibility.
$courses = $pdo->prepare(
    "SELECT DISTINCT c.id, c.course_name FROM courses c
     LEFT JOIN sections sec ON sec.id = ?
     WHERE c.class_id = (SELECT class_id FROM sections WHERE id = ?) OR c.class_id IS NULL
     ORDER BY c.course_name"
);
$courses->execute([$sectionId, $sectionId]);
$courses = $courses->fetchAll();

$teachers = $pdo->query("SELECT id, full_name FROM teachers WHERE is_active=1 ORDER BY full_name")->fetchAll();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verify();
    $action = $_POST['action'] ?? '';

    if ($action === 'add') {
        $courseId = clean_id($_POST['course_id'] ?? null);
        $teacherId = clean_id($_POST['teacher_id'] ?? null);
        $day = $_POST['day_of_week'] ?? '';
        $period = (int)($_POST['period_number'] ?? 0);
        $start = $_POST['start_time'] ?? '';
        $end = $_POST['end_time'] ?? '';

        $validDays = ['Monday','Tuesday','Wednesday','Thursday','Friday','Saturday'];
        if (!$courseId || !in_array($day, $validDays, true) || $period < 1 || $period > 12 || !$start || !$end) {
            flash('error', 'Please fill in all required fields correctly.');
        } elseif ($start >= $end) {
            flash('error', 'Start time must be before end time.');
        } else {
            try {
                $pdo->prepare(
                    "INSERT INTO timetable (section_id, course_id, teacher_id, day_of_week, period_number, start_time, end_time)
                     VALUES (?,?,?,?,?,?,?)
                     ON DUPLICATE KEY UPDATE course_id=VALUES(course_id), teacher_id=VALUES(teacher_id), start_time=VALUES(start_time), end_time=VALUES(end_time)"
                )->execute([$sectionId, $courseId, $teacherId, $day, $period, $start, $end]);
                log_activity('timetable_updated', "section_id=$sectionId day=$day period=$period");
                flash('success', 'Timetable slot saved.');
            } catch (Throwable $e) {
                error_log($e->getMessage());
                flash('error', 'Could not save this slot.');
            }
        }
    } elseif ($action === 'delete') {
        $slotId = clean_id($_POST['slot_id'] ?? null);
        if ($slotId) {
            $pdo->prepare("DELETE FROM timetable WHERE id = ? AND section_id = ?")->execute([$slotId, $sectionId]);
            flash('success', 'Slot removed.');
        }
    } elseif ($action === 'add_break') {
        $afterPeriod = (int)($_POST['after_period'] ?? 0);
        $label = trim($_POST['break_label'] ?? 'Break');
        $start = $_POST['break_start'] ?? '';
        $end = $_POST['break_end'] ?? '';
        if ($label === '' || !$start || !$end) {
            flash('error', 'Please provide a label and start/end time for the break.');
        } elseif ($start >= $end) {
            flash('error', 'Break start time must be before end time.');
        } else {
            $pdo->prepare(
                "INSERT INTO timetable_breaks (section_id, after_period, label, start_time, end_time) VALUES (?,?,?,?,?)"
            )->execute([$sectionId, $afterPeriod, $label, $start, $end]);
            flash('success', "\"$label\" added to the timetable.");
        }
    } elseif ($action === 'delete_break') {
        $breakId = clean_id($_POST['break_id'] ?? null);
        if ($breakId) {
            $pdo->prepare("DELETE FROM timetable_breaks WHERE id = ? AND section_id = ?")->execute([$breakId, $sectionId]);
            flash('success', 'Break removed.');
        }
    }
    redirect("edit.php?section_id=$sectionId");
}

$slots = $pdo->prepare(
    "SELECT t.*, c.course_name, te.full_name as teacher_name
     FROM timetable t JOIN courses c ON t.course_id = c.id LEFT JOIN teachers te ON t.teacher_id = te.id
     WHERE t.section_id = ? ORDER BY t.day_of_week, t.period_number"
);
$slots->execute([$sectionId]);
$slots = $slots->fetchAll();

$breaks = $pdo->prepare("SELECT * FROM timetable_breaks WHERE section_id = ? ORDER BY after_period, start_time");
$breaks->execute([$sectionId]);
$breaks = $breaks->fetchAll();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Edit Timetable – <?php echo e(APP_NAME); ?></title>
    <link rel="stylesheet" href="<?php echo BASE_URL; ?>assets/css/style.css">
</head>
<body>
<?php include BASE_PATH . '/includes/sidebar.php'; ?>
<div class="content">
    <div class="card">
        <div class="card-header">
            <div class="card-title">✏️ Edit Timetable: <?php echo e(($section['class_name'] ?? '') . ' - ' . $section['section_name']); ?></div>
            <a href="view.php?section_id=<?php echo $sectionId; ?>" class="btn btn-secondary">← View Timetable</a>
        </div>

        <?php echo flash_render(); ?>

        <form method="GET" style="margin-bottom:20px;">
            <label>Section</label>
            <select name="section_id" onchange="this.form.submit()">
                <?php foreach ($sections as $s): ?>
                    <option value="<?php echo $s['id']; ?>" <?php echo $sectionId == $s['id'] ? 'selected' : ''; ?>>
                        <?php echo e(($s['class_name'] ?? '') . ' - ' . $s['section_name']); ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </form>

        <form method="POST" class="form-grid" style="margin-bottom:24px;">
            <?php echo csrf_field(); ?>
            <input type="hidden" name="action" value="add">
            <div class="form-group">
                <label>Day</label>
                <select name="day_of_week" required>
                    <?php foreach (['Monday','Tuesday','Wednesday','Thursday','Friday','Saturday'] as $d): ?>
                        <option value="<?php echo $d; ?>"><?php echo $d; ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="form-group">
                <label>Period #</label>
                <input type="number" name="period_number" min="1" max="12" value="1" required>
            </div>
            <div class="form-group">
                <label>Subject</label>
                <select name="course_id" required>
                    <option value="">— Select —</option>
                    <?php foreach ($courses as $c): ?>
                        <option value="<?php echo $c['id']; ?>"><?php echo e($c['course_name']); ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="form-group">
                <label>Teacher</label>
                <select name="teacher_id">
                    <option value="">— None —</option>
                    <?php foreach ($teachers as $t): ?>
                        <option value="<?php echo $t['id']; ?>"><?php echo e($t['full_name']); ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="form-group">
                <label>Start Time</label>
                <input type="time" name="start_time" required>
            </div>
            <div class="form-group">
                <label>End Time</label>
                <input type="time" name="end_time" required>
            </div>
            <div class="form-group" style="align-self:end;">
                <button type="submit" class="btn btn-success btn-block">💾 Save Slot</button>
            </div>
        </form>

        <div class="table-wrap">
        <table>
            <thead><tr><th>Day</th><th>Period</th><th>Subject</th><th>Teacher</th><th>Time</th><th>Actions</th></tr></thead>
            <tbody>
            <?php if (!$slots): ?>
                <tr><td colspan="6" style="text-align:center;color:#aaa;padding:20px;">No timetable slots yet.</td></tr>
            <?php endif; ?>
            <?php foreach ($slots as $slot): ?>
                <tr>
                    <td><?php echo e($slot['day_of_week']); ?></td>
                    <td><?php echo (int)$slot['period_number']; ?></td>
                    <td><?php echo e($slot['course_name']); ?></td>
                    <td><?php echo e($slot['teacher_name'] ?? '—'); ?></td>
                    <td><?php echo substr($slot['start_time'],0,5) . '–' . substr($slot['end_time'],0,5); ?></td>
                    <td>
                        <form method="POST" style="display:inline;" onsubmit="return confirm('Remove this slot?');">
                            <?php echo csrf_field(); ?>
                            <input type="hidden" name="action" value="delete">
                            <input type="hidden" name="slot_id" value="<?php echo $slot['id']; ?>">
                            <button type="submit" class="btn btn-sm btn-danger">🗑️</button>
                        </form>
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
        </div>
    </div>

    <div class="card">
        <div class="card-title" style="margin-bottom:14px;">☕ Break / Recess Times</div>
        <p style="color:#888;font-size:13px;margin-bottom:14px;">Add a break (recess, lunch, assembly) that will appear as its own row across every day in the weekly timetable, right after the period you choose.</p>

        <form method="POST" class="form-grid" style="margin-bottom:18px;">
            <?php echo csrf_field(); ?>
            <input type="hidden" name="action" value="add_break">
            <div class="form-group">
                <label>Insert After Period</label>
                <select name="after_period">
                    <option value="0">Before Period 1</option>
                    <?php for ($p = 1; $p <= 12; $p++): ?>
                        <option value="<?php echo $p; ?>">After Period <?php echo $p; ?></option>
                    <?php endfor; ?>
                </select>
            </div>
            <div class="form-group">
                <label>Label</label>
                <input type="text" name="break_label" maxlength="50" placeholder="e.g. Recess, Lunch Break, Assembly" required value="Break">
            </div>
            <div class="form-group">
                <label>Start Time</label>
                <input type="time" name="break_start" required>
            </div>
            <div class="form-group">
                <label>End Time</label>
                <input type="time" name="break_end" required>
            </div>
            <div class="form-group" style="align-self:end;">
                <button type="submit" class="btn btn-warning btn-block">☕ Add Break Row</button>
            </div>
        </form>

        <div class="table-wrap">
        <table>
            <thead><tr><th>Position</th><th>Label</th><th>Time</th><th>Actions</th></tr></thead>
            <tbody>
            <?php if (!$breaks): ?>
                <tr><td colspan="4" style="text-align:center;color:#aaa;padding:16px;">No break rows added yet.</td></tr>
            <?php endif; ?>
            <?php foreach ($breaks as $b): ?>
                <tr>
                    <td><?php echo $b['after_period'] == 0 ? 'Before Period 1' : 'After Period ' . (int)$b['after_period']; ?></td>
                    <td><span class="badge badge-warning"><?php echo e($b['label']); ?></span></td>
                    <td><?php echo substr($b['start_time'],0,5) . '–' . substr($b['end_time'],0,5); ?></td>
                    <td>
                        <form method="POST" style="display:inline;" onsubmit="return confirm('Remove this break row?');">
                            <?php echo csrf_field(); ?>
                            <input type="hidden" name="action" value="delete_break">
                            <input type="hidden" name="break_id" value="<?php echo $b['id']; ?>">
                            <button type="submit" class="btn btn-sm btn-danger">🗑️</button>
                        </form>
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
        </div>
    </div>
</div>
</body>
</html>
