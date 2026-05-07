<?php
// ============================================================
// includes/auth.php  — Session Auth Guard
// ============================================================

if (session_status() === PHP_SESSION_NONE) {
    session_set_cookie_params([
        'lifetime' => 0,
        'path'     => '/',
        'secure'   => true,          // set false if not on HTTPS locally
        'httponly' => true,
        'samesite' => 'Strict',
    ]);
    session_start();
}

function is_logged_in(): bool {
    return !empty($_SESSION['admin_id'])
        && !empty($_SESSION['admin_role'])
        && !empty($_SESSION['admin_last_activity'])
        && (time() - $_SESSION['admin_last_activity']) < 3600; // 1hr timeout
}

function require_login(): void {
    if (!is_logged_in()) {
        $_SESSION = [];
        session_destroy();
        header('Location: /admin/index.php?msg=session_expired');
        exit;
    }
    // Refresh activity timestamp
    $_SESSION['admin_last_activity'] = time();
}

function require_role(string $role): void {
    require_login();
    $hierarchy = ['viewer' => 1, 'admin' => 2, 'superadmin' => 3];
    $user_level = $hierarchy[$_SESSION['admin_role']] ?? 0;
    $need_level = $hierarchy[$role] ?? 99;

    if ($user_level < $need_level) {
        http_response_code(403);
        die('<h2>403 — Access Denied.</h2>');
    }
}

function login_admin(array $admin): void {
    session_regenerate_id(true);
    $_SESSION['admin_id']            = $admin['id'];
    $_SESSION['admin_username']      = $admin['username'];
    $_SESSION['admin_full_name']     = $admin['full_name'];
    $_SESSION['admin_role']          = $admin['role'];
    $_SESSION['admin_last_activity'] = time();
}

function current_admin(): array {
    return [
        'id'        => $_SESSION['admin_id']        ?? 0,
        'username'  => $_SESSION['admin_username']  ?? '',
        'full_name' => $_SESSION['admin_full_name'] ?? '',
        'role'      => $_SESSION['admin_role']      ?? 'viewer',
    ];
}

function attempt_login(string $identifier, string $password): array {
    require_once __DIR__ . '/db.php';
    $db   = db();
    
    // Group the OR condition with parentheses so is_active = 1 applies to BOTH
    $sql = "SELECT * FROM admin_users WHERE (username = ? OR email = ?) AND is_active = 1 LIMIT 1";
    $stmt = $db->prepare($sql);
    
    // Bind the $identifier twice (once for username, once for email)
    $stmt->bind_param('ss', $identifier, $identifier);
    $stmt->execute();
    $admin = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    // Verify user exists and password is correct
    if (!$admin || !password_verify($password, $admin['password'])) {
        return ['success' => false, 'error' => 'Invalid username/email or password.'];
    }

    // Update last login timestamp
    $stmt = $db->prepare("UPDATE admin_users SET last_login = NOW() WHERE id = ?");
    $stmt->bind_param('i', $admin['id']);
    $stmt->execute();
    $stmt->close();

    login_admin($admin);
    return ['success' => true];
}
