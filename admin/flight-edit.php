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

// require_role expects an array of roles; provide as array to satisfy type checks
require_role(['admin']);

if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

$error = '';

// Get flight ID
$id = (int)($_GET['id'] ?? 0);
if (!$id) {
    header('Location: flights-list.php');
    exit;
}

// Fetch existing flight
$stmt = $pdo->prepare('SELECT * FROM flights WHERE id = ?');
$stmt->execute([$id]);
$flight = $stmt->fetch();

if (!$flight) {
    header('Location: flights-list.php');
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'] ?? '')) {
        $error = 'Invalid request. Please try again.';
    } else {
        $flight_number  = strtoupper(trim($_POST['flight_number'] ?? ''));
        $origin         = trim($_POST['origin'] ?? '');
        $destination    = trim($_POST['destination'] ?? '');
        $departure_time = trim($_POST['departure_time'] ?? '');
        $arrival_time   = trim($_POST['arrival_time'] ?? '');
        $seats          = (int)($_POST['seats_available'] ?? 0);
        $price          = (float)($_POST['price'] ?? 0);
        $status         = $_POST['status'] ?? 'scheduled';

        if (empty($flight_number) || empty($origin) || empty($destination) || empty($departure_time) || empty($arrival_time)) {
            $error = 'All fields are required.';
        } elseif ($seats <= 0) {
            $error = 'Seats available must be greater than 0.';
        } elseif ($price <= 0) {
            $error = 'Price must be greater than 0.';
        } elseif ($departure_time >= $arrival_time) {
            $error = 'Arrival time must be after departure time.';
        } elseif (!in_array($status, ['scheduled', 'cancelled', 'completed'])) {
            $error = 'Invalid status.';
        } else {
            // Check duplicate flight number (excluding this flight)
            $stmt = $pdo->prepare('SELECT id FROM flights WHERE flight_number = ? AND id != ?');
            $stmt->execute([$flight_number, $id]);
            if ($stmt->fetch()) {
                $error = 'Another flight with this number already exists.';
            } else {
                $stmt = $pdo->prepare('
                    UPDATE flights
                    SET flight_number = ?, origin = ?, destination = ?,
                        departure_time = ?, arrival_time = ?,
                        seats_available = ?, price = ?, status = ?
                    WHERE id = ?
                ');
                $stmt->execute([$flight_number, $origin, $destination, $departure_time, $arrival_time, $seats, $price, $status, $id]);
                log_action($pdo, $_SESSION['user_id'], 'Admin edited flight ID: ' . $id . ' (' . $flight_number . ')');
                header('Location: flights-list.php?saved=1');
                exit;
            }
        }

        // Repopulate $flight with submitted values on error
        $flight = array_merge($flight, $_POST);
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Edit Flight — Admin</title>
    <style>
        * { box-sizing: border-box; margin: 0; padding: 0; }
        body { font-family: sans-serif; background: #f4f6f9; }
        .navbar { background: #1e1b4b; color: #fff; padding: 14px 24px; display: flex; align-items: center; justify-content: space-between; }
        .navbar h1 { font-size: 16px; font-weight: 600; }
        .navbar a { color: #a5b4fc; text-decoration: none; font-size: 13px; margin-left: 16px; }
        .navbar a:hover { color: #fff; }
        .container { max-width: 700px; margin: 0 auto; padding: 24px; }
        .breadcrumb { font-size: 13px; color: #6b7280; margin-bottom: 16px; }
        .breadcrumb a { color: #4f46e5; text-decoration: none; }
        .page-title { font-size: 20px; font-weight: 600; color: #1a1a1a; margin-bottom: 6px; }
        .sub { font-size: 13px; color: #666; margin-bottom: 24px; }
        .card { background: #fff; border: 1px solid #e5e7eb; border-radius: 10px; padding: 24px; }
        .form-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 16px; }
        .form-group { display: flex; flex-direction: column; }
        label { font-size: 13px; font-weight: 500; color: #374151; margin-bottom: 5px; }
        input, select { padding: 9px 12px; border: 1px solid #d1d5db; border-radius: 6px; font-size: 14px; color: #1a1a1a; }
        input:focus, select:focus { outline: none; border-color: #4f46e5; }
        .btn-row { display: flex; gap: 10px; margin-top: 20px; }
        .btn { padding: 10px 20px; border-radius: 6px; font-size: 14px; cursor: pointer; font-weight: 500; border: none; }
        .btn-primary { background: #4f46e5; color: #fff; }
        .btn-primary:hover { background: #4338ca; }
        .btn-secondary { background: #fff; color: #374151; border: 1px solid #d1d5db; text-decoration: none; display: inline-flex; align-items: center; }
        .btn-secondary:hover { background: #f9fafb; }
        .alert-error { background: #fef2f2; border: 1px solid #fca5a5; color: #b91c1c; padding: 10px 14px; border-radius: 6px; font-size: 13px; margin-bottom: 16px; }
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
    <div class="breadcrumb"><a href="dashboard.php">Dashboard</a> → <a href="flights-list.php">Flights</a> → Edit Flight</div>
    <div class="page-title">Edit Flight — <?= htmlspecialchars($flight['flight_number'], ENT_QUOTES, 'UTF-8') ?></div>
    <div class="sub">Update the flight details below</div>

    <?php if ($error): ?>
        <div class="alert-error"><?= htmlspecialchars($error, ENT_QUOTES, 'UTF-8') ?></div>
    <?php endif; ?>

    <div class="card">
        <form method="POST" action="flight-edit.php?id=<?= $id ?>">
            <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
            <div class="form-grid">
                <div class="form-group">
                    <label for="flight_number">Flight Number</label>
                    <input type="text" id="flight_number" name="flight_number"
                           value="<?= htmlspecialchars($flight['flight_number'], ENT_QUOTES, 'UTF-8') ?>" required>
                </div>
                <div class="form-group">
                    <label for="status">Status</label>
                    <select id="status" name="status">
                        <option value="scheduled" <?= $flight['status'] === 'scheduled' ? 'selected' : '' ?>>Scheduled</option>
                        <option value="cancelled" <?= $flight['status'] === 'cancelled' ? 'selected' : '' ?>>Cancelled</option>
                        <option value="completed" <?= $flight['status'] === 'completed' ? 'selected' : '' ?>>Completed</option>
                    </select>
                </div>
                <div class="form-group">
                    <label for="origin">Origin</label>
                    <input type="text" id="origin" name="origin"
                           value="<?= htmlspecialchars($flight['origin'], ENT_QUOTES, 'UTF-8') ?>" required>
                </div>
                <div class="form-group">
                    <label for="destination">Destination</label>
                    <input type="text" id="destination" name="destination"
                           value="<?= htmlspecialchars($flight['destination'], ENT_QUOTES, 'UTF-8') ?>" required>
                </div>
                <div class="form-group">
                    <label for="departure_time">Departure Time</label>
                    <input type="datetime-local" id="departure_time" name="departure_time"
                           value="<?= htmlspecialchars(str_replace(' ', 'T', $flight['departure_time']), ENT_QUOTES, 'UTF-8') ?>" required>
                </div>
                <div class="form-group">
                    <label for="arrival_time">Arrival Time</label>
                    <input type="datetime-local" id="arrival_time" name="arrival_time"
                           value="<?= htmlspecialchars(str_replace(' ', 'T', $flight['arrival_time']), ENT_QUOTES, 'UTF-8') ?>" required>
                </div>
                <div class="form-group">
                    <label for="seats_available">Seats Available</label>
                    <input type="number" id="seats_available" name="seats_available"
                           value="<?= $flight['seats_available'] ?>" min="1" required>
                </div>
                <div class="form-group">
                    <label for="price">Price (USD)</label>
                    <input type="number" id="price" name="price"
                           value="<?= $flight['price'] ?>" min="0.01" step="0.01" required>
                </div>
            </div>
            <div class="btn-row">
                <button type="submit" class="btn btn-primary">Save Changes</button>
                <a href="flights-list.php" class="btn btn-secondary">Cancel</a>
            </div>
        </form>
    </div>
</div>
</body>
</html>
