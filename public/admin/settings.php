<?php
// ============================================================
// admin/settings.php  — Campaign & System Settings
// ============================================================

require_once __DIR__ . '/../../core/includes/auth.php';
require_once __DIR__ . '/../../core/includes/functions.php';

require_role('admin');   // admin or superadmin can edit settings

$db      = db();
$current = current_admin();
$success = '';
$error   = '';

// ── Handle POST ───────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verify_csrf($_POST['csrf_token'] ?? '')) {
        $error = 'Invalid request. Please try again.';
    } else {
        $editable_keys = [
            'campaign_name', 'campaign_active', 'max_registrations',
            'fb_pixel_id', 'contact_email', 'contact_phone', 'registration_note',
        ];

        foreach ($editable_keys as $key) {
            if (isset($_POST[$key])) {
                $val = clean($_POST[$key]);

                if ($key === 'max_registrations' && ((int)$val < 1 || (int)$val > 99999)) {
                    $error = 'Max registrations must be between 1 and 99999.';
                    break;
                }
                if ($key === 'contact_email' && !empty($val) && !is_valid_email($val)) {
                    $error = 'Invalid contact email address.';
                    break;
                }
                if ($key === 'campaign_active') {
                    $val = $val === '1' ? '1' : '0';
                }

                // ✅ uses existing set_setting() → writes to campaign_settings table
                set_setting($key, $val);
            }
        }

        // campaign_active checkbox sends nothing when unchecked — handle explicitly
        if (!isset($_POST['campaign_active']) && !$error) {
            set_setting('campaign_active', '0');
        }

        if (!$error) $success = 'Settings saved successfully.';
    }
}

// ── Load current values via get_setting() ─────────────────────
// ✅ All reads go through existing get_setting() → campaign_settings table
$settings = [
    'campaign_name'     => get_setting('campaign_name',     'DMA Grooming Campaign'),
    'campaign_active'   => get_setting('campaign_active',   '1'),
    'max_registrations' => get_setting('max_registrations', '500'),
    'fb_pixel_id'       => get_setting('fb_pixel_id',       ''),
    'contact_email'     => get_setting('contact_email',     ''),
    'contact_phone'     => get_setting('contact_phone',     ''),
    'registration_note' => get_setting('registration_note', ''),
];

// ── Live DB stats ─────────────────────────────────────────────
$counts = $db->query("
    SELECT
        COUNT(*)                   AS total,
        SUM(status = 'pending')    AS pending,
        SUM(status = 'approved')   AS approved,
        SUM(status = 'rejected')   AS rejected
    FROM registrations
")->fetch_assoc();

$max      = (int)$settings['max_registrations'];
$fill_pct = $max > 0 ? min(100, round(($counts['total'] / $max) * 100)) : 0;
$is_active = $settings['campaign_active'] === '1';

$csrf = csrf_token();
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Settings – DMA Admin</title>
<link rel="stylesheet" href="/assets/admin.css">
</head>
<body>

<div class="sidebar-overlay" id="sidebarOverlay" onclick="closeSidebar()"></div>

<div class="admin-layout">

<!-- ── Sidebar ── -->
<aside class="sidebar" id="sidebar">
  <div class="sidebar-logo">
    <div class="sidebar-logo-icon">📋</div>
    <div>
      <div class="sidebar-logo-text">DMA Admin</div>
      <div class="sidebar-logo-sub">Settings</div>
    </div>
  </div>
  <div class="sidebar-section">
    <ul class="sidebar-nav">
      <li><a href="/admin/dashboard.php"><span class="nav-icon">←</span> Dashboard</a></li>
      <?php if ($current['role'] === 'superadmin'): ?>
      <li><a href="/admin/users.php"><span class="nav-icon">👥</span> Users</a></li>
      <?php endif; ?>
      <li><a href="/admin/settings.php" class="active"><span class="nav-icon">⚙️</span> Settings</a></li>
    </ul>
  </div>
  <div class="sidebar-section">
    <div class="sidebar-section-label">Quick Links</div>
    <ul class="sidebar-nav">
      <li><a href="/admin/dashboard.php?status=pending">
        <span class="nav-icon">⏳</span> Pending Leads
      </a></li>
      <li><a href="/admin/export.php">
        <span class="nav-icon">⬇</span> Export CSV
      </a></li>
      <li><a href="/admin/history.php">
        <span class="nav-icon">🕓</span> History
      </a></li>
    </ul>
  </div>
  <div class="sidebar-footer">
    <a href="/admin/logout.php" class="sidebar-user">
      <div class="sidebar-avatar"><?= strtoupper(substr($current['full_name'], 0, 1)) ?></div>
      <div class="sidebar-user-info">
        <div class="sidebar-user-name"><?= clean($current['full_name']) ?></div>
        <div class="sidebar-user-role"><?= $current['role'] ?> · Log out</div>
      </div>
    </a>
  </div>
</aside>

<!-- ── Main ── -->
<div class="main-wrap">

  <div class="topbar">
    <button class="topbar-hamburger" onclick="openSidebar()">☰</button>
    <div class="topbar-breadcrumb">
      <a href="/admin/dashboard.php">Dashboard</a>
      <span class="sep">/</span>
      <span>Settings</span>
    </div>
  </div>

  <div class="page-content">

    <?php if ($success): ?>
    <div class="alert alert-success"><span class="alert-icon">✅</span><?= htmlspecialchars($success) ?></div>
    <?php endif; ?>
    <?php if ($error): ?>
    <div class="alert alert-error"><span class="alert-icon">⚠️</span><?= htmlspecialchars($error) ?></div>
    <?php endif; ?>

    <div class="settings-grid">

      <!-- ── Left: Settings Form ── -->
      <div>
        <div class="card">
          <div class="card-header">
            <div class="card-title">⚙️ Campaign Settings</div>
          </div>
          <div class="card-body">
            <form method="POST" action="/admin/settings.php">
              <input type="hidden" name="csrf_token" value="<?= $csrf ?>">

              <!-- Campaign Name -->
              <div class="form-group">
                <label class="form-label">Campaign Name</label>
                <input type="text" name="campaign_name" class="form-control"
                       value="<?= htmlspecialchars($settings['campaign_name']) ?>" required>
              </div>

              <!-- Campaign Active toggle -->
              <div class="form-group">
                <label class="form-label">Registration Status</label>
                <label style="display:flex;align-items:center;gap:12px;cursor:pointer;">
                  <div class="toggle-track <?= $is_active ? 'on' : '' ?>" id="toggleTrack"
                       onclick="toggleCampaign()">
                    <div class="toggle-thumb"></div>
                  </div>
                  <input type="hidden" name="campaign_active" id="campaignActiveInput"
                         value="<?= $is_active ? '1' : '0' ?>">
                  <div>
                    <div style="font-weight:700;" id="toggleLabel">
                      <?= $is_active ? '🟢 Open — accepting registrations' : '🔴 Closed — no new submissions' ?>
                    </div>
                    <div style="font-size:0.78rem;color:var(--text-muted);margin-top:2px;">
                      Toggle to open or close the registration form on the landing page.
                    </div>
                  </div>
                </label>
              </div>

              <!-- Max Registrations -->
              <div class="form-group">
                <label class="form-label">Maximum Registrations</label>
                <input type="number" name="max_registrations" class="form-control"
                       value="<?= (int)$settings['max_registrations'] ?>"
                       min="1" max="99999">
                <div style="font-size:0.75rem;color:var(--text-muted);margin-top:6px;">
                  <?= $counts['total'] ?> registered · <?= max(0, $max - $counts['total']) ?> slots remaining (<?= $fill_pct ?>% full)
                </div>
                <div class="stat-bar-wrap" style="margin-top:8px;">
                  <div class="stat-bar" style="width:<?= $fill_pct ?>%"></div>
                </div>
              </div>

              <hr style="border:none;border-top:1px solid var(--border);margin:20px 0;">

              <!-- Contact -->
              <div style="font-size:0.75rem;font-weight:700;text-transform:uppercase;
                   letter-spacing:0.06em;color:var(--text-muted);margin-bottom:14px;">
                Contact Details
              </div>

              <div class="form-group">
                <label class="form-label">Contact Phone</label>
                <input type="text" name="contact_phone" class="form-control"
                       value="<?= htmlspecialchars($settings['contact_phone']) ?>"
                       placeholder="+880 1X XX XXXXXX">
              </div>

              <div class="form-group">
                <label class="form-label">Contact Email</label>
                <input type="email" name="contact_email" class="form-control"
                       value="<?= htmlspecialchars($settings['contact_email']) ?>"
                       placeholder="info@dmagrooming.com">
              </div>

              <hr style="border:none;border-top:1px solid var(--border);margin:20px 0;">

              <!-- Tracking -->
              <div style="font-size:0.75rem;font-weight:700;text-transform:uppercase;
                   letter-spacing:0.06em;color:var(--text-muted);margin-bottom:14px;">
                Tracking
              </div>

              <div class="form-group">
                <label class="form-label">Facebook Pixel ID</label>
                <input type="text" name="fb_pixel_id" class="form-control"
                       value="<?= htmlspecialchars($settings['fb_pixel_id']) ?>"
                       placeholder="e.g. 1234567890123456"
                       style="font-family:var(--mono,monospace);">
                <div style="font-size:0.75rem;color:var(--text-muted);margin-top:4px;">
                  Numeric ID only from your Events Manager. Leave blank to disable.
                </div>
              </div>

              <hr style="border:none;border-top:1px solid var(--border);margin:20px 0;">

              <!-- Registration Note -->
              <div class="form-group">
                <label class="form-label">Registration Confirmation Note</label>
                <textarea name="registration_note" class="form-control" rows="4"
                          placeholder="Message shown on the thank-you page after registration."><?= htmlspecialchars($settings['registration_note']) ?></textarea>
              </div>

              <button type="submit" class="btn btn-primary btn-lg btn-full">
                💾 Save All Settings
              </button>
            </form>
          </div>
        </div>
      </div>

      <!-- ── Right: Live Stats + Status ── -->
      <div>

        <!-- Campaign Status -->
        <div class="sidebar-card" style="margin-bottom:16px;">
          <div class="sidebar-card-header">🚦 Campaign Status</div>
          <div class="sidebar-card-body">
            <div style="display:flex;align-items:center;gap:12px;padding:16px;
                 background:<?= $is_active ? '#ecfdf5' : '#fef2f2' ?>;
                 border-radius:var(--radius);
                 border:1px solid <?= $is_active ? '#6ee7b7' : '#fca5a5' ?>;">
              <span style="font-size:1.8rem;"><?= $is_active ? '🟢' : '🔴' ?></span>
              <div>
                <div style="font-weight:800;color:<?= $is_active ? '#065f46' : '#991b1b' ?>;">
                  <?= $is_active ? 'Registrations Open' : 'Registrations Closed' ?>
                </div>
                <div style="font-size:0.78rem;color:var(--text-muted);margin-top:2px;">
                  <?= $is_active
                    ? 'Landing page is live and accepting applicants.'
                    : 'Form is hidden. No new submissions possible.' ?>
                </div>
              </div>
            </div>

            <?php if ($is_active && $counts['total'] >= $max): ?>
            <div class="alert alert-warning" style="margin-top:12px;margin-bottom:0;padding:12px 14px;">
              <span class="alert-icon">⚠️</span>
              Capacity reached. Increase limit or close registrations.
            </div>
            <?php endif; ?>
          </div>
        </div>

        <!-- Live Stats -->
        <div class="sidebar-card" style="margin-bottom:16px;">
          <div class="sidebar-card-header">📊 Live Stats</div>
          <div class="sidebar-card-body">
            <div style="display:flex;flex-direction:column;gap:14px;">
              <?php foreach ([
                ['Total',    $counts['total'],    ''],
                ['Pending',  $counts['pending'],  'color:var(--s-pending)'],
                ['Approved', $counts['approved'], 'color:var(--s-approved)'],
                ['Rejected', $counts['rejected'], 'color:var(--s-rejected)'],
                ['Remaining', max(0, $max - $counts['total']), ''],
              ] as [$label, $val, $style]): ?>
              <div style="display:flex;justify-content:space-between;align-items:center;">
                <span style="font-size:0.875rem;color:var(--text-muted);"><?= $label ?></span>
                <strong style="font-size:1.1rem;<?= $style ?>"><?= $val ?></strong>
              </div>
              <?php endforeach; ?>
            </div>
            <div class="stat-bar-wrap" style="margin-top:16px;">
              <div class="stat-bar" style="width:<?= $fill_pct ?>%"></div>
            </div>
            <div style="font-size:0.75rem;color:var(--text-muted);text-align:right;margin-top:4px;">
              <?= $fill_pct ?>% capacity used
            </div>
          </div>
        </div>

        <!-- User management link (superadmin only) -->
        <?php if ($current['role'] === 'superadmin'): ?>
        <div class="sidebar-card">
          <div class="sidebar-card-header">🔐 Admin Users</div>
          <div class="sidebar-card-body">
            <p style="font-size:0.875rem;color:var(--text-muted);margin-bottom:14px;">
              Manage staff access, roles, and passwords.
            </p>
            <a href="/admin/users.php" class="btn btn-outline btn-full">Manage Users →</a>
          </div>
        </div>
        <?php endif; ?>

      </div>
    </div>

  </div><!-- /page-content -->
</div><!-- /main-wrap -->
</div><!-- /admin-layout -->

<style>
.toggle-track {
  flex-shrink: 0;
  width: 48px; height: 26px;
  background: var(--border);
  border-radius: 999px;
  position: relative;
  cursor: pointer;
  transition: background 0.25s;
}
.toggle-track.on { background: #10b981; }
.toggle-thumb {
  position: absolute;
  top: 3px; left: 3px;
  width: 20px; height: 20px;
  background: white;
  border-radius: 50%;
  box-shadow: 0 1px 4px rgba(0,0,0,0.2);
  transition: transform 0.25s;
}
.toggle-track.on .toggle-thumb { transform: translateX(22px); }
</style>

<script>
function openSidebar() {
  document.getElementById('sidebar').classList.add('open');
  document.getElementById('sidebarOverlay').classList.add('active');
}
function closeSidebar() {
  document.getElementById('sidebar').classList.remove('open');
  document.getElementById('sidebarOverlay').classList.remove('active');
}

function toggleCampaign() {
  const track  = document.getElementById('toggleTrack');
  const input  = document.getElementById('campaignActiveInput');
  const label  = document.getElementById('toggleLabel');
  const isOn   = track.classList.toggle('on');
  input.value  = isOn ? '1' : '0';
  label.textContent = isOn
    ? '🟢 Open — accepting registrations'
    : '🔴 Closed — no new submissions';
}
</script>

</body>
</html>