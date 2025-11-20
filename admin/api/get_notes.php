<?php
header('Content-Type: application/json');
require_once '../../includes/config.php';
require_once '../../config/database.php';
require_once '../../includes/functions.php';
require_once '../../includes/auth.php';

if (!isLoggedIn() || getUserRole() !== 'admin') {
    echo json_encode(['success' => false, 'error' => 'Unauthorized access']);
    exit();
}

try {
    $admin_id = $_SESSION['user_id']; // Using user_id for admin
    
    $stmt = $conn->prepare("SELECT note_id, note_title, note_content, note_date, updated_at 
                           FROM admin_notes 
                           WHERE admin_id = ?");
    $stmt->bind_param("i", $admin_id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    $events = [];
    while ($note = $result->fetch_assoc()) {
        // Only include notes with a valid date
        if (!empty($note['note_date'])) {
            $events[] = [
                'id' => $note['note_id'],
                'title' => $note['note_title'],
                'start' => $note['note_date'],
                // For FullCalendar extendedProps
                'extendedProps' => [
                    'content' => $note['note_content'],
                    'updated_at' => date('M j, Y g:i A', strtotime($note['updated_at']))
                ]
            ];
        }
    }
    
    echo json_encode($events);
    
} catch (Exception $e) {
    error_log("Error in admin get_notes.php: " . $e->getMessage());
    echo json_encode([]);
} 