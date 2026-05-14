<?php
// ============================================================
// public/admin/import.php — Admin: Import Leads via CSV
// ============================================================

require_once __DIR__ . '/../../core/includes/auth.php';
require_once __DIR__ . '/../../core/includes/functions.php';

require_login();

$admin = current_admin();

if ($admin['role'] === 'viewer') {
    redirect('/admin/dashboard.php');
}

$csrf  = csrf_token();
$flash = flash_get();
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Import CSV – DMA Admin</title>
<link rel="stylesheet" href="/assets/admin.css">
</head>
<body>

<div class="sidebar-overlay" id="sidebarOverlay" onclick="closeSidebar()"></div>

<div class="admin-layout">

<!-- ════════════════ SIDEBAR ════════════════ -->
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

  <div class="sidebar-section">
    <div class="sidebar-section-label">Add Leads</div>
    <ul class="sidebar-nav">
      <li><a href="/admin/manual_entry.php">
        <span class="nav-icon">✏️</span> Add Lead Manually
      </a></li>
      <li><a href="/admin/import.php" class="active">
        <span class="nav-icon">📥</span> Import CSV
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

<!-- ════════════════ MAIN ════════════════ -->
<div class="main-wrap">

  <div class="topbar">
    <button class="topbar-hamburger" onclick="openSidebar()">☰</button>
    <div class="topbar-breadcrumb">
      <a href="/admin/dashboard.php">Dashboard</a>
      <span class="sep">/</span>
      <span>Import CSV</span>
    </div>
    <div class="topbar-actions">
      <a href="/admin/manual_entry.php" class="btn btn-primary btn-sm">✏️ Add Lead</a>
    </div>
  </div>

  <div class="page-content">

    <?php if ($flash): ?>
    <div class="alert alert-<?= $flash['type'] === 'success' ? 'success' : 'error' ?>">
      <span class="alert-icon"><?= $flash['type'] === 'success' ? '✅' : '⚠️' ?></span>
      <?= $flash['msg'] ?>
    </div>
    <?php endif; ?>

    <div style="display:grid;grid-template-columns:1fr 340px;gap:20px;align-items:start;">

      <!-- ══ LEFT: Upload form ══ -->
      <div>

        <!-- Upload card -->
        <div class="card" style="margin-bottom:16px;">
          <div class="card-header">
            <div class="card-title">📥 Upload CSV File</div>
          </div>
          <div class="card-body">
            <form method="POST" action="/admin/import_process.php"
                  enctype="multipart/form-data" id="importForm">
              <input type="hidden" name="csrf_token" value="<?= $csrf ?>">

              <!-- Drop zone -->
              <div id="dropZone"
                   style="border:2px dashed var(--border);border-radius:var(--radius-lg);
                          padding:52px 24px;text-align:center;cursor:pointer;
                          transition:border-color 0.2s,background 0.2s;margin-bottom:20px;
                          background:var(--bg);">
                <div style="font-size:3rem;margin-bottom:10px;">📂</div>
                <div style="font-weight:700;font-size:1rem;margin-bottom:4px;color:var(--text);">
                  Drag & drop your CSV here
                </div>
                <div style="font-size:0.85rem;color:var(--text-muted);margin-bottom:18px;">
                  or click the button below to browse
                </div>
                <input type="file" name="csv_file" id="csvFile"
                       accept=".csv,text/csv" style="display:none;" required
                       onchange="onFileSelected(this)">
                <button type="button" class="btn btn-outline"
                        onclick="document.getElementById('csvFile').click()">
                  Browse File
                </button>

                <!-- Selected file display -->
                <div id="fileInfo" style="display:none;margin-top:18px;">
                  <div style="display:inline-flex;align-items:center;gap:10px;
                              background:var(--surface);border:1px solid var(--border);
                              border-radius:var(--radius);padding:10px 16px;">
                    <span style="font-size:1.2rem;">📄</span>
                    <div style="text-align:left;">
                      <div id="fileName" style="font-weight:600;font-size:0.875rem;"></div>
                      <div id="fileSize" style="font-size:0.78rem;color:var(--text-muted);"></div>
                    </div>
                    <button type="button" onclick="clearFile()"
                            style="background:none;border:none;color:var(--text-muted);
                                   cursor:pointer;font-size:1rem;padding:4px;">✕</button>
                  </div>
                </div>
              </div>

              <div style="display:flex;gap:12px;align-items:center;flex-wrap:wrap;">
                <button type="submit" class="btn btn-primary btn-lg" id="submitBtn" disabled>
                  📥 Import Now
                </button>
                <a href="/admin/dashboard.php" class="btn btn-ghost">Cancel</a>
                <span style="font-size:0.8rem;color:var(--text-muted);margin-left:auto;">
                  ⚠️ Duplicates skipped · Max 500 rows per upload
                </span>
              </div>

            </form>
          </div>
        </div>

        <!-- Format reference table -->
        <div class="card">
          <div class="card-header">
            <div class="card-title">📋 CSV Column Reference</div>
            <a href="/admin/import_sample.csv" class="btn btn-ghost btn-sm">⬇ Download Sample</a>
          </div>
          <div class="card-body" style="padding:0;">
            <div style="overflow-x:auto;">
              <table class="data-table">
                <thead>
                  <tr>
                    <th>Column Name</th>
                    <th>Required?</th>
                    <th>Example</th>
                    <th>Allowed Values</th>
                  </tr>
                </thead>
                <tbody>
                  <tr>
                    <td><code>full_name</code></td>
                    <td><span class="badge badge-approved">Yes</span></td>
                    <td>Fatema Begum</td>
                    <td>Min 3 characters</td>
                  </tr>
                  <tr>
                    <td><code>phone</code></td>
                    <td><span class="badge badge-approved">Yes</span></td>
                    <td>01712345678</td>
                    <td>Bangladeshi numbers only</td>
                  </tr>
                  <tr>
                    <td><code>dob</code></td>
                    <td><span class="badge badge-approved">Yes</span></td>
                    <td>2000-05-14</td>
                    <td>YYYY-MM-DD format</td>
                  </tr>
                  <tr>
                    <td><code>gender</code></td>
                    <td><span class="badge badge-approved">Yes</span></td>
                    <td>female</td>
                    <td>male / female / other</td>
                  </tr>
                  <tr>
                    <td><code>height_cm</code></td>
                    <td><span class="badge badge-approved">Yes</span></td>
                    <td>165</td>
                    <td>100 – 250</td>
                  </tr>
                  <tr>
                    <td><code>email</code></td>
                    <td><span class="badge badge-secondary">No</span></td>
                    <td>a@b.com</td>
                    <td>Valid email or leave blank</td>
                  </tr>
                  <tr>
                    <td><code>weight_kg</code></td>
                    <td><span class="badge badge-secondary">No</span></td>
                    <td>55</td>
                    <td>Number</td>
                  </tr>
                  <tr>
                    <td><code>skin_tone</code></td>
                    <td><span class="badge badge-secondary">No</span></td>
                    <td>fair</td>
                    <td>fair / wheatish / dusky / dark</td>
                  </tr>
                  <tr>
                    <td><code>district</code></td>
                    <td><span class="badge badge-secondary">No</span></td>
                    <td>Dhaka</td>
                    <td>Any text</td>
                  </tr>
                  <tr>
                    <td><code>address</code></td>
                    <td><span class="badge badge-secondary">No</span></td>
                    <td>Mirpur, Dhaka</td>
                    <td>Any text</td>
                  </tr>
                  <tr>
                    <td><code>experience</code></td>
                    <td><span class="badge badge-secondary">No</span></td>
                    <td>none</td>
                    <td>none / some / professional</td>
                  </tr>
                  <tr>
                    <td><code>fb_profile</code></td>
                    <td><span class="badge badge-secondary">No</span></td>
                    <td>https://fb.com/…</td>
                    <td>URL or blank</td>
                  </tr>
                  <tr>
                    <td><code>how_heard</code></td>
                    <td><span class="badge badge-secondary">No</span></td>
                    <td>facebook</td>
                    <td>facebook / instagram / friend / poster / other</td>
                  </tr>
                  <tr>
                    <td><code>status</code></td>
                    <td><span class="badge badge-secondary">No</span></td>
                    <td>pending</td>
                    <td>pending / reviewed / approved / rejected / waitlist</td>
                  </tr>
                  <tr>
                    <td><code>priority</code></td>
                    <td><span class="badge badge-secondary">No</span></td>
                    <td>normal</td>
                    <td>normal / hot / warm / cold</td>
                  </tr>
                </tbody>
              </table>
            </div>
          </div>
        </div>

      </div><!-- /left -->

      <!-- ══ RIGHT: Tips ══ -->
      <div>
        <div class="sidebar-card" style="margin-bottom:16px;">
          <div class="sidebar-card-header">💡 Tips for a Clean Import</div>
          <div class="sidebar-card-body"
               style="font-size:0.85rem;color:var(--text-2);line-height:1.9;">
            <p style="margin-bottom:10px;">
              ✅ First row must be the <strong>header row</strong> with exact column names.
            </p>
            <p style="margin-bottom:10px;">
              ✅ Column order doesn't matter — headers are matched by name.
            </p>
            <p style="margin-bottom:10px;">
              ✅ Extra columns in your file are safely ignored.
            </p>
            <p style="margin-bottom:10px;">
              ⚠️ Rows with duplicate phone numbers are <strong>skipped</strong> — no overwrite.
            </p>
            <p style="margin-bottom:10px;">
              ⚠️ DOB must be <code>YYYY-MM-DD</code> (e.g. <code>2001-08-15</code>).
            </p>
            <p style="margin-bottom:10px;">
              ⚠️ Phone must be a valid BD number starting with <code>01</code>.
            </p>
            <p style="margin-bottom:0;">
              📌 All imported leads are tagged as <em>csv_import</em> in the audit trail.
            </p>
          </div>
        </div>

        <div class="sidebar-card">
          <div class="sidebar-card-header">📄 Sample CSV Preview</div>
          <div class="sidebar-card-body" style="padding:0;overflow-x:auto;">
            <pre style="font-size:0.7rem;font-family:var(--mono);
                        color:var(--text-2);padding:14px;
                        white-space:pre;margin:0;line-height:1.7;">full_name,phone,dob,gender,height_cm
Fatema Begum,01712345678,2000-05-14,female,165
Karim Hossain,01812345679,1998-03-22,male,172</pre>
          </div>
          <div style="padding:12px 14px;border-top:1px solid var(--border);">
            <a href="/admin/import_sample.csv" class="btn btn-outline btn-sm btn-full">
              ⬇ Download Full Sample CSV
            </a>
          </div>
        </div>

      </div><!-- /right -->

    </div><!-- /grid -->

  </div><!-- /page-content -->
</div><!-- /main-wrap -->
</div><!-- /admin-layout -->

<script>
// ── Sidebar ───────────────────────────────────────────────────
function openSidebar() {
  document.getElementById('sidebar').classList.add('open');
  document.getElementById('sidebarOverlay').classList.add('active');
}
function closeSidebar() {
  document.getElementById('sidebar').classList.remove('open');
  document.getElementById('sidebarOverlay').classList.remove('active');
}

// ── Drop zone ─────────────────────────────────────────────────
const dropZone = document.getElementById('dropZone');

dropZone.addEventListener('dragover', e => {
  e.preventDefault();
  dropZone.style.borderColor = 'var(--red)';
  dropZone.style.background  = '#fff0f0';
});

dropZone.addEventListener('dragleave', () => {
  dropZone.style.borderColor = 'var(--border)';
  dropZone.style.background  = 'var(--bg)';
});

dropZone.addEventListener('drop', e => {
  e.preventDefault();
  dropZone.style.borderColor = 'var(--border)';
  dropZone.style.background  = 'var(--bg)';
  const file = e.dataTransfer.files[0];
  if (file) {
    const dt = new DataTransfer();
    dt.items.add(file);
    document.getElementById('csvFile').files = dt.files;
    onFileSelected(document.getElementById('csvFile'));
  }
});

function onFileSelected(input) {
  const file = input.files[0];
  if (!file) return;
  document.getElementById('fileInfo').style.display  = 'block';
  document.getElementById('fileName').textContent    = file.name;
  document.getElementById('fileSize').textContent    = (file.size / 1024).toFixed(1) + ' KB';
  document.getElementById('submitBtn').disabled      = false;
  dropZone.style.borderColor = 'var(--red)';
}

function clearFile() {
  document.getElementById('csvFile').value           = '';
  document.getElementById('fileInfo').style.display  = 'none';
  document.getElementById('submitBtn').disabled      = true;
  dropZone.style.borderColor = 'var(--border)';
}

// ── Prevent accidental double-submit ─────────────────────────
document.getElementById('importForm').addEventListener('submit', function () {
  const btn = document.getElementById('submitBtn');
  btn.disabled     = true;
  btn.textContent  = '⏳ Importing…';
});
</script>
</body>
</html>