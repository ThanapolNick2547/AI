<?php
require_once 'includes/db.php';
require_once 'includes/auth.php';

// Protect this route
requireLogin();

$success = '';
$error = '';
$uploadDir = 'assets/images/students/';

/**
 * Handle File Upload securely
 */
function handleProfileUpload($file, $uploadDir) {
    if (!isset($file) || $file['error'] !== UPLOAD_ERR_OK) {
        return null;
    }
    
    // Check file size limit (2MB)
    if ($file['size'] > 2 * 1024 * 1024) {
        throw new Exception("File size exceeds 2MB limit.");
    }
    
    // Check MIME type securely
    $finfo = finfo_open(FILEINFO_MIME_TYPE);
    $mimeType = finfo_file($finfo, $file['tmp_name']);
    finfo_close($finfo);
    
    $allowedMimeTypes = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];
    if (!in_array($mimeType, $allowedMimeTypes)) {
        throw new Exception("Invalid file type. Only JPG, PNG, GIF, and WEBP are allowed.");
    }
    
    // Generate unique name
    $extension = pathinfo($file['name'], PATHINFO_EXTENSION);
    $newName = uniqid('stu_') . '_' . time() . '.' . $extension;
    $destination = $uploadDir . $newName;
    
    if (move_uploaded_file($file['tmp_name'], $destination)) {
        return $newName;
    } else {
        throw new Exception("Failed to save uploaded file.");
    }
}

/** ======================================
 *  Handle POST Requests (CRUD Actions)
 *  ====================================== */
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!isset($_POST['csrf_token']) || !validateCSRFToken($_POST['csrf_token'])) {
        $error = "Invalid CSRF Token.";
    } else {
        $action = $_POST['action'] ?? '';

        // CREATE STUDENT
        if ($action === 'create') {
            $code = trim($_POST['student_code']);
            $first = trim($_POST['first_name']);
            $last = trim($_POST['last_name']);
            $class_level = trim($_POST['class_level']);
            $room_number = trim($_POST['room_number']);
            $parent_phone = trim($_POST['parent_phone']);
            $profile_pic = null;

            try {
                if (!empty($_FILES['profile_picture']['name'])) {
                    $profile_pic = handleProfileUpload($_FILES['profile_picture'], $uploadDir);
                }

                $stmt = $pdo->prepare("INSERT INTO students (student_code, first_name, last_name, class_level, room_number, parent_phone, profile_picture) VALUES (?, ?, ?, ?, ?, ?, ?)");
                $stmt->execute([$code, $first, $last, $class_level, $room_number, $parent_phone, $profile_pic]);
                $_SESSION['success_msg'] = "Student added successfully!";
                header("Location: students.php");
                exit();
            } catch (Exception $e) {
                if ($e instanceof PDOException && $e->getCode() == 23000) {
                    $error = "รหัสนักเรียนซ้ำในระบบ";
                } else {
                    $error = "Error adding student: " . $e->getMessage();
                }
            }
        }

        // UPDATE STUDENT
        else if ($action === 'update') {
            $id = $_POST['student_id'];
            $code = trim($_POST['student_code']);
            $first = trim($_POST['first_name']);
            $last = trim($_POST['last_name']);
            $class_level = trim($_POST['class_level']);
            $room_number = trim($_POST['room_number']);
            $parent_phone = trim($_POST['parent_phone']);
            
            try {
                $stmt = $pdo->prepare("SELECT profile_picture FROM students WHERE id = ?");
                $stmt->execute([$id]);
                $student = $stmt->fetch();
                $profile_pic = $student['profile_picture'] ?? null;

                if (!empty($_FILES['profile_picture']['name'])) {
                    $new_pic = handleProfileUpload($_FILES['profile_picture'], $uploadDir);
                    if ($new_pic) {
                        // Delete old profile picture
                        if ($profile_pic && file_exists($uploadDir . $profile_pic)) {
                            unlink($uploadDir . $profile_pic);
                        }
                        $profile_pic = $new_pic;
                    }
                }

                $stmt = $pdo->prepare("UPDATE students SET student_code = ?, first_name = ?, last_name = ?, class_level = ?, room_number = ?, parent_phone = ?, profile_picture = ? WHERE id = ?");
                $stmt->execute([$code, $first, $last, $class_level, $room_number, $parent_phone, $profile_pic, $id]);
                $_SESSION['success_msg'] = "Student updated successfully!";
                header("Location: students.php");
                exit();
            } catch (Exception $e) {
                if ($e instanceof PDOException && $e->getCode() == 23000) {
                    $error = "รหัสนักเรียนซ้ำในระบบ";
                } else {
                    $error = "Error updating student: " . $e->getMessage();
                }
            }
        }

        // DELETE STUDENT
        else if ($action === 'delete') {
            $id = $_POST['student_id'];
            try {
                // Fetch to delete picture
                $stmt = $pdo->prepare("SELECT profile_picture FROM students WHERE id = ?");
                $stmt->execute([$id]);
                $student = $stmt->fetch();
                
                if ($student && $student['profile_picture']) {
                    if (file_exists($uploadDir . $student['profile_picture'])) {
                        unlink($uploadDir . $student['profile_picture']);
                    }
                }

                $stmt = $pdo->prepare("DELETE FROM students WHERE id = ?");
                $stmt->execute([$id]);
                $_SESSION['success_msg'] = "Student deleted successfully!";
                header("Location: students.php");
                exit();
            } catch (Exception $e) {
                if ($e instanceof PDOException && $e->getCode() == 23000) {
                    $error = "ไม่สามารถลบข้อมูลนี้ได้ เนื่องจากมีการอ้างอิงหรือถูกใช้งานอยู่ในระบบอื่น";
                } else {
                    $error = "Error deleting student: " . $e->getMessage();
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
 *  Fetch Data for READ
 *  ====================================== */
$search = $_GET['search'] ?? '';
$query = "SELECT * FROM students";
$params = [];

if ($search) {
    $query .= " WHERE student_code LIKE ? OR first_name LIKE ? OR last_name LIKE ?";
    $searchTerm = "%$search%";
    $params = [$searchTerm, $searchTerm, $searchTerm];
}

$query .= " ORDER BY id DESC";

try {
    $stmt = $pdo->prepare($query);
    $stmt->execute($params);
    $students = $stmt->fetchAll();
} catch (PDOException $e) {
    $error = "Error fetching data: " . $e->getMessage();
    $students = [];
}

// Include Layout Header
include 'includes/header.php';
?>

<!-- Breadcrumb -->
<nav aria-label="breadcrumb">
    <ol class="breadcrumb">
        <li class="breadcrumb-item"><a href="index.php">หน้าหลัก</a></li>
        <li class="breadcrumb-item"><a href="#">ข้อมูลหลัก</a></li>
        <li class="breadcrumb-item active" aria-current="page">ข้อมูลนักเรียน</li>
    </ol>
</nav>

<div class="d-flex justify-content-between align-items-center mb-4">
    <h2 class="mb-0 fw-bold text-dark">จัดการข้อมูลนักเรียน</h2>
    <button type="button" class="btn btn-primary shadow-sm" data-bs-toggle="modal" data-bs-target="#addStudentModal">
        <i class="fas fa-plus-circle me-1"></i> เพิ่มข้อมูลนักเรียนใหม่
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

<!-- Data Table Card -->
<div class="card border-0 shadow-sm" style="border-radius: 12px; border: 1px solid var(--border-color) !important;">
    <div class="card-header bg-white border-0 py-3 d-flex justify-content-between align-items-center" style="border-radius: 12px 12px 0 0;">
        <h5 class="mb-0 fw-bold">รายชื่อนักเรียนทั้งหมด</h5>
        <form method="GET" class="d-flex" style="max-width: 300px;">
            <div class="input-group input-group-sm">
                <input type="text" name="search" class="form-control" placeholder="ค้นหาชื่อ หรือ รหัส..." value="<?php echo e($search); ?>">
                <button class="btn btn-outline-secondary" type="submit"><i class="fas fa-search"></i></button>
                <?php if ($search): ?>
                    <a href="students.php" class="btn btn-outline-danger"><i class="fas fa-times"></i></a>
                <?php endif; ?>
            </div>
        </form>
    </div>
    
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0 datatable">
                <thead class="bg-light">
                    <tr>
                        <th class="ps-4">รหัสนักเรียน</th>
                        <th>รูปโปรไฟล์</th>
                        <th>ชื่อ-นามสกุล</th>
                        <th>ชั้นเรียน/ห้อง</th>
                        <th>เบอร์ผู้ปกครอง</th>
                        <th class="pe-4 text-end">จัดการ</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (count($students) > 0): ?>
                        <?php foreach($students as $s): ?>
                            <tr>
                                <td class="ps-4 fw-semibold text-primary">
                                    <i class="fas fa-id-card text-muted me-1"></i> 
                                    <?php echo e($s['student_code']); ?>
                                </td>
                                <td>
                                    <?php if ($s['profile_picture'] && file_exists($uploadDir . $s['profile_picture'])): ?>
                                        <img src="<?php echo $uploadDir . e($s['profile_picture']); ?>" alt="Profile" class="rounded-circle object-fit-cover shadow-sm border" width="45" height="45">
                                    <?php else: ?>
                                        <div class="bg-primary bg-opacity-10 text-primary rounded-circle d-flex justify-content-center align-items-center shadow-sm" style="width: 45px; height: 45px; font-weight:bold; font-size:1.2rem;">
                                            <?php echo strtoupper(substr(e($s['first_name']), 0, 1)); ?>
                                        </div>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <h6 class="mb-0"><?php echo e($s['first_name'] . ' ' . $s['last_name']); ?></h6>
                                </td>
                                <td>
                                    <span class="badge bg-info text-dark border"><i class="fas fa-layer-group me-1"></i> <?php echo e($s['class_level']); ?></span>
                                    <span class="badge bg-light text-dark border ms-1">ห้อง <?php echo e($s['room_number']); ?></span>
                                </td>
                                <td>
                                    <?php if($s['parent_phone']): ?>
                                        <div><i class="fas fa-phone fa-sm text-muted me-1"></i> <?php echo e($s['parent_phone']); ?></div>
                                    <?php else: ?>
                                        <span class="text-muted small">N/A</span>
                                    <?php endif; ?>
                                </td>
                                <td class="pe-4 text-end">
                                    <button class="btn btn-sm btn-outline-primary btn-edit me-1" 
                                            data-id="<?php echo $s['id']; ?>"
                                            data-code="<?php echo e($s['student_code']); ?>"
                                            data-first="<?php echo e($s['first_name']); ?>"
                                            data-last="<?php echo e($s['last_name']); ?>"
                                            data-class="<?php echo e($s['class_level']); ?>"
                                            data-room="<?php echo e($s['room_number']); ?>"
                                            data-phone="<?php echo e($s['parent_phone']); ?>"
                                            data-bs-toggle="modal" data-bs-target="#editStudentModal">
                                        <i class="fas fa-edit"></i>
                                    </button>
                                    
                                    <button type="button" class="btn btn-sm btn-outline-danger btn-delete" 
                                            data-id="<?php echo $s['id']; ?>"
                                            data-name="<?php echo e($s['first_name'] . ' ' . $s['last_name']); ?>"
                                            data-bs-toggle="modal" data-bs-target="#deleteStudentModal">
                                        <i class="fas fa-trash-alt"></i>
                                    </button>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
    <div class="card-footer bg-white border-top-0 py-3" style="border-radius: 0 0 12px 12px;">
        <small class="text-muted">รวมทั้งหมด: <?php echo count($students); ?> รายการ</small>
    </div>
</div>

<!-- ==========================================
     MODALS 
     ========================================== -->

<!-- ADD MODAL -->
<div class="modal fade" id="addStudentModal" tabindex="-1" aria-labelledby="addStudentModalLabel" aria-hidden="true">
  <div class="modal-dialog">
    <div class="modal-content border-0 shadow">
      <div class="modal-header bg-primary text-white">
        <h5 class="modal-title" id="addStudentModalLabel"><i class="fas fa-user-plus me-2"></i> Add New Student</h5>
        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>
      <form method="POST" action="students.php" enctype="multipart/form-data">
          <input type="hidden" name="csrf_token" value="<?php echo e($csrf_token); ?>">
          <input type="hidden" name="action" value="create">
          
          <div class="modal-body">
            
            <div class="mb-3 text-center">
                <label for="profile_picture" class="form-label text-muted fw-semibold small d-block text-start">Profile Picture (Max 2MB)</label>
                <div class="input-group">
                    <input type="file" class="form-control" id="profile_picture" name="profile_picture" accept="image/png, image/jpeg, image/gif, image/webp">
                </div>
            </div>

            <div class="mb-3">
                <label for="student_code" class="form-label text-muted fw-semibold small">Student ID / Code <span class="text-danger">*</span></label>
                <input type="text" class="form-control" id="student_code" name="student_code" required>
            </div>
            
            <div class="row">
                <div class="col-md-6 mb-3">
                    <label for="first_name" class="form-label text-muted fw-semibold small">First Name <span class="text-danger">*</span></label>
                    <input type="text" class="form-control" id="first_name" name="first_name" required>
                </div>
                <div class="col-md-6 mb-3">
                    <label for="last_name" class="form-label text-muted fw-semibold small">Last Name <span class="text-danger">*</span></label>
                    <input type="text" class="form-control" id="last_name" name="last_name" required>
                </div>
            </div>
            
            <div class="row">
                <div class="col-md-6 mb-3">
                    <label for="class_level" class="form-label text-muted fw-semibold small">Class Level <span class="text-danger">*</span></label>
                    <select class="form-select" id="class_level" name="class_level" required>
                        <option value="">Select Level...</option>
                        <option value="M.1">M.1</option>
                        <option value="M.2">M.2</option>
                        <option value="M.3">M.3</option>
                        <option value="M.4">M.4</option>
                        <option value="M.5">M.5</option>
                        <option value="M.6">M.6</option>
                    </select>
                </div>
                <div class="col-md-6 mb-3">
                    <label for="room_number" class="form-label text-muted fw-semibold small">Room Number <span class="text-danger">*</span></label>
                    <input type="text" class="form-control" id="room_number" name="room_number" placeholder="e.g. 1" required>
                </div>
            </div>
            
            <div class="mb-3">
                <label for="parent_phone" class="form-label text-muted fw-semibold small">Parent Phone Number</label>
                <input type="text" class="form-control" id="parent_phone" name="parent_phone">
            </div>

          </div>
          
          <div class="modal-footer bg-light">
            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
            <button type="submit" class="btn btn-primary">Save Student</button>
          </div>
      </form>
    </div>
  </div>
</div>

<!-- EDIT MODAL -->
<div class="modal fade" id="editStudentModal" tabindex="-1" aria-labelledby="editStudentModalLabel" aria-hidden="true">
  <div class="modal-dialog">
    <div class="modal-content border-0 shadow">
      <div class="modal-header bg-light">
        <h5 class="modal-title text-dark" id="editStudentModalLabel"><i class="fas fa-edit me-2 text-primary"></i> Edit Student</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>
      <form method="POST" action="students.php" enctype="multipart/form-data">
          <input type="hidden" name="csrf_token" value="<?php echo e($csrf_token); ?>">
          <input type="hidden" name="action" value="update">
          <input type="hidden" name="student_id" id="edit_student_id">
          
          <div class="modal-body">

            <div class="mb-3">
                <label for="edit_profile_picture" class="form-label text-muted fw-semibold small">Update Profile Picture</label>
                <input type="file" class="form-control" id="edit_profile_picture" name="profile_picture" accept="image/png, image/jpeg, image/gif, image/webp">
                <small class="text-muted">Leave empty to keep current picture.</small>
            </div>

            <div class="mb-3">
                <label for="edit_student_code" class="form-label text-muted fw-semibold small">Student ID / Code <span class="text-danger">*</span></label>
                <input type="text" class="form-control" id="edit_student_code" name="student_code" required>
            </div>
            
            <div class="row">
                <div class="col-md-6 mb-3">
                    <label for="edit_first_name" class="form-label text-muted fw-semibold small">First Name <span class="text-danger">*</span></label>
                    <input type="text" class="form-control" id="edit_first_name" name="first_name" required>
                </div>
                <div class="col-md-6 mb-3">
                    <label for="edit_last_name" class="form-label text-muted fw-semibold small">Last Name <span class="text-danger">*</span></label>
                    <input type="text" class="form-control" id="edit_last_name" name="last_name" required>
                </div>
            </div>
            
            <div class="row">
                <div class="col-md-6 mb-3">
                    <label for="edit_class_level" class="form-label text-muted fw-semibold small">Class Level <span class="text-danger">*</span></label>
                    <select class="form-select" id="edit_class_level" name="class_level" required>
                        <option value="">Select Level...</option>
                        <option value="M.1">M.1</option>
                        <option value="M.2">M.2</option>
                        <option value="M.3">M.3</option>
                        <option value="M.4">M.4</option>
                        <option value="M.5">M.5</option>
                        <option value="M.6">M.6</option>
                    </select>
                </div>
                <div class="col-md-6 mb-3">
                    <label for="edit_room_number" class="form-label text-muted fw-semibold small">Room Number <span class="text-danger">*</span></label>
                    <input type="text" class="form-control" id="edit_room_number" name="room_number" required>
                </div>
            </div>
            
            <div class="mb-3">
                <label for="edit_parent_phone" class="form-label text-muted fw-semibold small">Parent Phone Number</label>
                <input type="text" class="form-control" id="edit_parent_phone" name="parent_phone">
            </div>

          </div>
          
          <div class="modal-footer bg-light">
            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
            <button type="submit" class="btn btn-primary">Update Student</button>
          </div>
      </form>
    </div>
  </div>
</div>

<!-- DELETE MODAL -->
<div class="modal fade" id="deleteStudentModal" tabindex="-1" aria-labelledby="deleteStudentModalLabel" aria-hidden="true">
  <div class="modal-dialog modal-sm modal-dialog-centered">
    <div class="modal-content border-0 shadow">
      <div class="modal-header bg-danger text-white">
        <h5 class="modal-title" id="deleteStudentModalLabel"><i class="fas fa-exclamation-triangle me-2"></i> ยืนยันการลบ</h5>
        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>
      <form method="POST" action="students.php">
          <input type="hidden" name="csrf_token" value="<?php echo e($csrf_token); ?>">
          <input type="hidden" name="action" value="delete">
          <input type="hidden" name="student_id" id="delete_student_id">
          
          <div class="modal-body text-center p-4">
            <i class="fas fa-trash-alt text-danger mb-3" style="font-size: 3rem;"></i>
            <p class="mb-1">คุณแน่ใจหรือไม่ว่าต้องการลบข้อมูลนักเรียนคนนี้?</p>
            <strong id="delete_student_name" class="text-dark d-block mb-3"></strong>
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

<!-- Custom Script for modals -->
<script>
document.addEventListener('DOMContentLoaded', function() {
    // Edit Modal Fill
    const editButtons = document.querySelectorAll('.btn-edit');
    editButtons.forEach(button => {
        button.addEventListener('click', function() {
            document.getElementById('edit_student_id').value = this.getAttribute('data-id');
            document.getElementById('edit_student_code').value = this.getAttribute('data-code');
            document.getElementById('edit_first_name').value = this.getAttribute('data-first');
            document.getElementById('edit_last_name').value = this.getAttribute('data-last');
            document.getElementById('edit_class_level').value = this.getAttribute('data-class');
            document.getElementById('edit_room_number').value = this.getAttribute('data-room');
            document.getElementById('edit_parent_phone').value = this.getAttribute('data-phone');
        });
    });
    
    // Delete Modal Fill
    const deleteButtons = document.querySelectorAll('.btn-delete');
    deleteButtons.forEach(button => {
        button.addEventListener('click', function() {
            document.getElementById('delete_student_id').value = this.getAttribute('data-id');
            document.getElementById('delete_student_name').textContent = this.getAttribute('data-name');
        });
    });
});
</script>
