<?php
require_once __DIR__ . '/../includes/init.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';
requireLogin();

if (getUserRole() !== 'admin') {
    header("Location: " . BASE_URL . "/index.php");
    exit();
}

// Add these at the top of the file with other includes
require '../vendor/autoload.php'; // For PHPMailer
require_once '../config/email_config.php'; // For email settings

// At the beginning of the POST handling section
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['action'])) {
        switch ($_POST['action']) {
            case 'reset_password':
                $user_id = cleanInput($_POST['user_id']);
                $username = cleanInput($_POST['username']);
                // Reset password to username
                $password = password_hash($username, PASSWORD_DEFAULT);
                
                $stmt = $conn->prepare("UPDATE users SET password = ? WHERE user_id = ?");
                $stmt->bind_param("si", $password, $user_id);
                
                if ($stmt->execute()) {
                    $_SESSION['success_message'] = "Password reset successfully for user: " . $username;
                } else {
                    $_SESSION['error_message'] = "Failed to reset password";
                }
                break;
                
            case 'delete':
                $user_id = cleanInput($_POST['user_id']);
                $stmt = $conn->prepare("DELETE FROM users WHERE user_id = ? AND role != 'admin'");
                $stmt->bind_param("i", $user_id);
                
                if ($stmt->execute()) {
                    $_SESSION['success_message'] = "User deleted successfully";
                } else {
                    $_SESSION['error_message'] = "Failed to delete user";
                }
                break;

            case 'resend_verification':
                $user_id = cleanInput($_POST['user_id']);
                $email = cleanInput($_POST['email']);
                
                // Generate new verification code
                $verification_code = sprintf("%06d", mt_rand(1, 999999));
                $code_expiry = date('Y-m-d H:i:s', strtotime('+1 hour'));
                
                $stmt = $conn->prepare("UPDATE users SET verification_code = ?, code_expiry = ?, email_verified = 0 WHERE user_id = ?");
                $stmt->bind_param("ssi", $verification_code, $code_expiry, $user_id);
                
                if ($stmt->execute()) {
                    // Send verification email using PHPMailer
                    $mail = new PHPMailer\PHPMailer\PHPMailer(true);

                    try {
                        $mail->isSMTP();
                        $mail->Host = SMTP_HOST;
                        $mail->SMTPAuth = true;
                        $mail->Username = SMTP_USERNAME;
                        $mail->Password = SMTP_PASSWORD;
                        $mail->SMTPSecure = PHPMailer\PHPMailer\PHPMailer::ENCRYPTION_STARTTLS;
                        $mail->Port = 587;

                        $mail->setFrom(SMTP_USERNAME, SMTP_FROM_NAME);
                        $mail->addAddress($email);

                        $mail->isHTML(true);
                        $mail->Subject = 'New Verification Code - Attendance System';
                        $mail->Body = "
                            <html>
                            <body style='font-family: Arial, sans-serif;'>
                                <div style='max-width: 600px; margin: 0 auto; padding: 20px;'>
                                    <h2>New Verification Code</h2>
                                    <p>Your new verification code is: <strong>{$verification_code}</strong></p>
                                    <p>This code will expire in 1 hour.</p>
                                </div>
                            </body>
                            </html>";

                        $mail->send();
                        $_SESSION['success_message'] = "Verification code resent successfully";
                    } catch (Exception $e) {
                        error_log("Email Error: {$mail->ErrorInfo}");
                        $_SESSION['error_message'] = "Failed to send verification email";
                    }
                } else {
                    $_SESSION['error_message'] = "Failed to generate new verification code";
                }
                
                // Redirect after processing
                header("Location: " . $_SERVER['PHP_SELF']);
                exit();
        }
        
        // Redirect after any action
        header("Location: " . $_SERVER['PHP_SELF']);
        exit();
    }
}

// At the top of the page, after session start
if (isset($_SESSION['success_message'])) {
    $success = $_SESSION['success_message'];
    unset($_SESSION['success_message']);
} else {
    $success = null;
}

if (isset($_SESSION['error_message'])) {
    $error = $_SESSION['error_message'];
    unset($_SESSION['error_message']);
} else {
    $error = null;
}

// Get all users with their details
$query = "SELECT u.*, 
          CASE 
            WHEN u.role = 'student' THEN s.firstname
            WHEN u.role = 'teacher' THEN t.firstname
            ELSE 'Admin'
          END as firstname,
          CASE 
            WHEN u.role = 'student' THEN s.lastname
            WHEN u.role = 'teacher' THEN t.lastname
            ELSE 'Administrator'
          END as lastname,
          CASE 
            WHEN u.role = 'student' THEN s.email
            ELSE NULL
          END as email,
          u.email_verified,
          u.code_expiry
          FROM users u
          LEFT JOIN students s ON u.reference_id = s.student_id AND u.role = 'student'
          LEFT JOIN teachers t ON u.reference_id = t.teacher_id AND u.role = 'teacher'
          ORDER BY u.role, lastname, firstname";

$users = $conn->query($query);

// Now that all processing is done and no further redirects will occur, output the page header
include __DIR__ . '/../includes/header.php';
?>

<div class="row mb-4">
    <div class="col-md-12">
        <h2 class="fw-bold text-primary mb-2"><i class="fas fa-users me-2"></i>Manage User Accounts</h2>
        <hr class="mb-0">
    </div>
</div>

<!-- Users Table -->
<div class="card border-0 shadow-lg rounded-4 mb-4">
    <div class="card-body p-0">
        <div class="table-responsive" style="height: 650px; overflow-y: auto;">
            <table class="table table-hover align-middle mb-0">
                <thead class="sticky-top bg-light shadow-sm">
                    <tr class="align-middle">
                        <th class="text-primary fw-bold">Username</th>
                        <th class="text-primary fw-bold">Name</th>
                        <th class="text-primary fw-bold">Role</th>
                        <th class="text-primary fw-bold">Email</th>
                        <th class="text-primary fw-bold">Verification Status</th>
                        <th class="text-primary fw-bold text-center">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php while ($user = $users->fetch_assoc()): ?>
                        <tr class="user-row">
                            <td><?php echo $user['username']; ?></td>
                            <td><?php echo $user['lastname'] . ', ' . $user['firstname']; ?></td>
                            <td>
                                <span class="badge rounded-pill px-3 py-2 bg-<?php 
                                    echo $user['role'] === 'admin' ? 'danger' : 
                                        ($user['role'] === 'teacher' ? 'success' : 'primary'); 
                                ?> text-capitalize fs-7">
                                    <i class="fas fa-<?php
                                        echo $user['role'] === 'admin' ? 'user-shield' :
                                            ($user['role'] === 'teacher' ? 'chalkboard-teacher' : 'user-graduate');
                                    ?> me-1"></i>
                                    <?php echo ucfirst($user['role']); ?>
                                </span>
                            </td>
                            <td><?php echo $user['email'] ?? '<span class="text-muted">N/A</span>'; ?></td>
                            <td>
                                <?php if ($user['role'] === 'student'): ?>
                                    <?php if ($user['email_verified']): ?>
                                        <span class="badge bg-success rounded-pill px-3 py-2"><i class="fas fa-check-circle me-1"></i>Verified</span>
                                    <?php else: ?>
                                        <span class="badge bg-warning text-dark rounded-pill px-3 py-2"><i class="fas fa-clock me-1"></i>Pending</span>
                                        <?php if ($user['code_expiry'] && strtotime($user['code_expiry']) < time()): ?>
                                            <span class="badge bg-danger rounded-pill px-3 py-2"><i class="fas fa-times-circle me-1"></i>Expired</span>
                                        <?php endif; ?>
                                    <?php endif; ?>
                                <?php else: ?>
                                    <span class="badge bg-secondary rounded-pill px-3 py-2">N/A</span>
                                <?php endif; ?>
                            </td>
                            <td class="text-center">
                                <?php if ($user['role'] !== 'admin'): ?>
                                    <button type="button" 
                                            class="btn btn-outline-warning btn-sm me-1 d-inline-flex align-items-center" 
                                            data-bs-toggle="modal" 
                                            data-bs-target="#resetPasswordModal"
                                            data-user-id="<?php echo $user['user_id']; ?>"
                                            data-username="<?php echo $user['username']; ?>">
                                        <i class="fas fa-key me-1"></i>Reset
                                    </button>
                                    <?php if ($user['role'] === 'student' && !$user['email_verified']): ?>
                                        <button type="button" 
                                                class="btn btn-outline-info btn-sm me-1 d-inline-flex align-items-center"
                                                data-bs-toggle="modal" 
                                                data-bs-target="#resendVerificationModal"
                                                data-user-id="<?php echo $user['user_id']; ?>"
                                                data-email="<?php echo $user['email']; ?>"
                                                data-username="<?php echo $user['username']; ?>">
                                            <i class="fas fa-paper-plane me-1"></i>Resend
                                        </button>
                                    <?php endif; ?>
                                    <button type="button" 
                                            class="btn btn-outline-danger btn-sm d-inline-flex align-items-center" 
                                            data-bs-toggle="modal" 
                                            data-bs-target="#deleteUserModal"
                                            data-user-id="<?php echo $user['user_id']; ?>"
                                            data-username="<?php echo $user['username']; ?>"
                                            title="Delete User">
                                        <i class="fas fa-trash me-1"></i>Delete
                                    </button>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endwhile; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<!-- Reset Password Modal -->
<div class="modal fade" id="resetPasswordModal" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content rounded-4 shadow border-0">
            <div class="modal-header bg-warning text-dark rounded-top-4">
                <h5 class="modal-title"><i class="fas fa-key me-2"></i>Reset Password</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST">
                <div class="modal-body">
                    <input type="hidden" name="action" value="reset_password">
                    <input type="hidden" name="user_id" id="resetUserId">
                    <input type="hidden" name="username" id="resetUsername">
                    <p class="mb-2">Are you sure you want to reset the password for <strong id="resetUserDisplay"></strong>?</p>
                    <p class="text-muted mb-0">The password will be reset to their username.</p>
                </div>
                <div class="modal-footer bg-light rounded-bottom-4">
                    <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-warning"><i class="fas fa-key me-1"></i>Reset Password</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Delete User Modal -->
<div class="modal fade" id="deleteUserModal" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content rounded-4 shadow border-0">
            <div class="modal-header bg-danger text-white rounded-top-4">
                <h5 class="modal-title"><i class="fas fa-trash me-2"></i>Delete User</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST">
                <div class="modal-body">
                    <input type="hidden" name="action" value="delete">
                    <input type="hidden" name="user_id" id="deleteUserId">
                    <p class="mb-2">Are you sure you want to delete user <strong id="deleteUserDisplay"></strong>?</p>
                    <p class="text-danger mb-0"><i class="fas fa-exclamation-triangle me-1"></i>This action cannot be undone.</p>
                </div>
                <div class="modal-footer bg-light rounded-bottom-4">
                    <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-danger"><i class="fas fa-trash me-1"></i>Delete User</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Resend Verification Modal -->
<div class="modal fade" id="resendVerificationModal" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content rounded-4 shadow border-0">
            <div class="modal-header bg-info text-white rounded-top-4">
                <h5 class="modal-title"><i class="fas fa-paper-plane me-2"></i>Resend Verification Code</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST">
                <div class="modal-body">
                    <input type="hidden" name="action" value="resend_verification">
                    <input type="hidden" name="user_id" id="verifyUserId">
                    <input type="hidden" name="email" id="verifyEmail">
                    <p class="mb-2">Resend verification code to <strong id="verifyUserDisplay"></strong>?</p>
                    <p class="text-muted mb-0">A new verification code will be sent to their email.</p>
                </div>
                <div class="modal-footer bg-light rounded-bottom-4">
                    <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-info"><i class="fas fa-paper-plane me-1"></i>Resend Code</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
$(document).ready(function() {
    // Reset Password Modal
    $('#resetPasswordModal').on('show.bs.modal', function (event) {
        const button = $(event.relatedTarget);
        const userId = button.data('user-id');
        const username = button.data('username');
        
        $('#resetUserId').val(userId);
        $('#resetUsername').val(username);
        $('#resetUserDisplay').text(username);
    });
    
    // Delete User Modal
    $('#deleteUserModal').on('show.bs.modal', function (event) {
        const button = $(event.relatedTarget);
        const userId = button.data('user-id');
        const username = button.data('username');
        
        $('#deleteUserId').val(userId);
        $('#deleteUserDisplay').text(username);
    });

    // Resend Verification Modal
    $('#resendVerificationModal').on('show.bs.modal', function (event) {
        const button = $(event.relatedTarget);
        const userId = button.data('user-id');
        const email = button.data('email');
        const username = button.data('username');
        
        $('#verifyUserId').val(userId);
        $('#verifyEmail').val(email);
        $('#verifyUserDisplay').text(username);
    });
});
</script>

<!-- Floating Notification Container -->
<div id="floatingNotif" style="display:none;position:fixed;top:30px;right:30px;z-index:1080;min-width:300px;max-width:400px;pointer-events:none;">
    <div id="notifContent" class="shadow-lg rounded-3 px-4 py-3 fw-semibold d-flex align-items-center" style="background:#fff;border-left:6px solid #198754;gap:12px;font-size:1.1rem;min-height:48px;box-shadow:0 4px 24px rgba(0,0,0,0.12);">
        <span id="notifIcon" class="me-2"></span>
        <span id="notifMsg"></span>
    </div>
</div>

<script>
function showFloatingNotif(type, message) {
    const notif = document.getElementById('floatingNotif');
    const notifContent = document.getElementById('notifContent');
    const notifIcon = document.getElementById('notifIcon');
    const notifMsg = document.getElementById('notifMsg');
    // Set styles and icon
    if (type === 'success') {
        notifContent.style.borderLeftColor = '#198754';
        notifContent.style.background = '#e9fbe9';
        notifIcon.innerHTML = '<i class="fas fa-check-circle text-success fa-lg"></i>';
    } else {
        notifContent.style.borderLeftColor = '#dc3545';
        notifContent.style.background = '#fbe9e9';
        notifIcon.innerHTML = '<i class="fas fa-exclamation-circle text-danger fa-lg"></i>';
    }
    notifMsg.textContent = message;
    notif.style.display = 'block';
    notif.style.pointerEvents = 'auto';
    // Auto-dismiss after 3 seconds
    setTimeout(() => {
        notif.style.display = 'none';
        notif.style.pointerEvents = 'none';
    }, 3000);
}
// Show notification if PHP sets a message
window.addEventListener('DOMContentLoaded', function() {
    // PHP will echo the JS values for success/error
    const success = <?php echo $success ? json_encode($success) : 'null'; ?>;
    const error = <?php echo $error ? json_encode($error) : 'null'; ?>;
    if (success) {
        showFloatingNotif('success', success);
    } else if (error) {
        showFloatingNotif('error', error);
    }
});

</script>