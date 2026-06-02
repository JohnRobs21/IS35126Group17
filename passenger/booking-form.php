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

if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

$flight_id = (int)($_GET['flight_id'] ?? 0);
if (!$flight_id) {
    header('Location: flights-search.php');
    exit;
}

// Fetch flight
$stmt = $pdo->prepare("SELECT * FROM flights WHERE id = ? AND status = 'scheduled' AND seats_available > 0");
$stmt->execute([$flight_id]);
$flight = $stmt->fetch();

if (!$flight) {
    header('Location: flights-search.php?error=unavailable');
    exit;
}

// Check if passenger already booked this flight
$stmt = $pdo->prepare("SELECT id FROM bookings WHERE user_id = ? AND flight_id = ? AND booking_status != 'cancelled'");
$stmt->execute([$_SESSION['user_id'], $flight_id]);
if ($stmt->fetch()) {
    header('Location: my-bookings.php?error=already_booked');
    exit;
}

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'] ?? '')) {
        $error = 'Invalid request. Please try again.';
    } else {
        $seat_number = strtoupper(trim($_POST['seat_number'] ?? ''));

        if (empty($seat_number)) {
            $error = 'Please enter a seat number.';
        } elseif (!preg_match('/^[A-Z]{1}[0-9]{1,2}$/', $seat_number)) {
            $error = 'Invalid seat format. Use format like A1, B12.';
        } else {
            // Check seat not already taken on this flight
            $stmt = $pdo->prepare("SELECT id FROM bookings WHERE flight_id = ? AND seat_number = ? AND booking_status != 'cancelled'");
            $stmt->execute([$flight_id, $seat_number]);
            if ($stmt->fetch()) {
                $error = 'That seat is already taken. Please choose another.';
            } else {
                // Create booking and decrement seats in a transaction
                $pdo->beginTransaction();
                try {
                    $stmt = $pdo->prepare('
                        INSERT INTO bookings (user_id, flight_id, seat_number, booking_status)
                        VALUES (?, ?, ?, "pending")
                    ');
                    $stmt->execute([$_SESSION['user_id'], $flight_id, $seat_number]);
                    $booking_id = $pdo->lastInsertId();

                    $stmt = $pdo->prepare('UPDATE flights SET seats_available = seats_available - 1 WHERE id = ?');
                    $stmt->execute([$flight_id]);

                    $pdo->commit();
                    log_action($pdo, $_SESSION['user_id'], 'Passenger booked flight ID: ' . $flight_id . ' seat: ' . $seat_number);
                    header('Location: my-bookings.php?booked=1');
                    exit;
                } catch (Exception $e) {
                    $pdo->rollBack();
                    $error = 'Booking failed. Please try again.';
                }
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Book Flight — IS351 Airline</title>
    <style>
        * { box-sizing: border-box; margin: 0; padding: 0; }
        body { font-family: sans-serif; background: #f4f6f9; }
        .navbar { background: #1d4ed8; color: #fff; padding: 14px 24px; display: flex; align-items: center; justify-content: space-between; }
        .navbar h1 { font-size: 16px; font-weight: 600; }
        .navbar a { color: #bfdbfe; text-decoration: none; font-size: 13px; margin-left: 16px; }
        .navbar a:hover { color: #fff; }
        .container { max-width: 600px; margin: 0 auto; padding: 24px; }
        .breadcrumb { font-size: 13px; color: #6b7280; margin-bottom: 16px; }
        .breadcrumb a { color: #1d4ed8; text-decoration: none; }
        .page-title { font-size: 20px; font-weight: 600; color: #1a1a1a; margin-bottom: 6px; }
        .sub { font-size: 13px; color: #666; margin-bottom: 24px; }
        .flight-summary { background: #eff6ff; border: 1px solid #bfdbfe; border-radius: 10px; padding: 18px; margin-bottom: 20px; }
        .flight-summary h3 { font-size: 15px; font-weight: 600; color: #1e40af; margin-bottom: 12px; }
        .flight-detail { display: grid; grid-template-columns: 1fr 1fr; gap: 10px; }
        .detail-label { font-size: 11px; color: #6b7280; margin-bottom: 2px; }
        .detail-value { font-size: 13px; font-weight: 500; color: #1a1a1a; }
        .price-highlight { font-size: 22px; font-weight: 700; color: #1d4ed8; }
        .card { background: #fff; border: 1px solid #e5e7eb; border-radius: 10px; padding: 24px; }
        label { display: block; font-size: 13px; font-weight: 500; color: #374151; margin-bottom: 5px; }
        input[type=text] { width: 100%; padding: 10px 12px; border: 1px solid #d1d5db; border-radius: 6px; font-size: 14px; text-transform: uppercase; letter-spacing: 2px; }
        input:focus { outline: none; border-color: #1d4ed8; }
        .hint { font-size: 11px; color: #9ca3af; margin-top: 4px; margin-bottom: 16px; }
        .confirm-box { background: #f9fafb; border: 1px solid #e5e7eb; border-radius: 8px; padding: 14px; margin-bottom: 16px; font-size: 13px; color: #374151; line-height: 1.6; }
        .btn-row { display: flex; gap: 10px; }
        .btn { padding: 10px 20px; border-radius: 6px; font-size: 14px; cursor: pointer; font-weight: 500; border: none; }
        .btn-primary { background: #1d4ed8; color: #fff; }
        .btn-primary:hover { background: #1e40af; }
        .btn-secondary { background: #fff; color: #374151; border: 1px solid #d1d5db; text-decoration: none; display: inline-flex; align-items: center; }
        .btn-secondary:hover { background: #f9fafb; }
        .alert-error { background: #fef2f2; border: 1px solid #fca5a5; color: #b91c1c; padding: 10px 14px; border-radius: 6px; font-size: 13px; margin-bottom: 16px; }
    </style>
</head>
<body>
<div class="navbar">
    <h1>IS351 Airline System</h1>
    <div>
        <a href="dashboard.php">Dashboard</a>
        <a href="../logout.php">Logout</a>
    </div>
</div>

<div class="container">
    <div class="breadcrumb"><a href="dashboard.php">Dashboard</a> → <a href="flights-search.php">Search</a> → Book Flight</div>
    <div class="page-title">Book Flight</div>
    <div class="sub">Review and confirm your booking</div>

    <div class="flight-summary">
        <h3>Flight Details</h3>
        <div class="flight-detail">
            <div class="detail-item">
                <div class="detail-label">Flight Number</div>
                <div class="detail-value"><?= htmlspecialchars($flight['flight_number'], ENT_QUOTES, 'UTF-8') ?></div>
            </div>
            <div class="detail-item">
                <div class="detail-label">Seats Available</div>
                <div class="detail-value"><?= $flight['seats_available'] ?></div>
            </div>
            <div class="detail-item">
                <div class="detail-label">From</div>
                <div class="detail-value"><?= htmlspecialchars($flight['origin'], ENT_QUOTES, 'UTF-8') ?></div>
            </div>
            <div class="detail-item">
                <div class="detail-label">To</div>
                <div class="detail-value"><?= htmlspecialchars($flight['destination'], ENT_QUOTES, 'UTF-8') ?></div>
            </div>
            <div class="detail-item">
                <div class="detail-label">Departure</div>
                <div class="detail-value"><?= htmlspecialchars($flight['departure_time'], ENT_QUOTES, 'UTF-8') ?></div>
            </div>
            <div class="detail-item">
                <div class="detail-label">Arrival</div>
                <div class="detail-value"><?= htmlspecialchars($flight['arrival_time'], ENT_QUOTES, 'UTF-8') ?></div>
            </div>
            <div class="detail-item" style="grid-column:1/-1">
                <div class="detail-label">Price</div>
                <div class="price-highlight">$<?= number_format($flight['price'], 2) ?></div>
            </div>
        </div>
    </div>

    <?php if ($error): ?>
        <div class="alert-error"><?= htmlspecialchars($error, ENT_QUOTES, 'UTF-8') ?></div>
    <?php endif; ?>

    <div class="card">
        <form method="POST" action="booking-form.php?flight_id=<?= $flight_id ?>">
            <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">

            <label for="seat_number">Choose Your Seat</label>
            <input type="text" id="seat_number" name="seat_number"
                   maxlength="3" placeholder="e.g. A1"
                   value="<?= htmlspecialchars($_POST['seat_number'] ?? '', ENT_QUOTES, 'UTF-8') ?>"
                   required autofocus>
            <p class="hint">Format: one letter followed by 1-2 numbers (e.g. A1, B12, C3)</p>

            <div class="confirm-box">
                Booking for: <strong><?= htmlspecialchars($_SESSION['user_name'], ENT_QUOTES, 'UTF-8') ?></strong><br>
                Flight: <strong><?= htmlspecialchars($flight['flight_number'], ENT_QUOTES, 'UTF-8') ?></strong>
                (<?= htmlspecialchars($flight['origin'], ENT_QUOTES, 'UTF-8') ?> →
                <?= htmlspecialchars($flight['destination'], ENT_QUOTES, 'UTF-8') ?>)<br>
                Total: <strong>$<?= number_format($flight['price'], 2) ?></strong>
            </div>

            <div class="btn-row">
                <button type="submit" class="btn btn-primary">Confirm Booking</button>
                <a href="flights-search.php" class="btn btn-secondary">Back</a>
            </div>
        </form>
    </div>
</div>
</body>
</html>
