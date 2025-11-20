<?php
require_once '../../includes/config.php';
require_once '../../config/database.php';
require_once '../../includes/functions.php';
require_once '../../includes/auth.php';

header('Content-Type: application/json');

if (!isLoggedIn() || getUserRole() !== 'teacher') {
    echo json_encode(['success' => false, 'error' => 'Unauthorized access']);
    exit();
}

// Get POST data
$schedule_id = isset($_POST['schedule_id']) ? (int)$_POST['schedule_id'] : 0;
$date = isset($_POST['date']) ? $_POST['date'] : date('Y-m-d');
$attendance_data = json_decode($_POST['attendance'], true);

// Validate schedule belongs to teacher
$stmt = $conn->prepare("SELECT section_id FROM schedules WHERE schedule_id = ? AND teacher_id = ?");
$stmt->bind_param("ii", $schedule_id, $_SESSION['teacher_id']);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows === 0) {
    echo json_encode(['success' => false, 'error' => 'Invalid schedule']);
    exit();
}

try {
    // Start transaction
    $conn->begin_transaction();

    // First, get existing records for this schedule and date
    $check_stmt = $conn->prepare("SELECT attendance_id, student_id FROM attendance 
                                WHERE schedule_id = ? AND attendance_date = ?");
    $check_stmt->bind_param("is", $schedule_id, $date);
    $check_stmt->execute();
    $existing_records = $check_stmt->get_result();
    
    // Create a map of existing records
    $existing_map = [];
    while ($row = $existing_records->fetch_assoc()) {
        $existing_map[$row['student_id']] = $row['attendance_id'];
    }

    // Prepare statements for insert and update
    $update_stmt = $conn->prepare("UPDATE attendance 
                                 SET status = ?, updated_at = NOW() 
                                 WHERE attendance_id = ?");
    
    $insert_stmt = $conn->prepare("INSERT INTO attendance 
                                 (student_id, schedule_id, attendance_date, status, created_at) 
                                 VALUES (?, ?, ?, ?, NOW())");

    foreach ($attendance_data as $record) {
        $student_id = $record['student_id'];
        $status = $record['status'];

        if (isset($existing_map[$student_id])) {
            // Update existing record
            $attendance_id = $existing_map[$student_id];
            $update_stmt->bind_param("si", $status, $attendance_id);
            if (!$update_stmt->execute()) {
                throw new Exception("Error updating attendance for student: " . $student_id);
            }
        } else {
            // Insert new record
            $insert_stmt->bind_param("siss", $student_id, $schedule_id, $date, $status);
            if (!$insert_stmt->execute()) {
                throw new Exception("Error inserting attendance for student: " . $student_id);
            }
        }
    }

    // Commit transaction
    $conn->commit();
    echo json_encode(['success' => true]);

} catch (Exception $e) {
    // Rollback on error
    $conn->rollback();
    error_log("Save attendance error: " . $e->getMessage());
    echo json_encode([
        'success' => false, 
        'error' => 'Failed to save attendance records'
    ]);
}

$conn->close(); 