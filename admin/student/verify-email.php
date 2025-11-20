<?php
require '../../vendor/autoload.php';
include '../../includes/header.php';
require_once '../../config/email_config.php';
requireLogin();

if (getUserRole() !== 'student') {
    header("Location: " . BASE_URL . "/index.php");
    exit();
}

$user_id = $_SESSION['user_id'];
$error = '';
$success = '';

// Check if user needs verification
$stmt = $conn->prepare("SELECT u.verification_code, u.code_expiry, u.email_verified, s.email 
                       FROM users u 
                       JOIN students s ON u.reference_id = s.student_id 
                       WHERE u.user_id = ?");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$result = $stmt->get_result();
$user = $result->fetch_assoc();

if ($user['email_verified']) {
    header("Location: " . BASE_URL . "/admin/student/dashboard.php");
    exit();
}

// Automatically generate and send code if none exists or is expired
if (!isset($user['verification_code']) || 
    empty($user['verification_code']) || 
    $user['code_expiry'] < date('Y-m-d H:i:s')) {
    
    // Generate new code
    $new_code = sprintf("%06d", mt_rand(1, 999999));
    $expiry = date('Y-m-d H:i:s', strtotime('+1 hour'));
    
    $stmt = $conn->prepare("UPDATE users SET verification_code = ?, code_expiry = ? WHERE user_id = ?");
    $stmt->bind_param("ssi", $new_code, $expiry, $user_id);
    
    if ($stmt->execute()) {
        // Send verification email
        if (sendVerificationEmail($user['email'], $new_code)) {
            $success = "Verification code sent to your email!";
        } else {
            $error = "Error sending verification code. Please try the resend button.";
        }
    }
}

function sendVerificationEmail($to, $code) {
    $mail = new PHPMailer\PHPMailer\PHPMailer(true);

    try {
        // Server settings
        $mail->isSMTP();
        $mail->Host = SMTP_HOST;
        $mail->SMTPAuth = true;
        $mail->Username = SMTP_USERNAME;
        $mail->Password = SMTP_PASSWORD;
        $mail->SMTPSecure = PHPMailer\PHPMailer\PHPMailer::ENCRYPTION_STARTTLS;
        $mail->Port = 587;

        // Recipients
        $mail->setFrom(SMTP_USERNAME, SMTP_FROM_NAME);
        $mail->addAddress($to);

        // Content
        $mail->isHTML(true);
        $mail->Subject = 'Verification Code - Attendance System';
        $mail->Body    = "
            <html>
            <body style='font-family: Arial, sans-serif;'>
                <div style='max-width: 600px; margin: 0 auto; padding: 20px;'>
                    <h2 style='color: #333;'>Email Verification</h2>
                    <p>Your verification code is: <strong style='font-size: 24px; color: #007bff;'>{$code}</strong></p>
                    <p>This code will expire in 1 hour.</p>
                </div>
            </body>
            </html>";

        $mail->send();
        return true;
    } catch (Exception $e) {
        error_log("Email Error: {$mail->ErrorInfo}");
        return false;
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['verify_code'])) {
        $entered_code = cleanInput($_POST['code']);
        
        if ($user['code_expiry'] < date('Y-m-d H:i:s')) {
            $error = "Verification code has expired. Please request a new one.";
        } elseif ($entered_code === $user['verification_code']) {
            // Update user as verified
            $stmt = $conn->prepare("UPDATE users SET email_verified = 1, verification_date = NOW(), verification_code = NULL WHERE user_id = ?");
            $stmt->bind_param("i", $user_id);
            if ($stmt->execute()) {
                $success = "Email verified successfully!";
                // Redirect after 2 seconds
                header("refresh:2;url=" . BASE_URL . "/admin/student/dashboard.php");
            } else {
                $error = "Error verifying email. Please try again.";
            }
        } else {
            $error = "Invalid verification code. Please try again.";
        }
    } elseif (isset($_POST['resend_code'])) {
        // Generate new code
        $new_code = sprintf("%06d", mt_rand(1, 999999));
        $expiry = date('Y-m-d H:i:s', strtotime('+1 hour'));
        
        $stmt = $conn->prepare("UPDATE users SET verification_code = ?, code_expiry = ? WHERE user_id = ?");
        $stmt->bind_param("ssi", $new_code, $expiry, $user_id);
        
        if ($stmt->execute()) {
            // Send new verification email
            if (sendVerificationEmail($user['email'], $new_code)) {
                $success = "New verification code sent to your email!";
            } else {
                $error = "Error sending verification code. Please try again.";
            }
        } else {
            $error = "Error generating new code. Please try again.";
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Verify Email</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body>
    <div class="container mt-5">
        <div class="row justify-content-center">
            <div class="col-md-6">
                <div class="card">
                    <div class="card-body">
                        <h3 class="card-title text-center mb-4">Verify Your Email</h3>
                        
                        <?php if ($error): ?>
                            <div class="alert alert-danger"><?php echo $error; ?></div>
                        <?php endif; ?>
                        
                        <?php if ($success): ?>
                            <div class="alert alert-success"><?php echo $success; ?></div>
                        <?php endif; ?>
                        
                        <p class="text-center">
                            Please enter the verification code sent to:<br>
                            <strong><?php echo maskEmail($user['email']); ?></strong>
                        </p>
                        
                        <form method="POST" class="mb-3">
                            <div class="mb-3">
                                <input type="text" name="code" class="form-control form-control-lg text-center" 
                                       placeholder="Enter 6-digit code" maxlength="6" required>
                            </div>
                            <button type="submit" name="verify_code" class="btn btn-primary w-100">
                                Verify Code
                            </button>
                        </form>
                        
                        <form method="POST">
                            <button type="submit" name="resend_code" class="btn btn-link w-100">
                                Resend verification code
                            </button>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </div>
</body>
</html> 