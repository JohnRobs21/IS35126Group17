<?php
require_once __DIR__ . '/config/db.php';

$hash = password_hash('Test!1234', PASSWORD_BCRYPT);
$stmt = $pdo->prepare('UPDATE users SET password_hash = ?, login_attempts = 0, locked_until = NULL WHERE email = ?');
$stmt->execute([$hash, 'is35127group17@gmail.com']);
echo 'Done. Hash: ' . $hash;
