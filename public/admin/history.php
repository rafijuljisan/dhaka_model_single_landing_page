<?php
// ============================================================
// admin/history.php  — Activity & Status Logs
// ============================================================

require_once __DIR__ . '/../../core/includes/auth.php';
require_once __DIR__ . '/../../core/includes/functions.php';

require_login();

$admin = current_admin();
$db    = db();

// Pagination
$per_page     = 50;
$current_page = max(1, (int)($_GET['page'] ?? 1));
$offset       = ($current_page - 1) * $per_page;

// Count total
$total_rows = (int)$db->query("SELECT COUNT(*) AS c FROM status_logs")->fetch_assoc()['c'];
$total_pages = max(1, (int)ceil($total_rows / $per_page));

// Fetch logs
$logs = $db->query("
    SELECT
        l.changed_at,
        l.old_status,
        l.new_status,
        l.note,
        u.username   AS admin_username,
        u.full_name  AS admin_name,
        r.full_name  AS applicant_name,
        r.reg_code,
        r.id         AS reg_id
    FROM   status_logs l
    LEFT JOIN admin_users   u ON l.changed_by = u.id
    LEFT JOIN registrations r ON l.reg_id     = r.id
    ORDER BY l.changed_at DESC
    LIMIT $per_page OFFSET $offset
")->fetch_all(MYSQLI_ASSOC);
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Activity History – DMA Admin</title>
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
      <div class="sidebar-logo-sub">Activity History</div>
    </div>
  </div>
  <div class="sidebar-section">
    <ul class="sidebar-nav">
      <li><a href="/admin/dashboard.php"><span class="nav-icon">←</span> Dashboard</a></li>
      <li><a href="/admin/history.php" class="active"><span class="nav-icon">🕓</span> History</a></li>
      <?php if ($admin['role'] === 'superadmin'): ?>
      <li><a href="/admin/users.php"><span class="nav-icon">👥</span> Users</a></li>
      <li><a href="/admin/settings.php"><span class="nav-icon">⚙️</span> Settings</a></li>
      <?php endif; ?>
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

<!-- ── Main ── -->
<div class="main-wrap">

  <div class="topbar">
    <button class="topbar-hamburger" onclick="openSidebar()">☰</button>
    <div class="topbar-breadcrumb">
      <a href="/admin/dashboard.php">Dashboard</a>
      <span class="sep">/</span>
      <span>Activity History</span>
    </div>
    <div class="topbar-actions">
      <span style="font-size:0.8rem;color:var(--text-muted);">
        <?= number_format($total_rows) ?> total entries
      </span>
    </div>
  </div>

  <div class="page-content">

    <div class="table-wrap">

      <div class="table-header">
        <div class="card-title">🕓 Status Change Log</div>
        <div class="table-meta">
          Page <strong><?= $current_page ?></strong> of <strong><?= $total_pages ?></strong>
        </div>
      </div>

      <!-- Desktop table -->
      <table class="data-table">
        <thead>
          <tr>
            <th>Date & Time</th>
            <th>Admin</th>
            <th>Applicant</th>
            <th>Status Change</th>
            <th>Note</th>
          </tr>
        </thead>
        <tbody>
        <?php if (empty($logs)): ?>
          <tr><td colspan="5" class="no-data">No activity logs yet.</td></tr>
        <?php else: foreach ($logs as $log): ?>
          <tr>
            <td>
              <div style="font-size:0.85rem;font-weight:600;">
                <?= date('d M Y', strtotime($log['changed_at'])) ?>
              </div>
              <div style="font-size:0.75rem;color:var(--text-muted);">
                <?= date('h:i A', strtotime($log['changed_at'])) ?>
              </div>
            </td>
            <td>
              <div style="font-weight:600;font-size:0.875rem;">
                <?= clean($log['admin_name'] ?? 'System') ?>
              </div>
              <div style="font-size:0.75rem;color:var(--text-muted);">
                @<?= clean($log['admin_username'] ?? '—') ?>
              </div>
            </td>
            <td>
              <?php if ($log['reg_id']): ?>
              <a href="/admin/view.php?id=<?= (int)$log['reg_id'] ?>"
                 style="font-weight:700;color:var(--red);">
                <?= clean($log['applicant_name'] ?? 'Unknown') ?>
              </a>
              <?php else: ?>
              <span style="color:var(--text-muted);"><?= clean($log['applicant_name'] ?? 'Deleted') ?></span>
              <?php endif; ?>
              <div style="font-size:0.75rem;color:var(--text-muted);font-family:var(--mono);">
                <?= clean($log['reg_code'] ?? '—') ?>
              </div>
            </td>
            <td>
              <div style="display:flex;align-items:center;gap:8px;flex-wrap:wrap;">
                <?php if ($log['old_status']): ?>
                <span class="badge badge-<?= clean($log['old_status']) ?>">
                  <?= ucfirst(clean($log['old_status'])) ?>
                </span>
                <span style="color:var(--text-muted);">→</span>
                <?php endif; ?>
                <span class="badge badge-<?= clean($log['new_status']) ?>">
                  <?= ucfirst(clean($log['new_status'])) ?>
                </span>
              </div>
            </td>
            <td style="font-size:0.85rem;color:var(--text-muted);max-width:220px;">
              <?= $log['note'] ? nl2br(clean($log['note'])) : '—' ?>
            </td>
          </tr>
        <?php endforeach; endif; ?>
        </tbody>
      </table>

      <!-- Mobile cards -->
      <div style="display:none;" class="history-mobile-list">
        <?php foreach ($logs as $log): ?>
        <div style="padding:14px 16px;border-bottom:1px solid var(--border);">
          <div style="display:flex;justify-content:space-between;margin-bottom:6px;">
            <div style="display:flex;align-items:center;gap:6px;flex-wrap:wrap;">
              <?php if ($log['old_status']): ?>
              <span class="badge badge-<?= clean($log['old_status']) ?>"><?= ucfirst(clean($log['old_status'])) ?></span>
              <span style="color:var(--text-muted);">→</span>
              <?php endif; ?>
              <span class="badge badge-<?= clean($log['new_status']) ?>"><?= ucfirst(clean($log['new_status'])) ?></span>
            </div>
            <span style="font-size:0.75rem;color:var(--text-muted);">
              <?= date('d M, h:i A', strtotime($log['changed_at'])) ?>
            </span>
          </div>
          <?php if ($log['reg_id']): ?>
          <a href="/admin/view.php?id=<?= (int)$log['reg_id'] ?>"
             style="font-weight:700;font-size:0.9rem;color:var(--red);">
            <?= clean($log['applicant_name'] ?? 'Unknown') ?>
          </a>
          <?php endif; ?>
          <div style="font-size:0.78rem;color:var(--text-muted);margin-top:2px;">
            by <?= clean($log['admin_name'] ?? 'System') ?>
            <?= $log['note'] ? '· ' . clean(mb_substr($log['note'], 0, 60)) . (mb_strlen($log['note']) > 60 ? '…' : '') : '' ?>
          </div>
        </div>
        <?php endforeach; ?>
      </div>

    </div><!-- /table-wrap -->

    <!-- Pagination -->
    <?php if ($total_pages > 1): ?>
    <div class="pagination">
      <?php if ($current_page > 1): ?>
      <a href="?page=<?= $current_page - 1 ?>">&laquo;</a>
      <?php endif; ?>
      <?php
      $start = max(1, $current_page - 2);
      $end   = min($total_pages, $current_page + 2);
      if ($start > 1) echo '<span>…</span>';
      for ($p = $start; $p <= $end; $p++):
      ?>
      <a href="?page=<?= $p ?>" class="<?= $p === $current_page ? 'active' : '' ?>"><?= $p ?></a>
      <?php endfor;
      if ($end < $total_pages) echo '<span>…</span>';
      ?>
      <?php if ($current_page < $total_pages): ?>
      <a href="?page=<?= $current_page + 1 ?>">&raquo;</a>
      <?php endif; ?>
    </div>
    <?php endif; ?>

  </div><!-- /page-content -->
</div><!-- /main-wrap -->
</div><!-- /admin-layout -->

<style>
@media (max-width: 768px) {
  .data-table { display: none; }
  .history-mobile-list { display: block !important; }
}
</style>

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