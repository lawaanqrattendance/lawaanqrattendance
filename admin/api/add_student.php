<?php
header('Content-Type: application/json');
require_once '../../includes/config.php';
require_once '../../config/database.php';
require_once '../../includes/functions.php';
require_once '../../includes/auth.php';
require '../../vendor/autoload.php'; // Add this for PHPMailer
require_once '../../config/email_config.php'; // Add this for email settings

if (!isLoggedIn() || getUserRole() !== 'teacher') {
    echo json_encode(['success' => false, 'error' => 'Unauthorized access']);
    exit();
}

try {
    $student_id = cleanInput($_POST['student_id']);
    $firstname = cleanInput($_POST['firstname']);
    $lastname = cleanInput($_POST['lastname']);
    $middlename = cleanInput($_POST['middlename']);
    $email = cleanInput($_POST['email']);
    $section_id = cleanInput($_POST['section_id']);
    $teacher_id = $_SESSION['teacher_id'];
    // Optional guardian email
    $guardian_email = isset($_POST['guardian_email']) ? cleanInput($_POST['guardian_email']) : null;

    // Verify if teacher has access to this section
    $stmt = $conn->prepare("SELECT COUNT(*) as count FROM teacher_sections WHERE teacher_id = ? AND section_id = ?");
    $stmt->bind_param("ii", $teacher_id, $section_id);
    $stmt->execute();
    $result = $stmt->get_result()->fetch_assoc();

    if ($result['count'] == 0) {
        throw new Exception('You do not have permission to add students to this section');
    }

    // Get grade level from section
    $stmt = $conn->prepare("SELECT grade_level FROM sections WHERE section_id = ?");
    $stmt->bind_param("i", $section_id);
    $stmt->execute();
    $grade_level = $stmt->get_result()->fetch_assoc()['grade_level'];

    $conn->begin_transaction();

    // Check if student ID already exists
    $stmt = $conn->prepare("SELECT student_id FROM students WHERE student_id = ?");
    $stmt->bind_param("s", $student_id);
    $stmt->execute();
    if ($stmt->get_result()->num_rows > 0) {
        throw new Exception('Student ID already exists');
    }

    // Insert student (guardian_email is nullable)
    $stmt = $conn->prepare("INSERT INTO students (student_id, firstname, lastname, middlename, email, guardian_email, grade_level, section_id) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
    $stmt->bind_param("ssssssii", $student_id, $firstname, $lastname, $middlename, $email, $guardian_email, $grade_level, $section_id);
    
    if (!$stmt->execute()) {
        error_log("MySQL Error: " . $stmt->error);
        throw new Exception('Failed to add student: ' . $stmt->error);
    }

    // Generate verification code (6-digit code)
    $verification_code = sprintf("%06d", mt_rand(1, 999999));
    $expiry = date('Y-m-d H:i:s', strtotime('+1 hour'));
    
    // Store verification code in users table
    $stmt = $conn->prepare("INSERT INTO users (username, password, role, reference_id, verification_code, code_expiry, email_verified) 
                           VALUES (?, ?, 'student', ?, ?, ?, 0)");
    $username = strtolower($firstname . '.' . $lastname);
    $temp_password = bin2hex(random_bytes(4)); // 8 characters
    $hashed_password = password_hash($temp_password, PASSWORD_DEFAULT);
    $stmt->bind_param("sssss", $username, $hashed_password, $student_id, $verification_code, $expiry);
    
    if (!$stmt->execute()) {
        throw new Exception('Failed to create user account');
    }

    // Send verification email
    if (!sendVerificationEmail($email, $verification_code, $username, $temp_password)) {
        throw new Exception('Failed to send welcome email');
    }

    $conn->commit();
    echo json_encode([
        'success' => true,
        'message' => 'Student added successfully. Verification email has been sent.'
    ]);

} catch (Exception $e) {
    if (isset($conn)) {
        $conn->rollback();
    }
    error_log("Error in add_student.php: " . $e->getMessage());
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ]);
}

// Update the sendVerificationEmail function to include password
function sendVerificationEmail($to, $code, $username, $temp_password) {
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
        $mail->addAddress($to);

        $mail->isHTML(true);
        $mail->Subject = 'Welcome to Attendance System - Account Details';
        $mail->Body = "
            <html>
            <body style='font-family: Arial, sans-serif;'>
                <div style='max-width: 600px; margin: 0 auto; padding: 20px;'>
                    <h2>Welcome to the Attendance System</h2>
                    <p>Your account has been created with the following details:</p>
                    <p><strong>Username:</strong> {$username}</p>
                    <p><strong>Temporary Password:</strong> {$temp_password}</p>
                    <p><strong>Verification Code:</strong> {$code}</p>
                    <p>Please login and verify your email using the verification code above.</p>
                    <p>The verification code will expire in 1 hour.</p>
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