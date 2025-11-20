<?php
/**
 * Page Availability Controller
 * 
 * This script controls which pages are accessible.
 * Only specific pages are accessible, all others show 404.
 */

// Get the current script path relative to the document root
$docRoot = str_replace('\\', '/', $_SERVER['DOCUMENT_ROOT']);
$scriptPath = str_replace('\\', '/', dirname($_SERVER['SCRIPT_FILENAME']));
$scriptFile = basename($_SERVER['SCRIPT_FILENAME']);
$currentPath = str_replace($docRoot, '', $scriptPath) . '/' . $scriptFile;

// Normalize path (remove any double slashes and ensure leading slash)
$currentPath = '/' . ltrim(preg_replace('#/+#', '/', $currentPath), '/');

// Define allowed scripts (relative to document root)
$allowedScripts = [
    '/auth/login.php',
    '/auth/logout.php',
    '/admin/dashboard.php',
    '/index.php',
    '/includes/header.php',
    '/includes/footer.php',
    '/includes/functions.php',
    '/includes/config.php',
    '/includes/init.php',
    '/includes/auth.php'
];

// Debug: Uncomment to see the paths being compared
// error_log("Current path: " . $currentPath);
// error_log("Doc root: " . $docRoot);
// error_log("Script filename: " . $_SERVER['SCRIPT_FILENAME']);

// Include config to get BASE_URL
require_once __DIR__ . '/includes/config.php';

// Special case: Redirect root URL to login
if ($currentPath === '/index.php' || $currentPath === '/') {
    header('Location: ' . BASE_URL . '/auth/login.php');
    exit();
}

// Check if current path is allowed
$isAllowed = false;
foreach ($allowedScripts as $allowed) {
    if (str_ends_with($currentPath, $allowed)) {
        $isAllowed = true;
        break;
    }
}

// Show 404 if current script is not in the allowed list
if (!$isAllowed) {
    show404();
}

/**
 * Show 404 page and exit
 */
function show404() {
    http_response_code(404);
    ?>
    <!DOCTYPE html>
    <html lang="en">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title>Page Not Found</title>
        <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
        <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
        <style>
            body { 
                background: #f8f9fa;
                height: 100vh;
                display: flex;
                align-items: center;
                justify-content: center;
                padding: 20px;
            }
            .error-container {
                text-align: center;
                padding: 2.5rem;
                background: white;
                border-radius: 10px;
                box-shadow: 0 0.5rem 1rem rgba(0, 0, 0, 0.1);
                max-width: 500px;
                width: 100%;
            }
            .error-icon {
                font-size: 4rem;
                color: #6c757d;
                margin-bottom: 1.5rem;
            }
            .error-title {
                font-size: 2rem;
                margin-bottom: 1rem;
                color: #343a40;
            }
            .error-message {
                color: #6c757d;
                margin-bottom: 2rem;
                font-size: 1.1rem;
            }
            .btn-home {
                padding: 0.6rem 1.8rem;
                font-size: 1.1rem;
                border-radius: 50px;
                font-weight: 500;
            }
        </style>
    </head>
    <body>
        <div class="error-container">
            <div class="error-icon">
                <i class="fas fa-exclamation-triangle"></i>
            </div>
            <h1 class="error-title">404 - Page Not Found</h1>
            <p class="error-message">The page you are looking for might have been removed, had its name changed, or is temporarily unavailable.</p>
            <a href="<?php echo BASE_URL; ?>/index.php" class="btn btn-primary btn-home">
                <i class="fas fa-home me-2"></i>Go to Home
            </a>
        </div>
        
        <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    </body>
    </html>
    <?php
    exit();
}
