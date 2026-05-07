<?php
// ============================================================
// admin/export.php  — CSV Export (respects active filters)
// ============================================================

require_once __DIR__ . '/../../core/includes/auth.php';
require_once __DIR__ . '/../../core/includes/functions.php';

require_login();

$db = db();

// ── Reuse same filter logic as dashboard ─────────────────────
$allowed_status = ['', 'pending', 'reviewed', 'approved', 'rejected', 'waitlist'];
$allowed_gender = ['', 'male', 'female', 'other'];

$filter_status = in_array($_GET['status'] ?? '', $allowed_status) ? ($_GET['status'] ?? '') : '';
$filter_gender = in_array($_GET['gender'] ?? '', $allowed_gender) ? ($_GET['gender'] ?? '') : '';
$filter_search = clean($_GET['search'] ?? '');
$filter_date   = clean($_GET['date'] ?? '');

$where  = [];
$params = [];
$types  = '';

if ($filter_status !== '') { $where[] = 'status = ?'; $params[] = $filter_status; $types .= 's'; }
if ($filter_gender !== '') { $where[] = 'gender = ?'; $params[] = $filter_gender; $types .= 's'; }
if ($filter_search !== '') {
    $where[]  = '(full_name LIKE ? OR phone LIKE ? OR reg_code LIKE ? OR email LIKE ?)';
    $like     = '%' . $filter_search . '%';
    $params   = array_merge($params, [$like,$like,$like,$like]);
    $types   .= 'ssss';
}
if ($filter_date !== '') { $where[] = 'DATE(created_at) = ?'; $params[] = $filter_date; $types .= 's'; }

$where_sql = $where ? 'WHERE ' . implode(' AND ', $where) : '';

$sql  = "SELECT r.reg_code, r.full_name, r.phone, r.email, r.dob, r.age, r.gender,
                r.height_cm, r.weight_kg, r.skin_tone, r.district, r.address,
                r.experience, r.fb_profile, r.instagram, r.how_heard,
                r.status, r.admin_note, a.full_name AS reviewed_by,
                r.reviewed_at, r.created_at
         FROM   registrations r
         LEFT JOIN admin_users a ON r.reviewed_by = a.id
         $where_sql
         ORDER  BY r.created_at DESC";

$stmt = $db->prepare($sql);
if ($params) $stmt->bind_param($types, ...$params);
$stmt->execute();
$result = $stmt->get_result();
$stmt->close();

// ── Stream CSV headers ────────────────────────────────────────
$filename = 'dma_registrations_' . date('Ymd_His') . '.csv';
header('Content-Type: text/csv; charset=UTF-8');
header('Content-Disposition: attachment; filename="' . $filename . '"');
header('Cache-Control: no-cache, no-store, must-revalidate');
header('Pragma: no-cache');

// UTF-8 BOM — ensures Excel opens correctly
echo "\xEF\xBB\xBF";

$out = fopen('php://output', 'w');

// Header row
fputcsv($out, [
    'Reg Code', 'Full Name', 'Phone', 'Email', 'Date of Birth', 'Age',
    'Gender', 'Height (cm)', 'Weight (kg)', 'Skin Tone',
    'District', 'Address', 'Experience', 'Facebook Profile',
    'Instagram', 'How Heard', 'Status', 'Admin Note',
    'Reviewed By', 'Reviewed At', 'Registered At'
]);

// Data rows
while ($row = $result->fetch_assoc()) {
    fputcsv($out, [
        $row['reg_code'],
        $row['full_name'],
        $row['phone'],
        $row['email'] ?? '',
        $row['dob'],
        $row['age'],
        $row['gender'],
        $row['height_cm'],
        $row['weight_kg'] ?? '',
        $row['skin_tone'] ?? '',
        $row['district'],
        $row['address'] ?? '',
        $row['experience'],
        $row['fb_profile'] ?? '',
        $row['instagram'] ?? '',
        $row['how_heard'],
        $row['status'],
        $row['admin_note'] ?? '',
        $row['reviewed_by'] ?? '',
        $row['reviewed_at'] ?? '',
        $row['created_at'],
    ]);
}

fclose($out);
exit;
