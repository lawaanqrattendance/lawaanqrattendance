<?php
function cleanInput($data) {
    $data = trim($data);
    $data = stripslashes($data);
    $data = htmlspecialchars($data);
    return $data;
}

// ... other utility functions that are not auth-related ... 

// Add this function to mask email addresses
function maskEmail($email) {
    $parts = explode('@', $email);
    $name = $parts[0];
    $length = strlen($name);
    $masked = substr($name, 0, 1) . str_repeat('*', $length - 2) . substr($name, -1);
    return $masked . '@' . $parts[1];
}

function generateCSRFToken() {
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

function validateCSRFToken($token) {
    return isset($_SESSION['csrf_token']) && hash_equals($_SESSION['csrf_token'], $token);
} 