<?php
require_once 'includes/db.php';
require_once 'includes/auth.php';

// Require login to view and edit profile
requireLogin();

$user_id = $_SESSION['user_id'];
$success = '';
$error = '';

// Avatar directory setup
$avatar_dir = __DIR__ . '/assets/images/avatars/';

/** ======================================
 *  Handle POST Requests
 *  ====================================== */
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!isset($_POST['csrf_token']) || !validateCSRFToken($_POST['csrf_token'])) {
        $error = "Invalid CSRF Token.";
    } else {
        $action = $_POST['action'] ?? '';

        // 1. Update Personal Info
        if ($action === 'update_info') {
            $full_name  = trim($_POST['full_name'] ?? '');
            $email      = trim($_POST['email'] ?? '');
            $phone      = trim($_POST['phone'] ?? '');
            $gender     = trim($_POST['gender'] ?? '');
            $birth_date = trim($_POST['birth_date'] ?? '');
            $address    = trim($_POST['address'] ?? '');
            $bio        = trim($_POST['bio'] ?? '');

            // Convert empty dates to null for DB compatibility
            if (empty($birth_date)) $birth_date = null;

            try {
                $stmt = $pdo->prepare("
                    UPDATE users 
                    SET full_name = ?, email = ?, phone = ?, gender = ?, birth_date = ?, address = ?, bio = ?
                    WHERE id = ?
                ");
                $stmt->execute([$full_name, $email, $phone, $gender, $birth_date, $address, $bio, $user_id]);
                $_SESSION['success_msg'] = "บันทึกข้อมูลส่วนตัวสำเร็จ";
                header("Location: profile.php");
                exit();
            } catch (PDOException $e) {
                // If columns are missing, it means setup_profile_db.php wasn't run
                if ($e->getCode() == '42S22') {
                    $error = "เกิดข้อผิดพลาด: ไม่พบคอลัมน์ในฐานข้อมูล (กรุณารันไฟล์ setup_profile_db.php ก่อน)";
                } else {
                    $error = "เกิดข้อผิดพลาดในการบันทึก: " . $e->getMessage();
                }
            }
        }

        // 2. Change Password
        elseif ($action === 'update_password') {
            $current_pw = $_POST['current_password'] ?? '';
            $new_pw     = $_POST['new_password'] ?? '';
            $confirm_pw = $_POST['confirm_password'] ?? '';

            if (empty($current_pw) || empty($new_pw) || empty($confirm_pw)) {
                $error = "กรุณากรอกรหัสผ่านให้ครบทุกช่อง";
            } elseif ($new_pw !== $confirm_pw) {
                $error = "รหัสผ่านใหม่และการยืนยันรหัสผ่านไม่ตรงกัน";
            } elseif (strlen($new_pw) < 6) {
                $error = "รหัสผ่านใหม่ต้องมีความยาวอย่างน้อย 6 ตัวอักษร";
            } else {
                try {
                    // Fetch existing hash
                    $stmt = $pdo->prepare("SELECT password_hash FROM users WHERE id = ?");
                    $stmt->execute([$user_id]);
                    $hash = $stmt->fetchColumn();

                    if (password_verify($current_pw, $hash)) {
                        $new_hash = password_hash($new_pw, PASSWORD_BCRYPT);
                        $update = $pdo->prepare("UPDATE users SET password_hash = ? WHERE id = ?");
                        $update->execute([$new_hash, $user_id]);
                        $_SESSION['success_msg'] = "เปลี่ยนรหัสผ่านสำเร็จ";
                        header("Location: profile.php");
                        exit();
                    } else {
                        $error = "รหัสผ่านปัจจุบันไม่ถูกต้อง";
                    }
                } catch (PDOException $e) {
                    $error = "เกิดข้อผิดพลาด: " . $e->getMessage();
                }
            }
        }

        // 3. Upload Avatar
        elseif ($action === 'upload_avatar') {
            if (isset($_FILES['avatar_file']) && $_FILES['avatar_file']['error'] === UPLOAD_ERR_OK) {
                $fileTmpPath   = $_FILES['avatar_file']['tmp_name'];
                $fileName      = $_FILES['avatar_file']['name'];
                $fileSize      = $_FILES['avatar_file']['size'];
                $fileType      = $_FILES['avatar_file']['type'];
                
                $fileNameCmps  = explode(".", $fileName);
                $fileExtension = strtolower(end($fileNameCmps));
                
                $allowedExts   = ['jpg', 'jpeg', 'png', 'gif'];
                
                if (in_array($fileExtension, $allowedExts)) {
                    if ($fileSize <= 2000000) { // Max 2MB
                        // Ensure directory exists
                        if (!is_dir($avatar_dir)) {
                            mkdir($avatar_dir, 0755, true);
                            file_put_contents($avatar_dir . '.htaccess', "php_flag engine off\nOptions -ExecCGI\n");
                        }

                        // Generate unique file name
                        $newFileName = md5(time() . $fileName) . '.' . $fileExtension;
                        $destPath = $avatar_dir . $newFileName;
                        
                        if (move_uploaded_file($fileTmpPath, $destPath)) {
                            try {
                                // Get old avatar to delete
                                $stmt = $pdo->prepare("SELECT avatar FROM users WHERE id = ?");
                                $stmt->execute([$user_id]);
                                $old_avatar = $stmt->fetchColumn();

                                // Update DB
                                $update = $pdo->prepare("UPDATE users SET avatar = ? WHERE id = ?");
                                $update->execute([$newFileName, $user_id]);

                                // Remove old avatar file if exists
                                if ($old_avatar && file_exists($avatar_dir . $old_avatar)) {
                                    unlink($avatar_dir . $old_avatar);
                                }

                                $_SESSION['success_msg'] = "อัปโหลดรูปโปรไฟล์สำเร็จ";
                                header("Location: profile.php");
                                exit();
                            } catch (PDOException $e) {
                                // Missing column error check
                                if ($e->getCode() == '42S22') {
                                    $error = "เกิดข้อผิดพลาด: ไม่พบคอลัมน์ในฐานข้อมูล (กรุณารันไฟล์ setup_profile_db.php ก่อน)";
                                } else {
                                    $error = "เกิดข้อผิดพลาด: " . $e->getMessage();
                                }
                            }
                        } else {
                            $error = "เกิดข้อผิดพลาดในการย้ายไฟล์ที่อัปโหลด";
                        }
                    } else {
                        $error = "ขนาดไฟล์ต้องไม่เกิน 2MB";
                    }
                } else {
                    $error = "อนุญาตเฉพาะไฟล์ .jpg, .jpeg, .png และ .gif เท่านั้น";
                }
            } else {
                $error = "ไม่พบไฟล์ที่อัปโหลด หรือไฟล์มีปัญหา";
            }
        }
        
        // 4. Remove Avatar
        elseif ($action === 'remove_avatar') {
             try {
                $stmt = $pdo->prepare("SELECT avatar FROM users WHERE id = ?");
                $stmt->execute([$user_id]);
                $old_avatar = $stmt->fetchColumn();

                $update = $pdo->prepare("UPDATE users SET avatar = NULL WHERE id = ?");
                $update->execute([$user_id]);

                if ($old_avatar && file_exists($avatar_dir . $old_avatar)) {
                    unlink($avatar_dir . $old_avatar);
                }

                $_SESSION['success_msg'] = "ลบรูปโปรไฟล์สำเร็จ";
                header("Location: profile.php");
                exit();
            } catch (PDOException $e) {
                $error = "เกิดข้อผิดพลาด: " . $e->getMessage();
            }
        }
    }
}

// Session Messages
if (isset($_SESSION['success_msg'])) {
    $success = $_SESSION['success_msg'];
    unset($_SESSION['success_msg']);
}

$csrf_token = generateCSRFToken();

/** ======================================
 *  Fetch Current User Data
 *  ====================================== */
try {
    $stmt = $pdo->prepare("SELECT * FROM users WHERE id = ?");
    $stmt->execute([$user_id]);
    $user = $stmt->fetch();
} catch (PDOException $e) {
    if ($e->getCode() == '42S22') {
         // Fallback dummy structure if setup wasn't run
         $user = ['username' => $_SESSION['username'], 'role' => $_SESSION['user_role'] ?? ''];
         $error = "⚠️ โปรดแจ้งผู้ดูแลระบบให้รันระบบติดตั้งฐานข้อมูลโปรไฟล์ (setup_profile_db.php)";
    } else {
         $error = "เกิดข้อผิดพลาดในการดึงข้อมูล: " . $e->getMessage();
         $user = [];
    }
}

// Prepare Data variables
$username   = $user['username'] ?? '';
$role       = $user['role'] ?? '';
$full_name  = $user['full_name'] ?? '';
$email      = $user['email'] ?? '';
$phone      = $user['phone'] ?? '';
$gender     = $user['gender'] ?? '';
$birth_date = $user['birth_date'] ?? '';
$address    = $user['address'] ?? '';
$bio        = $user['bio'] ?? '';
$avatar     = $user['avatar'] ?? '';

// Determine Avatar URL
if (!empty($avatar) && file_exists("assets/images/avatars/" . $avatar)) {
    $avatar_url = "assets/images/avatars/" . $avatar;
} else {
    $avatar_url = "https://ui-avatars.com/api/?name=" . urlencode($full_name ?: $username) . "&background=D02752&color=fff&size=150";
}

// Role labels
$roleTH = ['admin' => 'ผู้ดูแลระบบ', 'teacher' => 'ครูผู้สอน', 'student' => 'นักเรียน'];
$roleColors = ['admin' => 'danger', 'teacher' => 'primary', 'student' => 'success'];
$roleIcons = ['admin' => 'fa-user-shield', 'teacher' => 'fa-chalkboard-teacher', 'student' => 'fa-user-graduate'];

$displayRole = $roleTH[$role] ?? e($role);
$roleColor = $roleColors[$role] ?? 'secondary';
$roleIcon = $roleIcons[$role] ?? 'fa-user';

include 'includes/header.php';
?>

<!-- Breadcrumb -->
<nav aria-label="breadcrumb">
    <ol class="breadcrumb">
        <li class="breadcrumb-item"><a href="index.php">หน้าหลัก</a></li>
        <li class="breadcrumb-item active" aria-current="page">โปรไฟล์ส่วนตัว</li>
    </ol>
</nav>

<div class="d-flex justify-content-between align-items-center mb-4">
    <h2 class="mb-0 fw-bold text-dark"><i class="fas fa-id-badge text-primary me-2"></i>โปรไฟล์ส่วนตัว</h2>
</div>

<!-- Alerts -->
<?php if ($success): ?>
    <div class="alert alert-success alert-dismissible fade show shadow-sm" role="alert">
        <i class="fas fa-check-circle me-2"></i> <?php echo e($success); ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
    </div>
<?php endif; ?>
<?php if ($error): ?>
    <div class="alert alert-danger alert-dismissible fade show shadow-sm" role="alert">
        <i class="fas fa-exclamation-triangle me-2"></i> <?php echo e($error); ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
    </div>
<?php endif; ?>

<div class="row">
    <!-- Left Sidebar: Avatar & Quick Info -->
    <div class="col-xl-4 col-lg-5 mb-4">
        <div class="card border-0 shadow-sm" style="border-radius:12px;">
            <div class="card-body text-center p-4">
                <div class="position-relative d-inline-block mb-3">
                    <img src="<?php echo $avatar_url; ?>" alt="Profile Picture" class="rounded-circle border border-3 border-light shadow-sm" style="width: 150px; height: 150px; object-fit: cover;">
                    <button class="btn btn-primary btn-sm rounded-circle position-absolute bottom-0 end-0 shadow" data-bs-toggle="modal" data-bs-target="#uploadAvatarModal" style="width: 35px; height: 35px;">
                        <i class="fas fa-camera"></i>
                    </button>
                </div>
                
                <h4 class="fw-bold mb-1"><?php echo e($full_name ?: $username); ?></h4>
                <p class="text-muted mb-2"><i class="fas fa-at me-1"></i><?php echo e($username); ?></p>
                
                <span class="badge bg-<?php echo $roleColor; ?> bg-opacity-10 text-<?php echo $roleColor; ?> px-3 py-2 rounded-pill">
                    <i class="fas <?php echo $roleIcon; ?> me-1"></i> <?php echo $displayRole; ?>
                </span>
                
                <hr class="my-4 text-muted">
                
                <div class="text-start">
                    <h6 class="fw-bold text-uppercase text-muted" style="font-size: 0.8rem; letter-spacing: 0.5px;">ข้อมูลการติดต่อ</h6>
                    <ul class="list-unstyled mt-3 mb-0">
                        <li class="mb-3 d-flex align-items-center">
                            <i class="fas fa-envelope text-muted me-3" style="width: 15px;"></i>
                            <span class="<?php echo $email ? 'text-dark' : 'text-muted fst-italic'; ?>"><?php echo $email ? e($email) : 'ยังไม่ระบุอีเมล'; ?></span>
                        </li>
                        <li class="mb-3 d-flex align-items-center">
                            <i class="fas fa-phone-alt text-muted me-3" style="width: 15px;"></i>
                            <span class="<?php echo $phone ? 'text-dark' : 'text-muted fst-italic'; ?>"><?php echo $phone ? e($phone) : 'ยังไม่ระบุเบอร์โทรศัพท์'; ?></span>
                        </li>
                        <li class="d-flex align-items-start">
                            <i class="fas fa-map-marker-alt text-muted me-3 mt-1" style="width: 15px;"></i>
                            <span class="<?php echo $address ? 'text-dark' : 'text-muted fst-italic'; ?>"><?php echo $address ? nl2br(e($address)) : 'ยังไม่ระบุที่อยู่'; ?></span>
                        </li>
                    </ul>
                </div>
            </div>
        </div>
    </div>

    <!-- Right Content: Tabs (Info / Password) -->
    <div class="col-xl-8 col-lg-7">
        <div class="card border-0 shadow-sm" style="border-radius:12px;">
            <div class="card-header bg-white border-bottom-0 pt-3 pb-0 px-4" style="border-radius:12px 12px 0 0;">
                <ul class="nav nav-tabs border-bottom-0" id="profileTabs" role="tablist">
                    <li class="nav-item" role="presentation">
                        <button class="nav-link active fw-semibold text-dark px-4 py-3 border-0 border-bottom border-3 border-primary" id="info-tab" data-bs-toggle="tab" data-bs-target="#info" type="button" role="tab" aria-controls="info" aria-selected="true">
                            <i class="fas fa-user-edit me-2"></i>ข้อมูลส่วนตัว
                        </button>
                    </li>
                    <li class="nav-item" role="presentation">
                        <button class="nav-link fw-semibold text-muted px-4 py-3 border-0" id="password-tab" data-bs-toggle="tab" data-bs-target="#password" type="button" role="tab" aria-controls="password" aria-selected="false">
                            <i class="fas fa-key me-2"></i>เปลี่ยนรหัสผ่าน
                        </button>
                    </li>
                </ul>
            </div>
            
            <div class="card-body p-4 bg-light bg-opacity-50" style="border-radius:0 0 12px 12px;">
                <div class="tab-content" id="profileTabsContent">
                    
                    <!-- TAB: Personal Info -->
                    <div class="tab-pane fade show active" id="info" role="tabpanel" aria-labelledby="info-tab">
                        <form method="POST" action="profile.php">
                            <input type="hidden" name="csrf_token" value="<?php echo e($csrf_token); ?>">
                            <input type="hidden" name="action" value="update_info">
                            
                            <h5 class="fw-bold mb-4">แก้ไขข้อมูลส่วนตัว</h5>
                            
                            <div class="row g-3">
                                <div class="col-md-6">
                                    <label class="form-label text-muted fw-semibold small">ชื่อ-นามสกุล (เต็ม)</label>
                                    <div class="input-group">
                                        <span class="input-group-text"><i class="fas fa-id-card text-muted"></i></span>
                                        <input type="text" class="form-control" name="full_name" value="<?php echo e($full_name); ?>" placeholder="ชื่อ และ นามสกุล">
                                    </div>
                                </div>
                                
                                <div class="col-md-6">
                                    <label class="form-label text-muted fw-semibold small">อีเมล (Email)</label>
                                    <div class="input-group">
                                        <span class="input-group-text"><i class="fas fa-envelope text-muted"></i></span>
                                        <input type="email" class="form-control" name="email" value="<?php echo e($email); ?>" placeholder="example@school.com">
                                    </div>
                                </div>
                                
                                <div class="col-md-6">
                                    <label class="form-label text-muted fw-semibold small">เบอร์โทรศัพท์มือถือ</label>
                                    <div class="input-group">
                                        <span class="input-group-text"><i class="fas fa-phone-alt text-muted"></i></span>
                                        <input type="text" class="form-control" name="phone" value="<?php echo e($phone); ?>" placeholder="08X-XXX-XXXX">
                                    </div>
                                </div>
                                
                                <div class="col-md-6">
                                    <label class="form-label text-muted fw-semibold small">เพศ</label>
                                    <select class="form-select" name="gender">
                                        <option value="" <?php echo empty($gender) ? 'selected' : ''; ?>>-- ไม่ระบุ --</option>
                                        <option value="male" <?php echo $gender === 'male' ? 'selected' : ''; ?>>ชาย (Male)</option>
                                        <option value="female" <?php echo $gender === 'female' ? 'selected' : ''; ?>>หญิง (Female)</option>
                                        <option value="other" <?php echo $gender === 'other' ? 'selected' : ''; ?>>อื่นๆ (Other)</option>
                                    </select>
                                </div>
                                
                                <div class="col-md-6">
                                    <label class="form-label text-muted fw-semibold small">วันเกิด (ปี ค.ศ.)</label>
                                    <div class="input-group">
                                        <span class="input-group-text"><i class="fas fa-birthday-cake text-muted"></i></span>
                                        <input type="date" class="form-control" name="birth_date" value="<?php echo e($birth_date); ?>">
                                    </div>
                                </div>
                                
                                <div class="col-12 mt-3">
                                    <label class="form-label text-muted fw-semibold small">ที่อยู่ปัจจุบัน</label>
                                    <textarea class="form-control" name="address" rows="2" placeholder="บ้านเลขที่ ซอย ถนน แขวง เขต จังหวัด รหัสไปรษณีย์"><?php echo e($address); ?></textarea>
                                </div>
                                
                                <div class="col-12 mt-3 mb-1">
                                    <label class="form-label text-muted fw-semibold small">ข้อมูลแนะนำตัว (Bio) / ความเชี่ยวชาญ</label>
                                    <textarea class="form-control" name="bio" rows="3" placeholder="เขียนอธิบายเกี่ยวกับตัวคุณสั้นๆ..."><?php echo e($bio); ?></textarea>
                                </div>
                            </div>
                            
                            <div class="mt-4 pt-3 border-top text-end">
                                <button type="reset" class="btn btn-light me-2 px-4 shadow-sm">คืนค่า</button>
                                <button type="submit" class="btn btn-primary px-4 shadow-sm"><i class="fas fa-save me-2"></i>บันทึกข้อมูล</button>
                            </div>
                        </form>
                    </div>
                    
                    <!-- TAB: Change Password -->
                    <div class="tab-pane fade" id="password" role="tabpanel" aria-labelledby="password-tab">
                        <form method="POST" action="profile.php">
                            <input type="hidden" name="csrf_token" value="<?php echo e($csrf_token); ?>">
                            <input type="hidden" name="action" value="update_password">
                            
                            <h5 class="fw-bold mb-4">เปลี่ยนรหัสผ่าน</h5>
                            
                            <div class="row g-4 justify-content-center">
                                <div class="col-md-9">
                                    <div class="mb-3">
                                        <label class="form-label text-muted fw-semibold small">รหัสผ่านปัจจุบัน <span class="text-danger">*</span></label>
                                        <div class="input-group">
                                            <span class="input-group-text bg-white"><i class="fas fa-lock text-muted"></i></span>
                                            <input type="password" class="form-control" name="current_password" id="current_pw" required>
                                            <button class="btn btn-outline-secondary bg-white" type="button" onclick="togglePw('current_pw')"><i class="fas fa-eye text-muted"></i></button>
                                        </div>
                                    </div>
                                    
                                    <hr class="my-4 border-light">
                                    
                                    <div class="mb-3">
                                        <label class="form-label text-muted fw-semibold small">รหัสผ่านใหม่ <span class="text-danger">*</span></label>
                                        <div class="input-group">
                                            <span class="input-group-text bg-white"><i class="fas fa-key text-muted"></i></span>
                                            <input type="password" class="form-control" name="new_password" id="new_pw" required minlength="6" placeholder="อย่างน้อย 6 ตัวอักษร">
                                            <button class="btn btn-outline-secondary bg-white" type="button" onclick="togglePw('new_pw')"><i class="fas fa-eye text-muted"></i></button>
                                        </div>
                                    </div>
                                    
                                    <div class="mb-4">
                                        <label class="form-label text-muted fw-semibold small">ยืนยันรหัสผ่านใหม่ <span class="text-danger">*</span></label>
                                        <div class="input-group">
                                            <span class="input-group-text bg-white"><i class="fas fa-check-double text-muted"></i></span>
                                            <input type="password" class="form-control" name="confirm_password" id="confirm_pw" required minlength="6">
                                            <button class="btn btn-outline-secondary bg-white" type="button" onclick="togglePw('confirm_pw')"><i class="fas fa-eye text-muted"></i></button>
                                        </div>
                                    </div>
                                    
                                    <div class="text-end">
                                        <button type="submit" class="btn btn-warning text-white px-4 shadow-sm"><i class="fas fa-key me-2"></i>อัปเดตรหัสผ่าน</button>
                                    </div>
                                </div>
                            </div>
                        </form>
                    </div>
                    
                </div>
            </div>
        </div>
    </div>
</div>

<!-- ==========================================
     MODALS
     ========================================== -->

<!-- Upload Avatar Modal -->
<div class="modal fade" id="uploadAvatarModal" tabindex="-1" aria-labelledby="uploadAvatarModalLabel" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content border-0 shadow">
      <div class="modal-header bg-light">
        <h5 class="modal-title font-weight-bold" id="uploadAvatarModalLabel"><i class="fas fa-camera text-primary me-2"></i>อัปโหลดรูปโปรไฟล์</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>
      <form method="POST" action="profile.php" enctype="multipart/form-data">
          <input type="hidden" name="csrf_token" value="<?php echo e($csrf_token); ?>">
          <input type="hidden" name="action" value="upload_avatar">
          <div class="modal-body p-4 text-center">
              
              <div class="mb-4">
                  <img src="<?php echo $avatar_url; ?>" id="avatarPreview" class="rounded-circle shadow-sm" style="width: 120px; height: 120px; object-fit: cover; border: 3px solid #f8f9fa;">
              </div>
              
              <div class="mb-3 text-start">
                  <label for="avatar_file" class="form-label fw-semibold small">เลือกไฟล์รูปภาพ (JPG, PNG, GIF)</label>
                  <input class="form-control" type="file" id="avatar_file" name="avatar_file" accept=".jpg,.jpeg,.png,.gif" required onchange="previewAvatar(this)">
                  <div class="form-text">ขนาดไฟล์สูงสุด 2MB แนะนำขนาด 500x500 พิกเซล</div>
              </div>
          </div>
          <div class="modal-footer bg-light justify-content-between">
              <?php if (!empty($avatar)): ?>
              <button type="button" class="btn btn-outline-danger" onclick="document.getElementById('removeAvatarForm').submit();">
                  <i class="fas fa-trash-alt me-1"></i> ลบรูปปัจจุบัน
              </button>
              <?php else: ?>
              <div></div>
              <?php endif; ?>
              <div>
                  <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">ยกเลิก</button>
                  <button type="submit" class="btn btn-primary"><i class="fas fa-upload me-1"></i>อัปโหลด</button>
              </div>
          </div>
      </form>
    </div>
  </div>
</div>

<!-- Hidden form for removing avatar -->
<form method="POST" action="profile.php" id="removeAvatarForm" style="display:none;">
    <input type="hidden" name="csrf_token" value="<?php echo e($csrf_token); ?>">
    <input type="hidden" name="action" value="remove_avatar">
</form>

<?php include 'includes/footer.php'; ?>

<script>
// Handle Bootstrap Tab Switching manually if needed, 
// but data-bs-toggle="tab" should handle it natively
document.addEventListener('DOMContentLoaded', function() {
    var tabEls = document.querySelectorAll('button[data-bs-toggle="tab"]');
    tabEls.forEach(function(tabEl) {
        tabEl.addEventListener('show.bs.tab', function(event) {
            // Remove bottom border from all tabs, add to active
            tabEls.forEach(function(el) {
                el.classList.remove('border-bottom', 'border-3', 'border-primary', 'text-dark');
                el.classList.add('text-muted');
            });
            event.target.classList.add('border-bottom', 'border-3', 'border-primary', 'text-dark');
            event.target.classList.remove('text-muted');
        });
    });
});

function togglePw(id) {
    const input = document.getElementById(id);
    const icon = input.nextElementSibling.querySelector('i');
    if (input.type === 'password') {
        input.type = 'text';
        icon.classList.remove('fa-eye');
        icon.classList.add('fa-eye-slash');
    } else {
        input.type = 'password';
        icon.classList.remove('fa-eye-slash');
        icon.classList.add('fa-eye');
    }
}

function previewAvatar(input) {
    if (input.files && input.files[0]) {
        var reader = new FileReader();
        reader.onload = function(e) {
            document.getElementById('avatarPreview').src = e.target.result;
        }
        reader.readAsDataURL(input.files[0]);
    }
}
</script>
