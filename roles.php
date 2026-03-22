<?php
require_once 'includes/db.php';
require_once 'includes/auth.php';

// Protect this route
requireLogin();

$success = '';
$error = '';

/** ======================================
 *  Handle POST Requests
 *  ====================================== */
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!isset($_POST['csrf_token']) || !validateCSRFToken($_POST['csrf_token'])) {
        $error = "Invalid CSRF Token.";
    } else {
        $action = $_POST['action'] ?? '';

        if ($action === 'create_role') {
            $name = strtolower(trim($_POST['name']));
            $description = trim($_POST['description']);
            
            try {
                $stmt = $pdo->prepare("INSERT INTO roles (name, description) VALUES (?, ?)");
                $stmt->execute([$name, $description]);
                $_SESSION['success_msg'] = "สร้างสิทธิ์ '$name' สำเร็จ!";
                header("Location: roles.php");
                exit();
            } catch (PDOException $e) {
                if ($e->getCode() == 23000) {
                    $error = "มีชื่อสิทธิ์นี้อยู่ในระบบแล้ว";
                } else {
                    $error = "เกิดข้อผิดพลาดในการสร้างสิทธิ์: " . $e->getMessage();
                }
            }
        }
        else if ($action === 'update_role') {
            $id = intval($_POST['role_id']);
            $name = strtolower(trim($_POST['name']));
            $description = trim($_POST['description']);
            
            try {
                $stmt = $pdo->prepare("UPDATE roles SET name = ?, description = ? WHERE id = ?");
                $stmt->execute([$name, $description, $id]);
                $_SESSION['success_msg'] = "อัปเดตสิทธิ์สำเร็จ!";
                header("Location: roles.php");
                exit();
            } catch (PDOException $e) {
                if ($e->getCode() == 23000) {
                    $error = "ชื่อสิทธิ์ซ้ำซ้อน";
                } else {
                    $error = "เกิดข้อผิดพลาดในการอัปเดตสิทธิ์: " . $e->getMessage();
                }
            }
        }
        else if ($action === 'delete_role') {
            $id = intval($_POST['role_id']);
            try {
                // Prevent deleting 'admin' role
                $check = $pdo->prepare("SELECT name FROM roles WHERE id = ?");
                $check->execute([$id]);
                if ($check->fetchColumn() === 'admin') {
                    $error = "ไม่สามารถลบสิทธิ์ admin หลักได้";
                } else {
                    $stmt = $pdo->prepare("DELETE FROM roles WHERE id = ?");
                    $stmt->execute([$id]);
                    $_SESSION['success_msg'] = "ลบสิทธิ์สำเร็จ!";
                    header("Location: roles.php");
                    exit();
                }
            } catch (Exception $e) {
                $error = "เกิดข้อผิดพลาดในการลบสิทธิ์: " . $e->getMessage();
            }
        }
        else if ($action === 'update_permissions') {
            $role_id = intval($_POST['role_id']);
            $permissions = $_POST['permissions'] ?? [];
            
            try {
                $pdo->beginTransaction();
                
                // Clear existing
                $stmt = $pdo->prepare("DELETE FROM role_permissions WHERE role_id = ?");
                $stmt->execute([$role_id]);
                
                // Insert new ones
                if (!empty($permissions)) {
                    $insertStmt = $pdo->prepare("INSERT INTO role_permissions (role_id, permission_id) VALUES (?, ?)");
                    foreach ($permissions as $perm_id) {
                        $insertStmt->execute([$role_id, intval($perm_id)]);
                    }
                }
                
                $pdo->commit();
                $_SESSION['success_msg'] = "อัปเดตการจัดสรรสิทธิ์สำเร็จ!";
                header("Location: roles.php");
                exit();
            } catch (Exception $e) {
                $pdo->rollBack();
                $error = "เกิดข้อผิดพลาดในการจัดการสิทธิ์: " . $e->getMessage();
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
 *  Fetch Roles and Permissions
 *  ====================================== */
$roles = [];
$groupedPerms = [];
$rolePermMap = []; // format: [role_id => [perm_id, perm_id, ...]]

try {
    // 1. Fetch Roles
    $stmt = $pdo->query("SELECT r.id, r.name, r.description, COUNT(rp.permission_id) as perm_count 
                         FROM roles r 
                         LEFT JOIN role_permissions rp ON r.id = rp.role_id 
                         GROUP BY r.id ORDER BY r.name ASC");
    $roles = $stmt->fetchAll();

    // 2. Fetch all Permissions grouped by Module
    $pStmt = $pdo->query("SELECT * FROM permissions ORDER BY module_name ASC, action DESC");
    foreach ($pStmt->fetchAll() as $p) {
        $groupedPerms[$p['module_name']][] = $p;
    }

    // 3. Fetch exact mappings for checkboxes
    $rpStmt = $pdo->query("SELECT role_id, permission_id FROM role_permissions");
    foreach ($rpStmt->fetchAll() as $rp) {
        $rolePermMap[$rp['role_id']][] = $rp['permission_id'];
    }

} catch (PDOException $e) {
    if ($e->getCode() == '42S02') {
        $error = "ไม่พบตารางข้อมูล กรุณารัน setup_roles_db.php";
    } else {
        $error = "เกิดข้อผิดพลาดเกี่ยวกับฐานข้อมูล: " . $e->getMessage();
    }
}

// Include Layout Header
include 'includes/header.php';
?>

<!-- Breadcrumb -->
<nav aria-label="breadcrumb">
    <ol class="breadcrumb">
        <li class="breadcrumb-item"><a href="index.php">หน้าหลัก</a></li>
        <li class="breadcrumb-item active" aria-current="page">จัดการสิทธิ์การเข้าถึง</li>
    </ol>
</nav>

<div class="d-flex justify-content-between align-items-center mb-4">
    <h2 class="mb-0 fw-bold text-dark">บทบาทและสิทธิการเข้าถึง</h2>
    <button type="button" class="btn btn-primary shadow-sm" data-bs-toggle="modal" data-bs-target="#addRoleModal">
        <i class="fas fa-plus-circle me-1"></i> สร้างกลุ่มสิทธิ์ใหม่
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

<!-- Roles Listing -->
<div class="row">
    <?php foreach($roles as $r): ?>
        <div class="col-md-6 mb-4">
            <div class="card h-100 border-0 shadow-sm" style="border-radius: 12px; border-top: 4px solid var(--primary-color) !important;">
                <div class="card-body position-relative">
                    <div class="d-flex justify-content-between align-items-start mb-3">
                        <div class="d-flex align-items-center">
                            <div class="bg-primary bg-opacity-10 text-primary p-3 rounded-circle me-3">
                                <?php if($r['name'] === 'admin'): ?>
                                    <i class="fas fa-crown fa-lg"></i>
                                <?php elseif($r['name'] === 'teacher'): ?>
                                    <i class="fas fa-chalkboard-teacher fa-lg"></i>
                                <?php elseif($r['name'] === 'student'): ?>
                                    <i class="fas fa-user-graduate fa-lg"></i>
                                <?php else: ?>
                                    <i class="fas fa-users-cog fa-lg"></i>
                                <?php endif; ?>
                            </div>
                            <div>
                                <h5 class="mb-1 fw-bold text-dark text-capitalize"><?php echo e($r['name']); ?></h5>
                                <span class="badge bg-secondary bg-opacity-10 text-secondary border border-secondary"><?php echo $r['perm_count']; ?> สิทธิ์การใช้งาน</span>
                            </div>
                        </div>
                        
                        <div class="dropdown">
                            <button class="btn btn-light btn-sm rounded-circle" type="button" data-bs-toggle="dropdown" aria-expanded="false">
                                <i class="fas fa-ellipsis-v text-muted"></i>
                            </button>
                            <ul class="dropdown-menu dropdown-menu-end shadow border-0">
                                <li>
                                    <button class="dropdown-item btn-edit-role" 
                                            data-id="<?php echo $r['id']; ?>"
                                            data-name="<?php echo e($r['name']); ?>"
                                            data-desc="<?php echo e($r['description']); ?>"
                                            data-bs-toggle="modal" data-bs-target="#editRoleModal">
                                        <i class="fas fa-pencil-alt fa-sm me-2 text-primary"></i> แก้ไขข้อมูลกลุ่มสิทธิ์
                                    </button>
                                </li>
                                <?php if($r['name'] !== 'admin'): ?>
                                <li><hr class="dropdown-divider"></li>
                                <li>
                                    <button class="dropdown-item text-danger btn-delete-role" 
                                            data-id="<?php echo $r['id']; ?>"
                                            data-name="<?php echo e($r['name']); ?>"
                                            data-bs-toggle="modal" data-bs-target="#deleteRoleModal">
                                        <i class="fas fa-trash-alt fa-sm me-2"></i> ลบกลุ่มสิทธิ์
                                    </button>
                                </li>
                                <?php endif; ?>
                            </ul>
                        </div>
                    </div>
                    
                    <p class="text-muted small mb-4 pr-4" style="min-height: 40px;">
                        <?php echo e($r['description'] ?: 'ไม่มีคำอธิบายเพิ่มเติม'); ?>
                    </p>
                    
                    <button class="btn btn-outline-primary btn-sm w-100 fw-semibold" data-bs-toggle="modal" data-bs-target="#permsModal_<?php echo $r['id']; ?>">
                        <i class="fas fa-tasks me-1"></i> จัดการสิทธิ์ที่อนุญาต
                    </button>
                </div>
            </div>
        </div>
        
        <!-- ================= PERMISSION MATRIX MODAL FOR THIS ROLE ================= -->
        <div class="modal fade" id="permsModal_<?php echo $r['id']; ?>" tabindex="-1" aria-hidden="true">
          <div class="modal-dialog modal-xl modal-dialog-scrollable">
            <div class="modal-content border-0 shadow mx-auto">
              <div class="modal-header bg-dark text-white">
                <h5 class="modal-title"><i class="fas fa-user-lock me-2 text-warning"></i> นโยบายการเข้าถึงสำหรับ <strong class="text-capitalize text-warning"><?php echo e($r['name']); ?></strong></h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
              </div>
              <form method="POST" action="roles.php">
                  <input type="hidden" name="csrf_token" value="<?php echo e($csrf_token); ?>">
                  <input type="hidden" name="action" value="update_permissions">
                  <input type="hidden" name="role_id" value="<?php echo $r['id']; ?>">
                  
                  <div class="modal-body bg-light p-4">
                      <?php if($r['name'] === 'admin'): ?>
                          <div class="alert alert-info border-info mb-4">
                              <i class="fas fa-info-circle me-2"></i> <strong>หมายเหตุ:</strong> สิทธิ์ของผู้ดูแลระบบ (Admin) อาจมีความครอบคลุมสูงสุดในบางระบบ โปรดจัดการสิทธิ์อย่างระมัดระวัง
                          </div>
                      <?php endif; ?>
                      
                      <div class="row">
                          <?php foreach($groupedPerms as $module => $perms): ?>
                              <div class="col-md-6 col-lg-4 mb-4">
                                  <div class="card h-100 border-0 shadow-sm">
                                      <div class="card-header bg-white border-bottom py-3">
                                          <h6 class="mb-0 fw-bold text-dark"><i class="fas fa-cube text-primary me-2"></i> โมดูล: <?php echo e($module); ?></h6>
                                      </div>
                                      <ul class="list-group list-group-flush">
                                          <?php foreach($perms as $p): ?>
                                              <?php 
                                                  $isChecked = isset($rolePermMap[$r['id']]) && in_array($p['id'], $rolePermMap[$r['id']]); 
                                              ?>
                                              <li class="list-group-item d-flex justify-content-between align-items-center py-2 px-3 hover-bg-light">
                                                  <label class="form-check-label small stretched-link text-muted" for="perm_<?php echo $r['id']; ?>_<?php echo $p['id']; ?>">
                                                      <?php echo e(ucwords($p['action'])); ?>
                                                  </label>
                                                  <div class="form-check form-switch m-0">
                                                      <input class="form-check-input" type="checkbox" role="switch" name="permissions[]" value="<?php echo $p['id']; ?>" id="perm_<?php echo $r['id']; ?>_<?php echo $p['id']; ?>" <?php echo $isChecked ? 'checked' : ''; ?>>
                                                  </div>
                                              </li>
                                          <?php endforeach; ?>
                                      </ul>
                                  </div>
                              </div>
                          <?php endforeach; ?>
                      </div>
                  </div>
                  <div class="modal-footer bg-white border-top">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">ยกเลิก</button>
                    <button type="submit" class="btn btn-primary">บันทึกการเปลี่ยนแปลง</button>
                  </div>
              </form>
            </div>
          </div>
        </div>
        
    <?php endforeach; ?>
    
    <?php if(empty($roles)): ?>
        <div class="col-12"><div class="alert alert-warning">ไม่มีข้อมูลกลุ่มสิทธิ์ในระบบ</div></div>
    <?php endif; ?>
</div>

<!-- ==========================================
     CRUD MODALS FOR ROLES
     ========================================== -->

<!-- ADD ROLE MODAL -->
<div class="modal fade" id="addRoleModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog">
    <div class="modal-content border-0 shadow">
      <div class="modal-header bg-primary text-white">
        <h5 class="modal-title"><i class="fas fa-user-plus me-2"></i> สร้างกลุ่มสิทธิ์ใหม่</h5>
        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>
      <form method="POST" action="roles.php">
          <input type="hidden" name="csrf_token" value="<?php echo e($csrf_token); ?>">
          <input type="hidden" name="action" value="create_role">
          <div class="modal-body p-4">
              <div class="mb-3">
                  <label class="form-label text-muted fw-semibold small">ชื่อกลุ่มสิทธิ์ <span class="text-danger">*</span></label>
                  <input type="text" class="form-control" name="name" placeholder="e.g. parent, auditor" required>
                  <div class="form-text mt-1">แนะนำให้ใช้คำเดียว ตัวพิมพ์เล็ก และชัดเจน</div>
              </div>
              <div class="mb-2">
                  <label class="form-label text-muted fw-semibold small">คำอธิบาย</label>
                  <textarea class="form-control" name="description" rows="3" placeholder="อธิบายจุดประสงค์ของกลุ่มสิทธิ์นี้สั้นๆ..."></textarea>
              </div>
          </div>
          <div class="modal-footer bg-light border-0">
            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">ยกเลิก</button>
            <button type="submit" class="btn btn-primary">สร้างสิทธิ์</button>
          </div>
      </form>
    </div>
  </div>
</div>

<!-- EDIT ROLE MODAL -->
<div class="modal fade" id="editRoleModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog">
    <div class="modal-content border-0 shadow">
      <div class="modal-header bg-light">
        <h5 class="modal-title text-dark"><i class="fas fa-edit me-2 text-primary"></i> แก้ไขคุณสมบัติสิทธิ์</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>
      <form method="POST" action="roles.php">
          <input type="hidden" name="csrf_token" value="<?php echo e($csrf_token); ?>">
          <input type="hidden" name="action" value="update_role">
          <input type="hidden" name="role_id" id="edit_role_id">
          
          <div class="modal-body p-4">
              <div class="mb-3">
                  <label class="form-label text-muted fw-semibold small">ชื่อกลุ่มสิทธิ์ <span class="text-danger">*</span></label>
                  <input type="text" class="form-control bg-light" id="edit_name" name="name" required readonly>
                  <div class="form-text mt-1 text-warning">ชื่อที่ใช้ระบุในระบบไม่สามารถเปลี่ยนได้หลังจากสร้างแล้ว</div>
              </div>
              <div class="mb-2">
                  <label class="form-label text-muted fw-semibold small">คำอธิบาย</label>
                  <textarea class="form-control" id="edit_description" name="description" rows="3"></textarea>
              </div>
          </div>
          
          <div class="modal-footer bg-light border-0">
            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">ยกเลิก</button>
            <button type="submit" class="btn btn-primary">อัปเดตข้อมูล</button>
          </div>
      </form>
    </div>
  </div>
</div>

<!-- DELETE ROLE MODAL -->
<div class="modal fade" id="deleteRoleModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-sm modal-dialog-centered">
    <div class="modal-content border-0 shadow">
      <div class="modal-header bg-danger text-white">
        <h5 class="modal-title"><i class="fas fa-exclamation-triangle me-2"></i> ยืนยันการลบ</h5>
        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>
      <form method="POST" action="roles.php">
          <input type="hidden" name="csrf_token" value="<?php echo e($csrf_token); ?>">
          <input type="hidden" name="action" value="delete_role">
          <input type="hidden" name="role_id" id="delete_role_id">
          
          <div class="modal-body text-center p-4">
            <i class="fas fa-shield-alt text-danger mb-3" style="font-size: 3rem;"></i>
            <p class="mb-1">คุณแน่ใจหรือไม่ว่าต้องการลบสิทธิ์นี้อย่างถาวร?</p>
            <strong id="delete_role_name" class="text-dark d-block mb-3 h5 text-capitalize text-danger"></strong>
            <p class="text-muted small mb-0">ผู้ใช้งานที่ได้รับสิทธิ์นี้จะสูญเสียการเข้าถึงที่เกี่ยวข้องทันที</p>
          </div>
          
          <div class="modal-footer bg-light justify-content-center border-0">
            <button type="button" class="btn btn-secondary btn-sm px-3" data-bs-dismiss="modal">ยกเลิก</button>
            <button type="submit" class="btn btn-danger btn-sm px-3">ยืนยันการลบสิทธิ์</button>
          </div>
      </form>
    </div>
  </div>
</div>

<?php include 'includes/footer.php'; ?>

<!-- Custom Script for modals -->
<script>
document.addEventListener('DOMContentLoaded', function() {
    const editButtons = document.querySelectorAll('.btn-edit-role');
    editButtons.forEach(button => {
        button.addEventListener('click', function() {
            document.getElementById('edit_role_id').value = this.getAttribute('data-id');
            document.getElementById('edit_name').value = this.getAttribute('data-name');
            document.getElementById('edit_description').value = this.getAttribute('data-desc');
        });
    });
    
    const deleteButtons = document.querySelectorAll('.btn-delete-role');
    deleteButtons.forEach(button => {
        button.addEventListener('click', function() {
            document.getElementById('delete_role_id').value = this.getAttribute('data-id');
            document.getElementById('delete_role_name').textContent = this.getAttribute('data-name');
        });
    });
});
</script>
