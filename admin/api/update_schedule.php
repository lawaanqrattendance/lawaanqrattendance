<?php
// CORS headers
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: POST, GET, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type");
header("Content-Type: application/json");

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

// Start session
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Include dependencies
require_once '../../includes/config.php';
require_once '../../config/database.php';
require_once '../../includes/functions.php';
require_once '../../includes/auth.php';

// Auth check
$userRole = getUserRole();
if (!isLoggedIn() || ($userRole !== 'admin' && $userRole !== 'teacher')) {
    http_response_code(403);
    echo json_encode(['success' => false, 'error' => 'Unauthorized access']);
    exit();
}

// Input validation
if (!isset($_POST['schedule_id'], $_POST['day_of_week'], $_POST['start_time'], $_POST['end_time'])) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Missing required input']);
    exit();
}

try {
    $schedule_id = cleanInput($_POST['schedule_id']);
    $day_of_week = cleanInput($_POST['day_of_week']);
    $start_time = cleanInput($_POST['start_time']);
    $end_time = cleanInput($_POST['end_time']);

    // Time format check (HH:MM or HH:MM:SS)
    if (!preg_match('/^\d{2}:\d{2}(:\d{2})?$/', $start_time) || !preg_match('/^\d{2}:\d{2}(:\d{2})?$/', $end_time)) {
        throw new Exception('Invalid time format');
    }

    if (strtotime($end_time) <= strtotime($start_time)) {
        throw new Exception('End time must be after start time');
    }

    // Check if schedule exists and get section_id, subject_id, and school_year
    $check_stmt = $conn->prepare("SELECT schedule_id, teacher_id, section_id, subject_id, school_year FROM schedules WHERE schedule_id = ?");
    $check_stmt->bind_param("i", $schedule_id);
    $check_stmt->execute();
    $result = $check_stmt->get_result();

    if ($result->num_rows === 0) {
        throw new Exception('Schedule not found');
    }

    $schedule = $result->fetch_assoc();
    $teacher_id = $schedule['teacher_id'];
    $section_id = $schedule['section_id'];
    $subject_id = $schedule['subject_id'];
    $school_year = $schedule['school_year'];

    // Verify ownership if role is teacher
    if ($userRole === 'teacher') {
        if (!isset($_SESSION['teacher_id'])) {
            $teacher_stmt = $conn->prepare("SELECT reference_id FROM users WHERE user_id = ? AND role = 'teacher'");
            $teacher_stmt->bind_param("i", $_SESSION['user_id']);
            $teacher_stmt->execute();
            $teacher_result = $teacher_stmt->get_result();
            $teacher = $teacher_result->fetch_assoc();

            if (!$teacher) {
                throw new Exception('Teacher ID not found');
            }

            $_SESSION['teacher_id'] = $teacher['reference_id'];
        }

        if ($teacher_id != $_SESSION['teacher_id']) {
            throw new Exception('Unauthorized to modify this schedule');
        }
    }

    // Validate time range
    if (strtotime($end_time) <= strtotime($start_time)) {
        throw new Exception('End time must be after start time');
    }
    // Check for overlapping schedules for the same section, teacher, or subject on the same day, time, and school year
    $conflict_check = $conn->prepare("
        SELECT COUNT(*) AS conflict_count
        FROM schedules
        WHERE day_of_week = ?
        AND schedule_id != ?
        AND school_year = ?
        AND (
            section_id = ?
            OR teacher_id = ?
            OR subject_id = ?
        )
        AND NOT (
            end_time <= ? OR start_time >= ?
        )
    ");
    $conflict_check->bind_param(
        "sissiiss",
        $day_of_week,
        $schedule_id,
        $school_year,
        $section_id,
        $teacher_id,
        $subject_id,
        $start_time,
        $end_time
    );
    
    $conflict_check->execute();
    $conflict_result = $conflict_check->get_result();
    $conflict_row = $conflict_result->fetch_assoc();

    if ($conflict_row['conflict_count'] > 0) {
        throw new Exception('Schedule conflict detected for this time slot');
    }

    // Update the schedule
    $update_stmt = $conn->prepare("
        UPDATE schedules
        SET day_of_week = ?, start_time = ?, end_time = ?
        WHERE schedule_id = ?
    ");
    $update_stmt->bind_param("sssi", $day_of_week, $start_time, $end_time, $schedule_id);

    if ($update_stmt->execute()) {
        echo json_encode(['success' => true, 'message' => 'Schedule updated successfully']);
    } else {
        throw new Exception('Database error: ' . $conn->error);
    }
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
?>
