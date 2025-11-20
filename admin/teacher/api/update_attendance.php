<?php
require_once '../../../config/database.php';
require_once '../../../includes/functions.php';
require_once '../../../includes/auth.php';

// Check if user is logged in and is a teacher
session_start();
if (!isLoggedIn() || getUserRole() !== 'teacher') {
    http_response_code(403);
    echo json_encode(['error' => 'Unauthorized']);
    exit();
}

// Validate input
if (!isset($_POST['student_id'], $_POST['schedule_id'], $_POST['attendance_date'], $_POST['status'])) {
    http_response_code(400);
    echo json_encode(['error' => 'Missing required fields']);
    exit();
}

$student_id = cleanInput($_POST['student_id']);
$schedule_id = cleanInput($_POST['schedule_id']);
$attendance_date = cleanInput($_POST['attendance_date']);
$status = cleanInput($_POST['status']);

// Verify the schedule belongs to this teacher
$stmt = $conn->prepare("SELECT teacher_id FROM schedules WHERE schedule_id = ?");
$stmt->bind_param("i", $schedule_id);
$stmt->execute();
$result = $stmt->get_result();
$schedule = $result->fetch_assoc();

if (!$schedule || $schedule['teacher_id'] != $_SESSION['teacher_id']) {
    http_response_code(403);
    echo json_encode(['error' => 'Unauthorized schedule access']);
    exit();
}

// Update or insert attendance record
$stmt = $conn->prepare("INSERT INTO attendance (student_id, schedule_id, attendance_date, status) 
                       VALUES (?, ?, ?, ?) 
                       ON DUPLICATE KEY UPDATE status = ?");
$stmt->bind_param("sisss", $student_id, $schedule_id, $attendance_date, $status, $status);

if ($stmt->execute()) {
    echo json_encode(['success' => true]);
} else {
    http_response_code(500);
    echo json_encode(['error' => 'Failed to update attendance']);
}
?> 