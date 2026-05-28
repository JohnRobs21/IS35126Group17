<?php
session_set_cookie_params([
    'lifetime' => 1800,
    'httponly' => true,
    'secure'   => isset($_SERVER['HTTPS']),
    'samesite' => 'Strict'
]);

require_once '../includes/security-headers.php';

session_start();

require_once '../config/db.php';
require_once '../includes/auth.php';

require_role(['admin']);
log_action($pdo, $_SESSION['user_id'], 'Admin viewed flights list');

// Handle delete
if (isset($_GET['delete']) && is_numeric($_GET['delete'])) {
    if (!hash_equals($_SESSION['csrf_token'], $_GET['csrf_token'] ?? '')) {
        $error = 'Invalid request.';
    } else {
        $stmt = $pdo->prepare('DELETE FROM flights WHERE id = ?');
        $stmt->execute([$_GET['delete']]);
        log_action($pdo, $_SESSION['user_id'], 'Admin deleted flight ID: ' . $_GET['delete']);
        header('Location: flights-list.php?deleted=1');
        exit;
    }
}

// Generate CSRF token
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

// Search/filter
$search = trim($_GET['search'] ?? '');
if ($search) {
    $stmt = $pdo->prepare('
        SELECT * FROM flights
        WHERE flight_number LIKE ? OR origin LIKE ? OR destination LIKE ?
        ORDER BY departure_time ASC
    ');
    $like = '%' . $search . '%';
    $stmt->execute([$like, $like, $like]);
} else {
    $stmt = $pdo->query('SELECT * FROM flights ORDER BY departure_time ASC');
}
$flights = $stmt->fetchAll();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Flights — Admin</title>
    <style>
        * { box-sizing: border-box; margin: 0; padding: 0; }
        body { font-family: sans-serif; background: #f4f6f9; }
        .navbar { background: #1e1b4b; color: #fff; padding: 14px 24px; display: flex; align-items: center; justify-content: space-between; }
        .navbar h1 { font-size: 16px; font-weight: 600; }
        .navbar a { color: #a5b4fc; text-decoration: none; font-size: 13px; margin-left: 16px; }
        .navbar a:hover { color: #fff; }
        .container { max-width: 1100px; margin: 0 auto; padding: 24px; }
        .page-title { font-size: 20px; font-weight: 600; color: #1a1a1a; margin-bottom: 6px; }
        .sub { font-size: 13px; color: #666; margin-bottom: 24px; }
        .toolbar { display: flex; align-items: center; justify-content: space-between; margin-bottom: 16px; flex-wrap: wrap; gap: 10px; }
        .search-box { display: flex; gap: 8px; }
        .search-box input { padding: 8px 12px; border: 1px solid #ccc; border-radius: 6px; font-size: 13px; width: 260px; }
        .search-box input:focus { outline: none; border-color: #4f46e5; }
        .search-box button { padding: 8px 14px; background: #4f46e5; color: #fff; border: none; border-radius: 6px; font-size: 13px; cursor: pointer; }
        .btn-add { background: #4f46e5; color: #fff; padding: 8px 16px; border-radius: 6px; text-decoration: none; font-size: 13px; }
        .btn-add:hover { background: #4338ca; }
        .card { background: #fff; border: 1px solid #e5e7eb; border-radius: 10px; overflow: hidden; }
        table { width: 100%; border-collapse: collapse; font-size: 13px; }
        th { text-align: left; padding: 10px 14px; background: #f9fafb; color: #6b7280; font-weight: 500; border-bottom: 1px solid #e5e7eb; }
        td { padding: 11px 14px; border-bottom: 1px solid #f3f4f6; color: #374151; vertical-align: middle; }
        tr:last-child td { border-bottom: none; }
        tr:hover td { background: #fafafa; }
        .badge { display: inline-block; padding: 2px 8px; border-radius: 20px; font-size: 11px; font-weight: 500; }
        .badge-green { background: #dcfce7; color: #166534; }
        .badge-red { background: #fef2f2; color: #b91c1c; }
        .badge-gray { background: #f3f4f6; color: #374151; }
        .action-link { color: #4f46e5; text-decoration: none; font-size: 12px; margin-right: 10px; }
        .action-link.danger { color: #b91c1c; }
        .action-link:hover { text-decoration: underline; }
        .alert { padding: 10px 14px; border-radius: 6px; font-size: 13px; margin-bottom: 16px; }
        .alert-success { background: #f0fdf4; border: 1px solid #86efac; color: #166534; }
        .alert-error { background: #fef2f2; border: 1px solid #fca5a5; color: #b91c1c; }
        .empty { padding: 32px; text-align: center; color: #9ca3af; font-size: 13px; }
        .breadcrumb { font-size: 13px; color: #6b7280; margin-bottom: 16px; }
        .breadcrumb a { color: #4f46e5; text-decoration: none; }
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
    <div class="breadcrumb"><a href="dashboard.php">Dashboard</a> → Flights</div>
    <div class="page-title">Manage Flights</div>
    <div class="sub">Add, edit or remove flights from the system</div>

    <?php if (isset($_GET['deleted'])): ?>
        <div class="alert alert-success">Flight deleted successfully.</div>
    <?php endif; ?>
    <?php if (isset($_GET['saved'])): ?>
        <div class="alert alert-success">Flight saved successfully.</div>
    <?php endif; ?>
    <?php if (!empty($error)): ?>
        <div class="alert alert-error"><?= htmlspecialchars($error, ENT_QUOTES, 'UTF-8') ?></div>
    <?php endif; ?>

    <div class="toolbar">
        <form method="GET" class="search-box">
            <input type="text" name="search" placeholder="Search flight, origin, destination..."
                   value="<?= htmlspecialchars($search, ENT_QUOTES, 'UTF-8') ?>">
            <button type="submit">Search</button>
        </form>
        <a href="flight-add.php" class="btn-add">+ Add New Flight</a>
    </div>

    <div class="card">
        <?php if (empty($flights)): ?>
            <div class="empty">
                <?= $search ? 'No flights match your search.' : 'No flights added yet.' ?>
                <?php if (!$search): ?><br><a href="flight-add.php" style="color:#4f46e5">Add your first flight</a><?php endif; ?>
            </div>
        <?php else: ?>
        <table>
            <thead>
                <tr>
                    <th>Flight No.</th>
                    <th>Origin</th>
                    <th>Destination</th>
                    <th>Departure</th>
                    <th>Arrival</th>
                    <th>Seats</th>
                    <th>Price</th>
                    <th>Status</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($flights as $f): ?>
                <tr>
                    <td><strong><?= htmlspecialchars($f['flight_number'], ENT_QUOTES, 'UTF-8') ?></strong></td>
                    <td><?= htmlspecialchars($f['origin'], ENT_QUOTES, 'UTF-8') ?></td>
                    <td><?= htmlspecialchars($f['destination'], ENT_QUOTES, 'UTF-8') ?></td>
                    <td><?= htmlspecialchars($f['departure_time'], ENT_QUOTES, 'UTF-8') ?></td>
                    <td><?= htmlspecialchars($f['arrival_time'], ENT_QUOTES, 'UTF-8') ?></td>
                    <td><?= $f['seats_available'] ?></td>
                    <td>$<?= number_format($f['price'], 2) ?></td>
                    <td>
                        <span class="badge <?= $f['status'] === 'scheduled' ? 'badge-green' : ($f['status'] === 'cancelled' ? 'badge-red' : 'badge-gray') ?>">
                            <?= htmlspecialchars($f['status'], ENT_QUOTES, 'UTF-8') ?>
                        </span>
                    </td>
                    <td>
                        <a href="flight-edit.php?id=<?= $f['id'] ?>" class="action-link">Edit</a>
                        <a href="flights-list.php?delete=<?= $f['id'] ?>&csrf_token=<?= $_SESSION['csrf_token'] ?>"
                           class="action-link danger"
                           onclick="return confirm('Are you sure you want to delete this flight?')">Delete</a>
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