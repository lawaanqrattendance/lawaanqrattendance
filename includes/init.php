<?php
// Set session cookie parameters before any session starts
if (session_status() === PHP_SESSION_NONE) {
    session_set_cookie_params([
        'lifetime' => 21600,
        'path' => '/',
        'secure' => false, // Set to true in production with HTTPS
        'httponly' => true,
        'samesite' => 'Lax'
    ]);
    session_start();
} 