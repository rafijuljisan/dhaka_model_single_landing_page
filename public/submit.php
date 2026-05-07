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
// META CONVERSIONS API (SERVER-SIDE TRACKING)
// ============================================================

// 1. Generate unique Event ID for deduplication
$event_id = uniqid('lead_');

// 2. Safely extract and hash user data
$applicant_email = $result['data']['email'] ?? '';
$applicant_phone = $result['data']['phone'] ?? ''; 

// Meta requires strictly formatted SHA256 hashes
$hashed_email = !empty($applicant_email) ? hash('sha256', strtolower(trim($applicant_email))) : null;
// Clean phone number (remove +, -, spaces, leading zeros) before hashing
$clean_phone  = preg_replace('/[^0-9]/', '', $applicant_phone);
$hashed_phone = !empty($clean_phone) ? hash('sha256', ltrim($clean_phone, '0')) : null;

$worker_payload = [
    'event_name' => 'Lead',
    'event_id'   => $event_id,
    'user_data'  => array_filter([ // array_filter removes null values
        'em'  => $hashed_email,
        'ph'  => $hashed_phone,
        'fbp' => $_COOKIE['_fbp'] ?? null, // Browser pixel cookie
        'fbc' => $_COOKIE['_fbc'] ?? null, // Click ID cookie (highly valuable if they clicked an ad)
    ]),
    'custom_data' => [
        'content_category' => 'ModelAgency',
        'content_name'     => 'DMA Grooming Registration',
        'currency'         => 'BDT',
        'value'            => 0
    ]
];

// 3. Send to Cloudflare Worker (Fast / Non-blocking)
$worker_url = 'https://dma-capi-proxy.admin-dhakamodelagency.workers.dev';

$ch = curl_init($worker_url);
curl_setopt($ch, CURLOPT_CUSTOMREQUEST, "POST");
curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($worker_payload));
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
curl_setopt($ch, CURLOPT_TIMEOUT_MS, 1000); // 1 Second timeout - doesn't slow down the user's redirect

// Execute and immediately close connection
curl_exec($ch);
curl_close($ch);

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

redirect('/thank-you.php');