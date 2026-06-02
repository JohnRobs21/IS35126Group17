<?php
session_set_cookie_params([
    'lifetime' => 1800,
    'httponly' => true,
    'secure'   => isset($_SERVER['HTTPS']),
    'samesite' => 'Strict'
]);

require_once __DIR__ . '/../includes/security-headers.php';   

session_start();

require_once 'config/db.php';
require_once 'includes/auth.php';

if (isset($_SESSION['user_id'])) {
    log_action($pdo, $_SESSION['user_id'], 'User logged out');
}

session_unset();
session_destroy();

// Expire the session cookie
setcookie(session_name(), '', time() - 3600, '/');

header('Location: login.php');
exit;
