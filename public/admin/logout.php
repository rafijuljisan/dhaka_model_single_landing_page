<?php
// ============================================================
// admin/logout.php
// ============================================================

require_once __DIR__ . '/../../core/includes/auth.php';

if (session_status() === PHP_SESSION_NONE) session_start();

// Clear all session data
$_SESSION = [];

// Expire the session cookie
if (ini_get('session.use_cookies')) {
    $params = session_get_cookie_params();
    setcookie(
        session_name(), '', time() - 42000,
        $params['path'], $params['domain'],
        $params['secure'], $params['httponly']
    );
}

session_destroy();

// ✅ Correct path — login page is index.php in their project
header('Location: /admin/index.php?msg=logged_out');
exit;