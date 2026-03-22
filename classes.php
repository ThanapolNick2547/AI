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

        // CREATE CLASS LEVEL
        if ($action === 'create') {
            $class_name = trim($_POST['class_name']);
            $class_abbr = trim($_POST['class_abbr']);
            $description = trim($_POST['description']);

            try {
                $stmt = $pdo->prepare("INSERT INTO classes (class_name, class_abbr, description) VALUES (?, ?, ?)");
                $stmt->execute([$class_name, $class_abbr, $description]);
                $_SESSION['success_msg'] = "เพิ่มระดับชั้นเรียนสำเร็จ!";
                header("Location: classes.php");
                exit();
            } catch (Exception $e) {
                if ($e instanceof PDOException && $e->getCode() == 23000) {
                    $error = "ชื่อย่อชั้นเรียนซ้ำในระบบ";
                } else {
                    $error = "Error adding class level: " . $e->getMessage();
                }
            }
        }

        // UPDATE CLASS LEVEL
        else if ($action === 'update') {
            $id = $_POST['class_id'];
            $class_name = trim($_POST['class_name']);
            $class_abbr = trim($_POST['class_abbr']);
            $description = trim($_POST['description']);
            
            try {
                $stmt = $pdo->prepare("UPDATE classes SET class_name = ?, class_abbr = ?, description = ? WHERE id = ?");
                $stmt->execute([$class_name, $class_abbr, $description, $id]);
                $_SESSION['success_msg'] = "อัปเดตระดับชั้นเรียนสำเร็จ!";
                header("Location: classes.php");
                exit();
            } catch (Exception $e) {
                if ($e instanceof PDOException && $e->getCode() == 23000) {
                    $error = "ชื่อย่อชั้นเรียนซ้ำในระบบ";
                } else {
                    $error = "Error updating class level: " . $e->getMessage();
                }
            }
        }

        // DELETE CLASS LEVEL
        else if ($action === 'delete') {
            $id = $_POST['class_id'];
            try {
                $stmt = $pdo->prepare("DELETE FROM classes WHERE id = ?");
                $stmt->execute([$id]);
                $_SESSION['success_msg'] = "ลบระดับชั้นเรียนสำเร็จ!";
                header("Location: classes.php");
                exit();
            } catch (Exception $e) {
                if ($e instanceof PDOException && $e->getCode() == 23000) {
                    $error = "ไม่สามารถลบข้อมูลนี้ได้ เนื่องจากมีการอ้างอิงหรือถูกใช้งานอยู่ในระบบอื่น";
                } else {
                    $error = "Error deleting class level: " . $e->getMessage();
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
$query = "SELECT * FROM classes";
$params = [];

if ($search) {
    $query .= " WHERE class_name LIKE ? OR class_abbr LIKE ?";
    $searchTerm = "%$search%";
    $params = [$searchTerm, $searchTerm];
}

$query .= " ORDER BY id ASC";

try {
    $stmt = $pdo->prepare($query);
    $stmt->execute($params);
    $classes = $stmt->fetchAll();
} catch (PDOException $e) {
    $error = "Error fetching data: " . $e->getMessage();
    $classes = [];
}

// Include Layout Header
include 'includes/header.php';
?>

<!-- Breadcrumb -->
<nav aria-label="breadcrumb">
    <ol class="breadcrumb">
        <li class="breadcrumb-item"><a href="index.php">หน้าหลัก</a></li>
        <li class="breadcrumb-item"><a href="#">ข้อมูลหลัก</a></li>
        <li class="breadcrumb-item active" aria-current="page">ระดับชั้นเรียน</li>
    </ol>
</nav>

<div class="d-flex justify-content-between align-items-center mb-4">
    <h2 class="mb-0 fw-bold text-dark">จัดการระดับชั้นเรียน</h2>
    <button type="button" class="btn btn-primary shadow-sm" data-bs-toggle="modal" data-bs-target="#addClassModal">
        <i class="fas fa-plus-circle me-1"></i> เพิ่มระดับชั้นเรียนใหม่
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
        <h5 class="mb-0 fw-bold">รายการระดับชั้นเรียน</h5>
        <form method="GET" class="d-flex" style="max-width: 300px;">
            <div class="input-group input-group-sm">
                <input type="text" name="search" class="form-control" placeholder="ค้นหาชื่อ หรือ ชื่อย่อชั้นเรียน..." value="<?php echo e($search); ?>">
                <button class="btn btn-outline-secondary" type="submit"><i class="fas fa-search"></i></button>
                <?php if ($search): ?>
                    <a href="classes.php" class="btn btn-outline-danger"><i class="fas fa-times"></i></a>
                <?php endif; ?>
            </div>
        </form>
    </div>
    
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0 datatable">
                <thead class="bg-light">
                    <tr>
                        <th class="ps-4" width="8%">ลำดับ</th>
                        <th width="30%">ชื่อชั้นเรียน</th>
                        <th width="20%">ชื่อย่อ</th>
                        <th width="30%">คำอธิบาย</th>
                        <th class="pe-4 text-end" width="12%">จัดการ</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (count($classes) > 0): ?>
                        <?php foreach($classes as $index => $c): ?>
                            <tr>
                                <td class="ps-4 text-muted">
                                    <?php echo $index + 1; ?>
                                </td>
                                <td>
                                    <h6 class="mb-0 fw-semibold text-dark"><?php echo e($c['class_name']); ?></h6>
                                </td>
                                <td>
                                    <span class="badge bg-primary bg-opacity-10 text-primary px-3 py-2 border border-primary"><i class="fas fa-layer-group me-1"></i> <?php echo e($c['class_abbr']); ?></span>
                                </td>
                                <td>
                                    <?php if($c['description']): ?>
                                        <span class="text-muted"><?php echo e($c['description']); ?></span>
                                    <?php else: ?>
                                        <span class="text-muted fst-italic">-</span>
                                    <?php endif; ?>
                                </td>
                                <td class="pe-4 text-end">
                                    <button class="btn btn-sm btn-outline-primary btn-edit me-1" 
                                            data-id="<?php echo $c['id']; ?>"
                                            data-name="<?php echo e($c['class_name']); ?>"
                                            data-abbr="<?php echo e($c['class_abbr']); ?>"
                                            data-desc="<?php echo e($c['description']); ?>"
                                            data-bs-toggle="modal" data-bs-target="#editClassModal">
                                        <i class="fas fa-edit"></i>
                                    </button>
                                    
                                    <button type="button" class="btn btn-sm btn-outline-danger btn-delete" 
                                            data-id="<?php echo $c['id']; ?>"
                                            data-name="<?php echo e($c['class_name'] . ' (' . $c['class_abbr'] . ')'); ?>"
                                            data-bs-toggle="modal" data-bs-target="#deleteClassModal">
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
        <small class="text-muted">รวมทั้งหมด: <?php echo count($classes); ?> ระดับชั้น</small>
    </div>
</div>

<!-- ==========================================
     MODALS 
     ========================================== -->

<!-- ADD MODAL -->
<div class="modal fade" id="addClassModal" tabindex="-1" aria-labelledby="addClassModalLabel" aria-hidden="true">
  <div class="modal-dialog">
    <div class="modal-content border-0 shadow">
      <div class="modal-header bg-primary text-white">
        <h5 class="modal-title" id="addClassModalLabel"><i class="fas fa-layer-group me-2"></i> เพิ่มระดับชั้นเรียนใหม่</h5>
        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>
      <form method="POST" action="classes.php">
          <input type="hidden" name="csrf_token" value="<?php echo e($csrf_token); ?>">
          <input type="hidden" name="action" value="create">
          
          <div class="modal-body">
            <div class="mb-3">
                <label for="class_name" class="form-label text-muted fw-semibold small">ชื่อเต็มชั้นเรียน <span class="text-danger">*</span></label>
                <input type="text" class="form-control" id="class_name" name="class_name" placeholder="e.g. มัธยมศึกษาปีที่ 1 หรือ Secondary 1" required>
            </div>
            
            <div class="mb-3">
                <label for="class_abbr" class="form-label text-muted fw-semibold small">ชื่อย่อ <span class="text-danger">*</span></label>
                <input type="text" class="form-control" id="class_abbr" name="class_abbr" placeholder="e.g. ม.1 หรือ M.1" required>
            </div>
            
            <div class="mb-3">
                <label for="description" class="form-label text-muted fw-semibold small">คำอธิบาย (ทางเลือก)</label>
                <textarea class="form-control" id="description" name="description" rows="3" placeholder="รายละเอียดเพิ่มเติม..."></textarea>
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
<div class="modal fade" id="editClassModal" tabindex="-1" aria-labelledby="editClassModalLabel" aria-hidden="true">
  <div class="modal-dialog">
    <div class="modal-content border-0 shadow">
      <div class="modal-header bg-light">
        <h5 class="modal-title text-dark" id="editClassModalLabel"><i class="fas fa-edit me-2 text-primary"></i> แก้ไขระดับชั้นเรียน</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>
      <form method="POST" action="classes.php">
          <input type="hidden" name="csrf_token" value="<?php echo e($csrf_token); ?>">
          <input type="hidden" name="action" value="update">
          <input type="hidden" name="class_id" id="edit_class_id">
          
          <div class="modal-body">
            <div class="mb-3">
                <label for="edit_class_name" class="form-label text-muted fw-semibold small">ชื่อเต็มชั้นเรียน <span class="text-danger">*</span></label>
                <input type="text" class="form-control" id="edit_class_name" name="class_name" required>
            </div>
            
            <div class="mb-3">
                <label for="edit_class_abbr" class="form-label text-muted fw-semibold small">ชื่อย่อ <span class="text-danger">*</span></label>
                <input type="text" class="form-control" id="edit_class_abbr" name="class_abbr" required>
            </div>
            
            <div class="mb-3">
                <label for="edit_description" class="form-label text-muted fw-semibold small">คำอธิบาย (ทางเลือก)</label>
                <textarea class="form-control" id="edit_description" name="description" rows="3"></textarea>
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
<div class="modal fade" id="deleteClassModal" tabindex="-1" aria-labelledby="deleteClassModalLabel" aria-hidden="true">
  <div class="modal-dialog modal-sm modal-dialog-centered">
    <div class="modal-content border-0 shadow">
      <div class="modal-header bg-danger text-white">
        <h5 class="modal-title" id="deleteClassModalLabel"><i class="fas fa-exclamation-triangle me-2"></i> ยืนยันการลบ</h5>
        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>
      <form method="POST" action="classes.php">
          <input type="hidden" name="csrf_token" value="<?php echo e($csrf_token); ?>">
          <input type="hidden" name="action" value="delete">
          <input type="hidden" name="class_id" id="delete_class_id">
          
          <div class="modal-body text-center p-4">
            <i class="fas fa-trash-alt text-danger mb-3" style="font-size: 3rem;"></i>
            <p class="mb-1">คุณแน่ใจหรือไม่ว่าต้องการลบข้อมูลระดับชั้นเรียนนี้?</p>
            <strong id="delete_class_name" class="text-dark d-block mb-3"></strong>
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
            document.getElementById('edit_class_id').value = this.getAttribute('data-id');
            document.getElementById('edit_class_name').value = this.getAttribute('data-name');
            document.getElementById('edit_class_abbr').value = this.getAttribute('data-abbr');
            document.getElementById('edit_description').value = this.getAttribute('data-desc');
        });
    });
    
    // Delete Modal Fill
    const deleteButtons = document.querySelectorAll('.btn-delete');
    deleteButtons.forEach(button => {
        button.addEventListener('click', function() {
            document.getElementById('delete_class_id').value = this.getAttribute('data-id');
            document.getElementById('delete_class_name').textContent = this.getAttribute('data-name');
        });
    });
});
</script>
