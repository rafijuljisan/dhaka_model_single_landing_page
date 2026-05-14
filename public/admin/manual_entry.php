<?php
// ============================================================
// public/admin/manual_entry.php — Admin: Manually Add a Lead
// ============================================================

require_once __DIR__ . '/../../core/includes/auth.php';
require_once __DIR__ . '/../../core/includes/functions.php';

require_login();

$db    = db();
$admin = current_admin();

if ($admin['role'] === 'viewer') {
    redirect('/admin/dashboard.php');
}

$all_admins = $db->query("
    SELECT id, full_name FROM admin_users WHERE is_active = 1 ORDER BY full_name
")->fetch_all(MYSQLI_ASSOC);

$errors = [];
$old    = [];

// ── Handle POST ───────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    if (!verify_csrf($_POST['csrf_token'] ?? '')) {
        $errors[] = 'Invalid security token. Please refresh and try again.';
    } else {

        $old = $_POST;

        $full_name   = clean($_POST['full_name']   ?? '');
        $phone       = sanitize_phone($_POST['phone'] ?? '');
        $email       = clean($_POST['email']       ?? '');
        $dob         = clean($_POST['dob']         ?? '');
        $gender      = clean($_POST['gender']      ?? '');
        $height_cm   = (int)($_POST['height_cm']   ?? 0);
        $weight_kg   = !empty($_POST['weight_kg'])  ? (int)$_POST['weight_kg'] : null;
        $skin_tone   = clean($_POST['skin_tone']   ?? '');
        $district    = clean($_POST['district']    ?? '');
        $address     = clean($_POST['address']     ?? '');
        $experience  = clean($_POST['experience']  ?? 'none');
        $exp_details = clean($_POST['exp_details'] ?? '');
        $fb_profile  = clean($_POST['fb_profile']  ?? '');
        $instagram   = clean($_POST['instagram']   ?? '');
        $how_heard   = clean($_POST['how_heard']   ?? 'other');
        $status      = clean($_POST['status']      ?? 'pending');
        $priority    = clean($_POST['priority']    ?? 'normal');
        $admin_note  = clean($_POST['admin_note']  ?? '');
        $assigned_to = (($_POST['assigned_to'] ?? '') !== '') ? (int)$_POST['assigned_to'] : null;

        // ── Validate ──────────────────────────────────────────
        if (strlen($full_name) < 3)
            $errors[] = 'Full name must be at least 3 characters.';

        if (!is_valid_phone($phone))
            $errors[] = 'Enter a valid Bangladeshi mobile number (01XXXXXXXXX).';

        if (!empty($email) && !is_valid_email($email))
            $errors[] = 'Enter a valid email address.';

        if (empty($dob) || !strtotime($dob)) {
            $errors[] = 'Valid date of birth is required.';
        } else {
            $age_check = calculate_age($dob);
            if ($age_check < 14 || $age_check > 50)
                $errors[] = 'Age seems unusual (' . $age_check . ' yrs). Please double-check DOB.';
        }

        if (!in_array($gender, ['male', 'female', 'other']))
            $errors[] = 'Please select a valid gender.';

        if ($height_cm < 100 || $height_cm > 250)
            $errors[] = 'Height must be between 100 cm and 250 cm.';

        if (!in_array($status, all_statuses()))   $status   = 'pending';
        if (!in_array($priority, ['normal','hot','warm','cold'])) $priority = 'normal';

        // Duplicate phone check
        if (empty($errors)) {
            $dup = $db->prepare("SELECT id FROM registrations WHERE phone = ?");
            $dup->bind_param('s', $phone);
            $dup->execute();
            $dup->store_result();
            if ($dup->num_rows > 0)
                $errors[] = 'This phone number is already registered.';
            $dup->close();
        }

        // ── Optional photo upload ─────────────────────────────
        $photo_path = null;
        if (isset($_FILES['photo']) && $_FILES['photo']['error'] !== UPLOAD_ERR_NO_FILE) {
            $upload = upload_photo($_FILES['photo']);
            if (!$upload['success']) $errors[] = $upload['error'];
            else $photo_path = $upload['path'];
        }

        // ── Insert ────────────────────────────────────────────
        if (empty($errors)) {
            $age      = calculate_age($dob);
            $reg_code = generate_reg_code();

            if (!in_array($skin_tone, ['fair','wheatish','dusky','dark'])) $skin_tone  = null;
            if (!in_array($experience, ['none','some','professional']))     $experience = 'none';
            if (!in_array($how_heard, ['facebook','instagram','friend','poster','other'])) $how_heard = 'other';
            if (empty($district)) $district = 'N/A';

            $stmt = $db->prepare("
                INSERT INTO registrations
                    (reg_code, full_name, phone, email, dob, age, gender,
                     height_cm, weight_kg, skin_tone, district, address,
                     experience, exp_details, photo_path, fb_profile, instagram,
                     how_heard, status, priority, admin_note, assigned_to,
                     reviewed_by, reviewed_at, entry_source)
                VALUES
                    (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,NOW(),'manual')
            ");

            $stmt->bind_param(
                'sssssisiissssssssssssii',
                $reg_code,
                $full_name,
                $phone,
                $email,
                $dob,
                $age,
                $gender,
                $height_cm,
                $weight_kg,
                $skin_tone,
                $district,
                $address,
                $experience,
                $exp_details,
                $photo_path,
                $fb_profile,
                $instagram,
                $how_heard,
                $status,
                $priority,
                $admin_note,
                $assigned_to,
                $admin['id']
            );

            if ($stmt->execute()) {
                $new_id = $stmt->insert_id;
                $stmt->close();

                $act      = $db->prepare("
                    INSERT INTO lead_activities (reg_id, admin_id, type, content)
                    VALUES (?, ?, 'note', ?)
                ");
                $note_txt = 'Lead manually entered by ' . $admin['full_name'] . '. Initial status: ' . $status . '.';
                $act->bind_param('iis', $new_id, $admin['id'], $note_txt);
                $act->execute();
                $act->close();

                flash_set('success', 'Lead added. Reg code: ' . $reg_code);
                redirect('/admin/view.php?id=' . $new_id);

            } else {
                $errors[] = 'Database error: ' . $stmt->error;
                $stmt->close();
            }
        }
    }
}

$csrf  = csrf_token();
$flash = flash_get();
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Add Lead Manually – DMA Admin</title>
<link rel="stylesheet" href="/assets/admin.css">
</head>
<body>

<div class="sidebar-overlay" id="sidebarOverlay" onclick="closeSidebar()"></div>

<div class="admin-layout">

<!-- ════════════════ SIDEBAR ════════════════ -->
<aside class="sidebar" id="sidebar">
  <div class="sidebar-logo">
    <div class="sidebar-logo-icon">📋</div>
    <div>
      <div class="sidebar-logo-text">DMA Admin</div>
      <div class="sidebar-logo-sub">Grooming Programme</div>
    </div>
  </div>

  <div class="sidebar-section">
    <div class="sidebar-section-label">Main</div>
    <ul class="sidebar-nav">
      <li><a href="/admin/dashboard.php">
        <span class="nav-icon">📊</span> Dashboard
      </a></li>
      <li><a href="/admin/export.php">
        <span class="nav-icon">⬇</span> Export CSV
      </a></li>
    </ul>
  </div>

  <div class="sidebar-section">
    <div class="sidebar-section-label">Add Leads</div>
    <ul class="sidebar-nav">
      <li><a href="/admin/manual_entry.php" class="active">
        <span class="nav-icon">✏️</span> Add Lead Manually
      </a></li>
      <li><a href="/admin/import.php">
        <span class="nav-icon">📥</span> Import CSV
      </a></li>
    </ul>
  </div>

  <?php if ($admin['role'] !== 'viewer'): ?>
  <div class="sidebar-section">
    <div class="sidebar-section-label">Manage</div>
    <ul class="sidebar-nav">
      <li><a href="/admin/settings.php">
        <span class="nav-icon">⚙️</span> Settings
      </a></li>
      <?php if ($admin['role'] === 'superadmin'): ?>
      <li><a href="/admin/users.php">
        <span class="nav-icon">👥</span> Users
      </a></li>
      <li><a href="/admin/history.php">
        <span class="nav-icon">🕓</span> History
      </a></li>
      <?php endif; ?>
    </ul>
  </div>
  <?php endif; ?>

  <div class="sidebar-section">
    <div class="sidebar-section-label">Quick Filters</div>
    <ul class="sidebar-nav">
      <li><a href="/admin/dashboard.php?status=pending">
        <span class="nav-icon">⏳</span> Pending
      </a></li>
      <li><a href="/admin/dashboard.php?priority=hot">
        <span class="nav-icon">🔥</span> Hot Leads
      </a></li>
      <li><a href="/admin/dashboard.php?status=approved">
        <span class="nav-icon">✅</span> Approved
      </a></li>
    </ul>
  </div>

  <div class="sidebar-footer">
    <a href="/admin/logout.php" class="sidebar-user">
      <div class="sidebar-avatar"><?= strtoupper(substr($admin['full_name'], 0, 1)) ?></div>
      <div class="sidebar-user-info">
        <div class="sidebar-user-name"><?= clean($admin['full_name']) ?></div>
        <div class="sidebar-user-role"><?= $admin['role'] ?> · Log out</div>
      </div>
    </a>
  </div>
</aside>

<!-- ════════════════ MAIN ════════════════ -->
<div class="main-wrap">

  <div class="topbar">
    <button class="topbar-hamburger" onclick="openSidebar()">☰</button>
    <div class="topbar-breadcrumb">
      <a href="/admin/dashboard.php">Dashboard</a>
      <span class="sep">/</span>
      <span>Add Lead Manually</span>
    </div>
    <div class="topbar-actions">
      <a href="/admin/import.php" class="btn btn-outline btn-sm">📥 Import CSV</a>
    </div>
  </div>

  <div class="page-content">

    <?php if ($flash): ?>
    <div class="alert alert-<?= $flash['type'] === 'success' ? 'success' : 'error' ?>">
      <span class="alert-icon"><?= $flash['type'] === 'success' ? '✅' : '⚠️' ?></span>
      <?= $flash['msg'] ?>
    </div>
    <?php endif; ?>

    <?php if (!empty($errors)): ?>
    <div class="alert alert-error">
      <span class="alert-icon">⚠️</span>
      <div>
        <strong>Please fix the following:</strong>
        <ul style="margin:6px 0 0 18px;padding:0;">
          <?php foreach ($errors as $e): ?>
          <li><?= htmlspecialchars($e) ?></li>
          <?php endforeach; ?>
        </ul>
      </div>
    </div>
    <?php endif; ?>

    <form method="POST" enctype="multipart/form-data" id="entryForm">
      <input type="hidden" name="csrf_token" value="<?= $csrf ?>">

      <div style="display:grid;grid-template-columns:1fr 320px;gap:20px;align-items:start;">

        <!-- ══ LEFT COLUMN ══ -->
        <div>

          <!-- Personal Info -->
          <div class="card" style="margin-bottom:16px;">
            <div class="card-header">
              <div class="card-title">👤 Personal Information</div>
            </div>
            <div class="card-body" style="display:flex;flex-direction:column;gap:14px;">

              <div style="display:grid;grid-template-columns:1fr 1fr;gap:14px;">
                <div class="form-group" style="margin-bottom:0;">
                  <label class="form-label">Full Name <span style="color:var(--red)">*</span></label>
                  <input type="text" name="full_name" class="form-control"
                         placeholder="e.g. Fatema Begum"
                         value="<?= htmlspecialchars($old['full_name'] ?? '') ?>" required>
                </div>
                <div class="form-group" style="margin-bottom:0;">
                  <label class="form-label">Phone <span style="color:var(--red)">*</span></label>
                  <input type="text" name="phone" class="form-control"
                         placeholder="01XXXXXXXXX"
                         value="<?= htmlspecialchars($old['phone'] ?? '') ?>" required>
                </div>
              </div>

              <div style="display:grid;grid-template-columns:1fr 1fr;gap:14px;">
                <div class="form-group" style="margin-bottom:0;">
                  <label class="form-label">Email</label>
                  <input type="email" name="email" class="form-control" placeholder="optional"
                         value="<?= htmlspecialchars($old['email'] ?? '') ?>">
                </div>
                <div class="form-group" style="margin-bottom:0;">
                  <label class="form-label">Date of Birth <span style="color:var(--red)">*</span></label>
                  <input type="date" name="dob" id="dobInput" class="form-control"
                         value="<?= htmlspecialchars($old['dob'] ?? '') ?>" required>
                  <span id="ageDisplay"
                        style="font-size:0.78rem;color:var(--text-muted);margin-top:4px;display:block;"></span>
                </div>
              </div>

              <div style="display:grid;grid-template-columns:1fr 1fr 1fr;gap:14px;">
                <div class="form-group" style="margin-bottom:0;">
                  <label class="form-label">Gender <span style="color:var(--red)">*</span></label>
                  <select name="gender" class="form-control" required>
                    <option value="">Select…</option>
                    <?php foreach (['male'=>'Male','female'=>'Female','other'=>'Other'] as $v=>$l): ?>
                    <option value="<?= $v ?>" <?= ($old['gender'] ?? '') === $v ? 'selected':'' ?>><?= $l ?></option>
                    <?php endforeach; ?>
                  </select>
                </div>
                <div class="form-group" style="margin-bottom:0;">
                  <label class="form-label">Height (cm) <span style="color:var(--red)">*</span></label>
                  <input type="number" name="height_cm" class="form-control"
                         min="100" max="250" placeholder="e.g. 165"
                         value="<?= htmlspecialchars($old['height_cm'] ?? '') ?>" required>
                </div>
                <div class="form-group" style="margin-bottom:0;">
                  <label class="form-label">Weight (kg)</label>
                  <input type="number" name="weight_kg" class="form-control"
                         min="30" max="200" placeholder="optional"
                         value="<?= htmlspecialchars($old['weight_kg'] ?? '') ?>">
                </div>
              </div>

              <div style="display:grid;grid-template-columns:1fr 1fr;gap:14px;">
                <div class="form-group" style="margin-bottom:0;">
                  <label class="form-label">Skin Tone</label>
                  <select name="skin_tone" class="form-control">
                    <option value="">— Optional</option>
                    <?php foreach (['fair'=>'Fair','wheatish'=>'Wheatish','dusky'=>'Dusky','dark'=>'Dark'] as $v=>$l): ?>
                    <option value="<?= $v ?>" <?= ($old['skin_tone'] ?? '') === $v ? 'selected':'' ?>><?= $l ?></option>
                    <?php endforeach; ?>
                  </select>
                </div>
                <div class="form-group" style="margin-bottom:0;">
                  <label class="form-label">Experience Level</label>
                  <select name="experience" class="form-control">
                    <?php foreach (['none'=>'None','some'=>'Some','professional'=>'Professional'] as $v=>$l): ?>
                    <option value="<?= $v ?>" <?= ($old['experience'] ?? 'none') === $v ? 'selected':'' ?>><?= $l ?></option>
                    <?php endforeach; ?>
                  </select>
                </div>
              </div>

              <div class="form-group" style="margin-bottom:0;">
                <label class="form-label">Experience Details</label>
                <textarea name="exp_details" class="form-control" rows="2"
                          placeholder="Brief description of past experience…"><?= htmlspecialchars($old['exp_details'] ?? '') ?></textarea>
              </div>

            </div>
          </div>

          <!-- Location -->
          <div class="card" style="margin-bottom:16px;">
            <div class="card-header">
              <div class="card-title">📍 Location</div>
            </div>
            <div class="card-body" style="display:flex;flex-direction:column;gap:14px;">
              <div style="display:grid;grid-template-columns:1fr 1fr;gap:14px;">
                <div class="form-group" style="margin-bottom:0;">
                  <label class="form-label">District</label>
                  <input type="text" name="district" class="form-control" placeholder="e.g. Dhaka"
                         value="<?= htmlspecialchars($old['district'] ?? '') ?>">
                </div>
                <div class="form-group" style="margin-bottom:0;">
                  <label class="form-label">How Did They Hear?</label>
                  <select name="how_heard" class="form-control">
                    <?php foreach (['facebook'=>'Facebook','instagram'=>'Instagram','friend'=>'Friend','poster'=>'Poster','other'=>'Other'] as $v=>$l): ?>
                    <option value="<?= $v ?>" <?= ($old['how_heard'] ?? 'other') === $v ? 'selected':'' ?>><?= $l ?></option>
                    <?php endforeach; ?>
                  </select>
                </div>
              </div>
              <div class="form-group" style="margin-bottom:0;">
                <label class="form-label">Full Address</label>
                <textarea name="address" class="form-control" rows="2"
                          placeholder="Thana, District…"><?= htmlspecialchars($old['address'] ?? '') ?></textarea>
              </div>
            </div>
          </div>

          <!-- Social -->
          <div class="card">
            <div class="card-header">
              <div class="card-title">🌐 Social Media</div>
            </div>
            <div class="card-body" style="display:grid;grid-template-columns:1fr 1fr;gap:14px;">
              <div class="form-group" style="margin-bottom:0;">
                <label class="form-label">Facebook Profile URL</label>
                <input type="url" name="fb_profile" class="form-control"
                       placeholder="https://facebook.com/…"
                       value="<?= htmlspecialchars($old['fb_profile'] ?? '') ?>">
              </div>
              <div class="form-group" style="margin-bottom:0;">
                <label class="form-label">Instagram Handle</label>
                <input type="text" name="instagram" class="form-control" placeholder="@username"
                       value="<?= htmlspecialchars($old['instagram'] ?? '') ?>">
              </div>
            </div>
          </div>

        </div><!-- /left -->

        <!-- ══ RIGHT COLUMN ══ -->
        <div>

          <!-- Photo -->
          <div class="sidebar-card" style="margin-bottom:16px;">
            <div class="sidebar-card-header">📸 Photo</div>
            <div class="sidebar-card-body">
              <input type="file" name="photo" id="photoInput"
                     accept="image/jpeg,image/png,image/webp"
                     style="display:none;" onchange="previewPhoto(this)">
              <div id="photoBox" onclick="document.getElementById('photoInput').click()"
                   style="width:100%;height:200px;background:var(--bg);
                          border:2px dashed var(--border);border-radius:var(--radius);
                          display:flex;flex-direction:column;align-items:center;
                          justify-content:center;gap:8px;cursor:pointer;
                          overflow:hidden;position:relative;transition:border-color 0.2s;">
                <img id="photoPreview" src="" alt=""
                     style="display:none;position:absolute;inset:0;
                            width:100%;height:100%;object-fit:cover;">
                <div id="photoPlaceholder" style="text-align:center;">
                  <div style="font-size:2rem;margin-bottom:4px;">📷</div>
                  <div style="font-size:0.82rem;color:var(--text-muted);">Click to upload photo</div>
                  <div style="font-size:0.75rem;color:var(--text-muted);margin-top:4px;">JPG · PNG · WEBP · Max 5 MB</div>
                </div>
              </div>
              <div id="photoName" style="font-size:0.78rem;color:var(--text-muted);margin-top:8px;display:none;"></div>
              <button type="button" id="removePhoto" onclick="removePhotoPreview()"
                      style="display:none;margin-top:8px;width:100%;"
                      class="btn btn-ghost btn-sm">✕ Remove Photo</button>
            </div>
          </div>

          <!-- Status & Priority -->
          <div class="sidebar-card" style="margin-bottom:16px;">
            <div class="sidebar-card-header">🎯 Status & Priority</div>
            <div class="sidebar-card-body">
              <div class="form-group">
                <label class="form-label">Status</label>
                <select name="status" class="form-control">
                  <?php foreach (all_statuses() as $s): ?>
                  <option value="<?= $s ?>" <?= ($old['status'] ?? 'pending') === $s ? 'selected':'' ?>>
                    <?= match($s) {
                      'pending'  => '⏳ Pending',
                      'reviewed' => '👁 Reviewed',
                      'approved' => '✅ Approved',
                      'rejected' => '❌ Rejected',
                      'waitlist' => '📋 Waitlist',
                      default    => ucfirst($s)
                    } ?>
                  </option>
                  <?php endforeach; ?>
                </select>
              </div>
              <div class="form-group">
                <label class="form-label">Priority</label>
                <select name="priority" class="form-control">
                  <option value="normal" <?= ($old['priority'] ?? 'normal') === 'normal' ? 'selected':'' ?>>Normal</option>
                  <option value="hot"    <?= ($old['priority'] ?? '') === 'hot'    ? 'selected':'' ?>>🔥 Hot — Act now</option>
                  <option value="warm"   <?= ($old['priority'] ?? '') === 'warm'   ? 'selected':'' ?>>🌤 Warm — Follow up</option>
                  <option value="cold"   <?= ($old['priority'] ?? '') === 'cold'   ? 'selected':'' ?>>❄️ Cold — Low interest</option>
                </select>
              </div>
              <div class="form-group">
                <label class="form-label">Assign To</label>
                <select name="assigned_to" class="form-control">
                  <option value="">— Unassigned</option>
                  <?php foreach ($all_admins as $adm): ?>
                  <option value="<?= $adm['id'] ?>"
                    <?= (isset($old['assigned_to']) && (int)$old['assigned_to'] === (int)$adm['id']) ? 'selected':'' ?>>
                    <?= clean($adm['full_name']) ?>
                  </option>
                  <?php endforeach; ?>
                </select>
              </div>
              <div class="form-group" style="margin-bottom:0;">
                <label class="form-label">Internal Note</label>
                <textarea name="admin_note" class="form-control" rows="3"
                          placeholder="Private note — not shown to applicant…"><?= htmlspecialchars($old['admin_note'] ?? '') ?></textarea>
              </div>
            </div>
          </div>

          <button type="submit" class="btn btn-primary btn-full btn-lg">✅ Save Lead</button>
          <a href="/admin/dashboard.php" class="btn btn-ghost btn-full" style="margin-top:8px;">← Cancel</a>

          <div style="margin-top:16px;padding:12px;background:var(--bg);
                      border-radius:var(--radius);font-size:0.78rem;
                      color:var(--text-muted);line-height:1.8;">
            <strong style="color:var(--text-2);">ℹ️ Notes</strong><br>
            • Reg code is generated automatically.<br>
            • Duplicate phone numbers are rejected.<br>
            • Entry is logged as <em>manual</em> in audit trail.<br>
            • You can add activities after saving.
          </div>

        </div><!-- /right -->

      </div><!-- /grid -->
    </form>

  </div><!-- /page-content -->
</div><!-- /main-wrap -->
</div><!-- /admin-layout -->

<script>
function openSidebar() {
  document.getElementById('sidebar').classList.add('open');
  document.getElementById('sidebarOverlay').classList.add('active');
}
function closeSidebar() {
  document.getElementById('sidebar').classList.remove('open');
  document.getElementById('sidebarOverlay').classList.remove('active');
}

// Live age display from DOB
document.getElementById('dobInput').addEventListener('change', function () {
  if (!this.value) { document.getElementById('ageDisplay').textContent = ''; return; }
  const dob   = new Date(this.value);
  const today = new Date();
  let age = today.getFullYear() - dob.getFullYear();
  if (today.getMonth() < dob.getMonth() ||
     (today.getMonth() === dob.getMonth() && today.getDate() < dob.getDate())) age--;
  document.getElementById('ageDisplay').textContent = age >= 0 ? '→ Age: ' + age + ' years' : '';
});

// Photo preview
function previewPhoto(input) {
  const file = input.files[0];
  if (!file) return;
  const reader = new FileReader();
  reader.onload = e => {
    document.getElementById('photoPreview').src              = e.target.result;
    document.getElementById('photoPreview').style.display    = 'block';
    document.getElementById('photoPlaceholder').style.display = 'none';
    document.getElementById('photoName').style.display       = 'block';
    document.getElementById('photoName').textContent         = '📄 ' + file.name;
    document.getElementById('removePhoto').style.display     = 'block';
    document.getElementById('photoBox').style.borderColor    = 'var(--red)';
  };
  reader.readAsDataURL(file);
}

function removePhotoPreview() {
  document.getElementById('photoInput').value                = '';
  document.getElementById('photoPreview').style.display      = 'none';
  document.getElementById('photoPreview').src                = '';
  document.getElementById('photoPlaceholder').style.display  = 'flex';
  document.getElementById('photoName').style.display         = 'none';
  document.getElementById('removePhoto').style.display       = 'none';
  document.getElementById('photoBox').style.borderColor      = 'var(--border)';
}
</script>
</body>
</html>