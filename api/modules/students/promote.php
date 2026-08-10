<?php
require_once __DIR__ . '/../../config/app.php';
require_login();
if (!is_admin() && !can('edit', 'students')) {
    require_role('admin');
}

$pdo = getDB();
$classes = $pdo->query("SELECT id, class_name FROM classes ORDER BY sort_order")->fetchAll();

$classId = clean_id($_GET['class_id'] ?? null);

$students = [];
if ($classId) {
    $stmt = $pdo->prepare(
        "SELECT s.id, s.full_name, s.roll_no, c.class_name
         FROM students s LEFT JOIN classes c ON s.class_id = c.id
         WHERE s.class_id = ? AND s.status = 'Active' ORDER BY s.full_name"
    );
    $stmt->execute([$classId]);
    $students = $stmt->fetchAll();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verify();
    $ids = array_filter(array_map('intval', $_POST['ids'] ?? []));
    $targetClassId = clean_id($_POST['target_class_id'] ?? null);
    $targetSectionId = clean_id($_POST['target_section_id'] ?? null);
    $markGraduated = isset($_POST['mark_graduated']);

    if (!$ids) {
        flash('error', 'Please select at least one student to promote.');
    } elseif (!$targetClassId && !$markGraduated) {
        flash('error', 'Please choose a target class (or mark as graduated).');
    } else {
        $placeholders = implode(',', array_fill(0, count($ids), '?'));
        if ($markGraduated) {
            $sql = "UPDATE students SET status='Graduated' WHERE id IN ($placeholders)";
            $pdo->prepare($sql)->execute($ids);
            log_activity('students_graduated', 'count=' . count($ids));
            flash('success', count($ids) . ' student(s) marked as Graduated.');
        } else {
            $sql = "UPDATE students SET class_id=?, section_id=? WHERE id IN ($placeholders)";
            $params = array_merge([$targetClassId, $targetSectionId ?: null], $ids);
            $pdo->prepare($sql)->execute($params);
            log_activity('students_promoted', 'count=' . count($ids) . ' to_class=' . $targetClassId);
            flash('success', count($ids) . ' student(s) promoted successfully.');
        }
        redirect('promote.php' . ($classId ? "?class_id=$classId" : ''));
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Promote Students – <?php echo e(APP_NAME); ?></title>
    <link rel="stylesheet" href="<?php echo BASE_URL; ?>assets/css/style.css">
    <style>
        .promote-layout { display: grid; grid-template-columns: 1fr 320px; gap: 20px; align-items: flex-start; }
        @media (max-width: 900px) { .promote-layout { grid-template-columns: 1fr; } }
        .stat-pill { display:inline-block; background:linear-gradient(135deg,#667eea,#764ba2); color:#fff; font-weight:800; font-size:22px; padding:14px 0; border-radius:12px; text-align:center; width:100%; margin-bottom:6px; }
        .side-card { background:#fff; border-radius:14px; padding:18px 20px; box-shadow:0 2px 10px rgba(30,60,114,0.06); }
        .checkbox-cell { width:auto; margin:0; }
    </style>
</head>
<body>
<?php include BASE_PATH . '/includes/sidebar.php'; ?>
<div class="content">
    <div class="card-header" style="margin-bottom:16px;">
        <div class="card-title">🎓 Promote Students</div>
        <a href="list.php" class="btn btn-secondary">← Back to Students</a>
    </div>

    <?php echo flash_render(); ?>

    <div class="card">
        <form method="GET" class="form-grid">
            <div class="form-group">
                <label>Select Source Class</label>
                <select name="class_id" onchange="this.form.submit()">
                    <option value="">— Select a class —</option>
                    <?php foreach ($classes as $c): ?>
                        <option value="<?php echo $c['id']; ?>" <?php echo $classId == $c['id'] ? 'selected' : ''; ?>><?php echo e($c['class_name']); ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
        </form>
    </div>

    <?php if ($classId): ?>
    <form method="POST" id="promoteForm">
        <?php echo csrf_field(); ?>
        <div class="promote-layout">
            <div class="card" style="margin-bottom:0;">
                <div class="card-title" style="margin-bottom:14px;">👥 Students in <?php echo e($classes[array_search($classId, array_column($classes,'id'))]['class_name'] ?? ''); ?></div>
                <?php if (!$students): ?>
                    <p style="color:#aaa;text-align:center;padding:20px;">No active students in this class.</p>
                <?php else: ?>
                <div class="table-wrap" style="max-height:420px;overflow-y:auto;">
                <table>
                    <thead><tr><th><input type="checkbox" onclick="document.querySelectorAll('.pickbox').forEach(c=>c.checked=this.checked);updateCount();"></th><th>Name</th><th>Roll No</th><th>Current Class</th></tr></thead>
                    <tbody>
                    <?php foreach ($students as $s): ?>
                        <tr>
                            <td><input type="checkbox" class="pickbox checkbox-cell" name="ids[]" value="<?php echo $s['id']; ?>" onclick="updateCount()"></td>
                            <td><?php echo e($s['full_name']); ?></td>
                            <td><?php echo e($s['roll_no']); ?></td>
                            <td><?php echo e($s['class_name']); ?></td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
                </div>
                <?php endif; ?>
            </div>

            <div class="side-card">
                <div class="stat-pill" id="countPill">0</div>
                <div style="text-align:center;color:#888;font-size:12px;margin-bottom:18px;text-transform:uppercase;letter-spacing:0.5px;">Students Selected</div>

                <div class="form-group">
                    <label>Target Class *</label>
                    <select name="target_class_id" id="targetClass" onchange="loadSections()">
                        <option value="">— Select target class —</option>
                        <?php foreach ($classes as $c): ?>
                            <option value="<?php echo $c['id']; ?>"><?php echo e($c['class_name']); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="form-group">
                    <label>Target Section (optional)</label>
                    <select name="target_section_id" id="targetSection">
                        <option value="">— No specific section —</option>
                    </select>
                </div>

                <div class="form-group" style="margin-top:6px;">
                    <label style="display:flex;align-items:center;gap:8px;font-weight:600;">
                        <input type="checkbox" name="mark_graduated" id="markGrad" style="width:auto;margin:0;" onclick="toggleGrad()">
                        Mark as Graduated instead (final class)
                    </label>
                </div>

                <button type="submit" class="btn btn-success btn-block" style="margin-top:10px;">⬆️ Promote Selected</button>
                <p style="font-size:12px;color:#888;margin-top:10px;">This updates the class (and section) for selected students across all records — attendance history is preserved.</p>
            </div>
        </div>
    </form>
    <?php endif; ?>
</div>
<script>
function updateCount() {
    const n = document.querySelectorAll('.pickbox:checked').length;
    document.getElementById('countPill').textContent = n;
}
function toggleGrad() {
    const grad = document.getElementById('markGrad').checked;
    document.getElementById('targetClass').disabled = grad;
    document.getElementById('targetSection').disabled = grad;
}
async function loadSections() {
    const classId = document.getElementById('targetClass').value;
    const sectionSelect = document.getElementById('targetSection');
    sectionSelect.innerHTML = '<option value="">— No specific section —</option>';
    if (!classId) return;
    try {
        const res = await fetch('sections_ajax.php?class_id=' + classId);
        const data = await res.json();
        (data.sections || data || []).forEach(sec => {
            const opt = document.createElement('option');
            opt.value = sec.id;
            opt.textContent = sec.section_name;
            sectionSelect.appendChild(opt);
        });
    } catch (e) { /* silently ignore if endpoint shape differs */ }
}
</script>
</body>
</html>
