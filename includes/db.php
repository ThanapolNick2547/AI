<?php
// includes/db.php
// Secure PDO Connection to MySQL Database

$host = '127.0.0.1';
$db = 'schoolai_db';
$user = 'root'; // XAMPP default
$pass = ''; // XAMPP default usually empty

$charset = 'utf8mb4';
$dsn = "mysql:host=$host;dbname=$db;charset=$charset";

// Set PDO options for error handling and fetch modes
$options = [
    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, // Throw exceptions on error
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC, // Fetch associative arrays
    PDO::ATTR_EMULATE_PREPARES => false, // Use real prepared statements to prevent SQL Injection
];

try {
    $pdo = new PDO($dsn, $user, $pass, $options);
}
catch (\PDOException $e) {
    // In production, do not expose exact error messages to the user
    // Log the error instead
    error_log($e->getMessage());
    exit('Database Connection Failed. Please contact administrator.');
}
?>
