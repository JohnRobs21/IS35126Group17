<?php
// 1. Load dependencies first
require_once __DIR__ . '/vendor/autoload.php';

// 2. Configure session cookie rules BEFORE anything else runs
session_set_cookie_params([
    'lifetime' => 1800,
    'httponly' => true,
    'secure'   => true, // Force true since you're live on HTTPS on Railway now
    'samesite' => 'Strict'
]);

// 3. Start the session safely
session_start();

// 4. NOW inject your security headers file
require_once __DIR__ . '/includes/security-headers.php';

// 5. Connect your system database and helper utilities
require_once __DIR__ . '/config/db.php';
require_once __DIR__ . '/includes/auth.php';

if (isset($_SESSION['user_id'])) {
    log_action($pdo, $_SESSION['user_id'], 'User logged out');
}

session_unset();
session_destroy();

// Expire the session cookie
setcookie(session_name(), '', time() - 3600, '/');

header('Location: login.php');
exit;
