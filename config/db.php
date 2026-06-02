<?php
require_once __DIR__ . '/../vendor/autoload.php';

// Load .env file if it exists (local development)
$envFile = __DIR__ . '/../.env';
if (file_exists($envFile)) {
    $dotenv = Dotenv\Dotenv::createImmutable(__DIR__ . '/../');
    $dotenv->load();
}

$db_host = $_ENV['DB_HOST'] ?? 'localhost';
$db_name = $_ENV['DB_NAME'] ?? 'airline_db';
$db_user = $_ENV['DB_USER'] ?? 'root';
$db_pass = $_ENV['DB_PASS'] ?? '';
$db_port = $_ENV['DB_PORT'] ?? '3306';

// TEMPORARY DEBUG — remove before submission
error_log('DB_HOST: ' . $db_host);
error_log('DB_NAME: ' . $db_name);
error_log('DB_USER: ' . $db_user);
error_log('DB_PORT: ' . $db_port);

try {
    $pdo = new PDO(
        'mysql:host=' . $db_host . ';port=' . $db_port . ';dbname=' . $db_name . ';charset=utf8',
        $db_user,
        $db_pass,
        [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES   => false
        ]
    );
} catch (PDOException $e) {
    // TEMPORARY — show actual error
    die('Database connection failed: ' . $e->getMessage());
}