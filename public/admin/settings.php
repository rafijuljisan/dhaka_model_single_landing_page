<?php
// ============================================================
// admin/settings.php  — Campaign Settings
// ============================================================

require_once __DIR__ . '/../../core/includes/auth.php';
require_once __DIR__ . '/../../core/includes/functions.php';

require_role('admin');

$success = '';
$error   = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verify_csrf($_POST['csrf_token'] ?? '')) {
        $error = 'Invalid request. Please try again.';
    } else {
        $editable_keys = [
            'campaign_name', 'campaign_active', 'max_registrations',
            'fb_pixel_id', 'contact_email', 'contact_phone', 'registration_note'
        ];

        foreach ($editable_keys as $key) {
            if (isset($_POST[$key])) {
                $val = clean($_POST[$key]);

                // Extra validation per key
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

                set_setting($key, $val);
            }
        }

        if (!$error) $success = 'Settings saved successfully.';
    }
}

// Load current values
$settings = [
    'campaign_name'     => get_setting('campaign_name'),
    'campaign_active'   => get_setting('campaign_active', '1'),
    'max_registrations' => get_setting('max_registrations', '500'),
    'fb_pixel_id'       => get_setting('fb_pixel_id'),
    'contact_email'     => get_setting('contact_email'),
    'contact_phone'     => get_setting('contact_phone'),
    'registration_note' => get_setting('registration_note'),
];

// Live DB stats
$db   = db();
$total = $db->query("SELECT COUNT(*) AS c FROM registrations")->fetch_assoc()['c'];
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

<?php include __DIR__ . '/../../core/admin_partials/navbar.php'; ?>

<div class="container">
  <div class="page-header">
    <h1>⚙ Campaign Settings</h1>
  </div>

  <?php if ($success): ?><div class="alert alert-success"><?= htmlspecialchars($success) ?></div><?php endif; ?>
  <?php if ($error):   ?><div class="alert alert-error"><?= htmlspecialchars($error) ?></div><?php endif; ?>

  <div class="settings-grid">

    <!-- Settings Form -->
    <div class="settings-main">
      <form method="POST" action="/admin/settings.php" class="settings-form">
        <input type="hidden" name="csrf_token" value="<?= csrf_token() ?>">

        <div class="form-group">
          <label>Campaign Name</label>
          <input type="text" name="campaign_name"
                 value="<?= htmlspecialchars($settings['campaign_name']) ?>" required>
        </div>

        <div class="form-group">
          <label>Campaign Status</label>
          <div class="toggle-row">
            <label class="toggle">
              <input type="checkbox" name="campaign_active" value="1"
                     <?= $settings['campaign_active'] === '1' ? 'checked' : '' ?>>
              <span class="toggle-slider"></span>
            </label>
            <span><?= $settings['campaign_active'] === '1' ? 'Active — accepting registrations' : 'Closed' ?></span>
          </div>
        </div>

        <div class="form-group">
          <label>Max Registrations</label>
          <input type="number" name="max_registrations" min="1" max="99999"
                 value="<?= htmlspecialchars($settings['max_registrations']) ?>">
          <small><?= $total ?> registered so far · <?= max(0, (int)$settings['max_registrations'] - $total) ?> slots remaining</small>
        </div>

        <div class="form-group">
          <label>Meta (Facebook) Pixel ID</label>
          <input type="text" name="fb_pixel_id"
                 value="<?= htmlspecialchars($settings['fb_pixel_id']) ?>"
                 placeholder="e.g. 1234567890123456">
          <small>Paste only the numeric ID from your Events Manager.</small>
        </div>

        <div class="form-group">
          <label>Contact Email</label>
          <input type="email" name="contact_email"
                 value="<?= htmlspecialchars($settings['contact_email']) ?>">
        </div>

        <div class="form-group">
          <label>Contact Phone</label>
          <input type="text" name="contact_phone"
                 value="<?= htmlspecialchars($settings['contact_phone']) ?>">
        </div>

        <div class="form-group">
          <label>Registration Confirmation Note</label>
          <textarea name="registration_note" rows="4"><?= htmlspecialchars($settings['registration_note']) ?></textarea>
          <small>Shown on the thank-you page and sent in confirmation email.</small>
        </div>

        <button type="submit" class="btn btn-primary">Save Settings</button>
      </form>
    </div>

    <!-- Info sidebar -->
    <div class="settings-sidebar">
      <div class="sidebar-card">
        <h3>📊 Live Stats</h3>
        <div class="info-row"><span>Total Registered</span><strong><?= $total ?></strong></div>
        <div class="info-row"><span>Quota</span><strong><?= $settings['max_registrations'] ?></strong></div>
        <div class="info-row"><span>Campaign</span>
          <strong class="<?= $settings['campaign_active']==='1' ? 'text-success' : 'text-danger' ?>">
            <?= $settings['campaign_active']==='1' ? 'Active' : 'Closed' ?>
          </strong>
        </div>
      </div>

      <?php if (current_admin()['role'] === 'superadmin'): ?>
      <div class="sidebar-card">
        <h3>🔐 Admin Users</h3>
        <p>Manage who has access to this panel.</p>
        <a href="/admin/users.php" class="btn btn-outline btn-full" style="margin-top:10px;">Manage Users</a>
      </div>
      <?php endif; ?>
    </div>

  </div>
</div>
</body>
</html>
