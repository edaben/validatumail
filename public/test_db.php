<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

$host = getenv('DB_HOST') ?: 'localhost';
$dbname = getenv('DB_NAME') ?: 'validamail';
$user = getenv('DB_USER') ?: 'validamail';
$pass = getenv('DB_PASSWORD') ?: 'Rastro@2228';

echo "Host: " . htmlspecialchars($host) . "<br>";
echo "DB: " . htmlspecialchars($dbname) . "<br>";
echo "User: " . htmlspecialchars($user) . "<br>";

try {
    $pdo = new PDO("mysql:host=$host;dbname=$dbname;charset=utf8mb4", $user, $pass);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    echo "Connection successful!<br>";

    // check tables
    $stmt = $pdo->query("SHOW TABLES");
    $tables = $stmt->fetchAll(PDO::FETCH_COLUMN);
    echo "Tables: " . implode(", ", $tables);
} catch (PDOException $e) {
    echo "Connection failed: " . htmlspecialchars($e->getMessage());
}
