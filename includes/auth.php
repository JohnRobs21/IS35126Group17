<?php
define('SESSION_TIMEOUT', 1800); // 30 minutes

function check_session_timeout(): void {
    if (!isset($_SESSION['authenticated']) || $_SESSION['authenticated'] !== true) {
        return; // Not logged in, nothing to check
    }

    if (isset($_SESSION['last_activity'])) {
        $inactive = time() - $_SESSION['last_activity'];
        if ($inactive > SESSION_TIMEOUT) {
            // Session expired — destroy and redirect
            session_unset();
            session_destroy();
            header('Location: /IS35126Group17/login.php?error=session_expired');
            exit;
        }
    }

    // Update last activity timestamp on every request
    $_SESSION['last_activity'] = time();
}

function require_role(array $allowed_roles) {
    if (!isset($_SESSION['authenticated']) || $_SESSION['authenticated'] !== true) {
        header('Location: /IS35126Group17/login.php');
        exit;
    }
    if (!in_array($_SESSION['role'], (array)$allowed_roles)) {
        header('Location: /IS35126Group17/login.php?error=unauthorized');
        exit;
    }
}

function redirect_by_role(string $role): void {
    switch ($role) {
        case 'admin':
            header('Location: /IS35126Group17/admin/dashboard.php');
            break;
        case 'staff':
            header('Location: /IS35126Group17/staff/dashboard.php');
            break;
        default:
            header('Location: /IS35126Group17/passenger/dashboard.php');
            break;
    }
    exit;
}

function log_action(\PDO $pdo, int $user_id, string $action): void {
    $ip = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
    $stmt = $pdo->prepare('INSERT INTO audit_logs (user_id, action, ip_address) VALUES (?, ?, ?)');
    $stmt->execute([$user_id, $action, $ip]);
}
