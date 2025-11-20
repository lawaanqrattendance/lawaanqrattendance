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
    $note_date = $_POST['note_date'];
    $note_title = $_POST['note_title'];
    $note_content = $_POST['note_content'];
    
    $stmt = $conn->prepare("INSERT INTO admin_notes (admin_id, note_date, note_title, note_content) 
                           VALUES (?, ?, ?, ?)");
    $stmt->bind_param("isss", $admin_id, $note_date, $note_title, $note_content);
    
    if ($stmt->execute()) {
        echo json_encode(['success' => true]);
    } else {
        throw new Exception('Failed to add note');
    }
    
} catch (Exception $e) {
    error_log("Error in admin add_note.php: " . $e->getMessage());
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ]);
} 