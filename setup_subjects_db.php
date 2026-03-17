<?php
require_once 'includes/db.php';

try {
    $sql = "
    CREATE TABLE IF NOT EXISTS subjects (
        id INT AUTO_INCREMENT PRIMARY KEY,
        subject_code VARCHAR(20) NOT NULL UNIQUE,
        subject_name VARCHAR(100) NOT NULL,
        department VARCHAR(50) NOT NULL,
        credits DECIMAL(3,1) NOT NULL,
        description TEXT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
    );
    ";
    
    $pdo->exec($sql);
    echo "Subjects table setup completed successfully.\n";
} catch (PDOException $e) {
    echo "Error creating table: " . $e->getMessage() . "\n";
}
?>
