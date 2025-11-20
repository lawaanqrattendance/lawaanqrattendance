<?php
// Include page availability controller first
//require_once __DIR__ . '/../page_availability_controller.php';

// Only include these if they haven't been included already
if (!defined('HEADER_INCLUDED')) {
    define('HEADER_INCLUDED', true);
    
    // Include required files only once
    $required_files = [
        __DIR__ . '/init.php',
        __DIR__ . '/../config/database.php',
        __DIR__ . '/config.php',
        __DIR__ . '/functions.php',
        __DIR__ . '/auth.php'
    ];
    
    foreach ($required_files as $file) {
        if (file_exists($file)) {
            require_once $file;
        } else {
            error_log("Required file not found: " . $file);
        }
    }
}

// If headers haven't been sent yet, start output buffering
if (!headers_sent() && !in_array(ob_get_status()['name'], ['ob_gzhandler', 'zlib output compression'])) {
    ob_start();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Attendance System</title>
    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- jQuery -->
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <!-- Font Awesome -->
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <!-- Bootstrap Bundle JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <style>
        /* Header Customization */
        body {
    background: linear-gradient(135deg, #99CFD0, #65afa7);
        }
        .navbar-custom {
            background: linear-gradient(90deg, #7de29c 0%, #55c97e 100%);
            box-shadow: 0 2px 6px rgba(0, 0, 0, 0.3);
        }
        .navbar-custom .navbar-brand,
        .navbar-custom .nav-link {
            color: #fff;
        }
        .navbar-custom .nav-link:hover {
            color: #ffd700;
        }
        /* Logout button */
        .logout-btn {
            border: 1px solid #ffc107;
            border-radius: 20px;
            padding: 4px 14px;
            transition: all 0.3s ease;
        }
        .logout-btn:hover {
            background-color: #ffc107;
            color: #000 !important;
        }
        .back-btn {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    border: 2px solid #55c97e; /* Green border */
    color: #000; /* Green text */
    background: transparent;
    border-radius: 50px; /* Rounded edges */
    padding: 8px 20px;
    font-size: 1.2rem;
    font-weight: 900;
    text-decoration: none;
    transition: all 0.3s ease;
}

.back-btn .arrow {
    font-size: 1.2rem;
    margin-right: 8px;
}

.back-btn:hover {
    background: #20c997;
    color: #fff;
    box-shadow: 0 0 8px rgba(32, 201, 151, 0.5);
}
    </style>
</head>
<body>
<nav class="navbar navbar-expand-lg navbar-dark navbar-custom">
    <div class="container">
        <a class="navbar-brand d-flex align-items-center" href="<?php echo BASE_URL; ?>/index.php">
    <span class="back-btn me-2">
        <span class="arrow">←</span>
    </span>
    Attendance System
</a>
        <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav" aria-controls="navbarNav" aria-expanded="false" aria-label="Toggle navigation">
            <span class="navbar-toggler-icon"></span>
        </button>
        <div class="collapse navbar-collapse" id="navbarNav">
            <?php if (isLoggedIn()): ?>
                <div class="navbar-nav ms-auto" style="display: flex; align-items: center;">
                    <span class="nav-item nav-link" style="font-weight: bold; text-transform: capitalize; color: #fff !important;">Welcome, 
                        <?php 
                        if (!isset($_SESSION['name']) || empty($_SESSION['name'])) {
                            // Fetch user's name if not in session
                            $user_id = $_SESSION['user_id'];
                            $role = $_SESSION['role'];
                            
                            switch ($role) {
                                case 'teacher':
                                    $stmt = $conn->prepare("
                                        SELECT CONCAT(t.firstname, ' ', t.lastname) as full_name 
                                        FROM teachers t 
                                        INNER JOIN users u ON t.teacher_id = u.reference_id 
                                        WHERE u.user_id = ?
                                    ");
                                    break;
                                    
                                case 'student':
                                    $stmt = $conn->prepare("
                                        SELECT CONCAT(s.firstname, ' ', s.lastname) as full_name 
                                        FROM students s 
                                        INNER JOIN users u ON s.student_id = u.reference_id 
                                        WHERE u.user_id = ?
                                    ");
                                    break;
                                    
                                case 'admin':
                                    $stmt = $conn->prepare("
                                        SELECT username as full_name 
                                        FROM users 
                                        WHERE user_id = ?
                                    ");
                                    break;
                                    
                                default:
                                    $stmt = $conn->prepare("
                                        SELECT username as full_name 
                                        FROM users 
                                        WHERE user_id = ?
                                    ");
                            }
                            
                            $stmt->bind_param("i", $user_id);
                            $stmt->execute();
                            $result = $stmt->get_result();
                            if ($row = $result->fetch_assoc()) {
                                $_SESSION['name'] = $row['full_name'];
                            }
                        }
                        echo htmlspecialchars($_SESSION['name'] ?? 'User');
                        ?>
                    </span>
                    <a class="nav-item nav-link logout-btn d-flex align-items-center" style="padding: 10px 20px; background: rgba(0, 128, 128, 1); color: #000;" href="#" data-bs-toggle="modal" data-bs-target="#logoutModal">
                        <i class="fas fa-sign-out-alt me-1"></i>Logout
                    </a>
                </div>
            <?php endif; ?>
        </div>
    </div>
</nav>

<script>
document.addEventListener('DOMContentLoaded', function() {
    // Get the navbar toggler and collapse element
    const navbarToggler = document.querySelector('.navbar-toggler');
    const navbarCollapse = document.querySelector('.navbar-collapse');

    // Close navbar when clicking outside
    document.addEventListener('click', function(event) {
        const isClickInside = navbarToggler.contains(event.target) || navbarCollapse.contains(event.target);
        
        if (!isClickInside && navbarCollapse.classList.contains('show')) {
            navbarCollapse.classList.remove('show');
        }
    });

    // Close navbar when clicking nav-links
    const navLinks = document.querySelectorAll('.nav-link');
    navLinks.forEach(link => {
        link.addEventListener('click', () => {
            if (navbarCollapse.classList.contains('show')) {
                navbarCollapse.classList.remove('show');
            }
        });
    });
});
</script>

<!-- Logout Confirmation Modal -->
<div class="modal fade" id="logoutModal" tabindex="-1" aria-labelledby="logoutModalLabel" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title" id="logoutModalLabel">Confirm Logout</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>
      <div class="modal-body">
        Are you sure you want to log out?
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
        <a href="<?php echo BASE_URL; ?>/auth/logout.php" class="btn btn-primary">Logout</a>
      </div>
    </div>
  </div>
</div>

<div class="container mt-4"> 