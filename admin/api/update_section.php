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
    $section_name = trim(cleanInput($_POST['section_name']));
    $grade_level = cleanInput($_POST['grade_level']);

    // Input validation
    if ($section_name === '' || strlen($section_name) > 50) {
        throw new Exception('Invalid section name.');
    }
    if (!is_numeric($grade_level) || $grade_level < 1 || $grade_level > 12) {
        throw new Exception('Invalid grade level.');
    }

    // Check for duplicates (exclude self)
    $dup_stmt = $conn->prepare("SELECT COUNT(*) FROM sections WHERE section_name = ? AND grade_level = ? AND section_id != ?");
    $dup_stmt->bind_param("sii", $section_name, $grade_level, $section_id);
    $dup_stmt->execute();
    $dup_stmt->bind_result($dup_count);
    $dup_stmt->fetch();
    $dup_stmt->close();
    if ($dup_count > 0) {
        throw new Exception('A section with this name and grade level already exists.');
    }

    // Start transaction
    $conn->begin_transaction();

    $stmt = $conn->prepare("UPDATE sections SET section_name = ?, grade_level = ? WHERE section_id = ?");
    $stmt->bind_param("sii", $section_name, $grade_level, $section_id);
    
    if ($stmt->execute()) {
        $conn->commit();
        echo json_encode([
            'success' => true,
            'message' => 'Section updated successfully'
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
        'error' => 'Failed to update section: ' . $e->getMessage()
    ]);
} 