<?php
$current_page = basename($_SERVER['PHP_SELF']);
$role = current_role();
$uri = $_SERVER['REQUEST_URI'] ?? '';
$__settings = function_exists('get_settings') ? get_settings() : [];
$__brandName = !empty($__settings['school_name']) ? $__settings['school_name'] : APP_NAME;
$__brandLogo = !empty($__settings['logo']) ? BASE_URL . 'assets/uploads/branding/' . $__settings['logo'] : null;
?>
<div class="sidebar">
    <div class="sidebar-brand">
        <div class="sidebar-brand-logo">
            <?php if ($__brandLogo): ?>
                <img src="<?php echo e($__brandLogo); ?>" alt="logo">
            <?php else: ?>
                <span>🏫</span>
            <?php endif; ?>
        </div>
        <div class="sidebar-brand-name"><?php echo e($__brandName); ?></div>
        <div class="sidebar-brand-role"><?php echo e(ucfirst($role ?? '')); ?> Panel</div>
    </div>

    <?php if (is_student_role($role)): ?>
        <a href="<?php echo BASE_URL; ?>dashboard.php" class="<?php echo $current_page=='dashboard.php' ? 'active':''; ?>">👤 My Profile</a>
        <a href="<?php echo BASE_URL; ?>modules/attendance/my.php" class="<?php echo str_contains($uri,'attendance/my') ? 'active':''; ?>">✅ My Attendance</a>
        <a href="<?php echo BASE_URL; ?>modules/exams/my_results.php" class="<?php echo str_contains($uri,'my_results') ? 'active':''; ?>">📝 My Results</a>
        <a href="<?php echo BASE_URL; ?>modules/fees/my_status.php" class="<?php echo str_contains($uri,'my_status') ? 'active':''; ?>">💰 My Fee Status</a>
        <a href="<?php echo BASE_URL; ?>modules/timetable/view.php" class="<?php echo str_contains($uri,'timetable') ? 'active':''; ?>">🗓️ Timetable</a>
        <a href="<?php echo BASE_URL; ?>modules/notices/list.php" class="<?php echo str_contains($uri,'notices') ? 'active':''; ?>">📢 Notices</a>
        <a href="<?php echo BASE_URL; ?>change_password.php" style="margin-top:auto;">🔑 Change Password</a>
        <a href="<?php echo BASE_URL; ?>logout.php" style="border-top:1px solid rgba(255,255,255,0.15); padding-top:16px;">🚪 Logout</a>
    <?php else: ?>

    <a href="<?php echo BASE_URL; ?>dashboard.php" class="<?php echo $current_page=='dashboard.php' ? 'active':''; ?>">📊 Dashboard</a>

    <?php if (is_admin() || is_teacher_role($role) || can('view','students')): ?>
        <a href="<?php echo BASE_URL; ?>modules/students/list.php" class="<?php echo str_contains($uri,'students') && !str_contains($uri,'promote') ? 'active':''; ?>">👨‍🎓 Students</a>
    <?php endif; ?>
    <?php if (is_admin() || can('edit','students')): ?>
        <a href="<?php echo BASE_URL; ?>modules/students/promote.php" class="<?php echo str_contains($uri,'promote') ? 'active':''; ?>">🎓 Promote Students</a>
    <?php endif; ?>

    <?php if (is_admin() || can('view','teachers')): ?>
        <a href="<?php echo BASE_URL; ?>modules/teachers/list.php" class="<?php echo str_contains($uri,'teachers') ? 'active':''; ?>">👩‍🏫 Teachers</a>
    <?php endif; ?>
    <?php if (is_admin()): ?>
        <a href="<?php echo BASE_URL; ?>modules/staff/list.php" class="<?php echo str_contains($uri,'staff') ? 'active':''; ?>">👥 Staff Accounts</a>
        <a href="<?php echo BASE_URL; ?>modules/classes/list.php" class="<?php echo str_contains($uri,'classes') ? 'active':''; ?>">🏷️ Classes &amp; Sections</a>
        <a href="<?php echo BASE_URL; ?>modules/courses/list.php" class="<?php echo str_contains($uri,'courses') ? 'active':''; ?>">📖 Subjects</a>
    <?php endif; ?>

    <?php if (is_admin() || is_teacher_role($role) || can('view','attendance')): ?>
        <a href="<?php echo BASE_URL; ?>modules/attendance/mark.php" class="<?php echo str_contains($uri,'attendance/mark') ? 'active':''; ?>">✅ Attendance</a>
        <a href="<?php echo BASE_URL; ?>modules/attendance/scan.php" class="<?php echo str_contains($uri,'attendance/scan') ? 'active':''; ?>">📷 Scan Attendance</a>
    <?php endif; ?>

    <div class="sidebar-section-label">Academics</div>
    <?php if (is_admin() || is_teacher_role($role) || can('view','exams')): ?>
        <a href="<?php echo BASE_URL; ?>modules/exams/list.php" class="<?php echo str_contains($uri,'exams') ? 'active':''; ?>">📝 Exams &amp; Results</a>
    <?php endif; ?>
    <a href="<?php echo BASE_URL; ?>modules/timetable/view.php" class="<?php echo str_contains($uri,'timetable') ? 'active':''; ?>">🗓️ Timetable</a>

    <?php if (is_admin() || can('view','certificates')): ?>
        <a href="<?php echo BASE_URL; ?>modules/certificates/index.php" class="<?php echo str_contains($uri,'certificates') ? 'active':''; ?>">📄 Certificates</a>
    <?php endif; ?>
    <?php if (is_admin() || can('view','idcards')): ?>
        <a href="<?php echo BASE_URL; ?>modules/idcards/index.php" class="<?php echo str_contains($uri,'idcards') ? 'active':''; ?>">🪪 ID Cards</a>
    <?php endif; ?>

    <?php if (is_admin() || can('view','fees')): ?>
        <div class="sidebar-section-label">Finance</div>
        <a href="<?php echo BASE_URL; ?>modules/fees/list.php" class="<?php echo str_contains($uri,'fees') ? 'active':''; ?>">💰 Fee Management</a>
    <?php endif; ?>
    <?php if (is_admin()): ?>
        <a href="<?php echo BASE_URL; ?>modules/salary/paid_slips.php" class="<?php echo str_contains($uri,'salary') ? 'active':''; ?>">💵 Salary</a>
    <?php endif; ?>

    <?php if (is_admin() || can('view','reports')): ?>
        <div class="sidebar-section-label">Insights</div>
        <a href="<?php echo BASE_URL; ?>modules/reports/index.php" class="<?php echo str_contains($uri,'reports') ? 'active':''; ?>">📊 Reports &amp; Analytics</a>
    <?php endif; ?>

    <div class="sidebar-section-label">Communication</div>
    <a href="<?php echo BASE_URL; ?>modules/notices/list.php" class="<?php echo str_contains($uri,'notices') ? 'active':''; ?>">📢 Notices</a>

    <?php if (is_admin()): ?>
        <div class="sidebar-section-label">System</div>
        <a href="<?php echo BASE_URL; ?>modules/settings/index.php" class="<?php echo str_contains($uri,'settings') ? 'active':''; ?>">🏫 Institute Settings</a>
        <a href="<?php echo BASE_URL; ?>modules/roles/list.php" class="<?php echo str_contains($uri,'roles') ? 'active':''; ?>">🛡️ Roles &amp; Permissions</a>
    <?php endif; ?>

    <a href="<?php echo BASE_URL; ?>change_password.php" style="margin-top:auto;">🔑 Change Password</a>
    <a href="<?php echo BASE_URL; ?>logout.php" style="border-top:1px solid rgba(255,255,255,0.15); padding-top:16px;">🚪 Logout</a>

    <?php endif; ?>
</div>
