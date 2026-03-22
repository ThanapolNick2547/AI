<?php
require_once 'includes/db.php';
require_once 'includes/auth.php';

// Protect this route - Admin only
requireLogin();
requireRole('admin');

$success = '';
$error = '';

/** ======================================
 *  Handle POST Requests (CRUD Actions)
 *  ====================================== */
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!isset($_POST['csrf_token']) || !validateCSRFToken($_POST['csrf_token'])) {
        $error = "Invalid CSRF Token.";
    } else {
        $action = $_POST['action'] ?? '';

        if ($action === 'create') {
            $username    = trim($_POST['username']);
            $password    = trim($_POST['password']);
            $role        = trim($_POST['role']);
            $status      = trim($_POST['status']);

            if (empty($username) || empty($password)) {
                $error = "กรุณากรอกชื่อผู้ใช้และรหัสผ่านให้ครบถ้วน";
            } elseif (strlen($password) < 6) {
                $error = "รหัสผ่านต้องมีความยาวอย่างน้อย 6 ตัวอักษร";
            } else {
                try {
                    // Check duplicate username
                    $chk = $pdo->prepare("SELECT id FROM users WHERE username = ?");
                    $chk->execute([$username]);
                    if ($chk->rowCount() > 0) {
                        $error = "ชื่อผู้ใช้งาน '$username' มีในระบบแล้ว กรุณาใช้ชื่ออื่น";
                    } else {
                        $hash = password_hash($password, PASSWORD_BCRYPT);
                        $stmt = $pdo->prepare("INSERT INTO users (username, password_hash, role, status) VALUES (?, ?, ?, ?)");
                        $stmt->execute([$username, $hash, $role, $status]);
                        $_SESSION['success_msg'] = "เพิ่มบัญชีผู้ใช้ '{$username}' สำเร็จ!";
                        header("Location: users.php");
                        exit();
                    }
                } catch (PDOException $e) {
                    $error = "เกิดข้อผิดพลาด: " . $e->getMessage();
                }
            }
        }

        elseif ($action === 'update') {
            $id     = intval($_POST['user_id']);
            $username = trim($_POST['username']);
            $role   = trim($_POST['role']);
            $status = trim($_POST['status']);

            try {
                // Check duplicate username (exclude current user)
                $chk = $pdo->prepare("SELECT id FROM users WHERE username = ? AND id != ?");
                $chk->execute([$username, $id]);
                if ($chk->rowCount() > 0) {
                    $error = "ชื่อผู้ใช้งาน '$username' มีในระบบแล้ว";
                } else {
                    $stmt = $pdo->prepare("UPDATE users SET username = ?, role = ?, status = ? WHERE id = ?");
                    $stmt->execute([$username, $role, $status, $id]);
                    $_SESSION['success_msg'] = "อัปเดตบัญชีผู้ใช้สำเร็จ!";
                    header("Location: users.php");
                    exit();
                }
            } catch (PDOException $e) {
                $error = "เกิดข้อผิดพลาด: " . $e->getMessage();
            }
        }

        elseif ($action === 'reset_password') {
            $id       = intval($_POST['user_id']);
            $password = trim($_POST['new_password']);

            if (strlen($password) < 6) {
                $error = "รหัสผ่านต้องมีความยาวอย่างน้อย 6 ตัวอักษร";
            } else {
                try {
                    $hash = password_hash($password, PASSWORD_BCRYPT);
                    $stmt = $pdo->prepare("UPDATE users SET password_hash = ? WHERE id = ?");
                    $stmt->execute([$hash, $id]);
                    $_SESSION['success_msg'] = "รีเซ็ตรหัสผ่านสำเร็จ!";
                    header("Location: users.php");
                    exit();
                } catch (PDOException $e) {
                    $error = "เกิดข้อผิดพลาด: " . $e->getMessage();
                }
            }
        }

        elseif ($action === 'delete') {
            $id = intval($_POST['user_id']);
            // Prevent admin from deleting themselves
            if ($id === intval($_SESSION['user_id'])) {
                $error = "ไม่สามารถลบบัญชีของตัวเองได้";
            } else {
                try {
                    $stmt = $pdo->prepare("DELETE FROM users WHERE id = ?");
                    $stmt->execute([$id]);
                    $_SESSION['success_msg'] = "ลบบัญชีผู้ใช้สำเร็จ!";
                    header("Location: users.php");
                    exit();
                } catch (PDOException $e) {
                    if ($e instanceof PDOException && $e->getCode() == 23000) {
                        $error = "ไม่สามารถลบข้อมูลนี้ได้ เนื่องจากมีการอ้างอิงหรือถูกใช้งานอยู่ในระบบอื่น";
                    } else {
                        $error = "เกิดข้อผิดพลาด: " . $e->getMessage();
                    }
                }
            }
        }

        elseif ($action === 'toggle_status') {
            $id = intval($_POST['user_id']);
            $newStatus = trim($_POST['new_status']);
            if ($id === intval($_SESSION['user_id'])) {
                $error = "ไม่สามารถเปลี่ยนสถานะบัญชีของตัวเองได้";
            } else {
                try {
                    $stmt = $pdo->prepare("UPDATE users SET status = ? WHERE id = ?");
                    $stmt->execute([$newStatus, $id]);
                    $_SESSION['success_msg'] = "เปลี่ยนสถานะบัญชีสำเร็จ!";
                    header("Location: users.php");
                    exit();
                } catch (PDOException $e) {
                    $error = "เกิดข้อผิดพลาด: " . $e->getMessage();
                }
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
 *  Fetch Users
 *  ====================================== */
$search = $_GET['search'] ?? '';
$role_filter = $_GET['role_filter'] ?? '';

$query = "SELECT id, username, role, status, last_login, created_at FROM users WHERE 1=1";
$params = [];

if ($search) {
    $query .= " AND username LIKE ?";
    $params[] = "%$search%";
}
if ($role_filter) {
    $query .= " AND role = ?";
    $params[] = $role_filter;
}
$query .= " ORDER BY created_at DESC";

try {
    $stmt = $pdo->prepare($query);
    $stmt->execute($params);
    $users = $stmt->fetchAll();
} catch (PDOException $e) {
    $error = "เกิดข้อผิดพลาดในการดึงข้อมูล: " . $e->getMessage();
    $users = [];
}

// Stats
try {
    $stats = [
        'total' => $pdo->query("SELECT COUNT(*) FROM users")->fetchColumn(),
        'admin' => $pdo->query("SELECT COUNT(*) FROM users WHERE role = 'admin'")->fetchColumn(),
        'teacher' => $pdo->query("SELECT COUNT(*) FROM users WHERE role = 'teacher'")->fetchColumn(),
        'student' => $pdo->query("SELECT COUNT(*) FROM users WHERE role = 'student'")->fetchColumn(),
        'active' => $pdo->query("SELECT COUNT(*) FROM users WHERE status = 'active'")->fetchColumn(),
        'inactive' => $pdo->query("SELECT COUNT(*) FROM users WHERE status = 'inactive'")->fetchColumn(),
    ];
} catch (PDOException $e) {
    $stats = ['total'=>0,'admin'=>0,'teacher'=>0,'student'=>0,'active'=>0,'inactive'=>0];
}

// Role labels
$roleTH = ['admin' => 'ผู้ดูแลระบบ', 'teacher' => 'ครูผู้สอน', 'student' => 'นักเรียน'];
$roleColors = ['admin' => 'danger', 'teacher' => 'primary', 'student' => 'success'];
$roleIcons = ['admin' => 'fa-user-shield', 'teacher' => 'fa-chalkboard-teacher', 'student' => 'fa-user-graduate'];

include 'includes/header.php';
?>

<!-- Breadcrumb -->
<nav aria-label="breadcrumb">
    <ol class="breadcrumb">
        <li class="breadcrumb-item"><a href="index.php">หน้าหลัก</a></li>
        <li class="breadcrumb-item active" aria-current="page">บัญชีผู้ใช้งาน</li>
    </ol>
</nav>

<div class="d-flex justify-content-between align-items-center mb-4">
    <h2 class="mb-0 fw-bold text-dark"><i class="fas fa-users-cog text-primary me-2"></i>จัดการบัญชีผู้ใช้งาน</h2>
    <button type="button" class="btn btn-primary shadow-sm" data-bs-toggle="modal" data-bs-target="#addUserModal">
        <i class="fas fa-user-plus me-1"></i> เพิ่มบัญชีผู้ใช้ใหม่
    </button>
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

<!-- Stats Cards -->
<div class="row g-3 mb-4">
    <div class="col-6 col-lg-2">
        <div class="card border-0 shadow-sm text-center p-3" style="border-radius:12px;">
            <div class="fw-bold text-primary" style="font-size:2rem;"><?php echo $stats['total']; ?></div>
            <div class="text-muted small">บัญชีทั้งหมด</div>
        </div>
    </div>
    <div class="col-6 col-lg-2">
        <div class="card border-0 shadow-sm text-center p-3" style="border-radius:12px;">
            <div class="fw-bold text-danger" style="font-size:2rem;"><?php echo $stats['admin']; ?></div>
            <div class="text-muted small">ผู้ดูแลระบบ</div>
        </div>
    </div>
    <div class="col-6 col-lg-2">
        <div class="card border-0 shadow-sm text-center p-3" style="border-radius:12px;">
            <div class="fw-bold text-primary" style="font-size:2rem;"><?php echo $stats['teacher']; ?></div>
            <div class="text-muted small">ครูผู้สอน</div>
        </div>
    </div>
    <div class="col-6 col-lg-2">
        <div class="card border-0 shadow-sm text-center p-3" style="border-radius:12px;">
            <div class="fw-bold text-success" style="font-size:2rem;"><?php echo $stats['student']; ?></div>
            <div class="text-muted small">นักเรียน</div>
        </div>
    </div>
    <div class="col-6 col-lg-2">
        <div class="card border-0 shadow-sm text-center p-3" style="border-radius:12px;">
            <div class="fw-bold text-success" style="font-size:2rem;"><?php echo $stats['active']; ?></div>
            <div class="text-muted small">ใช้งานอยู่</div>
        </div>
    </div>
    <div class="col-6 col-lg-2">
        <div class="card border-0 shadow-sm text-center p-3" style="border-radius:12px;">
            <div class="fw-bold text-secondary" style="font-size:2rem;"><?php echo $stats['inactive']; ?></div>
            <div class="text-muted small">ปิดใช้งาน</div>
        </div>
    </div>
</div>

<!-- Filter Bar -->
<div class="card border-0 shadow-sm mb-4" style="border-radius:12px;">
    <div class="card-body py-3">
        <form method="GET" class="row g-2 align-items-center">
            <div class="col-md-5">
                <div class="input-group">
                    <span class="input-group-text bg-white border-end-0"><i class="fas fa-search text-muted"></i></span>
                    <input type="text" name="search" class="form-control border-start-0" placeholder="ค้นหาชื่อผู้ใช้งาน..." value="<?php echo e($search); ?>">
                </div>
            </div>
            <div class="col-md-3">
                <select name="role_filter" class="form-select">
                    <option value="">ทุกประเภท</option>
                    <option value="admin" <?php echo $role_filter=='admin'?'selected':''; ?>>ผู้ดูแลระบบ</option>
                    <option value="teacher" <?php echo $role_filter=='teacher'?'selected':''; ?>>ครูผู้สอน</option>
                    <option value="student" <?php echo $role_filter=='student'?'selected':''; ?>>นักเรียน</option>
                </select>
            </div>
            <div class="col-md-2">
                <button type="submit" class="btn btn-primary w-100"><i class="fas fa-search me-1"></i>ค้นหา</button>
            </div>
            <div class="col-md-2">
                <a href="users.php" class="btn btn-outline-secondary w-100"><i class="fas fa-redo me-1"></i>ล้างค่า</a>
            </div>
        </form>
    </div>
</div>

<!-- Users Table -->
<div class="card border-0 shadow-sm" style="border-radius:12px;">
    <div class="card-header bg-white border-0 py-3" style="border-radius:12px 12px 0 0;">
        <h5 class="mb-0 fw-bold">รายการบัญชีผู้ใช้งาน
            <span class="badge bg-primary ms-2"><?php echo count($users); ?></span>
        </h5>
    </div>
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0 datatable">
                <thead class="bg-light">
                    <tr>
                        <th class="ps-4" style="width:50px;">#</th>
                        <th>ชื่อผู้ใช้งาน</th>
                        <th>ประเภทบัญชี</th>
                        <th>สถานะ</th>
                        <th>เข้าใช้งานล่าสุด</th>
                        <th>วันที่สร้าง</th>
                        <th class="pe-4 text-end" style="width:180px;">จัดการ</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach($users as $u): ?>
                        <tr class="<?php echo $u['id'] == $_SESSION['user_id'] ? 'table-active' : ''; ?>">
                            <td class="ps-4 text-muted small"><?php echo $u['id']; ?></td>
                            <td>
                                <div class="d-flex align-items-center">
                                    <div class="me-3" style="width:40px;height:40px;border-radius:50%;background:<?php echo ['admin'=>'#ffebee','teacher'=>'#e3f2fd','student'=>'#e8f5e9'][$u['role']] ?? '#f5f5f5'; ?>;display:flex;align-items:center;justify-content:center;">
                                        <i class="fas <?php echo $roleIcons[$u['role']] ?? 'fa-user'; ?> text-<?php echo $roleColors[$u['role']] ?? 'secondary'; ?>"></i>
                                    </div>
                                    <div>
                                        <div class="fw-semibold text-dark">
                                            <?php echo e($u['username']); ?>
                                            <?php if($u['id'] == $_SESSION['user_id']): ?>
                                                <span class="badge bg-warning text-dark ms-1 small">คุณ</span>
                                            <?php endif; ?>
                                        </div>
                                        <small class="text-muted">ID: <?php echo $u['id']; ?></small>
                                    </div>
                                </div>
                            </td>
                            <td>
                                <span class="badge bg-<?php echo $roleColors[$u['role']] ?? 'secondary'; ?> bg-opacity-10 border border-<?php echo $roleColors[$u['role']] ?? 'secondary'; ?> text-<?php echo $roleColors[$u['role']] ?? 'secondary'; ?>">
                                    <i class="fas <?php echo $roleIcons[$u['role']] ?? 'fa-user'; ?> me-1"></i>
                                    <?php echo $roleTH[$u['role']] ?? e($u['role']); ?>
                                </span>
                            </td>
                            <td>
                                <?php if($u['status'] === 'active'): ?>
                                    <span class="badge bg-success"><i class="fas fa-circle me-1" style="font-size:0.6rem;"></i>ใช้งานอยู่</span>
                                <?php else: ?>
                                    <span class="badge bg-secondary"><i class="fas fa-circle me-1" style="font-size:0.6rem;"></i>ปิดใช้งาน</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <small class="text-muted">
                                    <?php echo $u['last_login'] ? date('d/m/Y H:i', strtotime($u['last_login'])) : '—'; ?>
                                </small>
                            </td>
                            <td>
                                <small class="text-muted">
                                    <?php echo date('d/m/Y', strtotime($u['created_at'])); ?>
                                </small>
                            </td>
                            <td class="pe-4 text-end">
                                <!-- Edit -->
                                <button class="btn btn-sm btn-outline-primary btn-edit me-1"
                                        data-id="<?php echo $u['id']; ?>"
                                        data-username="<?php echo e($u['username']); ?>"
                                        data-role="<?php echo e($u['role']); ?>"
                                        data-status="<?php echo e($u['status']); ?>"
                                        data-bs-toggle="modal" data-bs-target="#editUserModal"
                                        title="แก้ไข">
                                    <i class="fas fa-edit"></i>
                                </button>
                                <!-- Reset Password -->
                                <button class="btn btn-sm btn-outline-warning btn-reset me-1"
                                        data-id="<?php echo $u['id']; ?>"
                                        data-username="<?php echo e($u['username']); ?>"
                                        data-bs-toggle="modal" data-bs-target="#resetPasswordModal"
                                        title="รีเซ็ตรหัสผ่าน">
                                    <i class="fas fa-key"></i>
                                </button>
                                <!-- Toggle Status -->
                                <?php if($u['id'] != $_SESSION['user_id']): ?>
                                <form method="POST" class="d-inline">
                                    <input type="hidden" name="csrf_token" value="<?php echo e($csrf_token); ?>">
                                    <input type="hidden" name="action" value="toggle_status">
                                    <input type="hidden" name="user_id" value="<?php echo $u['id']; ?>">
                                    <input type="hidden" name="new_status" value="<?php echo $u['status']==='active' ? 'inactive' : 'active'; ?>">
                                    <button type="submit" class="btn btn-sm btn-outline-<?php echo $u['status']==='active' ? 'secondary' : 'success'; ?> me-1"
                                            title="<?php echo $u['status']==='active' ? 'ปิดใช้งาน' : 'เปิดใช้งาน'; ?>">
                                        <i class="fas <?php echo $u['status']==='active' ? 'fa-ban' : 'fa-check-circle'; ?>"></i>
                                    </button>
                                </form>
                                <!-- Delete -->
                                <button class="btn btn-sm btn-outline-danger btn-delete"
                                        data-id="<?php echo $u['id']; ?>"
                                        data-username="<?php echo e($u['username']); ?>"
                                        data-bs-toggle="modal" data-bs-target="#deleteUserModal"
                                        title="ลบ">
                                    <i class="fas fa-trash-alt"></i>
                                </button>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
    <div class="card-footer bg-white border-0 py-3" style="border-radius:0 0 12px 12px;">
        <small class="text-muted">รวมทั้งหมด: <?php echo count($users); ?> บัญชี</small>
    </div>
</div>

<!-- ==========================================
     MODALS
     ========================================== -->

<!-- ADD USER MODAL -->
<div class="modal fade" id="addUserModal" tabindex="-1" aria-labelledby="addUserModalLabel" aria-hidden="true">
  <div class="modal-dialog modal-lg">
    <div class="modal-content border-0 shadow">
      <div class="modal-header bg-primary text-white">
        <h5 class="modal-title" id="addUserModalLabel"><i class="fas fa-user-plus me-2"></i>เพิ่มบัญชีผู้ใช้ใหม่</h5>
        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>
      <form method="POST" action="users.php" id="addUserForm">
          <input type="hidden" name="csrf_token" value="<?php echo e($csrf_token); ?>">
          <input type="hidden" name="action" value="create">
          <div class="modal-body">
            <div class="row g-3">
                <div class="col-md-6">
                    <label class="form-label text-muted fw-semibold small">ชื่อผู้ใช้งาน (Username) <span class="text-danger">*</span></label>
                    <div class="input-group">
                        <span class="input-group-text"><i class="fas fa-user text-muted"></i></span>
                        <input type="text" class="form-control" name="username" placeholder="เช่น teacher01" required minlength="3">
                    </div>
                    <div class="form-text">อย่างน้อย 3 ตัวอักษร ไม่มีช่องว่าง</div>
                </div>
                <div class="col-md-6">
                    <label class="form-label text-muted fw-semibold small">รหัสผ่าน <span class="text-danger">*</span></label>
                    <div class="input-group">
                        <span class="input-group-text"><i class="fas fa-lock text-muted"></i></span>
                        <input type="password" class="form-control" name="password" id="add_password" placeholder="อย่างน้อย 6 ตัวอักษร" required minlength="6">
                        <button class="btn btn-outline-secondary" type="button" onclick="togglePassword('add_password')"><i class="fas fa-eye"></i></button>
                    </div>
                </div>
                <div class="col-md-6">
                    <label class="form-label text-muted fw-semibold small">ประเภทบัญชี <span class="text-danger">*</span></label>
                    <select class="form-select" name="role" required>
                        <option value="teacher" selected>ครูผู้สอน</option>
                        <option value="student">นักเรียน</option>
                        <option value="admin">ผู้ดูแลระบบ</option>
                    </select>
                </div>
                <div class="col-md-6">
                    <label class="form-label text-muted fw-semibold small">สถานะบัญชี <span class="text-danger">*</span></label>
                    <select class="form-select" name="status" required>
                        <option value="active" selected>ใช้งานอยู่ (Active)</option>
                        <option value="inactive">ปิดใช้งาน (Inactive)</option>
                    </select>
                </div>
            </div>

            <!-- Role description boxes -->
            <div class="alert alert-info border-0 mt-3 py-2" style="background:#e3f2fd;">
                <i class="fas fa-info-circle me-2 text-primary"></i>
                <strong>ผู้ดูแลระบบ:</strong> เข้าถึงได้ทุกเมนู &nbsp;|&nbsp;
                <strong>ครูผู้สอน:</strong> จัดการตาราง, เกรด, เช็คชื่อ &nbsp;|&nbsp;
                <strong>นักเรียน:</strong> ดูข้อมูลของตัวเอง
            </div>
          </div>
          <div class="modal-footer bg-light">
            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">ยกเลิก</button>
            <button type="submit" class="btn btn-primary"><i class="fas fa-save me-1"></i>บันทึกข้อมูล</button>
          </div>
      </form>
    </div>
  </div>
</div>

<!-- EDIT USER MODAL -->
<div class="modal fade" id="editUserModal" tabindex="-1" aria-labelledby="editUserModalLabel" aria-hidden="true">
  <div class="modal-dialog modal-lg">
    <div class="modal-content border-0 shadow">
      <div class="modal-header bg-light">
        <h5 class="modal-title text-dark" id="editUserModalLabel"><i class="fas fa-edit me-2 text-primary"></i>แก้ไขบัญชีผู้ใช้</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>
      <form method="POST" action="users.php">
          <input type="hidden" name="csrf_token" value="<?php echo e($csrf_token); ?>">
          <input type="hidden" name="action" value="update">
          <input type="hidden" name="user_id" id="edit_user_id">
          <div class="modal-body">
            <div class="row g-3">
                <div class="col-md-6">
                    <label class="form-label text-muted fw-semibold small">ชื่อผู้ใช้งาน (Username) <span class="text-danger">*</span></label>
                    <div class="input-group">
                        <span class="input-group-text"><i class="fas fa-user text-muted"></i></span>
                        <input type="text" class="form-control" id="edit_username" name="username" required>
                    </div>
                </div>
                <div class="col-md-6">
                    <label class="form-label text-muted fw-semibold small">ประเภทบัญชี <span class="text-danger">*</span></label>
                    <select class="form-select" id="edit_role" name="role" required>
                        <option value="teacher">ครูผู้สอน</option>
                        <option value="student">นักเรียน</option>
                        <option value="admin">ผู้ดูแลระบบ</option>
                    </select>
                </div>
                <div class="col-md-6">
                    <label class="form-label text-muted fw-semibold small">สถานะบัญชี <span class="text-danger">*</span></label>
                    <select class="form-select" id="edit_status" name="status" required>
                        <option value="active">ใช้งานอยู่ (Active)</option>
                        <option value="inactive">ปิดใช้งาน (Inactive)</option>
                    </select>
                </div>
                <div class="col-md-6 d-flex align-items-end">
                    <div class="alert alert-warning py-2 mb-0 w-100 small">
                        <i class="fas fa-exclamation-triangle me-1"></i>
                        การเปลี่ยนรหัสผ่านให้ใช้ปุ่ม "รีเซ็ตรหัสผ่าน"
                    </div>
                </div>
            </div>
          </div>
          <div class="modal-footer bg-light">
            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">ยกเลิก</button>
            <button type="submit" class="btn btn-primary"><i class="fas fa-save me-1"></i>อัปเดตข้อมูล</button>
          </div>
      </form>
    </div>
  </div>
</div>

<!-- RESET PASSWORD MODAL -->
<div class="modal fade" id="resetPasswordModal" tabindex="-1" aria-labelledby="resetPasswordModalLabel" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content border-0 shadow">
      <div class="modal-header" style="background:#ff6f00;color:#fff;">
        <h5 class="modal-title" id="resetPasswordModalLabel"><i class="fas fa-key me-2"></i>รีเซ็ตรหัสผ่าน</h5>
        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>
      <form method="POST" action="users.php">
          <input type="hidden" name="csrf_token" value="<?php echo e($csrf_token); ?>">
          <input type="hidden" name="action" value="reset_password">
          <input type="hidden" name="user_id" id="reset_user_id">
          <div class="modal-body p-4">
              <p class="text-muted mb-3">กำลังรีเซ็ตรหัสผ่านของ: <strong id="reset_username" class="text-dark"></strong></p>
              <label class="form-label fw-semibold small">รหัสผ่านใหม่ <span class="text-danger">*</span></label>
              <div class="input-group">
                  <span class="input-group-text"><i class="fas fa-lock text-muted"></i></span>
                  <input type="password" class="form-control" name="new_password" id="new_password" placeholder="อย่างน้อย 6 ตัวอักษร" required minlength="6">
                  <button class="btn btn-outline-secondary" type="button" onclick="togglePassword('new_password')"><i class="fas fa-eye"></i></button>
              </div>
          </div>
          <div class="modal-footer bg-light">
            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">ยกเลิก</button>
            <button type="submit" class="btn btn-warning text-white"><i class="fas fa-key me-1"></i>รีเซ็ตรหัสผ่าน</button>
          </div>
      </form>
    </div>
  </div>
</div>

<!-- DELETE USER MODAL -->
<div class="modal fade" id="deleteUserModal" tabindex="-1" aria-labelledby="deleteUserModalLabel" aria-hidden="true">
  <div class="modal-dialog modal-sm modal-dialog-centered">
    <div class="modal-content border-0 shadow">
      <div class="modal-header bg-danger text-white">
        <h5 class="modal-title" id="deleteUserModalLabel"><i class="fas fa-exclamation-triangle me-2"></i>ยืนยันการลบ</h5>
        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>
      <form method="POST" action="users.php">
          <input type="hidden" name="csrf_token" value="<?php echo e($csrf_token); ?>">
          <input type="hidden" name="action" value="delete">
          <input type="hidden" name="user_id" id="delete_user_id">
          <div class="modal-body text-center p-4">
            <i class="fas fa-user-times text-danger mb-3" style="font-size:3rem;"></i>
            <p class="mb-1">คุณแน่ใจหรือไม่ว่าต้องการลบบัญชีนี้?</p>
            <strong id="delete_username" class="text-dark d-block mb-3"></strong>
            <p class="text-muted small mb-0">การกระทำนี้ไม่สามารถย้อนกลับได้</p>
          </div>
          <div class="modal-footer bg-light justify-content-center border-0">
            <button type="button" class="btn btn-secondary btn-sm px-3" data-bs-dismiss="modal">ยกเลิก</button>
            <button type="submit" class="btn btn-danger btn-sm px-3">ยืนยันการลบ</button>
          </div>
      </form>
    </div>
  </div>
</div>

<?php include 'includes/footer.php'; ?>

<script>
document.addEventListener('DOMContentLoaded', function() {
    // Edit Modal
    document.querySelectorAll('.btn-edit').forEach(btn => {
        btn.addEventListener('click', function() {
            document.getElementById('edit_user_id').value = this.dataset.id;
            document.getElementById('edit_username').value = this.dataset.username;
            document.getElementById('edit_role').value = this.dataset.role;
            document.getElementById('edit_status').value = this.dataset.status;
        });
    });

    // Reset Password Modal
    document.querySelectorAll('.btn-reset').forEach(btn => {
        btn.addEventListener('click', function() {
            document.getElementById('reset_user_id').value = this.dataset.id;
            document.getElementById('reset_username').textContent = this.dataset.username;
            document.getElementById('new_password').value = '';
        });
    });

    // Delete Modal
    document.querySelectorAll('.btn-delete').forEach(btn => {
        btn.addEventListener('click', function() {
            document.getElementById('delete_user_id').value = this.dataset.id;
            document.getElementById('delete_username').textContent = this.dataset.username;
        });
    });
});

// Toggle password visibility
function togglePassword(id) {
    const input = document.getElementById(id);
    input.type = input.type === 'password' ? 'text' : 'password';
}
</script>
