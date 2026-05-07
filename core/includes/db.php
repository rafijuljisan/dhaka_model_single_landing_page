<?php
// ============================================================
// includes/db.php  — Database Connection (MySQLi)
// ============================================================

define('DB_HOST', 'localhost');
define('DB_USER', 'root');       // ← change
define('DB_PASS', '');   // ← change
define('DB_NAME', 'landing');       // ← change
define('DB_CHARSET', 'utf8mb4');

function db_connect(): mysqli {
    static $conn = null;
    if ($conn !== null) return $conn;

    $conn = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);

    if ($conn->connect_error) {
        error_log('DB Connection failed: ' . $conn->connect_error);
        http_response_code(500);
        die(json_encode(['error' => 'Database connection failed. Please try again later.']));
    }

    $conn->set_charset(DB_CHARSET);
    return $conn;
}

// Shortcut alias
function db(): mysqli {
    return db_connect();
}
