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

// Redirect already logged in users
if (isset($_SESSION['authenticated']) && $_SESSION['authenticated'] === true) {
    redirect_by_role($_SESSION['role']);
}

// Generate CSRF token
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    // Verify CSRF token
    if (!hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'] ?? '')) {
        $error = 'Invalid request. Please try again.';
    } else {
        $email    = trim($_POST['email'] ?? '');
        $password = $_POST['password'] ?? '';

        if (empty($email) || empty($password)) {
            $error = 'Please enter your email and password.';
        } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $error = 'Invalid email format.';
        } else {
            // Fetch user
            $stmt = $pdo->prepare('SELECT * FROM users WHERE email = ?');
            $stmt->execute([$email]);
            $user = $stmt->fetch();

            if (!$user) {
                $error = 'Invalid email or password.';
            } elseif ($user['locked_until'] && new DateTime() < new DateTime($user['locked_until'])) {
                // Account is locked
                $remaining = (new DateTime($user['locked_until']))->diff(new DateTime());
                $error = 'Account locked. Try again in ' . $remaining->i . ' minute(s).';
            } elseif (!password_verify($password, $user['password_hash'])) {
                // Wrong password — increment attempts
                $attempts = $user['login_attempts'] + 1;

                if ($attempts >= 5) {
                    // Lock the account for 15 minutes
                    $locked_until = (new DateTime())->modify('+15 minutes')->format('Y-m-d H:i:s');
                    $stmt = $pdo->prepare('UPDATE users SET login_attempts = ?, locked_until = ? WHERE id = ?');
                    $stmt->execute([$attempts, $locked_until, $user['id']]);
                    $error = 'Too many failed attempts. Account locked for 15 minutes.';
                    log_action($pdo, $user['id'], 'Account locked after 5 failed login attempts');
                } else {
                    $stmt = $pdo->prepare('UPDATE users SET login_attempts = ? WHERE id = ?');
                    $stmt->execute([$attempts, $user['id']]);
                    $remaining = 5 - $attempts;
                    $error = 'Invalid email or password. ' . $remaining . ' attempt(s) remaining.';
                }
            } else {
                // Password correct — reset attempts and send OTP
                $stmt = $pdo->prepare('UPDATE users SET login_attempts = 0, locked_until = NULL WHERE id = ?');
                $stmt->execute([$user['id']]);

                // Generate OTP
                $otp = (string)rand(100000, 999999);
                $otp_expiry = (new DateTime())->modify('+10 minutes')->format('Y-m-d H:i:s');

                // Save OTP to DB
                $stmt = $pdo->prepare("UPDATE users SET otp_code = ?, otp_expires_at = ? WHERE id = ?");
                $stmt->execute([$otp, $otp_expiry, $user['id']]);

                require __DIR__ . '/vendor/autoload.php';

                $email = new \SendGrid\Mail\Mail();

                $email->setFrom(
                    getenv('SENDGRID_FROM_EMAIL'),
                    getenv('SENDGRID_FROM_NAME')
                );

                $email->setSubject("Your Login OTP Code — IS351 Airline");
                $email->addTo($user['email'], $user['name']);

                $email->addContent(
                    "text/html",
                    "
                    <div style='font-family:sans-serif'>
                        <h2>IS351 Airline System</h2>
                        <p>Hello <b>{$user['name']}</b>,</p>

                        <p>Your OTP code is:</p>

                        <h1 style='letter-spacing:5px'>{$otp}</h1>

                        <p>Expires in 10 minutes.</p>
                    </div>
                    "
                );

                $sendgrid = new \SendGrid(getenv('SENDGRID_API_KEY'));

                try {
                    $response = $sendgrid->send($email);

                    if ($response->statusCode() >= 200 && $response->statusCode() < 300) {
                        $_SESSION['pre_auth_user_id'] = $user['id'];
                        header("Location: otp-verify.php");
                        exit;
                    } else {
                        $error = "Failed to send OTP email. SendGrid error.";
                    }

                } catch (Exception $e) {
                    $error = "SendGrid Exception: " . $e->getMessage();
                }

                if (empty($error)) {
                    // Store user ID in session temporarily until OTP verified
                    $_SESSION['pre_auth_user_id'] = $user['id'];
                    log_action($pdo, $user['id'], 'OTP sent — login in progress');
                    header('Location: otp-verify.php');
                    exit;
                }
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login — IS351 Airline System</title>
    <style>
        * { box-sizing: border-box; margin: 0; padding: 0; }
        body { font-family: sans-serif; background: #f4f6f9; display: flex; align-items: center; justify-content: center; min-height: 100vh; }
        .card { background: #fff; border-radius: 10px; padding: 2rem; width: 100%; max-width: 400px; border: 1px solid #e0e0e0; }
        h1 { font-size: 22px; margin-bottom: 6px; color: #1a1a1a; }
        p.sub { font-size: 13px; color: #666; margin-bottom: 1.5rem; }
        label { display: block; font-size: 13px; font-weight: 500; color: #333; margin-bottom: 5px; }
        input[type=email], input[type=password] { width: 100%; padding: 10px 12px; border: 1px solid #ccc; border-radius: 6px; font-size: 14px; margin-bottom: 1rem; }
        input:focus { outline: none; border-color: #4f46e5; }
        .btn { width: 100%; padding: 11px; background: #4f46e5; color: #fff; border: none; border-radius: 6px; font-size: 14px; cursor: pointer; font-weight: 500; }
        .btn:hover { background: #4338ca; }
        .error { background: #fef2f2; border: 1px solid #fca5a5; color: #b91c1c; padding: 10px 12px; border-radius: 6px; font-size: 13px; margin-bottom: 1rem; }
        .divider { text-align: center; color: #999; font-size: 13px; margin: 1rem 0; }
        .google-btn { width: 100%; padding: 10px; background: #fff; border: 1px solid #ccc; border-radius: 6px; font-size: 14px; cursor: pointer; display: flex; align-items: center; justify-content: center; gap: 8px; }
        .google-btn:hover { background: #f9fafb; }
        .register-link { text-align: center; font-size: 13px; color: #666; margin-top: 1.2rem; }
        .register-link a { color: #4f46e5; text-decoration: none; }
    </style>
</head>
<body>
<div class="card">
    <h1>Airline System</h1>
    <p class="sub">Sign in to your account</p>

    <?php if ($error): ?>
        <div class="error"><?= htmlspecialchars($error, ENT_QUOTES, 'UTF-8') ?></div>
    <?php endif; ?>

    <form method="POST" action="login.php">
        <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">

        <label for="email">Email address</label>
        <input type="email" id="email" name="email"
               value="<?= htmlspecialchars($_POST['email'] ?? '', ENT_QUOTES, 'UTF-8') ?>"
               required autofocus>

        <label for="password">Password</label>
        <input type="password" id="password" name="password" required>

        <button type="submit" class="btn">Sign in</button>
    </form>

    <div class="divider">or</div>

    <a href="google-auth.php">
        <button class="google-btn">
            <svg width="18" height="18" viewBox="0 0 48 48">
                <path fill="#EA4335" d="M24 9.5c3.54 0 6.71 1.22 9.21 3.6l6.85-6.85C35.9 2.38 30.47 0 24 0 14.62 0 6.51 5.38 2.56 13.22l7.98 6.19C12.43 13.72 17.74 9.5 24 9.5z"/>
                <path fill="#4285F4" d="M46.98 24.55c0-1.57-.15-3.09-.38-4.55H24v9.02h12.94c-.58 2.96-2.26 5.48-4.78 7.18l7.73 6c4.51-4.18 7.09-10.36 7.09-17.65z"/>
                <path fill="#FBBC05" d="M10.53 28.59c-.48-1.45-.76-2.99-.76-4.59l-7.98-6.19C.92 16.46 0 20.12 0 24c0 3.88.92 7.54 2.56 10.78l7.97-6.19z"/>
                <path fill="#34A853" d="M24 48c6.48 0 11.93-2.13 15.89-5.81l-7.73-6c-2.15 1.45-4.92 2.3-8.16 2.3-6.26 0-11.57-4.22-13.47-9.91l-7.98 6.19C6.51 42.62 14.62 48 24 48z"/>
            </svg>
            Continue with Google
        </button>
    </a>

    <div class="register-link">
        Don't have an account? <a href="register.php">Register here</a>
    </div>
</div>
</body>
</html>
