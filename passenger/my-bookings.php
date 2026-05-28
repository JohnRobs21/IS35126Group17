<?php
session_set_cookie_params([
    'lifetime' => 1800,
    'httponly' => true,
    'secure'   => isset($_SERVER['HTTPS']),
    'samesite' => 'Strict'
]);

require_once 'includes/security-headers.php';

session_start();

require_once '../config/db.php';
require_once '../includes/auth.php';

require_role(['passenger']);

if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

// Handle cancel booking
if (isset($_GET['cancel']) && is_numeric($_GET['cancel'])) {
    if (!hash_equals($_SESSION['csrf_token'], $_GET['csrf_token'] ?? '')) {
        $cancel_error = 'Invalid request.';
    } else {
        $booking_id = (int)$_GET['cancel'];
        // Make sure this booking belongs to this user
        $stmt = $pdo->prepare("SELECT * FROM bookings WHERE id = ? AND user_id = ? AND booking_status != 'cancelled'");
        $stmt->execute([$booking_id, $_SESSION['user_id']]);
        $booking = $stmt->fetch();

        if ($booking) {
            $pdo->beginTransaction();
            try {
                $stmt = $pdo->prepare("UPDATE bookings SET booking_status = 'cancelled' WHERE id = ?");
                $stmt->execute([$booking_id]);
                // Return seat to flight
                $stmt = $pdo->prepare('UPDATE flights SET seats_available = seats_available + 1 WHERE id = ?');
                $stmt->execute([$booking['flight_id']]);
                $pdo->commit();
                log_action($pdo, $_SESSION['user_id'], 'Passenger cancelled booking ID: ' . $booking_id);
                header('Location: my-bookings.php?cancelled=1');
                exit;
            } catch (Exception $e) {
                $pdo->rollBack();
                $cancel_error = 'Could not cancel booking. Please try again.';
            }
        }
    }
}

// Fetch all bookings for this passenger
$stmt = $pdo->prepare('
    SELECT b.*, f.flight_number, f.origin, f.destination,
           f.departure_time, f.arrival_time, f.price, f.status AS flight_status
    FROM bookings b
    JOIN flights f ON b.flight_id = f.id
    WHERE b.user_id = ?
    ORDER BY b.booked_at DESC
');
$stmt->execute([$_SESSION['user_id']]);
$bookings = $stmt->fetchAll();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>My Bookings — IS351 Airline</title>
    <style>
        * { box-sizing: border-box; margin: 0; padding: 0; }
        body { font-family: sans-serif; background: #f4f6f9; }
        .navbar { background: #1d4ed8; color: #fff; padding: 14px 24px; display: flex; align-items: center; justify-content: space-between; }
        .navbar h1 { font-size: 16px; font-weight: 600; }
        .navbar a { color: #bfdbfe; text-decoration: none; font-size: 13px; margin-left: 16px; }
        .navbar a:hover { color: #fff; }
        .container { max-width: 1000px; margin: 0 auto; padding: 24px; }
        .breadcrumb { font-size: 13px; color: #6b7280; margin-bottom: 16px; }
        .breadcrumb a { color: #1d4ed8; text-decoration: none; }
        .page-title { font-size: 20px; font-weight: 600; color: #1a1a1a; margin-bottom: 6px; }
        .sub { font-size: 13px; color: #666; margin-bottom: 24px; }
        .alert { padding: 10px 14px; border-radius: 6px; font-size: 13px; margin-bottom: 16px; }
        .alert-success { background: #f0fdf4; border: 1px solid #86efac; color: #166534; }
        .alert-error { background: #fef2f2; border: 1px solid #fca5a5; color: #b91c1c; }
        .booking-card { background: #fff; border: 1px solid #e5e7eb; border-radius: 10px; padding: 20px; margin-bottom: 14px; }
        .booking-header { display: flex; align-items: center; justify-content: space-between; margin-bottom: 14px; flex-wrap: wrap; gap: 8px; }
        .flight-num { font-size: 16px; font-weight: 600; color: #1a1a1a; }
        .badge { display: inline-block; padding: 3px 10px; border-radius: 20px; font-size: 12px; font-weight: 500; }
        .badge-green { background: #dcfce7; color: #166534; }
        .badge-yellow { background: #fef9c3; color: #854d0e; }
        .badge-red { background: #fef2f2; color: #b91c1c; }
        .booking-details { display: grid; grid-template-columns: repeat(auto-fit, minmax(140px, 1fr)); gap: 12px; margin-bottom: 14px; }
        .detail-label { font-size: 11px; color: #6b7280; margin-bottom: 2px; }
        .detail-value { font-size: 13px; font-weight: 500; color: #1a1a1a; }
        .booking-footer { border-top: 1px solid #f3f4f6; padding-top: 12px; display: flex; align-items: center; justify-content: space-between; }
        .booked-at { font-size: 12px; color: #9ca3af; }
        .btn-cancel { color: #b91c1c; font-size: 12px; text-decoration: none; border: 1px solid #fca5a5; padding: 5px 12px; border-radius: 6px; }
        .btn-cancel:hover { background: #fef2f2; }
        .btn-book { background: #1d4ed8; color: #fff; padding: 8px 16px; border-radius: 6px; text-decoration: none; font-size: 13px; display: inline-block; margin-bottom: 20px; }
        .empty { text-align: center; padding: 48px; color: #9ca3af; font-size: 13px; }
        .route { font-size: 14px; color: #374151; }
    </style>
</head>
<body>
<div class="navbar">
    <h1>IS351 Airline System</h1>
    <div>
        <a href="dashboard.php">Dashboard</a>
        <a href="flights-search.php">Search Flights</a>
        <a href="../logout.php">Logout</a>
    </div>
</div>

<div class="container">
    <div class="breadcrumb"><a href="dashboard.php">Dashboard</a> → My Bookings</div>
    <div class="page-title">My Bookings</div>
    <div class="sub">View and manage your flight reservations</div>

    <?php if (isset($_GET['booked'])): ?>
        <div class="alert alert-success">Your flight has been booked successfully! Status is pending until confirmed by staff.</div>
    <?php endif; ?>
    <?php if (isset($_GET['cancelled'])): ?>
        <div class="alert alert-success">Your booking has been cancelled.</div>
    <?php endif; ?>
    <?php if (!empty($cancel_error)): ?>
        <div class="alert alert-error"><?= htmlspecialchars($cancel_error, ENT_QUOTES, 'UTF-8') ?></div>
    <?php endif; ?>

    <a href="flights-search.php" class="btn-book">+ Book a New Flight</a>

    <?php if (empty($bookings)): ?>
        <div class="empty">You have no bookings yet.<br><a href="flights-search.php" style="color:#1d4ed8">Search for a flight to get started</a></div>
    <?php else: ?>
        <?php foreach ($bookings as $b): ?>
        <div class="booking-card">
            <div class="booking-header">
                <div>
                    <div class="flight-num"><?= htmlspecialchars($b['flight_number'], ENT_QUOTES, 'UTF-8') ?></div>
                    <div class="route">
                        <?= htmlspecialchars($b['origin'], ENT_QUOTES, 'UTF-8') ?> →
                        <?= htmlspecialchars($b['destination'], ENT_QUOTES, 'UTF-8') ?>
                    </div>
                </div>
                <span class="badge <?= $b['booking_status'] === 'confirmed' ? 'badge-green' : ($b['booking_status'] === 'cancelled' ? 'badge-red' : 'badge-yellow') ?>">
                    <?= htmlspecialchars($b['booking_status'], ENT_QUOTES, 'UTF-8') ?>
                </span>
            </div>

            <div class="booking-details">
                <div>
                    <div class="detail-label">Seat</div>
                    <div class="detail-value"><?= htmlspecialchars($b['seat_number'], ENT_QUOTES, 'UTF-8') ?></div>
                </div>
                <div>
                    <div class="detail-label">Departure</div>
                    <div class="detail-value"><?= htmlspecialchars($b['departure_time'], ENT_QUOTES, 'UTF-8') ?></div>
                </div>
                <div>
                    <div class="detail-label">Arrival</div>
                    <div class="detail-value"><?= htmlspecialchars($b['arrival_time'], ENT_QUOTES, 'UTF-8') ?></div>
                </div>
                <div>
                    <div class="detail-label">Price Paid</div>
                    <div class="detail-value">$<?= number_format($b['price'], 2) ?></div>
                </div>
            </div>

            <div class="booking-footer">
                <span class="booked-at">Booked on <?= htmlspecialchars($b['booked_at'], ENT_QUOTES, 'UTF-8') ?></span>
                <?php if ($b['booking_status'] !== 'cancelled' && $b['flight_status'] === 'scheduled'): ?>
                    <a href="my-bookings.php?cancel=<?= $b['id'] ?>&csrf_token=<?= $_SESSION['csrf_token'] ?>"
                       class="btn-cancel"
                       onclick="return confirm('Are you sure you want to cancel this booking?')">
                        Cancel Booking
                    </a>
                <?php endif; ?>
            </div>
        </div>
        <?php endforeach; ?>
    <?php endif; ?>
</div>
</body>
</html>