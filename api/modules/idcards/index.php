<?php
require_once __DIR__ . '/../../config/app.php';
require_login();
if (!is_admin() && !can('view', 'idcards')) {
    require_role('admin');
}

$pdo = getDB();
$settings = get_settings();
$brandName = !empty($settings['school_name']) ? $settings['school_name'] : APP_NAME;

$type = $_GET['cardtype'] ?? 'student';
if (!in_array($type, ['student','teacher'], true)) $type = 'student';

$classes = $pdo->query("SELECT id, class_name FROM classes ORDER BY sort_order")->fetchAll();
$classId = clean_id($_GET['class_id'] ?? null);

$people = [];
if ($type === 'student') {
    $sql = "SELECT s.id, s.full_name, s.roll_no, s.picture, s.date_of_birth, s.guardian_name, s.guardian_phone, s.cnic_bform, c.class_name, sec.section_name
            FROM students s LEFT JOIN classes c ON s.class_id = c.id LEFT JOIN sections sec ON s.section_id = sec.id
            WHERE s.status = 'Active'" . ($classId ? " AND s.class_id = ?" : "") . " ORDER BY s.full_name";
    $stmt = $pdo->prepare($sql);
    $classId ? $stmt->execute([$classId]) : $stmt->execute();
    $people = $stmt->fetchAll();
} else {
    $people = $pdo->query("SELECT id, full_name, teacher_code, picture, phone FROM teachers WHERE is_active=1 ORDER BY full_name")->fetchAll();
}

$selectedIds = array_filter(array_map('intval', $_GET['ids'] ?? []));

// Build a simple deterministic "barcode" pattern (visual only) from an id string.
function id_barcode_bars(string $code): string {
    $bars = '';
    $seed = 0;
    foreach (str_split($code) as $ch) { $seed += ord($ch); }
    mt_srand($seed ?: 1);
    $n = 28;
    for ($i = 0; $i < $n; $i++) {
        $w = mt_rand(1, 3);
        $bars .= '<div class="bar" style="width:' . $w . 'px;"></div>';
    }
    return $bars;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>ID Cards – <?php echo e($brandName); ?></title>
    <link rel="stylesheet" href="<?php echo BASE_URL; ?>assets/css/style.css">
    <script src="<?php echo BASE_URL; ?>assets/js/qrcode.min.js"></script>
    <style>
        @media print {
            .sidebar, .no-print { display: none !important; }
            .content { margin-left: 0 !important; padding: 0 !important; }
            .card-pair { box-shadow: none !important; }
        }

        .card-grid { display: flex; flex-wrap: wrap; gap: 22px; margin-top: 22px; }

        .card-pair {
            display: flex; flex-direction: column; gap: 10px;
            background: #fff; border-radius: 16px; padding: 14px;
            box-shadow: 0 6px 22px rgba(0,0,0,0.10);
        }
        .pair-label { font-size: 10px; text-transform: uppercase; letter-spacing: 1px; color: #999; text-align: center; font-weight: 700; }

        /* ===== ID CARD (CR80-ish ratio) ===== */
        .id-card {
            width: 324px; height: 220px; border-radius: 16px; position: relative;
            overflow: hidden; font-family: 'Segoe UI', Tahoma, sans-serif;
            box-shadow: 0 3px 10px rgba(0,0,0,0.18);
            background: #fff;
        }

        /* FRONT */
        .id-card.front {
            background: linear-gradient(165deg, #ffffff 0%, #f4f7fd 60%, #eef2fb 100%);
            border: 1px solid #e3e8f5;
        }
        .id-card.front .top-band {
            background: linear-gradient(120deg, #1e3c72 0%, #2a5298 55%, #1a6b3a 130%);
            color: #fff; padding: 10px 12px; display: flex; align-items: center; gap: 10px;
            position: relative; overflow: hidden;
        }
        .id-card.front .top-band::after {
            content: ''; position: absolute; right: -30px; top: -30px; width: 110px; height: 110px;
            background: rgba(255,255,255,0.08); border-radius: 50%;
        }
        .id-card .logo-circle {
            width: 34px; height: 34px; border-radius: 50%; background: rgba(255,255,255,0.18);
            border: 1.5px solid rgba(255,255,255,0.6); display: flex; align-items: center; justify-content: center;
            font-size: 17px; flex-shrink: 0; overflow: hidden; z-index: 1;
        }
        .id-card .logo-circle img { width: 100%; height: 100%; object-fit: cover; }
        .id-card .top-band .sname { font-size: 12.5px; font-weight: 800; line-height: 1.2; z-index: 1; }
        .id-card .top-band .saddr { font-size: 8.5px; opacity: 0.85; margin-top: 1px; z-index: 1; }
        .id-card .role-ribbon {
            margin-left: auto; background: rgba(255,255,255,0.92); color: #1e3c72; font-size: 9px;
            font-weight: 800; letter-spacing: 0.6px; padding: 3px 9px; border-radius: 20px; z-index: 1;
            text-transform: uppercase; box-shadow: 0 2px 6px rgba(0,0,0,0.15);
        }

        .id-card .body-row { display: flex; padding: 12px 14px 0; gap: 12px; }
        .id-card .photo-frame {
            width: 78px; height: 88px; border-radius: 10px; background: #e8edf8;
            border: 3px solid #fff; box-shadow: 0 2px 8px rgba(30,60,114,0.25);
            display: flex; align-items: center; justify-content: center; font-size: 34px;
            overflow: hidden; flex-shrink: 0;
        }
        .id-card .photo-frame img { width: 100%; height: 100%; object-fit: cover; }
        .id-card .details { font-size: 10.3px; line-height: 1.42; color: #333; flex: 1; min-width: 0; }
        .id-card .details .pname { font-size: 14px; font-weight: 800; color: #1e3c72; margin-bottom: 2px; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
        .id-card .details .row b { color: #888; font-weight: 600; }
        .id-card .details .row { white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
        .id-card .id-pill {
            display: inline-block; margin-top: 3px; background: linear-gradient(135deg,#1a6b3a,#27ae60); color: #fff;
            font-size: 9.5px; font-weight: 800; padding: 2px 8px; border-radius: 6px; letter-spacing: 0.4px;
        }

        .id-card .bottom-band {
            position: absolute; left: 0; right: 0; bottom: 0; padding: 6px 12px 7px;
            display: flex; align-items: center; justify-content: space-between; gap: 8px;
            border-top: 1.5px dashed #c7d2ea; background: rgba(255,255,255,0.65);
        }
        .id-card .barcode { display: flex; align-items: flex-end; gap: 1.5px; height: 18px; flex-shrink: 0; }
        .id-card .barcode .bar { background: #1e3c72; height: 100%; }
        .id-card .valid-year { font-size: 8px; color: #1e3c72; font-weight: 700; text-align: center; line-height: 1.3; }
        .id-card .qr-box { width: 50px; height: 50px; flex-shrink: 0; background: #fff; border-radius: 4px; padding: 3px; box-shadow: 0 1px 4px rgba(0,0,0,0.15); }
        .id-card .qr-box img, .id-card .qr-box canvas, .id-card .qr-box table { width: 100% !important; height: 100% !important; image-rendering: pixelated; }

        .id-card .watermark {
            position: absolute; right: -18px; bottom: 28px; font-size: 90px; opacity: 0.05;
            color: #1e3c72; pointer-events: none; transform: rotate(-12deg);
        }

        /* BACK */
        .id-card.back {
            background: linear-gradient(165deg, #1e3c72 0%, #14294f 100%);
            color: #eef2fb; padding: 14px 16px;
        }
        .id-card.back h4 { font-size: 12px; letter-spacing: 0.5px; text-transform: uppercase; margin-bottom: 8px; color: #74b9ff; border-bottom: 1px solid rgba(255,255,255,0.18); padding-bottom: 6px; }
        .id-card.back .terms { font-size: 9.5px; line-height: 1.55; opacity: 0.85; }
        .id-card.back .terms li { margin-bottom: 3px; }
        .id-card.back .sign-row { position: absolute; left: 16px; right: 16px; bottom: 12px; display: flex; justify-content: space-between; align-items: flex-end; font-size: 9px; }
        .id-card.back .sign-row .sign-block { text-align: center; }
        .id-card.back .sign-row img.sign-img { height: 26px; object-fit: contain; display: block; margin: 0 auto 2px; }
        .id-card.back .sign-row .sign-line { width: 90px; border-top: 1px solid rgba(255,255,255,0.5); margin: 0 auto 3px; }
        .id-card.back .contact { font-size: 9px; opacity: 0.8; margin-top: 8px; }

        .checkbox-cell { width: auto; margin: 0; }
        .tip-box { background:#f8f9ff; border:1px solid #e8eaff; border-radius:10px; padding:10px 14px; font-size:12.5px; color:#555; margin-bottom:14px; }
    </style>
</head>
<body>
<?php include BASE_PATH . '/includes/sidebar.php'; ?>
<div class="content">
    <div class="card no-print">
        <div class="card-header">
            <div class="card-title">🪪 ID Card Generator</div>
        </div>
        <div class="tip-box">✨ Cards print front &amp; back, sized for standard CR80 plastic card sleeves. Logo, school name and signature come straight from <a href="<?php echo BASE_URL; ?>modules/settings/index.php">Institute Settings</a> — update them there any time.</div>

        <form method="GET" class="form-grid" style="margin-bottom:14px;">
            <div class="form-group">
                <label>Card Type</label>
                <select name="cardtype" onchange="this.form.submit()">
                    <option value="student" <?php echo $type==='student'?'selected':''; ?>>Students</option>
                    <option value="teacher" <?php echo $type==='teacher'?'selected':''; ?>>Teachers</option>
                </select>
            </div>
            <?php if ($type === 'student'): ?>
            <div class="form-group">
                <label>Class (optional filter)</label>
                <select name="class_id" onchange="this.form.submit()">
                    <option value="">All Classes</option>
                    <?php foreach ($classes as $c): ?>
                        <option value="<?php echo $c['id']; ?>" <?php echo $classId == $c['id'] ? 'selected' : ''; ?>><?php echo e($c['class_name']); ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <?php endif; ?>
        </form>

        <form method="GET" id="selectForm">
            <input type="hidden" name="cardtype" value="<?php echo e($type); ?>">
            <?php if ($classId): ?><input type="hidden" name="class_id" value="<?php echo $classId; ?>"><?php endif; ?>
            <div class="table-wrap" style="max-height:300px;overflow-y:auto;">
            <table>
                <thead><tr><th><input type="checkbox" onclick="document.querySelectorAll('.pickbox').forEach(c=>c.checked=this.checked)"></th><th>Name</th><th><?php echo $type === 'student' ? 'Roll No' : 'Code'; ?></th></tr></thead>
                <tbody>
                <?php foreach ($people as $p): ?>
                    <tr>
                        <td><input type="checkbox" class="pickbox checkbox-cell" name="ids[]" value="<?php echo $p['id']; ?>" <?php echo in_array($p['id'], $selectedIds, true) ? 'checked' : ''; ?>></td>
                        <td><?php echo e($p['full_name']); ?></td>
                        <td><?php echo e($p['roll_no'] ?? $p['teacher_code'] ?? ''); ?></td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
            </div>
            <button type="submit" class="btn" style="margin-top:12px;">👁️ Generate Selected Cards</button>
        </form>
        <?php if ($selectedIds): ?>
            <button onclick="window.print()" class="btn btn-success" style="margin-top:12px;">🖨️ Print Cards</button>
        <?php endif; ?>
    </div>

    <?php if ($selectedIds): ?>
    <div class="card-grid">
        <?php foreach ($people as $p): if (!in_array($p['id'], $selectedIds, true)) continue; ?>
            <?php
                $code = $type === 'student' ? ($p['roll_no'] ?? ('S' . $p['id'])) : ($p['teacher_code'] ?? ('T' . $p['id']));
                $picPath = $type === 'student'
                    ? (!empty($p['picture']) ? BASE_URL . UPLOAD_URL . '/' . $p['picture'] : null)
                    : (!empty($p['picture']) ? BASE_URL . 'assets/uploads/teachers/' . $p['picture'] : null);
                $scanTable = $type === 'student' ? 'students' : 'teachers';
                $scanToken = ensure_scan_token($pdo, $scanTable, (int)$p['id']);
                $qrElId = 'qr_' . $type . '_' . $p['id'];
            ?>
            <div class="card-pair">
                <div class="pair-label">Front</div>
                <div class="id-card front">
                    <div class="watermark">🏫</div>
                    <div class="top-band">
                        <div class="logo-circle">
                            <?php if (!empty($settings['logo'])): ?>
                                <img src="<?php echo BASE_URL; ?>assets/uploads/branding/<?php echo e($settings['logo']); ?>">
                            <?php else: ?>🏫<?php endif; ?>
                        </div>
                        <div>
                            <div class="sname"><?php echo e(mb_strtoupper($brandName)); ?></div>
                            <?php if (!empty($settings['address'])): ?><div class="saddr"><?php echo e($settings['address']); ?></div><?php endif; ?>
                        </div>
                        <div class="role-ribbon"><?php echo $type === 'student' ? 'Student' : 'Staff'; ?></div>
                    </div>
                    <div class="body-row">
                        <div class="photo-frame">
                            <?php if ($picPath): ?><img src="<?php echo e($picPath); ?>"><?php else: ?>👤<?php endif; ?>
                        </div>
                        <div class="details">
                            <div class="pname"><?php echo e($p['full_name']); ?></div>
                            <?php if ($type === 'student'): ?>
                                <div class="row"><b>Class:</b> <?php echo e(trim(($p['class_name'] ?? '') . ' ' . ($p['section_name'] ?? ''))) ?: '—'; ?></div>
                                <div class="row"><b>Guardian:</b> <?php echo e($p['guardian_name'] ?: '—'); ?></div>
                                <div class="row"><b>DOB:</b> <?php echo $p['date_of_birth'] ? date('d-M-Y', strtotime($p['date_of_birth'])) : '—'; ?></div>
                                <?php if (!empty($p['cnic_bform'])): ?>
                                    <div class="row"><b>CNIC/B-Form:</b> <?php echo e($p['cnic_bform']); ?></div>
                                <?php endif; ?>
                            <?php else: ?>
                                <div class="row"><b>Designation:</b> Faculty Member</div>
                                <div class="row"><b>Phone:</b> <?php echo e($p['phone'] ?: '—'); ?></div>
                            <?php endif; ?>
                            <span class="id-pill"><?php echo $type === 'student' ? 'ROLL ' : 'ID '; ?><?php echo e($code); ?></span>
                        </div>
                    </div>
                    <div class="bottom-band">
                        <div class="barcode"><?php echo id_barcode_bars((string)$code . $p['id']); ?></div>
                        <div class="valid-year">Valid<br><?php echo e($settings['academic_year'] ?: date('Y') . '-' . (date('Y') + 1)); ?></div>
                        <div class="qr-box" id="<?php echo $qrElId; ?>" data-token="<?php echo e($scanToken); ?>"></div>
                    </div>
                </div>

                <div class="pair-label">Back</div>
                <div class="id-card back">
                    <h4>Terms &amp; Conditions</h4>
                    <ul class="terms">
                        <li>This card is the property of <?php echo e($brandName); ?> and must be carried at all times on campus.</li>
                        <li>Scan the QR code with the school's Attendance Scanner to mark attendance instantly.</li>
                        <li>If found, please return to the school office or call the number below.</li>
                        <li>Loss of card must be reported immediately to administration.</li>
                        <li>Card is non-transferable and valid only for the academic year shown.</li>
                    </ul>
                    <div class="contact">
                        📞 <?php echo e($settings['phone'] ?: '—'); ?> &nbsp;|&nbsp; ✉️ <?php echo e($settings['email'] ?: '—'); ?><br>
                        <span style="font-family:monospace;letter-spacing:1px;opacity:0.7;">Card Code: <?php echo e($scanToken); ?></span>
                    </div>
                    <div class="sign-row">
                        <div class="sign-block">
                            <?php if (!empty($settings['principal_signature'])): ?>
                                <img class="sign-img" src="<?php echo BASE_URL; ?>assets/uploads/branding/<?php echo e($settings['principal_signature']); ?>">
                            <?php else: ?>
                                <div style="height:26px;"></div>
                            <?php endif; ?>
                            <div class="sign-line"></div>
                            Principal
                        </div>
                        <div class="sign-block">
                            <div style="height:26px;"></div>
                            <div class="sign-line"></div>
                            Card Holder
                        </div>
                    </div>
                </div>
            </div>
        <?php endforeach; ?>
    </div>
    <script>
        document.querySelectorAll('.qr-box').forEach(function (box) {
            var token = box.getAttribute('data-token');
            if (token && window.QRCode) {
                new QRCode(box, { text: token, width: 130, height: 130, correctLevel: QRCode.CorrectLevel.L });
            }
        });
    </script>
    <?php endif; ?>
</div>
</body>
</html>
