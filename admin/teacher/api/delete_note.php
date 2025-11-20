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
    $note_id = $_POST['note_id'];
    
    // Verify note belongs to teacher
    $stmt = $conn->prepare("SELECT note_id FROM teacher_notes WHERE note_id = ? AND teacher_id = ?");
    $stmt->bind_param("ii", $note_id, $teacher_id);
    $stmt->execute();
    if ($stmt->get_result()->num_rows === 0) {
        throw new Exception('Note not found');
    }
    
    // Delete note
    $stmt = $conn->prepare("DELETE FROM teacher_notes WHERE note_id = ? AND teacher_id = ?");
    $stmt->bind_param("ii", $note_id, $teacher_id);
    
    if ($stmt->execute()) {
        echo json_encode(['success' => true]);
    } else {
        throw new Exception('Failed to delete note');
    }
    
} catch (Exception $e) {
    error_log("Error in delete_note.php: " . $e->getMessage());
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ]);
} 