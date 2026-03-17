<?php
require_once 'includes/db.php';

try {
    $sql = "
    ALTER TABLE teachers
    ADD line_id VARCHAR(50) NULL AFTER last_name,
    ADD profile_picture VARCHAR(255) NULL AFTER phone;
    ";
    
    $pdo->exec($sql);
    echo "Teachers table updated successfully with line_id and profile_picture.\n";
} catch (PDOException $e) {
    if ($e->getCode() == '42S21') {
        echo "Columns already exist.\n";
    } else {
        echo "Error: " . $e->getMessage() . "\n";
    }
}
?>
