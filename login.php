<?php
// login.php
require_once 'includes/db.php';
require_once 'includes/auth.php';

// If already logged in, redirect to dashboard
if (isset($_SESSION['user_id'])) {
    header("Location: index.php");
    exit();
}

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Validate CSRF token
    if (!isset($_POST['csrf_token']) || !validateCSRFToken($_POST['csrf_token'])) {
        $error = "Invalid request. Please try again.";
    } else {
        $username = trim($_POST['username']);
        $password = $_POST['password'];

        if (empty($username) || empty($password)) {
            $error = "กรุณากรอกทั้งชื่อผู้ใช้และรหัสผ่าน";
        } else {
            try {
                // Prepare statement to fetch user by username
                $stmt = $pdo->prepare("SELECT id, username, password_hash, role, status FROM users WHERE username = ? LIMIT 1");
                $stmt->execute([$username]);
                $user = $stmt->fetch();

                if ($user) {
                    if ($user['status'] !== 'active') {
                        $error = "บัญชีของคุณถูกระงับ กรุณาติดต่อผู้ดูแลระบบ";
                    } else if (password_verify($password, $user['password_hash'])) {
                        // Password is correct, start new secure session
                        session_regenerate_id(true);
                        
                        $_SESSION['user_id'] = $user['id'];
                        $_SESSION['username'] = $user['username'];
                        $_SESSION['user_role'] = $user['role'];
                        
                        // Update last_login
                        $updateStmt = $pdo->prepare("UPDATE users SET last_login = NOW() WHERE id = ?");
                        $updateStmt->execute([$user['id']]);

                        // Redirect to dashboard
                        header("Location: index.php");
                        exit();
                    } else {
                        $error = "ชื่อผู้ใช้หรือรหัสผ่านไม่ถูกต้อง";
                    }
                } else {
                    $error = "ชื่อผู้ใช้หรือรหัสผ่านไม่ถูกต้อง";
                }
            } catch (PDOException $e) {
                error_log("Login Error: " . $e->getMessage());
                $error = "An error occurred during login. Please try again later.";
            }
        }
    }
}

// Generate new CSRF token for the form
$csrf_token = generateCSRFToken();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>เข้าสู่ระบบ - SchoolAI</title>
    <!-- Favicon -->
    <link rel="icon" type="image/png" href="assets/images/favicon.png">
    <!-- Google Fonts: Inter -->
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <!-- Bootstrap 5 CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.2/css/all.min.css">
    <!-- Custom CSS -->
    <link rel="stylesheet" href="assets/css/style.css">
    <style>
        body {
            background-color: var(--bg-light);
            height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
        }
        .login-card {
            width: 100%;
            max-width: 420px;
            border: none;
            border-radius: 16px;
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.08);
            overflow: hidden;
        }
        .login-header {
            background-color: var(--primary-color);
            padding: 40px 30px;
            text-align: center;
            color: #fff;
        }
        .login-header .logo-icon {
            font-size: 3rem;
            margin-bottom: 10px;
        }
        .login-body {
            padding: 40px 30px;
            background-color: #fff;
        }
        .form-control {
            border-radius: 8px;
            padding: 12px 15px;
            border: 1px solid var(--border-color);
        }
        .form-control:focus {
            box-shadow: 0 0 0 0.25rem rgba(208, 39, 82, 0.25);
            border-color: var(--primary-color);
        }
        .btn-login {
            border-radius: 8px;
            padding: 12px;
            font-weight: 600;
            width: 100%;
        }
    </style>
</head>
<body>

<div class="login-card">
    <div class="login-header">
        <i class="fas fa-school logo-icon"></i>
        <h3 class="fw-bold mb-0">SchoolAI</h3>
        <p class="mb-0 text-white-50">ระบบจัดการสถานศึกษา</p>
    </div>
    <div class="login-body">
        <h5 class="text-center fw-bold mb-4 text-dark">เข้าสู่ระบบบัญชีของคุณ</h5>
        
        <?php if (!empty($error)): ?>
            <div class="alert alert-danger alert-dismissible fade show" role="alert">
                <i class="fas fa-exclamation-circle me-2"></i> <?php echo e($error); ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>
        <?php endif; ?>

        <?php if (isset($_GET['logout'])): ?>
            <div class="alert alert-success alert-dismissible fade show" role="alert">
                <i class="fas fa-check-circle me-2"></i> คุณได้ออกจากระบบสำเร็จแล้ว
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>
        <?php endif; ?>

        <form method="POST" action="login.php">
            <input type="hidden" name="csrf_token" value="<?php echo e($csrf_token); ?>">
            
            <div class="mb-3">
                <label for="username" class="form-label text-muted fw-semibold small">ชื่อผู้ใช้งาน</label>
                <div class="input-group">
                    <span class="input-group-text bg-light border-end-0 text-muted"><i class="fas fa-user"></i></span>
                    <input type="text" class="form-control border-start-0" id="username" name="username" placeholder="กรอกชื่อผู้ใช้งาน" required value="<?php echo isset($_POST['username']) ? e($_POST['username']) : ''; ?>">
                </div>
            </div>
            
            <div class="mb-4">
                <div class="d-flex justify-content-between align-items-center">
                    <label for="password" class="form-label text-muted fw-semibold small mb-0">รหัสผ่าน</label>
                    <a href="#" class="small text-decoration-none">ลืมรหัสผ่าน?</a>
                </div>
                <div class="input-group mt-2">
                    <span class="input-group-text bg-light border-end-0 text-muted"><i class="fas fa-lock"></i></span>
                    <input type="password" class="form-control border-start-0" id="password" name="password" placeholder="กรอกรหัสผ่าน" required>
                </div>
            </div>
            
            <button type="submit" class="btn btn-primary btn-login mt-2">เข้าสู่ระบบ</button>
        </form>
        
        <div class="text-center mt-4">
            <p class="text-muted small mb-0">บัญชีทดสอบ (รหัสผ่าน: password123)</p>
            <p class="text-muted small"><strong>admin</strong>, <strong>teacher1</strong>, <strong>student1</strong></p>
        </div>
    </div>
</div>

<!-- Bootstrap 5 JS Bundle -->
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
