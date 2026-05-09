<?php
// ============================================================
// includes/functions.php  — Core Helper Functions
// ============================================================

require_once __DIR__ . '/db.php';

// ── String & Sanitization ───────────────────────────────────

function clean(string $val): string {
    return htmlspecialchars(trim($val), ENT_QUOTES, 'UTF-8');
}

function sanitize_phone(string $phone): string {
    $phone = preg_replace('/[^\d+]/', '', $phone);
    // Normalize Bangladeshi numbers → +8801XXXXXXXXX
    if (preg_match('/^01[3-9]\d{8}$/', $phone)) {
        $phone = '+88' . $phone; // Fixed: Changed '+880' to '+88'
    } elseif (preg_match('/^8801[3-9]\d{8}$/', $phone)) {
        $phone = '+' . $phone;
    }
    return $phone;
}

function is_valid_phone(string $phone): bool {
    return (bool) preg_match('/^\+8801[3-9]\d{8}$/', $phone);
}

function is_valid_email(string $email): bool {
    return filter_var($email, FILTER_VALIDATE_EMAIL) !== false;
}

// ── Registration Code Generator ─────────────────────────────

function generate_reg_code(): string {
    $db   = db();
    $year = date('Y');

    $stmt = $db->prepare("SELECT COUNT(*) AS total FROM registrations WHERE YEAR(created_at) = ?");
    $stmt->bind_param('s', $year);
    $stmt->execute();
    $row   = $stmt->get_result()->fetch_assoc();
    $next  = (int)$row['total'] + 1;
    $stmt->close();

    return 'DMA-' . $year . '-' . str_pad($next, 5, '0', STR_PAD_LEFT);
}

// ── Age Calculator ──────────────────────────────────────────

function calculate_age(string $dob): int {
    return (int) (new DateTime($dob))->diff(new DateTime())->y;
}

// ── Photo Upload ────────────────────────────────────────────

// 1. ABSOLUTE SERVER PATH: Forces PHP to save exactly inside your live public folder
define('UPLOAD_DIR', $_SERVER['DOCUMENT_ROOT'] . '/uploads/photos/');

// 2. PUBLIC URL PATH: Tells the browser where to find the image (starts at the domain root)
define('UPLOAD_URL_PATH', '/uploads/photos/');

// 3. SECURITY: Maximum file size allowed (5 MB)
define('MAX_FILE_SIZE', 5 * 1024 * 1024);

// 4. SECURITY: Only allow these exact image formats (prevents PHP script uploads)
define('ALLOWED_TYPES', ['image/jpeg', 'image/png', 'image/webp']);

function upload_photo(array $file): array {
    // Returns ['success' => bool, 'path' => string|null, 'error' => string|null]

    if ($file['error'] !== UPLOAD_ERR_OK) {
        return ['success' => false, 'path' => null, 'error' => 'File upload error code: ' . $file['error']];
    }
    if ($file['size'] > MAX_FILE_SIZE) {
        return ['success' => false, 'path' => null, 'error' => 'Photo must be under 5 MB.'];
    }

    // Verify actual MIME (not just extension)
    $finfo = new finfo(FILEINFO_MIME_TYPE);
    $mime  = $finfo->file($file['tmp_name']);
    if (!in_array($mime, ALLOWED_TYPES, true)) {
        return ['success' => false, 'path' => null, 'error' => 'Only JPG, PNG, WEBP allowed.'];
    }

    $ext      = match($mime) {
        'image/jpeg' => 'jpg',
        'image/png'  => 'png',
        'image/webp' => 'webp',
    };
    $filename = date('Ymd_His') . '_' . bin2hex(random_bytes(5)) . '.' . $ext;
    $dest     = UPLOAD_DIR . $filename;

    if (!is_dir(UPLOAD_DIR)) {
        mkdir(UPLOAD_DIR, 0777, true);
    }

    if (!move_uploaded_file($file['tmp_name'], $dest)) {
        return ['success' => false, 'path' => null, 'error' => 'Could not save photo. Check server permissions.'];
    }

    return ['success' => true, 'path' => UPLOAD_URL_PATH . $filename, 'error' => null];
}

// ── Registration: Save ──────────────────────────────────────

function save_registration(array $d): array {
    $db = db();

    // Duplicate phone check
    $stmt = $db->prepare("SELECT id FROM registrations WHERE phone = ?");
    $stmt->bind_param('s', $d['phone']);
    $stmt->execute();
    $stmt->store_result();
    if ($stmt->num_rows > 0) {
        $stmt->close();
        return ['success' => false, 'error' => 'This phone number is already registered.'];
    }
    $stmt->close();

    $reg_code = generate_reg_code();
    $age      = calculate_age($d['dob']);
    $ip       = $_SERVER['REMOTE_ADDR'] ?? null;
    $ua       = $_SERVER['HTTP_USER_AGENT'] ?? null;

    $sql = "INSERT INTO registrations 
            (reg_code, full_name, phone, email, dob, age, gender, height_cm, weight_kg,
             skin_tone, district, address, experience, exp_details, photo_path,
             fb_profile, instagram, how_heard, ip_address, user_agent)
            VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)";

$stmt = $db->prepare($sql);
    
    // FIXED: 20 characters mapped exactly (s=string, i=integer) with no spaces.
    $stmt->bind_param(
        'sssssisiisssssssssss', 
        $reg_code,         // 1. s
        $d['full_name'],   // 2. s
        $d['phone'],       // 3. s
        $d['email'],       // 4. s
        $d['dob'],         // 5. s
        $age,              // 6. i (integer)
        $d['gender'],      // 7. s
        $d['height_cm'],   // 8. i (integer)
        $d['weight_kg'],   // 9. i (integer)
        $d['skin_tone'],   // 10. s
        $d['district'],    // 11. s
        $d['address'],     // 12. s
        $d['experience'],  // 13. s
        $d['exp_details'], // 14. s
        $d['photo_path'],  // 15. s
        $d['fb_profile'],  // 16. s
        $d['instagram'],   // 17. s
        $d['how_heard'],   // 18. s
        $ip,               // 19. s
        $ua                // 20. s
    );

    if (!$stmt->execute()) {
        error_log('Registration insert error: ' . $stmt->error);
        $stmt->close();
        return ['success' => false, 'error' => 'Database error. Please try again.'];
    }
    $stmt->close();

    return ['success' => true, 'reg_code' => $reg_code, 'age' => $age];
}

// ── Registration: Validate POST ─────────────────────────────

function validate_registration(array $post): array {
    $errors = [];
    $d      = [];

    // Full name
    $d['full_name'] = clean($post['full_name'] ?? '');
    if (strlen($d['full_name']) < 3) $errors[] = 'Full name is required (min 3 characters).';

    // Phone
    $d['phone'] = sanitize_phone($post['phone'] ?? '');
    if (!is_valid_phone($d['phone'])) $errors[] = 'Enter a valid Bangladeshi mobile number (01XXXXXXXXX).';

    // Email (optional)
    $d['email'] = clean($post['email'] ?? '');
    if (!empty($d['email']) && !is_valid_email($d['email'])) $errors[] = 'Enter a valid email address.';

    // DOB
    $d['dob'] = clean($post['dob'] ?? '');
    if (empty($d['dob']) || !strtotime($d['dob'])) {
        $errors[] = 'Valid date of birth is required.';
    } else {
        $age = calculate_age($d['dob']);
        if ($age < 16 || $age > 35) $errors[] = 'Age must be between 16 and 35 years.';
    }

    // Gender
    $allowed_genders = ['male', 'female', 'other'];
    $d['gender'] = clean($post['gender'] ?? '');
    if (!in_array($d['gender'], $allowed_genders)) $errors[] = 'Please select a valid gender.';

    // Height
    $d['height_cm'] = (int)($post['height_cm'] ?? 0);
    if ($d['height_cm'] < 140 || $d['height_cm'] > 220) $errors[] = 'Height must be between 140cm and 220cm.';

    // Weight (optional)
    $d['weight_kg'] = !empty($post['weight_kg']) ? (int)$post['weight_kg'] : null;

    // Skin tone (optional)
    $allowed_tones = ['fair', 'wheatish', 'dusky', 'dark', ''];
    $d['skin_tone'] = clean($post['skin_tone'] ?? '');
    if (!in_array($d['skin_tone'], $allowed_tones)) $d['skin_tone'] = null;

    // District (Auto-fill to satisfy the database 'NOT NULL' requirement)
    $d['district'] = 'N/A';

    // Address (Now required)
    $d['address'] = clean($post['address'] ?? '');
    if (strlen($d['address']) < 5) {
        $errors[] = 'সম্পূর্ণ ঠিকানা লিখুন (যেমন: জেলা, উপজেলা)।';
    }

    // Address (optional)
    $d['address'] = clean($post['address'] ?? '');

    // Experience
    $allowed_exp = ['none', 'some', 'professional'];
    $d['experience'] = clean($post['experience'] ?? 'none');
    if (!in_array($d['experience'], $allowed_exp)) $d['experience'] = 'none';
    $d['exp_details'] = clean($post['exp_details'] ?? '');

    // Social
    $d['fb_profile']  = clean($post['fb_profile'] ?? '');
    $d['instagram']   = clean($post['instagram'] ?? '');

    // How heard
    $allowed_heard = ['facebook', 'instagram', 'friend', 'poster', 'other'];
    $d['how_heard'] = clean($post['how_heard'] ?? 'facebook');
    if (!in_array($d['how_heard'], $allowed_heard)) $d['how_heard'] = 'other';

    // Photo (optional upload)
    $d['photo_path'] = null;
    if (isset($_FILES['photo']) && $_FILES['photo']['error'] !== UPLOAD_ERR_NO_FILE) {
        $upload = upload_photo($_FILES['photo']);
        if (!$upload['success']) {
            $errors[] = $upload['error'];
        } else {
            $d['photo_path'] = $upload['path'];
        }
    }

    return ['errors' => $errors, 'data' => $d];
}

// ── Settings ────────────────────────────────────────────────

function get_setting(string $key, string $default = ''): string {
    static $cache = [];
    if (isset($cache[$key])) return $cache[$key];

    $db   = db();
    $stmt = $db->prepare("SELECT setting_value FROM campaign_settings WHERE setting_key = ?");
    $stmt->bind_param('s', $key);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    $cache[$key] = $row ? $row['setting_value'] : $default;
    return $cache[$key];
}

function set_setting(string $key, string $value): void {
    $db   = db();
    $stmt = $db->prepare("INSERT INTO campaign_settings (setting_key, setting_value)
                          VALUES (?, ?) ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value)");
    $stmt->bind_param('ss', $key, $value);
    $stmt->execute();
    $stmt->close();
}

// ── Status Helpers ───────────────────────────────────────────

function status_badge_class(string $status): string {
    return match($status) {
        'approved'  => 'badge-success',
        'rejected'  => 'badge-danger',
        'reviewed'  => 'badge-info',
        'waitlist'  => 'badge-warning',
        default     => 'badge-secondary',
    };
}

function all_statuses(): array {
    return ['pending', 'reviewed', 'approved', 'rejected', 'waitlist'];
}

// ── Pagination ───────────────────────────────────────────────

function paginate(int $total, int $per_page, int $current_page): array {
    $total_pages = (int) ceil($total / $per_page);
    $current_page = max(1, min($current_page, $total_pages));
    $offset = ($current_page - 1) * $per_page;

    return [
        'total'        => $total,
        'per_page'     => $per_page,
        'current_page' => $current_page,
        'total_pages'  => $total_pages,
        'offset'       => $offset,
    ];
}

// ── Email Notification ───────────────────────────────────────

function send_notification_email(string $reg_code, string $name, string $to_email): bool {
    if (empty($to_email)) return false;

    $contact = get_setting('contact_phone');
    $subject = 'Registration Received – Dhaka Model Agency';
    $body    = "Dear {$name},\n\nThank you for registering!\n\n"
             . "Your Registration Code: {$reg_code}\n\n"
             . get_setting('registration_note') . "\n\n"
             . "Contact: {$contact}\n\n"
             . "— Dhaka Model Agency Team";

    $headers = "From: noreply@dhakamodelAgency.com\r\n"
             . "Reply-To: " . get_setting('contact_email') . "\r\n"
             . "X-Mailer: PHP/" . phpversion();

    return mail($to_email, $subject, $body, $headers);
}

// ── CSRF Protection ──────────────────────────────────────────

function csrf_token(): string {
    if (session_status() === PHP_SESSION_NONE) session_start();
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

function verify_csrf(string $token): bool {
    if (session_status() === PHP_SESSION_NONE) session_start();
    return isset($_SESSION['csrf_token']) && hash_equals($_SESSION['csrf_token'], $token);
}
// ============================================================
// APPEND THIS BLOCK to the bottom of:
//   core/includes/functions.php
//
// These are NEW helpers used by the new admin pages.
// They do NOT replace any existing function.
// ============================================================
 
// ── Time ago display ─────────────────────────────────────────
// Used in activity timeline to show "2h ago" style timestamps.
 
if (!function_exists('time_ago')) {
    function time_ago(string $datetime): string {
        $diff = time() - strtotime($datetime);
        if ($diff < 60)     return 'just now';
        if ($diff < 3600)   return floor($diff / 60) . 'm ago';
        if ($diff < 86400)  return floor($diff / 3600) . 'h ago';
        if ($diff < 604800) return floor($diff / 86400) . 'd ago';
        return date('d M Y', strtotime($datetime));
    }
}
 
// ── Age from DOB ─────────────────────────────────────────────
// Alias so new admin pages can call age_from_dob() while the
// public submit.php continues using calculate_age().
 
if (!function_exists('age_from_dob')) {
    function age_from_dob(string $dob): int {
        return calculate_age($dob); // delegates to existing function
    }
}
 
// ── Flash messages ────────────────────────────────────────────
// One-time session messages passed across redirects.
 
if (!function_exists('flash_set')) {
    function flash_set(string $type, string $msg): void {
        if (session_status() === PHP_SESSION_NONE) session_start();
        $_SESSION['flash'] = compact('type', 'msg');
    }
}
 
if (!function_exists('flash_get')) {
    function flash_get(): ?array {
        if (session_status() === PHP_SESSION_NONE) session_start();
        $flash = $_SESSION['flash'] ?? null;
        unset($_SESSION['flash']);
        return $flash;
    }
}
 
// ── BD phone normaliser alias ─────────────────────────────────
// New admin files call normalise_phone(); existing code uses
// sanitize_phone(). This alias bridges the gap.
 
if (!function_exists('normalise_phone')) {
    function normalise_phone(string $phone): string {
        return sanitize_phone($phone); // delegates to existing function
    }
}
 
// ── BD phone validator alias ──────────────────────────────────
 
if (!function_exists('is_valid_bd_phone')) {
    function is_valid_bd_phone(string $phone): bool {
        return is_valid_phone(sanitize_phone($phone));
    }
}
/*/
// ── status_badge_class — NEW names used by new admin pages ────
// ⚠️  The EXISTING function returns Bootstrap-style names
//    (badge-success, badge-danger…).
//    The NEW admin pages use status names directly as CSS classes
//    (badge-approved, badge-rejected…) — those are handled via
//    inline class="badge badge-<?= $status ?>" in HTML.
//
//    The audit log and sidebar in view.php still call
//    status_badge_class() — which keeps returning the old names.
//    Both old names and new names are defined in the new admin.css.
//    So NO change needed here — existing function is fine as-is.
 
// ── Paginate — ensure 'total_rows' key exists ────────────────
// The existing paginate() returns 'total' but new dashboard.php
// stores the count separately, so there is NO conflict.
// Nothing to add here.
*/
// ── Redirect ─────────────────────────────────────────────────

function redirect(string $url): never {
    header('Location: ' . $url);
    exit;
}
