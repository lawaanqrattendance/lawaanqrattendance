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
    
    $stmt = $conn->prepare("SELECT note_id, note_title, note_content, note_date, updated_at 
                           FROM teacher_notes 
                           WHERE teacher_id = ?");
    $stmt->bind_param("i", $teacher_id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    $events = [];
    while ($note = $result->fetch_assoc()) {
        $events[] = [
            'id' => $note['note_id'],
            'title' => $note['note_title'],
            'start' => $note['note_date'],
            'content' => $note['note_content'],
            'updated_at' => date('M j, Y g:i A', strtotime($note['updated_at']))
        ];
    }
    
    echo json_encode($events);
    
} catch (Exception $e) {
    error_log("Error in get_notes.php: " . $e->getMessage());
    echo json_encode([]);
} 