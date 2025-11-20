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
    $teacher_id = cleanInput($_POST['teacher_id']);
    $firstname = cleanInput($_POST['firstname']);
    $lastname = cleanInput($_POST['lastname']);
    $middlename = cleanInput($_POST['middlename']);
    $password = $_POST['password'] ?? '';
    $confirm_password = $_POST['confirm_password'] ?? '';
    $sections = isset($_POST['sections']) ? $_POST['sections'] : [];

    // Start transaction
    $conn->begin_transaction();

    // Update teacher information
    $stmt = $conn->prepare("UPDATE teachers SET firstname = ?, lastname = ?, middlename = ? WHERE teacher_id = ?");
    $stmt->bind_param("ssss", $firstname, $lastname, $middlename, $teacher_id);
    
    if ($stmt->execute()) {
        // Update username
        $username = strtolower($firstname . '.' . $lastname);
        $stmt = $conn->prepare("UPDATE users SET username = ? WHERE role = 'teacher' AND reference_id = ?");
        $stmt->bind_param("ss", $username, $teacher_id);
        $stmt->execute();
        
        // Update password if provided
        if (!empty($password)) {
            if ($password !== $confirm_password) {
                throw new Exception('Passwords do not match');
            }
            
            $hashed_password = password_hash($password, PASSWORD_DEFAULT);
            $stmt = $conn->prepare("UPDATE users SET password = ? WHERE role = 'teacher' AND reference_id = ?");
            $stmt->bind_param("ss", $hashed_password, $teacher_id);
            $stmt->execute();
        }

        // Update section assignments
        // First, remove existing assignments
        $stmt = $conn->prepare("DELETE FROM teacher_sections WHERE teacher_id = ?");
        $stmt->bind_param("i", $teacher_id);
        $stmt->execute();

        // Then add new assignments
        if (!empty($sections)) {
            $stmt = $conn->prepare("INSERT INTO teacher_sections (teacher_id, section_id) VALUES (?, ?)");
            foreach ($sections as $section_id) {
                $stmt->bind_param("ii", $teacher_id, $section_id);
                $stmt->execute();
            }
        }
        
        $conn->commit();
        echo json_encode([
            'success' => true,
            'message' => 'Teacher updated successfully'
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
        'error' => 'Failed to update teacher: ' . $e->getMessage()
    ]);
} 