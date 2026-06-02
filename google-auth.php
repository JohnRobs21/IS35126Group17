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

$client = new Google\Client();
$client->setClientId($_ENV['GOOGLE_CLIENT_ID']);
$client->setClientSecret($_ENV['GOOGLE_CLIENT_SECRET']);
$client->setRedirectUri($_ENV['GOOGLE_REDIRECT_URI']);
$client->addScope('email');
$client->addScope('profile');

// Redirect to Google login
$auth_url = $client->createAuthUrl();
header('Location: ' . $auth_url);
exit;
