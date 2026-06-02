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
log_action($pdo, $_SESSION['user_id'], 'Staff viewed all bookings');

if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

// Filter by status
$filter = $_GET['status'] ?? 'all';
$allowed = ['all', 'pending', 'confirmed', 'cancelled'];
if (!in_array($filter, $allowed)) $filter = 'all';

if ($filter === 'all') {
    $bookings = $pdo->query('
        SELECT b.*, u.name AS passenger_name, u.email AS passenger_email,
               f.flight_number, f.origin, f.destination, f.departure_time
        FROM bookings b
        JOIN users u ON b.user_id = u.id
        JOIN flights f ON b.flight_id = f.id
        ORDER BY b.booked_at DESC
    ')->fetchAll();
} else {
    $stmt = $pdo->prepare('
        SELECT b.*, u.name AS passenger_name, u.email AS passenger_email,
               f.flight_number, f.origin, f.destination, f.departure_time
        FROM bookings b
        JOIN users u ON b.user_id = u.id
        JOIN flights f ON b.flight_id = f.id
        WHERE b.booking_status = ?
        ORDER BY b.booked_at DESC
    ');
    $stmt->execute([$filter]);
    $bookings = $stmt->fetchAll();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>All Bookings — Staff</title>
    <style>
        * { box-sizing: border-box; margin: 0; padding: 0; }
        body { font-family: sans-serif; background: #f4f6f9; }
        .navbar { background: #065f46; color: #fff; padding: 14px 24px; display: flex; align-items: center; justify-content: space-between; }
        .navbar h1 { font-size: 16px; font-weight: 600; }
        .navbar a { color: #a7f3d0; text-decoration: none; font-size: 13px; margin-left: 16px; }
        .navbar a:hover { color: #fff; }
        .container { max-width: 1100px; margin: 0 auto; padding: 24px; }
        .breadcrumb { font-size: 13px; color: #6b7280; margin-bottom: 16px; }
        .breadcrumb a { color: #059669; text-decoration: none; }
        .page-title { font-size: 20px; font-weight: 600; color: #1a1a1a; margin-bottom: 6px; }
        .sub { font-size: 13px; color: #666; margin-bottom: 20px; }
        .filter-tabs { display: flex; gap: 8px; margin-bottom: 20px; flex-wrap: wrap; }
        .tab { padding: 7px 16px; border-radius: 20px; font-size: 13px; text-decoration: none; border: 1px solid #e5e7eb; color: #374151; background: #fff; }
        .tab:hover { background: #f9fafb; }
        .tab.active { background: #059669; color: #fff; border-color: #059669; }
        .alert { padding: 10px 14px; border-radius: 6px; font-size: 13px; margin-bottom: 16px; }
        .alert-success { background: #f0fdf4; border: 1px solid #86efac; color: #166534; }
        .card { background: #fff; border: 1px solid #e5e7eb; border-radius: 10px; overflow: hidden; }
        table { width: 100%; border-collapse: collapse; font-size: 13px; }
        th { text-align: left; padding: 10px 14px; background: #f9fafb; color: #6b7280; font-weight: 500; border-bottom: 1px solid #e5e7eb; }
        td { padding: 12px 14px; border-bottom: 1px solid #f3f4f6; color: #374151; vertical-align: middle; }
        tr:last-child td { border-bottom: none; }
        tr:hover td { background: #fafafa; }
        .badge { display: inline-block; padding: 2px 8px; border-radius: 20px; font-size: 11px; font-weight: 500; }
        .badge-green { background: #dcfce7; color: #166534; }
        .badge-yellow { background: #fef9c3; color: #854d0e; }
        .badge-red { background: #fef2f2; color: #b91c1c; }
        .action-link { font-size: 12px; text-decoration: none; padding: 4px 10px; border-radius: 5px; border: 1px solid; margin-right: 6px; }
        .action-confirm { color: #166534; border-color: #86efac; }
        .action-confirm:hover { background: #f0fdf4; }
        .action-cancel { color: #b91c1c; border-color: #fca5a5; }
        .action-cancel:hover { background: #fef2f2; }
        .empty { padding: 40px; text-align: center; color: #9ca3af; font-size: 13px; }
        .passenger-name { font-weight: 500; }
        .passenger-email { font-size: 11px; color: #9ca3af; }
    </style>
</head>
<body>
<div class="navbar">
    <h1>IS351 Airline System — Staff</h1>
    <div>
        <a href="dashboard.php">Dashboard</a>
        <a href="../logout.php">Logout</a>
    </div>
</div>

<div class="container">
    <div class="breadcrumb"><a href="dashboard.php">Dashboard</a> → All Bookings</div>
    <div class="page-title">Manage Bookings</div>
    <div class="sub">Confirm or cancel passenger bookings</div>

    <?php if (isset($_GET['updated'])): ?>
        <div class="alert alert-success">Booking status updated successfully.</div>
    <?php endif; ?>

    <div class="filter-tabs">
        <a href="bookings-list.php?status=all" class="tab <?= $filter === 'all' ? 'active' : '' ?>">All</a>
        <a href="bookings-list.php?status=pending" class="tab <?= $filter === 'pending' ? 'active' : '' ?>">Pending</a>
        <a href="bookings-list.php?status=confirmed" class="tab <?= $filter === 'confirmed' ? 'active' : '' ?>">Confirmed</a>
        <a href="bookings-list.php?status=cancelled" class="tab <?= $filter === 'cancelled' ? 'active' : '' ?>">Cancelled</a>
    </div>

    <div class="card">
        <?php if (empty($bookings)): ?>
            <div class="empty">No bookings found.</div>
        <?php else: ?>
        <table>
            <thead>
                <tr>
                    <th>#</th>
                    <th>Passenger</th>
                    <th>Flight</th>
                    <th>Route</th>
                    <th>Departure</th>
                    <th>Seat</th>
                    <th>Status</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($bookings as $b): ?>
                <tr>
                    <td><?= $b['id'] ?></td>
                    <td>
                        <div class="passenger-name"><?= htmlspecialchars($b['passenger_name'], ENT_QUOTES, 'UTF-8') ?></div>
                        <div class="passenger-email"><?= htmlspecialchars($b['passenger_email'], ENT_QUOTES, 'UTF-8') ?></div>
                    </td>
                    <td><strong><?= htmlspecialchars($b['flight_number'], ENT_QUOTES, 'UTF-8') ?></strong></td>
                    <td><?= htmlspecialchars($b['origin'], ENT_QUOTES, 'UTF-8') ?> → <?= htmlspecialchars($b['destination'], ENT_QUOTES, 'UTF-8') ?></td>
                    <td><?= htmlspecialchars($b['departure_time'], ENT_QUOTES, 'UTF-8') ?></td>
                    <td><?= htmlspecialchars($b['seat_number'], ENT_QUOTES, 'UTF-8') ?></td>
                    <td>
                        <span class="badge <?= $b['booking_status'] === 'confirmed' ? 'badge-green' : ($b['booking_status'] === 'cancelled' ? 'badge-red' : 'badge-yellow') ?>">
                            <?= htmlspecialchars($b['booking_status'], ENT_QUOTES, 'UTF-8') ?>
                        </span>
                    </td>
                    <td>
                        <?php if ($b['booking_status'] !== 'confirmed'): ?>
                            <a href="booking-update.php?id=<?= $b['id'] ?>&status=confirmed&csrf_token=<?= $_SESSION['csrf_token'] ?>"
                               class="action-link action-confirm">Confirm</a>
                        <?php endif; ?>
                        <?php if ($b['booking_status'] !== 'cancelled'): ?>
                            <a href="booking-update.php?id=<?= $b['id'] ?>&status=cancelled&csrf_token=<?= $_SESSION['csrf_token'] ?>"
                               class="action-link action-cancel"
                               onclick="return confirm('Cancel this booking?')">Cancel</a>
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
