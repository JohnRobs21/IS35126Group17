<?php
require_once 'vendor/autoload.php';
session_set_cookie_params([
    'lifetime' => 1800,
    'httponly' => true,
    'secure'   => isset($_SERVER['HTTPS']),
    'samesite' => 'Strict'
]);

session_start();

require_once 'includes/security-headers.php';
require_once 'config/db.php';

$client = new Google\Client();
$client->setClientId($_ENV['GOOGLE_CLIENT_ID'] ?? getenv('GOOGLE_CLIENT_ID'));
$client->setClientSecret($_ENV['GOOGLE_CLIENT_SECRET'] ?? getenv('GOOGLE_CLIENT_SECRET'));
$client->setRedirectUri($_ENV['GOOGLE_REDIRECT_URI'] ?? getenv('GOOGLE_REDIRECT_URI'));
$client->addScope('email');
$client->addScope('profile');

// Redirect to Google login
$auth_url = $client->createAuthUrl();
header('Location: ' . $auth_url);
exit;
