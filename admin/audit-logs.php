<?php
session_set_cookie_params([
    'lifetime' => 1800,
    'httponly' => true,
    'secure'   => isset($_SERVER['HTTPS']),
    'samesite' => 'Strict'
]);

require_once __DIR__ . '/../includes/security-headers.php';
session_start();

require_once '../config/db.php';
require_once '../includes/auth.php';

require_role(['admin']);
log_action($pdo, $_SESSION['user_id'], 'Admin viewed audit logs');

// Filters
$filter_user   = trim($_GET['user'] ?? '');
$filter_action = trim($_GET['action'] ?? '');
$filter_date   = trim($_GET['date'] ?? '');

$where  = ['1=1'];
$params = [];

if ($filter_user) {
    $where[]  = '(u.name LIKE ? OR u.email LIKE ?)';
    $params[] = '%' . $filter_user . '%';
    $params[] = '%' . $filter_user . '%';
}
if ($filter_action) {
    $where[]  = 'l.action LIKE ?';
    $params[] = '%' . $filter_action . '%';
}
if ($filter_date) {
    $where[]  = 'DATE(l.created_at) = ?';
    $params[] = $filter_date;
}

$sql = '
    SELECT l.*, u.name AS user_name, u.email AS user_email, u.role AS user_role
    FROM audit_logs l
    LEFT JOIN users u ON l.user_id = u.id
    WHERE ' . implode(' AND ', $where) . '
    ORDER BY l.created_at DESC
    LIMIT 200
';
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$logs = $stmt->fetchAll();

// Stats
$total_logs   = $pdo->query('SELECT COUNT(*) FROM audit_logs')->fetchColumn();
$today_logs   = $pdo->query("SELECT COUNT(*) FROM audit_logs WHERE DATE(created_at) = CURDATE()")->fetchColumn();
$login_count  = $pdo->query("SELECT COUNT(*) FROM audit_logs WHERE action LIKE '%login%'")->fetchColumn();
$locked_count = $pdo->query("SELECT COUNT(*) FROM audit_logs WHERE action LIKE '%locked%'")->fetchColumn();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Audit Logs — Admin</title>
    <style>
        * { box-sizing: border-box; margin: 0; padding: 0; }
        body { font-family: sans-serif; background: #f4f6f9; }
        .navbar { background: #1e1b4b; color: #fff; padding: 14px 24px; display: flex; align-items: center; justify-content: space-between; }
        .navbar h1 { font-size: 16px; font-weight: 600; }
        .navbar a { color: #a5b4fc; text-decoration: none; font-size: 13px; margin-left: 16px; }
        .navbar a:hover { color: #fff; }
        .container { max-width: 1200px; margin: 0 auto; padding: 24px; }
        .breadcrumb { font-size: 13px; color: #6b7280; margin-bottom: 16px; }
        .breadcrumb a { color: #4f46e5; text-decoration: none; }
        .page-title { font-size: 20px; font-weight: 600; color: #1a1a1a; margin-bottom: 6px; }
        .sub { font-size: 13px; color: #666; margin-bottom: 24px; }
        .stats { display: grid; grid-template-columns: repeat(auto-fit, minmax(160px, 1fr)); gap: 14px; margin-bottom: 24px; }
        .stat { background: #fff; border: 1px solid #e5e7eb; border-radius: 10px; padding: 16px; }
        .stat-label { font-size: 12px; color: #6b7280; margin-bottom: 4px; }
        .stat-value { font-size: 26px; font-weight: 600; color: #1a1a1a; }
        .filter-card { background: #fff; border: 1px solid #e5e7eb; border-radius: 10px; padding: 18px; margin-bottom: 20px; }
        .filter-grid { display: grid; grid-template-columns: 1fr 1fr 1fr auto; gap: 12px; align-items: end; }
        @media(max-width:700px) { .filter-grid { grid-template-columns: 1fr; } }
        .filter-grid label { font-size: 12px; font-weight: 500; color: #374151; display: block; margin-bottom: 4px; }
        .filter-grid input { width: 100%; padding: 8px 12px; border: 1px solid #d1d5db; border-radius: 6px; font-size: 13px; }
        .filter-grid input:focus { outline: none; border-color: #4f46e5; }
        .btn-filter { padding: 8px 16px; background: #4f46e5; color: #fff; border: none; border-radius: 6px; font-size: 13px; cursor: pointer; white-space: nowrap; }
        .btn-clear { padding: 8px 14px; background: #fff; color: #374151; border: 1px solid #d1d5db; border-radius: 6px; font-size: 13px; cursor: pointer; text-decoration: none; display: inline-block; }
        .card { background: #fff; border: 1px solid #e5e7eb; border-radius: 10px; overflow: hidden; }
        .card-header { padding: 14px 18px; border-bottom: 1px solid #f3f4f6; display: flex; align-items: center; justify-content: space-between; }
        .card-title { font-size: 14px; font-weight: 600; color: #1a1a1a; }
        .result-count { font-size: 12px; color: #9ca3af; }
        table { width: 100%; border-collapse: collapse; font-size: 13px; }
        th { text-align: left; padding: 10px 14px; background: #f9fafb; color: #6b7280; font-weight: 500; border-bottom: 1px solid #e5e7eb; white-space: nowrap; }
        td { padding: 10px 14px; border-bottom: 1px solid #f3f4f6; color: #374151; vertical-align: middle; }
        tr:last-child td { border-bottom: none; }
        tr:hover td { background: #fafafa; }
        .user-name { font-weight: 500; color: #1a1a1a; }
        .user-email { font-size: 11px; color: #9ca3af; }
        .action-text { color: #374151; max-width: 320px; }
        .ip { font-family: monospace; font-size: 12px; color: #6b7280; }
        .timestamp { font-size: 12px; color: #6b7280; white-space: nowrap; }
        .role-badge { display: inline-block; padding: 1px 7px; border-radius: 20px; font-size: 11px; }
        .role-admin { background: #ede9fe; color: #5b21b6; }
        .role-staff { background: #dcfce7; color: #166534; }
        .role-passenger { background: #dbeafe; color: #1e40af; }
        .role-system { background: #f3f4f6; color: #374151; }
        .empty { padding: 40px; text-align: center; color: #9ca3af; font-size: 13px; }
        .action-highlight { color: #b91c1c; font-weight: 500; }
    </style>
</head>
<body>
<div class="navbar">
    <h1>IS351 Airline System — Admin</h1>
    <div>
        <a href="dashboard.php">Dashboard</a>
        <a href="../logout.php">Logout</a>
    </div>
</div>

<div class="container">
    <div class="breadcrumb"><a href="dashboard.php">Dashboard</a> → Audit Logs</div>
    <div class="page-title">Audit Logs</div>
    <div class="sub">Monitor all system activity and user actions</div>

    <div class="stats">
        <div class="stat">
            <div class="stat-label">Total Log Entries</div>
            <div class="stat-value"><?= number_format($total_logs) ?></div>
        </div>
        <div class="stat">
            <div class="stat-label">Today's Activity</div>
            <div class="stat-value"><?= number_format($today_logs) ?></div>
        </div>
        <div class="stat">
            <div class="stat-label">Login Events</div>
            <div class="stat-value"><?= number_format($login_count) ?></div>
        </div>
        <div class="stat">
            <div class="stat-label">Lockout Events</div>
            <div class="stat-value" style="color:#b91c1c"><?= number_format($locked_count) ?></div>
        </div>
    </div>

    <div class="filter-card">
        <form method="GET" action="audit-logs.php">
            <div class="filter-grid">
                <div>
                    <label>Filter by User</label>
                    <input type="text" name="user" placeholder="Name or email..."
                           value="<?= htmlspecialchars($filter_user, ENT_QUOTES, 'UTF-8') ?>">
                </div>
                <div>
                    <label>Filter by Action</label>
                    <input type="text" name="action" placeholder="e.g. login, booking..."
                           value="<?= htmlspecialchars($filter_action, ENT_QUOTES, 'UTF-8') ?>">
                </div>
                <div>
                    <label>Filter by Date</label>
                    <input type="date" name="date"
                           value="<?= htmlspecialchars($filter_date, ENT_QUOTES, 'UTF-8') ?>">
                </div>
                <div style="display:flex;gap:8px">
                    <button type="submit" class="btn-filter">Filter</button>
                    <a href="audit-logs.php" class="btn-clear">Clear</a>
                </div>
            </div>
        </form>
    </div>

    <div class="card">
        <div class="card-header">
            <div class="card-title">Activity Log</div>
            <div class="result-count">Showing <?= count($logs) ?> of <?= number_format($total_logs) ?> entries</div>
        </div>
        <?php if (empty($logs)): ?>
            <div class="empty">No log entries found matching your filters.</div>
        <?php else: ?>
        <table>
            <thead>
                <tr>
                    <th>ID</th>
                    <th>User</th>
                    <th>Role</th>
                    <th>Action</th>
                    <th>IP Address</th>
                    <th>Timestamp</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($logs as $log): ?>
                <tr>
                    <td style="color:#9ca3af"><?= $log['id'] ?></td>
                    <td>
                        <?php if ($log['user_name']): ?>
                            <div class="user-name"><?= htmlspecialchars($log['user_name'], ENT_QUOTES, 'UTF-8') ?></div>
                            <div class="user-email"><?= htmlspecialchars($log['user_email'], ENT_QUOTES, 'UTF-8') ?></div>
                        <?php else: ?>
                            <span style="color:#9ca3af;font-size:12px">System / Deleted user</span>
                        <?php endif; ?>
                    </td>
                    <td>
                        <?php if ($log['user_role']): ?>
                            <span class="role-badge role-<?= htmlspecialchars($log['user_role'], ENT_QUOTES, 'UTF-8') ?>">
                                <?= htmlspecialchars($log['user_role'], ENT_QUOTES, 'UTF-8') ?>
                            </span>
                        <?php else: ?>
                            <span class="role-badge role-system">—</span>
                        <?php endif; ?>
                    </td>
                    <td class="action-text">
                        <?php
                        $action = htmlspecialchars($log['action'], ENT_QUOTES, 'UTF-8');
                        // Highlight security-related actions in red
                        $is_security = stripos($log['action'], 'lock') !== false
                                    || stripos($log['action'], 'fail') !== false
                                    || stripos($log['action'], 'unauthori') !== false;
                        ?>
                        <span <?= $is_security ? 'class="action-highlight"' : '' ?>>
                            <?= $action ?>
                        </span>
                    </td>
                    <td class="ip"><?= htmlspecialchars($log['ip_address'] ?? '—', ENT_QUOTES, 'UTF-8') ?></td>
                    <td class="timestamp"><?= htmlspecialchars($log['created_at'], ENT_QUOTES, 'UTF-8') ?></td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
        <?php endif; ?>
    </div>
</div>
</body>
</html>
