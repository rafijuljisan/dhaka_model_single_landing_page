<?php
// ============================================================
// admin/bulk_action.php  — Bulk Status Update (JSON endpoint)
// ============================================================

require_once __DIR__ . '/../../core/includes/auth.php';
require_once __DIR__ . '/../../core/includes/functions.php';

header('Content-Type: application/json');

require_login();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'error' => 'Method not allowed.']);
    exit;
}

if (current_admin()['role'] === 'viewer') {
    http_response_code(403);
    echo json_encode(['success' => false, 'error' => 'Permission denied.']);
    exit;
}

$input = json_decode(file_get_contents('php://input'), true);

$csrf       = $input['csrf_token'] ?? '';
$ids        = $input['ids']        ?? [];
$new_status = $input['status']     ?? '';
$note       = clean($input['note'] ?? '');

if (!verify_csrf($csrf)) {
    http_response_code(403);
    echo json_encode(['success' => false, 'error' => 'Invalid CSRF token.']);
    exit;
}

if (!in_array($new_status, all_statuses(), true)) {
    echo json_encode(['success' => false, 'error' => 'Invalid status.']);
    exit;
}

if (empty($ids) || !is_array($ids)) {
    echo json_encode(['success' => false, 'error' => 'No registrations selected.']);
    exit;
}

// Sanitize IDs — integers only
$clean_ids = array_filter(array_map('intval', $ids), fn($v) => $v > 0);

if (empty($clean_ids)) {
    echo json_encode(['success' => false, 'error' => 'No valid IDs provided.']);
    exit;
}

$db        = db();
$admin_id  = current_admin()['id'];
$id_list   = implode(',', $clean_ids);

// Fetch current statuses for logging
$current = $db->query("SELECT id, status FROM registrations WHERE id IN ($id_list)")
               ->fetch_all(MYSQLI_ASSOC);

// Bulk update
$db->query("
    UPDATE registrations
    SET status = '$new_status', reviewed_by = $admin_id, reviewed_at = NOW()
    WHERE id IN ($id_list)
");

$affected = $db->affected_rows;

// Insert audit logs
$log_stmt = $db->prepare("
    INSERT INTO status_logs (reg_id, changed_by, old_status, new_status, note)
    VALUES (?, ?, ?, ?, ?)
");

foreach ($current as $row) {
    $log_stmt->bind_param('iisss', $row['id'], $admin_id, $row['status'], $new_status, $note);
    $log_stmt->execute();
}
$log_stmt->close();

echo json_encode([
    'success'  => true,
    'affected' => $affected,
    'message'  => "{$affected} registration(s) updated to " . ucfirst($new_status) . '.',
]);
exit;
