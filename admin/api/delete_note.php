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
    $admin_id = $_SESSION['user_id'];
    $note_id = $_POST['note_id'];
    
    // Verify note belongs to admin
    $stmt = $conn->prepare("SELECT note_id FROM admin_notes WHERE note_id = ? AND admin_id = ?");
    $stmt->bind_param("ii", $note_id, $admin_id);
    $stmt->execute();
    if ($stmt->get_result()->num_rows === 0) {
        throw new Exception('Note not found');
    }
    
    // Delete note
    $stmt = $conn->prepare("DELETE FROM admin_notes WHERE note_id = ? AND admin_id = ?");
    $stmt->bind_param("ii", $note_id, $admin_id);
    
    if ($stmt->execute()) {
        echo json_encode(['success' => true]);
    } else {
        throw new Exception('Failed to delete note');
    }
    
} catch (Exception $e) {
    error_log("Error in admin delete_note.php: " . $e->getMessage());
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ]);
} 