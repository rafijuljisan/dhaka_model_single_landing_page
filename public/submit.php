<?php
// ============================================================
// submit.php  — Public Registration Form Handler
// ============================================================

require_once __DIR__ . '/../core/includes/functions.php';

// Only accept POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    redirect('/index.php');
}

// CSRF check
$csrf = $_POST['csrf_token'] ?? '';
if (!verify_csrf($csrf)) {
    http_response_code(403);
    redirect('/index.php?error=invalid_token');
}

// Check campaign is active
if (get_setting('campaign_active', '1') !== '1') {
    redirect('/index.php?error=campaign_closed');
}

// Check registration cap
$db    = db();
$count = $db->query("SELECT COUNT(*) AS c FROM registrations")->fetch_assoc()['c'];
$max   = (int) get_setting('max_registrations', '500');
if ($count >= $max) {
    redirect('/index.php?error=quota_full');
}

// Validate + sanitize
$result = validate_registration($_POST);

if (!empty($result['errors'])) {
    // Store errors in session and redirect back
    if (session_status() === PHP_SESSION_NONE) session_start();
    $_SESSION['form_errors'] = $result['errors'];
    $_SESSION['form_old']    = $_POST;  // repopulate fields
    redirect('/index.php?error=validation');
}

// Save to DB
$save = save_registration($result['data']);

if (!$save['success']) {
    if (session_status() === PHP_SESSION_NONE) session_start();
    $_SESSION['form_errors'] = [$save['error']];
    $_SESSION['form_old']    = $_POST;
    redirect('/index.php?error=save_failed');
}

// Send email (non-blocking — failure is acceptable)
if (!empty($result['data']['email'])) {
    send_notification_email($save['reg_code'], $result['data']['full_name'], $result['data']['email']);
}

// ============================================================
// COMBINED CAPI — Meta + TikTok via Cloudflare Worker
// ============================================================

$event_id = uniqid('lead_');

$applicant_email = $result['data']['email'] ?? '';
$applicant_phone = $result['data']['phone'] ?? '';

$hashed_email = !empty($applicant_email)
    ? hash('sha256', strtolower(trim($applicant_email)))
    : null;

$clean_phone  = preg_replace('/[^0-9]/', '', $applicant_phone);
$hashed_phone = !empty($clean_phone)
    ? hash('sha256', ltrim($clean_phone, '0'))
    : null;

$worker_payload = [
    'event_name' => 'Lead',
    'event_id'   => $event_id,
    'test_event_code' => 'TEST28213',
    'page_url'   => 'https://' . ($_SERVER['HTTP_HOST'] ?? '') . '/thank-you.php',
    'user_data'  => array_filter([
        'em'         => $hashed_email,
        'ph'         => $hashed_phone,
        'fbp'        => $_COOKIE['_fbp']           ?? null,
        'fbc'        => $_COOKIE['_fbc']            ?? null,
        'ttclid'     => $_COOKIE['ttclid']          ?? null, // TikTok click ID
        'ip'         => $_SERVER['REMOTE_ADDR']     ?? null,
        'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? null,
    ]),
    'custom_data' => [
        'currency'         => 'BDT',
        'value'            => 0,
        'content_name'     => 'DMA Grooming Registration',
        'content_category' => 'ModelAgency',
    ],
];

$ch = curl_init('https://dma-capi-proxy.admin-dhakamodelagency.workers.dev');
curl_setopt($ch, CURLOPT_CUSTOMREQUEST,  'POST');
curl_setopt($ch, CURLOPT_POSTFIELDS,     json_encode($worker_payload));
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_HTTPHEADER,    ['Content-Type: application/json']);
curl_setopt($ch, CURLOPT_TIMEOUT_MS,     2000);
$worker_response = curl_exec($ch);
$curl_error      = curl_error($ch);        // ← add
$http_code       = curl_getinfo($ch, CURLINFO_HTTP_CODE); // ← add
curl_close($ch);

// Log silently — never blocks the redirect
if ($curl_error) {
    error_log('[CAPI Worker] cURL error: ' . $curl_error);
} elseif ($http_code !== 200) {
    error_log('[CAPI Worker] Non-200 response: ' . $http_code . ' | ' . $worker_response);
}


// ============================================================
// FINALIZE & REDIRECT
// ============================================================

// Clear any old session data
if (session_status() === PHP_SESSION_NONE) session_start();
unset($_SESSION['form_errors'], $_SESSION['form_old']);

// Pass data to thank-you page via session (never in URL)
$_SESSION['reg_code']    = $save['reg_code'];
$_SESSION['applicant']   = $result['data']['full_name'];
$_SESSION['fb_event_id'] = $event_id; // Pass Event ID to the browser pixel for deduplication
$_SESSION['tt_event_id'] = $event_id; // TikTok deduplication — same event_id

redirect('/thank-you.php');