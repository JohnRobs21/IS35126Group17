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

require_role(['passenger']);
log_action($pdo, $_SESSION['user_id'], 'Passenger accessed dashboard');

// Fetch this passenger's bookings
$stmt = $pdo->prepare('
    SELECT b.*, f.flight_number, f.origin, f.destination,
           f.departure_time, f.arrival_time, f.price
    FROM bookings b
    JOIN flights f ON b.flight_id = f.id
    WHERE b.user_id = ?
    ORDER BY b.booked_at DESC
');
$stmt->execute([$_SESSION['user_id']]);
$my_bookings = $stmt->fetchAll();

// Fetch available flights
$flights = $pdo->query('
    SELECT * FROM flights
    WHERE status = "scheduled" AND seats_available > 0
    ORDER BY departure_time ASC
    LIMIT 5
')->fetchAll();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>My Dashboard — IS351 Airline</title>
    <style>
        * { box-sizing: border-box; margin: 0; padding: 0; }
        body { font-family: sans-serif; background: #f4f6f9; }
        .navbar { background: #1d4ed8; color: #fff; padding: 14px 24px; display: flex; align-items: center; justify-content: space-between; }
        .navbar h1 { font-size: 16px; font-weight: 600; }
        .navbar a { color: #bfdbfe; text-decoration: none; font-size: 13px; margin-left: 16px; }
        .navbar a:hover { color: #fff; }
        .container { max-width: 1100px; margin: 0 auto; padding: 24px; }
        .welcome { font-size: 20px; font-weight: 600; color: #1a1a1a; margin-bottom: 6px; }
        .sub { font-size: 13px; color: #666; margin-bottom: 24px; }
        .grid { display: grid; grid-template-columns: 1fr 1fr; gap: 20px; }
        @media(max-width:700px) { .grid { grid-template-columns: 1fr; } }
        .card { background: #fff; border: 1px solid #e5e7eb; border-radius: 10px; padding: 20px; margin-bottom: 20px; }
        .card-title { font-size: 15px; font-weight: 600; margin-bottom: 16px; color: #1a1a1a; display: flex; align-items: center; justify-content: space-between; }
        table { width: 100%; border-collapse: collapse; font-size: 13px; }
        th { text-align: left; padding: 8px 12px; background: #f9fafb; color: #6b7280; font-weight: 500; border-bottom: 1px solid #e5e7eb; }
        td { padding: 10px 12px; border-bottom: 1px solid #f3f4f6; color: #374151; }
        tr:last-child td { border-bottom: none; }
        .badge { display: inline-block; padding: 2px 8px; border-radius: 20px; font-size: 11px; font-weight: 500; }
        .badge-green { background: #dcfce7; color: #166534; }
        .badge-yellow { background: #fef9c3; color: #854d0e; }
        .badge-red { background: #fef2f2; color: #b91c1c; }
        .btn-book { background: #1d4ed8; color: #fff; padding: 6px 14px; border-radius: 6px; text-decoration: none; font-size: 12px; }
        .btn-book:hover { background: #1e40af; }
        .nav-link { background: #1d4ed8; color: #fff; padding: 8px 16px; border-radius: 6px; text-decoration: none; font-size: 13px; display: inline-block; margin-bottom: 20px; }
        .nav-link:hover { background: #1e40af; }
        .empty { font-size: 13px; color: #999; }
    </style>
</head>
<body>
<div class="navbar">
    <h1>IS351 Airline System</h1>
    <div>
        <span style="font-size:13px;color:#bfdbfe">Welcome, <?= htmlspecialchars($_SESSION['user_name'], ENT_QUOTES, 'UTF-8') ?></span>
        <a href="../logout.php">Logout</a>
    </div>
</div>

<div class="container">
    <div class="welcome">My Dashboard</div>
    <div class="sub">Book flights and manage your reservations</div>

    <a href="flights-search.php" class="nav-link">Search & Book Flights</a>

    <div class="grid">
        <div class="card">
            <div class="card-title">My Bookings</div>
            <?php if (empty($my_bookings)): ?>
                <p class="empty">You have no bookings yet. <a href="flights-search.php">Book a flight</a>.</p>
            <?php else: ?>
            <table>
                <thead>
                    <tr>
                        <th>Flight</th>
                        <th>Route</th>
                        <th>Date</th>
                        <th>Status</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($my_bookings as $b): ?>
                    <tr>
                        <td><?= htmlspecialchars($b['flight_number'], ENT_QUOTES, 'UTF-8') ?></td>
                        <td><?= htmlspecialchars($b['origin'], ENT_QUOTES, 'UTF-8') ?> → <?= htmlspecialchars($b['destination'], ENT_QUOTES, 'UTF-8') ?></td>
                        <td><?= htmlspecialchars($b['departure_time'], ENT_QUOTES, 'UTF-8') ?></td>
                        <td>
                            <span class="badge <?= $b['booking_status'] === 'confirmed' ? 'badge-green' : ($b['booking_status'] === 'cancelled' ? 'badge-red' : 'badge-yellow') ?>">
                                <?= htmlspecialchars($b['booking_status'], ENT_QUOTES, 'UTF-8') ?>
                            </span>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
            <?php endif; ?>
        </div>

        <div class="card">
            <div class="card-title">Available Flights</div>
            <?php if (empty($flights)): ?>
                <p class="empty">No flights available right now.</p>
            <?php else: ?>
            <table>
                <thead>
                    <tr>
                        <th>Flight</th>
                        <th>Route</th>
                        <th>Price</th>
                        <th></th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($flights as $f): ?>
                    <tr>
                        <td><?= htmlspecialchars($f['flight_number'], ENT_QUOTES, 'UTF-8') ?></td>
                        <td><?= htmlspecialchars($f['origin'], ENT_QUOTES, 'UTF-8') ?> → <?= htmlspecialchars($f['destination'], ENT_QUOTES, 'UTF-8') ?></td>
                        <td>$<?= number_format($f['price'], 2) ?></td>
                        <td><a href="booking-form.php?flight_id=<?= $f['id'] ?>" class="btn-book">Book</a></td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
            <?php endif; ?>
        </div>
    </div>
</div>
</body>
</html>
