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
            $academic_year = trim($_POST['academic_year']);
            $semester = intval($_POST['semester']);
            $score = trim($_POST['score']) === '' ? null : floatval($_POST['score']);
            $grade_letter = trim($_POST['grade_letter']);
            $remarks = trim($_POST['remarks']);

            try {
                $stmt = $pdo->prepare("INSERT INTO grades (student_id, subject_id, academic_year, semester, score, grade_letter, remarks) VALUES (?, ?, ?, ?, ?, ?, ?)");
                $stmt->execute([$student_id, $subject_id, $academic_year, $semester, $score, $grade_letter, $remarks]);
                $_SESSION['success_msg'] = "บันทึกผลการเรียนสำเร็จ!";
                header("Location: grading.php");
                exit();
            } catch (Exception $e) {
                $error = "Error adding grade: " . $e->getMessage();
            }
        }
        else if ($action === 'update') {
            $id = intval($_POST['grade_id']);
            $student_id = intval($_POST['student_id']);
            $subject_id = intval($_POST['subject_id']);
            $academic_year = trim($_POST['academic_year']);
            $semester = intval($_POST['semester']);
            $score = trim($_POST['score']) === '' ? null : floatval($_POST['score']);
            $grade_letter = trim($_POST['grade_letter']);
            $remarks = trim($_POST['remarks']);
            
            try {
                $stmt = $pdo->prepare("UPDATE grades SET student_id = ?, subject_id = ?, academic_year = ?, semester = ?, score = ?, grade_letter = ?, remarks = ? WHERE id = ?");
                $stmt->execute([$student_id, $subject_id, $academic_year, $semester, $score, $grade_letter, $remarks, $id]);
                $_SESSION['success_msg'] = "อัปเดตผลการเรียนสำเร็จ!";
                header("Location: grading.php");
                exit();
            } catch (Exception $e) {
                $error = "Error updating grade: " . $e->getMessage();
            }
        }
        else if ($action === 'delete') {
            $id = intval($_POST['grade_id']);
            try {
                $stmt = $pdo->prepare("DELETE FROM grades WHERE id = ?");
                $stmt->execute([$id]);
                $_SESSION['success_msg'] = "ลบข้อมูลผลการเรียนสำเร็จ!";
                header("Location: grading.php");
                exit();
            } catch (Exception $e) {
                if ($e instanceof PDOException && $e->getCode() == 23000) {
                    $error = "ไม่สามารถลบข้อมูลนี้ได้ เนื่องจากมีการอ้างอิงหรือถูกใช้งานอยู่ในระบบอื่น";
                } else {
                    $error = "Error deleting grade record: " . $e->getMessage();
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
 *  Fetch Grades Data
 *  ====================================== */
$search = $_GET['search'] ?? '';
$query = "
    SELECT 
        g.id, g.academic_year, g.semester, g.score, g.grade_letter, g.remarks,
        g.student_id, g.subject_id,
        s.first_name, s.last_name, s.student_code, s.class_level,
        sub.subject_name, sub.subject_code
    FROM grades g
    LEFT JOIN students s ON g.student_id = s.id
    LEFT JOIN subjects sub ON g.subject_id = sub.id
";
$params = [];

if ($search) {
    $query .= " WHERE s.first_name LIKE ? OR s.last_name LIKE ? OR s.student_code LIKE ? OR sub.subject_name LIKE ? OR sub.subject_code LIKE ?";
    $searchTerm = "%$search%";
    $params = [$searchTerm, $searchTerm, $searchTerm, $searchTerm, $searchTerm];
}

$query .= " ORDER BY g.academic_year DESC, g.semester DESC, s.first_name ASC";

try {
    $stmt = $pdo->prepare($query);
    $stmt->execute($params);
    $grades = $stmt->fetchAll();
} catch (PDOException $e) {
    if ($e->getCode() == '42S02') {
        $error = "Database table 'grades' does not exist. Please run setup_grades_db.php";
    } else {
        $error = "Error fetching data: " . $e->getMessage();
    }
    $grades = [];
}

// Helper to color-code grades
function getGradeColor($grade) {
    $g = trim(strtoupper($grade));
    if (in_array($g, ['A', 'A+', 'A-'])) return 'success';
    if (in_array($g, ['B', 'B+', 'B-'])) return 'primary';
    if (in_array($g, ['C', 'C+', 'C-'])) return 'info';
    if (in_array($g, ['D', 'D+', 'D-'])) return 'warning';
    if (in_array($g, ['F', '0'])) return 'danger';
    return 'secondary';
}

// Include Layout Header
include 'includes/header.php';
?>

<!-- Breadcrumb -->
<nav aria-label="breadcrumb">
    <ol class="breadcrumb">
        <li class="breadcrumb-item"><a href="index.php">หน้าหลัก</a></li>
        <li class="breadcrumb-item active" aria-current="page">ผลการเรียน</li>
    </ol>
</nav>

<div class="d-flex justify-content-between align-items-center mb-4">
    <h2 class="mb-0 fw-bold text-dark">จัดการผลการเรียน</h2>
    <button type="button" class="btn btn-primary shadow-sm" data-bs-toggle="modal" data-bs-target="#addGradeModal">
        <i class="fas fa-plus-circle me-1"></i> บันทึกผลการเรียนใหม่
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
        <h5 class="mb-0 fw-bold">ผลการเรียน</h5>
        <form method="GET" class="d-flex" style="max-width: 350px;">
            <div class="input-group input-group-sm">
                <input type="text" name="search" class="form-control" placeholder="ค้นหานักเรียน หรือ วิชา..." value="<?php echo e($search); ?>">
                <button class="btn btn-outline-secondary" type="submit"><i class="fas fa-search"></i></button>
                <?php if ($search): ?>
                    <a href="grading.php" class="btn btn-outline-danger"><i class="fas fa-times"></i></a>
                <?php endif; ?>
            </div>
        </form>
    </div>
    
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0 datatable">
                <thead class="bg-light">
                    <tr>
                        <th class="ps-4">นักเรียน</th>
                        <th>วิชา</th>
                        <th>ภาคเรียน</th>
                        <th>คะแนน</th>
                        <th>เกรด</th>
                        <th>หมายเหตุ</th>
                        <th class="pe-4 text-end" width="12%">จัดการ</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (count($grades) > 0): ?>
                        <?php foreach($grades as $g): ?>
                            <tr>
                                <td class="ps-4">
                                    <h6 class="mb-0 fw-semibold text-dark"><?php echo e($g['first_name'] . ' ' . $g['last_name']); ?></h6>
                                    <small class="text-muted"><i class="fas fa-id-badge"></i> <?php echo e($g['student_code']); ?> | <i class="fas fa-layer-group"></i> <?php echo e($g['class_level']); ?></small>
                                </td>
                                <td>
                                    <span class="fw-semibold text-primary"><?php echo e($g['subject_name']); ?></span>
                                    <br><small class="text-muted"><?php echo e($g['subject_code']); ?></small>
                                </td>
                                <td>
                                    <span class="text-muted"><?php echo e($g['semester']); ?>/<?php echo e($g['academic_year']); ?></span>
                                </td>
                                <td>
                                    <?php if($g['score'] !== null): ?>
                                        <span class="fw-bold text-dark"><?php echo e($g['score']); ?></span>
                                    <?php else: ?>
                                        <span class="text-muted fst-italic">-</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?php if($g['grade_letter']): ?>
                                        <?php $gradeColor = getGradeColor($g['grade_letter']); ?>
                                        <span class="badge bg-<?php echo $gradeColor; ?> bg-opacity-10 text-<?php echo $gradeColor; ?> border border-<?php echo $gradeColor; ?> px-3 py-2 fw-bold" style="font-size: 0.9rem;">
                                            <?php echo e($g['grade_letter']); ?>
                                        </span>
                                    <?php else: ?>
                                        <span class="text-muted fst-italic">N/A</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?php if($g['remarks']): ?>
                                        <span class="text-muted d-inline-block text-truncate" style="max-width: 150px;" title="<?php echo e($g['remarks']); ?>">
                                            <?php echo e($g['remarks']); ?>
                                        </span>
                                    <?php else: ?>
                                        <span class="text-muted fst-italic">-</span>
                                    <?php endif; ?>
                                </td>
                                <td class="pe-4 text-end">
                                    <button class="btn btn-sm btn-outline-primary btn-edit me-1" 
                                            data-id="<?php echo $g['id']; ?>"
                                            data-student="<?php echo $g['student_id']; ?>"
                                            data-subject="<?php echo $g['subject_id']; ?>"
                                            data-year="<?php echo e($g['academic_year']); ?>"
                                            data-semester="<?php echo e($g['semester']); ?>"
                                            data-score="<?php echo e($g['score']); ?>"
                                            data-grade="<?php echo e($g['grade_letter']); ?>"
                                            data-remarks="<?php echo e($g['remarks']); ?>"
                                            data-bs-toggle="modal" data-bs-target="#editGradeModal">
                                        <i class="fas fa-edit"></i>
                                    </button>
                                    
                                    <button type="button" class="btn btn-sm btn-outline-danger btn-delete" 
                                            data-id="<?php echo $g['id']; ?>"
                                            data-name="<?php echo e($g['first_name'] . ' - ' . $g['subject_name']); ?>"
                                            data-bs-toggle="modal" data-bs-target="#deleteGradeModal">
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
        <small class="text-muted">รวมทั้งหมด: <?php echo count($grades); ?> รายการ</small>
    </div>
</div>

<!-- ==========================================
     MODALS 
     ========================================== -->

<!-- ADD MODAL -->
<div class="modal fade" id="addGradeModal" tabindex="-1" aria-labelledby="addGradeModalLabel" aria-hidden="true">
  <div class="modal-dialog modal-lg">
    <div class="modal-content border-0 shadow">
      <div class="modal-header bg-primary text-white">
        <h5 class="modal-title" id="addGradeModalLabel"><i class="fas fa-award me-2"></i> บันทึกผลการเรียนใหม่</h5>
        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>
      <form method="POST" action="grading.php">
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
                    <label class="form-label text-muted fw-semibold small">ปีการศึกษา <span class="text-danger">*</span></label>
                    <input type="text" class="form-control" name="academic_year" placeholder="e.g. 2024" value="<?php echo date('Y'); ?>" required>
                </div>
                <div class="col-md-6 mb-3">
                    <label class="form-label text-muted fw-semibold small">ภาคเรียน <span class="text-danger">*</span></label>
                    <select class="form-select" name="semester" required>
                        <option value="1">1</option>
                        <option value="2">2</option>
                        <option value="3">3 (ฤดูร้อน)</option>
                    </select>
                </div>
            </div>

            <div class="row">
                <div class="col-md-6 mb-3">
                    <label class="form-label text-muted fw-semibold small">คะแนน (ทางเลือก)</label>
                    <input type="number" step="0.01" min="0" max="100" class="form-control" name="score" placeholder="e.g. 85.50">
                </div>
                <div class="col-md-6 mb-3">
                    <label class="form-label text-muted fw-semibold small">ระดับเกรด <span class="text-danger">*</span></label>
                    <select class="form-select" name="grade_letter" required>
                        <option value="" disabled selected>เลือกระดับเกรด...</option>
                        <option value="A">A / 4.0</option>
                        <option value="B+">B+ / 3.5</option>
                        <option value="B">B / 3.0</option>
                        <option value="C+">C+ / 2.5</option>
                        <option value="C">C / 2.0</option>
                        <option value="D+">D+ / 1.5</option>
                        <option value="D">D / 1.0</option>
                        <option value="F">F / 0</option>
                        <option value="W">W</option>
                        <option value="I">I</option>
                    </select>
                </div>
            </div>

            <div class="mb-3">
                <label class="form-label text-muted fw-semibold small">หมายเหตุ / บันทึกจากครู</label>
                <textarea class="form-control" name="remarks" rows="2" placeholder="รายละเอียดเพิ่มเติม..."></textarea>
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
<div class="modal fade" id="editGradeModal" tabindex="-1" aria-labelledby="editGradeModalLabel" aria-hidden="true">
  <div class="modal-dialog modal-lg">
    <div class="modal-content border-0 shadow">
      <div class="modal-header bg-light">
        <h5 class="modal-title text-dark" id="editGradeModalLabel"><i class="fas fa-edit me-2 text-primary"></i> แก้ไขผลการเรียน</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>
      <form method="POST" action="grading.php">
          <input type="hidden" name="csrf_token" value="<?php echo e($csrf_token); ?>">
          <input type="hidden" name="action" value="update">
          <input type="hidden" name="grade_id" id="edit_grade_id">
          
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
                    <label class="form-label text-muted fw-semibold small">ปีการศึกษา <span class="text-danger">*</span></label>
                    <input type="text" class="form-control" id="edit_academic_year" name="academic_year" required>
                </div>
                <div class="col-md-6 mb-3">
                    <label class="form-label text-muted fw-semibold small">ภาคเรียน <span class="text-danger">*</span></label>
                    <select class="form-select" id="edit_semester" name="semester" required>
                        <option value="1">1</option>
                        <option value="2">2</option>
                        <option value="3">3 (ฤดูร้อน)</option>
                    </select>
                </div>
            </div>

            <div class="row">
                <div class="col-md-6 mb-3">
                    <label class="form-label text-muted fw-semibold small">คะแนน (ทางเลือก)</label>
                    <input type="number" step="0.01" min="0" max="100" class="form-control" id="edit_score" name="score">
                </div>
                <div class="col-md-6 mb-3">
                    <label class="form-label text-muted fw-semibold small">ระดับเกรด <span class="text-danger">*</span></label>
                    <select class="form-select" id="edit_grade_letter" name="grade_letter" required>
                        <option value="A">A / 4.0</option>
                        <option value="B+">B+ / 3.5</option>
                        <option value="B">B / 3.0</option>
                        <option value="C+">C+ / 2.5</option>
                        <option value="C">C / 2.0</option>
                        <option value="D+">D+ / 1.5</option>
                        <option value="D">D / 1.0</option>
                        <option value="F">F / 0</option>
                        <option value="W">W</option>
                        <option value="I">I</option>
                    </select>
                </div>
            </div>

            <div class="mb-3">
                <label class="form-label text-muted fw-semibold small">หมายเหตุ / บันทึกจากครู</label>
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
<div class="modal fade" id="deleteGradeModal" tabindex="-1" aria-labelledby="deleteGradeModalLabel" aria-hidden="true">
  <div class="modal-dialog modal-sm modal-dialog-centered">
    <div class="modal-content border-0 shadow">
      <div class="modal-header bg-danger text-white">
        <h5 class="modal-title" id="deleteGradeModalLabel"><i class="fas fa-exclamation-triangle me-2"></i> ยืนยันการลบ</h5>
        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>
      <form method="POST" action="grading.php">
          <input type="hidden" name="csrf_token" value="<?php echo e($csrf_token); ?>">
          <input type="hidden" name="action" value="delete">
          <input type="hidden" name="grade_id" id="delete_grade_id">
          
          <div class="modal-body text-center p-4">
            <i class="fas fa-trash-alt text-danger mb-3" style="font-size: 3rem;"></i>
            <p class="mb-1">คุณแน่ใจหรือไม่ว่าต้องการลบข้อมูลผลการเรียนนี้?</p>
            <strong id="delete_grade_name" class="text-dark d-block mb-3"></strong>
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
            document.getElementById('edit_grade_id').value = this.getAttribute('data-id');
            document.getElementById('edit_student_id').value = this.getAttribute('data-student');
            document.getElementById('edit_subject_id').value = this.getAttribute('data-subject');
            document.getElementById('edit_academic_year').value = this.getAttribute('data-year');
            document.getElementById('edit_semester').value = this.getAttribute('data-semester');
            document.getElementById('edit_score').value = this.getAttribute('data-score');
            document.getElementById('edit_grade_letter').value = this.getAttribute('data-grade');
            document.getElementById('edit_remarks').value = this.getAttribute('data-remarks');
        });
    });
    
    // Delete Modal Fill
    const deleteButtons = document.querySelectorAll('.btn-delete');
    deleteButtons.forEach(button => {
        button.addEventListener('click', function() {
            document.getElementById('delete_grade_id').value = this.getAttribute('data-id');
            document.getElementById('delete_grade_name').textContent = this.getAttribute('data-name');
        });
    });
});
</script>
