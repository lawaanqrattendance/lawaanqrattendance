<?php
include '../../includes/header.php';
requireLogin();

if (getUserRole() !== 'student') {
    header("Location: " . BASE_URL . "/index.php");
    exit();
}

// Check if email verification is required
$user_id = $_SESSION['user_id'];
$stmt = $conn->prepare("SELECT u.email_verified, u.verification_date, s.email 
                       FROM users u 
                       JOIN students s ON u.reference_id = s.student_id 
                       WHERE u.user_id = ?");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$result = $stmt->get_result();
$user = $result->fetch_assoc();

// Check if verification has expired (e.g., after 30 days)
$verification_expired = false;
if ($user['email_verified'] && $user['verification_date']) {
    $verification_date = new DateTime($user['verification_date']);
    $now = new DateTime();
    $interval = $verification_date->diff($now);
    
    if ($interval->days > 30) { // Set to your preferred expiration period
        $verification_expired = true;
        
        // Reset verification status
        $stmt = $conn->prepare("UPDATE users SET email_verified = 0 WHERE user_id = ?");
        $stmt->bind_param("i", $user_id);
        $stmt->execute();
    }
}

if (!$user['email_verified'] || $verification_expired) {
    header("Location: " . BASE_URL . "/admin/student/verify-email.php");
    exit();
}

// Get student_id from users table if not in session
if (!isset($_SESSION['student_id'])) {
    $stmt = $conn->prepare("SELECT reference_id FROM users WHERE user_id = ? AND role = 'student'");
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $result = $stmt->get_result();
    if ($row = $result->fetch_assoc()) {
        $_SESSION['student_id'] = $row['reference_id'];
    } else {
        // Handle error - student not found
        header("Location: " . BASE_URL . "/index.php");
        exit();
    }
}

$student_id = $_SESSION['student_id'];
$current_date = date('Y-m-d');
$day_of_week = date('l'); // Gets current day name

// Get student's schedule for today
$query = "SELECT s.*, 
          sub.subject_name,
          CONCAT(t.lastname, ', ', t.firstname) as teacher_name,
          a.status as attendance_status,
          a.in_time,
          a.out_time,
          a.created_at as attendance_time
          FROM schedules s
          JOIN subjects sub ON s.subject_id = sub.subject_id
          JOIN teachers t ON s.teacher_id = t.teacher_id
          LEFT JOIN attendance a ON s.schedule_id = a.schedule_id 
            AND a.student_id = ? 
            AND a.attendance_date = CURDATE()
          WHERE s.section_id = (SELECT section_id FROM students WHERE student_id = ?)
          AND s.day_of_week = ?
          ORDER BY s.start_time";

$stmt = $conn->prepare($query);
$stmt->bind_param("sss", $student_id, $student_id, $day_of_week);

$stmt->execute();
$schedules = $stmt->get_result();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <!-- Add PWA meta tags -->
    <meta name="mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-capable" content="yes">
    <link rel="manifest" href="<?php echo BASE_URL; ?>/manifest.json">
    <title>Student Dashboard</title>
    
    <!-- Add iOS support -->
    <link rel="apple-touch-icon" href="<?php echo BASE_URL; ?>/icons/icon-192x192.png">
    <meta name="apple-mobile-web-app-status-bar" content="#0d6efd">
    <meta name="theme-color" content="#0d6efd">
    
    <!-- Existing styles and scripts -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/all.min.css">

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>

    <!-- Add install prompt script -->
    <script>
        // Check if service worker is supported
        if ('serviceWorker' in navigator) {
            navigator.serviceWorker.register('<?php echo BASE_URL; ?>/sw.js')
            .then((reg) => console.log('Service worker registered'))
            .catch((err) => console.log('Service worker not registered', err));
        }

        // Handle install prompt
        let deferredPrompt;
        window.addEventListener('beforeinstallprompt', (e) => {
            e.preventDefault();
            deferredPrompt = e;
            // Show install button or prompt
            document.getElementById('installBtn').style.display = 'block';
        });
    </script>
    <style>
        /* Mobile-first styles */
        body {
            background-color: #99CFD0;
        }
        .schedule-card {
            border-radius: 15px;
            margin-bottom: 15px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }
        .schedule-card.current {
            border: 2px solid #0d6efd;
        }
        /* Removed scanner styles */
        .scan-result {
            position: fixed;
            bottom: 20px;
            left: 50%;
            transform: translateX(-50%);
            z-index: 1000;
            width: 90%;
            max-width: 400px;
        }
        /* Hide desktop elements on mobile */
        @media (max-width: 768px) {
            .container {
                padding-left: 10px;
                padding-right: 10px;
            }
            .navbar-brand {
                font-size: 1rem;
            }
        }
        /* Add these styles to your existing styles */
        .toast-container {
            position: fixed;
            bottom: 20px;
            left: 50%;
            transform: translateX(-50%);
            z-index: 1060;  /* Higher than Bootstrap's modal z-index */
            width: 90%;
            max-width: 400px;
        }
        
        .toast {
            background: rgba(255, 255, 255, 0.95);
            border-radius: 8px;
            padding: 15px;
            margin-bottom: 10px;
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.15);
            transition: opacity 0.3s ease-out;
            opacity: 0;
        }
        
        .toast.success {
            border-left: 4px solid #198754;
        }
        
        .toast.warning {
            border-left: 4px solid #ffc107;
        }
        
        .toast.danger {
            border-left: 4px solid #dc3545;
        }

        .loading-overlay {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0, 0, 0, 0.8);
            z-index: 1070;
            display: flex;
            justify-content: center;
            align-items: center;
        }

        .loading-content {
            text-align: center;
        }

        .navbar {
            position: sticky;
            top: 0;
            z-index: 1100;
        }

        #changePasswordModal {
            z-index: 1110;
        }
        /* Change Password UI enhancements */
        .password-strength .progress { height: 6px; }
        .password-strength .strength-label { font-size: 0.85rem; }
        .input-group .btn { border-top-left-radius: 0; border-bottom-left-radius: 0; }
       
    </style>
</head>
<body>

<!-- Password Change Modal -->
<div class="modal fade" id="changePasswordModal" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header bg-primary text-white">
                <h5 class="modal-title mb-0"><i class="fas fa-key me-2"></i>Change Password</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form id="changePasswordForm">
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label">Current Password</label>
                        <div class="input-group">
                            <input type="password" class="form-control" name="current_password" id="current_password" autocomplete="current-password" required>
                            <button type="button" class="btn btn-outline-secondary" data-toggle="password" data-target="#current_password" aria-label="Show/Hide"><i class="far fa-eye"></i></button>
                        </div>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">New Password</label>
                        <div class="input-group">
                            <input type="password" class="form-control" name="new_password" id="new_password" minlength="8" autocomplete="new-password" required>
                            <button type="button" class="btn btn-outline-secondary" data-toggle="password" data-target="#new_password" aria-label="Show/Hide"><i class="far fa-eye"></i></button>
                        </div>
                        <div class="form-text">At least 8 characters, mix of uppercase, lowercase, number, and symbol.</div>
                        <div class="password-strength mt-2">
                            <div class="d-flex justify-content-between align-items-center mb-1">
                                <small class="text-muted">Strength:</small>
                                <small class="strength-label text-muted">Weak</small>
                            </div>
                            <div class="progress">
                                <div class="progress-bar bg-danger" role="progressbar" style="width: 0%"></div>
                            </div>
                        </div>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Confirm New Password</label>
                        <div class="input-group">
                            <input type="password" class="form-control" name="confirm_password" id="confirm_password" minlength="8" autocomplete="new-password" required>
                            <button type="button" class="btn btn-outline-secondary" data-toggle="password" data-target="#confirm_password" aria-label="Show/Hide"><i class="far fa-eye"></i></button>
                        </div>
                        <div class="small mt-1" id="passMatchMsg"></div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary">
                        <span class="spinner-border spinner-border-sm me-2 d-none" id="cpwSpinner"></span>
                        Change Password
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<div class="container mt-4">
    <div class="row justify-content-center">
        <div class="col-md-8 col-lg-6">
            <div class="d-flex justify-content-between align-items-center mb-3">
                <h4 class="mb-0">Today's Schedule</h4>
                <div class="d-flex align-items-center">
                    <!-- Attendance Records Button -->
                    <a href="<?php echo BASE_URL; ?>/admin/student/attendance_records.php" 
                       class="btn btn-outline-primary btn-sm me-3">
                        <i class="fas fa-history me-2"></i>Attendance Records
                    </a>
                    
                    <!-- Settings Dropdown -->
                    <div class="dropdown">
                        <a class="text-dark" href="#" role="button" data-bs-toggle="dropdown">
                            <i class="fas fa-cog fs-5"></i>
                        </a>
                        <ul class="dropdown-menu dropdown-menu-end" style="background-color: #198754;">
                            <li><a class="dropdown-item" href="#" data-bs-toggle="modal" data-bs-target="#changePasswordModal" style="color: white !important;">
                                <i class="fas fa-key me-2"></i>Change Password
                            </a></li>
                        </ul>
                    </div>
                </div>
            </div>
            <div class="text-muted mb-3">
                <?php echo date('l, F j, Y'); ?>
            </div>

            <!-- QR Scanner -->
            <!-- Attendance Summary -->
            <?php
                // Build today's summary stats
                $summary = [
                    'Present' => 0,
                    'Late' => 0,
                    'Absent' => 0
                ];
                // Use DB date to avoid PHP/DB timezone mismatch
                $stmtSum = $conn->prepare("SELECT status, COUNT(*) AS c FROM attendance WHERE student_id = ? AND attendance_date = CURDATE() GROUP BY status");
                $stmtSum->bind_param("s", $student_id);
                $stmtSum->execute();
                $resSum = $stmtSum->get_result();
                while ($row = $resSum->fetch_assoc()) {
                    $st = trim($row['status']);
                    if (!isset($summary[$st])) { $summary[$st] = 0; }
                    $summary[$st] = (int)$row['c'];
                }

                // Load schedules into array to avoid exhausting cursor
                $schedulesData = [];
                while ($row = $schedules->fetch_assoc()) { $schedulesData[] = $row; }
                $total_classes = count($schedulesData);
                // Recompute summary based on joined schedule data to ensure consistency
                $summary['Present'] = 0;
                $summary['Late'] = 0;
                foreach ($schedulesData as $row) {
                    $st = isset($row['attendance_status']) ? trim($row['attendance_status']) : '';
                    if ($st === 'Present') { $summary['Present']++; }
                    elseif ($st === 'Late') { $summary['Late']++; }
                }
                $attended = $summary['Present'] + $summary['Late'];
                $absent = max(0, $total_classes - $attended);

                // Recent attendance (last 5)
                $recentData = [];
                $recentSql = "SELECT a.attendance_date, a.status, a.in_time, a.out_time,
                                     sub.subject_name, s.start_time, s.end_time,
                                     CONCAT(t.lastname, ', ', t.firstname) AS teacher_name
                              FROM attendance a
                              JOIN schedules s ON a.schedule_id = s.schedule_id
                              JOIN subjects sub ON s.subject_id = sub.subject_id
                              JOIN teachers t ON s.teacher_id = t.teacher_id
                              WHERE a.student_id = ?
                              ORDER BY a.attendance_date DESC, s.start_time DESC
                              LIMIT 5";
                if ($stmtRecent = $conn->prepare($recentSql)) {
                    $stmtRecent->bind_param("s", $student_id);
                    $stmtRecent->execute();
                    $resRecent = $stmtRecent->get_result();
                    while ($r = $resRecent->fetch_assoc()) { $recentData[] = $r; }
                }

                // Info values
                $studentEmail = isset($user['email']) ? $user['email'] : '';
                $studentCode = $student_id;
            ?>

            <div class="card mb-4">
                <div class="card-body">
                    <h5 class="card-title mb-3">Today's Summary</h5>
                    <div class="row g-2 text-center">
                        <div class="col-6 col-sm-3">
                            <div class="p-2 border rounded-3 bg-light">
                                <div class="small text-muted">Classes</div>
                                <div class="fs-5 fw-bold"><?php echo $total_classes; ?></div>
                            </div>
                        </div>
                        <div class="col-6 col-sm-3">
                            <div class="p-2 border rounded-3 bg-light">
                                <div class="small text-muted">Attended</div>
                                <div class="fs-5 fw-bold text-success"><?php echo $attended; ?></div>
                            </div>
                        </div>
                        <div class="col-6 col-sm-3">
                            <div class="p-2 border rounded-3 bg-light">
                                <div class="small text-muted">Late</div>
                                <div class="fs-5 fw-bold text-warning"><?php echo $summary['Late']; ?></div>
                            </div>
                        </div>
                        <div class="col-6 col-sm-3">
                            <div class="p-2 border rounded-3 bg-light">
                                <div class="small text-muted">Absent</div>
                                <div class="fs-5 fw-bold text-danger"><?php echo $absent; ?></div>
                            </div>
                        </div>
                    </div>
                    <div class="mt-3">
                        <a href="<?php echo BASE_URL; ?>/admin/student/attendance_records.php" class="btn btn-outline-primary btn-sm">
                            <i class="fas fa-history me-2"></i>View Records
                        </a>
                    </div>
                </div>
            </div>

            <!-- Recent Attendance + Info -->
            <div class="row g-3 mb-4">
                <div class="col-12">
                    <div class="card">
                        <div class="card-body">
                            <h5 class="card-title mb-3"><i class="fas fa-clock me-2"></i>Recent Attendance</h5>
                            <?php if (empty($recentData)): ?>
                                <div class="text-muted">No recent records.</div>
                            <?php else: ?>
                                <?php foreach ($recentData as $rec): ?>
                                    <div class="d-flex justify-content-between align-items-start py-2 border-bottom">
                                        <div class="me-2">
                                            <div class="fw-semibold"><?php echo htmlspecialchars($rec['subject_name']); ?></div>
                                            <div class="text-muted small">
                                                <?php echo date('M j, Y', strtotime($rec['attendance_date'])); ?> •
                                                <?php echo date('g:i A', strtotime($rec['start_time'])) . ' - ' . date('g:i A', strtotime($rec['end_time'])); ?> •
                                                <?php echo htmlspecialchars($rec['teacher_name']); ?>
                                            </div>
                                            <div class="text-muted small">
                                                <?php if (!empty($rec['in_time'])): ?>IN: <?php echo date('g:i A', strtotime($rec['in_time'])); ?><?php endif; ?>
                                                <?php if (!empty($rec['out_time'])): ?>  &nbsp; OUT: <?php echo date('g:i A', strtotime($rec['out_time'])); ?><?php endif; ?>
                                            </div>
                                        </div>
                                        <span class="badge bg-<?php echo $rec['status'] === 'Present' ? 'success' : ($rec['status'] === 'Late' ? 'warning' : 'danger'); ?>">
                                            <?php echo $rec['status']; ?>
                                        </span>
                                    </div>
                                <?php endforeach; ?>
                                <div class="mt-3">
                                    <a href="<?php echo BASE_URL; ?>/admin/student/attendance_records.php" class="btn btn-outline-primary btn-sm">
                                        View all records
                                    </a>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
                <div class="col-12">
                    <div class="card">
                        <div class="card-body">
                            <h5 class="card-title mb-3"><i class="fas fa-info-circle me-2"></i>Info</h5>
                            <div class="row g-2">
                                <div class="col-12 col-sm-6">
                                    <div class="p-2 border rounded-3 bg-light h-100">
                                        <div class="small text-muted">Student ID</div>
                                        <div class="fw-semibold"><?php echo htmlspecialchars($studentCode); ?></div>
                                    </div>
                                </div>
                                <div class="col-12 col-sm-6">
                                    <div class="p-2 border rounded-3 bg-light h-100">
                                        <div class="small text-muted">Email</div>
                                        <div class="fw-semibold text-truncate"><?php echo htmlspecialchars($studentEmail); ?></div>
                                    </div>
                                </div>
                                <div class="col-12 col-sm-6">
                                    <div class="p-2 border rounded-3 bg-light h-100">
                                        <div class="small text-muted">Today</div>
                                        <div class="fw-semibold"><?php echo date('l, M j, Y'); ?></div>
                                    </div>
                                </div>
                                <div class="col-12 col-sm-6">
                                    <div class="p-2 border rounded-3 bg-light h-100">
                                        <div class="small text-muted">Classes Today</div>
                                        <div class="fw-semibold"><?php echo $total_classes; ?></div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Schedule Cards -->
            <div id="scheduleContainer">
                <?php foreach ($schedulesData as $schedule): 
                    $start_time = strtotime($schedule['start_time']);
                    $end_time = strtotime($schedule['end_time']);
                    $current_time = strtotime(date('H:i:s'));
                    $is_current = ($current_time >= $start_time && $current_time <= $end_time);
                    $has_att = !empty($schedule['attendance_status']);
                ?>
                    <div class="card schedule-card <?php echo $is_current ? 'current' : ''; ?>">
                        <div class="card-body">
                            <div class="d-flex justify-content-between align-items-start">
                                <div>
                                    <h5 class="card-title mb-1"><?php echo htmlspecialchars($schedule['subject_name']); ?></h5>
                                    <p class="text-muted mb-1">
                                        <?php echo date('h:i A', $start_time) . ' - ' . date('h:i A', $end_time); ?>
                                    </p>
                                    <small class="text-muted"><?php echo htmlspecialchars($schedule['teacher_name']); ?></small>
                                </div>
                                <div class="text-end">
                                    <span class="attendance-status" data-schedule-id="<?php echo $schedule['schedule_id']; ?>">
                                        <?php if ($has_att): ?>
                                            <span class="badge bg-<?php 
                                                echo $schedule['attendance_status'] === 'Present' ? 'success' : 
                                                    ($schedule['attendance_status'] === 'Late' ? 'warning' : 'danger'); 
                                            ?>">
                                                <?php echo $schedule['attendance_status']; ?>
                                            </span>
                                            <div class="small text-muted mt-1">
                                                <?php if (!empty($schedule['in_time'])): ?>
                                                    <div>IN: <?php echo date('h:i A', strtotime($schedule['in_time'])); ?></div>
                                                <?php endif; ?>
                                                <?php if (!empty($schedule['out_time'])): ?>
                                                    <div>OUT: <?php echo date('h:i A', strtotime($schedule['out_time'])); ?></div>
                                                <?php endif; ?>
                                            </div>
                                        <?php else: ?>
                                            <span class="badge bg-secondary">Not Recorded</span>
                                        <?php endif; ?>
                                    </span>
                                </div>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
    </div>
</div>

<!-- Success/Error Alert (toasts) -->
<div class="scan-result" style="display: none;"></div>

<!-- Add install button -->
<button id="installBtn" style="display: none;" class="btn btn-primary position-fixed bottom-0 end-0 m-3">
    Install App
</button>

<!-- Add these toast elements before closing body tag -->
<div class="toast-container">
    <div class="toast success" style="display: none;">
        <div>
            <span class="me-2">✅</span>
            <span class="toast-message"></span>
        </div>
    </div>
    <div class="toast warning" style="display: none;">
        <div>
            <span class="me-2">⚠️</span>
            <span class="toast-message"></span>
        </div>
    </div>
    <div class="toast danger" style="display: none;">
        <div>
            <span class="me-2">❌</span>
            <span class="toast-message"></span>
        </div>
    </div>
</div>

<!-- Add this loading overlay HTML after your toast container -->
<div id="loadingOverlay" class="loading-overlay" style="display: none;">
    <div class="loading-content">
        <div class="spinner-border text-light mb-2" role="status">
            <span class="visually-hidden">Loading...</span>
        </div>
        <div class="text-light">Logging out...</div>
    </div>
</div>

<script>
function showToast(message, type = 'success') {
    console.log('Showing toast:', message, type);
    
    const toast = document.querySelector(`.toast.${type}`);
    if (!toast) {
        console.error('Toast element not found!');
        return;
    }
    
    // Set message
    toast.querySelector('.toast-message').textContent = message;
    
    // Show toast
    toast.style.display = 'flex';
    toast.style.opacity = '1';
    
    // For success, show loading overlay and redirect
    if (type === 'success') {
        setTimeout(() => {
            toast.style.opacity = '0';
            document.getElementById('loadingOverlay').style.display = 'flex';
            setTimeout(() => {
                window.location.href = '<?php echo BASE_URL; ?>/auth/logout.php';
            }, 1500); // Redirect after 1.5s of loading animation
        }, 2000); // Show toast for 2s
    } else {
        // For other types, just hide the toast
        setTimeout(() => {
            toast.style.opacity = '0';
            setTimeout(() => {
                toast.style.display = 'none';
            }, 300);
        }, 3000);
    }
}

document.addEventListener('DOMContentLoaded', function() {
    // Removed student-side scanning; dashboard is view-only

    // Install button handler
    document.getElementById('installBtn').addEventListener('click', async () => {
        if (deferredPrompt) {
            deferredPrompt.prompt();
            const { outcome } = await deferredPrompt.userChoice;
            if (outcome === 'accepted') {
                console.log('App installed');
            }
            deferredPrompt = null;
            document.getElementById('installBtn').style.display = 'none';
        }
    });
});

// Function to refresh schedule and attendance status
function refreshSchedule() {
    fetch('<?php echo BASE_URL; ?>/admin/student/api/get_schedule.php')
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                document.getElementById('scheduleContainer').innerHTML = data.html;
            }
        })
        .catch(error => console.error('Error:', error));
}

// Refresh every 30 seconds to reduce load
setInterval(refreshSchedule, 30000);

$(document).ready(function() {
    // Password UI enhancements
    function setStrength(score) {
        const bar = $('.password-strength .progress-bar');
        const label = $('.password-strength .strength-label');
        const widths = [0, 25, 50, 75, 100];
        const classes = ['bg-danger','bg-danger','bg-warning','bg-info','bg-success'];
        const texts = ['Weak','Weak','Fair','Good','Strong'];
        bar.removeClass('bg-danger bg-warning bg-info bg-success').addClass(classes[score]).css('width', widths[score] + '%');
        label.text(texts[score]);
    }
    function calcStrength(pwd) {
        let score = 0;
        if (!pwd) return 0;
        if (pwd.length >= 8) score++;
        if (/[a-z]/.test(pwd)) score++;
        if (/[A-Z]/.test(pwd)) score++;
        if (/[0-9]/.test(pwd)) score++;
        if (/[^A-Za-z0-9]/.test(pwd)) score++;
        return Math.min(score, 4);
    }
    function updateStrength() {
        const pwd = $('#new_password').val();
        setStrength(calcStrength(pwd));
    }
    function updateMatch() {
        const p1 = $('#new_password').val();
        const p2 = $('#confirm_password').val();
        const el = $('#passMatchMsg');
        if (!p2) { el.text(''); return; }
        if (p1 === p2) {
            el.text('Passwords match').removeClass('text-danger').addClass('text-success');
        } else {
            el.text('Passwords do not match').removeClass('text-success').addClass('text-danger');
        }
    }
    $(document).on('click', '[data-toggle="password"]', function() {
        const target = $(this).data('target');
        const input = $(target);
        const icon = $(this).find('i');
        const type = input.attr('type') === 'password' ? 'text' : 'password';
        input.attr('type', type);
        icon.toggleClass('fa-eye fa-eye-slash');
    });
    $('#new_password, #confirm_password').on('input', function() {
        updateStrength();
        updateMatch();
    });
    updateStrength();

    $('#changePasswordForm').submit(function(e) {
        e.preventDefault();
        
        const formData = $(this).serialize();
        const form = $(this);
        const submitBtn = form.find('button[type="submit"]');
        const spinner = $('#cpwSpinner');
        
        // Basic validation
        const newPass = form.find('input[name="new_password"]').val();
        const confirmPass = form.find('input[name="confirm_password"]').val();
        
        if (newPass !== confirmPass) {
            showToast('New passwords do not match', 'warning');
            return;
        }
        
        // Disable form while processing
        submitBtn.prop('disabled', true);
        spinner.removeClass('d-none');
        
        $.ajax({
            url: '<?php echo BASE_URL; ?>/admin/student/api/change_password.php',
            method: 'POST',
            data: formData,
            dataType: 'json',
            success: function(response) {
                if (response.success) {
                    $('#changePasswordModal').modal('hide');
                    form[0].reset();
                    showToast('Password changed successfully!', 'success');
                } else {
                    showToast(response.error || 'Failed to change password', 'danger');
                }
            },
            error: function() {
                showToast('Failed to change password', 'danger');
            },
            complete: function() {
                submitBtn.prop('disabled', false);
                spinner.addClass('d-none');
            }
        });
    });
});
</script>

<?php include '../../includes/footer.php'; ?>
