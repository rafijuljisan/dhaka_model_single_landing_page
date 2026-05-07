<?php
// ============================================================
// admin/users.php  — Admin User Management (superadmin only)
// ============================================================

require_once __DIR__ . '/../../core/includes/auth.php';
require_once __DIR__ . '/../../core/includes/functions.php';

require_role('superadmin');

$db      = db();
$success = '';
$error   = '';
$action  = clean($_GET['action'] ?? '');
$edit_id = (int)($_GET['edit'] ?? 0);

// ── Handle POST ───────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verify_csrf($_POST['csrf_token'] ?? '')) {
        $error = 'Invalid request token.';
    } else {
        $post_action = clean($_POST['action'] ?? '');

        // ── Create new admin user ──────────────────────────
        if ($post_action === 'create') {
            $username  = clean($_POST['username'] ?? '');
            $full_name = clean($_POST['full_name'] ?? '');
            $email     = clean($_POST['email'] ?? '');
            $password  = $_POST['password'] ?? '';
            $role      = clean($_POST['role'] ?? 'viewer');

            if (strlen($username) < 3)        { $error = 'Username must be at least 3 characters.'; }
            elseif (!is_valid_email($email))   { $error = 'Invalid email address.'; }
            elseif (strlen($password) < 8)    { $error = 'Password must be at least 8 characters.'; }
            elseif (!in_array($role, ['admin','viewer','superadmin'])) { $error = 'Invalid role.'; }
            else {
                $hash = password_hash($password, PASSWORD_BCRYPT, ['cost' => 12]);
                $stmt = $db->prepare("
                    INSERT INTO admin_users (username, password, full_name, email, role)
                    VALUES (?, ?, ?, ?, ?)
                ");
                $stmt->bind_param('sssss', $username, $hash, $full_name, $email, $role);
                if ($stmt->execute()) {
                    $success = "User '{$username}' created successfully.";
                } else {
                    $error = str_contains($stmt->error, 'Duplicate')
                           ? "Username '{$username}' is already taken."
                           : 'Database error: ' . $stmt->error;
                }
                $stmt->close();
            }
        }

        // ── Update existing user ───────────────────────────
        elseif ($post_action === 'update') {
            $uid       = (int)($_POST['user_id'] ?? 0);
            $full_name = clean($_POST['full_name'] ?? '');
            $email     = clean($_POST['email'] ?? '');
            $role      = clean($_POST['role'] ?? 'viewer');
            $password  = $_POST['password'] ?? '';
            $is_active = isset($_POST['is_active']) ? 1 : 0;

            // Prevent superadmin from demoting/deactivating themselves
            if ($uid === (int)current_admin()['id'] && ($role !== 'superadmin' || !$is_active)) {
                $error = 'You cannot change your own role or deactivate yourself.';
            } elseif (!is_valid_email($email)) {
                $error = 'Invalid email address.';
            } elseif (!in_array($role, ['admin','viewer','superadmin'])) {
                $error = 'Invalid role.';
            } else {
                // Update base fields
                $stmt = $db->prepare("
                    UPDATE admin_users
                    SET full_name = ?, email = ?, role = ?, is_active = ?
                    WHERE id = ?
                ");
                $stmt->bind_param('sssii', $full_name, $email, $role, $is_active, $uid);
                $stmt->execute();
                $stmt->close();

                // Update password only if provided
                if (!empty($password)) {
                    if (strlen($password) < 8) {
                        $error = 'Password must be at least 8 characters.';
                    } else {
                        $hash  = password_hash($password, PASSWORD_BCRYPT, ['cost' => 12]);
                        $stmt  = $db->prepare("UPDATE admin_users SET password = ? WHERE id = ?");
                        $stmt->bind_param('si', $hash, $uid);
                        $stmt->execute();
                        $stmt->close();
                    }
                }

                if (!$error) $success = 'User updated successfully.';
            }
        }

        // ── Toggle active status ───────────────────────────
        elseif ($post_action === 'toggle') {
            $uid = (int)($_POST['user_id'] ?? 0);
            if ($uid === (int)current_admin()['id']) {
                $error = 'You cannot deactivate yourself.';
            } else {
                $db->query("UPDATE admin_users SET is_active = NOT is_active WHERE id = $uid");
                $success = 'User status toggled.';
            }
        }
    }
}

// ── Fetch all users ──────────────────────────────────────────
$users = $db->query("SELECT * FROM admin_users ORDER BY role DESC, id ASC")->fetch_all(MYSQLI_ASSOC);

// If editing — fetch that user
$edit_user = null;
if ($edit_id) {
    foreach ($users as $u) {
        if ((int)$u['id'] === $edit_id) { $edit_user = $u; break; }
    }
}
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

<?php include __DIR__ . '/../../core/admin_partials/navbar.php'; ?>

<div class="container">
  <div class="page-header">
    <h1>👥 Admin Users</h1>
  </div>

  <?php if ($success): ?><div class="alert alert-success"><?= htmlspecialchars($success) ?></div><?php endif; ?>
  <?php if ($error):   ?><div class="alert alert-error"><?= htmlspecialchars($error) ?></div><?php endif; ?>

  <div class="settings-grid">

    <!-- ── User List ── -->
    <div class="settings-main">
      <div class="table-wrap">
      <table class="data-table">
        <thead>
          <tr>
            <th>Username</th>
            <th>Full Name</th>
            <th>Email</th>
            <th>Role</th>
            <th>Last Login</th>
            <th>Status</th>
            <th>Actions</th>
          </tr>
        </thead>
        <tbody>
        <?php foreach ($users as $u): ?>
          <tr class="<?= !$u['is_active'] ? 'row-inactive' : '' ?>">
            <td><strong><?= clean($u['username']) ?></strong></td>
            <td><?= clean($u['full_name']) ?></td>
            <td><?= clean($u['email']) ?></td>
            <td><span class="role-badge role-<?= $u['role'] ?>"><?= $u['role'] ?></span></td>
            <td><?= $u['last_login'] ? date('d M Y', strtotime($u['last_login'])) : '—' ?></td>
            <td>
              <?php if ($u['is_active']): ?>
                <span class="badge badge-success">Active</span>
              <?php else: ?>
                <span class="badge badge-danger">Inactive</span>
              <?php endif; ?>
            </td>
            <td>
              <a href="/admin/users.php?edit=<?= $u['id'] ?>" class="btn btn-xs btn-outline">Edit</a>
              <?php if ((int)$u['id'] !== (int)current_admin()['id']): ?>
              <form method="POST" style="display:inline">
                <input type="hidden" name="csrf_token" value="<?= csrf_token() ?>">
                <input type="hidden" name="action"    value="toggle">
                <input type="hidden" name="user_id"   value="<?= $u['id'] ?>">
                <button type="submit" class="btn btn-xs <?= $u['is_active'] ? 'btn-danger' : 'btn-success' ?>"
                        onclick="return confirm('Toggle this user?')">
                  <?= $u['is_active'] ? 'Disable' : 'Enable' ?>
                </button>
              </form>
              <?php endif; ?>
            </td>
          </tr>
        <?php endforeach; ?>
        </tbody>
      </table>
      </div>
    </div>

    <!-- ── Create / Edit Form ── -->
    <div class="settings-sidebar">
      <div class="sidebar-card">
        <h3><?= $edit_user ? '✏ Edit User' : '➕ New User' ?></h3>
        <form method="POST" action="/admin/users.php">
          <input type="hidden" name="csrf_token" value="<?= csrf_token() ?>">
          <input type="hidden" name="action"    value="<?= $edit_user ? 'update' : 'create' ?>">
          <?php if ($edit_user): ?>
          <input type="hidden" name="user_id"   value="<?= $edit_user['id'] ?>">
          <?php endif; ?>

          <?php if (!$edit_user): ?>
          <div class="form-group">
            <label>Username</label>
            <input type="text" name="username" required minlength="3"
                   placeholder="e.g. reviewer1">
          </div>
          <?php endif; ?>

          <div class="form-group">
            <label>Full Name</label>
            <input type="text" name="full_name" required
                   value="<?= htmlspecialchars($edit_user['full_name'] ?? '') ?>">
          </div>

          <div class="form-group">
            <label>Email</label>
            <input type="email" name="email" required
                   value="<?= htmlspecialchars($edit_user['email'] ?? '') ?>">
          </div>

          <div class="form-group">
            <label>Password <?= $edit_user ? '(leave blank to keep current)' : '' ?></label>
            <input type="password" name="password" minlength="8"
                   <?= $edit_user ? '' : 'required' ?>
                   placeholder="Min 8 characters">
          </div>

          <div class="form-group">
            <label>Role</label>
            <select name="role">
              <?php foreach (['viewer','admin','superadmin'] as $r): ?>
              <option value="<?= $r ?>" <?= ($edit_user['role'] ?? '') === $r ? 'selected' : '' ?>>
                <?= ucfirst($r) ?>
              </option>
              <?php endforeach; ?>
            </select>
            <small>viewer = read-only · admin = full · superadmin = user management</small>
          </div>

          <?php if ($edit_user): ?>
          <div class="form-group">
            <label class="checkbox-label">
              <input type="checkbox" name="is_active" value="1"
                     <?= $edit_user['is_active'] ? 'checked' : '' ?>>
              Active Account
            </label>
          </div>
          <?php endif; ?>

          <button type="submit" class="btn btn-primary btn-full">
            <?= $edit_user ? 'Update User' : 'Create User' ?>
          </button>
          <?php if ($edit_user): ?>
          <a href="/admin/users.php" class="btn btn-outline btn-full" style="margin-top:8px;">Cancel</a>
          <?php endif; ?>
        </form>
      </div>
    </div>

  </div>
</div>
</body>
</html>
