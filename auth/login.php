<?php
// Start session if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Include required files
require_once "../includes/config.php";
require_once "../config/database.php";
require_once "../includes/functions.php";
require_once "../includes/auth.php";

// Check if the user is already logged in, if yes then redirect to dashboard
if (isLoggedIn()) {
    header("Location: " . BASE_URL . "/index.php");
    exit();
}

$login_err = '';

// Processing form data when form is submitted
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $username = cleanInput($_POST['username']);
    $password = $_POST['password'];
    
    $sql = "SELECT u.*, 
            COALESCE(s.firstname, t.firstname) as firstname,
            COALESCE(s.lastname, t.lastname) as lastname
            FROM users u 
            LEFT JOIN students s ON u.reference_id = s.student_id AND u.role = 'student'
            LEFT JOIN teachers t ON u.reference_id = t.teacher_id AND u.role = 'teacher'
            WHERE u.username = ?";
            
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("s", $username);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows === 1) {
        $user = $result->fetch_assoc();
        $stmt->close(); // Close the first statement
        
        if (password_verify($password, $user['password'])) {
            $_SESSION['user_id'] = $user['user_id'];
            $_SESSION['username'] = $user['username'];
            $_SESSION['role'] = $user['role'];
            $_SESSION['logged_in'] = true;
            $_SESSION['name'] = trim(($user['firstname'] ?? '') . ' ' . ($user['lastname'] ?? ''));

            // Set teacher_id for teacher role
            if ($user['role'] === 'teacher') {
                $stmt2 = $conn->prepare("SELECT reference_id FROM users WHERE user_id = ?");
                $stmt2->bind_param("i", $user['user_id']);
                $stmt2->execute();
                $result2 = $stmt2->get_result();
                if ($teacherData = $result2->fetch_assoc()) {
                    $_SESSION['teacher_id'] = $teacherData['reference_id'];
                }
                $stmt2->close();
            }

            if ($user['role'] === 'student') {
                $stmt3 = $conn->prepare("SELECT reference_id FROM users WHERE user_id = ?");
                $stmt3->bind_param("i", $user['user_id']);
                $stmt3->execute();
                $result3 = $stmt3->get_result();
                if ($studentData = $result3->fetch_assoc()) {
                    $_SESSION['student_id'] = $studentData['reference_id'];
                }
                $stmt3->close();
            }

            // Debug log
            error_log("Login session data: " . print_r($_SESSION, true));
            
            header("Location: " . BASE_URL . "/index.php");
            exit();
        } else {
            $login_err = "Invalid username or password.";
        }
    } else {
        $login_err = "Invalid username or password.";
        $stmt->close();
    }
}
?>

<div class="login-container">
    <div class="login-wrapper">
        <div class="login-card">
            <div class="login-header">
                <div class="logo-container">
                    <img src="../free-user-icon-3296-thumb.png" alt="School Logo" class="logo">
                    <div class="logo-text">
                        <h2>Attendance<span>Pro</span></h2>
                        <p>Smart Attendance Management System</p>
                    </div>
                </div>
            </div>
            
            <div class="login-body">
                <?php if ($login_err): ?>
                    <div class="alert alert-danger alert-dismissible fade show" role="alert">
                        <i class="fas fa-exclamation-circle me-2"></i>
                        <?php echo htmlspecialchars($login_err); ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                    </div>
                <?php endif; ?>
                
                <form method="POST" action="" class="login-form" id="loginForm">
                    <div class="form-group">
                        <label for="username" class="form-label">
                            <i class="fas fa-user"></i> Username or ID
                        </label>
                        <div class="input-group">
                            <span class="input-group-text">
                                <i class="fas fa-user-graduate"></i>
                            </span>
                            <input type="text" class="form-control" id="username" name="username" 
                                   placeholder="Enter your username or ID" required 
                                   value="<?php echo isset($_POST['username']) ? htmlspecialchars($_POST['username']) : ''; ?>">
                        </div>
                    </div>
                    
                    <div class="form-group">
                        <label for="password" class="form-label">
                            <i class="fas fa-lock"></i> Password
                        </label>
                        <div class="input-group">
                            <span class="input-group-text">
                                <i class="fas fa-key"></i>
                            </span>
                            <input type="password" class="form-control" id="password" name="password" 
                                   placeholder="Enter your password" required>
                            <button class="btn btn-outline-secondary toggle-password" type="button">
                                <i class="fas fa-eye"></i>
                            </button>
                        </div>
                        <!-- <div class="d-flex justify-content-between mt-2">
                            <div class="form-check">
                                <input type="checkbox" class="form-check-input" id="rememberMe" name="remember_me">
                                <label class="form-check-label" for="rememberMe">Remember me</label>
                            </div>
                            <a href="forgot-password.php" class="forgot-password">Forgot Password?</a>
                        </div> -->
                    </div>
                    
                    <button type="submit" class="btn btn-primary w-100 login-btn">
                        <i class="fas fa-sign-in-alt me-2"></i> Sign In
                    </button>
                </form>
            </div>
            
            <div class="login-footer text-center">
                <p class="mb-0">
                    &copy; <?php echo date('Y'); ?> AttendancePro. All rights reserved.
                    <a href="#" class="text-muted ms-2">Privacy Policy</a> | 
                    <a href="#" class="text-muted">Terms of Service</a>
                </p>
            </div>
        </div>
        
        <div class="login-features">
            <div class="feature active">
                <i class="fas fa-clock"></i>
                <h4>Real-time Tracking</h4>
                <p>Monitor attendance in real-time with our advanced tracking system.</p>
            </div>
            <div class="feature">
                <i class="fas fa-chart-line"></i>
                <h4>Analytics Dashboard</h4>
                <p>Gain insights with detailed attendance reports and analytics.</p>
            </div>
            <div class="feature">
                <i class="fas fa-mobile-alt"></i>
                <h4>Mobile Friendly</h4>
                <p>Access the system from any device, anywhere, anytime.</p>
            </div>
        </div>
    </div>
</div>

<!-- Add Font Awesome for icons -->
<link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">

<style>
:root {
    --primary-color: #008080;
    --primary-light: #eef2ff;
    --secondary-color: #6c757d;
    --success-color: #28a745;
    --danger-color: #dc3545;
    --light-color: #f8f9fa;
    --dark-color: #343a40;
    --border-radius: 8px;
    --box-shadow: 0 5px 15px rgba(0, 0, 0, 0.05);
    --transition: all 0.3s ease;
}

* {
    margin: 0;
    padding: 0;
    box-sizing: border-box;
}

body {
    font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
    line-height: 1.6;
    height: 100vh;
    display: flex;
    align-items: center;
    justify-content: center;
    background: url('../main-bg.png') no-repeat center center fixed;
    background-size: cover;
    position: relative;
    z-index: -2;
}

body::before {
    content: '';
    position: absolute;
    top: 0;
    left: 0;
    right: 0;
    bottom: 0;
    background: rgba(255, 255, 255, 0.7);
    z-index: -1;
}

.login-container {
    width: 100%;
    max-width: 1200px;
    margin: 0 auto;
    padding: 20px;
    position: relative;
    z-index: 1;
}

.login-wrapper {
    display: flex;
    background: white;
    border-radius: var(--border-radius);
    overflow: hidden;
    box-shadow: 0 10px 30px rgba(0, 0, 0, 0.1);
    height: 700px;
}

.login-card {
    flex: 1;
    padding: 40px;
    display: flex;
    flex-direction: column;
    background: white;
    max-width: 500px;
}

.login-header {
    margin-bottom: 30px;
    text-align: center;
}

.logo-container {
    text-align: center;
    margin-bottom: 30px;
}

.logo {
    width: 80px;
    height: 80px;
    margin-bottom: 15px;
    object-fit: contain;
}

.logo-text h2 {
    font-size: 1.8rem;
    font-weight: 700;
    color: var(--dark-color);
    margin-bottom: 5px;
}

.logo-text h2 span {
    color: var(--primary-color);
}

.logo-text p {
    color: var(--secondary-color);
    font-size: 0.9rem;
    margin: 0;
}

.login-body {
    flex: 1;
    overflow-y: auto;
    padding-right: 10px;
}

/* Form styles */
.form-group {
    margin-bottom: 1.5rem;
}

.form-label {
    font-weight: 500;
    color: var(--dark-color);
    margin-bottom: 0.5rem;
    display: block;
}

.input-group {
    position: relative;
    display: flex;
    align-items: stretch;
    width: 100%;
    margin-bottom: 1rem;
}

.input-group-text {
    display: flex;
    align-items: center;
    padding: 0 1rem;
    background-color: #f8f9fa;
    border: 1px solid #ced4da;
    border-right: none;
    border-radius: 0.25rem 0 0 0.25rem;
}

.form-control {
    position: relative;
    flex: 1 1 auto;
    width: 1%;
    min-width: 0;
    padding: 0.75rem 1rem;
    font-size: 1rem;
    font-weight: 400;
    line-height: 1.5;
    color: #495057;
    background-color: #fff;
    background-clip: padding-box;
    border: 1px solid #ced4da;
    border-left: none;
    border-radius: 0 0.25rem 0.25rem 0;
    transition: border-color 0.15s ease-in-out, box-shadow 0.15s ease-in-out;
    height: auto;
}

.toggle-password {
    display: flex;
    align-items: center;
    justify-content: center;
    padding: 0 1rem;
    background-color: #f8f9fa;
    border: 1px solid #ced4da;
    border-left: none;
    border-radius: 0 0.25rem 0.25rem 0;
    cursor: pointer;
    color: #6c757d;
    transition: background-color 0.15s ease-in-out;
}

.toggle-password:hover {
    background-color: #e9ecef;
    color: #495057;
}

.input-group-text {
    background-color: var(--primary-light);
    border: 1px solid #dee2e6;
    border-right: none;
    border-radius: var(--border-radius) 0 0 var(--border-radius);
    padding: 0.75rem 1rem;
    color: var(--primary-color);
}

.form-control {
    width: 100%;
    padding: 0.75rem 1rem;
    font-size: 1rem;
    line-height: 1.5;
    color: #495057;
    background-color: #fff;
    background-clip: padding-box;
    border: 1px solid #dee2e6;
    border-radius: 0 var(--border-radius) var(--border-radius) 0;
    transition: var(--transition);
}

.form-control:focus {
    border-color: var(--primary-color);
    box-shadow: 0 0 0 0.25rem rgba(67, 97, 238, 0.15);
    outline: 0;
}

.toggle-password {
    background: none;
    border: 1px solid #dee2e6;
    border-left: none;
    border-radius: 0 var(--border-radius) var(--border-radius) 0;
    padding: 0.75rem 1rem;
    color: var(--secondary-color);
    cursor: pointer;
    transition: var(--transition);
}

.toggle-password:hover {
    background-color: #f8f9fa;
    color: var(--primary-color);
}

/* Login button */
.login-btn {
    background-color: var(--primary-color);
    color: white;
    border: none;
    padding: 12px;
    font-size: 1rem;
    font-weight: 600;
    border-radius: var(--border-radius);
    cursor: pointer;
    transition: var(--transition);
    margin-top: 10px;
    width: 100%;
}

.login-btn:hover {
    background-color: #3a56d4;
    transform: translateY(-2px);
    box-shadow: 0 5px 15px rgba(67, 97, 238, 0.3);
}

/* Login footer */
.login-footer {
    margin-top: auto;
    padding-top: 20px;
    border-top: 1px solid #eee;
    font-size: 0.8rem;
    color: var(--secondary-color);
}

.login-footer a {
    color: var(--secondary-color);
    text-decoration: none;
    transition: var(--transition);
}

.login-footer a:hover {
    color: var(--primary-color);
    text-decoration: underline;
}

/* Features section */
.login-features {
    flex: 1;
    background: linear-gradient(135deg, #008080, #008080);
    color: black;
    padding: 40px;
    display: flex;
    flex-direction: column;
    justify-content: center;
    position: relative;
    overflow: hidden;
}

.login-features::before {
    content: '';
    position: absolute;
    top: 0;
    left: 0;
    right: 0;
    bottom: 0;
    background: url('../assets/images/pattern.png') center/cover;
    opacity: 0.1;
    z-index: 1;
}

.feature {
    margin-bottom: 2.5rem;
    position: relative;
    z-index: 2;
    opacity: 0.9;
    transition: var(--transition);
    padding: 20px;
    border-radius: var(--border-radius);
    cursor: pointer;
}

.feature:hover {
    background: rgba(255, 255, 255, 0.1);
    transform: translateY(-5px);
    opacity: 1;
}

.feature i {
    font-size: 2.5rem;
    margin-bottom: 1rem;
    color: rgba(255, 255, 255, 0.9);
}

.feature h4 {
    font-size: 1.25rem;
    margin-bottom: 0.75rem;
    font-weight: 600;
}

.feature p {
    font-size: 0.95rem;
    opacity: 0.9;
    margin: 0;
}

.feature.active {
    opacity: 1;
}

/* Responsive styles */
@media (max-width: 992px) {
    .login-wrapper {
        flex-direction: column;
        height: auto;
        max-width: 500px;
        margin: 20px auto;
    }
    
    .login-card {
        max-width: 100%;
        padding: 30px;
    }
    
    .login-features {
        display: none;
    }
}

@media (max-width: 576px) {
    .login-container {
        padding: 10px;
    }
    
    .login-card {
        padding: 20px;
    }
    
    .logo-text h2 {
        font-size: 1.5rem;
    }
    
    .logo-text p {
        font-size: 0.8rem;
    }
}

/* Animations */
@keyframes fadeIn {
    from { opacity: 0; transform: translateY(10px); }
    to { opacity: 1; transform: translateY(0); }
}

.login-card {
    animation: fadeIn 0.5s ease-out;
}

.feature {
    animation: fadeIn 0.5s ease-out forwards;
}

.feature:nth-child(1) { animation-delay: 0.1s; }
.feature:nth-child(2) { animation-delay: 0.3s; }
.feature:nth-child(3) { animation-delay: 0.5s; }
</style>

<script>
document.addEventListener('DOMContentLoaded', function() {
    // Toggle password visibility
    const togglePassword = document.querySelector('.toggle-password');
    const password = document.getElementById('password');
    
    if (togglePassword && password) {
        togglePassword.addEventListener('click', function() {
            const type = password.getAttribute('type') === 'password' ? 'text' : 'password';
            password.setAttribute('type', type);
            this.innerHTML = type === 'password' ? 
                '<i class="fas fa-eye"></i>' : 
                '<i class="fas fa-eye-slash"></i>';
        });
    }

    // Add animation to features on hover
    const features = document.querySelectorAll('.feature');
    features.forEach(feature => {
        feature.addEventListener('mouseenter', function() {
            features.forEach(f => f.classList.remove('active'));
            this.classList.add('active');
        });
    });
});
</script>

<?php include '../includes/footer.php'; ?>