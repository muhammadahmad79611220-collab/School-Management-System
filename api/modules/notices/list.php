<?php
require_once __DIR__ . '/../../config/app.php';
require_login();

$pdo = getDB();

if (is_admin()) {
    $audienceFilter = "1=1"; // admin sees all
    $notices = $pdo->query(
        "SELECT n.*, u.full_name as posted_by_name FROM notices n LEFT JOIN users u ON n.posted_by = u.id ORDER BY n.created_at DESC"
    )->fetchAll();
} elseif (is_student_role(current_role())) {
    $stmt = $pdo->prepare(
        "SELECT n.*, u.full_name as posted_by_name FROM notices n LEFT JOIN users u ON n.posted_by = u.id
         WHERE n.audience IN ('All','Students') ORDER BY n.created_at DESC"
    );
    $stmt->execute();
    $notices = $stmt->fetchAll();
} else {
    $stmt = $pdo->prepare(
        "SELECT n.*, u.full_name as posted_by_name FROM notices n LEFT JOIN users u ON n.posted_by = u.id
         WHERE n.audience IN ('All','Teachers') ORDER BY n.created_at DESC"
    );
    $stmt->execute();
    $notices = $stmt->fetchAll();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && is_admin()) {
    csrf_verify();
    $title = trim($_POST['title'] ?? '');
    $body = trim($_POST['body'] ?? '');
    $audience = $_POST['audience'] ?? 'All';
    if (!in_array($audience, ['All','Teachers','Students','Admins'], true)) $audience = 'All';

    if ($title === '' || $body === '') {
        flash('error', 'Title and message are required.');
    } else {
        $pdo->prepare("INSERT INTO notices (title, body, audience, posted_by) VALUES (?,?,?,?)")
            ->execute([$title, $body, $audience, $_SESSION['user_id']]);
        log_activity('notice_posted', "title=$title");
        flash('success', 'Notice posted.');
        redirect('list.php');
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Notices – <?php echo e(APP_NAME); ?></title>
    <link rel="stylesheet" href="<?php echo BASE_URL; ?>assets/css/style.css">
</head>
<body>
<?php include BASE_PATH . '/includes/sidebar.php'; ?>
<div class="content">
    <div class="card">
        <div class="card-header">
            <div class="card-title">📢 Notices &amp; Announcements</div>
            <?php if (is_admin()): ?>
                <button class="btn" onclick="document.getElementById('addForm').style.display='block'">➕ Post Notice</button>
            <?php endif; ?>
        </div>

        <?php echo flash_render(); ?>

        <?php if (is_admin()): ?>
        <form id="addForm" method="POST" style="display:none;margin-bottom:20px;">
            <?php echo csrf_field(); ?>
            <label>Title</label>
            <input type="text" name="title" required maxlength="150">
            <label>Message</label>
            <textarea name="body" rows="4" required></textarea>
            <label>Audience</label>
            <select name="audience">
                <option value="All">Everyone</option>
                <option value="Teachers">Teachers Only</option>
                <option value="Students">Students Only</option>
                <option value="Admins">Admins Only</option>
            </select>
            <button type="submit" class="btn btn-success" style="margin-top:10px;">📢 Post Notice</button>
        </form>
        <?php endif; ?>

        <?php if (!$notices): ?>
            <p style="color:#aaa;text-align:center;padding:30px;">No notices yet.</p>
        <?php endif; ?>

        <?php foreach ($notices as $n): ?>
            <div class="card" style="margin-bottom:14px;box-shadow:none;border:1px solid #eee;">
                <div style="display:flex;justify-content:space-between;align-items:flex-start;">
                    <div>
                        <strong style="color:#1e3c72;font-size:16px;"><?php echo e($n['title']); ?></strong>
                        <span class="badge badge-info" style="margin-left:8px;"><?php echo e($n['audience']); ?></span>
                    </div>
                    <span style="font-size:12px;color:#999;"><?php echo date('d M Y, H:i', strtotime($n['created_at'])); ?></span>
                </div>
                <p style="margin-top:10px;color:#444;white-space:pre-wrap;"><?php echo e($n['body']); ?></p>
                <p style="margin-top:8px;font-size:12px;color:#aaa;">Posted by <?php echo e($n['posted_by_name'] ?? 'Unknown'); ?></p>
            </div>
        <?php endforeach; ?>
    </div>
</div>
</body>
</html>
