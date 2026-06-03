<?php
if (session_status() === PHP_SESSION_NONE) {
    session_set_cookie_params([
        'lifetime' => 1800,
        'httponly' => true,
        'secure'   => true,
        'samesite' => 'Lax'
    ]);
    session_start();
}

require_once 'vendor/autoload.php';
require_once 'config/db.php';

$client = new Google\Client();
$client->setClientId(getenv('GOOGLE_CLIENT_ID'));
$client->setClientSecret(getenv('GOOGLE_CLIENT_SECRET'));
$client->setRedirectUri(getenv('GOOGLE_REDIRECT_URI'));
$client->addScope('email');
$client->addScope('profile');

$auth_url = $client->createAuthUrl();
header('Location: ' . $auth_url);
exit;