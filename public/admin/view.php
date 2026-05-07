<?php
// ============================================================
// admin/view.php  — Single Applicant Detail + Status Update
// ============================================================

require_once __DIR__ . '/../../core/includes/auth.php';
require_once __DIR__ . '/../../core/includes/functions.php';

require_login();

$db    = db();
$admin = current_admin();
$id    = (int)($_GET['id'] ?? 0);

if ($id <= 0) redirect('/admin/dashboard.php');

// ── Fetch registration ────────────────────────────────────────
$stmt = $db->prepare("
    SELECT r.*, a.full_name AS reviewed_by_name
    FROM   registrations r
    LEFT JOIN admin_users a ON r.reviewed_by = a.id
    WHERE  r.id = ?
    LIMIT 1
");
$stmt->bind_param('i', $id);
$stmt->execute();
$reg = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$reg) {
    http_response_code(404);
    die('<h2>Registration not found.</h2> <a href="/admin/dashboard.php">← Back</a>');
}

// ── Fetch audit log ───────────────────────────────────────────
$log_stmt = $db->prepare("
    SELECT l.old_status, l.new_status, l.note, l.changed_at, a.full_name AS changed_by
    FROM   status_logs l
    JOIN   admin_users a ON l.changed_by = a.id
    WHERE  l.reg_id = ?
    ORDER  BY l.changed_at DESC
");
$log_stmt->bind_param('i', $id);
$log_stmt->execute();
$logs = $log_stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$log_stmt->close();

// ── Handle status update (POST) ───────────────────────────────
$success_msg = '';
$error_msg   = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $admin['role'] !== 'viewer') {

    if (!verify_csrf($_POST['csrf_token'] ?? '')) {
        $error_msg = 'Invalid CSRF token. Please try again.';
    } else {
        $new_status = clean($_POST['new_status'] ?? '');
        $note       = clean($_POST['admin_note'] ?? '');

        if (!in_array($new_status, all_statuses(), true)) {
            $error_msg = 'Invalid status selected.';
        } else {
            $old_status = $reg['status'];

            // Update registration
            $upd = $db->prepare("
                UPDATE registrations
                SET    status = ?, admin_note = ?, reviewed_by = ?, reviewed_at = NOW()
                WHERE  id = ?
            ");
            $upd->bind_param('ssii', $new_status, $note, $admin['id'], $id);
            $upd->execute();
            $upd->close();

            // Log the change
            $log = $db->prepare("
                INSERT INTO status_logs (reg_id, changed_by, old_status, new_status, note)
                VALUES (?, ?, ?, ?, ?)
            ");
            $log->bind_param('iisss', $id, $admin['id'], $old_status, $new_status, $note);
            $log->execute();
            $log->close();

            $success_msg   = "Status updated to <strong>" . ucfirst($new_status) . "</strong>.";
            $reg['status'] = $new_status;    // reflect immediately
            $reg['admin_note']       = $note;
            $reg['reviewed_by_name'] = $admin['full_name'];
            $reg['reviewed_at']      = date('Y-m-d H:i:s');

            // Re-fetch logs
            $logs = $db->query("
                SELECT l.old_status, l.new_status, l.note, l.changed_at, a.full_name AS changed_by
                FROM   status_logs l
                JOIN   admin_users a ON l.changed_by = a.id
                WHERE  l.reg_id = $id
                ORDER  BY l.changed_at DESC
            ")->fetch_all(MYSQLI_ASSOC);
        }
    }
}

// ── Navigate prev / next ───────────────────────────────────────
$prev = $db->query("SELECT id FROM registrations WHERE id < $id ORDER BY id DESC LIMIT 1")->fetch_assoc();
$next = $db->query("SELECT id FROM registrations WHERE id > $id ORDER BY id ASC  LIMIT 1")->fetch_assoc();
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title><?= clean($reg['full_name']) ?> – DMA Admin</title>
<link rel="stylesheet" href="/assets/admin.css">
</head>
<body>

<?php include __DIR__ . '/../../core/admin_partials/navbar.php'; ?>

<div class="container">

  <!-- ── Breadcrumb + Navigation ── -->
  <div class="breadcrumb">
    <a href="/admin/dashboard.php">← Dashboard</a>
    <?php if ($prev): ?>
      <a href="/admin/view.php?id=<?= $prev['id'] ?>">‹ Prev</a>
    <?php endif; ?>
    <?php if ($next): ?>
      <a href="/admin/view.php?id=<?= $next['id'] ?>">Next ›</a>
    <?php endif; ?>
  </div>

  <?php if ($success_msg): ?>
  <div class="alert alert-success"><?= $success_msg ?></div>
  <?php endif; ?>
  <?php if ($error_msg): ?>
  <div class="alert alert-error"><?= htmlspecialchars($error_msg) ?></div>
  <?php endif; ?>

  <div class="view-grid">

    <!-- ── Left: Applicant Info ── -->
    <div class="view-main">
      <div class="view-card">
        <div class="view-header">
          <?php if ($reg['photo_path']): ?>
          <img src="<?= clean($reg['photo_path']) ?>" class="profile-photo" alt="Applicant Photo">
          <?php else: ?>
          <div class="profile-photo-placeholder">👤</div>
          <?php endif; ?>
          <div>
            <h2><?= clean($reg['full_name']) ?></h2>
            <code><?= clean($reg['reg_code']) ?></code>
            <span class="badge <?= status_badge_class($reg['status']) ?> badge-lg">
              <?= ucfirst($reg['status']) ?>
            </span>
          </div>
        </div>

        <!-- Personal Info -->
        <div class="info-section">
          <h3>Personal Information</h3>
          <div class="info-grid">
            <div class="info-row"><span>Phone</span><strong><?= clean($reg['phone']) ?></strong></div>
            <div class="info-row"><span>Email</span><strong><?= $reg['email'] ? clean($reg['email']) : '—' ?></strong></div>
            <div class="info-row"><span>Date of Birth</span><strong><?= date('d M Y', strtotime($reg['dob'])) ?></strong></div>
            <div class="info-row"><span>Age</span><strong><?= (int)$reg['age'] ?> years</strong></div>
            <div class="info-row"><span>Gender</span><strong><?= ucfirst(clean($reg['gender'])) ?></strong></div>
            <div class="info-row"><span>Height</span><strong><?= (int)$reg['height_cm'] ?> cm</strong></div>
            <div class="info-row"><span>Weight</span><strong><?= $reg['weight_kg'] ? (int)$reg['weight_kg'].' kg' : '—' ?></strong></div>
            <div class="info-row"><span>Skin Tone</span><strong><?= $reg['skin_tone'] ? ucfirst(clean($reg['skin_tone'])) : '—' ?></strong></div>
          </div>
        </div>

        <!-- Location -->
        <div class="info-section">
          <h3>Location</h3>
          <div class="info-grid">
            <div class="info-row"><span>District</span><strong><?= clean($reg['district']) ?></strong></div>
            <div class="info-row"><span>Address</span><strong><?= $reg['address'] ? clean($reg['address']) : '—' ?></strong></div>
          </div>
        </div>

        <!-- Experience -->
        <div class="info-section">
          <h3>Experience</h3>
          <div class="info-grid">
            <div class="info-row"><span>Level</span><strong><?= ucfirst(clean($reg['experience'])) ?></strong></div>
          </div>
          <?php if ($reg['exp_details']): ?>
          <div class="info-text"><?= nl2br(clean($reg['exp_details'])) ?></div>
          <?php endif; ?>
        </div>

        <!-- Social -->
        <div class="info-section">
          <h3>Social & Source</h3>
          <div class="info-grid">
            <div class="info-row">
              <span>Facebook</span>
              <strong>
                <?php if ($reg['fb_profile']): ?>
                <a href="<?= clean($reg['fb_profile']) ?>" target="_blank" rel="noopener">View Profile ↗</a>
                <?php else: ?>—<?php endif; ?>
              </strong>
            </div>
            <div class="info-row"><span>Instagram</span><strong><?= $reg['instagram'] ? '@'.clean($reg['instagram']) : '—' ?></strong></div>
            <div class="info-row"><span>Heard from</span><strong><?= ucfirst(clean($reg['how_heard'])) ?></strong></div>
          </div>
        </div>

        <!-- Meta -->
        <div class="info-section info-meta">
          <h3>Submission Meta</h3>
          <div class="info-grid">
            <div class="info-row"><span>Submitted</span><strong><?= date('d M Y, h:i A', strtotime($reg['created_at'])) ?></strong></div>
            <div class="info-row"><span>IP Address</span><strong><?= clean($reg['ip_address'] ?? '—') ?></strong></div>
            <?php if ($reg['reviewed_by_name']): ?>
            <div class="info-row"><span>Reviewed by</span><strong><?= clean($reg['reviewed_by_name']) ?></strong></div>
            <div class="info-row"><span>Reviewed at</span><strong><?= date('d M Y, h:i A', strtotime($reg['reviewed_at'])) ?></strong></div>
            <?php endif; ?>
          </div>
        </div>
      </div>
    </div>

    <!-- ── Right: Status Panel + Log ── -->
    <div class="view-sidebar">

      <!-- Status Update -->
      <?php if ($admin['role'] !== 'viewer'): ?>
      <div class="sidebar-card">
        <h3>Update Status</h3>
        <form method="POST" action="/admin/view.php?id=<?= $id ?>">
          <input type="hidden" name="csrf_token" value="<?= csrf_token() ?>">

          <label>New Status</label>
          <select name="new_status" class="form-select">
            <?php foreach (all_statuses() as $s): ?>
            <option value="<?= $s ?>" <?= $reg['status'] === $s ? 'selected' : '' ?>>
              <?= ucfirst($s) ?>
            </option>
            <?php endforeach; ?>
          </select>

          <label style="margin-top:12px;">Admin Note</label>
          <textarea name="admin_note" class="form-textarea" rows="4"
                    placeholder="Internal note (not shown to applicant)"><?= htmlspecialchars($reg['admin_note'] ?? '') ?></textarea>

          <button type="submit" class="btn btn-primary btn-full" style="margin-top:12px;">
            Save Status
          </button>
        </form>
      </div>
      <?php else: ?>
      <div class="sidebar-card">
        <h3>Current Status</h3>
        <span class="badge <?= status_badge_class($reg['status']) ?> badge-lg"><?= ucfirst($reg['status']) ?></span>
        <?php if ($reg['admin_note']): ?>
        <p style="margin-top:12px; color:#555; font-size:.9rem;"><?= nl2br(clean($reg['admin_note'])) ?></p>
        <?php endif; ?>
      </div>
      <?php endif; ?>

      <!-- Audit Log -->
      <div class="sidebar-card">
        <h3>Status History</h3>
        <?php if (empty($logs)): ?>
          <p class="muted">No status changes yet.</p>
        <?php else: ?>
          <div class="audit-log">
          <?php foreach ($logs as $log): ?>
            <div class="audit-item">
              <div class="audit-change">
                <span class="badge <?= status_badge_class($log['old_status'] ?? 'pending') ?>"><?= ucfirst($log['old_status'] ?? 'new') ?></span>
                →
                <span class="badge <?= status_badge_class($log['new_status']) ?>"><?= ucfirst($log['new_status']) ?></span>
              </div>
              <div class="audit-by">by <?= clean($log['changed_by']) ?></div>
              <div class="audit-time"><?= date('d M Y, h:i A', strtotime($log['changed_at'])) ?></div>
              <?php if ($log['note']): ?>
              <div class="audit-note"><?= nl2br(clean($log['note'])) ?></div>
              <?php endif; ?>
            </div>
          <?php endforeach; ?>
          </div>
        <?php endif; ?>
      </div>

    </div><!-- /sidebar -->
  </div><!-- /view-grid -->
</div><!-- /container -->
</body>
</html>
