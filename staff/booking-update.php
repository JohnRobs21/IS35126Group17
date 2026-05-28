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

require_role(['staff']);

// Validate CSRF
if (!hash_equals($_SESSION['csrf_token'], $_GET['csrf_token'] ?? '')) {
    header('Location: bookings-list.php');
    exit;
}

$id     = (int)($_GET['id'] ?? 0);
$status = $_GET['status'] ?? '';

if (!$id || !in_array($status, ['confirmed', 'cancelled'])) {
    header('Location: bookings-list.php');
    exit;
}

// Fetch the booking
$stmt = $pdo->prepare('SELECT * FROM bookings WHERE id = ?');
$stmt->execute([$id]);
$booking = $stmt->fetch();

if (!$booking) {
    header('Location: bookings-list.php');
    exit;
}

// If cancelling a confirmed/pending booking, restore the seat
if ($status === 'cancelled' && $booking['booking_status'] !== 'cancelled') {
    $pdo->beginTransaction();
    try {
        $stmt = $pdo->prepare("UPDATE bookings SET booking_status = 'cancelled' WHERE id = ?");
        $stmt->execute([$id]);
        $stmt = $pdo->prepare('UPDATE flights SET seats_available = seats_available + 1 WHERE id = ?');
        $stmt->execute([$booking['flight_id']]);
        $pdo->commit();
    } catch (Exception $e) {
        $pdo->rollBack();
        header('Location: bookings-list.php?error=1');
        exit;
    }
} else {
    $stmt = $pdo->prepare('UPDATE bookings SET booking_status = ? WHERE id = ?');
    $stmt->execute([$status, $id]);
}

log_action($pdo, $_SESSION['user_id'], 'Staff updated booking ID: ' . $id . ' to ' . $status);
header('Location: bookings-list.php?updated=1');
exit;