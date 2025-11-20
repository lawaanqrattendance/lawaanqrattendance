<?php
function logUserActivity($action, $details = '') {
    global $conn;
    
    $user_id = $_SESSION['user_id'] ?? 0;
    $ip = $_SERVER['REMOTE_ADDR'];
    $user_agent = $_SERVER['HTTP_USER_AGENT'];
    
    $stmt = $conn->prepare("INSERT INTO activity_logs 
                           (user_id, action, details, ip_address, user_agent) 
                           VALUES (?, ?, ?, ?, ?)");
    $stmt->bind_param("issss", $user_id, $action, $details, $ip, $user_agent);
    return $stmt->execute();
}

// Add this to your database schema
$sql = "CREATE TABLE IF NOT EXISTS activity_logs (
    log_id INT PRIMARY KEY AUTO_INCREMENT,
    user_id INT,
    action VARCHAR(255),
    details TEXT,
    ip_address VARCHAR(45),
    user_agent TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
)"; 