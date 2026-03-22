<?php
/**
 * api/dashboard_stats.php
 * Returns real-time dashboard statistics as JSON.
 * Protected: requires active session.
 */
require_once '../includes/db.php';
require_once '../includes/auth.php';

// Only allow logged-in users via AJAX
if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['error' => 'Unauthorized']);
    exit();
}

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store, no-cache, must-revalidate');

try {
    $today = date('Y-m-d');
    $now   = date('H:i:s');

    // 1. Counts
    $totalTeachers  = (int) $pdo->query("SELECT COUNT(*) FROM teachers")->fetchColumn();
    $totalStudents  = (int) $pdo->query("SELECT COUNT(*) FROM students")->fetchColumn();
    $totalClasses   = (int) $pdo->query("SELECT COUNT(*) FROM classes")->fetchColumn();
    $totalSubjects  = (int) $pdo->query("SELECT COUNT(*) FROM subjects")->fetchColumn();
    $totalSchedules = (int) $pdo->query("SELECT COUNT(*) FROM schedules")->fetchColumn();

    // 2. Today's attendance rate
    $stTotal = $pdo->prepare("SELECT COUNT(*) FROM attendance WHERE attendance_date = ?");
    $stTotal->execute([$today]);
    $totalToday = (int) $stTotal->fetchColumn();

    $stPresent = $pdo->prepare("SELECT COUNT(*) FROM attendance WHERE attendance_date = ? AND status = 'present'");
    $stPresent->execute([$today]);
    $presentToday = (int) $stPresent->fetchColumn();

    $attendanceRate     = $totalToday > 0 ? round(($presentToday / $totalToday) * 100, 1) : null;
    $attendanceRateStr  = $attendanceRate !== null ? $attendanceRate . '%' : 'ไม่มีข้อมูล';
    $attendanceDisplay  = $attendanceRate !== null ? $attendanceRate : 0; // numeric for progress bar

    // 3. Attendance trend for last 7 days
    $trend = [];
    for ($i = 6; $i >= 0; $i--) {
        $d = date('Y-m-d', strtotime("-$i days"));
        $label = date('d/m', strtotime("-$i days"));
        $sp = $pdo->prepare("SELECT COUNT(*) FROM attendance WHERE attendance_date = ? AND status = 'present'");
        $sp->execute([$d]);
        $sa = $pdo->prepare("SELECT COUNT(*) FROM attendance WHERE attendance_date = ? AND status IN ('absent','late')");
        $sa->execute([$d]);
        $trend[] = [
            'label'   => $label,
            'present' => (int) $sp->fetchColumn(),
            'absent'  => (int) $sa->fetchColumn(),
        ];
    }

    // 4. Grade distribution
    $gradeRows = $pdo->query("SELECT grade_letter, COUNT(*) as cnt FROM grades WHERE grade_letter IS NOT NULL AND grade_letter != '' GROUP BY grade_letter ORDER BY grade_letter")->fetchAll();
    $gradeLabels = array_column($gradeRows, 'grade_letter');
    $gradeCounts = array_map('intval', array_column($gradeRows, 'cnt'));

    // 5. Recent activities (last 5 attendance)
    $stmt = $pdo->query("
        SELECT a.status, a.created_at,
               CONCAT(s.first_name, ' ', s.last_name) AS student_name,
               sub.subject_name
        FROM attendance a
        LEFT JOIN students s ON a.student_id = s.id
        LEFT JOIN subjects sub ON a.subject_id = sub.id
        ORDER BY a.created_at DESC
        LIMIT 5
    ");
    $activities = [];
    foreach ($stmt->fetchAll() as $row) {
        $diff = time() - strtotime($row['created_at']);
        if ($diff < 60)       $timeAgo = 'เพิ่งเกิด';
        elseif ($diff < 3600) $timeAgo = floor($diff/60) . ' นาทีที่แล้ว';
        elseif ($diff < 86400)$timeAgo = floor($diff/3600) . ' ชั่วโมงที่แล้ว';
        else                   $timeAgo = date('d/m/Y', strtotime($row['created_at']));

        $activities[] = [
            'student_name' => $row['student_name'],
            'subject_name' => $row['subject_name'],
            'status'       => $row['status'],
            'time_ago'     => $timeAgo,
        ];
    }

    echo json_encode([
        'success'          => true,
        'timestamp'        => date('H:i:s'),
        'total_teachers'   => $totalTeachers,
        'total_students'   => $totalStudents,
        'total_classes'    => $totalClasses,
        'total_subjects'   => $totalSubjects,
        'total_schedules'  => $totalSchedules,
        'attendance_rate'  => $attendanceRateStr,
        'attendance_num'   => $attendanceDisplay,
        'attendance_today' => $totalToday,
        'present_today'    => $presentToday,
        'trend'            => $trend,
        'grade_labels'     => $gradeLabels,
        'grade_counts'     => $gradeCounts,
        'activities'       => $activities,
    ]);

} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'Database error']);
}
?>
