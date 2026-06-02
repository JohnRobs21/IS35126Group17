<?php
require_once __DIR__ . '/../vendor/autoload.php';

// Load .env file if it exists (local development)
$envFile = __DIR__ . '/../.env';
if (file_exists($envFile)) {
    $dotenv = Dotenv\Dotenv::createImmutable(__DIR__ . '/../');
    $dotenv->load();
    // TEMPORARY DEBUG
    die('HOST=' . ($_ENV['DB_HOST'] ?? 'NOT SET') . 
        ' | NAME=' . ($_ENV['DB_NAME'] ?? 'NOT SET') . 
        ' | USER=' . ($_ENV['DB_USER'] ?? 'NOT SET') .
        ' | PORT=' . ($_ENV['DB_PORT'] ?? 'NOT SET'));
}

$db_host = $_ENV['DB_HOST'] ?? 'localhost';
$db_name = $_ENV['DB_NAME'] ?? 'airline_db';
$db_user = $_ENV['DB_USER'] ?? 'root';
$db_pass = $_ENV['DB_PASS'] ?? '';
$db_port = $_ENV['DB_PORT'] ?? '3306';

try {
    $dsn = 'mysql:host=' . $db_host . ';port=' . $db_port . ';dbname=' . $db_name . ';charset=utf8';
    $pdo = new PDO($dsn, $db_user, $db_pass, [
        PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES   => false,
        PDO::MYSQL_ATTR_INIT_COMMAND => 'SET NAMES utf8'
    ]);
} catch (PDOException $e) {
    die('Database connection failed: ' . $e->getMessage());
}