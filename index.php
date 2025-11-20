<?php
require_once __DIR__ . '/includes/init.php';
require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/includes/config.php';
require_once __DIR__ . '/includes/auth.php';


if (!isLoggedIn()) {
    header("Location: auth/login.php");
    exit();
}

// Redirect based on user role
// Redirect based on user role BEFORE output
switch (getUserRole()) {
    case 'admin':
        header("Location: admin/dashboard.php");
        exit();
    case 'teacher':
        header("Location: admin/teacher/dashboard.php");
        exit();
    case 'student':
        header("Location: admin/student/dashboard.php");
        exit();
    default:
        header("Location: auth/logout.php");
        exit();
}


// If execution reaches here, no redirects happened (edge case)
include 'includes/footer.php';
?> 