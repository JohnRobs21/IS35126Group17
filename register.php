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

session_start();

require_once 'config/db.php';
require_once 'includes/auth.php';

// Redirect if already logged in
if (isset($_SESSION['authenticated']) && $_SESSION['authenticated'] === true) {
    redirect_by_role($_SESSION['role']);
}

// Generate CSRF token
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

$error   = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'] ?? '')) {
        $error = 'Invalid request. Please try again.';
    } else {
        $name     = trim($_POST['name'] ?? '');
        $email    = trim($_POST['email'] ?? '');
        $password = $_POST['password'] ?? '';
        $confirm  = $_POST['confirm_password'] ?? '';

        if (empty($name) || empty($email) || empty($password) || empty($confirm)) {
            $error = 'All fields are required.';
        } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $error = 'Invalid email format.';
        } elseif (strlen($password) < 8) {
            $error = 'Password must be at least 8 characters.';
        } elseif (!preg_match('/[A-Z]/', $password) || !preg_match('/[0-9]/', $password)) {
            $error = 'Password must contain at least one uppercase letter and one number.';
        } elseif ($password !== $confirm) {
            $error = 'Passwords do not match.';
        } else {
            // Check if email already exists
            $stmt = $pdo->prepare('SELECT id FROM users WHERE email = ?');
            $stmt->execute([$email]);
            if ($stmt->fetch()) {
                $error = 'An account with this email already exists.';
            } else {
                $hash = password_hash($password, PASSWORD_BCRYPT);
                $stmt = $pdo->prepare('INSERT INTO users (name, email, password_hash, role) VALUES (?, ?, ?, ?)');
                $stmt->execute([$name, $email, $hash, 'passenger']);
                $new_id = $pdo->lastInsertId();
                log_action($pdo, $new_id, 'New passenger account registered');
                $success = 'Account created successfully! You can now log in.';
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
    <title>Register — IS351 Airline System</title>
    <style>
        * { box-sizing: border-box; margin: 0; padding: 0; }
        body { font-family: sans-serif; background: #f4f6f9; display: flex; align-items: center; justify-content: center; min-height: 100vh; }
        .card { background: #fff; border-radius: 10px; padding: 2rem; width: 100%; max-width: 420px; border: 1px solid #e0e0e0; }
        h1 { font-size: 22px; margin-bottom: 6px; color: #1a1a1a; }
        p.sub { font-size: 13px; color: #666; margin-bottom: 1.5rem; }
        label { display: block; font-size: 13px; font-weight: 500; color: #333; margin-bottom: 5px; }
        input[type=text], input[type=email], input[type=password] { width: 100%; padding: 10px 12px; border: 1px solid #ccc; border-radius: 6px; font-size: 14px; margin-bottom: 1rem; }
        input:focus { outline: none; border-color: #4f46e5; }
        .btn { width: 100%; padding: 11px; background: #4f46e5; color: #fff; border: none; border-radius: 6px; font-size: 14px; cursor: pointer; font-weight: 500; }
        .btn:hover { background: #4338ca; }
        .error { background: #fef2f2; border: 1px solid #fca5a5; color: #b91c1c; padding: 10px 12px; border-radius: 6px; font-size: 13px; margin-bottom: 1rem; }
        .success { background: #f0fdf4; border: 1px solid #86efac; color: #166534; padding: 10px 12px; border-radius: 6px; font-size: 13px; margin-bottom: 1rem; }
        .login-link { text-align: center; font-size: 13px; color: #666; margin-top: 1.2rem; }
        .login-link a { color: #4f46e5; text-decoration: none; }
        .hint { font-size: 11px; color: #999; margin-top: -10px; margin-bottom: 1rem; }
    </style>
</head>
<body>
<div class="card">
    <h1>Create Account</h1>
    <p class="sub">Register as a passenger to book flights</p>

    <?php if ($error): ?>
        <div class="error"><?= htmlspecialchars($error, ENT_QUOTES, 'UTF-8') ?></div>
    <?php endif; ?>
    <?php if ($success): ?>
        <div class="success"><?= htmlspecialchars($success, ENT_QUOTES, 'UTF-8') ?></div>
    <?php endif; ?>

    <form method="POST" action="register.php">
        <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">

        <label for="name">Full Name</label>
        <input type="text" id="name" name="name"
               value="<?= htmlspecialchars($_POST['name'] ?? '', ENT_QUOTES, 'UTF-8') ?>"
               required autofocus>

        <label for="email">Email Address</label>
        <input type="email" id="email" name="email"
               value="<?= htmlspecialchars($_POST['email'] ?? '', ENT_QUOTES, 'UTF-8') ?>"
               required>

        <label for="password">Password</label>
        <input type="password" id="password" name="password" required>
        <p class="hint">At least 8 characters, one uppercase letter and one number.</p>

        <label for="confirm_password">Confirm Password</label>
        <input type="password" id="confirm_password" name="confirm_password" required>

        <button type="submit" class="btn">Create Account</button>
    </form>

    <div class="login-link">
        Already have an account? <a href="login.php">Sign in</a>
    </div>
</div>
</body>
</html>
