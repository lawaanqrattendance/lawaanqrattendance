<?php
require_once 'includes/api_headers.php';
require_once '../../includes/config.php';
require_once '../../config/database.php';
require_once '../../includes/functions.php';
require_once '../../includes/auth.php';
require_once 'includes/api_response.php';

if (!isLoggedIn() || getUserRole() !== 'admin') {
    sendUnauthorizedResponse();
}

if (!isset($_POST['student_id']) || empty($_POST['student_id'])) {
    sendValidationError('Student ID is required');
}

try {
    $student_id = cleanInput($_POST['student_id']);
    
    $conn->begin_transaction();
    
    // Delete user account
    $stmt = $conn->prepare("DELETE FROM users WHERE role = 'student' AND reference_id = ?");
    $stmt->bind_param("s", $student_id);
    $stmt->execute();
    
    // Delete attendance records
    $stmt = $conn->prepare("DELETE FROM attendance WHERE student_id = ?");
    $stmt->bind_param("s", $student_id);
    $stmt->execute();
    
    // Delete student
    $stmt = $conn->prepare("DELETE FROM students WHERE student_id = ?");
    $stmt->bind_param("s", $student_id);
    
    if ($stmt->execute()) {
        $conn->commit();
        sendApiResponse(true, ['message' => 'Student deleted successfully']);
    } else {
        throw new Exception('Database error: ' . $conn->error);
    }
} catch (Exception $e) {
    $conn->rollback();
    sendServerError('Failed to delete student: ' . $e->getMessage());
} 