<?php
include '../../includes/header.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = cleanInput($_POST['email']);
    
    $stmt = $conn->prepare("SELECT u.user_id, u.username, s.email 
                           FROM users u 
                           JOIN students s ON u.reference_id = s.student_id 
                           WHERE s.email = ? AND u.role = 'student'");
    $stmt->bind_param("s", $email);
    $stmt->execute();
    
    if ($result = $stmt->get_result()) {
        if ($user = $result->fetch_assoc()) {
            $reset_token = bin2hex(random_bytes(32));
            $expiry = date('Y-m-d H:i:s', strtotime('+1 hour'));
            
            $stmt = $conn->prepare("UPDATE users SET reset_token = ?, reset_expiry = ? WHERE user_id = ?");
            $stmt->bind_param("ssi", $reset_token, $expiry, $user['user_id']);
            
            if ($stmt->execute()) {
                $reset_link = BASE_URL . "/auth/new_password.php?token=" . $reset_token;
                mail($email, "Password Reset Request", "Click here to reset your password: " . $reset_link);
                $success = "Password reset instructions sent to your email";
            }
        }
    }
    // Always show this message to prevent email enumeration
    $success = "If the email exists, password reset instructions have been sent";
}
?> 