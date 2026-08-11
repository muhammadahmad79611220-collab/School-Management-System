<?php
require_once __DIR__ . '/../../config/app.php';
require_login();

$pdo = getDB();
$id = clean_id($_GET['id'] ?? null);

// A student can only ever see their OWN profile — force this regardless of
// what id was requested in the URL, so changing the URL can't expose
// another student's record.
if (is_student_role(current_role())) {
    $id = current_student_id();
}

if (!$id) redirect('list.php');

if (!is_admin() && !can('view', 'students') && current_role() !== 'teacher' && !is_student_role(current_role())) {
    require_role('admin');
}

$stmt = $pdo->prepare(
    "SELECT s.*, c.class_name, sec.section_name
     FROM students s
     LEFT JOIN classes c ON s.class_id = c.id
     LEFT JOIN sections sec ON s.section_id = sec.id
     WHERE s.id = ?"
);
$stmt->execute([$id]);
$student = $stmt->fetch();

if (!$student) {
    flash('error', 'Student not found.');
    redirect('list.php');
}

// Teachers can only view students in their own section.
if (current_role() === 'teacher') {
    $check = $pdo->prepare("SELECT class_teacher_id FROM sections WHERE id = ?");
    $check->execute([$student['section_id']]);
    $sec = $check->fetch();
    if (!$sec || (int)$sec['class_teacher_id'] !== (int)current_teacher_id()) {
        require_role('admin'); // 403
    }
}

// Login account info — shown to admins so they can see/reset the student's
// portal login. Never shown to the student themself here (they already know
// their own username, and we never expose password hashes).
$loginAccount = null;
if (is_admin()) {
    $ustmt = $pdo->prepare("SELECT username, must_change_password, is_active, last_login FROM users WHERE student_id = ? LIMIT 1");
    $ustmt->execute([$id]);
    $loginAccount = $ustmt->fetch();
}

$__settings  = function_exists('get_settings') ? get_settings() : [];
$__brandName = !empty($__settings['school_name']) ? $__settings['school_name'] : APP_NAME;
$__brandLogo = !empty($__settings['logo']) ? BASE_URL . 'assets/uploads/branding/' . $__settings['logo'] : null;
$__isSelf    = is_student_role(current_role());

function row($label, $value) {
    $value = $value !== null && $value !== '' ? e($value) : '—';
    echo "<div class='form-group'><label>$label</label><div style='padding:8px 0;color:#333;'>$value</div></div>";
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title><?php echo e($student['full_name']); ?> – <?php echo e(APP_NAME); ?></title>
    <link rel="stylesheet" href="<?php echo BASE_URL; ?>assets/css/style.css">
</head>
<body>
<?php include BASE_PATH . '/includes/sidebar.php'; ?>
<div class="content">

    <!-- Print-only letterhead: hidden on screen, shown only when printing -->
    <div class="print-letterhead">
        <?php if ($__brandLogo): ?><img src="<?php echo e($__brandLogo); ?>" alt="logo"><?php endif; ?>
        <div>
            <div class="print-letterhead-school"><?php echo e($__brandName); ?></div>
            <div class="print-letterhead-sub">Student Profile — Printed <?php echo date('d M Y'); ?></div>
        </div>
    </div>

    <?php if ($__isSelf): ?>
        <!-- Student's own view: a warmer, welcoming portal header instead of an admin management bar -->
        <div class="profile-hero no-print">
            <div class="profile-hero-greeting">👋 Welcome back, <?php echo e(explode(' ', $student['full_name'])[0]); ?>!</div>
            <div class="profile-hero-sub">Here's your student profile at <?php echo e($__brandName); ?>.</div>
        </div>
    <?php else: ?>
        <div class="card-header no-print" style="margin-bottom:16px;">
            <div class="card-title">👤 Student Profile</div>
            <div>
                <button type="button" class="btn btn-secondary" onclick="window.print()">🖨️ Print</button>
                <a href="form.php?id=<?php echo $id; ?>" class="btn">✏️ Edit</a>
                <a href="list.php" class="btn btn-secondary">← Back</a>
            </div>
        </div>
    <?php endif; ?>

    <?php echo flash_render(); ?>

    <?php if (!empty($_SESSION['new_login_card'])): ?>
        <?php $__cred = $_SESSION['new_login_card']; unset($_SESSION['new_login_card']); ?>
        <?php include BASE_PATH . '/includes/credentials_card.php'; ?>
    <?php endif; ?>

    <div class="card profile-banner<?php echo $__isSelf ? ' profile-banner-self' : ''; ?>" style="display:flex;gap:20px;align-items:center;margin-bottom:20px;">
        <?php if ($student['picture']): ?>
            <img src="<?php echo BASE_URL . e(UPLOAD_URL . '/' . $student['picture']); ?>" style="width:90px;height:90px;border-radius:14px;object-fit:cover;">
        <?php else: ?>
            <div style="width:90px;height:90px;border-radius:14px;background:#f0f2f5;display:flex;align-items:center;justify-content:center;font-size:40px;">👤</div>
        <?php endif; ?>
        <div>
            <div style="font-size:22px;font-weight:800;color:#1e3c72;"><?php echo e($student['full_name']); ?></div>
            <div style="color:#888;">Roll No: <?php echo e($student['roll_no']); ?> &nbsp;|&nbsp; <?php echo e(($student['class_name'] ?? '—') . ' - ' . ($student['section_name'] ?? '—')); ?></div>
            <span class="badge <?php echo $student['status']==='Active'?'badge-success':'badge-secondary'; ?>" style="margin-top:6px;display:inline-block;"><?php echo e($student['status']); ?></span>
        </div>
        <?php if ($__isSelf): ?>
            <button type="button" class="btn no-print" style="margin-left:auto;" onclick="window.print()">🖨️ Print My Profile</button>
        <?php endif; ?>
    </div>

    <?php if (!$__isSelf && is_admin()): ?>
    <div class="card no-print">
        <div class="card-title" style="margin-bottom:14px;">🔑 Portal Login</div>
        <?php if ($loginAccount): ?>
            <div class="form-grid">
                <?php row('Username', $loginAccount['username']); ?>
                <?php row('Status', $loginAccount['is_active'] ? 'Active' : 'Disabled'); ?>
                <?php row('Password Status', $loginAccount['must_change_password'] ? 'Must change on next login' : 'Set by student'); ?>
                <?php row('Last Login', $loginAccount['last_login'] ? date('d M Y, H:i', strtotime($loginAccount['last_login'])) : 'Never'); ?>
            </div>
        <?php else: ?>
            <p style="color:#888;margin-bottom:12px;">This student doesn't have a portal login yet.</p>
        <?php endif; ?>
        <form method="POST" action="reset_login.php?id=<?php echo $id; ?>" onsubmit="return confirm('Generate a new temporary password for this student? The old one will stop working immediately.');">
            <?php echo csrf_field(); ?>
            <input type="hidden" name="id" value="<?php echo $id; ?>">
            <button type="submit" class="btn btn-secondary">🔄 <?php echo $loginAccount ? 'Reset Password' : 'Create Login'; ?></button>
        </form>
    </div>
    <?php endif; ?>

    <div class="card">
        <div class="card-title" style="margin-bottom:14px;">Personal Information</div>
        <div class="form-grid">
            <?php row('Gender', $student['gender']); ?>
            <?php row('Date of Birth', $student['date_of_birth'] ? date('d M Y', strtotime($student['date_of_birth'])) : ''); ?>
            <?php row('CNIC / B-Form', $student['cnic_bform']); ?>
            <?php row('Blood Group', $student['blood_group']); ?>
            <?php row('Religion', $student['religion']); ?>
            <?php if (!$__isSelf): ?>
                <?php row('Caste', $student['caste']); ?>
                <?php row('Identification Mark', $student['identification_mark']); ?>
                <?php row('Disease, if any', $student['disease_if_any']); ?>
                <?php row('Orphan Student', $student['is_orphan'] ? 'Yes' : 'No'); ?>
            <?php endif; ?>
        </div>
    </div>

    <div class="card">
        <div class="card-title" style="margin-bottom:14px;">Academic &amp; Admission</div>
        <div class="form-grid">
            <?php row('Date of Admission', $student['enrollment_date'] ? date('d M Y', strtotime($student['enrollment_date'])) : ''); ?>
            <?php if (!$__isSelf): ?>
                <?php row('Previous School', $student['previous_school']); ?>
                <?php row('Previous ID / Board Roll No', $student['previous_id_board_roll_no']); ?>
                <?php row('Discount in Fee', $student['discount_percent'] > 0 ? $student['discount_percent'] . '%' : '0%'); ?>
            <?php endif; ?>
        </div>
        <?php if ($student['additional_note'] && !$__isSelf): ?>
            <div class="form-group"><label>Additional Note</label><div style="padding:8px 0;color:#333;"><?php echo e($student['additional_note']); ?></div></div>
        <?php endif; ?>
    </div>

    <div class="card">
        <div class="card-title" style="margin-bottom:14px;">Guardian Information</div>
        <div class="form-grid">
            <?php row('Guardian Name', $student['guardian_name']); ?>
            <?php row('Relation', $student['guardian_relation']); ?>
            <?php row('Guardian Phone', $student['guardian_phone']); ?>
            <?php row('Guardian Email', $student['guardian_email']); ?>
            <?php row('Mobile for SMS', $student['mobile_for_sms']); ?>
        </div>
        <?php row('Address', $student['address']); ?>
    </div>
</div>
</body>
</html>
