<?php
session_start();
require_once '../../config/database.php';
require_once '../../includes/functions.php';
require_once '../../includes/auth.php';

header('Content-Type: application/json');

// Debug information
$debug = [
    'isLoggedIn' => isLoggedIn(),
    'userRole' => getUserRole(),
    'sessionData' => $_SESSION,
    'rawInput' => file_get_contents('php://input')
];

if (!isLoggedIn()) {
    echo json_encode([
        'success' => false,
        'error' => 'Not logged in',
        'debug' => $debug
    ]);
    exit();
}

$userRole = getUserRole();
if ($userRole !== 'admin' && $userRole !== 'teacher') {
    echo json_encode([
        'success' => false,
        'error' => 'Unauthorized role',
        'debug' => $debug
    ]);
    exit();
}

// Get JSON input
$input = json_decode(file_get_contents('php://input'), true);
$debug['decodedInput'] = $input;

if (!isset($input['schedule_id'])) {
    echo json_encode([
        'success' => false,
        'error' => 'Schedule ID not provided',
        'debug' => $debug
    ]);
    exit();
}

$schedule_id = intval($input['schedule_id']);

try {
    // Start transaction
    $conn->begin_transaction();

    // First verify the schedule exists and get teacher_id
    $check_stmt = $conn->prepare("SELECT schedule_id, teacher_id FROM schedules WHERE schedule_id = ?");
    $check_stmt->bind_param("i", $schedule_id);
    $check_stmt->execute();
    $result = $check_stmt->get_result();
    
    if ($result->num_rows === 0) {
        throw new Exception('Schedule not found');
    }
    
    $schedule = $result->fetch_assoc();
    
    // For teachers, verify ownership
    if ($userRole === 'teacher') {
        if (!isset($_SESSION['teacher_id'])) {
            throw new Exception('Teacher ID not found in session');
        }
        
        if ($schedule['teacher_id'] != $_SESSION['teacher_id']) {
            throw new Exception('Unauthorized to delete this schedule');
        }
    }
    
    // First delete related attendance records
    $delete_attendance = $conn->prepare("DELETE FROM attendance WHERE schedule_id = ?");
    $delete_attendance->bind_param("i", $schedule_id);
    $delete_attendance->execute();
    
    // Then delete the schedule
    $delete_schedule = $conn->prepare("DELETE FROM schedules WHERE schedule_id = ?");
    $delete_schedule->bind_param("i", $schedule_id);
    
    if ($delete_schedule->execute()) {
        // Commit transaction
        $conn->commit();
        
        echo json_encode([
            'success' => true,
            'message' => 'Schedule and related attendance records deleted successfully',
            'debug' => $debug
        ]);
    } else {
        throw new Exception("Database error: " . $conn->error);
    }
    
} catch (Exception $e) {
    // Rollback transaction on error
    $conn->rollback();
    
    error_log("Delete schedule error: " . $e->getMessage());
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage(),
        'debug' => $debug
    ]);
}
?> 