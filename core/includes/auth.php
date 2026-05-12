<?php
// ============================================================
// core/includes/auth.php  — Session Auth Guard
// ============================================================

if (session_status() === PHP_SESSION_NONE) {
    ini_set('session.gc_maxlifetime', 86400);   // ← add this
    session_set_cookie_params([
        'lifetime' => 86400,                    // ← change from 0 to match
        'path'     => '/',
        'secure'   => true,
        'httponly' => true,
        'samesite' => 'Strict',
    ]);
    session_start();                            // must stay last
}

// ── Is the current session a valid admin session? ─────────────
function is_logged_in(): bool {
    return !empty($_SESSION['admin_id'])
        && !empty($_SESSION['admin_role'])
        && !empty($_SESSION['admin_last_activity'])
        && (time() - $_SESSION['admin_last_activity']) < 28800; // 8hr timeout
}

// ── Redirect to login if not authenticated ────────────────────
function require_login(): void {
    if (!is_logged_in()) {
        $_SESSION = [];
        session_destroy();
        // ✅ Their login page is /admin/index.php
        header('Location: /admin/index.php?msg=session_expired');
        exit;
    }
    // Refresh activity timestamp on every request
    $_SESSION['admin_last_activity'] = time();
}

// ── Require a minimum role level ──────────────────────────────
function require_role(string $role): void {
    require_login();
    $hierarchy  = ['viewer' => 1, 'admin' => 2, 'superadmin' => 3];
    $user_level = $hierarchy[$_SESSION['admin_role']] ?? 0;
    $need_level = $hierarchy[$role] ?? 99;

    if ($user_level < $need_level) {
        http_response_code(403);
        die('<div style="font-family:sans-serif;padding:40px;text-align:center;">
             <h2>403 — Access Denied</h2>
             <p>You need <strong>' . htmlspecialchars($role) . '</strong> access for this page.</p>
             <a href="/admin/dashboard.php">← Back to Dashboard</a>
             </div>');
    }
}

// ── Store admin into session after successful login ───────────
function login_admin(array $admin): void {
    session_regenerate_id(true);
    $_SESSION['admin_id']            = $admin['id'];
    $_SESSION['admin_username']      = $admin['username'];
    $_SESSION['admin_full_name']     = $admin['full_name'];
    $_SESSION['admin_role']          = $admin['role'];
    $_SESSION['admin_last_activity'] = time();
}

// ── Return current admin data from SESSION ────────────────────
// ✅ Reads from session (fast, no DB hit per request)
// Shape matches what all admin pages expect: id, username, full_name, role
function current_admin(): array {
    return [
        'id'        => (int)($_SESSION['admin_id']        ?? 0),
        'username'  => $_SESSION['admin_username']         ?? '',
        'full_name' => $_SESSION['admin_full_name']        ?? '',
        'role'      => $_SESSION['admin_role']             ?? 'viewer',
    ];
}

// ── Logout helper ─────────────────────────────────────────────
// ✅ Added — called by the new admin pages via logout.php
function logout(): void {
    $_SESSION = [];
    if (ini_get('session.use_cookies')) {
        $p = session_get_cookie_params();
        setcookie(session_name(), '', time() - 42000,
            $p['path'], $p['domain'], $p['secure'], $p['httponly']);
    }
    session_destroy();
}

// ── Attempt login (username OR email) ────────────────────────
function attempt_login(string $identifier, string $password): array {
    require_once __DIR__ . '/db.php';
    $db = db();

    $sql  = "SELECT * FROM admin_users
             WHERE (username = ? OR email = ?) AND is_active = 1
             LIMIT 1";
    $stmt = $db->prepare($sql);
    $stmt->bind_param('ss', $identifier, $identifier);
    $stmt->execute();
    $admin = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    if (!$admin || !password_verify($password, $admin['password'])) {
        return ['success' => false, 'error' => 'Invalid username/email or password.'];
    }

    // Update last login timestamp
    $upd = $db->prepare("UPDATE admin_users SET last_login = NOW() WHERE id = ?");
    $upd->bind_param('i', $admin['id']);
    $upd->execute();
    $upd->close();

    login_admin($admin);
    return ['success' => true];
}