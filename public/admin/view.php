<?php
// ============================================================
// admin/view.php  — Lead CRM View: Status, Assignment, Activities
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
    SELECT r.*, a.full_name AS reviewed_by_name,
           au.full_name AS assigned_to_name, au.id AS assigned_to_id
    FROM   registrations r
    LEFT JOIN admin_users a  ON r.reviewed_by  = a.id
    LEFT JOIN admin_users au ON r.assigned_to  = au.id
    WHERE  r.id = ? LIMIT 1
");
$stmt->bind_param('i', $id);
$stmt->execute();
$reg = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$reg) {
    http_response_code(404);
    die('<h2 style="font-family:sans-serif;padding:40px">Registration not found.</h2>');
}

// ── All admin users for assignment ────────────────────────────
$all_admins = $db->query("
    SELECT id, full_name FROM admin_users WHERE is_active = 1 ORDER BY full_name
")->fetch_all(MYSQLI_ASSOC);

// ── Fetch activities ──────────────────────────────────────────
$act_stmt = $db->prepare("
    SELECT la.*, a.full_name AS by_name
    FROM   lead_activities la
    JOIN   admin_users a ON la.admin_id = a.id
    WHERE  la.reg_id = ?
    ORDER  BY la.created_at DESC
");
$act_stmt->bind_param('i', $id);
$act_stmt->execute();
$activities = $act_stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$act_stmt->close();

// ── Fetch status logs ─────────────────────────────────────────
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

// ── Prev / Next ───────────────────────────────────────────────
$prev = $db->query("SELECT id FROM registrations WHERE id < $id ORDER BY id DESC LIMIT 1")->fetch_assoc();
$next = $db->query("SELECT id FROM registrations WHERE id > $id ORDER BY id ASC  LIMIT 1")->fetch_assoc();

// ── Handle POST actions ───────────────────────────────────────
$success_msg = '';
$error_msg   = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verify_csrf($_POST['csrf_token'] ?? '')) {
        $error_msg = 'Invalid security token. Please refresh and try again.';
    } elseif ($admin['role'] === 'viewer') {
        $error_msg = 'You have view-only access.';
    } else {
        $action = clean($_POST['_action'] ?? '');

        // ── Update status + priority + note ──────────────────
        if ($action === 'update_status') {
            $new_status   = clean($_POST['new_status']  ?? '');
            $new_priority = clean($_POST['new_priority'] ?? 'normal');
            $note         = clean($_POST['admin_note']  ?? '');

            if (!in_array($new_status, all_statuses(), true)) {
                $error_msg = 'Invalid status.';
            } else {
                $old_status = $reg['status'];

                $upd = $db->prepare("
                    UPDATE registrations
                    SET status = ?, priority = ?, admin_note = ?, reviewed_by = ?, reviewed_at = NOW()
                    WHERE id = ?
                ");
                $upd->bind_param('sssii', $new_status, $new_priority, $note, $admin['id'], $id);
                $upd->execute(); $upd->close();

                if ($old_status !== $new_status) {
                    $log = $db->prepare("
                        INSERT INTO status_logs (reg_id, changed_by, old_status, new_status, note)
                        VALUES (?, ?, ?, ?, ?)
                    ");
                    $log->bind_param('iisss', $id, $admin['id'], $old_status, $new_status, $note);
                    $log->execute(); $log->close();
                }

                $reg['status']           = $new_status;
                $reg['priority']         = $new_priority;
                $reg['admin_note']       = $note;
                $reg['reviewed_by_name'] = $admin['full_name'];
                $reg['reviewed_at']      = date('Y-m-d H:i:s');
                $success_msg = 'Status updated to <strong>' . ucfirst($new_status) . '</strong>.';

                // Re-fetch logs
                $logs = $db->query("
                    SELECT l.old_status, l.new_status, l.note, l.changed_at, a.full_name AS changed_by
                    FROM status_logs l JOIN admin_users a ON l.changed_by = a.id
                    WHERE l.reg_id = $id ORDER BY l.changed_at DESC
                ")->fetch_all(MYSQLI_ASSOC);
            }
        }

        // ── Assign lead ───────────────────────────────────────
        elseif ($action === 'assign') {
            $assign_to = $_POST['assign_to'] === '' ? null : (int)$_POST['assign_to'];
            $upd = $db->prepare("UPDATE registrations SET assigned_to = ? WHERE id = ?");
            $upd->bind_param('ii', $assign_to, $id);
            $upd->execute(); $upd->close();

            // Log as activity
            $assign_name = 'Unassigned';
            if ($assign_to) {
                foreach ($all_admins as $adm) {
                    if ((int)$adm['id'] === $assign_to) { $assign_name = $adm['full_name']; break; }
                }
            }
            $note_text = "Lead assigned to: $assign_name";
            $act = $db->prepare("
                INSERT INTO lead_activities (reg_id, admin_id, type, content)
                VALUES (?, ?, 'note', ?)
            ");
            $act->bind_param('iis', $id, $admin['id'], $note_text);
            $act->execute(); $act->close();

            $reg['assigned_to_id']   = $assign_to;
            $reg['assigned_to_name'] = $assign_to ? $assign_name : null;
            $success_msg = 'Lead assigned to ' . htmlspecialchars($assign_name) . '.';

            // Re-fetch activities
            $activities = $db->query("
                SELECT la.*, a.full_name AS by_name FROM lead_activities la
                JOIN admin_users a ON la.admin_id = a.id
                WHERE la.reg_id = $id ORDER BY la.created_at DESC
            ")->fetch_all(MYSQLI_ASSOC);
        }

        // ── Add activity ──────────────────────────────────────
        elseif ($action === 'add_activity') {
            $type        = clean($_POST['activity_type'] ?? 'note');
            $content     = clean($_POST['activity_content'] ?? '');
            $scheduled   = clean($_POST['follow_up_at'] ?? '') ?: null;

            $allowed_types = ['note','call','whatsapp','email','meeting','follow_up'];
            if (!in_array($type, $allowed_types)) $type = 'note';

            if (trim($content) === '') {
                $error_msg = 'Activity content cannot be empty.';
            } else {
                $act = $db->prepare("
                    INSERT INTO lead_activities (reg_id, admin_id, type, content, scheduled_at)
                    VALUES (?, ?, ?, ?, ?)
                ");
                $act->bind_param('iisss', $id, $admin['id'], $type, $content, $scheduled);
                $act->execute(); $act->close();
                $success_msg = 'Activity logged.';

                // Re-fetch
                $activities = $db->query("
                    SELECT la.*, a.full_name AS by_name FROM lead_activities la
                    JOIN admin_users a ON la.admin_id = a.id
                    WHERE la.reg_id = $id ORDER BY la.created_at DESC
                ")->fetch_all(MYSQLI_ASSOC);
            }
        }

        // ── Quick approve / reject ────────────────────────────
        elseif (in_array($action, ['quick_approve', 'quick_reject'])) {
            $new_status = $action === 'quick_approve' ? 'approved' : 'rejected';
            $old_status = $reg['status'];

            $upd = $db->prepare("
                UPDATE registrations SET status = ?, reviewed_by = ?, reviewed_at = NOW() WHERE id = ?
            ");
            $upd->bind_param('sii', $new_status, $admin['id'], $id);
            $upd->execute(); $upd->close();

            $log = $db->prepare("
                INSERT INTO status_logs (reg_id, changed_by, old_status, new_status, note)
                VALUES (?, ?, ?, ?, ?)
            ");
            $quick_note = 'Quick action from lead view.';
            $log->bind_param('iisss', $id, $admin['id'], $old_status, $new_status, $quick_note);
            $log->execute(); $log->close();

            $reg['status'] = $new_status;
            $success_msg   = 'Lead <strong>' . $new_status . '</strong> successfully.';

            $logs = $db->query("
                SELECT l.old_status, l.new_status, l.note, l.changed_at, a.full_name AS changed_by
                FROM status_logs l JOIN admin_users a ON l.changed_by = a.id
                WHERE l.reg_id = $id ORDER BY l.changed_at DESC
            ")->fetch_all(MYSQLI_ASSOC);
        }
    }
}

// ── Helpers ───────────────────────────────────────────────────
$activity_icons = [
    'note'      => '📝',
    'call'      => '📞',
    'whatsapp'  => '💬',
    'email'     => '📧',
    'meeting'   => '📅',
    'follow_up' => '⏰',
    'status'    => '🔄',
];
$activity_labels = [
    'note'      => 'Note added',
    'call'      => 'Call logged',
    'whatsapp'  => 'WhatsApp sent',
    'email'     => 'Email sent',
    'meeting'   => 'Meeting scheduled',
    'follow_up' => 'Follow-up set',
    'status'    => 'Status change',
];

$csrf = csrf_token();
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

<div class="sidebar-overlay" id="sidebarOverlay" onclick="closeSidebar()"></div>

<div class="admin-layout">

<!-- ── Sidebar ── -->
<aside class="sidebar" id="sidebar">
  <div class="sidebar-logo">
    <div class="sidebar-logo-icon">📋</div>
    <div>
      <div class="sidebar-logo-text">DMA Admin</div>
      <div class="sidebar-logo-sub">Lead Detail</div>
    </div>
  </div>
  <div class="sidebar-section">
    <ul class="sidebar-nav">
      <li><a href="/admin/dashboard.php"><span class="nav-icon">← </span> Back to Dashboard</a></li>
      <?php if ($prev): ?>
      <li><a href="/admin/view.php?id=<?= $prev['id'] ?>"><span class="nav-icon">‹</span> Previous Lead</a></li>
      <?php endif; ?>
      <?php if ($next): ?>
      <li><a href="/admin/view.php?id=<?= $next['id'] ?>"><span class="nav-icon">›</span> Next Lead</a></li>
      <?php endif; ?>
    </ul>
  </div>
  <!-- Status quick links -->
  <div class="sidebar-section">
    <div class="sidebar-section-label">Quick Status</div>
    <?php if ($admin['role'] !== 'viewer'): ?>
    <ul class="sidebar-nav">
      <?php foreach (all_statuses() as $s): ?>
      <li>
        <form method="POST" style="margin:0;padding:0;">
          <input type="hidden" name="csrf_token" value="<?= $csrf ?>">
          <input type="hidden" name="_action" value="update_status">
          <input type="hidden" name="new_status" value="<?= $s ?>">
          <input type="hidden" name="new_priority" value="<?= clean($reg['priority'] ?? 'normal') ?>">
          <input type="hidden" name="admin_note" value="<?= htmlspecialchars($reg['admin_note'] ?? '') ?>">
          <button type="submit" style="width:100%;text-align:left;background:none;border:none;cursor:pointer;
            padding:10px 12px;border-radius:8px;color:var(--sidebar-text);font-size:0.9rem;font-weight:500;
            display:flex;align-items:center;gap:10px;transition:all 0.2s;
            <?= $reg['status'] === $s ? 'background:rgba(255,68,68,0.15);color:#ff4444;' : '' ?>"
            onmouseover="this.style.background='rgba(255,255,255,0.06)'"
            onmouseout="this.style.background='<?= $reg['status'] === $s ? 'rgba(255,68,68,0.15)' : '' ?>'">
            <span>
              <?= match($s) {
                'pending'  => '⏳',
                'reviewed' => '👁',
                'approved' => '✅',
                'rejected' => '❌',
                'waitlist' => '📋',
                default    => '•'
              } ?>
            </span>
            <?= ucfirst($s) ?>
          </button>
        </form>
      </li>
      <?php endforeach; ?>
    </ul>
    <?php endif; ?>
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

<!-- ── Main ── -->
<div class="main-wrap">

  <div class="topbar">
    <button class="topbar-hamburger" onclick="openSidebar()">☰</button>
    <div class="topbar-breadcrumb">
      <a href="/admin/dashboard.php">Dashboard</a>
      <span class="sep">/</span>
      <span><?= clean($reg['full_name']) ?></span>
    </div>
    <div class="topbar-actions">
      <?php if ($prev): ?><a href="/admin/view.php?id=<?= $prev['id'] ?>" class="btn btn-ghost btn-sm">‹ Prev</a><?php endif; ?>
      <?php if ($next): ?><a href="/admin/view.php?id=<?= $next['id'] ?>" class="btn btn-ghost btn-sm">Next ›</a><?php endif; ?>
    </div>
  </div>

  <div class="page-content">

    <?php if ($success_msg): ?>
    <div class="alert alert-success"><span class="alert-icon">✅</span><?= $success_msg ?></div>
    <?php endif; ?>
    <?php if ($error_msg): ?>
    <div class="alert alert-error"><span class="alert-icon">⚠️</span><?= htmlspecialchars($error_msg) ?></div>
    <?php endif; ?>

    <!-- ── Lead Header ── -->
    <div class="lead-header">
      <?php if ($reg['photo_path']): ?>
      <img src="<?= clean($reg['photo_path']) ?>" class="lead-photo"
        alt="<?= clean($reg['full_name']) ?>"
        onclick="openPhotoModal(this.src)" title="Click to enlarge">
      <?php else: ?>
      <div class="lead-photo-placeholder">👤</div>
      <?php endif; ?>

      <div class="lead-header-info">
        <div class="lead-name"><?= clean($reg['full_name']) ?></div>
        <div class="lead-meta">
          <code style="font-family:var(--mono);font-size:0.8rem;background:var(--bg);padding:2px 8px;border-radius:4px;">
            <?= clean($reg['reg_code']) ?>
          </code>
          <span class="badge badge-<?= clean($reg['status']) ?> badge-lg"><?= ucfirst(clean($reg['status'])) ?></span>
          <?php if (!empty($reg['priority']) && $reg['priority'] !== 'normal'): ?>
          <span class="priority-dot <?= clean($reg['priority']) ?>"><?= ucfirst(clean($reg['priority'])) ?></span>
          <?php endif; ?>
          <?php if ($reg['assigned_to_name']): ?>
          <div class="assign-pill">
            <div class="assign-dot"><?= strtoupper(substr($reg['assigned_to_name'], 0, 1)) ?></div>
            <?= clean($reg['assigned_to_name']) ?>
          </div>
          <?php endif; ?>
        </div>
        <div class="lead-actions">
          <!-- WhatsApp -->
          <a href="https://wa.me/<?= preg_replace('/\D/','',$reg['phone']) ?>"
             target="_blank" class="btn btn-whatsapp always-show">
            💬 WhatsApp
          </a>
          <!-- Call -->
          <a href="tel:<?= clean($reg['phone']) ?>" class="btn btn-success always-show">
            📞 <?= clean($reg['phone']) ?>
          </a>
          <?php if ($reg['email']): ?>
          <a href="mailto:<?= clean($reg['email']) ?>" class="btn btn-outline">
            📧 Email
          </a>
          <?php endif; ?>
          <?php if ($reg['fb_profile']): ?>
          <a href="<?= clean($reg['fb_profile']) ?>" target="_blank" class="btn btn-outline">
            FB Profile ↗
          </a>
          <?php endif; ?>
          <?php if ($admin['role'] !== 'viewer' && !in_array($reg['status'], ['approved','rejected'])): ?>
          <form method="POST" style="display:inline;">
            <input type="hidden" name="csrf_token" value="<?= $csrf ?>">
            <input type="hidden" name="_action" value="quick_approve">
            <button class="btn btn-success" onclick="return confirm('Approve this applicant?')">✅ Approve</button>
          </form>
          <form method="POST" style="display:inline;">
            <input type="hidden" name="csrf_token" value="<?= $csrf ?>">
            <input type="hidden" name="_action" value="quick_reject">
            <button class="btn btn-danger" onclick="return confirm('Reject this applicant?')">❌ Reject</button>
          </form>
          <?php endif; ?>
        </div>
      </div>
    </div>

    <!-- ── 2-column layout ── -->
    <div class="view-grid">

      <!-- LEFT: Info + Activity Timeline -->
      <div class="view-main">

        <!-- Personal Info card -->
        <div class="card" style="margin-bottom:16px;">
          <div class="card-header">
            <div class="card-title">👤 Personal Information</div>
          </div>
          <div class="card-body">
            <div class="info-section">
              <div class="info-grid">
                <div class="info-row"><span>Full Name</span><strong><?= clean($reg['full_name']) ?></strong></div>
                <div class="info-row"><span>Phone</span>
                  <strong><a href="tel:<?= clean($reg['phone']) ?>"><?= clean($reg['phone']) ?></a></strong>
                </div>
                <div class="info-row"><span>Email</span>
                  <strong><?= $reg['email'] ? '<a href="mailto:'.clean($reg['email']).'">'.clean($reg['email']).'</a>' : '—' ?></strong>
                </div>
                <div class="info-row"><span>Date of Birth</span><strong><?= date('d M Y', strtotime($reg['dob'])) ?></strong></div>
                <div class="info-row"><span>Age</span><strong><?= (int)$reg['age'] ?> years</strong></div>
                <div class="info-row"><span>Gender</span><strong><?= ucfirst(clean($reg['gender'])) ?></strong></div>
                <div class="info-row"><span>Height</span><strong><?= (int)$reg['height_cm'] ?> cm</strong></div>
                <div class="info-row"><span>Weight</span><strong><?= $reg['weight_kg'] ? (int)$reg['weight_kg'].' kg' : '—' ?></strong></div>
                <div class="info-row"><span>Skin Tone</span><strong><?= $reg['skin_tone'] ? ucfirst(clean($reg['skin_tone'])) : '—' ?></strong></div>
                <div class="info-row"><span>Experience</span><strong><?= ucfirst(clean($reg['experience'])) ?></strong></div>
              </div>
            </div>
            <div class="info-section">
              <h4>📍 Location</h4>
              <div class="info-grid">
                <div class="info-row"><span>District</span><strong><?= clean($reg['district'] ?? '—') ?></strong></div>
                <div class="info-row"><span>Address</span><strong><?= $reg['address'] ? clean($reg['address']) : '—' ?></strong></div>
              </div>
            </div>
            <div class="info-section">
              <h4>🌐 Social & Source</h4>
              <div class="info-grid">
                <div class="info-row"><span>Facebook</span>
                  <strong><?= $reg['fb_profile'] ? '<a href="'.clean($reg['fb_profile']).'" target="_blank">View Profile ↗</a>' : '—' ?></strong>
                </div>
                <div class="info-row"><span>How Heard</span><strong><?= ucfirst(clean($reg['how_heard'] ?? '—')) ?></strong></div>
              </div>
            </div>
            <div class="info-section">
              <h4>🗂 Submission</h4>
              <div class="info-grid">
                <div class="info-row"><span>Registered</span><strong><?= date('d M Y, h:i A', strtotime($reg['created_at'])) ?></strong></div>
                <div class="info-row"><span>IP Address</span><strong class="mono"><?= clean($reg['ip_address'] ?? '—') ?></strong></div>
                <?php if ($reg['reviewed_by_name']): ?>
                <div class="info-row"><span>Reviewed by</span><strong><?= clean($reg['reviewed_by_name']) ?></strong></div>
                <div class="info-row"><span>Reviewed at</span><strong><?= date('d M Y, h:i A', strtotime($reg['reviewed_at'])) ?></strong></div>
                <?php endif; ?>
              </div>
            </div>
          </div>
        </div>

        <!-- Activity Timeline -->
        <div class="card">
          <div class="card-header">
            <div class="card-title">📋 Activity Timeline</div>
            <span style="font-size:0.8rem;color:var(--text-muted);"><?= count($activities) ?> entries</span>
          </div>

          <?php if (empty($activities)): ?>
          <div style="padding:32px;text-align:center;color:var(--text-muted);font-size:0.9rem;">
            No activities logged yet. Use the form below to add a note or log a call.
          </div>
          <?php else: ?>
          <div class="activity-list">
            <?php foreach ($activities as $act): ?>
            <div class="activity-item">
              <div class="activity-icon <?= clean($act['type']) ?>">
                <?= $activity_icons[$act['type']] ?? '📝' ?>
              </div>
              <div class="activity-body">
                <div class="activity-header">
                  <span class="activity-who">
                    <?= $activity_labels[$act['type']] ?? ucfirst(clean($act['type'])) ?>
                    <span style="font-weight:400;color:var(--text-muted)">by <?= clean($act['by_name']) ?></span>
                  </span>
                  <span class="activity-time">
                    <?= date('d M, h:i A', strtotime($act['created_at'])) ?>
                  </span>
                </div>
                <div class="activity-text"><?= nl2br(htmlspecialchars($act['content'])) ?></div>
                <?php if ($act['scheduled_at']): ?>
                <div class="activity-followup-badge">
                  ⏰ Follow-up: <?= date('d M Y, h:i A', strtotime($act['scheduled_at'])) ?>
                </div>
                <?php endif; ?>
              </div>
            </div>
            <?php endforeach; ?>
          </div>
          <?php endif; ?>

          <!-- Add Activity Form -->
          <?php if ($admin['role'] !== 'viewer'): ?>
          <div class="add-activity-form">
            <form method="POST">
              <input type="hidden" name="csrf_token" value="<?= $csrf ?>">
              <input type="hidden" name="_action" value="add_activity">
              <input type="hidden" name="activity_type" id="activityTypeInput" value="note">

              <div class="activity-type-tabs">
                <?php foreach ([
                  'note'      => '📝 Note',
                  'call'      => '📞 Call',
                  'whatsapp'  => '💬 WhatsApp',
                  'email'     => '📧 Email',
                  'meeting'   => '📅 Meeting',
                  'follow_up' => '⏰ Follow-up',
                ] as $t => $label): ?>
                <button type="button" class="activity-tab <?= $t === 'note' ? 'active' : '' ?>"
                        onclick="setActivityType('<?= $t ?>', this)">
                  <?= $label ?>
                </button>
                <?php endforeach; ?>
              </div>

              <textarea name="activity_content" class="form-control"
                        rows="3" placeholder="Write a note, log a call result, schedule a follow-up…"
                        required style="margin-bottom:10px;"></textarea>

              <div id="followUpDateWrap" style="display:none;margin-bottom:10px;">
                <label class="form-label">Follow-up Date & Time</label>
                <input type="datetime-local" name="follow_up_at" class="form-control">
              </div>

              <button type="submit" class="btn btn-primary btn-sm">Log Activity</button>
            </form>
          </div>
          <?php endif; ?>
        </div>

      </div><!-- /view-main -->

      <!-- RIGHT SIDEBAR -->
      <div class="view-sidebar">

        <!-- Status & Priority card -->
        <?php if ($admin['role'] !== 'viewer'): ?>
        <div class="sidebar-card">
          <div class="sidebar-card-header">🎯 Status & Priority</div>
          <div class="sidebar-card-body">
            <form method="POST">
              <input type="hidden" name="csrf_token" value="<?= $csrf ?>">
              <input type="hidden" name="_action" value="update_status">

              <div class="form-group">
                <label class="form-label">Status</label>
                <select name="new_status" class="form-control">
                  <?php foreach (all_statuses() as $s): ?>
                  <option value="<?= $s ?>" <?= $reg['status'] === $s ? 'selected' : '' ?>>
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
                <label class="form-label">Lead Priority</label>
                <select name="new_priority" class="form-control">
                  <option value="normal" <?= ($reg['priority']??'') === 'normal' ? 'selected':'' ?>>Normal</option>
                  <option value="hot"    <?= ($reg['priority']??'') === 'hot'    ? 'selected':'' ?>>🔥 Hot — Act now</option>
                  <option value="warm"   <?= ($reg['priority']??'') === 'warm'   ? 'selected':'' ?>>🌤 Warm — Follow up</option>
                  <option value="cold"   <?= ($reg['priority']??'') === 'cold'   ? 'selected':'' ?>>❄️ Cold — Low interest</option>
                </select>
              </div>

              <div class="form-group">
                <label class="form-label">Internal Note</label>
                <textarea name="admin_note" class="form-control" rows="3"
                          placeholder="Private note (not shown to applicant)"><?= htmlspecialchars($reg['admin_note'] ?? '') ?></textarea>
              </div>

              <button type="submit" class="btn btn-primary btn-full">Save Changes</button>
            </form>
          </div>
        </div>
        <?php else: ?>
        <div class="sidebar-card">
          <div class="sidebar-card-header">Status</div>
          <div class="sidebar-card-body">
            <span class="badge badge-<?= clean($reg['status']) ?> badge-lg"><?= ucfirst($reg['status']) ?></span>
            <?php if ($reg['admin_note']): ?>
            <p style="margin-top:12px;font-size:0.875rem;color:var(--text-2);"><?= nl2br(clean($reg['admin_note'])) ?></p>
            <?php endif; ?>
          </div>
        </div>
        <?php endif; ?>

        <!-- Assignment card -->
        <?php if ($admin['role'] !== 'viewer'): ?>
        <div class="sidebar-card">
          <div class="sidebar-card-header">👤 Assignment</div>
          <div class="sidebar-card-body">
            <?php if ($reg['assigned_to_name']): ?>
            <div class="assign-pill" style="margin-bottom:14px;">
              <div class="assign-dot"><?= strtoupper(substr($reg['assigned_to_name'], 0, 1)) ?></div>
              <?= clean($reg['assigned_to_name']) ?>
            </div>
            <?php else: ?>
            <div style="font-size:0.85rem;color:var(--text-muted);margin-bottom:14px;">Not assigned yet</div>
            <?php endif; ?>
            <form method="POST">
              <input type="hidden" name="csrf_token" value="<?= $csrf ?>">
              <input type="hidden" name="_action" value="assign">
              <div class="form-group" style="margin-bottom:10px;">
                <label class="form-label">Assign to</label>
                <div class="assign-select-wrap">
                  <?php if ($reg['assigned_to_name']): ?>
                  <div class="assign-avatar-prefix"><?= strtoupper(substr($reg['assigned_to_name'], 0, 1)) ?></div>
                  <?php endif; ?>
                  <select name="assign_to" class="form-control">
                    <option value="">— Unassign</option>
                    <?php foreach ($all_admins as $adm): ?>
                    <option value="<?= $adm['id'] ?>" <?= (int)($reg['assigned_to_id'] ?? 0) === (int)$adm['id'] ? 'selected' : '' ?>>
                      <?= clean($adm['full_name']) ?>
                    </option>
                    <?php endforeach; ?>
                  </select>
                </div>
              </div>
              <button type="submit" class="btn btn-outline btn-sm btn-full">Update Assignment</button>
            </form>
          </div>
        </div>
        <?php endif; ?>

        <!-- Status History -->
        <div class="sidebar-card">
          <div class="sidebar-card-header">🔄 Status History
            <span style="font-size:0.75rem;color:var(--text-muted);"><?= count($logs) ?> changes</span>
          </div>
          <?php if (empty($logs)): ?>
          <div style="padding:16px 18px;font-size:0.85rem;color:var(--text-muted);">No status changes yet.</div>
          <?php else: ?>
          <div class="audit-log">
            <?php foreach ($logs as $log): ?>
            <div class="audit-item">
              <div class="audit-change">
                <span class="badge badge-<?= clean($log['old_status'] ?? 'pending') ?>"><?= ucfirst($log['old_status'] ?? 'new') ?></span>
                →
                <span class="badge badge-<?= clean($log['new_status']) ?>"><?= ucfirst($log['new_status']) ?></span>
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

      </div><!-- /view-sidebar -->
    </div><!-- /view-grid -->

  </div><!-- /page-content -->
</div><!-- /main-wrap -->
</div><!-- /admin-layout -->

<!-- ── Mobile bottom action bar ── -->
<div class="mobile-action-bar">
  <a href="https://wa.me/<?= preg_replace('/\D/','',$reg['phone']) ?>"
     target="_blank" class="btn btn-whatsapp btn-lg" style="flex:1;">💬 WhatsApp</a>
  <a href="tel:<?= clean($reg['phone']) ?>" class="btn btn-success btn-lg" style="flex:1;">📞 Call</a>
  <?php if ($admin['role'] !== 'viewer' && !in_array($reg['status'], ['approved','rejected'])): ?>
  <form method="POST" style="flex:1">
    <input type="hidden" name="csrf_token" value="<?= $csrf ?>">
    <input type="hidden" name="_action" value="quick_approve">
    <button class="btn btn-primary btn-lg btn-full" onclick="return confirm('Approve this applicant?')">✅</button>
  </form>
  <?php endif; ?>
</div>
<!-- Photo Modal -->
<div id="photoModal" onclick="closePhotoModal()"
     style="display:none;position:fixed;inset:0;background:rgba(0,0,0,0.85);
            z-index:9999;align-items:center;justify-content:center;cursor:zoom-out;">
  <img id="photoModalImg" src="" alt="Full photo"
       style="max-width:90vw;max-height:90vh;border-radius:12px;
              box-shadow:0 8px 40px rgba(0,0,0,0.6);">
  <button onclick="closePhotoModal()"
          style="position:absolute;top:20px;right:24px;background:rgba(255,255,255,0.15);
                 border:none;color:#fff;font-size:1.5rem;border-radius:50%;
                 width:40px;height:40px;cursor:pointer;line-height:1;">✕</button>
</div>

<script>
function openSidebar() {
  document.getElementById('sidebar').classList.add('open');
  document.getElementById('sidebarOverlay').classList.add('active');
}
function closeSidebar() {
  document.getElementById('sidebar').classList.remove('open');
  document.getElementById('sidebarOverlay').classList.remove('active');
}

function setActivityType(type, btn) {
  document.getElementById('activityTypeInput').value = type;
  document.querySelectorAll('.activity-tab').forEach(t => t.classList.remove('active'));
  btn.classList.add('active');
  document.getElementById('followUpDateWrap').style.display =
    (type === 'follow_up') ? 'block' : 'none';
}
function openPhotoModal(src) {
  document.getElementById('photoModalImg').src = src;
  const m = document.getElementById('photoModal');
  m.style.display = 'flex';
  document.body.style.overflow = 'hidden';   // prevent background scroll
}
function closePhotoModal() {
  document.getElementById('photoModal').style.display = 'none';
  document.body.style.overflow = '';
}
// Also close on Escape key
document.addEventListener('keydown', e => {
  if (e.key === 'Escape') closePhotoModal();
});
</script>
</body>
</html>