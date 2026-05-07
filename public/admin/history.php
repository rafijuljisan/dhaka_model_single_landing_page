<?php
// ============================================================
// admin/history.php  — Activity & Status Logs
// ============================================================

require_once __DIR__ . '/../../core/includes/auth.php';
require_once __DIR__ . '/../../core/includes/functions.php';

// Ensure user is logged in
if (!is_logged_in()) {
    redirect('/admin/index.php?msg=session_expired');
}

$admin = current_admin();

// SECURITY Guard: Only Super Admins can access this page
if ($admin['role'] !== 'superadmin') {
    die("Unauthorized access. Super Admin privileges required.");
}

$db = db();

// Fetch logs matching the EXACT schema provided
$sql = "SELECT 
            l.changed_at,
            l.old_status,
            l.new_status,
            l.note,
            u.username AS admin_username,
            r.full_name AS applicant_name,
            r.reg_code
        FROM status_logs l
        LEFT JOIN admin_users u ON l.changed_by = u.id
        LEFT JOIN registrations r ON l.reg_id = r.id
        ORDER BY l.changed_at DESC 
        LIMIT 100"; 

$result = $db->query($sql);
$logs = $result ? $result->fetch_all(MYSQLI_ASSOC) : [];
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Activity History – DMA Admin</title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Playfair+Display:ital,wght@0,600;1,600&family=Montserrat:wght@400;500;600;700&display=swap" rel="stylesheet">
<style>
    :root {
        --primary-red: #cc0000;
        --bg-light: #f9f9fa;
        --text-dark: #222222;
        --text-muted: #666666;
        --border: #e5e7eb;
    }
    * { box-sizing: border-box; margin: 0; padding: 0; }
    body { 
        background: var(--bg-light); 
        font-family: 'Montserrat', sans-serif; 
        color: var(--text-dark); 
    }
    .container {
        max-width: 1200px;
        margin: 40px auto;
        padding: 0 20px;
    }
    .page-header {
        display: flex;
        justify-content: space-between;
        align-items: center;
        margin-bottom: 30px;
    }
    .page-title {
        font-family: 'Playfair Display', serif;
        font-size: 2rem;
        color: var(--primary-red);
    }
    .card {
        background: #fff;
        border-radius: 12px;
        box-shadow: 0 10px 30px rgba(0,0,0,0.05);
        overflow: hidden;
    }
    table {
        width: 100%;
        border-collapse: collapse;
        text-align: left;
    }
    th {
        background: #fdfdfd;
        padding: 16px 20px;
        font-size: 0.85rem;
        font-weight: 600;
        text-transform: uppercase;
        color: var(--text-muted);
        border-bottom: 2px solid var(--border);
    }
    td {
        padding: 16px 20px;
        font-size: 0.95rem;
        border-bottom: 1px solid var(--border);
        vertical-align: middle;
    }
    tr:last-child td { border-bottom: none; }
    tr:hover { background: #fafafa; }
    
    .badge {
        padding: 4px 10px;
        border-radius: 20px;
        font-size: 0.8rem;
        font-weight: 600;
        text-transform: capitalize;
    }
    .badge-admin { background: #f3f4f6; color: #4b5563; }
    
    /* Dynamic status badges */
    .badge-approved { background: #dcfce7; color: #166534; }
    .badge-rejected { background: #fee2e2; color: #991b1b; }
    .badge-pending  { background: #f3f4f6; color: #4b5563; }
    .badge-reviewed { background: #dbeafe; color: #1e3a8a; }
    .badge-waitlist { background: #fef9c3; color: #854d0e; }
    
    .status-flow {
        display: inline-flex;
        align-items: center;
        gap: 8px;
    }
    .status-arrow {
        color: #9ca3af;
        font-size: 0.8rem;
    }
    .admin-note {
        display: block;
        font-size: 0.8rem;
        color: var(--text-muted);
        margin-top: 6px;
        font-style: italic;
    }
    
    .empty-state {
        padding: 60px 20px;
        text-align: center;
        color: var(--text-muted);
    }
</style>
</head>
<body>

<?php include __DIR__ . '/../../core/admin_partials/navbar.php'; ?>

<div class="container">
    <div class="page-header">
        <h1 class="page-title">Activity History</h1>
    </div>

    <div class="card">
        <?php if (empty($logs)): ?>
            <div class="empty-state">
                <p>No activity logs found.</p>
            </div>
        <?php else: ?>
            <table>
                <thead>
                    <tr>
                        <th>Date & Time</th>
                        <th>Admin User</th>
                        <th>Applicant</th>
                        <th>Status Change & Notes</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($logs as $log): ?>
                    <tr>
                        <td style="color: var(--text-muted); font-size: 0.85rem;">
                            <?= date('M d, Y', strtotime($log['changed_at'])) ?><br>
                            <?= date('h:i A', strtotime($log['changed_at'])) ?>
                        </td>
                        <td>
                            <span class="badge badge-admin">
                                @<?= htmlspecialchars($log['admin_username'] ?? 'System') ?>
                            </span>
                        </td>
                        <td>
                            <strong><?= htmlspecialchars($log['applicant_name'] ?? 'Unknown/Deleted') ?></strong><br>
                            <span style="font-size: 0.8rem; color: var(--text-muted);">
                                <?= htmlspecialchars($log['reg_code'] ?? 'N/A') ?>
                            </span>
                        </td>
                        <td>
                            <div class="status-flow">
                                <?php if ($log['old_status']): ?>
                                    <span class="badge badge-<?= strtolower($log['old_status']) ?>">
                                        <?= htmlspecialchars($log['old_status']) ?>
                                    </span>
                                    <span class="status-arrow">→</span>
                                <?php endif; ?>
                                
                                <span class="badge badge-<?= strtolower($log['new_status']) ?>">
                                    <?= htmlspecialchars($log['new_status']) ?>
                                </span>
                            </div>
                            
                            <?php if (!empty($log['note'])): ?>
                                <span class="admin-note">"<?= htmlspecialchars($log['note']) ?>"</span>
                            <?php endif; ?>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        <?php endif; ?>
    </div>
</div>

</body>
</html>