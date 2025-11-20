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
    
    $stmt = $conn->prepare("SELECT note_id, note_title, note_content, 
                            DATE_FORMAT(note_date, '%M %e, %Y') as note_date,
                            DATE_FORMAT(created_at, '%M %e, %Y') as created_at,
                            DATE_FORMAT(updated_at, '%M %e, %Y %h:%i %p') as updated_at
                            FROM teacher_notes 
                            WHERE teacher_id = ? AND note_id = ?");
    $stmt->bind_param("ii", $teacher_id, $note_id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows > 0) {
        $note = $result->fetch_assoc();
        echo json_encode(['success' => true, 'note' => $note]);
    } else {
        throw new Exception('Note not found');
    }
    
} catch (Exception $e) {
    error_log("Error in get_note.php: " . $e->getMessage());
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
