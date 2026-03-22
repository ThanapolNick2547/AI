<?php
require_once 'includes/db.php';

try {
    // 1. Roles table
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS roles (
            id INT AUTO_INCREMENT PRIMARY KEY,
            name VARCHAR(50) NOT NULL UNIQUE,
            description TEXT NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
        );
    ");

    // 2. Permissions table
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS permissions (
            id INT AUTO_INCREMENT PRIMARY KEY,
            module_name VARCHAR(50) NOT NULL,
            action VARCHAR(50) NOT NULL,
            description TEXT NULL,
            UNIQUE KEY unique_permission (module_name, action)
        );
    ");

    // 3. Role Permissions Mapping
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS role_permissions (
            role_id INT NOT NULL,
            permission_id INT NOT NULL,
            PRIMARY KEY (role_id, permission_id),
            FOREIGN KEY (role_id) REFERENCES roles(id) ON DELETE CASCADE,
            FOREIGN KEY (permission_id) REFERENCES permissions(id) ON DELETE CASCADE
        );
    ");

    // Seed Roles
    $roles = [
        ['admin', 'Full system access and overall administration'],
        ['teacher', 'Access to classes, handling grades, and attendance'],
        ['student', 'View-only access to personal grades and schedules']
    ];
    $stmt = $pdo->prepare("INSERT IGNORE INTO roles (name, description) VALUES (?, ?)");
    foreach ($roles as $role) {
        $stmt->execute($role);
    }

    // Seed Permissions
    $modules = ['Dashboard', 'Students', 'Teachers', 'Classes', 'Classrooms', 'Subjects', 'Schedules', 'Grading', 'Attendance', 'Roles & Permissions'];
    $actions = ['view' => 'Can view', 'add' => 'Can create new', 'edit' => 'Can edit existing', 'delete' => 'Can delete'];
    
    $stmt = $pdo->prepare("INSERT IGNORE INTO permissions (module_name, action, description) VALUES (?, ?, ?)");
    foreach ($modules as $mod) {
        foreach ($actions as $act => $desc) {
            $stmt->execute([$mod, $act, "$desc $mod"]);
        }
    }

    // Assign all permissions to 'admin' role automatically
    $pdo->exec("
        INSERT IGNORE INTO role_permissions (role_id, permission_id)
        SELECT r.id, p.id FROM roles r, permissions p WHERE r.name = 'admin'
    ");

    echo "Roles & Permissions tables setup and seeded successfully.\n";
} catch (PDOException $e) {
    echo "Error creating tables: " . $e->getMessage() . "\n";
}
?>
