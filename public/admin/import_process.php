<?php
require_once __DIR__ . '/../../core/includes/auth.php';
require_once __DIR__ . '/../../core/includes/functions.php';
require_login();

$admin = current_admin();
if ($admin['role'] === 'viewer') redirect('/admin/import.php');

if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !verify_csrf($_POST['csrf_token'] ?? '')) {
    redirect('/admin/import.php');
}

// ── Validate uploaded file ────────────────────────────────────
if (!isset($_FILES['csv_file']) || $_FILES['csv_file']['error'] !== UPLOAD_ERR_OK) {
    flash_set('error', 'No file uploaded or upload error.');
    redirect('/admin/import.php');
}

$file = $_FILES['csv_file']['tmp_name'];
$finfo = new finfo(FILEINFO_MIME_TYPE);
$mime  = $finfo->file($file);
// Accept text/plain too — some OS label CSV as plain text
if (!in_array($mime, ['text/csv','text/plain','application/csv','application/octet-stream'])) {
    flash_set('error', 'File must be a CSV.');
    redirect('/admin/import.php');
}

// ── Parse CSV ─────────────────────────────────────────────────
$handle = fopen($file, 'r');
$headers = array_map('trim', fgetcsv($handle)); // first row = headers
$headers = array_map('strtolower', $headers);

$required = ['full_name','phone','dob','gender','height_cm'];
foreach ($required as $r) {
    if (!in_array($r, $headers)) {
        fclose($handle);
        flash_set('error', 'Missing required column: ' . $r);
        redirect('/admin/import.php');
    }
}

$db = db();
$inserted = 0;
$skipped  = 0;
$errors   = [];
$row_num  = 1;
$max_rows = 500;

while (($row = fgetcsv($handle)) !== false) {
    $row_num++;
    if ($row_num > $max_rows + 1) {
        $errors[] = 'Row limit (500) reached. Remaining rows ignored.';
        break;
    }

    // Map columns by header name
    $data = [];
    foreach ($headers as $i => $h) {
        $data[$h] = trim($row[$i] ?? '');
    }

    // ── Sanitise & validate each row ─────────────────────────
    $full_name = $data['full_name'] ?? '';
    $phone     = sanitize_phone($data['phone'] ?? '');
    $dob       = $data['dob'] ?? '';
    $gender    = strtolower($data['gender'] ?? '');
    $height_cm = (int)($data['height_cm'] ?? 0);

    if (strlen($full_name) < 3)                         { $skipped++; $errors[] = "Row $row_num: Name too short."; continue; }
    if (!is_valid_phone($phone))                         { $skipped++; $errors[] = "Row $row_num: Invalid phone ($phone)."; continue; }
    if (empty($dob) || !strtotime($dob))                { $skipped++; $errors[] = "Row $row_num: Invalid DOB."; continue; }
    if (!in_array($gender, ['male','female','other']))   { $skipped++; $errors[] = "Row $row_num: Invalid gender."; continue; }
    if ($height_cm < 100 || $height_cm > 250)           { $skipped++; $errors[] = "Row $row_num: Invalid height."; continue; }

    // Duplicate phone check
    $dup = $db->prepare("SELECT id FROM registrations WHERE phone = ?");
    $dup->bind_param('s', $phone);
    $dup->execute();
    $dup->store_result();
    $is_dup = $dup->num_rows > 0;
    $dup->close();
    if ($is_dup) { $skipped++; $errors[] = "Row $row_num: Phone $phone already exists."; continue; }

    // Optional fields
    $email       = $data['email'] ?? '';
    $weight_kg   = !empty($data['weight_kg'])  ? (int)$data['weight_kg'] : null;
    $skin_tone   = in_array($data['skin_tone'] ?? '', ['fair','wheatish','dusky','dark']) ? $data['skin_tone'] : null;
    $district    = !empty($data['district'])   ? $data['district'] : 'N/A';
    $address     = $data['address']     ?? '';
    $experience  = in_array($data['experience'] ?? '', ['none','some','professional']) ? $data['experience'] : 'none';
    $exp_details = $data['exp_details'] ?? '';
    $fb_profile  = $data['fb_profile']  ?? '';
    $instagram   = $data['instagram']   ?? '';
    $how_heard   = in_array($data['how_heard'] ?? '', ['facebook','instagram','friend','poster','other']) ? $data['how_heard'] : 'other';
    $status      = in_array($data['status'] ?? '', all_statuses()) ? $data['status'] : 'pending';
    $priority    = in_array($data['priority'] ?? '', ['normal','hot','warm','cold']) ? $data['priority'] : 'normal';
    $age         = calculate_age($dob);
    $reg_code    = generate_reg_code();

    // ── Insert ────────────────────────────────────────────────
    $stmt = $db->prepare("
        INSERT INTO registrations
            (reg_code, full_name, phone, email, dob, age, gender,
             height_cm, weight_kg, skin_tone, district, address,
             experience, exp_details, fb_profile, instagram,
             how_heard, status, priority, reviewed_by, reviewed_at, entry_source)
        VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,NOW(),'csv_import')
    ");
    $stmt->bind_param(
        'sssssiisiisssssssssi',
        $reg_code, $full_name, $phone, $email, $dob, $age, $gender,
        $height_cm, $weight_kg, $skin_tone, $district, $address,
        $experience, $exp_details, $fb_profile, $instagram,
        $how_heard, $status, $priority, $admin['id']
    );

    if ($stmt->execute()) {
        $new_id = $stmt->insert_id;
        $stmt->close();
        $inserted++;
        // Log activity
        $act = $db->prepare("INSERT INTO lead_activities (reg_id, admin_id, type, content) VALUES (?,?,'note',?)");
        $note = 'Imported via CSV by ' . $admin['full_name'] . '.';
        $act->bind_param('iis', $new_id, $admin['id'], $note);
        $act->execute();
        $act->close();
    } else {
        $errors[] = "Row $row_num: DB error — " . $stmt->error;
        $stmt->close();
        $skipped++;
    }
}
fclose($handle);

// ── Store results in session and redirect to results page ─────
$_SESSION['import_result'] = [
    'inserted' => $inserted,
    'skipped'  => $skipped,
    'errors'   => $errors,
];
redirect('/admin/import_result.php');