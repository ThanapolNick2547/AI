<?php
require_once 'includes/db.php';

try {
    // Generate valid hash for 'password123'
    $password = 'password123';
    $hash = password_hash($password, PASSWORD_BCRYPT);
    
    // Update existing users with correct hash
    $stmt = $pdo->prepare("UPDATE users SET password_hash = ? WHERE username IN ('admin', 'teacher1', 'student1')");
    $stmt->execute([$hash]);

    echo "Passwords updated successfully.";
} catch (PDOException $e) {
    echo "Error updating passwords: " . $e->getMessage();
}
?>
