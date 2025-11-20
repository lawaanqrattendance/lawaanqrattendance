<?php
// Allow from any origin
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: POST, GET, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type");
header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

// Include necessary files
require_once '../../includes/config.php';
require_once '../../config/database.php';
require_once '../../includes/functions.php';
require_once '../../includes/auth.php';

// Check if user is logged in and has appropriate role
if (!isLoggedIn() || (getUserRole() !== 'admin' && getUserRole() !== 'teacher')) {
    echo json_encode(['success' => false, 'error' => 'Unauthorized access']);
    exit();
}

try {
    $schedule_id = cleanInput($_POST['schedule_id']);
    $userRole = getUserRole();
    
    // First verify the schedule exists and get its details
    $check_stmt = $conn->prepare("SELECT schedule_id, teacher_id, status FROM schedules WHERE schedule_id = ?");
    $check_stmt->bind_param("i", $schedule_id);
    $check_stmt->execute();
    $result = $check_stmt->get_result();
    
    if ($result->num_rows === 0) {
        throw new Exception('Schedule not found');
    }
    
    $schedule = $result->fetch_assoc();
    
    // For teachers, verify ownership
    if ($userRole === 'teacher') {
        // Get teacher's ID from session or users table
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
        
        if ($schedule['teacher_id'] != $_SESSION['teacher_id']) {
            throw new Exception('Unauthorized to modify this schedule');
        }
    }

    // Toggle the status
    $new_status = ($schedule['status'] === 'Open') ? 'Closed' : 'Open';

    // Update the status
    $stmt = $conn->prepare("UPDATE schedules SET status = ? WHERE schedule_id = ?");
    $stmt->bind_param("si", $new_status, $schedule_id);

    if ($stmt->execute()) {
        echo json_encode([
            'success' => true,
            'new_status' => $new_status,
            'message' => "Schedule status updated to $new_status"
        ]);
    } else {
        throw new Exception('Database error: ' . $conn->error);
    }
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ]);
}
?>
