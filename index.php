<?php
require_once 'includes/db.php';
require_once 'includes/auth.php';

// Protect this route
requireLogin();

// Initial page load stats (so the page renders instantly on first load)
try {
    $today = date('Y-m-d');

    $totalTeachers  = (int) $pdo->query("SELECT COUNT(*) FROM teachers")->fetchColumn();
    $totalStudents  = (int) $pdo->query("SELECT COUNT(*) FROM students")->fetchColumn();
    $totalClasses   = (int) $pdo->query("SELECT COUNT(*) FROM classes")->fetchColumn();
    $totalSubjects  = (int) $pdo->query("SELECT COUNT(*) FROM subjects")->fetchColumn();

    $stTotal = $pdo->prepare("SELECT COUNT(*) FROM attendance WHERE attendance_date = ?");
    $stTotal->execute([$today]);
    $totalToday = (int) $stTotal->fetchColumn();

    $stPresent = $pdo->prepare("SELECT COUNT(*) FROM attendance WHERE attendance_date = ? AND status = 'present'");
    $stPresent->execute([$today]);
    $presentToday = (int) $stPresent->fetchColumn();

    $attendanceRate = $totalToday > 0 ? round(($presentToday / $totalToday) * 100, 1) . '%' : 'ไม่มีข้อมูล';

    // Recent Activities
    $stmtRecent = $pdo->query("
        SELECT a.status, a.created_at,
               CONCAT(s.first_name, ' ', s.last_name) as student_name,
               sub.subject_name
        FROM attendance a
        LEFT JOIN students s ON a.student_id = s.id
        LEFT JOIN subjects sub ON a.subject_id = sub.id
        ORDER BY a.created_at DESC LIMIT 5
    ");
    $recentActivities = $stmtRecent->fetchAll();

    // Chart Data
    $chartLabels = []; $chartDataPresent = []; $chartDataAbsent = [];
    for ($i = 6; $i >= 0; $i--) {
        $d = date('Y-m-d', strtotime("-$i days"));
        $chartLabels[] = date('d/m', strtotime("-$i days"));
        $sp = $pdo->prepare("SELECT COUNT(*) FROM attendance WHERE attendance_date = ? AND status = 'present'"); $sp->execute([$d]);
        $sa = $pdo->prepare("SELECT COUNT(*) FROM attendance WHERE attendance_date = ? AND status IN ('absent','late')"); $sa->execute([$d]);
        $chartDataPresent[] = (int) $sp->fetchColumn();
        $chartDataAbsent[]  = (int) $sa->fetchColumn();
    }

    $stmtGrades = $pdo->query("SELECT grade_letter, COUNT(*) as cnt FROM grades WHERE grade_letter IS NOT NULL AND grade_letter != '' GROUP BY grade_letter ORDER BY grade_letter");
    $gradeDist  = $stmtGrades->fetchAll();
    $gradeLabels = array_column($gradeDist, 'grade_letter');
    $gradeCounts = array_map('intval', array_column($gradeDist, 'cnt'));

} catch (PDOException $e) {
    $totalTeachers = $totalStudents = $totalClasses = $totalSubjects = 0;
    $attendanceRate = 'ไม่มีข้อมูล';
    $recentActivities = [];
    $chartLabels = $chartDataPresent = $chartDataAbsent = $gradeLabels = $gradeCounts = [];
}

include 'includes/header.php';
?>

<style>
/* ===================== Real-Time Dashboard Styles ===================== */
.rt-badge {
    display: inline-flex;
    align-items: center;
    gap: 6px;
    background: rgba(16, 185, 129, 0.12);
    border: 1px solid rgba(16, 185, 129, 0.4);
    color: #059669;
    padding: 4px 12px;
    border-radius: 999px;
    font-size: 0.78rem;
    font-weight: 600;
    letter-spacing: 0.03em;
}
.rt-badge .pulse-dot {
    width: 8px; height: 8px;
    background: #10b981;
    border-radius: 50%;
    animation: pulse-anim 1.5s infinite;
}
@keyframes pulse-anim {
    0%, 100% { opacity: 1; transform: scale(1); }
    50%       { opacity: 0.4; transform: scale(0.7); }
}
.stat-card {
    position: relative;
    overflow: hidden;
    transition: transform 0.2s, box-shadow 0.2s;
}
.stat-card:hover { transform: translateY(-3px); box-shadow: 0 8px 24px rgba(0,0,0,0.10); }
.stat-card .stat-details h3 {
    font-size: 2.2rem;
    font-weight: 700;
    transition: color 0.3s;
}
.stat-updating { color: #06b6d4 !important; }
#last-updated-badge {
    font-size: 0.75rem;
    color: #6c757d;
}
.activity-row-new {
    animation: highlight-row 1.5s ease;
}
@keyframes highlight-row {
    from { background: rgba(16,185,129,0.15); }
    to   { background: transparent; }
}
.progress-attendance {
    height: 6px;
    border-radius: 999px;
    background: #e2e8f0;
    margin-top: 6px;
    overflow: hidden;
}
.progress-attendance-bar {
    height: 100%;
    background: linear-gradient(90deg, #10b981, #059669);
    border-radius: 999px;
    transition: width 0.8s ease;
}
</style>

<!-- Breadcrumb -->
<nav aria-label="breadcrumb">
    <ol class="breadcrumb">
        <li class="breadcrumb-item"><a href="#">หน้าหลัก</a></li>
        <li class="breadcrumb-item active" aria-current="page">แดชบอร์ด</li>
    </ol>
</nav>

<div class="d-flex justify-content-between align-items-center mb-4 flex-wrap gap-2">
    <h2 class="mb-0 fw-bold text-dark">ยินดีต้อนรับสู่ แดชบอร์ด SchoolAI</h2>
    <div class="d-flex align-items-center gap-3">
        <span id="last-updated-badge">อัปเดตล่าสุด: –</span>
        <span class="rt-badge"><span class="pulse-dot"></span>ข้อมูลเรียลไทม์</span>
    </div>
</div>

<!-- Summary Widgets / Stat Cards -->
<div class="row g-4 mb-4">
    <!-- Total Teachers -->
    <div class="col-12 col-sm-6 col-xl-3">
        <div class="stat-card">
            <div class="stat-icon primary">
                <i class="fas fa-chalkboard-teacher"></i>
            </div>
            <div class="stat-details">
                <h3 id="stat-teachers"><?php echo number_format($totalTeachers); ?></h3>
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
                <h3 id="stat-students"><?php echo number_format($totalStudents); ?></h3>
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
                <h3 id="stat-classes"><?php echo number_format($totalClasses); ?></h3>
                <p>ระดับชั้นเรียนทั้งหมด</p>
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
                <h3 id="stat-attendance"><?php echo $attendanceRate; ?></h3>
                <p>อัตราเข้าเรียนวันนี้</p>
                <div class="progress-attendance">
                    <div class="progress-attendance-bar" id="stat-attendance-bar" style="width: 0%;"></div>
                </div>
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
                <span class="badge bg-primary bg-opacity-10 text-primary small">อัปเดตอัตโนมัติ</span>
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
                <span class="badge bg-success bg-opacity-10 text-success small">เรียลไทม์</span>
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
                <a href="attendance.php" class="btn btn-sm btn-light">ดูทั้งหมด</a>
            </div>
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-hover align-middle mb-0">
                        <thead class="bg-light">
                            <tr>
                                <th class="ps-4">กิจกรรม</th>
                                <th>ระบบงาน</th>
                                <th>เวลา</th>
                                <th class="pe-4 text-end">จัดการ</th>
                            </tr>
                        </thead>
                        <tbody id="activity-feed">
                            <?php if (empty($recentActivities)): ?>
                                <tr id="no-activity-row">
                                    <td colspan="4" class="text-center py-4 text-muted">ไม่มีประวัติการทำรายการล่าสุด</td>
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
                                                    <h6 class="mb-0"><?php echo e($act['student_name']); ?></h6>
                                                    <small class="text-muted">วิชา: <?php echo e($act['subject_name']); ?> | <?php echo e(ucfirst($act['status'])); ?></small>
                                                </div>
                                            </div>
                                        </td>
                                        <td><span class="badge bg-secondary">เช็คชื่อ</span></td>
                                        <td><small class="text-muted">–</small></td>
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

<?php include 'includes/footer.php'; ?>

<!-- Inject Chart.js logic + Real-Time Polling -->
<script>
document.addEventListener("DOMContentLoaded", function() {

    // ===================== Initial Charts =====================
    const colors = {
        present: 'rgba(16, 185, 129, 0.85)',
        absent:  'rgba(239, 68, 68, 0.85)',
        grades:  ['#10B981','#3B82F6','#FCD34D','#F97316','#EF4444','#9CA3AF','#A78BFA']
    };

    let initLabels   = <?php echo json_encode($chartLabels); ?>;
    let initPresent  = <?php echo json_encode($chartDataPresent); ?>;
    let initAbsent   = <?php echo json_encode($chartDataAbsent); ?>;
    let initGLabels  = <?php echo json_encode($gradeLabels); ?>;
    let initGCounts  = <?php echo json_encode($gradeCounts); ?>;

    // Attendance chart
    let attendanceChart = null;
    const ctxA = document.getElementById('attendanceChart');
    if (ctxA) {
        attendanceChart = new Chart(ctxA, {
            type: 'bar',
            data: {
                labels: initLabels,
                datasets: [
                    { label: 'เข้าเรียน', data: initPresent, backgroundColor: colors.present, borderRadius: 4 },
                    { label: 'ขาด/สาย',   data: initAbsent,  backgroundColor: colors.absent,  borderRadius: 4 }
                ]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                animation: { duration: 600 },
                scales: { y: { beginAtZero: true, suggestedMax: 10 } }
            }
        });
    }

    // Grade chart
    let gradeChart = null;
    const ctxG = document.getElementById('gradeChart');
    if (ctxG) {
        let gLabels = initGLabels.length ? initGLabels : ['ยังไม่มีข้อมูล'];
        let gCounts = initGCounts.length ? initGCounts : [1];
        gradeChart = new Chart(ctxG, {
            type: 'doughnut',
            data: {
                labels: gLabels,
                datasets: [{ data: gCounts, backgroundColor: colors.grades, borderWidth: 0 }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                animation: { duration: 600 },
                plugins: { legend: { position: 'bottom' } },
                cutout: '70%'
            }
        });
    }

    // ===================== Real-Time Polling =====================
    const POLL_INTERVAL = 10000; // 10 seconds

    function animateCount(el, target, isText) {
        if (isText) {
            el.classList.add('stat-updating');
            setTimeout(() => {
                el.textContent = target;
                el.classList.remove('stat-updating');
            }, 300);
            return;
        }
        const start = parseInt(el.textContent.replace(/,/g,'')) || 0;
        const end   = parseInt(target) || 0;
        if (start === end) return;
        el.classList.add('stat-updating');
        const duration = 600;
        const startTime = performance.now();
        function step(now) {
            const t = Math.min((now - startTime) / duration, 1);
            const val = Math.round(start + (end - start) * t);
            el.textContent = val.toLocaleString('th-TH');
            if (t < 1) requestAnimationFrame(step);
            else el.classList.remove('stat-updating');
        }
        requestAnimationFrame(step);
    }

    function renderActivities(activities) {
        const tbody = document.getElementById('activity-feed');
        if (!activities.length) {
            tbody.innerHTML = '<tr><td colspan="4" class="text-center py-4 text-muted">ไม่มีประวัติการทำรายการล่าสุด</td></tr>';
            return;
        }
        const statusIcon = { present: 'fa-check-circle text-success', absent: 'fa-times-circle text-danger', late: 'fa-clock text-warning' };
        tbody.innerHTML = activities.map(a => `
            <tr class="activity-row-new">
                <td class="ps-4">
                    <div class="d-flex align-items-center">
                        <div class="bg-success bg-opacity-10 text-success rounded-circle p-2 me-3">
                            <i class="fas fa-clipboard-check fa-fw"></i>
                        </div>
                        <div>
                            <h6 class="mb-0">${a.student_name || '–'}</h6>
                            <small class="text-muted">วิชา: ${a.subject_name || '–'} | ${a.status}</small>
                        </div>
                    </div>
                </td>
                <td><span class="badge bg-secondary">เช็คชื่อ</span></td>
                <td><small class="text-muted">${a.time_ago}</small></td>
                <td class="pe-4 text-end"><a href="attendance.php" class="btn btn-sm btn-outline-primary">ดู</a></td>
            </tr>
        `).join('');
    }

    function fetchStats() {
        fetch('api/dashboard_stats.php', { credentials: 'same-origin' })
            .then(r => r.json())
            .then(data => {
                if (!data.success) return;

                // Update timestamp badge
                document.getElementById('last-updated-badge').textContent = 'อัปเดตล่าสุด: ' + data.timestamp;

                // Animate stat cards
                animateCount(document.getElementById('stat-teachers'), data.total_teachers, false);
                animateCount(document.getElementById('stat-students'), data.total_students, false);
                animateCount(document.getElementById('stat-classes'),  data.total_classes,  false);
                animateCount(document.getElementById('stat-attendance'), data.attendance_rate, true);

                // Progress bar for attendance
                const bar = document.getElementById('stat-attendance-bar');
                if (bar) bar.style.width = (data.attendance_num || 0) + '%';

                // Update attendance chart
                if (attendanceChart && data.trend) {
                    attendanceChart.data.labels = data.trend.map(t => t.label);
                    attendanceChart.data.datasets[0].data = data.trend.map(t => t.present);
                    attendanceChart.data.datasets[1].data = data.trend.map(t => t.absent);
                    attendanceChart.update('active');
                }

                // Update grade chart
                if (gradeChart && data.grade_labels) {
                    const gl = data.grade_labels.length ? data.grade_labels : ['ยังไม่มีข้อมูล'];
                    const gc = data.grade_counts.length ? data.grade_counts : [1];
                    gradeChart.data.labels = gl;
                    gradeChart.data.datasets[0].data = gc;
                    gradeChart.update('active');
                }

                // Update activity feed
                renderActivities(data.activities || []);
            })
            .catch(err => {
                console.warn('[Dashboard] Polling error:', err);
            });
    }

    // Poll on load (after 2s delay) and then every 10s
    setTimeout(fetchStats, 2000);
    setInterval(fetchStats, POLL_INTERVAL);

    // Set initial progress bar
    (function() {
        const bar = document.getElementById('stat-attendance-bar');
        const text = document.getElementById('stat-attendance')?.textContent || '';
        const match = text.match(/([\d.]+)%/);
        if (bar && match) bar.style.width = parseFloat(match[1]) + '%';
    })();
});
</script>
