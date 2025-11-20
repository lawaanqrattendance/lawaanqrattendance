<?php
// Load SMTP configuration from .env ([mail] section)
// No external dependency: uses parse_ini_file

$envPath = dirname(__DIR__) . DIRECTORY_SEPARATOR . '.env';
$env = [];
if (is_readable($envPath)) {
    $parsed = parse_ini_file($envPath, true, INI_SCANNER_TYPED);
    if (is_array($parsed)) {
        $env = $parsed;
    }
}

$mail = $env['mail'] ?? [];

// Define constants expected by the rest of the app, with sensible defaults
define('SMTP_HOST', $mail['host'] ?? 'smtp.gmail.com');
define('SMTP_USERNAME', $mail['username'] ?? '');
define('SMTP_PASSWORD', $mail['password'] ?? '');
define('SMTP_FROM_NAME', $mail['from_name'] ?? 'Attendance System Pro');
define('SMTP_FROM_EMAIL', $mail['from_email'] ?? ($mail['username'] ?? 'no-reply@example.com'));
define('SMTP_PORT', (int)($mail['port'] ?? 587));
// "tls" or "ssl"; PHPMailer expects 'tls'/'ssl'. Default to tls
define('SMTP_SECURE', $mail['secure'] ?? 'tls');
