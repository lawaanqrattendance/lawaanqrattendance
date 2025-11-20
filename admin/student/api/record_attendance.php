<?php
require_once '../../../config/database.php';
require_once '../../../includes/functions.php';
require_once '../../../includes/auth.php';

// Check if user is logged in and is a student
session_start();
if (!isLoggedIn() || getUserRole() !== 'student') {
    http_response_code(403);
    echo json_encode(['error' => 'Unauthorized']);
    exit();
}

// Get POST data
$data = json_decode(file_get_contents('php://input'), true);
$student_id = $_SESSION['student_id'];
$qr_code = $data['qr_code'] ?? '';

// Verify QR code matches student ID
if ($qr_code !== $student_id) {
    echo json_encode(['error' => 'Invalid QR code']);
    exit();
}

// Get current time and date
$current_time = date('H:i:s');
$current_date = date('Y-m-d');
$day_of_week = date('l');

// Find current active schedule
$query = "SELECT s.* FROM schedules s
          JOIN students st ON s.section_id = st.section_id
          WHERE st.student_id = ?
          AND s.day_of_week = ?
          AND ? BETWEEN s.start_time AND s.end_time
          AND s.status = 'Open'
          LIMIT 1";

$stmt = $conn->prepare($query);
$stmt->bind_param("sss", $student_id, $day_of_week, $current_time);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows === 0) {
    echo json_encode(['error' => 'No active schedule found']);
    exit();
}

$schedule = $result->fetch_assoc();

// Check if attendance already recorded
$stmt = $conn->prepare("SELECT * FROM attendance WHERE student_id = ? AND schedule_id = ? AND attendance_date = ?");
$stmt->bind_param("sis", $student_id, $schedule['schedule_id'], $current_date);
$stmt->execute();

if ($stmt->get_result()->num_rows > 0) {
    echo json_encode(['error' => 'Attendance already recorded']);
    exit();
}

// Determine attendance status
$start_time = strtotime($schedule['start_time']);
$current = strtotime($current_time);
$grace_period = 15 * 60; // 15 minutes grace period
$status = ($current <= $start_time + $grace_period) ? 'Present' : 'Late';

// Record attendance
$stmt = $conn->prepare("INSERT INTO attendance (student_id, schedule_id, attendance_date, status) VALUES (?, ?, ?, ?)");
$stmt->bind_param("siss", $student_id, $schedule['schedule_id'], $current_date, $status);

if ($stmt->execute()) {
    echo json_encode(['success' => true, 'status' => $status]);
} else {
    echo json_encode(['error' => 'Failed to record attendance']);
}
?> 