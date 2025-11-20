<?php
header('Content-Type: application/json');
require_once '../../../includes/config.php';
require_once '../../../config/database.php';
require_once '../../../includes/functions.php';
require_once '../../../includes/auth.php';

if (!isLoggedIn() || getUserRole() !== 'teacher') {
    echo json_encode(['success' => false, 'error' => 'Unauthorized access']);
    exit();
}

try {
    $teacher_id = $_SESSION['teacher_id'];
    $note_date = $_POST['note_date'];
    $note_title = $_POST['note_title'];
    $note_content = $_POST['note_content'];
    
    $stmt = $conn->prepare("INSERT INTO teacher_notes (teacher_id, note_date, note_title, note_content) VALUES (?, ?, ?, ?)");
    $stmt->bind_param("isss", $teacher_id, $note_date, $note_title, $note_content);
    
    if ($stmt->execute()) {
        echo json_encode(['success' => true]);
    } else {
        throw new Exception('Failed to save note');
    }
    
} catch (Exception $e) {
    error_log("Error in save_note.php: " . $e->getMessage());
    echo json_encode(['success' => false, 'error' => 'Failed to save note']);
} 