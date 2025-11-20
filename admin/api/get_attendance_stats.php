<?php
require_once '../../config/database.php';
require_once '../../includes/auth.php';

// Start session if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Verify valid admin session
if (!isset($_SESSION['user_id']) || !isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
    http_response_code(401);
    die(json_encode(['error' => 'Unauthorized access']));
}

$timeframe = $_GET['timeframe'] ?? 'month';

// Calculate date range based on timeframe
$endDate = date('Y-m-d');
switch ($timeframe) {
    case 'week':
        $startDate = date('Y-m-d', strtotime('-7 days'));
        break;
    case 'month':
        $startDate = date('Y-m-d', strtotime('-30 days'));
        break;
    case 'year':
        $startDate = date('Y-m-d', strtotime('-365 days'));
        break;
    default:
        $startDate = date('Y-m-d', strtotime('-30 days'));
}

// Get attendance counts by status
$statusCounts = [
    'Present' => 0,
    'Late' => 0,
    'Absent' => 0
];

$query = "SELECT status, COUNT(*) as count FROM attendance 
          WHERE attendance_date BETWEEN ? AND ?
          GROUP BY status";
$stmt = $conn->prepare($query);
$stmt->bind_param('ss', $startDate, $endDate);
$stmt->execute();
$result = $stmt->get_result();

while ($row = $result->fetch_assoc()) {
    if (isset($statusCounts[$row['status']])) {
        $statusCounts[$row['status']] = (int)$row['count'];
    }
}

// Get attendance trend data
$trendQuery = "SELECT DATE(attendance_date) as date, COUNT(*) as count 
               FROM attendance 
               WHERE attendance_date BETWEEN ? AND ?
               GROUP BY DATE(attendance_date)
               ORDER BY date ASC";
$stmt = $conn->prepare($trendQuery);
$stmt->bind_param('ss', $startDate, $endDate);
$stmt->execute();
$trendResult = $stmt->get_result();

$attendanceTrend = [];
$dateLabels = [];
$dateCounts = [];

while ($row = $trendResult->fetch_assoc()) {
    $dateLabels[] = $row['date'];
    $dateCounts[] = (int)$row['count'];
}

// Get top sections with best attendance
$sectionQuery = "SELECT s.section_name, 
                 COUNT(*) as total,
                 SUM(CASE WHEN a.status = 'Present' THEN 1 ELSE 0 END) as present_count,
                 ROUND((SUM(CASE WHEN a.status = 'Present' THEN 1 ELSE 0 END) / COUNT(*)) * 100) as attendance_rate
                 FROM attendance a
                 JOIN students st ON a.student_id = st.student_id
                 JOIN sections s ON st.section_id = s.section_id
                 WHERE a.attendance_date BETWEEN ? AND ?
                 GROUP BY s.section_id
                 ORDER BY attendance_rate DESC
                 LIMIT 5";
$stmt = $conn->prepare($sectionQuery);
$stmt->bind_param('ss', $startDate, $endDate);
$stmt->execute();
$sectionResult = $stmt->get_result();

$topSections = [];
while ($row = $sectionResult->fetch_assoc()) {
    $topSections[] = $row;
}

header('Content-Type: application/json');
echo json_encode([
    'status_counts' => $statusCounts,
    'attendance_trend' => [
        'labels' => $dateLabels,
        'data' => $dateCounts
    ],
    'top_sections' => $topSections
]);
