<?php
// Start session if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Dynamic BASE_URL detection (ngrok-aware)
$protocol = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https://' : 'http://';
$host = $_SERVER['HTTP_HOST'];

// If behind a reverse proxy (e.g., ngrok), honor forwarded headers
if (!empty($_SERVER['HTTP_X_FORWARDED_PROTO'])) {
    $proto = strtolower($_SERVER['HTTP_X_FORWARDED_PROTO']);
    $protocol = ($proto === 'https') ? 'https://' : 'http://';
}
if (!empty($_SERVER['HTTP_X_FORWARDED_HOST'])) {
    $host = $_SERVER['HTTP_X_FORWARDED_HOST'];
}
$baseDir = dirname(dirname($_SERVER['PHP_SELF']));
$baseDir = str_replace('\\', '/', $baseDir);

// Define BASE_URL if not already defined
if (!defined('BASE_URL')) {
    // Detect if running from built-in PHP server and root is project folder
    $isBuiltInServer = (php_sapi_name() === 'cli-server');

    if ($isBuiltInServer) {
        // Built-in server from project root → no extra folder in URL
        define('BASE_URL', $protocol . $host);
    } else {
        // Apache with htdocs as root → include folder name
        define('BASE_URL', $protocol . $host . '/AttendancePro-UI-Enhanced');
    }
}

// Set session cookie parameters
// session_set_cookie_params([
//     'lifetime' => 0,
//     'path' => $baseDir,
//     'secure' => false,
//     'httponly' => true
// ]);