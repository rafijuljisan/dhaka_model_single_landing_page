<?php
// ============================================================
// admin/users.php  — Admin User Management (superadmin only)
// ============================================================

require_once __DIR__ . '/../../core/includes/auth.php';
require_once __DIR__ . '/../../core/includes/functions.php';

require_role('superadmin');

$db      = db();
$current = current_admin();
$success = '';
$error   = '';
$edit_id = (int)($_GET['edit'] ?? 0);

// ── Handle POST ───────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verify_csrf($_POST['csrf_token'] ?? '')) {
        $error = 'Invalid request token. Please refresh the page.';
    } else {
        $post_action = clean($_POST['action'] ?? '');

        // ── Create ────────────────────────────────────────────
        if ($post_action === 'create') {
            $username  = clean($_POST['username']  ?? '');
            $full_name = clean($_POST['full_name'] ?? '');
            $email     = clean($_POST['email']     ?? '');
            $password  = $_POST['password']        ?? '';
            $role      = clean($_POST['role']      ?? 'viewer');

            if (strlen($username) < 3)      $error = 'Username must be at least 3 characters.';
            elseif (!is_valid_email($email)) $error = 'Invalid email address.';
            elseif (strlen($password) < 8)  $error = 'Password must be at least 8 characters.';
            elseif (!in_array($role, ['admin','viewer','superadmin'])) $error = 'Invalid role.';
            else {
                $hash = password_hash($password, PASSWORD_BCRYPT, ['cost' => 12]);
                $stmt = $db->prepare("
                    INSERT INTO admin_users (username, password, full_name, email, role)
                    VALUES (?, ?, ?, ?, ?)
                ");
                $stmt->bind_param('sssss', $username, $hash, $full_name, $email, $role);
                if ($stmt->execute()) {
                    $success = "User <strong>$username</strong> created successfully.";
                } else {
                    $error = str_contains($stmt->error, 'Duplicate')
                           ? "Username <strong>$username</strong> is already taken."
                           : 'Database error: ' . $stmt->error;
                }
                $stmt->close();
                $edit_id = 0; // reset form
            }
        }

        // ── Update ────────────────────────────────────────────
        elseif ($post_action === 'update') {
            $uid       = (int)($_POST['user_id'] ?? 0);
            $full_name = clean($_POST['full_name'] ?? '');
            $email     = clean($_POST['email']     ?? '');
            $role      = clean($_POST['role']      ?? 'viewer');
            $password  = $_POST['password']        ?? '';
            $is_active = isset($_POST['is_active']) ? 1 : 0;

            if ($uid === (int)$current['id'] && ($role !== 'superadmin' || !$is_active)) {
                $error = 'You cannot change your own role or deactivate yourself.';
            } elseif (!is_valid_email($email)) {
                $error = 'Invalid email address.';
            } elseif (!in_array($role, ['admin','viewer','superadmin'])) {
                $error = 'Invalid role.';
            } else {
                $stmt = $db->prepare("
                    UPDATE admin_users SET full_name=?, email=?, role=?, is_active=? WHERE id=?
                ");
                $stmt->bind_param('sssii', $full_name, $email, $role, $is_active, $uid);
                $stmt->execute(); $stmt->close();

                if (!empty($password)) {
                    if (strlen($password) < 8) {
                        $error = 'Password must be at least 8 characters.';
                    } else {
                        $hash = password_hash($password, PASSWORD_BCRYPT, ['cost' => 12]);
                        $stmt = $db->prepare("UPDATE admin_users SET password=? WHERE id=?");
                        $stmt->bind_param('si', $hash, $uid);
                        $stmt->execute(); $stmt->close();
                    }
                }
                if (!$error) {
                    $success  = 'User updated successfully.';
                    $edit_id  = 0;
                }
            }
        }

        // ── Toggle active ─────────────────────────────────────
        elseif ($post_action === 'toggle') {
            $uid = (int)($_POST['user_id'] ?? 0);
            if ($uid === (int)$current['id']) {
                $error = 'You cannot deactivate yourself.';
            } else {
                $db->query("UPDATE admin_users SET is_active = NOT is_active WHERE id = $uid");
                $success = 'User status toggled.';
            }
        }
    }
}

// ── Fetch users ───────────────────────────────────────────────
$users = $db->query("
    SELECT u.*,
           (SELECT COUNT(*) FROM registrations WHERE assigned_to = u.id) AS lead_count
    FROM admin_users u
    ORDER BY FIELD(role,'superadmin','admin','viewer'), full_name
")->fetch_all(MYSQLI_ASSOC);

// Editing?
$edit_user = null;
if ($edit_id) {
    foreach ($users as $u) {
        if ((int)$u['id'] === $edit_id) { $edit_user = $u; break; }
    }
}

$csrf = csrf_token();
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Manage Users – DMA Admin</title>
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
      <div class="sidebar-logo-sub">User Management</div>
    </div>
  </div>
  <div class="sidebar-section">
    <ul class="sidebar-nav">
      <li><a href="/admin/dashboard.php"><span class="nav-icon">←</span> Dashboard</a></li>
      <li><a href="/admin/users.php" class="active"><span class="nav-icon">👥</span> Users</a></li>
      <li><a href="/admin/settings.php"><span class="nav-icon">⚙️</span> Settings</a></li>
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
      <span>Users</span>
    </div>
  </div>

  <div class="page-content">

    <?php if ($success): ?>
    <div class="alert alert-success"><span class="alert-icon">✅</span><?= $success ?></div>
    <?php endif; ?>
    <?php if ($error): ?>
    <div class="alert alert-error"><span class="alert-icon">⚠️</span><?= $error ?></div>
    <?php endif; ?>

    <div class="settings-grid">

      <!-- ── User Table ── -->
      <div>
        <div class="table-wrap">
          <div class="table-header">
            <div class="card-title">👥 Admin Users (<?= count($users) ?>)</div>
            <a href="/admin/users.php" class="btn btn-primary btn-sm">+ New User</a>
          </div>

          <!-- Desktop table -->
          <table class="data-table">
            <thead>
              <tr>
                <th>User</th>
                <th>Role</th>
                <th>Leads</th>
                <th>Last Login</th>
                <th>Status</th>
                <th>Actions</th>
              </tr>
            </thead>
            <tbody>
            <?php foreach ($users as $u): ?>
            <tr class="<?= !$u['is_active'] ? 'row-inactive' : '' ?>">
              <td>
                <div style="display:flex;align-items:center;gap:10px;">
                  <div style="width:36px;height:36px;border-radius:50%;background:var(--red);
                    color:white;display:flex;align-items:center;justify-content:center;
                    font-weight:700;font-size:0.85rem;flex-shrink:0;">
                    <?= strtoupper(substr($u['full_name'], 0, 1)) ?>
                  </div>
                  <div>
                    <div style="font-weight:700;"><?= clean($u['full_name']) ?></div>
                    <div style="font-size:0.75rem;color:var(--text-muted);">@<?= clean($u['username']) ?></div>
                    <div style="font-size:0.75rem;color:var(--text-muted);"><?= clean($u['email']) ?></div>
                  </div>
                </div>
              </td>
              <td><span class="role-badge role-<?= $u['role'] ?>"><?= $u['role'] ?></span></td>
              <td>
                <?php if ($u['lead_count'] > 0): ?>
                <a href="/admin/dashboard.php?assigned=<?= $u['id'] ?>"
                   style="font-weight:700;color:var(--red);"><?= $u['lead_count'] ?> leads</a>
                <?php else: ?>
                <span style="color:var(--text-muted);">—</span>
                <?php endif; ?>
              </td>
              <td style="font-size:0.8rem;color:var(--text-muted);">
                <?= $u['last_login'] ? date('d M Y, h:i A', strtotime($u['last_login'])) : 'Never' ?>
              </td>
              <td>
                <?php if ($u['is_active']): ?>
                <span class="badge badge-approved">Active</span>
                <?php else: ?>
                <span class="badge badge-rejected">Inactive</span>
                <?php endif; ?>
              </td>
              <td>
                <div class="td-actions">
                  <a href="/admin/users.php?edit=<?= $u['id'] ?>" class="btn btn-outline btn-sm">Edit</a>
                  <?php if ((int)$u['id'] !== (int)$current['id']): ?>
                  <form method="POST" style="display:inline;">
                    <input type="hidden" name="csrf_token" value="<?= $csrf ?>">
                    <input type="hidden" name="action"    value="toggle">
                    <input type="hidden" name="user_id"   value="<?= $u['id'] ?>">
                    <button type="submit"
                            class="btn btn-sm <?= $u['is_active'] ? 'btn-danger' : 'btn-success' ?>"
                            onclick="return confirm('Toggle this user\'s access?')">
                      <?= $u['is_active'] ? 'Disable' : 'Enable' ?>
                    </button>
                  </form>
                  <?php endif; ?>
                </div>
              </td>
            </tr>
            <?php endforeach; ?>
            </tbody>
          </table>
        </div>

        <!-- Mobile user cards -->
        <div class="lead-cards" style="margin-top:16px;">
          <?php foreach ($users as $u): ?>
          <div class="lead-card" style="pointer-events:none;">
            <div style="width:48px;height:48px;border-radius:50%;background:var(--red);color:white;
              display:flex;align-items:center;justify-content:center;font-weight:700;flex-shrink:0;">
              <?= strtoupper(substr($u['full_name'], 0, 1)) ?>
            </div>
            <div class="lead-card-body">
              <div class="lead-card-name">
                <?= clean($u['full_name']) ?>
                <?php if (!$u['is_active']): ?>
                <span style="font-size:0.75rem;color:#ef4444;"> (Inactive)</span>
                <?php endif; ?>
              </div>
              <div class="lead-card-sub">@<?= clean($u['username']) ?> · <?= clean($u['email']) ?></div>
              <div class="lead-card-footer">
                <span class="role-badge role-<?= $u['role'] ?>"><?= $u['role'] ?></span>
                <?php if ($u['lead_count'] > 0): ?>
                <a href="/admin/dashboard.php?assigned=<?= $u['id'] ?>"
                   style="font-size:0.78rem;color:var(--red);font-weight:700;pointer-events:auto;">
                  <?= $u['lead_count'] ?> leads →
                </a>
                <?php endif; ?>
                <div style="display:flex;gap:6px;pointer-events:auto;">
                  <a href="/admin/users.php?edit=<?= $u['id'] ?>" class="btn btn-outline btn-sm">Edit</a>
                  <?php if ((int)$u['id'] !== (int)$current['id']): ?>
                  <form method="POST">
                    <input type="hidden" name="csrf_token" value="<?= $csrf ?>">
                    <input type="hidden" name="action"  value="toggle">
                    <input type="hidden" name="user_id" value="<?= $u['id'] ?>">
                    <button class="btn btn-sm <?= $u['is_active'] ? 'btn-danger' : 'btn-success' ?>"
                            onclick="return confirm('Toggle this user?')">
                      <?= $u['is_active'] ? 'Disable' : 'Enable' ?>
                    </button>
                  </form>
                  <?php endif; ?>
                </div>
              </div>
            </div>
          </div>
          <?php endforeach; ?>
        </div>
      </div>

      <!-- ── Create / Edit form ── -->
      <div>
        <div class="sidebar-card" style="position:sticky;top:80px;">
          <div class="sidebar-card-header">
            <?= $edit_user ? '✏️ Edit: ' . clean($edit_user['full_name']) : '➕ New User' ?>
            <?php if ($edit_user): ?>
            <a href="/admin/users.php" class="btn btn-ghost btn-sm">Cancel</a>
            <?php endif; ?>
          </div>
          <div class="sidebar-card-body">
            <form method="POST" action="/admin/users.php">
              <input type="hidden" name="csrf_token" value="<?= $csrf ?>">
              <input type="hidden" name="action"    value="<?= $edit_user ? 'update' : 'create' ?>">
              <?php if ($edit_user): ?>
              <input type="hidden" name="user_id" value="<?= $edit_user['id'] ?>">
              <?php endif; ?>

              <?php if (!$edit_user): ?>
              <div class="form-group">
                <label class="form-label">Username <span style="color:var(--red)">*</span></label>
                <input type="text" name="username" class="form-control"
                       required minlength="3" placeholder="e.g. reviewer1"
                       autocomplete="username">
                <div style="font-size:0.75rem;color:var(--text-muted);margin-top:4px;">Min 3 characters. Cannot be changed later.</div>
              </div>
              <?php endif; ?>

              <div class="form-group">
                <label class="form-label">Full Name <span style="color:var(--red)">*</span></label>
                <input type="text" name="full_name" class="form-control" required
                       value="<?= htmlspecialchars($edit_user['full_name'] ?? '') ?>"
                       placeholder="Full name">
              </div>

              <div class="form-group">
                <label class="form-label">Email <span style="color:var(--red)">*</span></label>
                <input type="email" name="email" class="form-control" required
                       value="<?= htmlspecialchars($edit_user['email'] ?? '') ?>"
                       placeholder="email@example.com">
              </div>

              <div class="form-group">
                <label class="form-label">
                  Password <?= $edit_user ? '<span style="font-weight:400;text-transform:none;letter-spacing:0;">(leave blank to keep)</span>' : '<span style="color:var(--red)">*</span>' ?>
                </label>
                <input type="password" name="password" class="form-control"
                       minlength="8" <?= $edit_user ? '' : 'required' ?>
                       placeholder="Min 8 characters"
                       autocomplete="new-password">
              </div>

              <div class="form-group">
                <label class="form-label">Role <span style="color:var(--red)">*</span></label>
                <select name="role" class="form-control">
                  <option value="viewer"     <?= ($edit_user['role'] ?? 'viewer') === 'viewer'     ? 'selected':'' ?>>Viewer — Read only</option>
                  <option value="admin"      <?= ($edit_user['role'] ?? '') === 'admin'             ? 'selected':'' ?>>Admin — Full access</option>
                  <option value="superadmin" <?= ($edit_user['role'] ?? '') === 'superadmin'        ? 'selected':'' ?>>Superadmin — + User mgmt</option>
                </select>
              </div>

              <?php if ($edit_user): ?>
              <div class="form-group">
                <label style="display:flex;align-items:center;gap:8px;cursor:pointer;">
                  <input type="checkbox" name="is_active" value="1"
                         <?= $edit_user['is_active'] ? 'checked' : '' ?>
                         style="width:16px;height:16px;accent-color:var(--red);">
                  <span style="font-size:0.875rem;font-weight:600;">Active Account</span>
                </label>
              </div>
              <?php endif; ?>

              <button type="submit" class="btn btn-primary btn-full" style="margin-top:8px;">
                <?= $edit_user ? '💾 Update User' : '➕ Create User' ?>
              </button>
            </form>
          </div>
        </div>

        <!-- Role guide card -->
        <div class="card" style="margin-top:16px;padding:18px;">
          <div style="font-size:0.8rem;font-weight:700;text-transform:uppercase;letter-spacing:0.05em;
               color:var(--text-muted);margin-bottom:12px;">Role Permissions</div>
          <div style="display:flex;flex-direction:column;gap:10px;">
            <?php foreach ([
              ['viewer',     '👁',  'View-only — Can see leads, no edits'],
              ['admin',      '🔧',  'Full access — Can update status, assign, log activities'],
              ['superadmin', '🔑',  'Everything + manage users and settings'],
            ] as [$r, $icon, $desc]): ?>
            <div style="display:flex;gap:10px;align-items:flex-start;">
              <span><?= $icon ?></span>
              <div>
                <span class="role-badge role-<?= $r ?>"><?= $r ?></span>
                <div style="font-size:0.8rem;color:var(--text-muted);margin-top:3px;"><?= $desc ?></div>
              </div>
            </div>
            <?php endforeach; ?>
          </div>
        </div>
      </div>

    </div><!-- /settings-grid -->
  </div><!-- /page-content -->
</div><!-- /main-wrap -->
</div><!-- /admin-layout -->

<script>
function openSidebar()  {
  document.getElementById('sidebar').classList.add('open');
  document.getElementById('sidebarOverlay').classList.add('active');
}
function closeSidebar() {
  document.getElementById('sidebar').classList.remove('open');
  document.getElementById('sidebarOverlay').classList.remove('active');
}
</script>
</body>
</html>