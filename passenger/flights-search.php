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

$origin      = trim($_GET['origin'] ?? '');
$destination = trim($_GET['destination'] ?? '');
$date        = trim($_GET['date'] ?? '');

$where  = ["f.status = 'scheduled'", "f.seats_available > 0"];
$params = [];

if ($origin) {
    $where[]  = 'f.origin LIKE ?';
    $params[] = '%' . $origin . '%';
}
if ($destination) {
    $where[]  = 'f.destination LIKE ?';
    $params[] = '%' . $destination . '%';
}
if ($date) {
    $where[]  = 'DATE(f.departure_time) = ?';
    $params[] = $date;
}

$sql = 'SELECT * FROM flights f WHERE ' . implode(' AND ', $where) . ' ORDER BY f.departure_time ASC';
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$flights = $stmt->fetchAll();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Search Flights — IS351 Airline</title>
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
        .search-card { background: #fff; border: 1px solid #e5e7eb; border-radius: 10px; padding: 20px; margin-bottom: 20px; }
        .search-grid { display: grid; grid-template-columns: 1fr 1fr 1fr auto; gap: 12px; align-items: end; }
        @media(max-width:700px) { .search-grid { grid-template-columns: 1fr; } }
        label { display: block; font-size: 12px; font-weight: 500; color: #374151; margin-bottom: 4px; }
        input { width: 100%; padding: 9px 12px; border: 1px solid #d1d5db; border-radius: 6px; font-size: 13px; }
        input:focus { outline: none; border-color: #1d4ed8; }
        .btn-search { padding: 9px 20px; background: #1d4ed8; color: #fff; border: none; border-radius: 6px; font-size: 13px; cursor: pointer; white-space: nowrap; }
        .btn-search:hover { background: #1e40af; }
        .results-card { background: #fff; border: 1px solid #e5e7eb; border-radius: 10px; overflow: hidden; }
        .results-header { padding: 16px 20px; border-bottom: 1px solid #f3f4f6; font-size: 14px; font-weight: 600; color: #1a1a1a; }
        table { width: 100%; border-collapse: collapse; font-size: 13px; }
        th { text-align: left; padding: 10px 16px; background: #f9fafb; color: #6b7280; font-weight: 500; border-bottom: 1px solid #e5e7eb; }
        td { padding: 12px 16px; border-bottom: 1px solid #f3f4f6; color: #374151; vertical-align: middle; }
        tr:last-child td { border-bottom: none; }
        tr:hover td { background: #fafafa; }
        .btn-book { background: #1d4ed8; color: #fff; padding: 6px 14px; border-radius: 6px; text-decoration: none; font-size: 12px; font-weight: 500; }
        .btn-book:hover { background: #1e40af; }
        .price { font-weight: 600; color: #1a1a1a; }
        .seats { color: #059669; font-weight: 500; }
        .empty { padding: 40px; text-align: center; color: #9ca3af; font-size: 13px; }
        .route { display: flex; align-items: center; gap: 8px; }
        .arrow { color: #9ca3af; }
    </style>
</head>
<body>
<div class="navbar">
    <h1>IS351 Airline System</h1>
    <div>
        <a href="dashboard.php">Dashboard</a>
        <a href="my-bookings.php">My Bookings</a>
        <a href="../logout.php">Logout</a>
    </div>
</div>

<div class="container">
    <div class="breadcrumb"><a href="dashboard.php">Dashboard</a> → Search Flights</div>
    <div class="page-title">Search Flights</div>
    <div class="sub">Find and book available flights</div>

    <div class="search-card">
        <form method="GET" action="flights-search.php">
            <div class="search-grid">
                <div>
                    <label for="origin">From</label>
                    <input type="text" id="origin" name="origin"
                           placeholder="e.g. Suva"
                           value="<?= htmlspecialchars($origin, ENT_QUOTES, 'UTF-8') ?>">
                </div>
                <div>
                    <label for="destination">To</label>
                    <input type="text" id="destination" name="destination"
                           placeholder="e.g. Auckland"
                           value="<?= htmlspecialchars($destination, ENT_QUOTES, 'UTF-8') ?>">
                </div>
                <div>
                    <label for="date">Date</label>
                    <input type="date" id="date" name="date"
                           value="<?= htmlspecialchars($date, ENT_QUOTES, 'UTF-8') ?>">
                </div>
                <div>
                    <button type="submit" class="btn-search">Search</button>
                </div>
            </div>
        </form>
    </div>

    <div class="results-card">
        <div class="results-header">
            <?= count($flights) ?> flight(s) found
        </div>
        <?php if (empty($flights)): ?>
            <div class="empty">No flights found matching your search. Try different dates or locations.</div>
        <?php else: ?>
        <table>
            <thead>
                <tr>
                    <th>Flight</th>
                    <th>Route</th>
                    <th>Departure</th>
                    <th>Arrival</th>
                    <th>Seats Left</th>
                    <th>Price</th>
                    <th></th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($flights as $f): ?>
                <tr>
                    <td><strong><?= htmlspecialchars($f['flight_number'], ENT_QUOTES, 'UTF-8') ?></strong></td>
                    <td>
                        <div class="route">
                            <?= htmlspecialchars($f['origin'], ENT_QUOTES, 'UTF-8') ?>
                            <span class="arrow">→</span>
                            <?= htmlspecialchars($f['destination'], ENT_QUOTES, 'UTF-8') ?>
                        </div>
                    </td>
                    <td><?= htmlspecialchars($f['departure_time'], ENT_QUOTES, 'UTF-8') ?></td>
                    <td><?= htmlspecialchars($f['arrival_time'], ENT_QUOTES, 'UTF-8') ?></td>
                    <td><span class="seats"><?= $f['seats_available'] ?></span></td>
                    <td><span class="price">$<?= number_format($f['price'], 2) ?></span></td>
                    <td><a href="booking-form.php?flight_id=<?= $f['id'] ?>" class="btn-book">Book Now</a></td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
        <?php endif; ?>
    </div>
</div>
</body>
</html>