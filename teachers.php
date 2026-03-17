<?php
require_once 'includes/db.php';
require_once 'includes/auth.php';

// Protect this route
requireLogin();

$success = '';
$error = '';
$uploadDir = 'assets/images/teachers/';

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
    $newName = uniqid('prof_') . '_' . time() . '.' . $extension;
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

        // CREATE TEACHER
        if ($action === 'create') {
            $code = trim($_POST['teacher_code']);
            $first = trim($_POST['first_name']);
            $last = trim($_POST['last_name']);
            $phone = trim($_POST['phone']);
            $line_id = trim($_POST['line_id']);
            $dept = trim($_POST['department']);
            $profile_pic = null;

            try {
                if (!empty($_FILES['profile_picture']['name'])) {
                    $profile_pic = handleProfileUpload($_FILES['profile_picture'], $uploadDir);
                }

                $stmt = $pdo->prepare("INSERT INTO teachers (teacher_code, first_name, last_name, phone, line_id, department, profile_picture) VALUES (?, ?, ?, ?, ?, ?, ?)");
                $stmt->execute([$code, $first, $last, $phone, $line_id, $dept, $profile_pic]);
                $_SESSION['success_msg'] = "Teacher added successfully!";
                header("Location: teachers.php");
                exit();
            } catch (Exception $e) {
                if ($e instanceof PDOException && $e->getCode() == 23000) {
                    $error = "Teacher Code already exists.";
                } else {
                    $error = "Error adding teacher: " . $e->getMessage();
                }
            }
        }

        // UPDATE TEACHER
        else if ($action === 'update') {
            $id = $_POST['teacher_id'];
            $code = trim($_POST['teacher_code']);
            $first = trim($_POST['first_name']);
            $last = trim($_POST['last_name']);
            $phone = trim($_POST['phone']);
            $line_id = trim($_POST['line_id']);
            $dept = trim($_POST['department']);
            
            try {
                $stmt = $pdo->prepare("SELECT profile_picture FROM teachers WHERE id = ?");
                $stmt->execute([$id]);
                $teacher = $stmt->fetch();
                $profile_pic = $teacher['profile_picture'] ?? null;

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

                $stmt = $pdo->prepare("UPDATE teachers SET teacher_code = ?, first_name = ?, last_name = ?, phone = ?, line_id = ?, department = ?, profile_picture = ? WHERE id = ?");
                $stmt->execute([$code, $first, $last, $phone, $line_id, $dept, $profile_pic, $id]);
                $_SESSION['success_msg'] = "Teacher updated successfully!";
                header("Location: teachers.php");
                exit();
            } catch (Exception $e) {
                if ($e instanceof PDOException && $e->getCode() == 23000) {
                    $error = "Teacher Code already exists.";
                } else {
                    $error = "Error updating teacher: " . $e->getMessage();
                }
            }
        }

        // DELETE TEACHER
        else if ($action === 'delete') {
            $id = $_POST['teacher_id'];
            try {
                // Fetch to delete picture
                $stmt = $pdo->prepare("SELECT profile_picture FROM teachers WHERE id = ?");
                $stmt->execute([$id]);
                $teacher = $stmt->fetch();
                
                if ($teacher && $teacher['profile_picture']) {
                    if (file_exists($uploadDir . $teacher['profile_picture'])) {
                        unlink($uploadDir . $teacher['profile_picture']);
                    }
                }

                $stmt = $pdo->prepare("DELETE FROM teachers WHERE id = ?");
                $stmt->execute([$id]);
                $_SESSION['success_msg'] = "Teacher deleted successfully!";
                header("Location: teachers.php");
                exit();
            } catch (Exception $e) {
                $error = "Error deleting teacher: " . $e->getMessage();
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
$query = "SELECT * FROM teachers";
$params = [];

if ($search) {
    $query .= " WHERE teacher_code LIKE ? OR first_name LIKE ? OR last_name LIKE ?";
    $searchTerm = "%$search%";
    $params = [$searchTerm, $searchTerm, $searchTerm];
}

$query .= " ORDER BY id DESC";

try {
    $stmt = $pdo->prepare($query);
    $stmt->execute($params);
    $teachers = $stmt->fetchAll();
} catch (PDOException $e) {
    $error = "Error fetching data: " . $e->getMessage();
    $teachers = [];
}

// Include Layout Header
include 'includes/header.php';
?>

<!-- Breadcrumb -->
<nav aria-label="breadcrumb">
    <ol class="breadcrumb">
        <li class="breadcrumb-item"><a href="index.php">Home</a></li>
        <li class="breadcrumb-item"><a href="#">Master Data</a></li>
        <li class="breadcrumb-item active" aria-current="page">Teachers</li>
    </ol>
</nav>

<div class="d-flex justify-content-between align-items-center mb-4">
    <h2 class="mb-0 fw-bold text-dark">Teachers Management</h2>
    <button type="button" class="btn btn-primary shadow-sm" data-bs-toggle="modal" data-bs-target="#addTeacherModal">
        <i class="fas fa-plus-circle me-1"></i> Add New Teacher
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
        <h5 class="mb-0 fw-bold">Teacher List</h5>
        <form method="GET" class="d-flex" style="max-width: 300px;">
            <div class="input-group input-group-sm">
                <input type="text" name="search" class="form-control" placeholder="Search name or ID..." value="<?php echo e($search); ?>">
                <button class="btn btn-outline-secondary" type="submit"><i class="fas fa-search"></i></button>
                <?php if ($search): ?>
                    <a href="teachers.php" class="btn btn-outline-danger"><i class="fas fa-times"></i></a>
                <?php endif; ?>
            </div>
        </form>
    </div>
    
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead class="bg-light">
                    <tr>
                        <th class="ps-4">Teacher ID</th>
                        <th>Profile</th>
                        <th>Name</th>
                        <th>Department</th>
                        <th>Contact</th>
                        <th class="pe-4 text-end">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (count($teachers) > 0): ?>
                        <?php foreach($teachers as $t): ?>
                            <tr>
                                <td class="ps-4 fw-semibold text-primary">
                                    <i class="fas fa-id-badge text-muted me-1"></i> 
                                    <?php echo e($t['teacher_code']); ?>
                                </td>
                                <td>
                                    <?php if ($t['profile_picture'] && file_exists($uploadDir . $t['profile_picture'])): ?>
                                        <img src="<?php echo $uploadDir . e($t['profile_picture']); ?>" alt="Profile" class="rounded-circle object-fit-cover shadow-sm border" width="45" height="45">
                                    <?php else: ?>
                                        <div class="bg-primary bg-opacity-10 text-primary rounded-circle d-flex justify-content-center align-items-center shadow-sm" style="width: 45px; height: 45px; font-weight:bold; font-size:1.2rem;">
                                            <?php echo strtoupper(substr(e($t['first_name']), 0, 1)); ?>
                                        </div>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <h6 class="mb-0"><?php echo e($t['first_name'] . ' ' . $t['last_name']); ?></h6>
                                </td>
                                <td>
                                    <span class="badge bg-secondary"><?php echo e($t['department'] ?: 'N/A'); ?></span>
                                </td>
                                <td>
                                    <?php if($t['phone']): ?>
                                        <div><i class="fas fa-phone fa-sm text-muted me-1"></i> <?php echo e($t['phone']); ?></div>
                                    <?php endif; ?>
                                    <?php if($t['line_id']): ?>
                                        <div class="text-success small"><i class="fab fa-line fa-sm me-1"></i> <?php echo e($t['line_id']); ?></div>
                                    <?php endif; ?>
                                </td>
                                <td class="pe-4 text-end">
                                    <button class="btn btn-sm btn-outline-primary btn-edit me-1" 
                                            data-id="<?php echo $t['id']; ?>"
                                            data-code="<?php echo e($t['teacher_code']); ?>"
                                            data-first="<?php echo e($t['first_name']); ?>"
                                            data-last="<?php echo e($t['last_name']); ?>"
                                            data-phone="<?php echo e($t['phone']); ?>"
                                            data-line="<?php echo e($t['line_id']); ?>"
                                            data-dept="<?php echo e($t['department']); ?>"
                                            data-bs-toggle="modal" data-bs-target="#editTeacherModal">
                                        <i class="fas fa-edit"></i>
                                    </button>
                                    
                                    <button type="button" class="btn btn-sm btn-outline-danger btn-delete" 
                                            data-id="<?php echo $t['id']; ?>"
                                            data-name="<?php echo e($t['first_name'] . ' ' . $t['last_name']); ?>"
                                            data-bs-toggle="modal" data-bs-target="#deleteTeacherModal">
                                        <i class="fas fa-trash-alt"></i>
                                    </button>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="6" class="text-center py-4 text-muted">No teachers found.</td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
    <div class="card-footer bg-white border-top-0 py-3" style="border-radius: 0 0 12px 12px;">
        <small class="text-muted">Total: <?php echo count($teachers); ?> records</small>
    </div>
</div>

<!-- ==========================================
     MODALS 
     ========================================== -->

<!-- ADD MODAL -->
<div class="modal fade" id="addTeacherModal" tabindex="-1" aria-labelledby="addTeacherModalLabel" aria-hidden="true">
  <div class="modal-dialog">
    <div class="modal-content border-0 shadow">
      <div class="modal-header bg-primary text-white">
        <h5 class="modal-title" id="addTeacherModalLabel"><i class="fas fa-user-plus me-2"></i> Add New Teacher</h5>
        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>
      <form method="POST" action="teachers.php" enctype="multipart/form-data">
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
                <label for="teacher_code" class="form-label text-muted fw-semibold small">Teacher ID / Code <span class="text-danger">*</span></label>
                <input type="text" class="form-control" id="teacher_code" name="teacher_code" required>
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
                    <label for="phone" class="form-label text-muted fw-semibold small">Phone Number</label>
                    <input type="text" class="form-control" id="phone" name="phone">
                </div>
                <div class="col-md-6 mb-3">
                    <label for="line_id" class="form-label text-muted fw-semibold small">Line ID</label>
                    <input type="text" class="form-control" id="line_id" name="line_id">
                </div>
            </div>
            
            <div class="mb-3">
                <label for="department" class="form-label text-muted fw-semibold small">Department / Subject Group</label>
                <select class="form-select" id="department" name="department">
                    <option value="">Select Department...</option>
                    <option value="Mathematics">Mathematics</option>
                    <option value="Science">Science</option>
                    <option value="English">English</option>
                    <option value="Thai Language">Thai Language</option>
                    <option value="Social Studies">Social Studies</option>
                    <option value="Physical Education">Physical Education</option>
                    <option value="Arts">Arts</option>
                    <option value="Technology">Technology</option>
                </select>
            </div>
          </div>
          
          <div class="modal-footer bg-light">
            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
            <button type="submit" class="btn btn-primary">Save Teacher</button>
          </div>
      </form>
    </div>
  </div>
</div>

<!-- EDIT MODAL -->
<div class="modal fade" id="editTeacherModal" tabindex="-1" aria-labelledby="editTeacherModalLabel" aria-hidden="true">
  <div class="modal-dialog">
    <div class="modal-content border-0 shadow">
      <div class="modal-header bg-light">
        <h5 class="modal-title text-dark" id="editTeacherModalLabel"><i class="fas fa-edit me-2 text-primary"></i> Edit Teacher</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>
      <form method="POST" action="teachers.php" enctype="multipart/form-data">
          <input type="hidden" name="csrf_token" value="<?php echo e($csrf_token); ?>">
          <input type="hidden" name="action" value="update">
          <input type="hidden" name="teacher_id" id="edit_teacher_id">
          
          <div class="modal-body">

            <div class="mb-3">
                <label for="edit_profile_picture" class="form-label text-muted fw-semibold small">Update Profile Picture</label>
                <input type="file" class="form-control" id="edit_profile_picture" name="profile_picture" accept="image/png, image/jpeg, image/gif, image/webp">
                <small class="text-muted">Leave empty to keep current picture.</small>
            </div>

            <div class="mb-3">
                <label for="edit_teacher_code" class="form-label text-muted fw-semibold small">Teacher ID / Code <span class="text-danger">*</span></label>
                <input type="text" class="form-control" id="edit_teacher_code" name="teacher_code" required>
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
                    <label for="edit_phone" class="form-label text-muted fw-semibold small">Phone Number</label>
                    <input type="text" class="form-control" id="edit_phone" name="phone">
                </div>
                <div class="col-md-6 mb-3">
                    <label for="edit_line_id" class="form-label text-muted fw-semibold small">Line ID</label>
                    <input type="text" class="form-control" id="edit_line_id" name="line_id">
                </div>
            </div>
            
            <div class="mb-3">
                <label for="edit_department" class="form-label text-muted fw-semibold small">Department / Subject Group</label>
                <select class="form-select" id="edit_department" name="department">
                    <option value="">Select Department...</option>
                    <option value="Mathematics">Mathematics</option>
                    <option value="Science">Science</option>
                    <option value="English">English</option>
                    <option value="Thai Language">Thai Language</option>
                    <option value="Social Studies">Social Studies</option>
                    <option value="Physical Education">Physical Education</option>
                    <option value="Arts">Arts</option>
                    <option value="Technology">Technology</option>
                </select>
            </div>
          </div>
          
          <div class="modal-footer bg-light">
            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
            <button type="submit" class="btn btn-primary">Update Teacher</button>
          </div>
      </form>
    </div>
  </div>
</div>

<!-- DELETE MODAL -->
<div class="modal fade" id="deleteTeacherModal" tabindex="-1" aria-labelledby="deleteTeacherModalLabel" aria-hidden="true">
  <div class="modal-dialog modal-sm modal-dialog-centered">
    <div class="modal-content border-0 shadow">
      <div class="modal-header bg-danger text-white">
        <h5 class="modal-title" id="deleteTeacherModalLabel"><i class="fas fa-exclamation-triangle me-2"></i> Confirm Delete</h5>
        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>
      <form method="POST" action="teachers.php">
          <input type="hidden" name="csrf_token" value="<?php echo e($csrf_token); ?>">
          <input type="hidden" name="action" value="delete">
          <input type="hidden" name="teacher_id" id="delete_teacher_id">
          
          <div class="modal-body text-center p-4">
            <i class="fas fa-trash-alt text-danger mb-3" style="font-size: 3rem;"></i>
            <p class="mb-1">Are you sure you want to delete this teacher?</p>
            <strong id="delete_teacher_name" class="text-dark d-block mb-3"></strong>
            <p class="text-muted small mb-0">This action cannot be undone.</p>
          </div>
          
          <div class="modal-footer bg-light justify-content-center border-0">
            <button type="button" class="btn btn-secondary btn-sm px-3" data-bs-dismiss="modal">Cancel</button>
            <button type="submit" class="btn btn-danger btn-sm px-3">Yes, Delete</button>
          </div>
      </form>
    </div>
  </div>
</div>

<?php include 'includes/footer.php'; ?>

<!-- Custom Script for filling edit modal -->
<script>
document.addEventListener('DOMContentLoaded', function() {
    const editButtons = document.querySelectorAll('.btn-edit');
    
    editButtons.forEach(button => {
        button.addEventListener('click', function() {
            // Get data from attributes
            const id = this.getAttribute('data-id');
            const code = this.getAttribute('data-code');
            const first = this.getAttribute('data-first');
            const last = this.getAttribute('data-last');
            const phone = this.getAttribute('data-phone');
            const lineId = this.getAttribute('data-line');
            const dept = this.getAttribute('data-dept');
            
            // Populate the edit modal form fields
            document.getElementById('edit_teacher_id').value = id;
            document.getElementById('edit_teacher_code').value = code;
            document.getElementById('edit_first_name').value = first;
            document.getElementById('edit_last_name').value = last;
            document.getElementById('edit_phone').value = phone;
            document.getElementById('edit_line_id').value = lineId;
            document.getElementById('edit_department').value = dept;
        });
    });
    
    const deleteButtons = document.querySelectorAll('.btn-delete');
    deleteButtons.forEach(button => {
        button.addEventListener('click', function() {
            const id = this.getAttribute('data-id');
            const name = this.getAttribute('data-name');
            document.getElementById('delete_teacher_id').value = id;
            document.getElementById('delete_teacher_name').textContent = name;
        });
    });
});
</script>
