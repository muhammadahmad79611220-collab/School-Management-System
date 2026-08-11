<?php
require_once __DIR__ . '/config/app.php';
require_login();

// Students don't get the staff/admin dashboard (school-wide stats aren't
// relevant or appropriate for them to see) — send them straight to their
// own profile, which acts as their personal "dashboard".
if (is_student_role(current_role())) {
    $sid = current_student_id();
    if ($sid) {
        redirect('modules/students/view.php?id=' . $sid);
    }
}

$pdo = getDB();

$total_students = $pdo->query("SELECT COUNT(*) c FROM students WHERE status='Active'")->fetch()['c'];
$total_teachers = $pdo->query("SELECT COUNT(*) c FROM teachers WHERE is_active=1")->fetch()['c'];
$total_classes  = $pdo->query("SELECT COUNT(*) c FROM classes")->fetch()['c'];
$total_courses  = $pdo->query("SELECT COUNT(*) c FROM courses")->fetch()['c'];

$today = date('Y-m-d');

if (is_admin()) {
    $today_present = $pdo->prepare("SELECT COUNT(*) c FROM attendance WHERE attendance_date=? AND status='Present'");
    $today_present->execute([$today]);
    $today_present = $today_present->fetch()['c'];

    $recent_students = $pdo->query(
        "SELECT s.full_name, s.roll_no, c.class_name, s.enrollment_date
         FROM students s LEFT JOIN classes c ON s.class_id = c.id
         ORDER BY s.created_at DESC LIMIT 5"
    );
    $recent_notices = $pdo->query("SELECT title, created_at FROM notices ORDER BY created_at DESC LIMIT 5");
} else {
    // Teacher: scope everything to their own sections.
    $tid = current_teacher_id();
    $stmt = $pdo->prepare(
        "SELECT COUNT(DISTINCT s.id) c FROM attendance a
         JOIN students s ON s.id = a.student_id
         JOIN sections sec ON sec.id = a.section_id
         WHERE sec.class_teacher_id = ? AND a.attendance_date = ? AND a.status='Present'"
    );
    $stmt->execute([$tid, $today]);
    $today_present = $stmt->fetch()['c'];

    $recent_students = $pdo->prepare(
        "SELECT s.full_name, s.roll_no, c.class_name, s.enrollment_date
         FROM students s
         LEFT JOIN classes c ON s.class_id = c.id
         JOIN sections sec ON sec.id = s.section_id
         WHERE sec.class_teacher_id = ?
         ORDER BY s.created_at DESC LIMIT 5"
    );
    $recent_students->execute([$tid]);
    $recent_notices = $pdo->query("SELECT title, created_at FROM notices WHERE audience IN ('All','Teachers') ORDER BY created_at DESC LIMIT 5");
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard – <?php echo e(APP_NAME); ?></title>
    <link rel="manifest" href="manifest.json">
    <meta name="theme-color" content="#1e3c72">
    <link rel="apple-touch-icon" href="assets/icons/icon-192.png">
    <link rel="icon" type="image/png" href="assets/icons/icon-192.png">
    <link rel="stylesheet" href="assets/css/style.css">
    <style>
        .school-header {
            background: linear-gradient(135deg, #1e3c72 0%, #2a5298 50%, #1a6b3a 100%);
            color: white; border-radius: 18px; padding: 30px 35px; margin-bottom: 28px;
            display: flex; align-items: center; gap: 22px; box-shadow: 0 8px 30px rgba(30,60,114,0.25);
            position: relative; overflow: hidden;
        }
        .school-logo {
            width: 80px; height: 80px; background: rgba(255,255,255,0.15); border-radius: 50%;
            display: flex; align-items: center; justify-content: center; font-size: 38px;
            flex-shrink: 0; border: 3px solid rgba(255,255,255,0.3);
        }
        .school-info h1 { font-size: 24px; font-weight: 800; margin-bottom: 4px; }
        .school-info p { font-size: 14px; opacity: 0.85; margin-bottom: 2px; }
        .admin-badge { display: inline-block; background: rgba(255,255,255,0.2); padding: 3px 14px; border-radius: 20px; font-size: 13px; margin-top: 6px; }
        .header-right { margin-left: auto; text-align: right; font-size: 13px; opacity: 0.85; }
        .header-right .date-time { font-size: 15px; font-weight: 600; }

        .stats-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(185px, 1fr)); gap: 20px; margin-bottom: 28px; }
        .stat-card2 {
            background: white; border-radius: 16px; padding: 24px 20px; text-align: center;
            box-shadow: 0 4px 16px rgba(0,0,0,0.06); transition: transform .25s; position: relative; overflow: hidden;
        }
        .stat-card2::after { content:''; position:absolute; bottom:0; left:0; right:0; height:4px; }
        .stat-card2:nth-child(1)::after { background: linear-gradient(90deg,#667eea,#764ba2); }
        .stat-card2:nth-child(2)::after { background: linear-gradient(90deg,#11998e,#38ef7d); }
        .stat-card2:nth-child(3)::after { background: linear-gradient(90deg,#f7971e,#ffd200); }
        .stat-card2:nth-child(4)::after { background: linear-gradient(90deg,#e74c3c,#fd79a8); }
        .stat-card2:nth-child(5)::after { background: linear-gradient(90deg,#0984e3,#74b9ff); }
        .stat-card2:hover { transform: translateY(-5px); }
        .stat-icon { font-size: 36px; margin-bottom: 10px; display: block; }
        .stat-number { font-size: 42px; font-weight: 800; color: #1e3c72; line-height: 1; margin-bottom: 6px; }
        .stat-label { font-size: 13px; color: #888; font-weight: 500; text-transform: uppercase; letter-spacing: 0.5px; }

        .bottom-row { display: grid; grid-template-columns: 1fr 1fr; gap: 20px; }
        @media(max-width:900px){ .bottom-row { grid-template-columns: 1fr; } }
        .mini-table { width: 100%; border-collapse: collapse; }
        .mini-table th { background: #f0f4ff; color: #2a5298; font-size: 12px; text-transform: uppercase; padding: 10px 12px; text-align: left; }
        .mini-table td { padding: 11px 12px; font-size: 14px; color: #444; border-bottom: 1px solid #f5f5f5; }
        .class-badge { display: inline-block; padding: 2px 10px; border-radius: 12px; font-size: 11px; font-weight: 600; background: #e8eaff; color: #2a5298; }
        .quick-actions { display: flex; flex-direction: column; gap: 12px; }
        .action-btn {
            display: flex; align-items: center; gap: 14px; background: #f8f9ff; border: 2px solid #e8eaff;
            border-radius: 12px; padding: 14px 18px; text-decoration: none; color: #333; font-weight: 600; font-size: 15px; transition: all .25s;
        }
        .action-btn:hover { background: linear-gradient(135deg,#667eea,#764ba2); color: white; border-color: transparent; transform: translateX(5px); }
        .action-icon { font-size: 24px; width: 36px; text-align: center; }
        .action-desc { font-size: 12px; font-weight: 400; opacity: 0.7; margin-top: 2px; }
    </style>
</head>
<body>
    <?php include __DIR__ . '/includes/sidebar.php'; ?>

    <?php
        $settings = get_settings();
        $brandName = !empty($settings['school_name']) ? $settings['school_name'] : APP_NAME;
        $brandLogo = !empty($settings['logo']) ? 'assets/uploads/branding/' . $settings['logo'] : null;
    ?>
    <div class="content">
        <div class="school-header">
            <div class="school-logo">
                <?php if ($brandLogo): ?>
                    <img src="<?php echo e($brandLogo); ?>" alt="logo" style="width:100%;height:100%;object-fit:cover;border-radius:50%;">
                <?php else: ?>
                    🏫
                <?php endif; ?>
            </div>
            <div class="school-info">
                <h1><?php echo e($brandName); ?></h1>
                <p><?php echo e($settings['tagline'] ?: 'Management Information System — Academic Portal'); ?></p>
                <span class="admin-badge">👤 Welcome, <?php echo e($_SESSION['full_name']); ?> &nbsp;|&nbsp; <?php echo e(ucfirst(current_role())); ?></span>
            </div>
            <div class="header-right">
                <div class="date-time" id="liveTime">—</div>
                <div><?php echo date('l, F j, Y'); ?></div>
            </div>
        </div>

        <?php echo flash_render(); ?>

        <div class="stats-grid">
            <div class="stat-card2"><span class="stat-icon">👨‍🎓</span><div class="stat-number"><?php echo (int)$total_students; ?></div><div class="stat-label">Active Students</div></div>
            <div class="stat-card2"><span class="stat-icon">👩‍🏫</span><div class="stat-number"><?php echo (int)$total_teachers; ?></div><div class="stat-label">Teaching Staff</div></div>
            <div class="stat-card2"><span class="stat-icon">🏷️</span><div class="stat-number"><?php echo (int)$total_classes; ?></div><div class="stat-label">Classes</div></div>
            <div class="stat-card2"><span class="stat-icon">📚</span><div class="stat-number"><?php echo (int)$total_courses; ?></div><div class="stat-label">Subjects</div></div>
            <div class="stat-card2"><span class="stat-icon">✅</span><div class="stat-number"><?php echo (int)$today_present; ?></div><div class="stat-label">Present Today</div></div>
        </div>

        <div class="bottom-row">
            <div class="card">
                <div class="card-title" style="margin-bottom:18px;">🆕 Recent Admissions</div>
                <?php if ($recent_students && $recent_students->rowCount() !== 0): ?>
                <div class="table-wrap">
                <table class="mini-table">
                    <thead><tr><th>Name</th><th>Roll No</th><th>Class</th><th>Date</th></tr></thead>
                    <tbody>
                        <?php while($s = $recent_students->fetch()): ?>
                        <tr>
                            <td>👤 <?php echo e($s['full_name']); ?></td>
                            <td><?php echo e($s['roll_no']); ?></td>
                            <td><span class="class-badge"><?php echo e($s['class_name'] ?? '—'); ?></span></td>
                            <td><?php echo $s['enrollment_date'] ? date('d M', strtotime($s['enrollment_date'])) : '—'; ?></td>
                        </tr>
                        <?php endwhile; ?>
                    </tbody>
                </table>
                </div>
                <?php else: ?>
                    <p style="color:#aaa;text-align:center;padding:20px;">No students yet.</p>
                <?php endif; ?>
            </div>

            <div class="card">
                <div class="card-title" style="margin-bottom:18px;">⚡ Quick Actions</div>
                <div class="quick-actions">
                    <a href="modules/students/form.php" class="action-btn">
                        <span class="action-icon">➕</span>
                        <div><div>Add New Student</div><div class="action-desc">Register a student in any class</div></div>
                    </a>
                    <a href="modules/students/list.php" class="action-btn">
                        <span class="action-icon">🔍</span>
                        <div><div>Search Students</div><div class="action-desc">Find by class, name, or roll number</div></div>
                    </a>
                    <a href="modules/attendance/mark.php" class="action-btn">
                        <span class="action-icon">📋</span>
                        <div><div>Mark Attendance</div><div class="action-desc">Today's attendance records</div></div>
                    </a>
                    <?php if (is_admin()): ?>
                    <a href="modules/teachers/list.php" class="action-btn">
                        <span class="action-icon">👩‍🏫</span>
                        <div><div>Manage Teachers</div><div class="action-desc">View and manage teaching staff</div></div>
                    </a>
                    <a href="modules/certificates/index.php" class="action-btn">
                        <span class="action-icon">📄</span>
                        <div><div>Issue Certificate</div><div class="action-desc">Leaving, character, or bonafide certificate</div></div>
                    </a>
                    <a href="modules/reports/index.php" class="action-btn">
                        <span class="action-icon">📊</span>
                        <div><div>Reports &amp; Analytics</div><div class="action-desc">Charts, trends, and CSV exports</div></div>
                    </a>
                    <a href="modules/salary/pay.php" class="action-btn">
                        <span class="action-icon">💵</span>
                        <div><div>Pay Salary</div><div class="action-desc">Process teacher salary payments</div></div>
                    </a>
                    <?php else: ?>
                    <a href="modules/exams/list.php" class="action-btn">
                        <span class="action-icon">📝</span>
                        <div><div>Enter Marks</div><div class="action-desc">Record exam results for your classes</div></div>
                    </a>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>

    <script>
        function updateTime() {
            const now = new Date();
            const h = String(now.getHours()).padStart(2,'0');
            const m = String(now.getMinutes()).padStart(2,'0');
            const s = String(now.getSeconds()).padStart(2,'0');
            document.getElementById('liveTime').textContent = h+':'+m+':'+s;
        }
        setInterval(updateTime, 1000);
        updateTime();

        if ('serviceWorker' in navigator) {
            window.addEventListener('load', function () {
                navigator.serviceWorker.register('service-worker.js').catch(function (err) {
                    console.warn('Service worker registration failed:', err);
                });
            });
        }
    </script>
</body>
</html>
