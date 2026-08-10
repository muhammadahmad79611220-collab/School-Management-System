<?php
/**
 * includes/functions.php
 * Shared helper functions used across the whole app.
 */

/** Escape for safe HTML output. Use this every time you echo user data. */
function e(?string $value): string {
    return htmlspecialchars($value ?? '', ENT_QUOTES, 'UTF-8');
}

/** Generate (or reuse) a CSRF token for the current session. */
function csrf_token(): string {
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

/** Render a hidden CSRF input field. Include inside every <form>. */
function csrf_field(): string {
    return '<input type="hidden" name="csrf_token" value="' . e(csrf_token()) . '">';
}

/** Verify the CSRF token from a POST request. Call at the top of every state-changing action. */
function csrf_verify(): void {
    $token = $_POST['csrf_token'] ?? '';
    if (!$token || !hash_equals($_SESSION['csrf_token'] ?? '', $token)) {
        http_response_code(403);
        die('Security check failed (invalid or expired form token). Please go back and try again.');
    }
}

/** Set a one-time flash message shown on the next page load. */
function flash(string $type, string $message): void {
    $_SESSION['flash'][] = ['type' => $type, 'message' => $message];
}

/** Pop and render all pending flash messages as HTML. */
function flash_render(): string {
    if (empty($_SESSION['flash'])) return '';
    $html = '';
    foreach ($_SESSION['flash'] as $f) {
        $cls = $f['type'] === 'error' ? 'alert-error' : ($f['type'] === 'success' ? 'alert-success' : 'alert-info');
        $html .= '<div class="alert ' . $cls . '">' . e($f['message']) . '</div>';
    }
    unset($_SESSION['flash']);
    return $html;
}

/** Validate an integer ID coming from GET/POST. Returns null if invalid. */
function clean_id($value): ?int {
    if ($value === null || $value === '') return null;
    if (!ctype_digit((string)$value) && !is_int($value)) return null;
    $id = (int)$value;
    return $id > 0 ? $id : null;
}

/** Write an entry to the activity_log table. Never throws — logging failures shouldn't break the app. */
function log_activity(string $action, string $details = ''): void {
    try {
        $pdo = getDB();
        $stmt = $pdo->prepare(
            "INSERT INTO activity_log (user_id, action, details, ip_address) VALUES (?, ?, ?, ?)"
        );
        $stmt->execute([
            $_SESSION['user_id'] ?? null,
            $action,
            $details,
            $_SERVER['REMOTE_ADDR'] ?? ''
        ]);
    } catch (Throwable $e) {
        error_log('Activity log failed: ' . $e->getMessage());
    }
}

/** Redirect helper that always exits immediately afterward. */
function redirect(string $path): void {
    header("Location: $path");
    exit;
}

/** Handle a secure picture upload for students/teachers. Returns stored filename or null. */
function handle_picture_upload(string $field, string $destDir): ?string {
    if (empty($_FILES[$field]) || $_FILES[$field]['error'] === UPLOAD_ERR_NO_FILE) {
        return null;
    }
    $file = $_FILES[$field];
    if ($file['error'] !== UPLOAD_ERR_OK) {
        flash('error', 'File upload failed. Please try again.');
        return null;
    }
    $allowed = ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp'];
    $finfo = new finfo(FILEINFO_MIME_TYPE);
    $mime = $finfo->file($file['tmp_name']);
    if (!isset($allowed[$mime])) {
        flash('error', 'Only JPG, PNG, or WEBP images are allowed.');
        return null;
    }
    if ($file['size'] > 2 * 1024 * 1024) {
        flash('error', 'Image must be smaller than 2MB.');
        return null;
    }
    if (!is_dir($destDir)) {
        mkdir($destDir, 0755, true);
    }
    $filename = bin2hex(random_bytes(16)) . '.' . $allowed[$mime];
    if (!move_uploaded_file($file['tmp_name'], $destDir . '/' . $filename)) {
        flash('error', 'Could not save uploaded image.');
        return null;
    }
    return $filename;
}

/** Format currency for display. */
function money(float $amount): string {
    return 'Rs. ' . number_format($amount, 2);
}

/** Fetch institute settings (school name, logo, etc.), cached per-request. */
/**
 * Get (or lazily generate) a non-guessable scan token for a student/teacher,
 * used to encode a safe QR code on their ID card for the scanner attendance system.
 */
function ensure_scan_token(PDO $pdo, string $table, int $id): string {
    $stmt = $pdo->prepare("SELECT scan_token FROM `$table` WHERE id = ?");
    $stmt->execute([$id]);
    $token = $stmt->fetchColumn();
    if ($token) return $token;

    do {
        $token = strtoupper(bin2hex(random_bytes(10))); // 20-char token
        $check = $pdo->prepare("SELECT id FROM `$table` WHERE scan_token = ?");
        $check->execute([$token]);
    } while ($check->fetch());

    $pdo->prepare("UPDATE `$table` SET scan_token = ? WHERE id = ?")->execute([$token, $id]);
    return $token;
}

function get_settings(): array {
    static $settings = null;
    if ($settings === null) {
        $pdo = getDB();
        $settings = $pdo->query("SELECT * FROM settings WHERE id=1")->fetch() ?: [
            'school_name' => APP_NAME, 'tagline' => '', 'address' => '', 'phone' => '',
            'email' => '', 'logo' => '', 'principal_name' => '', 'principal_signature' => '',
            'academic_year' => '',
        ];
    }
    return $settings;
}
