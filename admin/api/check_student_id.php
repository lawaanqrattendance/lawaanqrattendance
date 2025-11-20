<?php
require_once '../../includes/config.php';
require_once '../../config/database.php';
require_once '../../includes/functions.php';
require_once '../../includes/auth.php';

header('Content-Type: application/json');

if (!isLoggedIn() || (getUserRole() !== 'admin' && getUserRole() !== 'teacher')) {
    echo json_encode(['error' => 'Unauthorized access']);
    exit();
}

if (isset($_GET['student_id'])) {
    $student_id = cleanInput($_GET['student_id']);
    
    $stmt = $conn->prepare("SELECT COUNT(*) as count FROM students WHERE student_id = ?");
    $stmt->bind_param("s", $student_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $count = $result->fetch_assoc()['count'];
    
    echo json_encode([
        'available' => $count === 0,
        'student_id' => $student_id
    ]);
} else {
    echo json_encode(['error' => 'No student ID provided']);
}