<?php
<?php
ini_set('display_errors', 1);
error_reporting(E_ALL);

// Remove the debug die() and add proper session handling
if (session_status() === PHP_SESSION_NONE) {
    session_set_cookie_params([
        'lifetime' => 1800,
        'httponly' => true,
        'secure'   => true,
        'samesite' => 'Lax' // Change from Strict to Lax for OAuth
    ]);
    session_start();
}

require_once 'vendor/autoload.php';
require_once 'config/db.php';
require_once 'includes/auth.php';

$client = new Google\Client();
$client->setClientId(getenv('GOOGLE_CLIENT_ID'));
$client->setClientSecret(getenv('GOOGLE_CLIENT_SECRET'));
$client->setRedirectUri(getenv('GOOGLE_REDIRECT_URI'));
$client->addScope('email');
$client->addScope('profile');

if (isset($_GET['error'])) {
    die('Google error: ' . $_GET['error']);
}

if (!isset($_GET['code'])) {
    die('No code received from Google');
}

try {
    $token = $client->fetchAccessTokenWithAuthCode($_GET['code']);

    if (isset($token['error'])) {
        die('Token error: ' . print_r($token, true));
    }

    $client->setAccessToken($token);

    $google_service = new Google\Service\Oauth2($client);
    $google_user    = $google_service->userinfo->get();

    $google_id    = $google_user->getId();
    $google_email = $google_user->getEmail();
    $google_name  = $google_user->getName();

    if (empty($google_email)) {
        die('Could not get email from Google');
    }

    $stmt = $pdo->prepare('SELECT * FROM users WHERE google_id = ? OR email = ?');
    $stmt->execute([$google_id, $google_email]);
    $user = $stmt->fetch();

    if ($user) {
        if (empty($user['google_id'])) {
            $stmt = $pdo->prepare('UPDATE users SET google_id = ? WHERE id = ?');
            $stmt->execute([$google_id, $user['id']]);
        }
    } else {
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

    session_regenerate_id(true);
    $_SESSION['authenticated'] = true;
    $_SESSION['user_id']       = $user['id'];
    $_SESSION['user_name']     = $user['name'];
    $_SESSION['role']          = $user['role'];
    $_SESSION['last_activity'] = time();

    log_action($pdo, $user['id'], 'Logged in via Google OAuth');
    redirect_by_role($user['role']);

} catch (Exception $e) {
    die('Exception: ' . $e->getMessage() . '<br>File: ' . $e->getFile() . '<br>Line: ' . $e->getLine());
}