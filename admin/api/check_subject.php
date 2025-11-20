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
if (!isLoggedIn() || getUserRole() !== 'admin') {
    echo json_encode(['success' => false, 'error' => 'Unauthorized access']);
    exit();
}

try {
    // Get input data
    $subject_name = isset($_POST['subject_name']) ? cleanInput($_POST['subject_name']) : '';
    $exclude_id = isset($_POST['exclude_id']) ? intval($_POST['exclude_id']) : 0;
    
    if (empty($subject_name)) {
        throw new Exception('Subject name is required');
    }
    
    // Check if subject name already exists
    $query = "SELECT subject_id FROM subjects WHERE subject_name = ?";
    $params = [$subject_name];
    $types = "s";
    
    if ($exclude_id > 0) {
        $query .= " AND subject_id != ?";
        $params[] = $exclude_id;
        $types .= "i";
    }
    
    $stmt = $conn->prepare($query);
    $stmt->bind_param($types, ...$params);
    $stmt->execute();
    $result = $stmt->get_result();
    
    echo json_encode([
        'success' => true,
        'exists' => $result->num_rows > 0
    ]);
    
} catch (Exception $e) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ]);
}
