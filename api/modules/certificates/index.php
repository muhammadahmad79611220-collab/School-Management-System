<?php
require_once __DIR__ . '/../../config/app.php';
require_login();
if (!is_admin() && !can('view', 'certificates')) {
    require_role('admin'); // 403s with a clear message
}

$pdo = getDB();
$settings = get_settings();

$students = $pdo->query("SELECT s.id, s.full_name, s.roll_no, c.class_name FROM students s LEFT JOIN classes c ON s.class_id = c.id ORDER BY s.full_name")->fetchAll();

$studentId = clean_id($_GET['student_id'] ?? null);
$certType = $_GET['type'] ?? 'leaving';
$validTypes = ['leaving' => 'School Leaving Certificate', 'character' => 'Character Certificate', 'bonafide' => 'Bonafide Certificate'];
if (!isset($validTypes[$certType])) $certType = 'leaving';

$student = null;
if ($studentId) {
    $stmt = $pdo->prepare("SELECT s.*, c.class_name, sec.section_name FROM students s LEFT JOIN classes c ON s.class_id = c.id LEFT JOIN sections sec ON s.section_id = sec.id WHERE s.id = ?");
    $stmt->execute([$studentId]);
    $student = $stmt->fetch();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verify();
    if (!is_admin() && !can('add', 'certificates')) {
        flash('error', 'You do not have permission to issue certificates.');
        redirect('index.php');
    }
    $studentId = clean_id($_POST['student_id'] ?? null);
    $certType = $_POST['certificate_type'] ?? 'leaving';
    if (!$studentId || !isset($validTypes[$certType])) {
        flash('error', 'Please select a student and certificate type.');
    } else {
        $certNo = strtoupper(substr($certType, 0, 3)) . '-' . date('Y') . '-' . str_pad((string)random_int(1, 9999), 4, '0', STR_PAD_LEFT);
        $pdo->prepare("INSERT INTO certificates (student_id, certificate_type, certificate_no, issue_date, issued_by) VALUES (?,?,?,?,?)")
            ->execute([$studentId, $certType, $certNo, date('Y-m-d'), $_SESSION['user_id']]);
        log_activity('certificate_issued', "student_id=$studentId type=$certType no=$certNo");
        redirect("index.php?student_id=$studentId&type=$certType&issued=1");
    }
}

// Most recent certificate number for this student+type, if just issued (for display)
$certNo = null;
$issueDate = date('d F Y');
if ($studentId && !empty($_GET['issued'])) {
    $stmt = $pdo->prepare("SELECT certificate_no, issue_date FROM certificates WHERE student_id = ? AND certificate_type = ? ORDER BY id DESC LIMIT 1");
    $stmt->execute([$studentId, $certType]);
    $cert = $stmt->fetch();
    if ($cert) {
        $certNo = $cert['certificate_no'];
        $issueDate = date('d F Y', strtotime($cert['issue_date']));
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Certificates – <?php echo e(APP_NAME); ?></title>
    <link rel="stylesheet" href="<?php echo BASE_URL; ?>assets/css/style.css">
    <style>
        @media print { .sidebar, .no-print { display: none !important; } .content { margin-left: 0 !important; } }
        .cert-paper {
            max-width: 750px; margin: 0 auto; background: white; padding: 50px 60px;
            border: 10px double #1e3c72; border-radius: 4px; position: relative;
        }
        .cert-header { text-align: center; margin-bottom: 30px; }
        .cert-header img { max-height: 70px; margin-bottom: 10px; }
        .cert-header h1 { color: #1e3c72; font-size: 24px; margin-bottom: 4px; }
        .cert-header p { color: #666; font-size: 13px; }
        .cert-title { text-align: center; font-size: 20px; font-weight: 800; text-decoration: underline; margin: 30px 0; color: #1e3c72; }
        .cert-body { font-size: 16px; line-height: 1.9; color: #222; text-align: justify; }
        .cert-footer { display: flex; justify-content: space-between; margin-top: 60px; font-size: 14px; }
        .cert-no { position: absolute; top: 20px; right: 30px; font-size: 12px; color: #888; }
    </style>
</head>
<body>
<?php include BASE_PATH . '/includes/sidebar.php'; ?>
<div class="content">
    <div class="card no-print">
        <div class="card-header">
            <div class="card-title">📄 Certificate Generator</div>
        </div>

        <?php echo flash_render(); ?>

        <form method="GET" class="form-grid" style="margin-bottom:10px;">
            <div class="form-group">
                <label>Student</label>
                <select name="student_id" onchange="this.form.submit()">
                    <option value="">— Select Student —</option>
                    <?php foreach ($students as $s): ?>
                        <option value="<?php echo $s['id']; ?>" <?php echo $studentId == $s['id'] ? 'selected' : ''; ?>>
                            <?php echo e($s['full_name'] . ' (' . $s['roll_no'] . ') - ' . ($s['class_name'] ?? '')); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="form-group">
                <label>Certificate Type</label>
                <select name="type" onchange="this.form.submit()">
                    <?php foreach ($validTypes as $key => $label): ?>
                        <option value="<?php echo $key; ?>" <?php echo $certType === $key ? 'selected' : ''; ?>><?php echo $label; ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
        </form>

        <?php if ($student && (is_admin() || can('add', 'certificates'))): ?>
        <form method="POST" style="display:inline;">
            <?php echo csrf_field(); ?>
            <input type="hidden" name="student_id" value="<?php echo $studentId; ?>">
            <input type="hidden" name="certificate_type" value="<?php echo e($certType); ?>">
            <button type="submit" class="btn btn-success">📝 Issue New Certificate (assigns a number)</button>
        </form>
        <?php endif; ?>
        <?php if ($student): ?>
            <button onclick="window.print()" class="btn">🖨️ Print</button>
        <?php endif; ?>
    </div>

    <?php if ($student): ?>
    <div class="cert-paper">
        <?php if ($certNo): ?><div class="cert-no">Certificate No: <?php echo e($certNo); ?></div><?php endif; ?>

        <div class="cert-header">
            <?php if (!empty($settings['logo'])): ?>
                <img src="<?php echo BASE_URL; ?>assets/uploads/branding/<?php echo e($settings['logo']); ?>" alt="logo">
            <?php endif; ?>
            <h1>🏫 <?php echo e($settings['school_name']); ?></h1>
            <?php if (!empty($settings['tagline'])): ?><p><?php echo e($settings['tagline']); ?></p><?php endif; ?>
            <?php if (!empty($settings['address'])): ?><p><?php echo e($settings['address']); ?></p><?php endif; ?>
        </div>

        <div class="cert-title"><?php echo $validTypes[$certType]; ?></div>

        <div class="cert-body">
            <?php if ($certType === 'leaving'): ?>
                This is to certify that <strong><?php echo e($student['full_name']); ?></strong>,
                Roll No. <strong><?php echo e($student['roll_no']); ?></strong>,
                S/D of <strong><?php echo e($student['guardian_name'] ?: '______________'); ?></strong>,
                was a student of <strong><?php echo e($student['class_name'] ?? '______'); ?></strong>
                <?php if ($student['section_name']): ?>, Section <strong><?php echo e($student['section_name']); ?></strong><?php endif; ?>
                at this institute. The student's conduct during their time here was satisfactory, and they are
                relieved from this institution as of <strong><?php echo $issueDate; ?></strong>.
                This certificate is issued upon request for further academic or official purposes.

            <?php elseif ($certType === 'character'): ?>
                This is to certify that <strong><?php echo e($student['full_name']); ?></strong>,
                Roll No. <strong><?php echo e($student['roll_no']); ?></strong>,
                a student of <strong><?php echo e($student['class_name'] ?? '______'); ?></strong> at this institute,
                has borne a good moral character throughout their association with this school.
                To the best of our knowledge, the student has not been involved in any activity that would
                reflect poorly on their character. This certificate is issued on
                <strong><?php echo $issueDate; ?></strong> for whatever purpose it may serve.

            <?php else: ?>
                This is to certify that <strong><?php echo e($student['full_name']); ?></strong>,
                Roll No. <strong><?php echo e($student['roll_no']); ?></strong>,
                is a bonafide student of <strong><?php echo e($student['class_name'] ?? '______'); ?></strong>
                <?php if ($student['section_name']): ?>, Section <strong><?php echo e($student['section_name']); ?></strong><?php endif; ?>
                at this institute for the academic year <strong><?php echo e($settings['academic_year'] ?: '______'); ?></strong>.
                This certificate is issued upon request for official purposes.
            <?php endif; ?>
        </div>

        <div class="cert-footer">
            <div>Date: <?php echo $issueDate; ?></div>
            <div style="text-align:center;">
                <?php if (!empty($settings['principal_signature'])): ?>
                    <img src="<?php echo BASE_URL; ?>assets/uploads/branding/<?php echo e($settings['principal_signature']); ?>" style="max-height:40px;display:block;margin-bottom:4px;">
                <?php endif; ?>
                <div style="border-top:1px solid #333;padding-top:4px;min-width:160px;">
                    <?php echo e($settings['principal_name'] ?: 'Principal'); ?>
                </div>
            </div>
        </div>
    </div>
    <?php else: ?>
        <p style="color:#aaa;text-align:center;padding:40px;">Select a student to preview their certificate.</p>
    <?php endif; ?>
</div>
</body>
</html>
