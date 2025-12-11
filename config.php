<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

$host = 'localhost:3306';
$dbname = 'edumind';
$username = 'root';
$password = '';

try {
    $pdo = new PDO("mysql:host=$host;dbname=$dbname;charset=utf8mb4", $username, $password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch(PDOException $e) {
    die("Database connection failed: " . $e->getMessage());
}

// Color scheme constants
define('PRIMARY_COLOR', '#2563eb'); // Royal blue
define('SECONDARY_COLOR', '#6b7280'); // Grey
define('BACKGROUND_COLOR', '#f8fafc'); // Off-white
?>