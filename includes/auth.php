<?php
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