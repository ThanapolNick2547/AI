<?php
require_once 'includes/db.php';
require_once 'includes/auth.php';

// Protect this route
requireLogin();

// Fetch Dynamic Statistics
try {
    // 1. Total Teachers
    $stmt = $pdo->query("SELECT COUNT(*) FROM teachers");
    $totalTeachers = $stmt->fetchColumn();

    // 2. Total Students
    $stmt = $pdo->query("SELECT COUNT(*) FROM students");
    $totalStudents = $stmt->fetchColumn();

    // 3. Active Classes
    $stmt = $pdo->query("SELECT COUNT(*) FROM classes WHERE status = 'active'"); 
    $totalClasses = $stmt->fetchColumn();

    // 4. Today's Attendance Rate
    $today = date('Y-m-d');
    $stmtTotal = $pdo->prepare("SELECT COUNT(*) FROM attendance WHERE attendance_date = ?");
    $stmtTotal->execute([$today]);
    $totalRecordsToday = $stmtTotal->fetchColumn();
    
    $stmtPresent = $pdo->prepare("SELECT COUNT(*) FROM attendance WHERE attendance_date = ? AND status = 'present'");
    $stmtPresent->execute([$today]);
    $presentToday = $stmtPresent->fetchColumn();
    
    if ($totalRecordsToday > 0) {
        $attendanceRate = round(($presentToday / $totalRecordsToday) * 100, 1) . '%';
    } else {
        $attendanceRate = "ไม่มีข้อมูล"; // No data
    }
    
    // 5. Recent Activities (Latest 5 attendance records)
    $stmtRecent = $pdo->query("
        SELECT a.id, a.attendance_date as event_date, a.created_at as event_time, 
               'เช็คชื่อ' as module, a.status,
               CONCAT(s.first_name, ' ', s.last_name) as primary_detail,
               sub.subject_name as secondary_detail
        FROM attendance a
        LEFT JOIN students s ON a.student_id = s.id
        LEFT JOIN subjects sub ON a.subject_id = sub.id
        ORDER BY a.created_at DESC
        LIMIT 5
    ");
    $recentActivities = $stmtRecent->fetchAll();

    // Chart Data: Attendance for the last 7 days
    $chartLabels = [];
    $chartDataPresent = [];
    $chartDataAbsent = [];
    
    for ($i = 6; $i >= 0; $i--) {
        $targetDate = date('Y-m-d', strtotime("-$i days"));
        $displayDate = date('d/m', strtotime("-$i days"));
        $chartLabels[] = $displayDate;
        
        $stmtP = $pdo->prepare("SELECT COUNT(*) FROM attendance WHERE attendance_date = ? AND status = 'present'");
        $stmtP->execute([$targetDate]);
        $chartDataPresent[] = $stmtP->fetchColumn();
        
        $stmtA = $pdo->prepare("SELECT COUNT(*) FROM attendance WHERE attendance_date = ? AND status IN ('absent', 'late')");
        $stmtA->execute([$targetDate]);
        $chartDataAbsent[] = $stmtA->fetchColumn();
    }
    
    // Chart Data: Grade Distribution (Count of each grade_letter ignoring NA/NULL)
    $stmtGrades = $pdo->query("SELECT grade_letter, COUNT(*) as count FROM grades WHERE grade_letter IS NOT NULL AND grade_letter != '' GROUP BY grade_letter ORDER BY grade_letter ASC");
    $gradeDist = $stmtGrades->fetchAll();
    $gradeLabels = [];
    $gradeCounts = [];
    foreach($gradeDist as $gd) {
        $gradeLabels[] = $gd['grade_letter'];
        $gradeCounts[] = $gd['count'];
    }

} catch (PDOException $e) {
    $totalTeachers = 0;
    $totalStudents = 0;
    $totalClasses = 0;
    $attendanceRate = "0%";
    $recentActivities = [];
    $chartLabels = []; $chartDataPresent = []; $chartDataAbsent = [];
    $gradeLabels = []; $gradeCounts = [];
}

$jsLabels = json_encode($chartLabels);
$jsPresent = json_encode($chartDataPresent);
$jsAbsent = json_encode($chartDataAbsent);
$jsGradeLabels = json_encode($gradeLabels);
$jsGradeCounts = json_encode($gradeCounts);

// Include Header (which includes sidebar internally)
include 'includes/header.php';
?>

<!-- Breadcrumb -->
<nav aria-label="breadcrumb">
    <ol class="breadcrumb">
        <li class="breadcrumb-item"><a href="#">หน้าหลัก</a></li>
        <li class="breadcrumb-item active" aria-current="page">แดชบอร์ด</li>
    </ol>
</nav>

<h2 class="mb-4 fw-bold text-dark">ยินดีต้อนรับสู่ แดชบอร์ด SchoolAI</h2>

<!-- Summary Widgets / Stat Cards -->
<div class="row g-4 mb-4">
    <!-- Total Teachers -->
    <div class="col-12 col-sm-6 col-xl-3">
        <div class="stat-card">
            <div class="stat-icon primary">
                <i class="fas fa-chalkboard-teacher"></i>
            </div>
            <div class="stat-details">
                <h3><?php echo number_format($totalTeachers); ?></h3>
                <p>จำนวนครูทั้งหมด</p>
            </div>
        </div>
    </div>
    
    <!-- Total Students -->
    <div class="col-12 col-sm-6 col-xl-3">
        <div class="stat-card">
            <div class="stat-icon info">
                <i class="fas fa-user-graduate"></i>
            </div>
            <div class="stat-details">
                <h3><?php echo number_format($totalStudents); ?></h3>
                <p>จำนวนนักเรียนรวม</p>
            </div>
        </div>
    </div>

    <!-- Active Classes -->
    <div class="col-12 col-sm-6 col-xl-3">
        <div class="stat-card">
            <div class="stat-icon warning">
                <i class="fas fa-layer-group"></i>
            </div>
            <div class="stat-details">
                <h3><?php echo number_format($totalClasses); ?></h3>
                <p>ระดับชั้นเรียนสร้างแล้ว</p>
            </div>
        </div>
    </div>

    <!-- Today's Attendance Rate -->
    <div class="col-12 col-sm-6 col-xl-3">
        <div class="stat-card">
            <div class="stat-icon success">
                <i class="fas fa-check-circle"></i>
            </div>
            <div class="stat-details">
                <h3><?php echo $attendanceRate; ?></h3>
                <p>อัตราเข้าเรียนวันนี้</p>
            </div>
        </div>
    </div>
</div>

<!-- Charts Section -->
<div class="row g-4">
    <!-- Bar Chart for Attendance -->
    <div class="col-12 col-lg-8">
        <div class="chart-card h-100">
            <div class="d-flex justify-content-between align-items-center mb-3">
                <h5 class="mb-0">ภาพรวมการเข้าเรียนรายสัปดาห์</h5>
                <div class="dropdown">
                    <button class="btn btn-sm btn-outline-secondary dropdown-toggle" type="button" data-bs-toggle="dropdown">
                        สัปดาห์นี้
                    </button>
                    <ul class="dropdown-menu">
                        <li><a class="dropdown-item" href="#">สัปดาห์นี้</a></li>
                        <li><a class="dropdown-item" href="#">สัปดาห์ที่แล้ว</a></li>
                    </ul>
                </div>
            </div>
            <div style="height: 300px;">
                <canvas id="attendanceChart"></canvas>
            </div>
        </div>
    </div>
    
    <!-- Doughnut Chart for Grade Distribution -->
    <div class="col-12 col-lg-4">
        <div class="chart-card h-100">
            <div class="d-flex justify-content-between align-items-center mb-3">
                <h5 class="mb-0">กระจายตัวของผลการเรียน</h5>
                <button class="btn btn-sm btn-outline-secondary">
                    เทอม 1
                </button>
            </div>
            <div style="height: 250px; display: flex; align-items: center; justify-content: center;">
                <canvas id="gradeChart"></canvas>
            </div>
            <div class="text-center mt-3 text-muted small">
                แสดงการกระจายตัวของผลการเรียนรวมในทุกรายวิชาที่เปิดสอน
            </div>
        </div>
    </div>
</div>

<!-- Recent Activities Table -->
<div class="row g-4 mt-1">
    <div class="col-12">
        <div class="card border-0 shadow-sm" style="border-radius: 12px; border: 1px solid var(--border-color) !important;">
            <div class="card-header bg-white border-0 py-3 d-flex justify-content-between align-items-center" style="border-radius: 12px 12px 0 0;">
                <h5 class="mb-0 fw-bold">อัปเดตล่าสุด</h5>
                <a href="#" class="btn btn-sm btn-light">ดูทั้งหมด</a>
            </div>
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-hover align-middle mb-0">
                        <thead class="bg-light">
                            <tr>
                                <th class="ps-4">กิจกรรม</th>
                                <th>ผู้ดำเนินการ</th>
                                <th>ระบบงาน</th>
                                <th>เวลา</th>
                                <th class="pe-4 text-end">จัดการ</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if(empty($recentActivities)): ?>
                                <tr>
                                    <td colspan="5" class="text-center py-4 text-muted">ไม่มีประวัติการทำรายการล่าสุด</td>
                                </tr>
                            <?php else: ?>
                                <?php foreach($recentActivities as $act): ?>
                                    <tr>
                                        <td class="ps-4">
                                            <div class="d-flex align-items-center">
                                                <div class="bg-success bg-opacity-10 text-success rounded-circle p-2 me-3">
                                                    <i class="fas fa-clipboard-check fa-fw"></i>
                                                </div>
                                                <div>
                                                    <h6 class="mb-0"><?php echo e($act['primary_detail']); ?></h6>
                                                    <small class="text-muted">วิชา: <?php echo e($act['secondary_detail']); ?> | สถานะ: <?php echo e(ucfirst($act['status'])); ?></small>
                                                </div>
                                            </div>
                                        </td>
                                        <td>ผู้สอน</td>
                                        <td><span class="badge bg-secondary"><?php echo e($act['module']); ?></span></td>
                                        <td>
                                            <?php 
                                                $eventTime = strtotime($act['event_time']);
                                                $diff = time() - $eventTime;
                                                if ($diff < 60) echo "เพิ่งเกิด";
                                                elseif ($diff < 3600) echo floor($diff/60) . " นาทีที่แล้ว";
                                                elseif ($diff < 86400) echo floor($diff/3600) . " ชั่วโมงที่แล้ว";
                                                else echo date('d/m/Y', $eventTime);
                                            ?>
                                        </td>
                                        <td class="pe-4 text-end"><a href="attendance.php" class="btn btn-sm btn-outline-primary">ดู</a></td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>

<?php

// Include Footer
include 'includes/footer.php';

?>

<!-- Inject Chart.js logic -->
<script>
document.addEventListener("DOMContentLoaded", function() {
    // Attendance Trend Chart
    const ctxAttendance = document.getElementById('attendanceChart');
    if(ctxAttendance) {
        new Chart(ctxAttendance, {
            type: 'bar',
            data: {
                labels: <?php echo $jsLabels; ?>,
                datasets: [
                    {
                        label: 'เข้าเรียน (Present)',
                        data: <?php echo $jsPresent; ?>,
                        backgroundColor: 'rgba(56, 178, 172, 0.8)', // Teal/Success logic
                        borderRadius: 4
                    },
                    {
                        label: 'ขาด/สาย (Absent/Late)',
                        data: <?php echo $jsAbsent; ?>,
                        backgroundColor: 'rgba(239, 68, 68, 0.8)', // Red/Danger logic
                        borderRadius: 4
                    }
                ]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                scales: {
                    y: { beginAtZero: true, suggestedMax: 10 }
                }
            }
        });
    }

    // Grade Distribution Doughnut Chart
    const ctxGrade = document.getElementById('gradeChart');
    if(ctxGrade) {
        const gradeLabels = <?php echo $jsGradeLabels; ?>;
        const gradeCounts = <?php echo $jsGradeCounts; ?>;
        
        let chartData = gradeCounts;
        let chartLabels = gradeLabels;
        
        // If no grades yet, show mockup or empty
        if(chartData.length === 0) {
            chartLabels = ['ยังไม่มีข้อมูล'];
            chartData = [1];
        }

        new Chart(ctxGrade, {
            type: 'doughnut',
            data: {
                labels: chartLabels,
                datasets: [{
                    data: chartData,
                    backgroundColor: [
                        '#10B981', // A - Green
                        '#3B82F6', // B - Blue
                        '#FCD34D', // C - Yellow
                        '#F97316', // D - Orange
                        '#EF4444', // F - Red
                        '#9CA3AF'  // Others
                    ],
                    borderWidth: 0
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: { position: 'bottom' }
                },
                cutout: '70%'
            }
        });
    }
});
</script>
