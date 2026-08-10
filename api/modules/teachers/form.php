<?php
require_once __DIR__ . '/../../config/app.php';
require_login();

$pdo = getDB();
$id = clean_id($_GET['id'] ?? null);
require_perm($id ? 'edit' : 'add', 'teachers');
$teacher = null;

/**
 * Generate the next available teacher code, e.g. TCH-0001, TCH-0002...
 */
function next_teacher_code(PDO $pdo): string {
    $max = $pdo->query("SELECT teacher_code FROM teachers WHERE teacher_code REGEXP '^TCH-[0-9]+$' ORDER BY CAST(SUBSTRING(teacher_code, 5) AS UNSIGNED) DESC LIMIT 1")->fetchColumn();
    $next = 1;
    if ($max && preg_match('/^TCH-(\d+)$/', $max, $m)) {
        $next = (int)$m[1] + 1;
    }
    return 'TCH-' . str_pad((string)$next, 4, '0', STR_PAD_LEFT);
}

if ($id) {
    $stmt = $pdo->prepare("SELECT * FROM teachers WHERE id = ?");
    $stmt->execute([$id]);
    $teacher = $stmt->fetch();
    if (!$teacher) {
        flash('error', 'Teacher not found.');
        redirect('list.php');
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verify();

    $full_name     = trim($_POST['full_name'] ?? '');
    $teacher_code  = $id ? $teacher['teacher_code'] : next_teacher_code($pdo);
    $gender        = trim($_POST['gender'] ?? '');
    $email         = trim($_POST['email'] ?? '');
    $phone         = trim($_POST['phone'] ?? '');
    $address       = trim($_POST['address'] ?? '');
    $qualification = trim($_POST['qualification'] ?? '');
    $joining_date  = $_POST['joining_date'] ?? null;
    $fixed_salary  = (float)($_POST['fixed_salary'] ?? 0);
    $is_active     = isset($_POST['is_active']) ? 1 : 0;

    $errors = [];
    if ($full_name === '') $errors[] = 'Full name is required.';
    if ($email !== '' && !filter_var($email, FILTER_VALIDATE_EMAIL)) $errors[] = 'Email is not valid.';
    if ($fixed_salary < 0) $errors[] = 'Fixed salary cannot be negative.';

    if (!$errors) {
        // teacher_code is auto-generated and unique by construction; no duplicate check needed.
    }

    if ($errors) {
        foreach ($errors as $err) flash('error', $err);
    } else {
        $picDir = BASE_PATH . '/assets/uploads/teachers';
        $picture = handle_picture_upload('picture', $picDir);

        if ($id) {
            $sql = "UPDATE teachers SET full_name=?, teacher_code=?, gender=?, email=?, phone=?, address=?,
                    qualification=?, joining_date=?, fixed_salary=?, is_active=?" . ($picture ? ", picture=?" : "") . " WHERE id=?";
            $params = [$full_name,$teacher_code,$gender,$email,$phone,$address,$qualification,$joining_date ?: null,$fixed_salary,$is_active];
            if ($picture) $params[] = $picture;
            $params[] = $id;
            $pdo->prepare($sql)->execute($params);
            log_activity('teacher_updated', "id=$id");
            flash('success', 'Teacher updated.');
        } else {
            $stmt = $pdo->prepare(
                "INSERT INTO teachers (full_name, teacher_code, gender, email, phone, address, qualification, joining_date, fixed_salary, is_active, picture)
                 VALUES (?,?,?,?,?,?,?,?,?,?,?)"
            );
            $stmt->execute([$full_name,$teacher_code,$gender,$email,$phone,$address,$qualification,$joining_date ?: null,$fixed_salary,$is_active,$picture]);
            log_activity('teacher_created', "code=$teacher_code");
            flash('success', 'Teacher added. You can now create a login account for them from the list page.');
        }
        redirect('list.php');
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title><?php echo $id ? 'Edit' : 'Add'; ?> Teacher – <?php echo e(APP_NAME); ?></title>
    <link rel="stylesheet" href="<?php echo BASE_URL; ?>assets/css/style.css">
</head>
<body>
<?php include BASE_PATH . '/includes/sidebar.php'; ?>
<div class="content">
    <div class="card">
        <div class="card-header">
            <div class="card-title"><?php echo $id ? '✏️ Edit Teacher' : '➕ Add Teacher'; ?></div>
            <a href="list.php" class="btn btn-secondary">← Back to List</a>
        </div>

        <?php echo flash_render(); ?>

        <form method="POST" enctype="multipart/form-data">
            <?php echo csrf_field(); ?>
            <div class="form-grid">
                <div class="form-group">
                    <label>Full Name *</label>
                    <input type="text" name="full_name" required maxlength="100" value="<?php echo e($teacher['full_name'] ?? ''); ?>">
                </div>
                <div class="form-group">
                    <label>Teacher Code <span style="color:#888;font-weight:400;">(auto-generated)</span></label>
                    <input type="text" value="<?php echo e($teacher['teacher_code'] ?? next_teacher_code($pdo)); ?>" readonly style="background:#f3f5fb;color:#1e3c72;font-weight:700;">
                </div>
                <div class="form-group">
                    <label>Gender</label>
                    <select name="gender">
                        <?php foreach (['','Male','Female','Other'] as $g): ?>
                            <option value="<?php echo $g; ?>" <?php echo (($teacher['gender'] ?? '') === $g) ? 'selected' : ''; ?>><?php echo $g ?: '— Select —'; ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="form-group">
                    <label>Email</label>
                    <input type="email" name="email" maxlength="100" value="<?php echo e($teacher['email'] ?? ''); ?>">
                </div>
                <div class="form-group">
                    <label>Phone</label>
                    <input type="text" name="phone" maxlength="20" value="<?php echo e($teacher['phone'] ?? ''); ?>">
                </div>
                <div class="form-group">
                    <label>Qualification</label>
                    <input type="text" name="qualification" maxlength="150" value="<?php echo e($teacher['qualification'] ?? ''); ?>">
                </div>
                <div class="form-group">
                    <label>Joining Date</label>
                    <input type="date" name="joining_date" value="<?php echo e($teacher['joining_date'] ?? date('Y-m-d')); ?>">
                </div>
                <div class="form-group">
                    <label>Fixed Monthly Salary</label>
                    <input type="number" name="fixed_salary" min="0" step="0.01" value="<?php echo e($teacher['fixed_salary'] ?? '0'); ?>">
                </div>
                <div class="form-group">
                    <label><input type="checkbox" name="is_active" style="width:auto;display:inline;margin-right:8px;" <?php echo (($teacher['is_active'] ?? 1)) ? 'checked' : ''; ?>> Active</label>
                </div>
            </div>
            <div class="form-group">
                <label>Address</label>
                <textarea name="address" rows="2"><?php echo e($teacher['address'] ?? ''); ?></textarea>
            </div>
            <div class="form-group">
                <label>Photo</label>
                <input type="file" name="picture" accept="image/jpeg,image/png,image/webp">
                <?php if (!empty($teacher['picture'])): ?>
                    <img src="<?php echo BASE_URL; ?>assets/uploads/teachers/<?php echo e($teacher['picture']); ?>" style="width:60px;height:60px;border-radius:10px;object-fit:cover;margin-top:6px;">
                <?php endif; ?>
            </div>
            <button type="submit" class="btn">💾 <?php echo $id ? 'Update Teacher' : 'Save Teacher'; ?></button>
        </form>
    </div>
</div>
</body>
</html>
