<?php
session_set_cookie_params([
    'lifetime' => 1800,
    'httponly' => true,
    'secure'   => isset($_SERVER['HTTPS']),
    'samesite' => 'Strict'
]);

require_once 'includes/security-headers.php';

session_start();

// TEMP DEBUG
if (!isset($_GET['code'])) {
    die('No code. GET params: ' . print_r($_GET, true) . ' | REQUEST_URI: ' . $_SERVER['REQUEST_URI']);
}

require_once 'vendor/autoload.php';
require_once 'config/db.php';
require_once 'includes/auth.php';

$client = new Google\Client();
$client->setClientId($_ENV['GOOGLE_CLIENT_ID'] ?? getenv('GOOGLE_CLIENT_ID'));
$client->setClientSecret($_ENV['GOOGLE_CLIENT_SECRET'] ?? getenv('GOOGLE_CLIENT_SECRET'));
$client->setRedirectUri($_ENV['GOOGLE_REDIRECT_URI'] ?? getenv('GOOGLE_REDIRECT_URI'));

$error = '';

if (isset($_GET['error'])) {
    header('Location: login.php?error=google_cancelled');
    exit;
}

if (!isset($_GET['code'])) {
    header('Location: login.php?error=google_failed');
    exit;
}

try {
    // Exchange code for token
    $token = $client->fetchAccessTokenWithAuthCode($_GET['code']);

    if (isset($token['error'])) {
        throw new Exception('Token error: ' . $token['error']);
    }

    $client->setAccessToken($token);

    // Get user info from Google
    $google_service = new Google\Service\Oauth2($client);
    $google_user    = $google_service->userinfo->get();

    $google_id    = $google_user->getId();
    $google_email = $google_user->getEmail();
    $google_name  = $google_user->getName();

    if (empty($google_email)) {
        throw new Exception('Could not retrieve email from Google.');
    }

    // Check if user exists by google_id or email
    $stmt = $pdo->prepare('SELECT * FROM users WHERE google_id = ? OR email = ?');
    $stmt->execute([$google_id, $google_email]);
    $user = $stmt->fetch();

    if ($user) {
        // Update google_id if they registered normally before
        if (empty($user['google_id'])) {
            $stmt = $pdo->prepare('UPDATE users SET google_id = ? WHERE id = ?');
            $stmt->execute([$google_id, $user['id']]);
        }
    } else {
        // Create new passenger account
        $stmt = $pdo->prepare('
            INSERT INTO users (name, email, google_id, role)
            VALUES (?, ?, ?, "passenger")
        ');
        $stmt->execute([$google_name, $google_email, $google_id]);
        $user = [
            'id'   => $pdo->lastInsertId(),
            'name' => $google_name,
            'role' => 'passenger'
        ];
        log_action($pdo, $user['id'], 'New account created via Google OAuth');
    }

    // Set session — Google login skips OTP
    session_regenerate_id(true);
    $_SESSION['authenticated'] = true;
    $_SESSION['user_id']       = $user['id'];
    $_SESSION['user_name']     = $user['name'];
    $_SESSION['role']          = $user['role'];
    $_SESSION['last_activity'] = time();

    log_action($pdo, $user['id'], 'Logged in via Google OAuth');
    redirect_by_role($user['role']);

} catch (Exception $e) {
    //header('Location: login.php?error=google_failed');
    //exit;
    die('Google error: ' . $e->getMessage());
}
