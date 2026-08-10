<?php
require_once __DIR__ . '/../../config/app.php';
require_login();

$pdo = getDB();

if (is_admin()) {
    $sections = $pdo->query(
        "SELECT sec.id, sec.section_name, c.class_name FROM sections sec LEFT JOIN classes c ON sec.class_id = c.id ORDER BY c.sort_order, sec.section_name"
    )->fetchAll();
} else {
    // Teachers see the timetable for sections they teach in OR are class teacher of.
    $stmt = $pdo->prepare(
        "SELECT DISTINCT sec.id, sec.section_name, c.class_name
         FROM sections sec LEFT JOIN classes c ON sec.class_id = c.id
         LEFT JOIN teacher_subjects ts ON ts.section_id = sec.id
         WHERE sec.class_teacher_id = ? OR ts.teacher_id = ?
         ORDER BY c.sort_order, sec.section_name"
    );
    $stmt->execute([current_teacher_id(), current_teacher_id()]);
    $sections = $stmt->fetchAll();
}

$allowedIds = array_column($sections, 'id');
$sectionId = clean_id($_GET['section_id'] ?? ($sections[0]['id'] ?? null));
if ($sectionId && !in_array($sectionId, $allowedIds, true)) {
    $sectionId = $sections[0]['id'] ?? null;
}

$days = ['Monday','Tuesday','Wednesday','Thursday','Friday','Saturday'];
$grid = [];
$breaks = [];
if ($sectionId) {
    $stmt = $pdo->prepare(
        "SELECT t.*, c.course_name, te.full_name as teacher_name
         FROM timetable t
         JOIN courses c ON t.course_id = c.id
         LEFT JOIN teachers te ON t.teacher_id = te.id
         WHERE t.section_id = ?
         ORDER BY t.period_number"
    );
    $stmt->execute([$sectionId]);
    foreach ($stmt->fetchAll() as $row) {
        $grid[$row['day_of_week']][$row['period_number']] = $row;
    }

    $bStmt = $pdo->prepare("SELECT * FROM timetable_breaks WHERE section_id = ? ORDER BY after_period, start_time");
    $bStmt->execute([$sectionId]);
    foreach ($bStmt->fetchAll() as $b) {
        $breaks[(int)$b['after_period']][] = $b;
    }
}
$maxPeriods = 8;
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Timetable – <?php echo e(APP_NAME); ?></title>
    <link rel="stylesheet" href="<?php echo BASE_URL; ?>assets/css/style.css">
    <style>
        .tt-table th, .tt-table td { text-align: center; font-size: 13px; }
        .tt-cell { background: #f8f9ff; border-radius: 6px; padding: 6px; display: block; }
        .tt-cell .subj { font-weight: 700; color: #1e3c72; }
        .tt-cell .teach { font-size: 11px; color: #888; }
        .break-row td { background: linear-gradient(135deg,#fff4e0,#ffe9c7) !important; }
        .break-cell { font-weight: 800; color: #d35400; letter-spacing: 0.4px; padding: 8px 0 !important; }
    </style>
</head>
<body>
<?php include BASE_PATH . '/includes/sidebar.php'; ?>
<div class="content">
    <div class="card">
        <div class="card-header">
            <div class="card-title">🗓️ Weekly Timetable</div>
            <?php if (is_admin() || can('edit','timetable')): ?>
                <a href="edit.php?section_id=<?php echo $sectionId; ?>" class="btn">✏️ Edit Timetable</a>
            <?php endif; ?>
        </div>

        <?php echo flash_render(); ?>

        <?php if (!$sections): ?>
            <p style="color:#888;">No sections available to you yet.</p>
        <?php else: ?>

        <form method="GET" style="margin-bottom:20px;">
            <label>Section</label>
            <select name="section_id" onchange="this.form.submit()">
                <?php foreach ($sections as $s): ?>
                    <option value="<?php echo $s['id']; ?>" <?php echo $sectionId == $s['id'] ? 'selected' : ''; ?>>
                        <?php echo e($s['class_name'] . ' - ' . $s['section_name']); ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </form>

        <div class="table-wrap">
        <table class="tt-table">
            <thead>
                <tr>
                    <th>Period</th>
                    <?php foreach ($days as $d): ?><th><?php echo $d; ?></th><?php endforeach; ?>
                </tr>
            </thead>
            <tbody>
                <?php if (!empty($breaks[0])): foreach ($breaks[0] as $b): ?>
                <tr class="break-row">
                    <td><strong>☕</strong></td>
                    <td colspan="<?php echo count($days); ?>" class="break-cell">
                        <?php echo e($b['label']); ?> &nbsp;·&nbsp; <?php echo substr($b['start_time'],0,5) . '–' . substr($b['end_time'],0,5); ?>
                    </td>
                </tr>
                <?php endforeach; endif; ?>
                <?php for ($p = 1; $p <= $maxPeriods; $p++): ?>
                <tr>
                    <td><strong>P<?php echo $p; ?></strong></td>
                    <?php foreach ($days as $d): ?>
                        <td>
                            <?php if (isset($grid[$d][$p])): $cell = $grid[$d][$p]; ?>
                                <span class="tt-cell">
                                    <span class="subj"><?php echo e($cell['course_name']); ?></span><br>
                                    <span class="teach"><?php echo e($cell['teacher_name'] ?? ''); ?></span><br>
                                    <span class="teach"><?php echo substr($cell['start_time'],0,5) . '–' . substr($cell['end_time'],0,5); ?></span>
                                </span>
                            <?php else: ?>
                                <span style="color:#ccc;">—</span>
                            <?php endif; ?>
                        </td>
                    <?php endforeach; ?>
                </tr>
                <?php if (!empty($breaks[$p])): foreach ($breaks[$p] as $b): ?>
                <tr class="break-row">
                    <td><strong>☕</strong></td>
                    <td colspan="<?php echo count($days); ?>" class="break-cell">
                        <?php echo e($b['label']); ?> &nbsp;·&nbsp; <?php echo substr($b['start_time'],0,5) . '–' . substr($b['end_time'],0,5); ?>
                    </td>
                </tr>
                <?php endforeach; endif; ?>
                <?php endfor; ?>
            </tbody>
        </table>
        </div>

        <?php endif; ?>
    </div>
</div>
</body>
</html>
