<?php
/**
 * setup_profile_db.php
 * Migration: Add profile columns to users table
 * Run once, then delete or protect this file.
 */
require_once 'includes/db.php';

$steps = [];
$errors = [];

// 1. Add profile columns to users table
$columns = [
    "full_name"   => "ALTER TABLE users ADD COLUMN full_name VARCHAR(100) NULL AFTER username",
    "email"       => "ALTER TABLE users ADD COLUMN email VARCHAR(100) NULL AFTER full_name",
    "phone"       => "ALTER TABLE users ADD COLUMN phone VARCHAR(20) NULL AFTER email",
    "bio"         => "ALTER TABLE users ADD COLUMN bio TEXT NULL AFTER phone",
    "avatar"      => "ALTER TABLE users ADD COLUMN avatar VARCHAR(255) NULL AFTER bio",
    "gender"      => "ALTER TABLE users ADD COLUMN gender ENUM('male','female','other') NULL AFTER avatar",
    "birth_date"  => "ALTER TABLE users ADD COLUMN birth_date DATE NULL AFTER gender",
    "address"     => "ALTER TABLE users ADD COLUMN address TEXT NULL AFTER birth_date",
];

foreach ($columns as $col => $sql) {
    // Check if column already exists
    $check = $pdo->prepare("SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS 
        WHERE TABLE_SCHEMA = ? AND TABLE_NAME = 'users' AND COLUMN_NAME = ?");
    $check->execute([$_ENV['DB_NAME'] ?? 'schoolai_db', $col]);
    if ($check->fetchColumn() == 0) {
        try {
            $pdo->exec($sql);
            $steps[] = "✅ เพิ่มคอลัมน์ '$col' สำเร็จ";
        } catch (PDOException $e) {
            $errors[] = "❌ คอลัมน์ '$col': " . $e->getMessage();
        }
    } else {
        $steps[] = "ℹ️ คอลัมน์ '$col' มีอยู่แล้ว";
    }
}

// 2. Create avatars upload directory
$avatarDir = __DIR__ . '/assets/images/avatars';
if (!is_dir($avatarDir)) {
    if (mkdir($avatarDir, 0755, true)) {
        $steps[] = "✅ สร้างโฟลเดอร์ avatars สำเร็จ";
    } else {
        $errors[] = "❌ ไม่สามารถสร้างโฟลเดอร์ avatars ได้";
    }
} else {
    $steps[] = "ℹ️ โฟลเดอร์ avatars มีอยู่แล้ว";
}

// .htaccess ป้องกัน PHP execution ใน avatars dir
$htaccess = $avatarDir . '/.htaccess';
if (!file_exists($htaccess)) {
    file_put_contents($htaccess, "php_flag engine off\nOptions -ExecCGI\n");
    $steps[] = "✅ สร้าง .htaccess ป้องกัน PHP ใน avatars/";
}
?>
<!DOCTYPE html>
<html lang="th">
<head>
<meta charset="UTF-8">
<title>Setup Profile DB - SchoolAI</title>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body class="bg-light p-5">
<div class="container" style="max-width:640px;">
    <div class="card shadow-sm border-0">
        <div class="card-header bg-primary text-white fw-bold">🔧 Setup: ระบบโปรไฟล์ส่วนตัว</div>
        <div class="card-body">
            <?php foreach ($steps as $s): ?>
                <p class="mb-1"><?= htmlspecialchars($s) ?></p>
            <?php endforeach; ?>
            <?php foreach ($errors as $e): ?>
                <p class="mb-1 text-danger"><?= htmlspecialchars($e) ?></p>
            <?php endforeach; ?>
            <?php if (empty($errors)): ?>
                <div class="alert alert-success mt-3 mb-0">
                    <strong>✅ Setup สำเร็จทั้งหมด!</strong><br>
                    <small>ลบไฟล์นี้ทิ้ง หรือป้องกันการเข้าถึง หลังจาก setup เรียบร้อยแล้ว</small>
                </div>
            <?php else: ?>
                <div class="alert alert-warning mt-3 mb-0">
                    <strong>⚠️ มีบางขั้นตอนที่เกิดข้อผิดพลาด</strong> กรุณาตรวจสอบ
                </div>
            <?php endif; ?>
            <a href="profile.php" class="btn btn-primary mt-3">ไปหน้าโปรไฟล์ →</a>
        </div>
    </div>
</div>
</body>
</html>
