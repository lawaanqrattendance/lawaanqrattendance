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
    $subject_id = cleanInput($_POST['subject_id']);
    $subject_name = cleanInput($_POST['subject_name']);
    $description = cleanInput($_POST['description']);

    // Start transaction
    $conn->begin_transaction();

    $stmt = $conn->prepare("UPDATE subjects SET subject_name = ?, description = ? WHERE subject_id = ?");
    $stmt->bind_param("ssi", $subject_name, $description, $subject_id);
    
    if ($stmt->execute()) {
        $conn->commit();
        echo json_encode([
            'success' => true,
            'message' => 'Subject updated successfully'
        ]);
    } else {
        throw new Exception('Database error: ' . $conn->error);
    }
} catch (Exception $e) {
    if (isset($conn)) {
        $conn->rollback();
    }
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => 'Failed to update subject: ' . $e->getMessage()
    ]);
} 