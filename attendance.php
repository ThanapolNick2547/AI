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

        if ($action === 'create') {
            $student_id = intval($_POST['student_id']);
            $subject_id = intval($_POST['subject_id']);
            $attendance_date = trim($_POST['attendance_date']);
            $status = trim($_POST['status']);
            $remarks = trim($_POST['remarks']);

            try {
                $stmt = $pdo->prepare("INSERT INTO attendance (student_id, subject_id, attendance_date, status, remarks) VALUES (?, ?, ?, ?, ?)");
                $stmt->execute([$student_id, $subject_id, $attendance_date, $status, $remarks]);
                $_SESSION['success_msg'] = "บันทึกเวลาเรียนสำเร็จ!";
                header("Location: attendance.php");
                exit();
            } catch (Exception $e) {
                $error = "Error adding attendance: " . $e->getMessage();
            }
        }
        else if ($action === 'update') {
            $id = intval($_POST['attendance_id']);
            $student_id = intval($_POST['student_id']);
            $subject_id = intval($_POST['subject_id']);
            $attendance_date = trim($_POST['attendance_date']);
            $status = trim($_POST['status']);
            $remarks = trim($_POST['remarks']);
            
            try {
                $stmt = $pdo->prepare("UPDATE attendance SET student_id = ?, subject_id = ?, attendance_date = ?, status = ?, remarks = ? WHERE id = ?");
                $stmt->execute([$student_id, $subject_id, $attendance_date, $status, $remarks, $id]);
                $_SESSION['success_msg'] = "อัปเดตเวลาเรียนสำเร็จ!";
                header("Location: attendance.php");
                exit();
            } catch (Exception $e) {
                $error = "Error updating attendance: " . $e->getMessage();
            }
        }
        else if ($action === 'delete') {
            $id = intval($_POST['attendance_id']);
            try {
                $stmt = $pdo->prepare("DELETE FROM attendance WHERE id = ?");
                $stmt->execute([$id]);
                $_SESSION['success_msg'] = "ลบข้อมูลเวลาเรียนสำเร็จ!";
                header("Location: attendance.php");
                exit();
            } catch (Exception $e) {
                if ($e instanceof PDOException && $e->getCode() == 23000) {
                    $error = "ไม่สามารถลบข้อมูลนี้ได้ เนื่องจากมีการอ้างอิงหรือถูกใช้งานอยู่ในระบบอื่น";
                } else {
                    $error = "Error deleting attendance record: " . $e->getMessage();
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
 *  Fetch Relational Data for Dropdowns
 *  ====================================== */
try {
    $students = $pdo->query("SELECT id, student_code, first_name, last_name, class_level FROM students ORDER BY first_name ASC")->fetchAll();
    $subjects = $pdo->query("SELECT id, subject_code, subject_name FROM subjects ORDER BY subject_name ASC")->fetchAll();
} catch (PDOException $e) {
    $error = "Missing required database tables: " . $e->getMessage();
    $students = $subjects = [];
}

/** ======================================
 *  Fetch Attendance Data
 *  ====================================== */
$search = $_GET['search'] ?? '';
$query = "
    SELECT 
        a.id, a.attendance_date, a.status, a.remarks,
        a.student_id, a.subject_id,
        s.first_name, s.last_name, s.student_code, s.class_level,
        sub.subject_name, sub.subject_code
    FROM attendance a
    LEFT JOIN students s ON a.student_id = s.id
    LEFT JOIN subjects sub ON a.subject_id = sub.id
";
$params = [];

if ($search) {
    $query .= " WHERE s.first_name LIKE ? OR s.last_name LIKE ? OR s.student_code LIKE ? OR sub.subject_name LIKE ? OR a.attendance_date LIKE ?";
    $searchTerm = "%$search%";
    $params = [$searchTerm, $searchTerm, $searchTerm, $searchTerm, $searchTerm];
}

$query .= " ORDER BY a.attendance_date DESC, s.first_name ASC";

try {
    $stmt = $pdo->prepare($query);
    $stmt->execute($params);
    $attendances = $stmt->fetchAll();
} catch (PDOException $e) {
    if ($e->getCode() == '42S02') {
        $error = "Database table 'attendance' does not exist. Please run setup_attendance_db.php";
    } else {
        $error = "Error fetching data: " . $e->getMessage();
    }
    $attendances = [];
}

// Helper to color-code attendance status
function getStatusBadge($status) {
    switch (strtolower($status)) {
        case 'present': return ['bg' => 'success', 'icon' => 'fa-check-circle', 'label' => 'มาเรียน'];
        case 'absent': return ['bg' => 'danger', 'icon' => 'fa-times-circle', 'label' => 'ขาดเรียน'];
        case 'late': return ['bg' => 'warning', 'icon' => 'fa-clock', 'label' => 'มาสาย'];
        case 'excused': return ['bg' => 'info', 'icon' => 'fa-envelope-open-text', 'label' => 'ลา'];
        default: return ['bg' => 'secondary', 'icon' => 'fa-question-circle', 'label' => 'ไม่ทราบ'];
    }
}

// Include Layout Header
include 'includes/header.php';
?>

<!-- Breadcrumb -->
<nav aria-label="breadcrumb">
    <ol class="breadcrumb">
        <li class="breadcrumb-item"><a href="index.php">หน้าหลัก</a></li>
        <li class="breadcrumb-item active" aria-current="page">เวลาเรียน</li>
    </ol>
</nav>

<div class="d-flex justify-content-between align-items-center mb-4">
    <h2 class="mb-0 fw-bold text-dark">ติดตามเวลาเรียน</h2>
    <button type="button" class="btn btn-primary shadow-sm" data-bs-toggle="modal" data-bs-target="#addAttendanceModal">
        <i class="fas fa-plus-circle me-1"></i> บันทึกเวลาเรียน
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
        <h5 class="mb-0 fw-bold">ข้อมูลเวลาเรียน</h5>
        <form method="GET" class="d-flex" style="max-width: 350px;">
            <div class="input-group input-group-sm">
                <input type="text" name="search" class="form-control" placeholder="ค้นหานักเรียน, วิชา หรือ วันที่..." value="<?php echo e($search); ?>">
                <button class="btn btn-outline-secondary" type="submit"><i class="fas fa-search"></i></button>
                <?php if ($search): ?>
                    <a href="attendance.php" class="btn btn-outline-danger"><i class="fas fa-times"></i></a>
                <?php endif; ?>
            </div>
        </form>
    </div>
    
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0 datatable">
                <thead class="bg-light">
                    <tr>
                        <th class="ps-4">วันที่</th>
                        <th>นักเรียน</th>
                        <th>วิชา</th>
                        <th>สถานะ</th>
                        <th>หมายเหตุ</th>
                        <th class="pe-4 text-end" width="12%">จัดการ</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (count($attendances) > 0): ?>
                        <?php foreach($attendances as $a): ?>
                            <tr>
                                <td class="ps-4">
                                    <h6 class="mb-0 fw-semibold text-dark"><i class="far fa-calendar-alt text-primary me-1"></i> <?php echo date('d M Y', strtotime($a['attendance_date'])); ?></h6>
                                </td>
                                <td>
                                    <span class="fw-semibold text-dark"><?php echo e($a['first_name'] . ' ' . $a['last_name']); ?></span>
                                    <br><small class="text-muted"><i class="fas fa-id-badge"></i> <?php echo e($a['student_code']); ?> | <i class="fas fa-layer-group"></i> <?php echo e($a['class_level']); ?></small>
                                </td>
                                <td>
                                    <span class="text-primary"><?php echo e($a['subject_name']); ?></span>
                                    <br><small class="text-muted"><?php echo e($a['subject_code']); ?></small>
                                </td>
                                <td>
                                    <?php $badge = getStatusBadge($a['status']); ?>
                                    <span class="badge bg-<?php echo $badge['bg']; ?> bg-opacity-10 text-<?php echo $badge['bg']; ?> border border-<?php echo $badge['bg']; ?> px-3 py-2 fw-semibold" style="font-size: 0.85rem;">
                                        <i class="fas <?php echo $badge['icon']; ?> me-1"></i> <?php echo $badge['label']; ?>
                                    </span>
                                </td>
                                <td>
                                    <?php if($a['remarks']): ?>
                                        <span class="text-muted d-inline-block text-truncate" style="max-width: 150px;" title="<?php echo e($a['remarks']); ?>">
                                            <i class="far fa-comment-dots me-1"></i> <?php echo e($a['remarks']); ?>
                                        </span>
                                    <?php else: ?>
                                        <span class="text-muted fst-italic">-</span>
                                    <?php endif; ?>
                                </td>
                                <td class="pe-4 text-end">
                                    <button class="btn btn-sm btn-outline-primary btn-edit me-1" 
                                            data-id="<?php echo $a['id']; ?>"
                                            data-student="<?php echo $a['student_id']; ?>"
                                            data-subject="<?php echo $a['subject_id']; ?>"
                                            data-date="<?php echo e($a['attendance_date']); ?>"
                                            data-status="<?php echo e($a['status']); ?>"
                                            data-remarks="<?php echo e($a['remarks']); ?>"
                                            data-bs-toggle="modal" data-bs-target="#editAttendanceModal">
                                        <i class="fas fa-edit"></i>
                                    </button>
                                    
                                    <button type="button" class="btn btn-sm btn-outline-danger btn-delete" 
                                            data-id="<?php echo $a['id']; ?>"
                                            data-name="<?php echo e($a['first_name'] . ' - ' . date('d M Y', strtotime($a['attendance_date']))); ?>"
                                            data-bs-toggle="modal" data-bs-target="#deleteAttendanceModal">
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
        <small class="text-muted">รวมทั้งหมด: <?php echo count($attendances); ?> รายการ</small>
    </div>
</div>

<!-- ==========================================
     MODALS 
     ========================================== -->

<!-- ADD MODAL -->
<div class="modal fade" id="addAttendanceModal" tabindex="-1" aria-labelledby="addAttendanceModalLabel" aria-hidden="true">
  <div class="modal-dialog modal-lg">
    <div class="modal-content border-0 shadow">
      <div class="modal-header bg-primary text-white">
        <h5 class="modal-title" id="addAttendanceModalLabel"><i class="fas fa-clipboard-user me-2"></i> บันทึกเวลาเรียน</h5>
        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>
      <form method="POST" action="attendance.php">
          <input type="hidden" name="csrf_token" value="<?php echo e($csrf_token); ?>">
          <input type="hidden" name="action" value="create">
          
          <div class="modal-body">
            <div class="row">
                <div class="col-md-6 mb-3">
                    <label class="form-label text-muted fw-semibold small">นักเรียน <span class="text-danger">*</span></label>
                    <select class="form-select" name="student_id" required>
                        <option value="" disabled selected>เลือกนักเรียน...</option>
                        <?php foreach($students as $st): ?>
                            <option value="<?php echo $st['id']; ?>"><?php echo e($st['student_code'] . ' - ' . $st['first_name'] . ' ' . $st['last_name']); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-6 mb-3">
                    <label class="form-label text-muted fw-semibold small">วิชา <span class="text-danger">*</span></label>
                    <select class="form-select" name="subject_id" required>
                        <option value="" disabled selected>เลือกวิชา...</option>
                        <?php foreach($subjects as $sub): ?>
                            <option value="<?php echo $sub['id']; ?>"><?php echo e($sub['subject_code'] . ' - ' . $sub['subject_name']); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>
            
            <div class="row">
                <div class="col-md-6 mb-3">
                    <label class="form-label text-muted fw-semibold small">วันที่ <span class="text-danger">*</span></label>
                    <input type="date" class="form-control" name="attendance_date" value="<?php echo date('Y-m-d'); ?>" required>
                </div>
                <div class="col-md-6 mb-3">
                    <label class="form-label text-muted fw-semibold small">สถานะ <span class="text-danger">*</span></label>
                    <select class="form-select" name="status" required>
                        <option value="present" selected>มาเรียน</option>
                        <option value="absent">ขาดเรียน</option>
                        <option value="late">มาสาย</option>
                        <option value="excused">ลา</option>
                    </select>
                </div>
            </div>

            <div class="mb-3">
                <label class="form-label text-muted fw-semibold small">หมายเหตุ / บันทึกเพิ่มเติม</label>
                <textarea class="form-control" name="remarks" rows="2" placeholder="เหตุผลที่ขาดเรียน, มาสาย ฯลฯ..."></textarea>
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
<div class="modal fade" id="editAttendanceModal" tabindex="-1" aria-labelledby="editAttendanceModalLabel" aria-hidden="true">
  <div class="modal-dialog modal-lg">
    <div class="modal-content border-0 shadow">
      <div class="modal-header bg-light">
        <h5 class="modal-title text-dark" id="editAttendanceModalLabel"><i class="fas fa-edit me-2 text-primary"></i> แก้ไขเวลาเรียน</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>
      <form method="POST" action="attendance.php">
          <input type="hidden" name="csrf_token" value="<?php echo e($csrf_token); ?>">
          <input type="hidden" name="action" value="update">
          <input type="hidden" name="attendance_id" id="edit_attendance_id">
          
          <div class="modal-body">
            <div class="row">
                <div class="col-md-6 mb-3">
                    <label class="form-label text-muted fw-semibold small">นักเรียน <span class="text-danger">*</span></label>
                    <select class="form-select" id="edit_student_id" name="student_id" required>
                        <?php foreach($students as $st): ?>
                            <option value="<?php echo $st['id']; ?>"><?php echo e($st['student_code'] . ' - ' . $st['first_name'] . ' ' . $st['last_name']); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-6 mb-3">
                    <label class="form-label text-muted fw-semibold small">วิชา <span class="text-danger">*</span></label>
                    <select class="form-select" id="edit_subject_id" name="subject_id" required>
                        <?php foreach($subjects as $sub): ?>
                            <option value="<?php echo $sub['id']; ?>"><?php echo e($sub['subject_code'] . ' - ' . $sub['subject_name']); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>
            
            <div class="row">
                <div class="col-md-6 mb-3">
                    <label class="form-label text-muted fw-semibold small">วันที่ <span class="text-danger">*</span></label>
                    <input type="date" class="form-control" id="edit_attendance_date" name="attendance_date" required>
                </div>
                <div class="col-md-6 mb-3">
                    <label class="form-label text-muted fw-semibold small">สถานะ <span class="text-danger">*</span></label>
                    <select class="form-select" id="edit_status" name="status" required>
                        <option value="present">มาเรียน</option>
                        <option value="absent">ขาดเรียน</option>
                        <option value="late">มาสาย</option>
                        <option value="excused">ลา</option>
                    </select>
                </div>
            </div>

            <div class="mb-3">
                <label class="form-label text-muted fw-semibold small">หมายเหตุ / บันทึกเพิ่มเติม</label>
                <textarea class="form-control" id="edit_remarks" name="remarks" rows="2"></textarea>
            </div>
          </div>
          
          <div class="modal-footer bg-light">
            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">ยกเลิก</button>
            <button type="submit" class="btn btn-primary">อัปเดตข้อมูล</button>
          </div>
      </form>
    </div>
  </div>
</div>

<!-- DELETE MODAL -->
<div class="modal fade" id="deleteAttendanceModal" tabindex="-1" aria-labelledby="deleteAttendanceModalLabel" aria-hidden="true">
  <div class="modal-dialog modal-sm modal-dialog-centered">
    <div class="modal-content border-0 shadow">
      <div class="modal-header bg-danger text-white">
        <h5 class="modal-title" id="deleteAttendanceModalLabel"><i class="fas fa-exclamation-triangle me-2"></i> ยืนยันการลบ</h5>
        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>
      <form method="POST" action="attendance.php">
          <input type="hidden" name="csrf_token" value="<?php echo e($csrf_token); ?>">
          <input type="hidden" name="action" value="delete">
          <input type="hidden" name="attendance_id" id="delete_attendance_id">
          
          <div class="modal-body text-center p-4">
            <i class="fas fa-trash-alt text-danger mb-3" style="font-size: 3rem;"></i>
            <p class="mb-1">คุณแน่ใจหรือไม่ว่าต้องการลบข้อมูลนี้?</p>
            <strong id="delete_attendance_name" class="text-dark d-block mb-3"></strong>
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
            document.getElementById('edit_attendance_id').value = this.getAttribute('data-id');
            document.getElementById('edit_student_id').value = this.getAttribute('data-student');
            document.getElementById('edit_subject_id').value = this.getAttribute('data-subject');
            document.getElementById('edit_attendance_date').value = this.getAttribute('data-date');
            document.getElementById('edit_status').value = this.getAttribute('data-status');
            document.getElementById('edit_remarks').value = this.getAttribute('data-remarks');
        });
    });
    
    // Delete Modal Fill
    const deleteButtons = document.querySelectorAll('.btn-delete');
    deleteButtons.forEach(button => {
        button.addEventListener('click', function() {
            document.getElementById('delete_attendance_id').value = this.getAttribute('data-id');
            document.getElementById('delete_attendance_name').textContent = this.getAttribute('data-name');
        });
    });
});
</script>
