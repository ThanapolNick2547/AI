<?php
require_once 'includes/db.php';
require_once 'includes/auth.php';

// Protect this route
requireLogin();

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

        // CREATE SUBJECT
        if ($action === 'create') {
            $code = trim($_POST['subject_code']);
            $name = trim($_POST['subject_name']);
            $department = trim($_POST['department']);
            $credits = floatval($_POST['credits']);
            $description = trim($_POST['description']);

            try {
                $stmt = $pdo->prepare("INSERT INTO subjects (subject_code, subject_name, department, credits, description) VALUES (?, ?, ?, ?, ?)");
                $stmt->execute([$code, $name, $department, $credits, $description]);
                $_SESSION['success_msg'] = "Subject added successfully!";
                header("Location: subjects.php");
                exit();
            } catch (Exception $e) {
                if ($e instanceof PDOException && $e->getCode() == 23000) {
                    $error = "Subject Code already exists.";
                } else {
                    $error = "Error adding subject: " . $e->getMessage();
                }
            }
        }

        // UPDATE SUBJECT
        else if ($action === 'update') {
            $id = $_POST['subject_id'];
            $code = trim($_POST['subject_code']);
            $name = trim($_POST['subject_name']);
            $department = trim($_POST['department']);
            $credits = floatval($_POST['credits']);
            $description = trim($_POST['description']);
            
            try {
                $stmt = $pdo->prepare("UPDATE subjects SET subject_code = ?, subject_name = ?, department = ?, credits = ?, description = ? WHERE id = ?");
                $stmt->execute([$code, $name, $department, $credits, $description, $id]);
                $_SESSION['success_msg'] = "Subject updated successfully!";
                header("Location: subjects.php");
                exit();
            } catch (Exception $e) {
                if ($e instanceof PDOException && $e->getCode() == 23000) {
                    $error = "Subject Code already exists.";
                } else {
                    $error = "Error updating subject: " . $e->getMessage();
                }
            }
        }

        // DELETE SUBJECT
        else if ($action === 'delete') {
            $id = $_POST['subject_id'];
            try {
                $stmt = $pdo->prepare("DELETE FROM subjects WHERE id = ?");
                $stmt->execute([$id]);
                $_SESSION['success_msg'] = "Subject deleted successfully!";
                header("Location: subjects.php");
                exit();
            } catch (Exception $e) {
                if ($e instanceof PDOException && $e->getCode() == 23000) {
                    $error = "ไม่สามารถลบข้อมูลนี้ได้ เนื่องจากมีการอ้างอิงหรือถูกใช้งานอยู่ในระบบอื่น";
                } else {
                    $error = "Error deleting subject: " . $e->getMessage();
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
$query = "SELECT * FROM subjects";
$params = [];

if ($search) {
    $query .= " WHERE subject_code LIKE ? OR subject_name LIKE ? OR department LIKE ?";
    $searchTerm = "%$search%";
    $params = [$searchTerm, $searchTerm, $searchTerm];
}

$query .= " ORDER BY id DESC";

try {
    $stmt = $pdo->prepare($query);
    $stmt->execute($params);
    $subjects = $stmt->fetchAll();
} catch (PDOException $e) {
    $error = "Error fetching data: " . $e->getMessage();
    $subjects = [];
}

// Include Layout Header
include 'includes/header.php';
?>

<!-- Breadcrumb -->
<nav aria-label="breadcrumb">
    <ol class="breadcrumb">
        <li class="breadcrumb-item"><a href="index.php">หน้าหลัก</a></li>
        <li class="breadcrumb-item"><a href="#">ข้อมูลหลัก</a></li>
        <li class="breadcrumb-item active" aria-current="page">รายวิชา</li>
    </ol>
</nav>

<div class="d-flex justify-content-between align-items-center mb-4">
    <h2 class="mb-0 fw-bold text-dark">จัดการข้อมูลรายวิชา</h2>
    <button type="button" class="btn btn-primary shadow-sm" data-bs-toggle="modal" data-bs-target="#addSubjectModal">
        <i class="fas fa-plus-circle me-1"></i> เพิ่มรายวิชาใหม่
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
        <h5 class="mb-0 fw-bold">รายวิชาทั้งหมด</h5>
        <form method="GET" class="d-flex" style="max-width: 300px;">
            <div class="input-group input-group-sm">
                <input type="text" name="search" class="form-control" placeholder="ค้นหารหัสวิชา หรือ ชื่อวิชา..." value="<?php echo e($search); ?>">
                <button class="btn btn-outline-secondary" type="submit"><i class="fas fa-search"></i></button>
                <?php if ($search): ?>
                    <a href="subjects.php" class="btn btn-outline-danger"><i class="fas fa-times"></i></a>
                <?php endif; ?>
            </div>
        </form>
    </div>
    
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0 datatable">
                <thead class="bg-light">
                    <tr>
                        <th class="ps-4">รหัสวิชา</th>
                        <th>ชื่อวิชา</th>
                        <th>หมวดหมู่</th>
                        <th>หน่วยกิต</th>
                        <th class="pe-4 text-end">จัดการ</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (count($subjects) > 0): ?>
                        <?php foreach($subjects as $s): ?>
                            <tr>
                                <td class="ps-4 fw-semibold text-primary">
                                    <i class="fas fa-book-open text-muted me-1"></i> 
                                    <?php echo e($s['subject_code']); ?>
                                </td>
                                <td>
                                    <h6 class="mb-0"><?php echo e($s['subject_name']); ?></h6>
                                    <?php if($s['description']): ?>
                                        <small class="text-muted text-truncate d-inline-block" style="max-width: 250px;"><?php echo e($s['description']); ?></small>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <span class="badge bg-light text-dark border"><i class="fas fa-tags me-1 text-muted"></i> <?php echo e($s['department']); ?></span>
                                </td>
                                <td>
                                    <span class="badge bg-success bg-opacity-10 text-success px-2 py-1"><i class="fas fa-star fa-sm me-1"></i> <?php echo e(number_format($s['credits'], 1)); ?> </span>
                                </td>
                                <td class="pe-4 text-end">
                                    <button class="btn btn-sm btn-outline-primary btn-edit me-1" 
                                            data-id="<?php echo $s['id']; ?>"
                                            data-code="<?php echo e($s['subject_code']); ?>"
                                            data-name="<?php echo e($s['subject_name']); ?>"
                                            data-department="<?php echo e($s['department']); ?>"
                                            data-credits="<?php echo e($s['credits']); ?>"
                                            data-desc="<?php echo e($s['description']); ?>"
                                            data-bs-toggle="modal" data-bs-target="#editSubjectModal">
                                        <i class="fas fa-edit"></i>
                                    </button>
                                    
                                    <button type="button" class="btn btn-sm btn-outline-danger btn-delete" 
                                            data-id="<?php echo $s['id']; ?>"
                                            data-name="<?php echo e($s['subject_code'] . ' - ' . $s['subject_name']); ?>"
                                            data-bs-toggle="modal" data-bs-target="#deleteSubjectModal">
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
        <small class="text-muted">รวมทั้งหมด: <?php echo count($subjects); ?> วิชา</small>
    </div>
</div>

<!-- ==========================================
     MODALS 
     ========================================== -->

<!-- ADD MODAL -->
<div class="modal fade" id="addSubjectModal" tabindex="-1" aria-labelledby="addSubjectModalLabel" aria-hidden="true">
  <div class="modal-dialog">
    <div class="modal-content border-0 shadow">
      <div class="modal-header bg-primary text-white">
        <h5 class="modal-title" id="addSubjectModalLabel"><i class="fas fa-book-medical me-2"></i> เพิ่มรายวิชาใหม่</h5>
        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>
      <form method="POST" action="subjects.php">
          <input type="hidden" name="csrf_token" value="<?php echo e($csrf_token); ?>">
          <input type="hidden" name="action" value="create">
          
          <div class="modal-body">

            <div class="row">
                <div class="col-md-5 mb-3">
                    <label for="subject_code" class="form-label text-muted fw-semibold small">รหัสวิชา <span class="text-danger">*</span></label>
                    <input type="text" class="form-control" id="subject_code" name="subject_code" placeholder="เช่น MATH101" required>
                </div>
                <div class="col-md-7 mb-3">
                    <label for="subject_name" class="form-label text-muted fw-semibold small">ชื่อวิชา <span class="text-danger">*</span></label>
                    <input type="text" class="form-control" id="subject_name" name="subject_name" placeholder="เช่น คณิตศาสตร์พื้นฐาน" required>
                </div>
            </div>
            
            <div class="row">
                <div class="col-md-8 mb-3">
                    <label for="department" class="form-label text-muted fw-semibold small">กลุ่มสาระการเรียนรู้ <span class="text-danger">*</span></label>
                    <select class="form-select" id="department" name="department" required>
                        <option value="">เลือกกลุ่มสาระ...</option>
                        <option value="Mathematics">คณิตศาสตร์</option>
                        <option value="Science">วิทยาศาสตร์</option>
                        <option value="Thai Language">ภาษาไทย</option>
                        <option value="Foreign Languages">ภาษาต่างประเทศ</option>
                        <option value="Social Studies">สังคมศึกษา</option>
                        <option value="Physical Education">สุขศึกษาและพลศึกษา</option>
                        <option value="Arts">ศิลปะ</option>
                        <option value="Technology">การงานอาชีพและเทคโนโลยี</option>
                        <option value="Other">อื่นๆ</option>
                    </select>
                </div>
                <div class="col-md-4 mb-3">
                    <label for="credits" class="form-label text-muted fw-semibold small">หน่วยกิต <span class="text-danger">*</span></label>
                    <input type="number" class="form-control" id="credits" name="credits" step="0.5" min="0" placeholder="เช่น 1.5" required>
                </div>
            </div>
            
            <div class="mb-3">
                <label for="description" class="form-label text-muted fw-semibold small">คำอธิบายรายวิชา (ถ้ามี)</label>
                <textarea class="form-control" id="description" name="description" rows="3" placeholder="คำอธิบายรายวิชาโดยย่อ..."></textarea>
            </div>

          </div>
          
          <div class="modal-footer bg-light">
            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">ยกเลิก</button>
            <button type="submit" class="btn btn-primary">บันทึกข้อมูล</button>
          </div>
      </form>
    </div>
  </div>
</div>

<!-- EDIT MODAL -->
<div class="modal fade" id="editSubjectModal" tabindex="-1" aria-labelledby="editSubjectModalLabel" aria-hidden="true">
  <div class="modal-dialog">
    <div class="modal-content border-0 shadow">
      <div class="modal-header bg-light">
        <h5 class="modal-title text-dark" id="editSubjectModalLabel"><i class="fas fa-edit me-2 text-primary"></i> แก้ไขข้อมูลรายวิชา</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>
      <form method="POST" action="subjects.php">
          <input type="hidden" name="csrf_token" value="<?php echo e($csrf_token); ?>">
          <input type="hidden" name="action" value="update">
          <input type="hidden" name="subject_id" id="edit_subject_id">
          
          <div class="modal-body">

            <div class="row">
                <div class="col-md-5 mb-3">
                    <label for="edit_subject_code" class="form-label text-muted fw-semibold small">รหัสวิชา <span class="text-danger">*</span></label>
                    <input type="text" class="form-control" id="edit_subject_code" name="subject_code" required>
                </div>
                <div class="col-md-7 mb-3">
                    <label for="edit_subject_name" class="form-label text-muted fw-semibold small">ชื่อวิชา <span class="text-danger">*</span></label>
                    <input type="text" class="form-control" id="edit_subject_name" name="subject_name" required>
                </div>
            </div>
            
            <div class="row">
                <div class="col-md-8 mb-3">
                    <label for="edit_department" class="form-label text-muted fw-semibold small">กลุ่มสาระการเรียนรู้ <span class="text-danger">*</span></label>
                    <select class="form-select" id="edit_department" name="department" required>
                        <option value="">เลือกกลุ่มสาระ...</option>
                        <option value="Mathematics">คณิตศาสตร์</option>
                        <option value="Science">วิทยาศาสตร์</option>
                        <option value="Thai Language">ภาษาไทย</option>
                        <option value="Foreign Languages">ภาษาต่างประเทศ</option>
                        <option value="Social Studies">สังคมศึกษา</option>
                        <option value="Physical Education">สุขศึกษาและพลศึกษา</option>
                        <option value="Arts">ศิลปะ</option>
                        <option value="Technology">การงานอาชีพและเทคโนโลยี</option>
                        <option value="Other">อื่นๆ</option>
                    </select>
                </div>
                <div class="col-md-4 mb-3">
                    <label for="edit_credits" class="form-label text-muted fw-semibold small">หน่วยกิต <span class="text-danger">*</span></label>
                    <input type="number" class="form-control" id="edit_credits" name="credits" step="0.5" min="0" required>
                </div>
            </div>
            
            <div class="mb-3">
                <label for="edit_description" class="form-label text-muted fw-semibold small">คำอธิบายรายวิชา (ถ้ามี)</label>
                <textarea class="form-control" id="edit_description" name="description" rows="3"></textarea>
            </div>

          </div>
          
          <div class="modal-footer bg-light">
            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">ยกเลิก</button>
            <button type="submit" class="btn btn-primary">บันทึกการแก้ไข</button>
          </div>
      </form>
    </div>
  </div>
</div>

<!-- DELETE MODAL -->
<div class="modal fade" id="deleteSubjectModal" tabindex="-1" aria-labelledby="deleteSubjectModalLabel" aria-hidden="true">
  <div class="modal-dialog modal-sm modal-dialog-centered">
    <div class="modal-content border-0 shadow">
      <div class="modal-header bg-danger text-white">
        <h5 class="modal-title" id="deleteSubjectModalLabel"><i class="fas fa-exclamation-triangle me-2"></i> ยืนยันการลบ</h5>
        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>
      <form method="POST" action="subjects.php">
          <input type="hidden" name="csrf_token" value="<?php echo e($csrf_token); ?>">
          <input type="hidden" name="action" value="delete">
          <input type="hidden" name="subject_id" id="delete_subject_id">
          
          <div class="modal-body text-center p-4">
            <i class="fas fa-trash-alt text-danger mb-3" style="font-size: 3rem;"></i>
            <p class="mb-1">คุณแน่ใจหรือไม่ว่าต้องการลบข้อมูลรายวิชานี้?</p>
            <strong id="delete_subject_name" class="text-dark d-block mb-3"></strong>
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
            document.getElementById('edit_subject_id').value = this.getAttribute('data-id');
            document.getElementById('edit_subject_code').value = this.getAttribute('data-code');
            document.getElementById('edit_subject_name').value = this.getAttribute('data-name');
            document.getElementById('edit_department').value = this.getAttribute('data-department');
            document.getElementById('edit_credits').value = this.getAttribute('data-credits');
            document.getElementById('edit_description').value = this.getAttribute('data-desc');
        });
    });
    
    // Delete Modal Fill
    const deleteButtons = document.querySelectorAll('.btn-delete');
    deleteButtons.forEach(button => {
        button.addEventListener('click', function() {
            document.getElementById('delete_subject_id').value = this.getAttribute('data-id');
            document.getElementById('delete_subject_name').textContent = this.getAttribute('data-name');
        });
    });
});
</script>
