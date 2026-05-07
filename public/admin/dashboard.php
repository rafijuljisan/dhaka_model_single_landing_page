<?php
// ============================================================
// admin/dashboard.php  — Main Dashboard
// ============================================================

require_once __DIR__ . '/../../core/includes/auth.php';
require_once __DIR__ . '/../../core/includes/functions.php';

require_login();

$db    = db();
$admin = current_admin();

// ── Stats ────────────────────────────────────────────────────
$stats = $db->query("
    SELECT
        COUNT(*)                                         AS total,
        SUM(status = 'pending')                          AS pending,
        SUM(status = 'reviewed')                         AS reviewed,
        SUM(status = 'approved')                         AS approved,
        SUM(status = 'rejected')                         AS rejected,
        SUM(status = 'waitlist')                         AS waitlist,
        SUM(gender  = 'male')                            AS male,
        SUM(gender  = 'female')                          AS female,
        SUM(DATE(created_at) = CURDATE())                AS today,
        SUM(created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)) AS this_week
    FROM registrations
")->fetch_assoc();

$max_reg = (int) get_setting('max_registrations', '500');

// ── Filters ──────────────────────────────────────────────────
$allowed_status = ['', 'pending', 'reviewed', 'approved', 'rejected', 'waitlist'];
$allowed_gender = ['', 'male', 'female', 'other'];
$allowed_sort   = ['created_at', 'full_name', 'age', 'height_cm', 'status'];

$filter_status  = in_array($_GET['status'] ?? '', $allowed_status) ? ($_GET['status'] ?? '') : '';
$filter_gender  = in_array($_GET['gender'] ?? '', $allowed_gender) ? ($_GET['gender'] ?? '') : '';
$filter_search  = clean($_GET['search'] ?? '');
$filter_date    = clean($_GET['date'] ?? '');
$filter_sort    = in_array($_GET['sort'] ?? '', $allowed_sort)     ? ($_GET['sort'] ?? 'created_at') : 'created_at';
$filter_order   = ($_GET['order'] ?? 'desc') === 'asc' ? 'ASC' : 'DESC';
$per_page       = 25;
$current_page   = max(1, (int)($_GET['page'] ?? 1));

// ── Build WHERE ──────────────────────────────────────────────
$where   = [];
$params  = [];
$types   = '';

if ($filter_status !== '') {
    $where[]  = 'status = ?';
    $params[] = $filter_status;
    $types   .= 's';
}
if ($filter_gender !== '') {
    $where[]  = 'gender = ?';
    $params[] = $filter_gender;
    $types   .= 's';
}
if ($filter_search !== '') {
    $where[]  = '(full_name LIKE ? OR phone LIKE ? OR reg_code LIKE ? OR email LIKE ?)';
    $like     = '%' . $filter_search . '%';
    $params   = array_merge($params, [$like, $like, $like, $like]);
    $types   .= 'ssss';
}
if ($filter_date !== '') {
    $where[]  = 'DATE(created_at) = ?';
    $params[] = $filter_date;
    $types   .= 's';
}

$where_sql = $where ? 'WHERE ' . implode(' AND ', $where) : '';

// ── Count total for pagination ────────────────────────────────
$count_sql  = "SELECT COUNT(*) AS c FROM registrations $where_sql";
$count_stmt = $db->prepare($count_sql);
if ($params) $count_stmt->bind_param($types, ...$params);
$count_stmt->execute();
$total_rows = (int)$count_stmt->get_result()->fetch_assoc()['c'];
$count_stmt->close();

$pager  = paginate($total_rows, $per_page, $current_page);

// ── Fetch rows ───────────────────────────────────────────────
$data_sql  = "SELECT r.id, r.reg_code, r.full_name, r.phone, r.email, r.age,
                     r.gender, r.height_cm, r.district, r.status, r.photo_path, r.created_at,
                     a.full_name AS reviewed_by_name
              FROM registrations r
              LEFT JOIN admin_users a ON r.reviewed_by = a.id
              $where_sql
              ORDER BY r.{$filter_sort} $filter_order
              LIMIT ? OFFSET ?";

$data_stmt = $db->prepare($data_sql);
$all_params = array_merge($params, [$per_page, $pager['offset']]);
$all_types  = $types . 'ii';
$data_stmt->bind_param($all_types, ...$all_params);
$data_stmt->execute();
$rows = $data_stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$data_stmt->close();

// ── Build query string helper for pagination links ────────────
function qs(array $overrides = []): string {
    $base = ['status','gender','search','date','sort','order'];
    $q    = [];
    foreach ($base as $k) {
        $v = $overrides[$k] ?? ($_GET[$k] ?? '');
        if ($v !== '') $q[$k] = $v;
    }
    if (isset($overrides['page'])) $q['page'] = $overrides['page'];
    return '?' . http_build_query($q);
}
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

<?php include __DIR__ . '/../../core/admin_partials/navbar.php'; ?>

<div class="container">

  <!-- ── Page Header ── -->
  <div class="page-header">
    <h1>📋 Dashboard</h1>
    <div class="header-actions">
      <a href="/admin/export.php<?= qs() ?>" class="btn btn-outline">⬇ Export CSV</a>
      <?php if ($admin['role'] !== 'viewer'): ?>
      <a href="/admin/settings.php" class="btn btn-secondary">⚙ Settings</a>
      <?php endif; ?>
    </div>
  </div>

  <!-- ── Stat Cards ── -->
  <div class="stats-grid">
    <div class="stat-card">
      <div class="stat-num"><?= $stats['total'] ?></div>
      <div class="stat-label">Total Registrations</div>
      <div class="stat-sub"><?= $max_reg - $stats['total'] ?> slots remaining</div>
      <div class="stat-bar"><div style="width:<?= min(100, round(($stats['total']/$max_reg)*100)) ?>%"></div></div>
    </div>
    <div class="stat-card stat-pending">
      <div class="stat-num"><?= $stats['pending'] ?></div>
      <div class="stat-label">Pending Review</div>
    </div>
    <div class="stat-card stat-approved">
      <div class="stat-num"><?= $stats['approved'] ?></div>
      <div class="stat-label">Approved</div>
    </div>
    <div class="stat-card stat-rejected">
      <div class="stat-num"><?= $stats['rejected'] ?></div>
      <div class="stat-label">Rejected</div>
    </div>
    <div class="stat-card">
      <div class="stat-num"><?= $stats['today'] ?></div>
      <div class="stat-label">Today</div>
    </div>
    <div class="stat-card">
      <div class="stat-num"><?= $stats['this_week'] ?></div>
      <div class="stat-label">This Week</div>
    </div>
    <div class="stat-card">
      <div class="stat-num"><?= $stats['female'] ?></div>
      <div class="stat-label">Female</div>
    </div>
    <div class="stat-card">
      <div class="stat-num"><?= $stats['male'] ?></div>
      <div class="stat-label">Male</div>
    </div>
  </div>

  <!-- ── Filters ── -->
  <form method="GET" action="/admin/dashboard.php" class="filter-bar">
    <input type="text" name="search" placeholder="Search name / phone / code…"
           value="<?= htmlspecialchars($filter_search) ?>">

    <select name="status">
      <option value="">All Status</option>
      <?php foreach (all_statuses() as $s): ?>
      <option value="<?= $s ?>" <?= $filter_status === $s ? 'selected' : '' ?>><?= ucfirst($s) ?></option>
      <?php endforeach; ?>
    </select>

    <select name="gender">
      <option value="">All Gender</option>
      <option value="female" <?= $filter_gender === 'female' ? 'selected' : '' ?>>Female</option>
      <option value="male"   <?= $filter_gender === 'male'   ? 'selected' : '' ?>>Male</option>
      <option value="other"  <?= $filter_gender === 'other'  ? 'selected' : '' ?>>Other</option>
    </select>

    <input type="date" name="date" value="<?= htmlspecialchars($filter_date) ?>">

    <button type="submit" class="btn btn-primary">Filter</button>
    <a href="/admin/dashboard.php" class="btn btn-outline">Clear</a>
  </form>

  <!-- ── Result info ── -->
  <div class="table-meta">
    Showing <strong><?= count($rows) ?></strong> of <strong><?= $total_rows ?></strong> results
    | Page <?= $pager['current_page'] ?> of <?= $pager['total_pages'] ?>
  </div>

  <!-- ── Table ── -->
  <div class="table-wrap">
  <table class="data-table">
    <thead>
      <tr>
        <th>#</th>
        <th><a href="<?= qs(['sort'=>'reg_code','order'=> $filter_order==='ASC'?'desc':'asc']) ?>">Code</a></th>
        <th>Photo</th>
        <th><a href="<?= qs(['sort'=>'full_name','order'=> $filter_order==='ASC'?'desc':'asc']) ?>">Name</a></th>
        <th>Phone</th>
        <th><a href="<?= qs(['sort'=>'age','order'=> $filter_order==='ASC'?'desc':'asc']) ?>">Age</a></th>
        <th>Gender</th>
        <th><a href="<?= qs(['sort'=>'height_cm','order'=> $filter_order==='ASC'?'desc':'asc']) ?>">Height</a></th>
        <th>District</th>
        <th><a href="<?= qs(['sort'=>'status','order'=> $filter_order==='ASC'?'desc':'asc']) ?>">Status</a></th>
        <th><a href="<?= qs(['sort'=>'created_at','order'=> $filter_order==='ASC'?'desc':'asc']) ?>">Registered</a></th>
        <th>Action</th>
      </tr>
    </thead>
    <tbody>
    <?php if (empty($rows)): ?>
      <tr><td colspan="12" class="no-data">No registrations found.</td></tr>
    <?php else: ?>
      <?php foreach ($rows as $i => $row): ?>
      <tr>
        <td><?= ($pager['offset'] + $i + 1) ?></td>
        <td><code><?= clean($row['reg_code']) ?></code></td>
        <td>
          <?php if ($row['photo_path']): ?>
          <img src="<?= clean($row['photo_path']) ?>" class="thumb" alt="photo">
          <?php else: ?>
          <span class="no-photo">—</span>
          <?php endif; ?>
        </td>
        <td><?= clean($row['full_name']) ?></td>
        <td><?= clean($row['phone']) ?></td>
        <td><?= (int)$row['age'] ?></td>
        <td><?= ucfirst(clean($row['gender'])) ?></td>
        <td><?= (int)$row['height_cm'] ?> cm</td>
        <td><?= clean($row['district']) ?></td>
        <td><span class="badge <?= status_badge_class($row['status']) ?>"><?= ucfirst($row['status']) ?></span></td>
        <td><?= date('d M Y', strtotime($row['created_at'])) ?></td>
        <td>
          <a href="/admin/view.php?id=<?= (int)$row['id'] ?>" class="btn btn-xs btn-primary">View</a>
        </td>
      </tr>
      <?php endforeach; ?>
    <?php endif; ?>
    </tbody>
  </table>
  </div>

  <!-- ── Pagination ── -->
  <?php if ($pager['total_pages'] > 1): ?>
  <div class="pagination">
    <?php if ($pager['current_page'] > 1): ?>
      <a href="<?= qs(['page' => $pager['current_page'] - 1]) ?>">&laquo; Prev</a>
    <?php endif; ?>

    <?php
    $start = max(1, $pager['current_page'] - 2);
    $end   = min($pager['total_pages'], $pager['current_page'] + 2);
    for ($p = $start; $p <= $end; $p++):
    ?>
      <a href="<?= qs(['page' => $p]) ?>"
         class="<?= $p === $pager['current_page'] ? 'active' : '' ?>"><?= $p ?></a>
    <?php endfor; ?>

    <?php if ($pager['current_page'] < $pager['total_pages']): ?>
      <a href="<?= qs(['page' => $pager['current_page'] + 1]) ?>">Next &raquo;</a>
    <?php endif; ?>
  </div>
  <?php endif; ?>

</div><!-- /container -->
</body>
</html>
