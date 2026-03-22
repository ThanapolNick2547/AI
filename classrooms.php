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

        // CREATE CLASSROOM
        if ($action === 'create') {
            $room_number = trim($_POST['room_number']);
            $building = trim($_POST['building']);
            $capacity = intval($_POST['capacity']);
            $status = trim($_POST['status']);

            try {
                $stmt = $pdo->prepare("INSERT INTO classrooms (room_number, building, capacity, status) VALUES (?, ?, ?, ?)");
                $stmt->execute([$room_number, $building, $capacity, $status]);
                $_SESSION['success_msg'] = "เพิ่มห้องเรียนสำเร็จ!";
                header("Location: classrooms.php");
                exit();
            } catch (Exception $e) {
                if ($e instanceof PDOException && $e->getCode() == 23000) {
                    $error = "หมายเลขห้องซ้ำในระบบ";
                } else {
                    $error = "Error adding classroom: " . $e->getMessage();
                }
            }
        }

        // UPDATE CLASSROOM
        else if ($action === 'update') {
            $id = $_POST['classroom_id'];
            $room_number = trim($_POST['room_number']);
            $building = trim($_POST['building']);
            $capacity = intval($_POST['capacity']);
            $status = trim($_POST['status']);
            
            try {
                $stmt = $pdo->prepare("UPDATE classrooms SET room_number = ?, building = ?, capacity = ?, status = ? WHERE id = ?");
                $stmt->execute([$room_number, $building, $capacity, $status, $id]);
                $_SESSION['success_msg'] = "อัปเดตห้องเรียนสำเร็จ!";
                header("Location: classrooms.php");
                exit();
            } catch (Exception $e) {
                if ($e instanceof PDOException && $e->getCode() == 23000) {
                    $error = "หมายเลขห้องซ้ำในระบบ";
                } else {
                    $error = "Error updating classroom: " . $e->getMessage();
                }
            }
        }

        // DELETE CLASSROOM
        else if ($action === 'delete') {
            $id = $_POST['classroom_id'];
            try {
                $stmt = $pdo->prepare("DELETE FROM classrooms WHERE id = ?");
                $stmt->execute([$id]);
                $_SESSION['success_msg'] = "ลบห้องเรียนสำเร็จ!";
                header("Location: classrooms.php");
                exit();
            } catch (Exception $e) {
                if ($e instanceof PDOException && $e->getCode() == 23000) {
                    $error = "ไม่สามารถลบข้อมูลนี้ได้ เนื่องจากมีการอ้างอิงหรือถูกใช้งานอยู่ในระบบอื่น";
                } else {
                    $error = "Error deleting classroom: " . $e->getMessage();
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
$query = "SELECT * FROM classrooms";
$params = [];

if ($search) {
    $query .= " WHERE room_number LIKE ? OR building LIKE ?";
    $searchTerm = "%$search%";
    $params = [$searchTerm, $searchTerm];
}

$query .= " ORDER BY id ASC";

try {
    $stmt = $pdo->prepare($query);
    $stmt->execute($params);
    $classrooms = $stmt->fetchAll();
} catch (PDOException $e) {
    if ($e->getCode() == '42S02') {
        $error = "Database table 'classrooms' does not exist. Please run setup_classrooms_db.php";
    } else {
        $error = "Error fetching data: " . $e->getMessage();
    }
    $classrooms = [];
}

// Include Layout Header
include 'includes/header.php';
?>

<!-- Breadcrumb -->
<nav aria-label="breadcrumb">
    <ol class="breadcrumb">
        <li class="breadcrumb-item"><a href="index.php">หน้าหลัก</a></li>
        <li class="breadcrumb-item"><a href="#">ข้อมูลหลัก</a></li>
        <li class="breadcrumb-item active" aria-current="page">ห้องเรียน</li>
    </ol>
</nav>

<div class="d-flex justify-content-between align-items-center mb-4">
    <h2 class="mb-0 fw-bold text-dark">จัดการห้องเรียน</h2>
    <button type="button" class="btn btn-primary shadow-sm" data-bs-toggle="modal" data-bs-target="#addClassroomModal">
        <i class="fas fa-plus-circle me-1"></i> เพิ่มห้องเรียนใหม่
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
        <h5 class="mb-0 fw-bold">รายการห้องเรียน</h5>
        <form method="GET" class="d-flex" style="max-width: 300px;">
            <div class="input-group input-group-sm">
                <input type="text" name="search" class="form-control" placeholder="ค้นหาห้อง หรือ อาคาร..." value="<?php echo e($search); ?>">
                <button class="btn btn-outline-secondary" type="submit"><i class="fas fa-search"></i></button>
                <?php if ($search): ?>
                    <a href="classrooms.php" class="btn btn-outline-danger"><i class="fas fa-times"></i></a>
                <?php endif; ?>
            </div>
        </form>
    </div>
    
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0 datatable">
                <thead class="bg-light">
                    <tr>
                        <th class="ps-4" width="5%">ลำดับ</th>
                        <th width="20%">หมายเลขห้อง</th>
                        <th width="25%">อาคาร</th>
                        <th width="15%">ความจุ</th>
                        <th width="15%">สถานะ</th>
                        <th class="pe-4 text-end" width="20%">จัดการ</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (count($classrooms) > 0): ?>
                        <?php foreach($classrooms as $index => $c): ?>
                            <tr>
                                <td class="ps-4 text-muted">
                                    <?php echo $index + 1; ?>
                                </td>
                                <td>
                                    <h6 class="mb-0 fw-semibold text-dark"><?php echo e($c['room_number']); ?></h6>
                                </td>
                                <td>
                                    <?php if($c['building']): ?>
                                        <span class="text-muted"><i class="far fa-building me-1"></i> <?php echo e($c['building']); ?></span>
                                    <?php else: ?>
                                        <span class="text-muted fst-italic">-</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <span class="text-muted"><i class="fas fa-users me-1"></i> <?php echo e($c['capacity']); ?></span>
                                </td>
                                <td>
                                    <?php if($c['status'] == 'active'): ?>
                                        <span class="badge bg-success bg-opacity-10 text-success border border-success"><i class="fas fa-check-circle me-1"></i> ใช้งาน</span>
                                    <?php elseif($c['status'] == 'maintenance'): ?>
                                        <span class="badge bg-warning bg-opacity-10 text-warning border border-warning"><i class="fas fa-tools me-1"></i> ซ่อมบำรุง</span>
                                    <?php else: ?>
                                        <span class="badge bg-secondary bg-opacity-10 text-secondary border border-secondary"><i class="fas fa-times-circle me-1"></i> ยกเลิกใช้งาน</span>
                                    <?php endif; ?>
                                </td>
                                <td class="pe-4 text-end">
                                    <button class="btn btn-sm btn-outline-primary btn-edit me-1" 
                                            data-id="<?php echo $c['id']; ?>"
                                            data-room="<?php echo e($c['room_number']); ?>"
                                            data-building="<?php echo e($c['building']); ?>"
                                            data-capacity="<?php echo e($c['capacity']); ?>"
                                            data-status="<?php echo e($c['status']); ?>"
                                            data-bs-toggle="modal" data-bs-target="#editClassroomModal">
                                        <i class="fas fa-edit"></i>
                                    </button>
                                    
                                    <button type="button" class="btn btn-sm btn-outline-danger btn-delete" 
                                            data-id="<?php echo $c['id']; ?>"
                                            data-name="<?php echo e($c['room_number'] . ' (' . ($c['building'] ? $c['building'] : 'No Building') . ')'); ?>"
                                            data-bs-toggle="modal" data-bs-target="#deleteClassroomModal">
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
        <small class="text-muted">รวมทั้งหมด: <?php echo count($classrooms); ?> ห้องเรียน</small>
    </div>
</div>

<!-- ==========================================
     MODALS 
     ========================================== -->

<!-- ADD MODAL -->
<div class="modal fade" id="addClassroomModal" tabindex="-1" aria-labelledby="addClassroomModalLabel" aria-hidden="true">
  <div class="modal-dialog">
    <div class="modal-content border-0 shadow">
      <div class="modal-header bg-primary text-white">
        <h5 class="modal-title" id="addClassroomModalLabel"><i class="fas fa-door-open me-2"></i> เพิ่มห้องเรียนใหม่</h5>
        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>
      <form method="POST" action="classrooms.php">
          <input type="hidden" name="csrf_token" value="<?php echo e($csrf_token); ?>">
          <input type="hidden" name="action" value="create">
          
          <div class="modal-body">
            <div class="mb-3">
                <label for="room_number" class="form-label text-muted fw-semibold small">หมายเลขห้อง <span class="text-danger">*</span></label>
                <input type="text" class="form-control" id="room_number" name="room_number" placeholder="e.g. 101, Lab A" required>
            </div>
            
            <div class="mb-3">
                <label for="building" class="form-label text-muted fw-semibold small">อาคาร</label>
                <input type="text" class="form-control" id="building" name="building" placeholder="e.g. Building A">
            </div>
            
            <div class="mb-3">
                <label for="capacity" class="form-label text-muted fw-semibold small">ความจุ (จำนวนที่นั่ง) <span class="text-danger">*</span></label>
                <input type="number" class="form-control" id="capacity" name="capacity" min="1" placeholder="e.g. 40" required>
            </div>
            
            <div class="mb-3">
                <label for="status" class="form-label text-muted fw-semibold small">สถานะ <span class="text-danger">*</span></label>
                <select class="form-select" id="status" name="status" required>
                    <option value="active" selected>ใช้งาน</option>
                    <option value="maintenance">ซ่อมบำรุง</option>
                    <option value="inactive">ยกเลิกใช้งาน</option>
                </select>
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
<div class="modal fade" id="editClassroomModal" tabindex="-1" aria-labelledby="editClassroomModalLabel" aria-hidden="true">
  <div class="modal-dialog">
    <div class="modal-content border-0 shadow">
      <div class="modal-header bg-light">
        <h5 class="modal-title text-dark" id="editClassroomModalLabel"><i class="fas fa-edit me-2 text-primary"></i> แก้ไขข้อมูลห้องเรียน</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>
      <form method="POST" action="classrooms.php">
          <input type="hidden" name="csrf_token" value="<?php echo e($csrf_token); ?>">
          <input type="hidden" name="action" value="update">
          <input type="hidden" name="classroom_id" id="edit_classroom_id">
          
          <div class="modal-body">
            <div class="mb-3">
                <label for="edit_room_number" class="form-label text-muted fw-semibold small">หมายเลขห้อง <span class="text-danger">*</span></label>
                <input type="text" class="form-control" id="edit_room_number" name="room_number" required>
            </div>
            
            <div class="mb-3">
                <label for="edit_building" class="form-label text-muted fw-semibold small">อาคาร</label>
                <input type="text" class="form-control" id="edit_building" name="building">
            </div>
            
            <div class="mb-3">
                <label for="edit_capacity" class="form-label text-muted fw-semibold small">ความจุ (จำนวนที่นั่ง) <span class="text-danger">*</span></label>
                <input type="number" class="form-control" id="edit_capacity" name="capacity" min="1" required>
            </div>
            
            <div class="mb-3">
                <label for="edit_status" class="form-label text-muted fw-semibold small">สถานะ <span class="text-danger">*</span></label>
                <select class="form-select" id="edit_status" name="status" required>
                    <option value="active">ใช้งาน</option>
                    <option value="maintenance">ซ่อมบำรุง</option>
                    <option value="inactive">ยกเลิกใช้งาน</option>
                </select>
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
<div class="modal fade" id="deleteClassroomModal" tabindex="-1" aria-labelledby="deleteClassroomModalLabel" aria-hidden="true">
  <div class="modal-dialog modal-sm modal-dialog-centered">
    <div class="modal-content border-0 shadow">
      <div class="modal-header bg-danger text-white">
        <h5 class="modal-title" id="deleteClassroomModalLabel"><i class="fas fa-exclamation-triangle me-2"></i> ยืนยันการลบ</h5>
        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>
      <form method="POST" action="classrooms.php">
          <input type="hidden" name="csrf_token" value="<?php echo e($csrf_token); ?>">
          <input type="hidden" name="action" value="delete">
          <input type="hidden" name="classroom_id" id="delete_classroom_id">
          
          <div class="modal-body text-center p-4">
            <i class="fas fa-trash-alt text-danger mb-3" style="font-size: 3rem;"></i>
            <p class="mb-1">คุณแน่ใจหรือไม่ว่าต้องการลบข้อมูลห้องเรียนนี้?</p>
            <strong id="delete_classroom_name" class="text-dark d-block mb-3"></strong>
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
            document.getElementById('edit_classroom_id').value = this.getAttribute('data-id');
            document.getElementById('edit_room_number').value = this.getAttribute('data-room');
            document.getElementById('edit_building').value = this.getAttribute('data-building');
            document.getElementById('edit_capacity').value = this.getAttribute('data-capacity');
            document.getElementById('edit_status').value = this.getAttribute('data-status');
        });
    });
    
    // Delete Modal Fill
    const deleteButtons = document.querySelectorAll('.btn-delete');
    deleteButtons.forEach(button => {
        button.addEventListener('click', function() {
            document.getElementById('delete_classroom_id').value = this.getAttribute('data-id');
            document.getElementById('delete_classroom_name').textContent = this.getAttribute('data-name');
        });
    });
});
</script>
