<?php
// Remove session handling (moved to init.php)
function requireLogin() {
    if (!isset($_SESSION['logged_in']) || $_SESSION['logged_in'] !== true) {
        header("Location: " . BASE_URL . "/auth/login.php");
        exit();
    }
}

function getUserRole() {
    return isset($_SESSION['role']) ? $_SESSION['role'] : null;
}

function isLoggedIn() {
    return isset($_SESSION['logged_in']) && $_SESSION['logged_in'] === true;
}

function getTeacherId() {
    global $conn;
    if (getUserRole() === 'teacher' && isset($_SESSION['user_id'])) {
        $stmt = $conn->prepare("SELECT reference_id FROM users WHERE user_id = ? AND role = 'teacher'");
        $stmt->bind_param("i", $_SESSION['user_id']);
        $stmt->execute();
        $result = $stmt->get_result();
        if ($row = $result->fetch_assoc()) {
            return $row['reference_id'];
        }
    }
    return null;
}

function login($username, $password) {
    global $conn;
    
    $stmt = $conn->prepare("SELECT u.*, s.email 
                           FROM users u 
                           LEFT JOIN students s ON u.reference_id = s.student_id AND u.role = 'student'
                           WHERE u.username = ?");
    $stmt->bind_param("s", $username);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows === 1) {
        $user = $result->fetch_assoc();
        
        if (password_verify($password, $user['password'])) {
            // Set session variables
            $_SESSION['user_id'] = $user['user_id'];
            $_SESSION['username'] = $user['username'];
            $_SESSION['role'] = $user['role'];
            $_SESSION['logged_in'] = true;
            
            // For students, handle verification
            if ($user['role'] === 'student' && !$user['email_verified']) {
                // Generate new verification code
                $verification_code = sprintf("%06d", mt_rand(1, 999999));
                $code_expiry = date('Y-m-d H:i:s', strtotime('+1 hour'));
                
                $stmt = $conn->prepare("UPDATE users SET verification_code = ?, code_expiry = ? WHERE user_id = ?");
                $stmt->bind_param("ssi", $verification_code, $code_expiry, $user['user_id']);
                $stmt->execute();
                
                // Send verification email
                if ($user['email']) {
                    require_once __DIR__ . '/../vendor/autoload.php';
                    require_once __DIR__ . '/../config/email_config.php';
                    
                    sendVerificationEmail($user['email'], $verification_code);
                }
            }
            
            return true;
        }
    }
    
    return false;
} 