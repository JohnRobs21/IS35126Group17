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

require_role(['staff']);
log_action($pdo, $_SESSION['user_id'], 'Staff accessed dashboard');

// Fetch booking stats
$total_bookings   = $pdo->query('SELECT COUNT(*) FROM bookings')->fetchColumn();
$confirmed        = $pdo->query('SELECT COUNT(*) FROM bookings WHERE booking_status = "confirmed"')->fetchColumn();
$pending          = $pdo->query('SELECT COUNT(*) FROM bookings WHERE booking_status = "pending"')->fetchColumn();
$cancelled        = $pdo->query('SELECT COUNT(*) FROM bookings WHERE booking_status = "cancelled"')->fetchColumn();

// Recent bookings with passenger and flight info
$bookings = $pdo->query('
    SELECT b.*, u.name AS passenger_name, u.email AS passenger_email,
           f.flight_number, f.origin, f.destination, f.departure_time
    FROM bookings b
    JOIN users u ON b.user_id = u.id
    JOIN flights f ON b.flight_id = f.id
    ORDER BY b.booked_at DESC
    LIMIT 10
')->fetchAll();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Staff Dashboard — IS351 Airline</title>
    <style>
        * { box-sizing: border-box; margin: 0; padding: 0; }
        body { font-family: sans-serif; background: #f4f6f9; }
        .navbar { background: #065f46; color: #fff; padding: 14px 24px; display: flex; align-items: center; justify-content: space-between; }
        .navbar h1 { font-size: 16px; font-weight: 600; }
        .navbar a { color: #a7f3d0; text-decoration: none; font-size: 13px; margin-left: 16px; }
        .navbar a:hover { color: #fff; }
        .container { max-width: 1100px; margin: 0 auto; padding: 24px; }
        .welcome { font-size: 20px; font-weight: 600; color: #1a1a1a; margin-bottom: 6px; }
        .sub { font-size: 13px; color: #666; margin-bottom: 24px; }
        .stats { display: grid; grid-template-columns: repeat(auto-fit, minmax(160px, 1fr)); gap: 16px; margin-bottom: 28px; }
        .stat { background: #fff; border: 1px solid #e5e7eb; border-radius: 10px; padding: 18px; }
        .stat-label { font-size: 12px; color: #6b7280; margin-bottom: 6px; }
        .stat-value { font-size: 28px; font-weight: 600; color: #1a1a1a; }
        .card { background: #fff; border: 1px solid #e5e7eb; border-radius: 10px; padding: 20px; }
        .card-title { font-size: 15px; font-weight: 600; margin-bottom: 16px; color: #1a1a1a; }
        table { width: 100%; border-collapse: collapse; font-size: 13px; }
        th { text-align: left; padding: 8px 12px; background: #f9fafb; color: #6b7280; font-weight: 500; border-bottom: 1px solid #e5e7eb; }
        td { padding: 10px 12px; border-bottom: 1px solid #f3f4f6; color: #374151; }
        tr:last-child td { border-bottom: none; }
        .badge { display: inline-block; padding: 2px 8px; border-radius: 20px; font-size: 11px; font-weight: 500; }
        .badge-green { background: #dcfce7; color: #166534; }
        .badge-yellow { background: #fef9c3; color: #854d0e; }
        .badge-red { background: #fef2f2; color: #b91c1c; }
        .nav-links { display: flex; gap: 12px; margin-bottom: 24px; }
        .nav-link { background: #059669; color: #fff; padding: 8px 16px; border-radius: 6px; text-decoration: none; font-size: 13px; }
        .nav-link:hover { background: #047857; }
        .action-link { color: #4f46e5; text-decoration: none; font-size: 12px; margin-right: 8px; }
    </style>
</head>
<body>
<div class="navbar">
    <h1>IS351 Airline System — Staff</h1>
    <div>
        <span style="font-size:13px;color:#a7f3d0">Welcome, <?= htmlspecialchars($_SESSION['user_name'], ENT_QUOTES, 'UTF-8') ?></span>
        <a href="../logout.php">Logout</a>
    </div>
</div>

<div class="container">
    <div class="welcome">Staff Dashboard</div>
    <div class="sub">Manage and update passenger bookings</div>

    <div class="stats">
        <div class="stat">
            <div class="stat-label">Total Bookings</div>
            <div class="stat-value"><?= $total_bookings ?></div>
        </div>
        <div class="stat">
            <div class="stat-label">Confirmed</div>
            <div class="stat-value" style="color:#166534"><?= $confirmed ?></div>
        </div>
        <div class="stat">
            <div class="stat-label">Pending</div>
            <div class="stat-value" style="color:#854d0e"><?= $pending ?></div>
        </div>
        <div class="stat">
            <div class="stat-label">Cancelled</div>
            <div class="stat-value" style="color:#b91c1c"><?= $cancelled ?></div>
        </div>
    </div>

    <div class="nav-links">
        <a href="bookings-list.php" class="nav-link">All Bookings</a>
    </div>

    <div class="card">
        <div class="card-title">Recent Bookings</div>
        <?php if (empty($bookings)): ?>
            <p style="font-size:13px;color:#999">No bookings yet.</p>
        <?php else: ?>
        <table>
            <thead>
                <tr>
                    <th>Passenger</th>
                    <th>Flight</th>
                    <th>Route</th>
                    <th>Departure</th>
                    <th>Status</th>
                    <th>Action</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($bookings as $b): ?>
                <tr>
                    <td>
                        <?= htmlspecialchars($b['passenger_name'], ENT_QUOTES, 'UTF-8') ?><br>
                        <span style="color:#9ca3af;font-size:11px"><?= htmlspecialchars($b['passenger_email'], ENT_QUOTES, 'UTF-8') ?></span>
                    </td>
                    <td><?= htmlspecialchars($b['flight_number'], ENT_QUOTES, 'UTF-8') ?></td>
                    <td><?= htmlspecialchars($b['origin'], ENT_QUOTES, 'UTF-8') ?> → <?= htmlspecialchars($b['destination'], ENT_QUOTES, 'UTF-8') ?></td>
                    <td><?= htmlspecialchars($b['departure_time'], ENT_QUOTES, 'UTF-8') ?></td>
                    <td>
                        <span class="badge <?= $b['booking_status'] === 'confirmed' ? 'badge-green' : ($b['booking_status'] === 'cancelled' ? 'badge-red' : 'badge-yellow') ?>">
                            <?= htmlspecialchars($b['booking_status'], ENT_QUOTES, 'UTF-8') ?>
                        </span>
                    </td>
                    <td>
                        <a href="booking-update.php?id=<?= $b['id'] ?>&status=confirmed" class="action-link">Confirm</a>
                        <a href="booking-update.php?id=<?= $b['id'] ?>&status=cancelled" class="action-link" style="color:#b91c1c">Cancel</a>
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
