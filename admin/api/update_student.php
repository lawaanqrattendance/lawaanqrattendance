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
    // Get POST data
    $student_id = cleanInput($_POST['student_id']);
    $firstname = cleanInput($_POST['firstname']);
    $lastname = cleanInput($_POST['lastname']);
    $middlename = cleanInput($_POST['middlename']);
    $email = cleanInput($_POST['email']);
    $section_id = cleanInput($_POST['section_id']);
    // Optional guardian email
    $guardian_email = isset($_POST['guardian_email']) ? cleanInput($_POST['guardian_email']) : null;

    // Get grade level from section
    $stmt = $conn->prepare("SELECT grade_level FROM sections WHERE section_id = ?");
    $stmt->bind_param("s", $section_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $grade_level = $result->fetch_assoc()['grade_level'];

    // Start transaction
    $conn->begin_transaction();

    // Update student information
    $stmt = $conn->prepare("UPDATE students SET 
        firstname = ?, 
        lastname = ?, 
        middlename = ?, 
        email = ?, 
        guardian_email = ?, 
        grade_level = ?, 
        section_id = ? 
        WHERE student_id = ?");
    
    $stmt->bind_param("sssssiis", 
        $firstname, 
        $lastname, 
        $middlename, 
        $email, 
        $guardian_email,
        $grade_level, 
        $section_id, 
        $student_id
    );
    
    if ($stmt->execute()) {
        // Reset email verification when email is changed
        $stmt = $conn->prepare("SELECT email FROM students WHERE student_id = ?");
        $stmt->bind_param("s", $student_id);
        $stmt->execute();
        $result = $stmt->get_result();
        $current_email = $result->fetch_assoc()['email'];

        if ($current_email !== $email) {
            $stmt = $conn->prepare("UPDATE users SET email_verified = 0 WHERE reference_id = ? AND role = 'student'");
            $stmt->bind_param("s", $student_id);
            $stmt->execute();
        }

        $conn->commit();
        echo json_encode([
            'success' => true,
            'message' => 'Student updated successfully'
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
        'error' => 'Failed to update student: ' . $e->getMessage()
    ]);
} 