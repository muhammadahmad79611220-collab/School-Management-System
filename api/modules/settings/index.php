<?php
require_once __DIR__ . '/../../config/app.php';
require_role('admin');

$pdo = getDB();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verify();

    $name = trim($_POST['school_name'] ?? '');
    $tagline = trim($_POST['tagline'] ?? '');
    $address = trim($_POST['address'] ?? '');
    $phone = trim($_POST['phone'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $principal = trim($_POST['principal_name'] ?? '');
    $year = trim($_POST['academic_year'] ?? '');
    $bankName = trim($_POST['bank_name'] ?? '');
    $bankAddress = trim($_POST['bank_address'] ?? '');
    $bankAccount = trim($_POST['bank_account'] ?? '');
    $defaultFine = (float)($_POST['default_fine_after_due'] ?? 0);

    if ($name === '') {
        flash('error', 'School name is required.');
    } else {
        $logoDir = BASE_PATH . '/assets/uploads/branding';
        $logo = handle_picture_upload('logo', $logoDir);
        $signature = handle_picture_upload('principal_signature', $logoDir);

        $sql = "UPDATE settings SET school_name=?, tagline=?, address=?, phone=?, email=?, principal_name=?, academic_year=?,
                bank_name=?, bank_address=?, bank_account=?, default_fine_after_due=?";
        $params = [$name, $tagline, $address, $phone, $email, $principal, $year, $bankName, $bankAddress, $bankAccount, $defaultFine];
        if ($logo) { $sql .= ", logo=?"; $params[] = $logo; }
        if ($signature) { $sql .= ", principal_signature=?"; $params[] = $signature; }
        $sql .= " WHERE id=1";

        $pdo->prepare($sql)->execute($params);
        log_activity('settings_updated');
        flash('success', 'Institute settings updated.');
        redirect('index.php');
    }
}

$settings = $pdo->query("SELECT * FROM settings WHERE id=1")->fetch();
if (!$settings) {
    $pdo->exec("INSERT INTO settings (id, school_name) VALUES (1, 'My School')");
    $settings = $pdo->query("SELECT * FROM settings WHERE id=1")->fetch();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Institute Settings – <?php echo e(APP_NAME); ?></title>
    <link rel="stylesheet" href="<?php echo BASE_URL; ?>assets/css/style.css">
</head>
<body>
<?php include BASE_PATH . '/includes/sidebar.php'; ?>
<div class="content">
    <div class="card" style="max-width:760px;">
        <div class="card-title" style="margin-bottom:6px;">🏫 Institute Settings &amp; Branding</div>
        <p style="color:#888;font-size:13px;margin-bottom:18px;">
            Set your school name and logo here — they'll automatically appear on the sidebar, dashboard, login page, ID cards, certificates, fee receipts and salary slips.
        </p>

        <?php echo flash_render(); ?>

        <form method="POST" enctype="multipart/form-data">
            <?php echo csrf_field(); ?>
            <div class="form-grid">
                <div class="form-group">
                    <label>School Name *</label>
                    <input type="text" name="school_name" required maxlength="150" value="<?php echo e($settings['school_name']); ?>">
                </div>
                <div class="form-group">
                    <label>Tagline</label>
                    <input type="text" name="tagline" maxlength="200" placeholder="e.g. Excellence in Education" value="<?php echo e($settings['tagline']); ?>">
                </div>
                <div class="form-group">
                    <label>Phone</label>
                    <input type="text" name="phone" maxlength="30" value="<?php echo e($settings['phone']); ?>">
                </div>
                <div class="form-group">
                    <label>Email</label>
                    <input type="email" name="email" maxlength="100" value="<?php echo e($settings['email']); ?>">
                </div>
                <div class="form-group">
                    <label>Principal Name</label>
                    <input type="text" name="principal_name" maxlength="100" value="<?php echo e($settings['principal_name']); ?>">
                </div>
                <div class="form-group">
                    <label>Academic Year</label>
                    <input type="text" name="academic_year" maxlength="20" placeholder="e.g. 2025-2026" value="<?php echo e($settings['academic_year']); ?>">
                </div>
            </div>

            <div class="form-group">
                <label>Address</label>
                <textarea name="address" rows="2"><?php echo e($settings['address']); ?></textarea>
            </div>

            <div class="section-step" style="margin:24px 0 16px;padding-bottom:10px;border-bottom:2px solid #f0f2f5;">
                <strong style="color:#1e3c72;">Bank Details (shown on fee invoices)</strong>
            </div>
            <div class="form-grid">
                <div class="form-group">
                    <label>Bank Name</label>
                    <input type="text" name="bank_name" maxlength="50" placeholder="e.g. JAZZCASH, HBL, Meezan Bank" value="<?php echo e($settings['bank_name'] ?? ''); ?>">
                </div>
                <div class="form-group">
                    <label>Bank Branch / Address</label>
                    <input type="text" name="bank_address" maxlength="150" value="<?php echo e($settings['bank_address'] ?? ''); ?>">
                </div>
                <div class="form-group">
                    <label>Account Number</label>
                    <input type="text" name="bank_account" maxlength="50" value="<?php echo e($settings['bank_account'] ?? ''); ?>">
                </div>
                <div class="form-group">
                    <label>Default Fine After Due Date</label>
                    <input type="number" name="default_fine_after_due" min="0" step="0.01" value="<?php echo e($settings['default_fine_after_due'] ?? '0'); ?>">
                </div>
            </div>

            <div class="form-grid">
                <div class="form-group">
                    <label>School Logo</label>
                    <input type="file" name="logo" accept="image/jpeg,image/png,image/webp">
                    <?php if ($settings['logo']): ?>
                        <div style="margin-top:8px;display:flex;align-items:center;gap:10px;">
                            <img src="<?php echo BASE_URL; ?>assets/uploads/branding/<?php echo e($settings['logo']); ?>" style="width:56px;height:56px;object-fit:cover;border-radius:50%;border:2px solid #e0e4ef;box-shadow:0 2px 8px rgba(0,0,0,0.08);">
                            <span style="font-size:12px;color:#888;">Current logo — appears on sidebar, dashboard &amp; printouts</span>
                        </div>
                    <?php endif; ?>
                </div>
                <div class="form-group">
                    <label>Principal's Signature (image)</label>
                    <input type="file" name="principal_signature" accept="image/jpeg,image/png,image/webp">
                    <?php if ($settings['principal_signature']): ?>
                        <div style="margin-top:8px;">
                            <img src="<?php echo BASE_URL; ?>assets/uploads/branding/<?php echo e($settings['principal_signature']); ?>" style="height:42px;object-fit:contain;background:#f8f9ff;padding:4px 10px;border-radius:8px;border:1px solid #e0e4ef;">
                        </div>
                    <?php endif; ?>
                </div>
            </div>

            <button type="submit" class="btn">💾 Save Settings</button>
        </form>
    </div>
</div>
</body>
</html>
