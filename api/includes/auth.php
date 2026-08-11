<?php
/**
 * includes/auth.php
 * Authentication & authorization guards.
 * Call require_login() / require_role() at the very top of every protected page,
 * BEFORE any HTML output.
 */

/** Is anyone logged in? */
function is_logged_in(): bool {
    return isset($_SESSION['user_id']);
}

/** Current user's role, or null if not logged in. */
function current_role(): ?string {
    return $_SESSION['role'] ?? null;
}

/** Block access entirely unless logged in. Redirects to login page. */
function require_login(): void {
    if (!is_logged_in()) {
        redirect('index.php');
    }
}

/**
 * Block access unless the current user has one of the given roles.
 * Usage: require_role('admin');  or  require_role(['admin','teacher']);
 */
function require_role($roles): void {
    require_login();
    $roles = is_array($roles) ? $roles : [$roles];
    if (!in_array(current_role(), $roles, true)) {
        http_response_code(403);
        die('<h2>403 Forbidden</h2><p>You do not have permission to view this page.</p><a href="' . (defined('BASE_URL') ? BASE_URL : '') . 'dashboard.php">Back to dashboard</a>');
    }
}

/**
 * Permission-aware gate for module pages: admins always pass; other roles must
 * have the given action permission (view/add/edit/delete) on the given module,
 * granted via Roles & Permissions. Otherwise this 403s just like require_role('admin').
 * Usage: require_perm('view', 'fees');  require_perm('delete', 'students');
 */
function require_perm(string $action, string $moduleKey): void {
    require_login();
    if (is_admin()) return;
    if (can($action, $moduleKey)) return;
    http_response_code(403);
    die('<h2>403 Forbidden</h2><p>You do not have permission to view this page.</p><a href="' . (defined('BASE_URL') ? BASE_URL : '') . 'dashboard.php">Back to dashboard</a>');
}

/** Is the current user an admin? Admins always have full access, bypassing permission checks. */
function is_admin(): bool {
    return current_role() === 'admin';
}

/**
 * Check whether the current user's role has a given permission on a module.
 * Admins always pass. Teachers keep their existing hand-coded scoping logic
 * elsewhere (this is mainly for the newer roles: accountant, librarian, receptionist).
 * Usage: can('view', 'fees')  /  can('delete', 'students')
 */
function can(string $action, string $moduleKey): bool {
    if (is_admin()) return true;
    if (!is_logged_in()) return false;

    static $cache = [];
    $role = current_role();
    $cacheKey = $role . ':' . $moduleKey;

    if (!isset($cache[$cacheKey])) {
        $pdo = getDB();
        $stmt = $pdo->prepare(
            "SELECT rp.can_view, rp.can_add, rp.can_edit, rp.can_delete
             FROM role_permissions rp
             JOIN roles r ON rp.role_id = r.id
             WHERE r.role_key = ? AND rp.module_key = ?"
        );
        $stmt->execute([$role, $moduleKey]);
        $cache[$cacheKey] = $stmt->fetch() ?: ['can_view'=>0,'can_add'=>0,'can_edit'=>0,'can_delete'=>0];
    }

    $row = $cache[$cacheKey];
    return match ($action) {
        'view'   => (bool)$row['can_view'],
        'add'    => (bool)$row['can_add'],
        'edit'   => (bool)$row['can_edit'],
        'delete' => (bool)$row['can_delete'],
        default  => false,
    };
}

/** Block access unless the current user can perform $action on $moduleKey. */
function require_permission(string $action, string $moduleKey): void {
    require_login();
    if (!can($action, $moduleKey)) {
        http_response_code(403);
        die('<h2>403 Forbidden</h2><p>You do not have permission to do this.</p><a href="' . (defined('BASE_URL') ? BASE_URL : '') . 'dashboard.php">Back to dashboard</a>');
    }
}

/** Get the teachers.id linked to the currently logged-in teacher user, or null. */
function current_teacher_id(): ?int {
    return $_SESSION['teacher_id'] ?? null;
}

/** Get the students.id linked to the currently logged-in student user, or null. */
function current_student_id(): ?int {
    return $_SESSION['student_id'] ?? null;
}

/** Is the given role key the built-in 'student' role? Used by the sidebar/pages to gate student-only views. */
function is_student_role(?string $role): bool {
    return $role === 'student';
}

/** Is the given role key the built-in 'teacher' role? Used by the sidebar to show teacher-specific links. */
function is_teacher_role(?string $role): bool {
    return $role === 'teacher';
}

/**
 * Attempt to log a user in. Handles brute-force lockout.
 * Returns true on success; on failure sets a flash message and returns false.
 */
function attempt_login(string $username, string $password): bool {
    $pdo = getDB();
    $stmt = $pdo->prepare("SELECT * FROM users WHERE username = ? LIMIT 1");
    $stmt->execute([$username]);
    $user = $stmt->fetch();

    if (!$user) {
        // Don't reveal whether the username exists.
        usleep(300000); // slight delay to reduce username-enumeration timing signal
        flash('error', 'Invalid username or password.');
        return false;
    }

    // Check lockout
    if (!empty($user['locked_until']) && strtotime($user['locked_until']) > time()) {
        $mins = ceil((strtotime($user['locked_until']) - time()) / 60);
        flash('error', "Account locked due to too many failed attempts. Try again in {$mins} minute(s).");
        return false;
    }

    if (!$user['is_active']) {
        flash('error', 'This account has been disabled. Contact the administrator.');
        return false;
    }

    if (!password_verify($password, $user['password'])) {
        $attempts = $user['failed_attempts'] + 1;
        $lockUntil = null;
        if ($attempts >= MAX_LOGIN_ATTEMPTS) {
            $lockUntil = date('Y-m-d H:i:s', time() + LOCKOUT_MINUTES * 60);
        }
        $upd = $pdo->prepare("UPDATE users SET failed_attempts = ?, locked_until = ? WHERE id = ?");
        $upd->execute([$attempts, $lockUntil, $user['id']]);

        log_activity('login_failed', "username={$username}");
        flash('error', 'Invalid username or password.');
        return false;
    }

    // Success — reset attempts, regenerate session ID (prevents session fixation)
    session_regenerate_id(true);
    $pdo->prepare("UPDATE users SET failed_attempts = 0, locked_until = NULL, last_login = NOW() WHERE id = ?")
        ->execute([$user['id']]);

    $_SESSION['user_id']    = $user['id'];
    $_SESSION['username']   = $user['username'];
    $_SESSION['role']       = $user['role'];
    $_SESSION['full_name']  = $user['full_name'];
    $_SESSION['teacher_id'] = $user['teacher_id'];
    $_SESSION['student_id'] = $user['student_id'] ?? null;
    $_SESSION['must_change_password'] = (bool)$user['must_change_password'];

    log_activity('login_success');
    return true;
}
