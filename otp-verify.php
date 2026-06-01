<?php
session_set_cookie_params([
    'lifetime' => 1800,
    'httponly' => true,
    'secure'   => isset($_SERVER['HTTPS']),
    'samesite' => 'Strict'
]);

require_once 'includes/security-headers.php';

session_start();

require_once 'config/db.php';
require_once 'includes/auth.php';

// Must have gone through login first
if (empty($_SESSION['pre_auth_user_id'])) {
    header('Location: login.php');
    exit;
}

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'] ?? '')) {
        $error = 'Invalid request. Please try again.';
    } else {
        $otp_input = trim($_POST['otp'] ?? '');
        $user_id   = $_SESSION['pre_auth_user_id'];

        $stmt = $pdo->prepare('SELECT * FROM users WHERE id = ?');
        $stmt->execute([$user_id]);
        $user = $stmt->fetch();

        if (!$user || empty($user['otp_code'])) {
            $error = 'OTP expired. Please log in again.';
        } elseif (new DateTime() > new DateTime($user['otp_expires_at'])) {
            $error = 'OTP has expired. Please log in again.';
        } elseif (!password_verify($otp_input, $user['otp_code'])) {
            $error = 'Incorrect OTP code. Please try again.';
        } else {
            // OTP correct — clear it and create full session
            $stmt = $pdo->prepare('UPDATE users SET otp_code = NULL, otp_expires_at = NULL WHERE id = ?');
            $stmt->execute([$user_id]);

            session_regenerate_id(true);
            $_SESSION['authenticated'] = true;
            $_SESSION['user_id']       = $user['id'];
            $_SESSION['user_name']     = $user['name'];
            $_SESSION['role']          = $user['role'];
            $_SESSION['last_activity'] = time();

            unset($_SESSION['pre_auth_user_id']);

            log_action($pdo, $user['id'], 'Successful login');
            redirect_by_role($user['role']);
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Verify OTP — IS351 Airline System</title>
    <style>
        * { box-sizing: border-box; margin: 0; padding: 0; }
        body { font-family: sans-serif; background: #f4f6f9; display: flex; align-items: center; justify-content: center; min-height: 100vh; }
        .card { background: #fff; border-radius: 10px; padding: 2rem; width: 100%; max-width: 380px; border: 1px solid #e0e0e0; }
        h1 { font-size: 20px; margin-bottom: 6px; }
        p.sub { font-size: 13px; color: #666; margin-bottom: 1.5rem; }
        label { display: block; font-size: 13px; font-weight: 500; margin-bottom: 5px; }
        input[type=text] { width: 100%; padding: 10px 12px; border: 1px solid #ccc; border-radius: 6px; font-size: 18px; letter-spacing: 6px; text-align: center; margin-bottom: 1rem; }
        .btn { width: 100%; padding: 11px; background: #4f46e5; color: #fff; border: none; border-radius: 6px; font-size: 14px; cursor: pointer; }
        .btn:hover { background: #4338ca; }
        .error { background: #fef2f2; border: 1px solid #fca5a5; color: #b91c1c; padding: 10px 12px; border-radius: 6px; font-size: 13px; margin-bottom: 1rem; }
        .back { text-align: center; font-size: 13px; margin-top: 1rem; }
        .back a { color: #4f46e5; text-decoration: none; }
    </style>
</head>
<body>
<div class="card">
    <h1>Two-Factor Verification</h1>
    <p class="sub">Enter the 6-digit code sent to your email. It expires in 10 minutes.</p>

    <?php if ($error): ?>
        <div class="error"><?= htmlspecialchars($error, ENT_QUOTES, 'UTF-8') ?></div>
    <?php endif; ?>

    <form method="POST" action="otp-verify.php">
        <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
        <label for="otp">OTP Code</label>
        <input type="text" id="otp" name="otp" maxlength="6" placeholder="000000" autofocus required>
        <button type="submit" class="btn">Verify</button>
    </form>

    <div class="back"><a href="login.php">Back to login</a></div>
</div>
</body>
</html>
