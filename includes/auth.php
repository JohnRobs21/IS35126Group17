<?php
define('SESSION_TIMEOUT', 1800);

function check_session_timeout(): void {
    if (!isset($_SESSION['authenticated']) || $_SESSION['authenticated'] !== true) {
        return;
    }
    if (isset($_SESSION['last_activity'])) {
        $inactive = time() - $_SESSION['last_activity'];
        if ($inactive > SESSION_TIMEOUT) {
            session_unset();
            session_destroy();
            header('Location: /login.php?error=session_expired');
            exit;
        }
    }
    $_SESSION['last_activity'] = time();
}

function require_role(array $allowed_roles) {
    check_session_timeout();

    if (!isset($_SESSION['authenticated']) || $_SESSION['authenticated'] !== true) {
        header('Location: /login.php');
        exit;
    }
    if (!in_array($_SESSION['role'], (array)$allowed_roles)) {
        header('Location: /login.php?error=unauthorized');
        exit;
    }
}

function redirect_by_role(string $role): void {
    switch ($role) {
        case 'admin':
            header('Location: /admin/dashboard.php');
            break;
        case 'staff':
            header('Location: /staff/dashboard.php');
            break;
        default:
            header('Location: /passenger/dashboard.php');
            break;
    }
    exit;
}

function log_action(\PDO $pdo, int $user_id, string $action): void {
    $ip = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
    $stmt = $pdo->prepare('INSERT INTO audit_logs (user_id, action, ip_address) VALUES (?, ?, ?)');
    $stmt->execute([$user_id, $action, $ip]);
}