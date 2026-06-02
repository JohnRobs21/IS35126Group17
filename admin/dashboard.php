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
log_action($pdo, $_SESSION['user_id'], 'Admin accessed dashboard');

// Fetch stats for dashboard
$total_flights   = $pdo->query('SELECT COUNT(*) FROM flights')->fetchColumn();
$total_bookings  = $pdo->query('SELECT COUNT(*) FROM bookings')->fetchColumn();
$total_users     = $pdo->query('SELECT COUNT(*) FROM users WHERE role = "passenger"')->fetchColumn();
$total_staff     = $pdo->query('SELECT COUNT(*) FROM users WHERE role = "staff"')->fetchColumn();

// Recent flights
$flights = $pdo->query('SELECT * FROM flights ORDER BY created_at DESC LIMIT 5')->fetchAll();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Dashboard — IS351 Airline</title>
    <style>
        * { box-sizing: border-box; margin: 0; padding: 0; }
        body { font-family: sans-serif; background: #f4f6f9; }
        .navbar { background: #1e1b4b; color: #fff; padding: 14px 24px; display: flex; align-items: center; justify-content: space-between; }
        .navbar h1 { font-size: 16px; font-weight: 600; }
        .navbar span { font-size: 13px; color: #a5b4fc; }
        .navbar a { color: #a5b4fc; text-decoration: none; font-size: 13px; margin-left: 16px; }
        .navbar a:hover { color: #fff; }
        .container { max-width: 1100px; margin: 0 auto; padding: 24px; }
        .welcome { font-size: 20px; font-weight: 600; color: #1a1a1a; margin-bottom: 6px; }
        .sub { font-size: 13px; color: #666; margin-bottom: 24px; }
        .stats { display: grid; grid-template-columns: repeat(auto-fit, minmax(180px, 1fr)); gap: 16px; margin-bottom: 28px; }
        .stat { background: #fff; border: 1px solid #e5e7eb; border-radius: 10px; padding: 18px; }
        .stat-label { font-size: 12px; color: #6b7280; margin-bottom: 6px; }
        .stat-value { font-size: 28px; font-weight: 600; color: #1a1a1a; }
        .card { background: #fff; border: 1px solid #e5e7eb; border-radius: 10px; padding: 20px; margin-bottom: 20px; }
        .card-title { font-size: 15px; font-weight: 600; margin-bottom: 16px; color: #1a1a1a; }
        table { width: 100%; border-collapse: collapse; font-size: 13px; }
        th { text-align: left; padding: 8px 12px; background: #f9fafb; color: #6b7280; font-weight: 500; border-bottom: 1px solid #e5e7eb; }
        td { padding: 10px 12px; border-bottom: 1px solid #f3f4f6; color: #374151; }
        tr:last-child td { border-bottom: none; }
        .badge { display: inline-block; padding: 2px 8px; border-radius: 20px; font-size: 11px; font-weight: 500; }
        .badge-green { background: #dcfce7; color: #166534; }
        .badge-red { background: #fef2f2; color: #b91c1c; }
        .badge-gray { background: #f3f4f6; color: #374151; }
        .nav-links { display: flex; gap: 12px; margin-bottom: 24px; flex-wrap: wrap; }
        .nav-link { background: #4f46e5; color: #fff; padding: 8px 16px; border-radius: 6px; text-decoration: none; font-size: 13px; }
        .nav-link:hover { background: #4338ca; }
        .nav-link.secondary { background: #fff; color: #4f46e5; border: 1px solid #4f46e5; }
    </style>
</head>
<body>
<div class="navbar">
    <h1>IS351 Airline System — Admin</h1>
    <div>
        <span>Welcome, <?= htmlspecialchars($_SESSION['user_name'], ENT_QUOTES, 'UTF-8') ?></span>
        <a href="../logout.php">Logout</a>
    </div>
</div>

<div class="container">
    <div class="welcome">Admin Dashboard</div>
    <div class="sub">Manage flights, users and bookings</div>

    <div class="stats">
        <div class="stat">
            <div class="stat-label">Total Flights</div>
            <div class="stat-value"><?= $total_flights ?></div>
        </div>
        <div class="stat">
            <div class="stat-label">Total Bookings</div>
            <div class="stat-value"><?= $total_bookings ?></div>
        </div>
        <div class="stat">
            <div class="stat-label">Passengers</div>
            <div class="stat-value"><?= $total_users ?></div>
        </div>
        <div class="stat">
            <div class="stat-label">Staff Members</div>
            <div class="stat-value"><?= $total_staff ?></div>
        </div>
    </div>

    <div class="nav-links">
        <a href="flights-list.php" class="nav-link">Manage Flights</a>
        <a href="flight-add.php" class="nav-link">Add New Flight</a>
        <a href="users-list.php" class="nav-link secondary">Manage Users</a>
        <a href="audit-logs.php" class="nav-link secondary">Audit Logs</a>
    </div>

    <div class="card">
        <div class="card-title">Recent Flights</div>
        <?php if (empty($flights)): ?>
            <p style="font-size:13px;color:#999">No flights added yet. <a href="flight-add.php">Add one now</a>.</p>
        <?php else: ?>
        <table>
            <thead>
                <tr>
                    <th>Flight No.</th>
                    <th>Route</th>
                    <th>Departure</th>
                    <th>Seats</th>
                    <th>Price</th>
                    <th>Status</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($flights as $f): ?>
                <tr>
                    <td><?= htmlspecialchars($f['flight_number'], ENT_QUOTES, 'UTF-8') ?></td>
                    <td><?= htmlspecialchars($f['origin'], ENT_QUOTES, 'UTF-8') ?> → <?= htmlspecialchars($f['destination'], ENT_QUOTES, 'UTF-8') ?></td>
                    <td><?= htmlspecialchars($f['departure_time'], ENT_QUOTES, 'UTF-8') ?></td>
                    <td><?= $f['seats_available'] ?></td>
                    <td>$<?= number_format($f['price'], 2) ?></td>
                    <td>
                        <span class="badge <?= $f['status'] === 'scheduled' ? 'badge-green' : ($f['status'] === 'cancelled' ? 'badge-red' : 'badge-gray') ?>">
                            <?= htmlspecialchars($f['status'], ENT_QUOTES, 'UTF-8') ?>
                        </span>
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
