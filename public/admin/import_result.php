<?php
require_once __DIR__ . '/../../core/includes/auth.php';
require_once __DIR__ . '/../../core/includes/functions.php';
require_login();

if (session_status() === PHP_SESSION_NONE) session_start();
$result = $_SESSION['import_result'] ?? null;
unset($_SESSION['import_result']);
if (!$result) redirect('/admin/import.php');

$admin = current_admin();

$total        = $result['inserted'] + $result['skipped'];
$success_rate = $total > 0 ? round(($result['inserted'] / $total) * 100) : 0;
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Import Results – DMA Admin</title>
<link rel="stylesheet" href="/assets/admin.css">
</head>
<body>

<!-- ── Sidebar Overlay (mobile) ── -->
<div class="sidebar-overlay" id="sidebarOverlay" onclick="closeSidebar()"></div>

<div class="admin-layout">

<!-- ════════════════════════════════════════════════════════
     SIDEBAR
════════════════════════════════════════════════════════ -->
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
      <li><a href="/admin/manual_entry.php">
        <span class="nav-icon">✏️</span> Add Lead Manually
      </a></li>
      <li><a href="/admin/import.php" class="active">
        <span class="nav-icon">📥</span> Import CSV
      </a></li>
    </ul>
  </div>

  <?php if ($admin['role'] !== 'superadmin'): ?>
  <div class="sidebar-section">
    <ul class="sidebar-nav">
      <li><a href="/admin/dashboard.php?assigned=<?= $admin['id'] ?>">
        <span class="nav-icon">🎯</span> My Leads
      </a></li>
    </ul>
  </div>
  <?php endif; ?>

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

<!-- ════════════════════════════════════════════════════════
     MAIN
════════════════════════════════════════════════════════ -->
<div class="main-wrap">

  <!-- Top bar -->
  <div class="topbar">
    <button class="topbar-hamburger" id="hamburgerBtn" onclick="openSidebar()">☰</button>
    <div class="topbar-breadcrumb">
      <span><a href="/admin/import.php" style="color:inherit;text-decoration:none;">Import CSV</a></span>
      <span class="sep">/</span>
      <span>Results</span>
    </div>
    <div class="topbar-actions">
      <a href="/admin/import.php" class="btn btn-outline btn-sm">📥 Import Another</a>
      <a href="/admin/dashboard.php" class="btn btn-primary btn-sm">📊 Dashboard</a>
    </div>
  </div>

  <div class="page-content">

    <div class="card" style="max-width:700px;">
      <div class="card-header">
        <div class="card-title">📊 Import Results</div>
      </div>
      <div class="card-body">

        <!-- Summary Stats -->
        <div style="display:grid;grid-template-columns:1fr 1fr;gap:16px;margin-bottom:24px;">
          <div style="background:#ecfdf5;border:1px solid #6ee7b7;border-radius:var(--radius);
                      padding:20px;text-align:center;">
            <div style="font-size:2rem;font-weight:800;color:#065f46;">
              <?= (int)$result['inserted'] ?>
            </div>
            <div style="font-size:0.85rem;color:#065f46;font-weight:600;">Leads Imported</div>
          </div>
          <div style="background:#fef2f2;border:1px solid #fca5a5;border-radius:var(--radius);
                      padding:20px;text-align:center;">
            <div style="font-size:2rem;font-weight:800;color:#991b1b;">
              <?= (int)$result['skipped'] ?>
            </div>
            <div style="font-size:0.85rem;color:#991b1b;font-weight:600;">Rows Skipped</div>
          </div>
        </div>

        <!-- Success Rate Bar -->
        <?php if ($total > 0): ?>
        <div style="margin-bottom:24px;">
          <div style="display:flex;justify-content:space-between;align-items:center;
                      margin-bottom:6px;font-size:0.82rem;font-weight:600;color:var(--text-2);">
            <span>Success Rate</span>
            <span><?= $success_rate ?>% (<?= (int)$result['inserted'] ?> of <?= $total ?> rows)</span>
          </div>
          <div style="background:var(--bg);border-radius:999px;height:8px;overflow:hidden;">
            <div style="width:<?= $success_rate ?>%;height:100%;
                        background:linear-gradient(90deg,#34d399,#10b981);
                        border-radius:999px;"></div>
          </div>
        </div>
        <?php endif; ?>

        <!-- All rows skipped notice -->
        <?php if ($result['inserted'] === 0 && $total > 0): ?>
        <div style="background:#fffbeb;border:1px solid #fcd34d;border-radius:var(--radius);
                    padding:14px;margin-bottom:20px;font-size:0.85rem;color:#92400e;">
          ⚠️ No rows were imported. Please check your CSV format and required columns
          (<code>full_name, phone, dob, gender, height_cm</code>) and try again.
        </div>
        <?php endif; ?>

        <!-- All rows imported notice -->
        <?php if ($result['skipped'] === 0 && $result['inserted'] > 0): ?>
        <div style="background:#ecfdf5;border:1px solid #6ee7b7;border-radius:var(--radius);
                    padding:14px;margin-bottom:20px;font-size:0.85rem;color:#065f46;">
          ✅ All rows imported successfully with no errors.
        </div>
        <?php endif; ?>

        <!-- Error / Skip Log -->
        <?php if (!empty($result['errors'])): ?>
        <div style="margin-bottom:20px;">
          <div style="font-size:0.85rem;font-weight:700;margin-bottom:8px;color:var(--text-2);">
            ⚠️ Skip reasons:
          </div>
          <div style="background:var(--bg);border-radius:var(--radius);padding:14px;
                      max-height:200px;overflow-y:auto;font-size:0.8rem;
                      font-family:var(--mono);color:var(--text-2);line-height:1.8;">
            <?php foreach ($result['errors'] as $e): ?>
            <div><?= htmlspecialchars($e) ?></div>
            <?php endforeach; ?>
          </div>
        </div>
        <?php endif; ?>

        <!-- Action Buttons -->
        <div style="display:flex;gap:10px;">
          <a href="/admin/dashboard.php" class="btn btn-primary">View Dashboard</a>
          <a href="/admin/import.php" class="btn btn-outline">Import Another File</a>
        </div>

      </div>
    </div>

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
</script>
</body>
</html>