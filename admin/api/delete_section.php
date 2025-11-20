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
    $section_id = cleanInput($_POST['section_id']);
    
    // Start transaction
    $conn->begin_transaction();
    
    // Delete related schedules
    $stmt = $conn->prepare("DELETE FROM schedules WHERE section_id = ?");
    $stmt->bind_param("i", $section_id);
    $stmt->execute();
    
    // Update students to remove section assignment
    $stmt = $conn->prepare("UPDATE students SET section_id = NULL WHERE section_id = ?");
    $stmt->bind_param("i", $section_id);
    $stmt->execute();
    
    // Delete section
    $stmt = $conn->prepare("DELETE FROM sections WHERE section_id = ?");
    $stmt->bind_param("i", $section_id);
    
    if ($stmt->execute()) {
        $conn->commit();
        echo json_encode(['success' => true]);
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
        'error' => 'Failed to delete section: ' . $e->getMessage()
    ]);
} 