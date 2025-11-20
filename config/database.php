<?php
// Default database configuration
$db_config = [
    'host' => 'localhost',
    'username' => 'root',
    'password' => '',
    'name' => 'attendance_system'
];

// Try to load from .env if exists
if (file_exists(__DIR__ . '/../.env')) {
    $env_config = parse_ini_file(__DIR__ . '/../.env', true);
    if ($env_config && isset($env_config['database'])) {
        $db_config = array_merge($db_config, $env_config['database']);
    }
}

// Define constants
define('DB_HOST', $db_config['host']);
define('DB_USER', $db_config['username']);
define('DB_PASS', $db_config['password']);
define('DB_NAME', $db_config['name']);

// Create connection
$conn = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);

if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

// Define BASE_URL
if (!defined('BASE_URL')) {
    // Determine scheme/host (ngrok-aware)
    $protocol = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https://' : 'http://';
    $host = $_SERVER['HTTP_HOST'];
    if (!empty($_SERVER['HTTP_X_FORWARDED_PROTO'])) {
        $proto = strtolower($_SERVER['HTTP_X_FORWARDED_PROTO']);
        $protocol = ($proto === 'https') ? 'https://' : 'http://';
    }
    if (!empty($_SERVER['HTTP_X_FORWARDED_HOST'])) {
        $host = $_SERVER['HTTP_X_FORWARDED_HOST'];
    }
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
?> 