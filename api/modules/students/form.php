<?php
require_once __DIR__ . '/../../config/app.php';
require_login();

$pdo = getDB();
$id = clean_id($_GET['id'] ?? null);
$student = null;

$action = $id ? 'edit' : 'add';
if (!is_admin() && current_role() !== 'teacher' && !can($action, 'students')) {
    require_role('admin'); // 403s with a clear message
}

if ($id) {
    $stmt = $pdo->prepare("SELECT * FROM students WHERE id = ?");
    $stmt->execute([$id]);
    $student = $stmt->fetch();
    if (!$student) {
        flash('error', 'Student not found.');
        redirect('list.php');
    }
    // Teachers may only edit students in their own section. (Other permissioned
    // roles like Receptionist aren't restricted this way — their access is
    // controlled purely by the can() check above.)
    if (current_role() === 'teacher') {
        $check = $pdo->prepare("SELECT class_teacher_id FROM sections WHERE id = ?");
        $check->execute([$student['section_id']]);
        $sec = $check->fetch();
        if (!$sec || (int)$sec['class_teacher_id'] !== (int)current_teacher_id()) {
            require_role('admin'); // will 403
        }
    }
}

$classes = $pdo->query("SELECT id, class_name FROM classes ORDER BY sort_order")->fetchAll();

// Suggest the next roll number for new students (purely a UI convenience, not enforced).
$nextRollSuggestion = '';
if (!$id) {
    $maxRoll = $pdo->query("SELECT roll_no FROM students ORDER BY id DESC LIMIT 1")->fetch();
    if ($maxRoll && ctype_digit($maxRoll['roll_no'])) {
        $nextRollSuggestion = (string)((int)$maxRoll['roll_no'] + 1);
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verify();

    $full_name        = trim($_POST['full_name'] ?? '');
    $roll_no          = trim($_POST['roll_no'] ?? '');
    $gender           = trim($_POST['gender'] ?? '');
    $dob              = $_POST['date_of_birth'] ?? '';
    $class_id         = clean_id($_POST['class_id'] ?? null);
    $section_id       = clean_id($_POST['section_id'] ?? null);
    $guardian_name    = trim($_POST['guardian_name'] ?? '');
    $guardian_phone   = trim($_POST['guardian_phone'] ?? '');
    $guardian_email   = trim($_POST['guardian_email'] ?? '');
    $guardian_relation= trim($_POST['guardian_relation'] ?? '');
    $address          = trim($_POST['address'] ?? '');
    $enrollment_date  = $_POST['enrollment_date'] ?? date('Y-m-d');
    $status           = $_POST['status'] ?? 'Active';

    // Extended admission fields
    $cnic_bform        = trim($_POST['cnic_bform'] ?? '');
    $blood_group        = trim($_POST['blood_group'] ?? '');
    $religion           = trim($_POST['religion'] ?? '');
    $caste              = trim($_POST['caste'] ?? '');
    $identification_mark= trim($_POST['identification_mark'] ?? '');
    $disease_if_any     = trim($_POST['disease_if_any'] ?? '');
    $previous_school    = trim($_POST['previous_school'] ?? '');
    $previous_id_board  = trim($_POST['previous_id_board_roll_no'] ?? '');
    $is_orphan          = isset($_POST['is_orphan']) ? 1 : 0;
    $additional_note    = trim($_POST['additional_note'] ?? '');
    $discount_percent   = (float)($_POST['discount_percent'] ?? 0);
    $mobile_for_sms      = trim($_POST['mobile_for_sms'] ?? '');

    $errors = [];
    if ($full_name === '') $errors[] = 'Full name is required.';
    if ($roll_no === '') $errors[] = 'Roll number is required.';
    if ($guardian_email !== '' && !filter_var($guardian_email, FILTER_VALIDATE_EMAIL)) {
        $errors[] = 'Guardian email is not valid.';
    }
    if ($cnic_bform !== '' && !preg_match('/^[0-9\-]{5,20}$/', $cnic_bform)) {
        $errors[] = 'CNIC / B-Form number should only contain digits and dashes (e.g. 35202-1234567-1).';
    }
    if ($discount_percent < 0 || $discount_percent > 100) {
        $errors[] = 'Discount must be between 0 and 100%.';
    }
    if (!in_array($status, ['Active','Inactive','Graduated','Transferred'], true)) {
        $errors[] = 'Invalid status.';
    }

    // Roll number must be unique
    if (!$errors) {
        $dupStmt = $id
            ? $pdo->prepare("SELECT id FROM students WHERE roll_no = ? AND id != ?")
            : $pdo->prepare("SELECT id FROM students WHERE roll_no = ?");
        $id ? $dupStmt->execute([$roll_no, $id]) : $dupStmt->execute([$roll_no]);
        if ($dupStmt->fetch()) {
            $errors[] = 'This roll number is already in use by another student.';
        }
    }

    // CNIC/B-Form must be unique if provided (it's a national identifier)
    if (!$errors && $cnic_bform !== '') {
        $dupStmt = $id
            ? $pdo->prepare("SELECT id FROM students WHERE cnic_bform = ? AND id != ?")
            : $pdo->prepare("SELECT id FROM students WHERE cnic_bform = ?");
        $id ? $dupStmt->execute([$cnic_bform, $id]) : $dupStmt->execute([$cnic_bform]);
        if ($dupStmt->fetch()) {
            $errors[] = 'This CNIC / B-Form number is already registered to another student.';
        }
    }

    if ($errors) {
        foreach ($errors as $err) flash('error', $err);
    } else {
        $picture = handle_picture_upload('picture', UPLOAD_PATH);

        $fields = [
            'full_name'=>$full_name, 'roll_no'=>$roll_no, 'gender'=>$gender, 'date_of_birth'=>$dob ?: null,
            'class_id'=>$class_id, 'section_id'=>$section_id, 'guardian_name'=>$guardian_name,
            'guardian_phone'=>$guardian_phone, 'guardian_email'=>$guardian_email, 'guardian_relation'=>$guardian_relation,
            'address'=>$address, 'enrollment_date'=>$enrollment_date, 'status'=>$status,
            'cnic_bform'=>$cnic_bform, 'blood_group'=>$blood_group, 'religion'=>$religion, 'caste'=>$caste,
            'identification_mark'=>$identification_mark, 'disease_if_any'=>$disease_if_any,
            'previous_school'=>$previous_school, 'previous_id_board_roll_no'=>$previous_id_board,
            'is_orphan'=>$is_orphan, 'additional_note'=>$additional_note, 'discount_percent'=>$discount_percent,
            'mobile_for_sms'=>$mobile_for_sms,
        ];
        if ($picture) $fields['picture'] = $picture;

        if ($id) {
            $setSql = implode(', ', array_map(fn($k) => "$k = ?", array_keys($fields)));
            $params = array_merge(array_values($fields), [$id]);
            $pdo->prepare("UPDATE students SET $setSql WHERE id = ?")->execute($params);
            log_activity('student_updated', "id=$id");
            flash('success', 'Student updated successfully.');
        } else {
            $cols = implode(', ', array_keys($fields));
            $placeholders = implode(', ', array_fill(0, count($fields), '?'));
            $pdo->prepare("INSERT INTO students ($cols) VALUES ($placeholders)")->execute(array_values($fields));
            $newStudentId = (int)$pdo->lastInsertId();
            log_activity('student_created', "roll_no=$roll_no");

            // Auto-create a student login account, same pattern as teacher logins.
            // Username: roll number, lowercased with spaces stripped (usually already clean).
            // If that username is somehow taken, fall back to std<id>.
            $studentUsername = strtolower(preg_replace('/\s+/', '', $roll_no));
            $dupCheck = $pdo->prepare("SELECT id FROM users WHERE username = ?");
            $dupCheck->execute([$studentUsername]);
            if ($studentUsername === '' || $dupCheck->fetch()) {
                $studentUsername = 'std' . $newStudentId;
            }
            $studentTempPassword = bin2hex(random_bytes(4)); // 8 hex characters, easy enough for a student/parent to type
            $studentHash = password_hash($studentTempPassword, PASSWORD_DEFAULT);
            $pdo->prepare(
                "INSERT INTO users (username, password, role, full_name, student_id, must_change_password) VALUES (?,?,?,?,?,1)"
            )->execute([$studentUsername, $studentHash, 'student', $full_name, $newStudentId]);
            log_activity('student_login_created', "student_id=$newStudentId username=$studentUsername");

            // Structured data for the pretty printable credentials card (shown once).
            $_SESSION['new_login_card'] = [
                'student_id' => $newStudentId,
                'full_name'  => $full_name,
                'roll_no'    => $roll_no,
                'class_name' => null, // filled in below if we can look it up cheaply
                'username'   => $studentUsername,
                'password'   => $studentTempPassword,
            ];
            if ($class_id) {
                $cn = $pdo->prepare("SELECT class_name FROM classes WHERE id = ?");
                $cn->execute([$class_id]);
                $_SESSION['new_login_card']['class_name'] = $cn->fetchColumn() ?: null;
            }

            flash('success', 'Student added successfully. Login credentials are ready below.');
        }
        redirect('list.php');
    }
}

// Sections for the selected class (for the dropdown), used both on load and via AJAX in sections.php
$sections = [];
$selectedClassId = $student['class_id'] ?? clean_id($_POST['class_id'] ?? null);
if ($selectedClassId) {
    $s = $pdo->prepare("SELECT id, section_name FROM sections WHERE class_id = ? ORDER BY section_name");
    $s->execute([$selectedClassId]);
    $sections = $s->fetchAll();
}

function val($student, $field, $default = '') {
    return e($student[$field] ?? $default);
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title><?php echo $id ? 'Edit' : 'Add'; ?> Student – <?php echo e(APP_NAME); ?></title>
    <link rel="stylesheet" href="<?php echo BASE_URL; ?>assets/css/style.css">
    <style>
        .section-step {
            display: flex; align-items: center; gap: 10px; margin: 28px 0 16px;
            padding-bottom: 10px; border-bottom: 2px solid #f0f2f5;
        }
        .section-step:first-child { margin-top: 0; }
        .step-num {
            width: 26px; height: 26px; border-radius: 50%; background: linear-gradient(135deg,#1e3c72,#2a5298);
            color: white; display: flex; align-items: center; justify-content: center; font-size: 13px; font-weight: 700; flex-shrink: 0;
        }
        .step-title { font-size: 16px; font-weight: 700; color: #1e3c72; }
        .photo-preview {
            width: 90px; height: 90px; border-radius: 12px; object-fit: cover; margin-top: 8px;
            border: 2px solid #e8eaff;
        }
        .checkbox-inline { display: flex; align-items: center; gap: 8px; margin-bottom: 10px; }
        .checkbox-inline input { width: auto; margin: 0; }
        .helper-text { font-size: 12px; color: #999; margin-top: -6px; margin-bottom: 10px; }
    </style>
</head>
<body>
    <?php include BASE_PATH . '/includes/sidebar.php'; ?>
    <div class="content">
        <div class="card">
            <div class="card-header">
                <div class="card-title"><?php echo $id ? '✏️ Edit Student' : '➕ Student Admission Form'; ?></div>
                <a href="list.php" class="btn btn-secondary">← Back to List</a>
            </div>

            <?php echo flash_render(); ?>

            <form method="POST" enctype="multipart/form-data">
                <?php echo csrf_field(); ?>

                <!-- STEP 1: Student Information -->
                <div class="section-step"><div class="step-num">1</div><div class="step-title">Student Information</div></div>

                <div class="form-grid">
                    <div class="form-group">
                        <label>Full Name *</label>
                        <input type="text" name="full_name" required maxlength="100" value="<?php echo val($student, 'full_name', $_POST['full_name'] ?? ''); ?>">
                    </div>
                    <div class="form-group">
                        <label>Roll Number *</label>
                        <input type="text" name="roll_no" required maxlength="20" placeholder="<?php echo e($nextRollSuggestion); ?>" value="<?php echo val($student, 'roll_no', $_POST['roll_no'] ?? ''); ?>">
                    </div>
                    <div class="form-group">
                        <label>Class</label>
                        <select name="class_id" id="class_id" onchange="loadSections()">
                            <option value="">— Select Class —</option>
                            <?php foreach ($classes as $c): ?>
                                <option value="<?php echo $c['id']; ?>" <?php echo ($selectedClassId == $c['id']) ? 'selected' : ''; ?>>
                                    <?php echo e($c['class_name']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="form-group">
                        <label>Section</label>
                        <select name="section_id" id="section_id">
                            <option value="">— Select Section —</option>
                            <?php foreach ($sections as $sec): ?>
                                <option value="<?php echo $sec['id']; ?>" <?php echo (($student['section_id'] ?? null) == $sec['id']) ? 'selected' : ''; ?>>
                                    <?php echo e($sec['section_name']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="form-group">
                        <label>Date of Admission</label>
                        <input type="date" name="enrollment_date" value="<?php echo val($student, 'enrollment_date', date('Y-m-d')); ?>">
                    </div>
                    <div class="form-group">
                        <label>Discount in Fee (%)</label>
                        <input type="number" name="discount_percent" min="0" max="100" step="0.01" value="<?php echo val($student, 'discount_percent', '0'); ?>">
                    </div>
                    <div class="form-group">
                        <label>Mobile for SMS / Reminders</label>
                        <input type="text" name="mobile_for_sms" maxlength="20" placeholder="e.g. 03001234567" value="<?php echo val($student, 'mobile_for_sms'); ?>">
                    </div>
                    <div class="form-group">
                        <label>Status</label>
                        <select name="status">
                            <?php foreach (['Active','Inactive','Graduated','Transferred'] as $st): ?>
                                <option value="<?php echo $st; ?>" <?php echo (($student['status'] ?? 'Active') === $st) ? 'selected' : ''; ?>><?php echo $st; ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>

                <div class="form-group">
                    <label>Student Photo <?php echo !empty($student['picture']) ? '(leave blank to keep current photo)' : ''; ?></label>
                    <input type="file" name="picture" accept="image/jpeg,image/png,image/webp">
                    <?php if (!empty($student['picture'])): ?>
                        <img src="<?php echo BASE_URL . e(UPLOAD_URL . '/' . $student['picture']); ?>" class="photo-preview">
                    <?php endif; ?>
                </div>

                <!-- STEP 2: Other Information -->
                <div class="section-step"><div class="step-num">2</div><div class="step-title">Other Information</div></div>

                <div class="form-grid">
                    <div class="form-group">
                        <label>Date of Birth</label>
                        <input type="date" name="date_of_birth" value="<?php echo val($student, 'date_of_birth'); ?>">
                    </div>
                    <div class="form-group">
                        <label>CNIC / B-Form Number</label>
                        <input type="text" name="cnic_bform" maxlength="20" placeholder="e.g. 35202-1234567-1" value="<?php echo val($student, 'cnic_bform'); ?>">
                    </div>
                    <div class="form-group">
                        <label>Gender</label>
                        <select name="gender">
                            <?php foreach (['','Male','Female','Other'] as $g): ?>
                                <option value="<?php echo $g; ?>" <?php echo (($student['gender'] ?? '') === $g) ? 'selected' : ''; ?>><?php echo $g ?: '— Select —'; ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="form-group">
                        <label>Blood Group</label>
                        <select name="blood_group">
                            <?php foreach (['','A+','A-','B+','B-','AB+','AB-','O+','O-'] as $bg): ?>
                                <option value="<?php echo $bg; ?>" <?php echo (($student['blood_group'] ?? '') === $bg) ? 'selected' : ''; ?>><?php echo $bg ?: '— Select —'; ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="form-group">
                        <label>Religion</label>
                        <select name="religion">
                            <?php foreach (['','Islam','Christianity','Hinduism','Sikhism','Other'] as $rel): ?>
                                <option value="<?php echo $rel; ?>" <?php echo (($student['religion'] ?? '') === $rel) ? 'selected' : ''; ?>><?php echo $rel ?: '— Select —'; ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="form-group">
                        <label>Caste</label>
                        <input type="text" name="caste" maxlength="50" value="<?php echo val($student, 'caste'); ?>">
                    </div>
                    <div class="form-group">
                        <label>Identification Mark</label>
                        <input type="text" name="identification_mark" maxlength="150" value="<?php echo val($student, 'identification_mark'); ?>">
                    </div>
                    <div class="form-group">
                        <label>Disease, if any</label>
                        <input type="text" name="disease_if_any" maxlength="150" value="<?php echo val($student, 'disease_if_any'); ?>">
                    </div>
                    <div class="form-group">
                        <label>Previous School</label>
                        <input type="text" name="previous_school" maxlength="150" value="<?php echo val($student, 'previous_school'); ?>">
                    </div>
                    <div class="form-group">
                        <label>Previous ID / Board Roll No</label>
                        <input type="text" name="previous_id_board_roll_no" maxlength="50" value="<?php echo val($student, 'previous_id_board_roll_no'); ?>">
                    </div>
                </div>

                <div class="checkbox-inline">
                    <input type="checkbox" name="is_orphan" id="is_orphan" <?php echo !empty($student['is_orphan']) ? 'checked' : ''; ?>>
                    <label for="is_orphan" style="margin:0;">Orphan Student</label>
                </div>

                <div class="form-group">
                    <label>Any Additional Note</label>
                    <textarea name="additional_note" rows="2"><?php echo val($student, 'additional_note'); ?></textarea>
                </div>

                <!-- STEP 3: Guardian Information -->
                <div class="section-step"><div class="step-num">3</div><div class="step-title">Guardian Information</div></div>

                <div class="form-grid">
                    <div class="form-group">
                        <label>Guardian Name</label>
                        <input type="text" name="guardian_name" maxlength="100" value="<?php echo val($student, 'guardian_name'); ?>">
                    </div>
                    <div class="form-group">
                        <label>Relation</label>
                        <input type="text" name="guardian_relation" maxlength="30" placeholder="Father / Mother / Guardian" value="<?php echo val($student, 'guardian_relation'); ?>">
                    </div>
                    <div class="form-group">
                        <label>Guardian Phone</label>
                        <input type="text" name="guardian_phone" maxlength="20" value="<?php echo val($student, 'guardian_phone'); ?>">
                    </div>
                    <div class="form-group">
                        <label>Guardian Email</label>
                        <input type="email" name="guardian_email" maxlength="100" value="<?php echo val($student, 'guardian_email'); ?>">
                    </div>
                </div>

                <div class="form-group">
                    <label>Address</label>
                    <textarea name="address" rows="2"><?php echo val($student, 'address'); ?></textarea>
                </div>

                <button type="submit" class="btn" style="margin-top:10px;">💾 <?php echo $id ? 'Update Student' : 'Save Student'; ?></button>
            </form>
        </div>
    </div>

    <script>
        async function loadSections() {
            const classId = document.getElementById('class_id').value;
            const sectionSelect = document.getElementById('section_id');
            sectionSelect.innerHTML = '<option value="">Loading...</option>';
            if (!classId) {
                sectionSelect.innerHTML = '<option value="">— Select Section —</option>';
                return;
            }
            try {
                const res = await fetch('sections_ajax.php?class_id=' + encodeURIComponent(classId));
                const data = await res.json();
                sectionSelect.innerHTML = '<option value="">— Select Section —</option>';
                data.forEach(sec => {
                    const opt = document.createElement('option');
                    opt.value = sec.id;
                    opt.textContent = sec.section_name;
                    sectionSelect.appendChild(opt);
                });
            } catch (e) {
                sectionSelect.innerHTML = '<option value="">— Select Section —</option>';
            }
        }
    </script>
</body>
</html>
