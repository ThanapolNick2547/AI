<?php
require_once 'includes/db.php';
$username = 'admin';
$password = 'password123';

try {
    $stmt = $pdo->prepare("SELECT id, username, password_hash, role, status FROM users WHERE username = ? LIMIT 1");
    $stmt->execute([$username]);
    $user = $stmt->fetch();
    
    if ($user) {
        if (password_verify($password, $user['password_hash'])) {
            echo "Login success for $username\n";
        } else {
            echo "Password mismatch for $username\n";
            echo "Hash in DB: " . $user['password_hash'] . "\n";
            echo "Generated Hash: " . password_hash($password, PASSWORD_BCRYPT) . "\n";
        }
    } else {
        echo "User not found\n";
    }
} catch (Exception $e) {
    echo "Error: " . $e->getMessage();
}
?>
