<?php
require_once __DIR__ . '/../../config/app.php';
require_login();
if (!is_admin() && !can('view', 'reports')) {
    require_role('admin');
}

$pdo = getDB();
$students = $pdo->query(
    "SELECT s.roll_no, s.full_name, s.gender, s.date_of_birth, c.class_name, sec.section_name,
            s.guardian_name, s.guardian_phone, s.status
     FROM students s
     LEFT JOIN classes c ON s.class_id = c.id
     LEFT JOIN sections sec ON s.section_id = sec.id
     ORDER BY c.sort_order, s.full_name"
)->fetchAll();

header('Content-Type: text/csv; charset=utf-8');
header('Content-Disposition: attachment; filename="students_export_' . date('Y-m-d') . '.csv"');

$out = fopen('php://output', 'w');
fputcsv($out, ['Roll No', 'Full Name', 'Gender', 'Date of Birth', 'Class', 'Section', 'Guardian Name', 'Guardian Phone', 'Status']);
foreach ($students as $s) {
    fputcsv($out, [
        $s['roll_no'], $s['full_name'], $s['gender'], $s['date_of_birth'],
        $s['class_name'], $s['section_name'], $s['guardian_name'], $s['guardian_phone'], $s['status']
    ]);
}
fclose($out);
exit;
