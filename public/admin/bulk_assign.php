<?php
// ============================================================
// admin/bulk_assign.php  — Bulk Assignment (JSON endpoint)
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

$input     = json_decode(file_get_contents('php://input'), true);
$csrf      = $input['csrf_token'] ?? '';
$ids       = $input['ids']       ?? [];
$assign_to = $input['assign_to'] ?? null; // null = unassign, int = admin user id

if (!verify_csrf($csrf)) {
    http_response_code(403);
    echo json_encode(['success' => false, 'error' => 'Invalid CSRF token.']);
    exit;
}

if (empty($ids) || !is_array($ids)) {
    echo json_encode(['success' => false, 'error' => 'No registrations selected.']);
    exit;
}

$db        = db();
$admin_id  = (int) current_admin()['id'];
$clean_ids = array_filter(array_map('intval', $ids), fn($v) => $v > 0);

if (empty($clean_ids)) {
    echo json_encode(['success' => false, 'error' => 'No valid IDs.']);
    exit;
}

// Validate assign_to is a real active admin if provided
if ($assign_to !== null) {
    $assign_to = (int)$assign_to;
    $check = $db->prepare("SELECT id FROM admin_users WHERE id = ? AND is_active = 1");
    $check->bind_param('i', $assign_to);
    $check->execute();
    if (!$check->get_result()->fetch_assoc()) {
        echo json_encode(['success' => false, 'error' => 'Invalid admin user.']);
        exit;
    }
    $check->close();
}

$id_list   = implode(',', $clean_ids);
$set_value = $assign_to === null ? 'NULL' : (int)$assign_to;

$db->query("UPDATE registrations SET assigned_to = $set_value WHERE id IN ($id_list)");
$affected = $db->affected_rows;

// Log each assignment as an activity
$assign_label = 'Unassigned';
if ($assign_to) {
    $row = $db->query("SELECT full_name FROM admin_users WHERE id = $assign_to")->fetch_assoc();
    if ($row) $assign_label = $row['full_name'];
}
$note = "Bulk assigned to: $assign_label";

$act = $db->prepare("
    INSERT INTO lead_activities (reg_id, admin_id, type, content)
    VALUES (?, ?, 'note', ?)
");
foreach ($clean_ids as $reg_id) {
    $act->bind_param('iis', $reg_id, $admin_id, $note);
    $act->execute();
}
$act->close();

echo json_encode([
    'success'  => true,
    'affected' => $affected,
    'message'  => "$affected lead(s) assigned to $assign_label.",
]);
exit;