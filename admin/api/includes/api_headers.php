<?php
require_once __DIR__ . '/../../../config/database.php';  // This will load BASE_URL

// Security headers
header("X-Content-Type-Options: nosniff");
header("X-Frame-Options: DENY");
header("X-XSS-Protection: 1; mode=block");
header("Referrer-Policy: same-origin");
header("Content-Security-Policy: default-src 'self'");

// CORS headers
header("Access-Control-Allow-Origin: " . BASE_URL);
header("Access-Control-Allow-Methods: POST, GET, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type");
header('Content-Type: application/json'); 