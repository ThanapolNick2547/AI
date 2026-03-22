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
            $teacher_id = intval($_POST['teacher_id']);
            $subject_id = intval($_POST['subject_id']);
            $class_id = intval($_POST['class_id']);
            $classroom_id = intval($_POST['classroom_id']);
            $day_of_week = trim($_POST['day_of_week']);
            $start_time = trim($_POST['start_time']);
            $end_time = trim($_POST['end_time']);
            $academic_year = trim($_POST['academic_year']);
            $semester = intval($_POST['semester']);

            try {
                $stmt = $pdo->prepare("INSERT INTO schedules (teacher_id, subject_id, class_id, classroom_id, day_of_week, start_time, end_time, academic_year, semester) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)");
                $stmt->execute([$teacher_id, $subject_id, $class_id, $classroom_id, $day_of_week, $start_time, $end_time, $academic_year, $semester]);
                $_SESSION['success_msg'] = "เพิ่มตารางสอนสำเร็จ!";
                header("Location: schedules.php");
                exit();
            } catch (Exception $e) {
                $error = "Error adding schedule: " . $e->getMessage();
            }
        }
        else if ($action === 'update') {
            $id = intval($_POST['schedule_id']);
            $teacher_id = intval($_POST['teacher_id']);
            $subject_id = intval($_POST['subject_id']);
            $class_id = intval($_POST['class_id']);
            $classroom_id = intval($_POST['classroom_id']);
            $day_of_week = trim($_POST['day_of_week']);
            $start_time = trim($_POST['start_time']);
            $end_time = trim($_POST['end_time']);
            $academic_year = trim($_POST['academic_year']);
            $semester = intval($_POST['semester']);
            
            try {
                $stmt = $pdo->prepare("UPDATE schedules SET teacher_id = ?, subject_id = ?, class_id = ?, classroom_id = ?, day_of_week = ?, start_time = ?, end_time = ?, academic_year = ?, semester = ? WHERE id = ?");
                $stmt->execute([$teacher_id, $subject_id, $class_id, $classroom_id, $day_of_week, $start_time, $end_time, $academic_year, $semester, $id]);
                $_SESSION['success_msg'] = "อัปเดตตารางสอนสำเร็จ!";
                header("Location: schedules.php");
                exit();
            } catch (Exception $e) {
                $error = "Error updating schedule: " . $e->getMessage();
            }
        }
        else if ($action === 'delete') {
            $id = intval($_POST['schedule_id']);
            try {
                $stmt = $pdo->prepare("DELETE FROM schedules WHERE id = ?");
                $stmt->execute([$id]);
                $_SESSION['success_msg'] = "ลบตารางสอนสำเร็จ!";
                header("Location: schedules.php");
                exit();
            } catch (Exception $e) {
                if ($e instanceof PDOException && $e->getCode() == 23000) {
                    $error = "ไม่สามารถลบข้อมูลนี้ได้ เนื่องจากมีการอ้างอิงหรือถูกใช้งานอยู่ในระบบอื่น";
                } else {
                    $error = "Error deleting schedule: " . $e->getMessage();
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
    $teachers = $pdo->query("SELECT id, first_name, last_name FROM teachers ORDER BY first_name ASC")->fetchAll();
    $subjects = $pdo->query("SELECT id, subject_code, subject_name FROM subjects ORDER BY subject_name ASC")->fetchAll();
    $classes = $pdo->query("SELECT id, class_name, class_abbr FROM classes ORDER BY class_name ASC")->fetchAll();
    $classrooms = $pdo->query("SELECT id, room_number FROM classrooms ORDER BY room_number ASC")->fetchAll();
} catch (PDOException $e) {
    $error = "Missing required database tables: " . $e->getMessage();
    $teachers = $subjects = $classes = $classrooms = [];
}

/** ======================================
 *  Fetch Schedules Data
 *  ====================================== */
$search = $_GET['search'] ?? '';
$query = "
    SELECT 
        s.id, s.day_of_week, s.start_time, s.end_time, s.academic_year, s.semester,
        s.teacher_id, s.subject_id, s.class_id, s.classroom_id,
        t.first_name, t.last_name,
        sub.subject_name, sub.subject_code,
        c.class_abbr,
        cr.room_number
    FROM schedules s
    LEFT JOIN teachers t ON s.teacher_id = t.id
    LEFT JOIN subjects sub ON s.subject_id = sub.id
    LEFT JOIN classes c ON s.class_id = c.id
    LEFT JOIN classrooms cr ON s.classroom_id = cr.id
";
$params = [];

if ($search) {
    $query .= " WHERE t.first_name LIKE ? OR t.last_name LIKE ? OR sub.subject_name LIKE ? OR c.class_abbr LIKE ?";
    $searchTerm = "%$search%";
    $params = [$searchTerm, $searchTerm, $searchTerm, $searchTerm];
}

$query .= " ORDER BY FIELD(s.day_of_week, 'Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday', 'Sunday'), s.start_time ASC";

try {
    $stmt = $pdo->prepare($query);
    $stmt->execute($params);
    $schedules = $stmt->fetchAll();
} catch (PDOException $e) {
    if ($e->getCode() == '42S02') {
        $error = "Database table 'schedules' does not exist. Please run setup_schedules_db.php";
    } else {
        $error = "Error fetching data: " . $e->getMessage();
    }
    $schedules = [];
}

// Timetable Grid Logic
$filter_type = $_GET['filter_type'] ?? 'classroom';
$filter_id = isset($_GET['filter_id']) ? intval($_GET['filter_id']) : 0;
if ($filter_id === 0) {
    if ($filter_type === 'classroom' && count($classrooms) > 0) $filter_id = $classrooms[0]['id'];
    elseif ($filter_type === 'teacher' && count($teachers) > 0) $filter_id = $teachers[0]['id'];
}

$gridParams = [$filter_id];
$gridWhere = $filter_type === 'classroom' ? "s.classroom_id = ?" : "s.teacher_id = ?";
$gridQuery = "
    SELECT 
        s.id, s.day_of_week, s.start_time, s.end_time, s.academic_year, s.semester,
        s.teacher_id, s.subject_id, s.class_id, s.classroom_id,
        t.first_name, t.last_name,
        sub.subject_name, sub.subject_code,
        c.class_abbr,
        cr.room_number
    FROM schedules s
    LEFT JOIN teachers t ON s.teacher_id = t.id
    LEFT JOIN subjects sub ON s.subject_id = sub.id
    LEFT JOIN classes c ON s.class_id = c.id
    LEFT JOIN classrooms cr ON s.classroom_id = cr.id
    WHERE $gridWhere
    ORDER BY FIELD(s.day_of_week, 'Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday', 'Sunday'), s.start_time ASC
";
try {
    $stmtG = $pdo->prepare($gridQuery);
    $stmtG->execute($gridParams);
    $grid_schedules = $stmtG->fetchAll();
} catch (PDOException $e) {
    $grid_schedules = [];
}

$timetable = ['Monday' => [], 'Tuesday' => [], 'Wednesday' => [], 'Thursday' => [], 'Friday' => [], 'Saturday' => [], 'Sunday' => []];
foreach($grid_schedules as $gs) {
    $timetable[$gs['day_of_week']][] = $gs;
}

// Include Layout Header
include 'includes/header.php';
?>

<style>
/* Timetable Grid Styles */
.timetable-grid {
    width: 100%;
    border-collapse: collapse;
    min-width: 900px;
    table-layout: fixed;
}
.timetable-grid th,
.timetable-grid td {
    border: 1px solid #dee2e6;
    text-align: center;
    vertical-align: middle;
    padding: 0;
    min-height: 60px;
}
.timetable-grid thead th {
    background: #f8f9fa;
    font-size: 0.8rem;
    font-weight: 600;
    color: #495057;
    padding: 8px 4px;
    white-space: nowrap;
}
.timetable-grid thead th.day-col-header {
    background: #343a40;
    color: #fff;
    width: 80px;
}
.timetable-grid td.day-label {
    font-weight: 700;
    font-size: 0.95rem;
    background: #f8f9fa;
    width: 80px;
    white-space: nowrap;
    padding: 8px;
}
.timetable-grid td.empty-slot {
    background: #fff;
    min-height: 60px;
    height: 70px;
}
.timetable-grid td.empty-slot:hover {
    background: #f0f7ff;
}
.timetable-cell-block {
    display: flex;
    flex-direction: column;
    justify-content: center;
    align-items: center;
    height: 70px;
    padding: 4px 6px;
    cursor: pointer;
    transition: filter 0.2s, transform 0.15s;
    border-radius: 4px;
    overflow: hidden;
    text-decoration: none;
}
.timetable-cell-block:hover {
    filter: brightness(0.92);
    transform: scale(0.97);
}
.timetable-cell-block .block-code {
    font-size: 0.82rem;
    font-weight: 700;
    color: #0d47a1;
    line-height: 1.2;
}
.timetable-cell-block .block-sub {
    font-size: 0.70rem;
    color: #555;
    line-height: 1.2;
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
    max-width: 100%;
}
.timetable-cell-block .block-time {
    font-size: 0.65rem;
    color: #888;
    margin-top: 2px;
}
</style>

<!-- Breadcrumb -->
<nav aria-label="breadcrumb">
    <ol class="breadcrumb">
        <li class="breadcrumb-item"><a href="index.php">หน้าหลัก</a></li>
        <li class="breadcrumb-item active" aria-current="page">ตารางสอน</li>
    </ol>
</nav>

<div class="d-flex justify-content-between align-items-center mb-4">
    <h2 class="mb-0 fw-bold text-dark">จัดการตารางสอน</h2>
    <button type="button" class="btn btn-primary shadow-sm" data-bs-toggle="modal" data-bs-target="#addScheduleModal">
        <i class="fas fa-plus-circle me-1"></i> เพิ่มตารางสอนใหม่
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

<!-- Nav tabs -->
<ul class="nav nav-tabs mb-4 px-1" id="scheduleTabs" role="tablist">
  <li class="nav-item" role="presentation">
    <button class="nav-link active fw-bold text-dark border-0 border-bottom border-3 <?php echo empty($search) ? 'border-primary' : 'border-transparent'; ?>" id="grid-tab" data-bs-toggle="tab" data-bs-target="#grid-view" type="button" role="tab" aria-controls="grid-view" aria-selected="true" style="border-radius:0;">
        <i class="fas fa-calendar-alt text-primary me-1"></i> มุมมองตารางสอน
    </button>
  </li>
  <li class="nav-item" role="presentation">
    <button class="nav-link fw-bold text-dark border-0 border-bottom border-3 <?php echo !empty($search) ? 'border-primary' : 'border-transparent'; ?>" id="list-tab" data-bs-toggle="tab" data-bs-target="#list-view" type="button" role="tab" aria-controls="list-view" aria-selected="false" style="border-radius:0;">
        <i class="fas fa-list text-primary me-1"></i> มุมมองแบบรายการ
    </button>
  </li>
</ul>

<div class="tab-content">
  <!-- Grid View Tab -->
  <div class="tab-pane fade <?php echo empty($search) ? 'show active' : ''; ?>" id="grid-view" role="tabpanel" aria-labelledby="grid-tab">
      <div class="card border-0 shadow-sm mb-4" style="border-radius: 12px; border: 1px solid var(--border-color) !important;">
          <div class="card-header bg-white py-3 d-flex flex-wrap justify-content-between align-items-center" style="border-radius: 12px 12px 0 0;">
              <h5 class="mb-0 fw-bold">ตารางการเรียนการสอน</h5>
              <form method="GET" class="d-flex align-items-center gap-2 mt-2 mt-md-0">
                  <span class="text-muted small fw-bold">กรองตาม:</span>
                  <select name="filter_type" class="form-select form-select-sm" onchange="this.form.submit()" style="width: auto;">
                      <option value="classroom" <?php echo $filter_type == 'classroom' ? 'selected' : ''; ?>>ห้องเรียน</option>
                      <option value="teacher" <?php echo $filter_type == 'teacher' ? 'selected' : ''; ?>>ครูผู้สอน</option>
                  </select>
                  <select name="filter_id" class="form-select form-select-sm" onchange="this.form.submit()" style="width: auto; min-width: 150px;">
                      <?php if ($filter_type == 'classroom'): ?>
                          <?php foreach($classrooms as $rm): ?>
                              <option value="<?php echo $rm['id']; ?>" <?php echo $filter_id == $rm['id'] ? 'selected' : ''; ?>>ห้อง <?php echo e($rm['room_number']); ?></option>
                          <?php endforeach; ?>
                      <?php else: ?>
                          <?php foreach($teachers as $t): ?>
                              <option value="<?php echo $t['id']; ?>" <?php echo $filter_id == $t['id'] ? 'selected' : ''; ?>><?php echo e($t['first_name'] . ' ' . $t['last_name']); ?></option>
                          <?php endforeach; ?>
                      <?php endif; ?>
                  </select>
              </form>
          </div>
          <div class="card-body p-0" style="overflow-x: auto;">
              <?php
              $days_th = ['Monday'=>'จันทร์', 'Tuesday'=>'อังคาร', 'Wednesday'=>'พุธ', 'Thursday'=>'พฤหัสบดี', 'Friday'=>'ศุกร์'];
              $dayColors = ['Monday'=>'#e3f2fd', 'Tuesday'=>'#fce4ec', 'Wednesday'=>'#e8f5e9', 'Thursday'=>'#fff3e0', 'Friday'=>'#f3e5f5'];
              $dayBorders = ['Monday'=>'#1976d2', 'Tuesday'=>'#c2185b', 'Wednesday'=>'#388e3c', 'Thursday'=>'#e65100', 'Friday'=>'#7b1fa2'];
              $subjectColors = ['#e3f2fd','#f3e5f5','#e8f5e9','#fff3e0','#ffebee','#e0f7fa','#fce4ec','#f9fbe7','#ede7f6'];
              $timeSlots = [];
              for($h = 8; $h < 17; $h++) { $timeSlots[] = $h; }  // 08:00-08:59, ..., 16:00-16:59
              ?>
              <table class="timetable-grid">
                  <thead>
                      <tr>
                          <th class="day-col-header"><i class="fas fa-calendar-week"></i></th>
                          <?php foreach($timeSlots as $h): ?>
                              <th><?php echo str_pad($h,2,'0',STR_PAD_LEFT); ?>:00<br><span style="font-weight:400;font-size:0.7rem;color:#adb5bd;"><?php echo str_pad($h+1,2,'0',STR_PAD_LEFT); ?>:00</span></th>
                          <?php endforeach; ?>
                      </tr>
                  </thead>
                  <tbody>
                      <?php foreach(['Monday','Tuesday','Wednesday','Thursday','Friday'] as $day): ?>
                          <?php
                          $skipCols = [];  // track which hour-slots are already spanned
                          $dayClasses = $timetable[$day];
                          // Index classes by their starting hour
                          $byHour = [];
                          foreach($dayClasses as $cls) {
                              $startH = (int)explode(':', $cls['start_time'])[0];
                              $byHour[$startH][] = $cls;
                          }
                          ?>
                          <tr>
                              <td class="day-label" style="color: <?php echo $dayBorders[$day]; ?>; border-left: 4px solid <?php echo $dayBorders[$day]; ?>;">
                                  <?php echo $days_th[$day]; ?>
                              </td>
                              <?php foreach($timeSlots as $h): ?>
                                  <?php if(in_array($h, $skipCols)) continue; ?>
                                  <?php
                                  // Check if any class starts at this hour
                                  $classesAtH = $byHour[$h] ?? [];
                                  if(count($classesAtH) > 0):
                                      $cls = $classesAtH[0]; // take first (overlapping not handled)
                                      $startH2 = (int)explode(':', $cls['start_time'])[0];
                                      $endH2 = (int)explode(':', $cls['end_time'])[0];
                                      $endM = (int)explode(':', $cls['end_time'])[1];
                                      // Span = number of 1-hour slots the class covers
                                      $span = max(1, $endH2 - $startH2 + ($endM > 0 ? 1 : 0));
                                      // Mark spanned slots as skip
                                      for($s = $startH2+1; $s < $startH2+$span && $s <= 16; $s++) { $skipCols[] = $s; }
                                      $blockBg = $subjectColors[$cls['subject_id'] % count($subjectColors)];
                                      $borderColor = $dayBorders[$day];
                                  ?>
                                      <td colspan="<?php echo $span; ?>" style="padding:0; border: 1px solid #dee2e6;">
                                          <div class="timetable-cell-block schedule-block"
                                               style="background-color: <?php echo $blockBg; ?>; border-left: 4px solid <?php echo $borderColor; ?>;"
                                               data-id="<?php echo $cls['id']; ?>"
                                               data-teacher="<?php echo $cls['teacher_id']; ?>"
                                               data-subject="<?php echo $cls['subject_id']; ?>"
                                               data-class="<?php echo $cls['class_id']; ?>"
                                               data-classroom="<?php echo $cls['classroom_id']; ?>"
                                               data-day="<?php echo e($cls['day_of_week']); ?>"
                                               data-start="<?php echo date('H:i', strtotime($cls['start_time'])); ?>"
                                               data-end="<?php echo date('H:i', strtotime($cls['end_time'])); ?>"
                                               data-year="<?php echo e($cls['academic_year']); ?>"
                                               data-semester="<?php echo e($cls['semester']); ?>"
                                               data-bs-toggle="modal" data-bs-target="#editScheduleModal"
                                               title="<?php echo e($cls['subject_name']); ?>">
                                              <div class="block-code"><?php echo e($cls['subject_code']); ?></div>
                                              <div class="block-sub">
                                                  <i class="fas fa-user-circle"></i>
                                                  <?php echo $filter_type == 'classroom' ? e($cls['first_name']) : 'ห้อง '.e($cls['room_number']); ?>
                                              </div>
                                              <div class="block-time">
                                                  <?php echo substr($cls['start_time'],0,5); ?>–<?php echo substr($cls['end_time'],0,5); ?>
                                              </div>
                                          </div>
                                      </td>
                                  <?php else: ?>
                                      <td class="empty-slot"></td>
                                  <?php endif; ?>
                              <?php endforeach; ?>
                          </tr>
                      <?php endforeach; ?>
                  </tbody>
              </table>
          </div>
      </div>
  </div>


  <!-- List View Tab -->
  <div class="tab-pane fade <?php echo !empty($search) ? 'show active' : ''; ?>" id="list-view" role="tabpanel" aria-labelledby="list-tab">
      <!-- Data Table Card -->
      <div class="card border-0 shadow-sm" style="border-radius: 12px; border: 1px solid var(--border-color) !important;">
          <div class="card-header bg-white border-0 py-3 d-flex justify-content-between align-items-center" style="border-radius: 12px 12px 0 0;">
              <h5 class="mb-0 fw-bold">รายการตารางสอนทั้งหมด</h5>
              <form method="GET" class="d-flex" style="max-width: 350px;">
                  <div class="input-group input-group-sm">
                      <input type="hidden" name="filter_type" value="<?php echo e($filter_type); ?>">
                      <input type="hidden" name="filter_id" value="<?php echo e($filter_id); ?>">
                      <input type="text" name="search" class="form-control" placeholder="ค้นหาครู, วิชา, ชั้นเรียน..." value="<?php echo e($search); ?>">
                      <button class="btn btn-outline-secondary" type="submit"><i class="fas fa-search"></i></button>
                      <?php if ($search): ?>
                          <a href="schedules.php" class="btn btn-outline-danger"><i class="fas fa-times"></i></a>
                      <?php endif; ?>
                  </div>
              </form>
          </div>
          
          <div class="card-body p-0">
              <div class="table-responsive">
                  <table class="table table-hover align-middle mb-0 datatable">
                      <thead class="bg-light">
                          <tr>
                              <th class="ps-4">วันและเวลา</th>
                              <th>วิชา</th>
                              <th>ชั้นเรียน</th>
                              <th>ห้องเรียน</th>
                              <th>ครูผู้สอน</th>
                              <th>ภาคเรียน</th>
                              <th class="pe-4 text-end" width="12%">จัดการ</th>
                          </tr>
                      </thead>
                      <tbody>
                          <?php if (count($schedules) > 0): ?>
                              <?php foreach($schedules as $s): ?>
                                  <tr>
                                      <td class="ps-4">
                                          <?php $days_th = ['Monday'=>'จันทร์', 'Tuesday'=>'อังคาร', 'Wednesday'=>'พุธ', 'Thursday'=>'พฤหัสบดี', 'Friday'=>'ศุกร์', 'Saturday'=>'เสาร์', 'Sunday'=>'อาทิตย์']; ?>
                                          <h6 class="mb-0 fw-semibold text-dark"><?php echo e($days_th[$s['day_of_week']] ?? $s['day_of_week']); ?></h6>
                                          <small class="text-muted"><i class="far fa-clock"></i> <?php echo date('H:i', strtotime($s['start_time'])); ?> - <?php echo date('H:i', strtotime($s['end_time'])); ?></small>
                                      </td>
                                      <td>
                                          <span class="fw-semibold text-primary"><?php echo e($s['subject_name']); ?></span>
                                          <br><small class="text-muted"><?php echo e($s['subject_code']); ?></small>
                                      </td>
                                      <td>
                                          <span class="badge bg-info text-dark bg-opacity-10 border border-info"><i class="fas fa-layer-group"></i> <?php echo e($s['class_abbr']); ?></span>
                                      </td>
                                      <td>
                                          <span class="text-muted"><i class="fas fa-door-open"></i> <?php echo e($s['room_number']); ?></span>
                                      </td>
                                      <td>
                                          <span class="text-dark"><i class="fas fa-chalkboard-teacher text-primary"></i> <?php echo e($s['first_name'] . ' ' . $s['last_name']); ?></span>
                                      </td>
                                      <td>
                                          <span class="text-muted"><?php echo e($s['semester']); ?>/<?php echo e($s['academic_year']); ?></span>
                                      </td>
                                      <td class="pe-4 text-end">
                                          <button class="btn btn-sm btn-outline-primary btn-edit me-1" 
                                                  data-id="<?php echo $s['id']; ?>"
                                                  data-teacher="<?php echo $s['teacher_id']; ?>"
                                                  data-subject="<?php echo $s['subject_id']; ?>"
                                                  data-class="<?php echo $s['class_id']; ?>"
                                                  data-classroom="<?php echo $s['classroom_id']; ?>"
                                                  data-day="<?php echo e($s['day_of_week']); ?>"
                                                  data-start="<?php echo date('H:i', strtotime($s['start_time'])); ?>"
                                                  data-end="<?php echo date('H:i', strtotime($s['end_time'])); ?>"
                                                  data-year="<?php echo e($s['academic_year']); ?>"
                                                  data-semester="<?php echo e($s['semester']); ?>"
                                                  data-bs-toggle="modal" data-bs-target="#editScheduleModal">
                                              <i class="fas fa-edit"></i>
                                          </button>
                                          
                                          <button type="button" class="btn btn-sm btn-outline-danger btn-delete" 
                                                  data-id="<?php echo $s['id']; ?>"
                                                  data-name="<?php echo e($s['subject_name'] . ' (' . $s['day_of_week'] . ')'); ?>"
                                                  data-bs-toggle="modal" data-bs-target="#deleteScheduleModal">
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
              <small class="text-muted">รวมทั้งหมด: <?php echo count($schedules); ?> รายการ</small>
          </div>
      </div>
  </div>
</div>

<!-- ==========================================
     MODALS 
     ========================================== -->

<!-- ADD MODAL -->
<div class="modal fade" id="addScheduleModal" tabindex="-1" aria-labelledby="addScheduleModalLabel" aria-hidden="true">
  <div class="modal-dialog modal-lg">
    <div class="modal-content border-0 shadow">
      <div class="modal-header bg-primary text-white">
        <h5 class="modal-title" id="addScheduleModalLabel"><i class="far fa-calendar-plus me-2"></i> เพิ่มตารางสอนใหม่</h5>
        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>
      <form method="POST" action="schedules.php">
          <input type="hidden" name="csrf_token" value="<?php echo e($csrf_token); ?>">
          <input type="hidden" name="action" value="create">
          
          <div class="modal-body">
            <div class="row">
                <div class="col-md-6 mb-3">
                    <label class="form-label text-muted fw-semibold small">วิชา <span class="text-danger">*</span></label>
                    <select class="form-select" name="subject_id" required>
                        <option value="" disabled selected>เลือกวิชา</option>
                        <?php foreach($subjects as $sub): ?>
                            <option value="<?php echo $sub['id']; ?>"><?php echo e($sub['subject_code'] . ' - ' . $sub['subject_name']); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-6 mb-3">
                    <label class="form-label text-muted fw-semibold small">ครูผู้สอน <span class="text-danger">*</span></label>
                    <select class="form-select" name="teacher_id" required>
                        <option value="" disabled selected>เลือกครูผู้สอน</option>
                        <?php foreach($teachers as $t): ?>
                            <option value="<?php echo $t['id']; ?>"><?php echo e($t['first_name'] . ' ' . $t['last_name']); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>
            
            <div class="row">
                <div class="col-md-6 mb-3">
                    <label class="form-label text-muted fw-semibold small">ระดับชั้นเรียน <span class="text-danger">*</span></label>
                    <select class="form-select" name="class_id" required>
                        <option value="" disabled selected>เลือกชั้นเรียน</option>
                        <?php foreach($classes as $cls): ?>
                            <option value="<?php echo $cls['id']; ?>"><?php echo e($cls['class_name'] . ' (' . $cls['class_abbr'] . ')'); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-6 mb-3">
                    <label class="form-label text-muted fw-semibold small">ห้องเรียน <span class="text-danger">*</span></label>
                    <select class="form-select" name="classroom_id" required>
                        <option value="" disabled selected>เลือกห้องเรียน</option>
                        <?php foreach($classrooms as $rm): ?>
                            <option value="<?php echo $rm['id']; ?>">ห้อง: <?php echo e($rm['room_number']); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>
            
            <div class="row">
                <div class="col-md-4 mb-3">
                    <label class="form-label text-muted fw-semibold small">วันในสัปดาห์ <span class="text-danger">*</span></label>
                    <select class="form-select" name="day_of_week" required>
                        <option value="Monday">จันทร์</option>
                        <option value="Tuesday">อังคาร</option>
                        <option value="Wednesday">พุธ</option>
                        <option value="Thursday">พฤหัสบดี</option>
                        <option value="Friday">ศุกร์</option>
                        <option value="Saturday">เสาร์</option>
                        <option value="Sunday">อาทิตย์</option>
                    </select>
                </div>
                <div class="col-md-4 mb-3">
                    <label class="form-label text-muted fw-semibold small">เวลาเริ่ม <span class="text-danger">*</span></label>
                    <input type="time" class="form-control" name="start_time" required>
                </div>
                <div class="col-md-4 mb-3">
                    <label class="form-label text-muted fw-semibold small">เวลาสิ้นสุด <span class="text-danger">*</span></label>
                    <input type="time" class="form-control" name="end_time" required>
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
                    </select>
                </div>
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
<div class="modal fade" id="editScheduleModal" tabindex="-1" aria-labelledby="editScheduleModalLabel" aria-hidden="true">
  <div class="modal-dialog modal-lg">
    <div class="modal-content border-0 shadow">
      <div class="modal-header bg-light">
        <h5 class="modal-title text-dark" id="editScheduleModalLabel"><i class="fas fa-edit me-2 text-primary"></i> แก้ไขตารางสอน</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>
      <form method="POST" action="schedules.php">
          <input type="hidden" name="csrf_token" value="<?php echo e($csrf_token); ?>">
          <input type="hidden" name="action" value="update">
          <input type="hidden" name="schedule_id" id="edit_schedule_id">
          
          <div class="modal-body">
            <div class="row">
                <div class="col-md-6 mb-3">
                    <label class="form-label text-muted fw-semibold small">วิชา <span class="text-danger">*</span></label>
                    <select class="form-select" id="edit_subject_id" name="subject_id" required>
                        <?php foreach($subjects as $sub): ?>
                            <option value="<?php echo $sub['id']; ?>"><?php echo e($sub['subject_code'] . ' - ' . $sub['subject_name']); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-6 mb-3">
                    <label class="form-label text-muted fw-semibold small">ครูผู้สอน <span class="text-danger">*</span></label>
                    <select class="form-select" id="edit_teacher_id" name="teacher_id" required>
                        <?php foreach($teachers as $t): ?>
                            <option value="<?php echo $t['id']; ?>"><?php echo e($t['first_name'] . ' ' . $t['last_name']); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>
            
            <div class="row">
                <div class="col-md-6 mb-3">
                    <label class="form-label text-muted fw-semibold small">ระดับชั้นเรียน <span class="text-danger">*</span></label>
                    <select class="form-select" id="edit_class_id" name="class_id" required>
                        <?php foreach($classes as $cls): ?>
                            <option value="<?php echo $cls['id']; ?>"><?php echo e($cls['class_name'] . ' (' . $cls['class_abbr'] . ')'); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-6 mb-3">
                    <label class="form-label text-muted fw-semibold small">ห้องเรียน <span class="text-danger">*</span></label>
                    <select class="form-select" id="edit_classroom_id" name="classroom_id" required>
                        <?php foreach($classrooms as $rm): ?>
                            <option value="<?php echo $rm['id']; ?>">ห้อง: <?php echo e($rm['room_number']); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>
            
            <div class="row">
                <div class="col-md-4 mb-3">
                    <label class="form-label text-muted fw-semibold small">วันในสัปดาห์ <span class="text-danger">*</span></label>
                    <select class="form-select" id="edit_day_of_week" name="day_of_week" required>
                        <option value="Monday">จันทร์</option>
                        <option value="Tuesday">อังคาร</option>
                        <option value="Wednesday">พุธ</option>
                        <option value="Thursday">พฤหัสบดี</option>
                        <option value="Friday">ศุกร์</option>
                        <option value="Saturday">เสาร์</option>
                        <option value="Sunday">อาทิตย์</option>
                    </select>
                </div>
                <div class="col-md-4 mb-3">
                    <label class="form-label text-muted fw-semibold small">เวลาเริ่ม <span class="text-danger">*</span></label>
                    <input type="time" class="form-control" id="edit_start_time" name="start_time" required>
                </div>
                <div class="col-md-4 mb-3">
                    <label class="form-label text-muted fw-semibold small">เวลาสิ้นสุด <span class="text-danger">*</span></label>
                    <input type="time" class="form-control" id="edit_end_time" name="end_time" required>
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
                    </select>
                </div>
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
<div class="modal fade" id="deleteScheduleModal" tabindex="-1" aria-labelledby="deleteScheduleModalLabel" aria-hidden="true">
  <div class="modal-dialog modal-sm modal-dialog-centered">
    <div class="modal-content border-0 shadow">
      <div class="modal-header bg-danger text-white">
        <h5 class="modal-title" id="deleteScheduleModalLabel"><i class="fas fa-exclamation-triangle me-2"></i> ยืนยันการลบ</h5>
        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>
      <form method="POST" action="schedules.php">
          <input type="hidden" name="csrf_token" value="<?php echo e($csrf_token); ?>">
          <input type="hidden" name="action" value="delete">
          <input type="hidden" name="schedule_id" id="delete_schedule_id">
          
          <div class="modal-body text-center p-4">
            <i class="fas fa-trash-alt text-danger mb-3" style="font-size: 3rem;"></i>
            <p class="mb-1">คุณแน่ใจหรือไม่ว่าต้องการลบข้อมูลตารางสอนนี้?</p>
            <strong id="delete_schedule_name" class="text-dark d-block mb-3"></strong>
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
    const editButtons = document.querySelectorAll('.btn-edit, .schedule-block');
    editButtons.forEach(button => {
        button.addEventListener('click', function() {
            document.getElementById('edit_schedule_id').value = this.getAttribute('data-id');
            document.getElementById('edit_subject_id').value = this.getAttribute('data-subject');
            document.getElementById('edit_teacher_id').value = this.getAttribute('data-teacher');
            document.getElementById('edit_class_id').value = this.getAttribute('data-class');
            document.getElementById('edit_classroom_id').value = this.getAttribute('data-classroom');
            document.getElementById('edit_day_of_week').value = this.getAttribute('data-day');
            document.getElementById('edit_start_time').value = this.getAttribute('data-start');
            document.getElementById('edit_end_time').value = this.getAttribute('data-end');
            document.getElementById('edit_academic_year').value = this.getAttribute('data-year');
            document.getElementById('edit_semester').value = this.getAttribute('data-semester');
        });
    });
    
    // Delete Modal Fill
    const deleteButtons = document.querySelectorAll('.btn-delete');
    deleteButtons.forEach(button => {
        button.addEventListener('click', function() {
            document.getElementById('delete_schedule_id').value = this.getAttribute('data-id');
            document.getElementById('delete_schedule_name').textContent = this.getAttribute('data-name');
        });
    });
});
</script>
