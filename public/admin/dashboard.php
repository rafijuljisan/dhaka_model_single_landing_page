<?php
// ============================================================
// admin/dashboard.php  — Enhanced Lead Management Dashboard
// ============================================================

require_once __DIR__ . '/../../core/includes/auth.php';
require_once __DIR__ . '/../../core/includes/functions.php';

require_login();

$db    = db();
$admin = current_admin();

// ── Stats ────────────────────────────────────────────────────
$stats = $db->query("
    SELECT
        COUNT(*)                                           AS total,
        SUM(status = 'pending')                            AS pending,
        SUM(status = 'reviewed')                           AS reviewed,
        SUM(status = 'approved')                           AS approved,
        SUM(status = 'rejected')                           AS rejected,
        SUM(status = 'waitlist')                           AS waitlist,
        SUM(gender  = 'male')                              AS male,
        SUM(gender  = 'female')                            AS female,
        SUM(DATE(created_at) = CURDATE())                  AS today,
        SUM(created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)) AS this_week,
        SUM(priority = 'hot')                              AS hot_leads,
        SUM(assigned_to IS NULL AND status = 'pending')    AS unassigned_pending
    FROM registrations
")->fetch_assoc();

$max_reg   = (int) get_setting('max_registrations', '500');
$fill_pct  = $max_reg > 0 ? min(100, round(($stats['total'] / $max_reg) * 100)) : 0;

// ── All admin users for assignment filter + bulk assign ──────
$all_admins = $db->query("
    SELECT id, full_name, username FROM admin_users WHERE is_active = 1 ORDER BY full_name
")->fetch_all(MYSQLI_ASSOC);

// ── Filters ──────────────────────────────────────────────────
$allowed_status   = ['', 'pending', 'reviewed', 'approved', 'rejected', 'waitlist'];
$allowed_gender   = ['', 'male', 'female', 'other'];
$allowed_priority = ['', 'hot', 'warm', 'cold', 'normal'];
$allowed_sort     = ['created_at', 'full_name', 'age', 'height_cm', 'status', 'priority'];

$filter_status   = in_array($_GET['status']   ?? '', $allowed_status)   ? ($_GET['status']   ?? '') : '';
$filter_gender   = in_array($_GET['gender']   ?? '', $allowed_gender)   ? ($_GET['gender']   ?? '') : '';
$filter_priority = in_array($_GET['priority'] ?? '', $allowed_priority) ? ($_GET['priority'] ?? '') : '';
$filter_followup = clean($_GET['followup_date'] ?? '');
$filter_search   = clean($_GET['search']   ?? '');
$filter_date     = clean($_GET['date']     ?? '');
$filter_assigned = (int)($_GET['assigned'] ?? 0);
$filter_sort     = in_array($_GET['sort']  ?? '', $allowed_sort) ? ($_GET['sort'] ?? 'created_at') : 'created_at';
$filter_order    = ($_GET['order'] ?? 'desc') === 'asc' ? 'ASC' : 'DESC';
$per_page        = 30;
$current_page    = max(1, (int)($_GET['page'] ?? 1));

// ── Build WHERE ──────────────────────────────────────────────
$where   = [];
$params  = [];
$types   = '';

if ($filter_status   !== '') { $where[] = 'r.status = ?';   $params[] = $filter_status;   $types .= 's'; }
if ($filter_gender   !== '') { $where[] = 'r.gender = ?';   $params[] = $filter_gender;   $types .= 's'; }
if ($filter_priority !== '') { $where[] = 'r.priority = ?'; $params[] = $filter_priority; $types .= 's'; }
if ($filter_search   !== '') {
    $where[]  = '(r.full_name LIKE ? OR r.phone LIKE ? OR r.reg_code LIKE ? OR r.email LIKE ?)';
    $like     = '%' . $filter_search . '%';
    $params   = array_merge($params, [$like, $like, $like, $like]);
    $types   .= 'ssss';
}
if ($filter_followup !== '') {
    $where[]  = 'EXISTS (
        SELECT 1 FROM lead_activities la
        WHERE la.reg_id      = r.id
          AND la.type        = "follow_up"
          AND DATE(la.scheduled_at) = ?
    )';
    $params[] = $filter_followup;
    $types   .= 's';
}
if ($filter_date !== '') { $where[] = 'DATE(r.created_at) = ?'; $params[] = $filter_date; $types .= 's'; }
if ($filter_assigned > 0) {
    $where[] = 'r.assigned_to = ?';
    $params[] = $filter_assigned;
    $types   .= 'i';
} elseif ($filter_assigned === -1) {
    $where[] = 'r.assigned_to IS NULL';
}

$where_sql = $where ? 'WHERE ' . implode(' AND ', $where) : '';

// ── Count ────────────────────────────────────────────────────
$count_stmt = $db->prepare("SELECT COUNT(*) AS c FROM registrations r $where_sql");
if ($params) $count_stmt->bind_param($types, ...$params);
$count_stmt->execute();
$total_rows = (int)$count_stmt->get_result()->fetch_assoc()['c'];
$count_stmt->close();

$pager = paginate($total_rows, $per_page, $current_page);

// ── Fetch rows ───────────────────────────────────────────────
$data_sql = "
    SELECT r.id, r.reg_code, r.full_name, r.phone, r.email, r.age,
           r.gender, r.height_cm, r.district, r.status, r.priority,
           r.photo_path, r.created_at,
           r.assigned_to,
           au.full_name AS assigned_name,
           au.username  AS assigned_username
    FROM registrations r
    LEFT JOIN admin_users au ON r.assigned_to = au.id
    $where_sql
    ORDER BY
        CASE WHEN r.priority = 'hot'  THEN 1
             WHEN r.priority = 'warm' THEN 2
             WHEN r.priority = 'cold' THEN 3
             ELSE 4 END,
        r.{$filter_sort} $filter_order
    LIMIT ? OFFSET ?
";
$data_stmt  = $db->prepare($data_sql);
$all_params = array_merge($params, [$per_page, $pager['offset']]);
$all_types  = $types . 'ii';
$data_stmt->bind_param($all_types, ...$all_params);
$data_stmt->execute();
$rows = $data_stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$data_stmt->close();

// ── Quick assign (AJAX-ish inline POST) ──────────────────────
$flash = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['quick_assign'])) {
    if (verify_csrf($_POST['csrf_token'] ?? '')) {
        $qa_id  = (int)($_POST['reg_id'] ?? 0);
        $qa_uid = $_POST['assign_to'] === '' ? 'NULL' : (int)$_POST['assign_to'];
        $db->query("UPDATE registrations SET assigned_to = $qa_uid WHERE id = $qa_id");
        $flash = 'Assignment updated.';
        header('Location: ' . $_SERVER['REQUEST_URI']);
        exit;
    }
}

function qs(array $overrides = []): string {
    $base = ['status','gender','priority','search','date','sort','order','assigned', 'followup_date'];
    $q    = [];
    foreach ($base as $k) {
        $v = $overrides[$k] ?? ($_GET[$k] ?? '');
        if ($v !== '' && $v !== '0') $q[$k] = $v;
    }
    if (isset($overrides['page'])) $q['page'] = $overrides['page'];
    return '?' . http_build_query($q);
}

function priority_label(string $p): string {
    return match($p) {
        'hot'  => '🔥 Hot',
        'warm' => '🌤 Warm',
        'cold' => '❄️ Cold',
        default => ''
    };
}

$csrf = csrf_token();
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Dashboard – DMA Admin</title>
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
      <li><a href="/admin/dashboard.php" class="active">
        <span class="nav-icon">📊</span> Dashboard
        <?php if ($stats['pending'] > 0): ?>
        <span class="nav-badge"><?= $stats['pending'] ?></span>
        <?php endif; ?>
      </a></li>
      <li><a href="/admin/export.php<?= qs() ?>">
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

  <!-- Quick filters in sidebar -->
  <div class="sidebar-section">
    <div class="sidebar-section-label">Quick Filters</div>
    <ul class="sidebar-nav">
      <li><a href="/admin/dashboard.php?status=pending">
        <span class="nav-icon">⏳</span> Pending
        <span class="nav-badge" style="background:#f59e0b"><?= $stats['pending'] ?></span>
      </a></li>
      <li><a href="/admin/dashboard.php?priority=hot">
        <span class="nav-icon">🔥</span> Hot Leads
        <span class="nav-badge"><?= $stats['hot_leads'] ?></span>
      </a></li>
      <li><a href="/admin/dashboard.php?status=approved">
        <span class="nav-icon">✅</span> Approved
        <span class="nav-badge" style="background:#10b981"><?= $stats['approved'] ?></span>
      </a></li>
      <li><a href="/admin/dashboard.php?assigned=-1&status=pending">
        <span class="nav-icon">👤</span> Unassigned
        <span class="nav-badge" style="background:#6b7280"><?= $stats['unassigned_pending'] ?></span>
      </a></li>
      <li><a href="/admin/dashboard.php?followup_date=<?= date('Y-m-d') ?>">
        <span class="nav-icon">⏰</span> Today's Follow-ups
      </a></li>
      <li><a href="/admin/manual_entry.php">
          <span class="nav-icon">✏️</span> Add Lead Manually
      </a></li>
      <li><a href="/admin/import.php">
          <span class="nav-icon">📥</span> Import CSV
      </a></li>
    </ul>
  </div>

  <!-- My leads (if admin) -->
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
      <span>Dashboard</span>
      <?php if ($filter_status || $filter_search || $filter_priority): ?>
      <span class="sep">/</span>
      <span>Filtered results</span>
      <?php endif; ?>
    </div>
    <div class="topbar-actions">
      <a href="/admin/export.php<?= qs() ?>" class="btn btn-outline btn-sm">⬇ Export</a>
      <a href="/admin/manual_entry.php" class="btn btn-primary btn-sm">✏️ Add Lead</a>
      <a href="/admin/import.php" class="btn btn-outline btn-sm">📥 Import</a>
      <?php if ($admin['role'] !== 'viewer'): ?>
      <a href="/admin/settings.php" class="btn btn-ghost btn-sm">⚙</a>
      <?php endif; ?>
    </div>
  </div>

  <div class="page-content">

    <?php if ($flash): ?>
    <div class="alert alert-success"><span class="alert-icon">✅</span><?= htmlspecialchars($flash) ?></div>
    <?php endif; ?>

    <!-- ── Quick stats pills (mobile) ── -->
    <div class="quick-stats">
      <a href="/admin/dashboard.php" class="quick-stat-pill <?= !$filter_status ? 'active' : '' ?>">
        <span class="num"><?= $stats['total'] ?></span> Total
      </a>
      <a href="/admin/dashboard.php?status=pending" class="quick-stat-pill" style="color:#f59e0b">
        <span class="num"><?= $stats['pending'] ?></span> Pending
      </a>
      <a href="/admin/dashboard.php?status=approved" class="quick-stat-pill" style="color:#10b981">
        <span class="num"><?= $stats['approved'] ?></span> Approved
      </a>
      <a href="/admin/dashboard.php?priority=hot" class="quick-stat-pill" style="color:#ef4444">
        🔥 <span class="num"><?= $stats['hot_leads'] ?></span> Hot
      </a>
      <a href="/admin/dashboard.php" class="quick-stat-pill">
        📅 <span class="num"><?= $stats['today'] ?></span> Today
      </a>
    </div>

    <!-- ── Stats grid (desktop) ── -->
    <div class="stats-grid">
      <div class="stat-card red">
        <div class="stat-top">
          <div class="stat-label">Total Registrations</div>
          <div class="stat-icon">📋</div>
        </div>
        <div class="stat-num"><?= number_format($stats['total']) ?></div>
        <div class="stat-sub"><?= $max_reg - $stats['total'] ?> slots remaining</div>
        <div class="stat-bar-wrap">
          <div class="stat-bar" style="width:<?= $fill_pct ?>%"></div>
        </div>
      </div>
      <div class="stat-card yellow">
        <div class="stat-top">
          <div class="stat-label">Pending Review</div>
          <div class="stat-icon">⏳</div>
        </div>
        <div class="stat-num"><?= $stats['pending'] ?></div>
        <div class="stat-sub"><?= $stats['unassigned_pending'] ?> unassigned</div>
      </div>
      <div class="stat-card green">
        <div class="stat-top">
          <div class="stat-label">Approved</div>
          <div class="stat-icon">✅</div>
        </div>
        <div class="stat-num"><?= $stats['approved'] ?></div>
        <div class="stat-sub">
          <?= $stats['total'] > 0 ? round(($stats['approved'] / $stats['total']) * 100) : 0 ?>% conversion
        </div>
      </div>
      <div class="stat-card">
        <div class="stat-top">
          <div class="stat-label">This Week</div>
          <div class="stat-icon">📅</div>
        </div>
        <div class="stat-num"><?= $stats['this_week'] ?></div>
        <div class="stat-sub"><?= $stats['today'] ?> today</div>
      </div>
      <div class="stat-card">
        <div class="stat-top">
          <div class="stat-label">Hot Leads</div>
          <div class="stat-icon">🔥</div>
        </div>
        <div class="stat-num"><?= $stats['hot_leads'] ?></div>
        <div class="stat-sub">Needs fast action</div>
      </div>
      <div class="stat-card">
        <div class="stat-top">
          <div class="stat-label">Female</div>
          <div class="stat-icon">👩</div>
        </div>
        <div class="stat-num"><?= $stats['female'] ?></div>
      </div>
      <div class="stat-card">
        <div class="stat-top">
          <div class="stat-label">Male</div>
          <div class="stat-icon">👨</div>
        </div>
        <div class="stat-num"><?= $stats['male'] ?></div>
      </div>
      <div class="stat-card">
        <div class="stat-top">
          <div class="stat-label">Rejected</div>
          <div class="stat-icon">❌</div>
        </div>
        <div class="stat-num"><?= $stats['rejected'] ?></div>
      </div>
    </div>

    <!-- ── Filters ── -->
    <form method="GET" action="/admin/dashboard.php" class="filter-bar" id="filterForm">

      <div class="filter-group">
        <label class="filter-label">Search</label>
        <div class="filter-search-wrap">
          <span class="filter-search-icon">🔍</span>
          <input type="text" name="search" placeholder="Name, phone, reg code…"
                value="<?= htmlspecialchars($filter_search) ?>">
        </div>
      </div>

      <div class="filter-group">
        <label class="filter-label">Status</label>
        <select name="status">
          <option value="">All</option>
          <?php foreach (all_statuses() as $s): ?>
          <option value="<?= $s ?>" <?= $filter_status === $s ? 'selected' : '' ?>><?= ucfirst($s) ?></option>
          <?php endforeach; ?>
        </select>
      </div>

      <div class="filter-group">
        <label class="filter-label">Priority</label>
        <select name="priority">
          <option value="">All</option>
          <option value="hot"    <?= $filter_priority === 'hot'    ? 'selected' : '' ?>>🔥 Hot</option>
          <option value="warm"   <?= $filter_priority === 'warm'   ? 'selected' : '' ?>>🌤 Warm</option>
          <option value="cold"   <?= $filter_priority === 'cold'   ? 'selected' : '' ?>>❄️ Cold</option>
          <option value="normal" <?= $filter_priority === 'normal' ? 'selected' : '' ?>>Normal</option>
        </select>
      </div>

      <div class="filter-group">
        <label class="filter-label">Gender</label>
        <select name="gender">
          <option value="">All</option>
          <option value="female" <?= $filter_gender === 'female' ? 'selected' : '' ?>>Female</option>
          <option value="male"   <?= $filter_gender === 'male'   ? 'selected' : '' ?>>Male</option>
        </select>
      </div>

      <div class="filter-group">
        <label class="filter-label">Assigned To</label>
        <select name="assigned">
          <option value="">All</option>
          <option value="-1" <?= $filter_assigned === -1 ? 'selected' : '' ?>>— Unassigned</option>
          <?php foreach ($all_admins as $adm): ?>
          <option value="<?= $adm['id'] ?>" <?= $filter_assigned === (int)$adm['id'] ? 'selected' : '' ?>>
            <?= clean($adm['full_name']) ?>
          </option>
          <?php endforeach; ?>
        </select>
      </div>

      <div class="filter-group">
        <label class="filter-label">Reg. Date</label>
        <input type="date" name="date" value="<?= htmlspecialchars($filter_date) ?>">
      </div>

      <div class="filter-group">
        <label class="filter-label">Follow-up Date</label>
        <input type="date" name="followup_date" value="<?= htmlspecialchars($filter_followup ?? '') ?>">
      </div>

      <div class="filter-group" style="justify-content:flex-end;">
        <label class="filter-label">&nbsp;</label>
        <div class="filter-actions">
          <button type="submit" class="btn btn-primary btn-sm">Filter</button>
          <a href="/admin/dashboard.php" class="btn btn-outline btn-sm">Clear</a>
        </div>
      </div>

    </form>

    <!-- ── Table header ── -->
    <div class="table-wrap">
      <!-- Bulk action bar -->
      <div class="bulk-bar" id="bulkBar">
        <span id="bulkCount">0</span> selected
        <select id="bulkStatus">
          <option value="">Change status to…</option>
          <?php foreach (all_statuses() as $s): ?>
          <option value="<?= $s ?>"><?= ucfirst($s) ?></option>
          <?php endforeach; ?>
        </select>
        <select id="bulkAssign">
          <option value="">Assign to…</option>
          <option value="0">— Unassign</option>
          <?php foreach ($all_admins as $adm): ?>
          <option value="<?= $adm['id'] ?>"><?= clean($adm['full_name']) ?></option>
          <?php endforeach; ?>
        </select>
        <button class="btn btn-primary btn-sm" onclick="applyBulk()">Apply</button>
        <button class="btn btn-ghost btn-sm" onclick="clearSelection()">Cancel</button>
      </div>

      <!-- Table meta -->
      <div class="table-header">
        <div class="table-meta">
          Showing <strong><?= count($rows) ?></strong> of <strong><?= $total_rows ?></strong>
          · Page <?= $pager['current_page'] ?> / <?= $pager['total_pages'] ?>
        </div>
        <div style="display:flex;gap:8px;align-items:center;">
          <label style="font-size:0.8rem;color:var(--text-muted);display:flex;align-items:center;gap:6px;cursor:pointer;">
            <input type="checkbox" id="selectAll" class="row-check"> All
          </label>
          <select style="font-size:0.8rem;padding:4px 8px;border:1px solid var(--border);border-radius:6px;" onchange="changeSort(this)">
            <option value="created_at" <?= $filter_sort === 'created_at' ? 'selected' : '' ?>>Newest first</option>
            <option value="status"     <?= $filter_sort === 'status'     ? 'selected' : '' ?>>By status</option>
            <option value="full_name"  <?= $filter_sort === 'full_name'  ? 'selected' : '' ?>>By name</option>
            <option value="age"        <?= $filter_sort === 'age'        ? 'selected' : '' ?>>By age</option>
          </select>
        </div>
      </div>

      <table class="data-table" id="mainTable">
        <thead>
          <tr>
            <th><input type="checkbox" id="selectAllTh" class="row-check"></th>
            <th>Photo</th>
            <th>Applicant</th>
            <th>Contact</th>
            <th>Profile</th>
            <th>Priority</th>
            <th>Status</th>
            <th>Assigned To</th>
            <th>Registered</th>
            <th>Actions</th>
          </tr>
        </thead>
        <tbody>
        <?php if (empty($rows)): ?>
          <tr><td colspan="10" class="no-data">
            No registrations found. <a href="/admin/dashboard.php" style="color:var(--red)">Clear filters</a>
          </td></tr>
        <?php else: ?>
          <?php foreach ($rows as $i => $row):
            $priority_class = $row['priority'] === 'hot' ? 'row-hot' : ($row['priority'] === 'warm' ? 'row-warm' : '');
          ?>
          <tr class="<?= $priority_class ?>">
            <td>
              <input type="checkbox" class="row-check lead-checkbox" value="<?= $row['id'] ?>">
            </td>
            <td>
              <?php if ($row['photo_path']): ?>
              <img src="<?= clean($row['photo_path']) ?>" class="thumb" alt="">
              <?php else: ?>
              <div class="thumb-placeholder">👤</div>
              <?php endif; ?>
            </td>
            <td>
              <div class="td-name"><?= clean($row['full_name']) ?></div>
              <div class="td-code"><?= clean($row['reg_code']) ?></div>
              <?php if ($row['district']): ?>
              <div style="font-size:0.75rem;color:var(--text-muted);">📍 <?= clean($row['district']) ?></div>
              <?php endif; ?>
            </td>
            <td>
              <div style="font-weight:600;font-size:0.875rem;"><?= clean($row['phone']) ?></div>
              <?php if ($row['email']): ?>
              <div style="font-size:0.75rem;color:var(--text-muted);"><?= clean($row['email']) ?></div>
              <?php endif; ?>
              <!-- Quick contact buttons -->
              <div class="td-actions" style="margin-top:6px;">
                <a href="https://wa.me/<?= preg_replace('/\D/','',$row['phone']) ?>" target="_blank"
                   class="btn btn-whatsapp btn-sm btn-icon" title="WhatsApp">💬</a>
                <a href="tel:<?= clean($row['phone']) ?>"
                   class="btn btn-success btn-sm btn-icon" title="Call">📞</a>
              </div>
            </td>
            <td>
              <div style="font-size:0.85rem;"><?= ucfirst(clean($row['gender'])) ?> · <?= (int)$row['age'] ?>y</div>
              <div style="font-size:0.75rem;color:var(--text-muted);"><?= (int)$row['height_cm'] ?> cm</div>
            </td>
            <td>
              <?php if ($row['priority'] && $row['priority'] !== 'normal'): ?>
              <span class="priority-dot <?= clean($row['priority']) ?>">
                <?= ucfirst(clean($row['priority'])) ?>
              </span>
              <?php else: ?>
              <span style="color:var(--text-xmuted);font-size:0.8rem;">—</span>
              <?php endif; ?>
            </td>
            <td>
              <span class="badge badge-<?= clean($row['status']) ?>"><?= ucfirst(clean($row['status'])) ?></span>
            </td>
            <td>
              <?php if ($row['assigned_name']): ?>
                <div class="assign-pill">
                  <div class="assign-dot"><?= strtoupper(substr($row['assigned_name'], 0, 1)) ?></div>
                  <?= clean($row['assigned_name']) ?>
                </div>
              <?php else: ?>
                <!-- Inline quick-assign dropdown -->
                <?php if ($admin['role'] !== 'viewer'): ?>
                <form method="POST" style="margin:0;">
                  <input type="hidden" name="csrf_token" value="<?= $csrf ?>">
                  <input type="hidden" name="quick_assign" value="1">
                  <input type="hidden" name="reg_id" value="<?= $row['id'] ?>">
                  <select name="assign_to" class="form-control" style="font-size:0.78rem;padding:4px 8px;min-height:32px;"
                          onchange="this.form.submit()">
                    <option value="">— Assign</option>
                    <?php foreach ($all_admins as $adm): ?>
                    <option value="<?= $adm['id'] ?>"><?= clean($adm['full_name']) ?></option>
                    <?php endforeach; ?>
                  </select>
                </form>
                <?php else: ?>
                <span class="assign-none">Unassigned</span>
                <?php endif; ?>
              <?php endif; ?>
            </td>
            <td>
              <div style="font-size:0.8rem;color:var(--text-muted);">
                <?= date('d M Y', strtotime($row['created_at'])) ?>
              </div>
              <div style="font-size:0.75rem;color:var(--text-xmuted);">
                <?= date('h:i A', strtotime($row['created_at'])) ?>
              </div>
            </td>
            <td>
              <div class="td-actions">
                <a href="/admin/view.php?id=<?= (int)$row['id'] ?>"
                   class="btn btn-primary btn-sm">View →</a>
              </div>
            </td>
          </tr>
          <?php endforeach; ?>
        <?php endif; ?>
        </tbody>
      </table>
    </div>

    <!-- ── Mobile card list ── -->
    <div class="lead-cards">
      <?php if (empty($rows)): ?>
      <div class="card" style="padding:32px;text-align:center;color:var(--text-muted);">
        No registrations found. <a href="/admin/dashboard.php" style="color:var(--red)">Clear filters</a>
      </div>
      <?php else: foreach ($rows as $row): ?>
      <a href="/admin/view.php?id=<?= $row['id'] ?>" class="lead-card">
        <?php if ($row['photo_path']): ?>
        <img src="<?= clean($row['photo_path']) ?>" class="lead-card-thumb" alt="">
        <?php else: ?>
        <div class="lead-card-thumb-placeholder">👤</div>
        <?php endif; ?>
        <div class="lead-card-body">
          <div class="lead-card-name"><?= clean($row['full_name']) ?></div>
          <div class="lead-card-sub">
            <?= clean($row['phone']) ?> · <?= ucfirst(clean($row['gender'])) ?> · <?= (int)$row['age'] ?>y
            <?php if ($row['district']): ?> · <?= clean($row['district']) ?><?php endif; ?>
          </div>
          <div class="lead-card-footer">
            <span class="badge badge-<?= clean($row['status']) ?>"><?= ucfirst(clean($row['status'])) ?></span>
            <?php if ($row['priority'] && $row['priority'] !== 'normal'): ?>
            <span class="priority-dot <?= clean($row['priority']) ?>"><?= ucfirst(clean($row['priority'])) ?></span>
            <?php endif; ?>
            <?php if ($row['assigned_name']): ?>
            <span style="font-size:0.75rem;color:var(--text-muted);">👤 <?= clean($row['assigned_name']) ?></span>
            <?php endif; ?>
            <span class="lead-card-date"><?= date('d M', strtotime($row['created_at'])) ?></span>
          </div>
        </div>
        <div class="lead-card-arrow">›</div>
      </a>
      <?php endforeach; endif; ?>
    </div>

    <!-- ── Pagination ── -->
    <?php if ($pager['total_pages'] > 1): ?>
    <div class="pagination">
      <?php if ($pager['current_page'] > 1): ?>
      <a href="<?= qs(['page' => $pager['current_page'] - 1]) ?>">&laquo;</a>
      <?php endif; ?>
      <?php
      $start = max(1, $pager['current_page'] - 2);
      $end   = min($pager['total_pages'], $pager['current_page'] + 2);
      if ($start > 1) echo '<span>…</span>';
      for ($p = $start; $p <= $end; $p++):
      ?>
      <a href="<?= qs(['page' => $p]) ?>"
         class="<?= $p === $pager['current_page'] ? 'active' : '' ?>"><?= $p ?></a>
      <?php endfor;
      if ($end < $pager['total_pages']) echo '<span>…</span>';
      ?>
      <?php if ($pager['current_page'] < $pager['total_pages']): ?>
      <a href="<?= qs(['page' => $pager['current_page'] + 1]) ?>">&raquo;</a>
      <?php endif; ?>
    </div>
    <?php endif; ?>

  </div><!-- /page-content -->
</div><!-- /main-wrap -->
</div><!-- /admin-layout -->

<script>
// ── Sidebar toggle ────────────────────────────────────────
function openSidebar() {
  document.getElementById('sidebar').classList.add('open');
  document.getElementById('sidebarOverlay').classList.add('active');
}
function closeSidebar() {
  document.getElementById('sidebar').classList.remove('open');
  document.getElementById('sidebarOverlay').classList.remove('active');
}

// ── Bulk select ───────────────────────────────────────────
const selectAll   = document.getElementById('selectAll');
const selectAllTh = document.getElementById('selectAllTh');
const bulkBar     = document.getElementById('bulkBar');
const bulkCount   = document.getElementById('bulkCount');

function updateBulkBar() {
  const checked = document.querySelectorAll('.lead-checkbox:checked');
  const n = checked.length;
  bulkBar.classList.toggle('visible', n > 0);
  bulkCount.textContent = n;
}

function clearSelection() {
  document.querySelectorAll('.lead-checkbox, #selectAll, #selectAllTh').forEach(el => el.checked = false);
  updateBulkBar();
}

[selectAll, selectAllTh].forEach(el => {
  if (el) el.addEventListener('change', () => {
    document.querySelectorAll('.lead-checkbox').forEach(cb => cb.checked = el.checked);
    [selectAll, selectAllTh].forEach(e => { if (e) e.checked = el.checked; });
    updateBulkBar();
  });
});

document.querySelectorAll('.lead-checkbox').forEach(cb => {
  cb.addEventListener('change', updateBulkBar);
});

function applyBulk() {
  const ids    = [...document.querySelectorAll('.lead-checkbox:checked')].map(el => el.value);
  const status = document.getElementById('bulkStatus').value;
  const assign = document.getElementById('bulkAssign').value;

  if (!ids.length) return alert('Select at least one registration.');
  if (!status && assign === '') return alert('Choose a status or assignee to apply.');

  const doConfirm = confirm(`Apply changes to ${ids.length} registration(s)?`);
  if (!doConfirm) return;

  const tasks = [];

  if (status) {
    tasks.push(
      fetch('/admin/bulk_action.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ csrf_token: '<?= $csrf ?>', ids, status })
      }).then(r => r.json())
    );
  }

  if (assign !== '') {
    tasks.push(
      fetch('/admin/bulk_assign.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ csrf_token: '<?= $csrf ?>', ids, assign_to: assign === '0' ? null : parseInt(assign) })
      }).then(r => r.json())
    );
  }

  Promise.all(tasks).then(results => {
    const allOk = results.every(r => r.success);
    if (allOk) {
      location.reload();
    } else {
      alert('Some actions failed: ' + results.map(r => r.error || r.message).join(', '));
    }
  });
}

function changeSort(sel) {
  const url = new URL(location.href);
  url.searchParams.set('sort', sel.value);
  location.href = url.toString();
}
</script>
</body>
</html>