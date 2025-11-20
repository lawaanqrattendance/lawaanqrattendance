<?php
session_start();
require_once '../config/database.php';
require_once '../includes/functions.php';
require_once '../includes/auth.php';

// Set page title
$pageTitle = 'Manage Class Schedules';

// Include header with additional CSS/JS
$additionalCSS = [
    'https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css',
    'https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css',
    'https://cdn.jsdelivr.net/npm/select2-bootstrap-5-theme@1.3.0/dist/select2-bootstrap-5-theme.min.css',
    'https://cdn.jsdelivr.net/npm/sweetalert2@11/dist/sweetalert2.min.css'
];

// These scripts will be loaded in the footer after jQuery
$additionalJS = [];
$footerJS = [
    'https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js',
    'https://cdn.jsdelivr.net/npm/sweetalert2@11',
    'https://unpkg.com/html5-qrcode@2.3.10/minified/html5-qrcode.min.js'
];

include '../includes/header.php';

requireLogin();

// Allow both admin and teacher roles
if (getUserRole() !== 'admin' && getUserRole() !== 'teacher') {
    header("Location: ../index.php");
    exit();
}

// Get teacher_id based on role
$userRole = getUserRole();
$teacher_id = null;

if ($userRole === 'teacher') {
    // Get teacher_id from session or users table
    $stmt = $conn->prepare("SELECT reference_id FROM users WHERE user_id = ? AND role = 'teacher'");
    $user_id = $_SESSION['user_id'];
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $result = $stmt->get_result();
    if ($row = $result->fetch_assoc()) {
        $teacher_id = $row['reference_id'];
        $_SESSION['teacher_id'] = $teacher_id; // Cache it in session
    }
    
    if (!$teacher_id) {
        header("Location: ../index.php");
        exit();
    }
}

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Check if it's an AJAX request
    $isAjax = !empty($_SERVER['HTTP_X_REQUESTED_WITH']) && 
              strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) == 'xmlhttprequest';
    
    if (isset($_POST['action'])) {
        switch ($_POST['action']) {
            case 'add':
                // Start transaction
                $conn->begin_transaction();
                
                try {
                    // Debug: Log received POST data
                    error_log('Received POST data: ' . print_r($_POST, true));
                    
                    // Check if required fields are set
                    $required = ['subject_id', 'section_id', 'teacher_id', 'day_of_week', 'start_time', 'end_time', 'school_year'];
                    $missing = [];
                    
                    foreach ($required as $field) {
                        if (!isset($_POST[$field]) || trim($_POST[$field]) === '') {
                            $missing[] = $field;
                        }
                    }
                    
                    if (!empty($missing)) {
                        throw new Exception('Missing required fields: ' . implode(', ', $missing));
                    }
                    // Validate and sanitize inputs
                    $subject_id = intval(cleanInput($_POST['subject_id'] ?? ''));
                    $section_id = intval(cleanInput($_POST['section_id'] ?? ''));
                    $teacher_id = intval(cleanInput($_POST['teacher_id'] ?? ''));
                    $day_of_week = cleanInput($_POST['day_of_week'] ?? '');
                    $start_time = cleanInput($_POST['start_time'] ?? '');
                    $end_time = cleanInput($_POST['end_time'] ?? '');
                    $school_year = cleanInput($_POST['school_year'] ?? '');
                    
                    // Validate required fields
                    if (empty($subject_id) || empty($section_id) || empty($teacher_id) || empty($day_of_week) || 
                        empty($start_time) || empty($end_time) || empty($school_year)) {
                        throw new Exception("All fields are required.");
                    }
                    
                    // Validate school year format
                    if (!preg_match('/^\d{4}-\d{4}$/', $school_year)) {
                        throw new Exception("Invalid school year format. Please use YYYY-YYYY format.");
                    }
                    
                    // Check if subject exists
                    $check_subject = $conn->prepare("SELECT subject_id FROM subjects WHERE subject_id = ?");
                    $check_subject->bind_param("i", $subject_id);
                    $check_subject->execute();
                    if ($check_subject->get_result()->num_rows === 0) {
                        throw new Exception("Selected subject does not exist.");
                    }
                    
                    // Check if section exists
                    $check_section = $conn->prepare("SELECT section_id FROM sections WHERE section_id = ?");
                    $check_section->bind_param("i", $section_id);
                    $check_section->execute();
                    if ($check_section->get_result()->num_rows === 0) {
                        throw new Exception("Selected section does not exist.");
                    }
                    
                    // Check if teacher exists
                    $check_teacher = $conn->prepare("SELECT teacher_id FROM teachers WHERE teacher_id = ?");
                    $check_teacher->bind_param("i", $teacher_id);
                    $check_teacher->execute();
                    if ($check_teacher->get_result()->num_rows === 0) {
                        throw new Exception("Selected teacher does not exist.");
                    }
                    
                    // Validate time range
                    if (strtotime($end_time) <= strtotime($start_time)) {
                        throw new Exception('End time must be after start time');
                    }
                    // Check for schedule conflicts for section, teacher, or subject on the same day/time/school_year
                    $check_conflict = $conn->prepare("
                        SELECT s.schedule_id, s.start_time, s.end_time,
                               sub.subject_name,
                               sec.section_name, sec.grade_level,
                               CONCAT(t.lastname, ', ', t.firstname) as teacher_name,
                               CASE 
                                   WHEN s.section_id = ? THEN 'Section'
                                   WHEN s.teacher_id = ? THEN 'Teacher'
                                   WHEN s.subject_id = ? THEN 'Subject'
                               END as conflict_type
                        FROM schedules s
                        JOIN subjects sub ON s.subject_id = sub.subject_id
                        JOIN sections sec ON s.section_id = sec.section_id
                        JOIN teachers t ON s.teacher_id = t.teacher_id
                        WHERE s.day_of_week = ?
                        AND s.school_year = ?
                        AND (
                            s.section_id = ?
                            OR s.teacher_id = ?
                            OR s.subject_id = ?
                        )
                        AND NOT (
                            s.end_time <= ? OR s.start_time >= ?
                        )
                        LIMIT 1
                    ");
                    $check_conflict->bind_param("iiissiisss", $section_id, $teacher_id, $subject_id, $day_of_week, $school_year, $section_id, $teacher_id, $subject_id, $start_time, $end_time);
                    $check_conflict->execute();
                    $conflict_result = $check_conflict->get_result();
                    
                    if ($conflict_result->num_rows > 0) {
                        $conflict = $conflict_result->fetch_assoc();
                        
                        // Get available time slots for the day
                        $available_slots = [];
                        $get_day_schedules = $conn->prepare("
                            SELECT start_time, end_time 
                            FROM schedules 
                            WHERE day_of_week = ? 
                            AND school_year = ?
                            AND (section_id = ? OR teacher_id = ? OR subject_id = ?)
                            ORDER BY start_time
                        ");
                        $get_day_schedules->bind_param("ssiii", $day_of_week, $school_year, $section_id, $teacher_id, $subject_id);
                        $get_day_schedules->execute();
                        $day_schedules = $get_day_schedules->get_result();
                        
                        $occupied_times = [];
                        while ($sched = $day_schedules->fetch_assoc()) {
                            $occupied_times[] = [
                                'start' => $sched['start_time'],
                                'end' => $sched['end_time']
                            ];
                        }
                        
                        // Generate available slots (7:00 AM to 6:00 PM)
                        $start_hour = 7;
                        $end_hour = 18;
                        for ($hour = $start_hour; $hour < $end_hour; $hour++) {
                            $slot_start = sprintf("%02d:00:00", $hour);
                            $slot_end = sprintf("%02d:00:00", $hour + 1);
                            
                            $is_available = true;
                            foreach ($occupied_times as $occupied) {
                                if (!($slot_end <= $occupied['start'] || $slot_start >= $occupied['end'])) {
                                    $is_available = false;
                                    break;
                                }
                            }
                            
                            if ($is_available) {
                                $available_slots[] = [
                                    'start' => date('h:i A', strtotime($slot_start)),
                                    'end' => date('h:i A', strtotime($slot_end)),
                                    'start_24h' => substr($slot_start, 0, 5),
                                    'end_24h' => substr($slot_end, 0, 5)
                                ];
                            }
                        }
                        
                        $error_data = [
                            'conflict' => [
                                'type' => $conflict['conflict_type'],
                                'subject' => $conflict['subject_name'],
                                'section' => 'Grade ' . $conflict['grade_level'] . ' - ' . $conflict['section_name'],
                                'teacher' => $conflict['teacher_name'],
                                'time' => date('h:i A', strtotime($conflict['start_time'])) . ' - ' . date('h:i A', strtotime($conflict['end_time']))
                            ],
                            'available_slots' => $available_slots,
                            'day' => $day_of_week
                        ];
                        
                        throw new Exception(json_encode($error_data));
                    }
                    
                    // Insert the schedule
                    $stmt = $conn->prepare("
                        INSERT INTO schedules 
                        (subject_id, section_id, teacher_id, day_of_week, start_time, end_time, school_year) 
                        VALUES (?, ?, ?, ?, ?, ?, ?)
                    ");
                    $stmt->bind_param("iiissss", $subject_id, $section_id, $teacher_id, $day_of_week, $start_time, $end_time, $school_year);
                    
                    if (!$stmt->execute()) {
                        throw new Exception("Failed to add schedule. Please try again.");
                    }
                    
                    // Commit transaction
                    $conn->commit();
                    
                    if ($isAjax) {
                        // Clean any previous output to ensure valid JSON response
                        if (ob_get_length()) {
                            ob_clean();
                        }
                        header('Content-Type: application/json; charset=utf-8');
                        echo json_encode([
                            'success' => true, 
                            'message' => 'Schedule added successfully!',
                            'data' => [
                                'subject_id' => $subject_id,
                                'section_id' => $section_id,
                                'teacher_id' => $teacher_id,
                                'day_of_week' => $day_of_week,
                                'start_time' => $start_time,
                                'end_time' => $end_time,
                                'school_year' => $school_year
                            ]
                        ]);
                        exit();
                    } else {
                        // Regular form submission
                        $_SESSION['success'] = "Schedule added successfully!";
                        header("Location: " . $_SERVER['PHP_SELF']);
                        exit();
                    }
                    
                } catch (Exception $e) {
                    // Rollback transaction on error
                    $conn->rollback();
                    $error = $e->getMessage();
                    
                    if ($isAjax) {
                        // Clean any previous output to ensure valid JSON response
                        if (ob_get_length()) {
                            ob_clean();
                        }
                        http_response_code(400);
                        header('Content-Type: application/json; charset=utf-8');
                        echo json_encode([
                            'success' => false, 
                            'error' => $error,
                            'debug' => [
                                'post_data' => $_POST,
                                'error_message' => $e->getMessage(),
                                'trace' => $e->getTraceAsString()
                            ]
                        ]);
                        exit();
                    } else {
                        // For non-AJAX requests, set the error in the session
                        $_SESSION['error'] = $error;
                    }
                }
                break;
        }
    }
}

// Base query
$query = "SELECT s.*, 
          sub.subject_name,
          sec.section_name,
          sec.grade_level,
          CONCAT(t.lastname, ', ', t.firstname) as teacher_name
          FROM schedules s
          JOIN subjects sub ON s.subject_id = sub.subject_id
          JOIN sections sec ON s.section_id = sec.section_id
          JOIN teachers t ON s.teacher_id = t.teacher_id";

// Add WHERE clause for teachers to only see their schedules
if ($userRole === 'teacher' && $teacher_id) {
    $query .= " WHERE s.teacher_id = " . intval($teacher_id);
}

// Add ordering
$query .= " ORDER BY s.day_of_week, s.start_time";

// Execute query
$schedules = $conn->query($query);
if (!$schedules) {
    die("Error in query: " . $conn->error);
}

// Get data for dropdowns
$subjects = $conn->query("SELECT * FROM subjects ORDER BY subject_name");
$sections = $conn->query("SELECT * FROM sections ORDER BY grade_level, section_name");
$teachers = $conn->query("SELECT * FROM teachers ORDER BY lastname, firstname");

// Check for success/error messages in session
if (isset($_SESSION['success'])) {
    $success = $_SESSION['success'];
    unset($_SESSION['success']);
}

if (isset($_SESSION['error'])) {
    $error = $_SESSION['error'];
    unset($_SESSION['error']);
}
?>

<div class="row mb-4">
    <div class="col-12">
        <div class="page-title-box d-flex align-items-center justify-content-between">
            <div class="page-title-right">
                <ol class="breadcrumb m-0">
                    <li class="breadcrumb-item"><a href="dashboard.php">Dashboard</a></li>
                    <li class="breadcrumb-item active">Class Schedules</li>
                </ol>
            </div>
            <h4 class="mb-0">Manage Class Schedules</h4>
            <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addScheduleModal">
                <i class="fas fa-plus me-1"></i> Add New Schedule
            </button>
        </div>
        <hr class="mt-2">
    </div>
</div>

<?php if (isset($success)): ?>
    <div class="alert alert-success"><?php echo $success; ?></div>
<?php endif; ?>

<?php if (isset($error)): ?>
    <div class="alert alert-danger"><?php echo $error; ?></div>
<?php endif; ?>

<!-- Schedules Table -->
<div class="card shadow rounded-4 border-0 bg-light-subtle">
    <div class="card-body pb-0">
        <div class="d-flex justify-content-between align-items-center mb-4">
            <h5 class="card-title mb-0 text-primary fw-bold d-flex align-items-center">
                <i class="fas fa-calendar-alt me-2"></i>Class Schedules
            </h5>
            <div class="input-group input-group-sm shadow-sm rounded-pill overflow-hidden" style="width: 270px;">
                <span class="input-group-text bg-white border-0"><i class="fas fa-search"></i></span>
                <input type="text" id="searchInput" class="form-control border-0" placeholder="Search schedules...">
            </div>
        </div>
        <div class="table-responsive rounded-4 py-4">
            <table class="table table-hover align-middle table-striped table-borderless mb-0" id="schedulesTable" style="background: #fff;">
                <thead class="table-light sticky-top shadow-sm rounded-4">
                    <tr style="vertical-align: middle;">
                        <th class="text-center">Day</th>
                        <th class="text-center">Time</th>
                        <th class="text-center">Subject</th>
                        <th class="text-center">Section</th>
                        <th class="text-center">Teacher</th>
                        <th class="text-center">Status</th>
                        <th class="text-center">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php while ($schedule = $schedules->fetch_assoc()): ?>
                        <tr class="schedule-row"
                            data-schedule-id="<?php echo (int)$schedule['schedule_id']; ?>"
                            data-day="<?php echo htmlspecialchars($schedule['day_of_week']); ?>"
                            data-start="<?php echo htmlspecialchars($schedule['start_time']); ?>"
                            data-end="<?php echo htmlspecialchars($schedule['end_time']); ?>"
                            data-grade="<?php echo htmlspecialchars($schedule['grade_level']); ?>"
                            data-section="<?php echo htmlspecialchars($schedule['section_name']); ?>"
                            data-subject="<?php echo htmlspecialchars($schedule['subject_name']); ?>"
                            data-teacher-id="<?php echo (int)$schedule['teacher_id']; ?>"
                        >
                            <td class="text-center fw-semibold text-dark-emphasis"><?php echo $schedule['day_of_week']; ?></td>
                            <td class="text-center fw-semibold text-primary-emphasis">
                                <i class="far fa-clock me-1 text-primary"></i>
                                <?php echo date('h:i A', strtotime($schedule['start_time'])) . ' - ' . date('h:i A', strtotime($schedule['end_time'])); ?>
                            </td>
                            <td class="fw-semibold text-center"><i class="fas fa-book-open me-1 text-secondary"></i><?php echo $schedule['subject_name']; ?></td>
                            <td class="text-center"><span class="status-badge bg-info-subtle text-dark-emphasis px-3 py-2 rounded-pill shadow-sm">Grade <?php echo $schedule['grade_level']; ?> - <?php echo $schedule['section_name']; ?></span></td>
                            <td class="text-center"><i class="fas fa-user-tie me-1 text-primary"></i><?php echo $schedule['teacher_name']; ?></td>
                            <td class="text-center">
                                <span class="badge rounded-pill px-3 py-2 shadow-sm bg-<?php echo $schedule['status'] === 'Open' ? 'success' : 'secondary'; ?>">
                                    <i class="fas fa-times-circle me-1"></i>
                                    <?php echo $schedule['status']; ?>
                                </span>
                            </td>
                            <td class="text-center">
                                <div class="btn-group btn-group-sm" role="group">
                                    <button class="btn btn-outline-<?php echo $schedule['status'] === 'Open' ? 'warning' : 'success'; ?> border-0 shadow-sm toggle-status" 
                                            data-id="<?php echo $schedule['schedule_id']; ?>"
                                            data-status="<?php echo strtolower($schedule['status']); ?>"
                                            title="<?php echo $schedule['status'] === 'Open' ? 'Close' : 'Open'; ?> Schedule">
                                        <span class="fw-bold"><?php echo $schedule['status'] === 'Open' ? 'Close' : 'Open'; ?></span>
                                        <i class="fas fa-exchange-alt ms-1"></i>
                                    </button>
                                    <button class="btn btn-outline-primary border-0 shadow-sm edit-schedule" 
                                            data-id="<?php echo $schedule['schedule_id']; ?>"
                                            data-day="<?php echo $schedule['day_of_week']; ?>"
                                            data-start="<?php echo $schedule['start_time']; ?>"
                                            data-end="<?php echo $schedule['end_time']; ?>"
                                            title="Edit Schedule">
                                            Edit
                                        <i class="fas fa-edit"></i>
                                    </button>
                                    <?php if ($userRole === 'teacher' && isset($_SESSION['teacher_id']) && $schedule['teacher_id'] == $_SESSION['teacher_id'] && $schedule['status'] === 'Open'): ?>
                                        <button class="btn btn-outline-dark border-0 shadow-sm scan-qr"
                                                data-id="<?php echo htmlspecialchars($schedule['schedule_id']); ?>"
                                                data-label="<?php echo htmlspecialchars('Grade ' . $schedule['grade_level'] . ' - ' . $schedule['section_name'] . ' | ' . $schedule['subject_name'] . ' | ' . $schedule['day_of_week'] . ' ' . date('h:i A', strtotime($schedule['start_time'])) . ' - ' . date('h:i A', strtotime($schedule['end_time']))); ?>"
                                                title="Scan QR for Attendance">
                                                Scan
                                            <i class="fas fa-qrcode"></i>
                                        </button>
                                    <?php endif; ?>
                                    <?php if ($userRole === 'admin' || ($userRole === 'teacher' && $schedule['teacher_id'] == $_SESSION['teacher_id'])): ?>
                                        <button class="btn btn-outline-danger border-0 shadow-sm delete-schedule" 
                                                data-id="<?php echo htmlspecialchars($schedule['schedule_id']); ?>"
                                                title="Delete Schedule">
                                                Delete
                                            <i class="fas fa-trash-alt"></i>
                                        </button>
                                    <?php endif; ?>
                                </div>
                            </td>
                        </tr>
                    <?php endwhile; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>
<style>
    #schedulesTable th, #schedulesTable td { vertical-align: middle !important; }
    #schedulesTable thead th { position: sticky; top: 0; z-index: 2; background: #f8f9fa; }
    #schedulesTable tbody tr.schedule-row:hover { background: #e9f5ff !important; transition: background 0.2s; }
    #schedulesTable .badge { font-size: 1em; letter-spacing: 0.03em; }
    #schedulesTable .btn { transition: box-shadow 0.2s, background 0.2s; }
    #schedulesTable .btn:focus { box-shadow: 0 0 0 0.15rem #0d6efd44 !important; }
    
    /* Conflict Modal Styles */
    .slot-card {
        transition: all 0.3s ease;
        border: 2px solid #ffffffff !important;
    }
    .slot-card:hover {
        transform: translateY(-5px);
        box-shadow: 0 5px 15px rgba(25, 135, 84, 0.3);
        background-color: #d1e7dd;
    }
    .slot-card .card-body {
        padding: 1rem;
    }
</style>

<!-- Add Schedule Modal -->
<div class="modal fade" id="addScheduleModal" tabindex="-1" aria-labelledby="addScheduleModalLabel" aria-hidden="true" data-bs-backdrop="static">
    <div class="modal-dialog modal-dialog-centered modal-lg">
        <div class="modal-content border-0 shadow">
            <div class="modal-header bg-primary text-white">
                <h5 class="modal-title d-flex align-items-center" id="addScheduleModalLabel">
                    <i class="fas fa-plus-circle me-2"></i>Add New Schedule
                </h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form id="addScheduleForm" method="POST" class="needs-validation" novalidate>
                <div class="modal-body p-4">
                    <input type="hidden" name="action" value="add">
                    
                    <div class="row g-3">
                        <!-- Subject -->
                        <div class="col-md-6">
                            <div class="form-group">
                                <label class="form-label fw-bold">
                                    <i class="fas fa-book me-1 text-primary"></i>Subject
                                    <span class="text-danger">*</span>
                                </label>
                                <div class="input-group">
                                    <span class="input-group-text"><i class="fas fa-search"></i></span>
                                    <select class="form-select select2" name="subject_id" required>
                                        <option value="">Select Subject</option>
                                        <?php 
                                        $subjects->data_seek(0); // Reset pointer to beginning
                                        while ($subject = $subjects->fetch_assoc()): ?>
                                            <option value="<?php echo $subject['subject_id']; ?>">
                                                <?php echo $subject['subject_name']; ?>
                                            </option>
                                        <?php endwhile; ?>
                                    </select>
                                    <div class="invalid-feedback">Please select a subject</div>
                                </div>
                            </div>
                        </div>
                        
                        <!-- Section -->
                        <div class="col-md-6">
                            <div class="form-group">
                                <label class="form-label fw-bold">
                                    <i class="fas fa-users me-1 text-primary"></i>Section
                                    <span class="text-danger">*</span>
                                </label>
                                <div class="input-group">
                                    <span class="input-group-text"><i class="fas fa-layer-group"></i></span>
                                    <select class="form-select select2" name="section_id" required>
                                        <option value="">Select Section</option>
                                        <?php 
                                        // Different queries for admin and teacher
                                        if ($userRole === 'admin') {
                                            // Admin sees all sections
                                            $sections_query = "SELECT section_id, section_name, grade_level 
                                                             FROM sections 
                                                             ORDER BY grade_level, section_name";
                                            $sections = $conn->query($sections_query);
                                        } else {
                                            // Teachers only see their assigned sections from teacher_sections table
                                            $sections_query = "SELECT DISTINCT s.section_id, s.section_name, s.grade_level 
                                                             FROM sections s
                                                             JOIN teacher_sections ts ON s.section_id = ts.section_id
                                                             WHERE ts.teacher_id = ?
                                                             ORDER BY s.grade_level, s.section_name";
                                            $stmt = $conn->prepare($sections_query);
                                            $stmt->bind_param("i", $_SESSION['teacher_id']);
                                            $stmt->execute();
                                            $sections = $stmt->get_result();
                                        }
                                        
                                        while ($section = $sections->fetch_assoc()): 
                                        ?>
                                            <option value="<?php echo $section['section_id']; ?>">
                                                Grade <?php echo $section['grade_level']; ?> - <?php echo $section['section_name']; ?>
                                            </option>
                                        <?php endwhile; ?>
                                    </select>
                                    <div class="invalid-feedback">Please select a section</div>
                                </div>
                            </div>
                        </div>
                        
                        <!-- Teacher -->
                        <div class="col-md-6">
                            <div class="form-group">
                                <label class="form-label fw-bold">
                                    <i class="fas fa-chalkboard-teacher me-1 text-primary"></i>Teacher
                                    <span class="text-danger">*</span>
                                </label>
                                <div class="input-group">
                                    <span class="input-group-text"><i class="fas fa-user-tie"></i></span>
                                    <?php if ($userRole === 'admin'): ?>
                                        <select class="form-select select2" name="teacher_id" required>
                                            <option value="">Select Teacher</option>
                                            <?php 
                                            $teachers->data_seek(0); // Reset pointer to beginning
                                            while ($teacher = $teachers->fetch_assoc()): ?>
                                                <option value="<?php echo $teacher['teacher_id']; ?>">
                                                    <?php echo $teacher['lastname'] . ', ' . $teacher['firstname']; ?>
                                                </option>
                                            <?php endwhile; ?>
                                        </select>
                                    <?php else: ?>
                                        <?php
                                        // Get teacher name for display
                                        $teacher_stmt = $conn->prepare("SELECT lastname, firstname FROM teachers WHERE teacher_id = ?");
                                        $teacher_stmt->bind_param("i", $_SESSION['teacher_id']);
                                        $teacher_stmt->execute();
                                        $teacher_result = $teacher_stmt->get_result();
                                        $teacher_name = $teacher_result->fetch_assoc();
                                        ?>
                                        <input type="text" class="form-control" value="<?php echo htmlspecialchars($teacher_name['lastname'] . ', ' . $teacher_name['firstname']); ?>" readonly>
                                        <input type="hidden" name="teacher_id" value="<?php echo $_SESSION['teacher_id']; ?>">
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                        
                        <!-- Day of Week -->
                        <div class="col-md-6">
                            <div class="form-group">
                                <label class="form-label fw-bold">
                                    <i class="far fa-calendar-alt me-1 text-primary"></i>Day of Week
                                    <span class="text-danger">*</span>
                                </label>
                                <div class="input-group">
                                    <span class="input-group-text"><i class="fas fa-calendar-day"></i></span>
                                    <select class="form-select" name="day_of_week" required>
                                        <option value="">Select Day</option>
                                        <option value="Monday">Monday</option>
                                        <option value="Tuesday">Tuesday</option>
                                        <option value="Wednesday">Wednesday</option>
                                        <option value="Thursday">Thursday</option>
                                        <option value="Friday">Friday</option>
                                        <option value="Saturday">Saturday</option>
                                        <option value="Sunday">Sunday</option>
                                    </select>
                                    <div class="invalid-feedback">Please select a day</div>
                                </div>
                            </div>
                        </div>
                        
                        <!-- Time Range -->
                        <div class="col-md-6">
                            <div class="form-group">
                                <label class="form-label fw-bold">
                                    <i class="far fa-clock me-1 text-primary"></i>Time Range
                                    <span class="text-danger">*</span>
                                </label>
                                <div class="input-group">
                                    <span class="input-group-text"><i class="fas fa-hourglass-start"></i></span>
                                    <input type="time" class="form-control timepicker" name="start_time" required>
                                    <span class="input-group-text">to</span>
                                    <input type="time" class="form-control timepicker" name="end_time" required>
                                </div>
                                <small class="form-text text-muted">Select start and end time</small>
                            </div>
                        </div>
                        
                        <!-- School Year -->
                        <div class="col-md-6">
                            <div class="form-group">
                                <label class="form-label fw-bold">
                                    <i class="fas fa-calendar-alt me-1 text-primary"></i>School Year
                                    <span class="text-danger">*</span>
                                </label>
                                <div class="input-group">
                                    <span class="input-group-text"><i class="far fa-calendar"></i></span>
                                    <input type="text" 
                                           class="form-control" 
                                           name="school_year" 
                                           placeholder="2024-2025" 
                                           pattern="\d{4}-\d{4}" 
                                           required
                                           data-bs-toggle="tooltip" 
                                           data-bs-placement="top" 
                                           title="Format: YYYY-YYYY (e.g., 2024-2025)">
                                    <div class="invalid-feedback">Please enter a valid school year (e.g., 2024-2025)</div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="modal-footer bg-light">
                    <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">
                        <i class="fas fa-times me-1"></i>Cancel
                    </button>
                    <button type="submit" class="btn btn-primary">
                        <i class="fas fa-save me-1"></i>Save Schedule
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Edit Schedule Modal -->
<div class="modal fade" id="editScheduleModal" tabindex="-1" aria-labelledby="editScheduleModalLabel" aria-hidden="true" data-bs-backdrop="static">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-0 shadow">
            <div class="modal-header bg-primary text-white">
                <h5 class="modal-title d-flex align-items-center" id="editScheduleModalLabel">
                    <i class="fas fa-calendar-edit me-2"></i>Edit Schedule
                </h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form id="editScheduleForm" class="needs-validation" novalidate>
                <div class="modal-body p-4">
                    <input type="hidden" name="schedule_id" id="edit_schedule_id">
                    
                    <div class="mb-4">
                        <label class="form-label fw-bold">
                            <i class="far fa-calendar-alt me-1 text-primary"></i>Day of Week
                            <span class="text-danger">*</span>
                        </label>
                        <div class="input-group">
                            <span class="input-group-text"><i class="fas fa-calendar-day"></i></span>
                            <select name="day_of_week" id="edit_day_of_week" class="form-select" required>
                                <option value="">Select Day</option>
                                <option value="Monday">Monday</option>
                                <option value="Tuesday">Tuesday</option>
                                <option value="Wednesday">Wednesday</option>
                                <option value="Thursday">Thursday</option>
                                <option value="Friday">Friday</option>
                                <option value="Saturday">Saturday</option>
                                <option value="Sunday">Sunday</option>
                            </select>
                            <div class="invalid-feedback">Please select a day</div>
                        </div>
                    </div>
                    
                    <div class="mb-4">
                        <label class="form-label fw-bold">
                            <i class="far fa-clock me-1 text-primary"></i>Time Range
                            <span class="text-danger">*</span>
                        </label>
                        <div class="input-group">
                            <span class="input-group-text"><i class="fas fa-hourglass-start"></i></span>
                            <input type="time" name="start_time" id="edit_start_time" class="form-control" required>
                            <span class="input-group-text bg-light">to</span>
                            <input type="time" name="end_time" id="edit_end_time" class="form-control" required>
                        </div>
                        <small class="form-text text-muted">Select start and end time</small>
                    </div>
                </div>
                <div class="modal-footer bg-light d-flex justify-content-between">
                    <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">
                        <i class="fas fa-times me-1"></i>Cancel
                    </button>
                    <button type="submit" class="btn btn-primary">
                        <i class="fas fa-save me-1"></i>Save Changes
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Scan QR Modal (Teacher-only) -->
<div class="modal fade" id="scanQrModal" tabindex="-1" aria-labelledby="scanQrModalLabel" aria-hidden="true" data-bs-backdrop="static">
    <div class="modal-dialog modal-dialog-centered modal-lg">
        <div class="modal-content border-0 shadow">
            <div class="modal-header bg-dark text-white">
                <h5 class="modal-title d-flex align-items-center" id="scanQrModalLabel">
                    <i class="fas fa-qrcode me-2"></i>Scan Attendance QR
                </h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body p-0">
                <div class="px-3 pt-3">
                    <div class="small text-muted" id="scanScheduleLabel"></div>
                </div>
                <div class="p-3">
                    <button type="button" class="btn btn-primary mb-2" id="scanPermissionBtn">Enable Camera</button>
                    <div class="d-flex align-items-center gap-2 mb-2">
                        <select id="cameraSelect" class="form-select form-select-sm" style="max-width: 260px; display: none;"></select>
                        <button type="button" id="refreshCamerasBtn" class="btn btn-outline-secondary btn-sm" style="display: none;">Refresh Cameras</button>
                    </div>
                    <div id="qr-reader" class="border rounded-3" style="width: 100%; min-height: 320px;"></div>
                    <div id="scanResult" class="mt-3"></div>
                </div>
            </div>
            <div class="modal-footer bg-light d-flex justify-content-between">
                <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">
                    <i class="fas fa-times me-1"></i>Close
                </button>
                <button type="button" class="btn btn-dark" id="restartScannerBtn">
                    <i class="fas fa-redo me-1"></i>Restart Camera
                </button>
            </div>
        </div>
    </div>
    
</div>

<!-- Schedule Conflict Modal -->
<div class="modal fade" id="conflictModal" tabindex="-1" aria-labelledby="conflictModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-lg">
        <div class="modal-content border-0 shadow">
            <div class="modal-header bg-danger text-white">
                <h5 class="modal-title d-flex align-items-center" id="conflictModalLabel">
                    <i class="fas fa-exclamation-triangle me-2"></i>Schedule Conflict Detected
                </h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body p-4">
                <div class="alert alert-danger d-flex align-items-start mb-4">
                    <i class="fas fa-times-circle me-2 mt-1"></i>
                    <div>
                        <strong>Conflict Type:</strong> <span id="conflictType"></span><br>
                        <strong>Conflicting Schedule:</strong>
                        <div class="mt-2 ms-3">
                            <div><i class="fas fa-book me-2"></i><strong>Subject:</strong> <span id="conflictSubject"></span></div>
                            <div><i class="fas fa-users me-2"></i><strong>Section:</strong> <span id="conflictSection"></span></div>
                            <div><i class="fas fa-user-tie me-2"></i><strong>Teacher:</strong> <span id="conflictTeacher"></span></div>
                            <div><i class="far fa-clock me-2"></i><strong>Time:</strong> <span id="conflictTime"></span></div>
                        </div>
                    </div>
                </div>
                
                <h6 class="fw-bold mb-3"><i class="fas fa-calendar-check me-2 text-success"></i>Available Time Slots on <span id="conflictDay"></span>:</h6>
                <div id="availableSlots" class="row g-2"></div>
                <div id="noSlotsMessage" class="alert alert-warning" style="display: none;">
                    <i class="fas fa-info-circle me-2"></i>No available time slots found for this day. Please try a different day or time.
                </div>
            </div>
            <div class="modal-footer bg-light">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">
                    <i class="fas fa-times me-1"></i>Close
                </button>
                <button type="button" class="btn btn-primary" id="tryDifferentTimeBtn">
                    <i class="fas fa-clock me-1"></i>Choose Different Time
                </button>
            </div>
        </div>
    </div>
</div>

<script>
$(document).ready(function() {
    // Current user context for client-side checks
    const CURRENT_USER_ROLE = <?php echo json_encode($userRole); ?>;
    const CURRENT_TEACHER_ID = <?php echo isset($_SESSION['teacher_id']) ? (int)$_SESSION['teacher_id'] : 'null'; ?>;

    // Function to show conflict modal
    function showConflictModal(data) {
        const conflict = data.conflict;
        const availableSlots = data.available_slots || [];
        const day = data.day;
        
        // Populate conflict details
        $('#conflictType').text(conflict.type);
        $('#conflictSubject').text(conflict.subject);
        $('#conflictSection').text(conflict.section);
        $('#conflictTeacher').text(conflict.teacher);
        $('#conflictTime').text(conflict.time);
        $('#conflictDay').text(day);
        
        // Populate available slots
        const slotsContainer = $('#availableSlots');
        slotsContainer.empty();
        
        if (availableSlots.length > 0) {
            $('#noSlotsMessage').hide();
            availableSlots.forEach(slot => {
                const slotCard = $(`
                    <div class="col-md-4 col-sm-6">
                        <div class="card border-success slot-card" style="cursor: pointer;" data-start="${slot.start_24h}" data-end="${slot.end_24h}">
                            <div class="card-body text-center py-3">
                                <i class="far fa-clock text-success mb-2" style="font-size: 1.5rem;"></i>
                                <div class="fw-bold">${slot.start} - ${slot.end}</div>
                            </div>
                        </div>
                    </div>
                `);
                slotsContainer.append(slotCard);
            });
            
            // Add click handler for slot cards
            $('.slot-card').on('click', function() {
                const start = $(this).data('start');
                const end = $(this).data('end');
                
                // Fill the form with selected time
                $('input[name="start_time"]').val(start);
                $('input[name="end_time"]').val(end);
                
                // Close conflict modal
                $('#conflictModal').modal('hide');
                
                // Show success message
                showAlert('Time slot selected! Please review and submit the form.', 'success');
            });
        } else {
            $('#noSlotsMessage').show();
        }
        
        // Show the modal
        $('#conflictModal').modal('show');
    }
    
    // Try different time button
    $('#tryDifferentTimeBtn').on('click', function() {
        $('#conflictModal').modal('hide');
    });

    // Function to show alerts using SweetAlert2
    function showAlert(message, type = 'success') {
        const Toast = Swal.mixin({
            toast: true,
            position: 'top-end',
            showConfirmButton: false,
            timer: 3000,
            timerProgressBar: true,
            didOpen: (toast) => {
                toast.addEventListener('mouseenter', Swal.stopTimer)
                toast.addEventListener('mouseleave', Swal.resumeTimer)
            }
        });
        
        Toast.fire({
            icon: type,
            title: message
        });
    }

    // Toggle Status with confirmation
    $(document).on('click', '.toggle-status', function() {
        const button = $(this);
        const id = button.data('id');
        // Always read the latest data-status from the button on click
        const currentStatus = button.attr('data-status');
        const newStatus = currentStatus === 'open' ? 'Closed' : 'Open';

        Swal.fire({
            title: 'Confirm Status Change',
            text: `Are you sure you want to change this schedule status to ${newStatus}?`,
            icon: 'question',
            showCancelButton: true,
            confirmButtonColor: '#3085d6',
            cancelButtonColor: '#d33',
            confirmButtonText: `Yes, set to ${newStatus}`,
            cancelButtonText: 'Cancel',
            reverseButtons: true
        }).then((result) => {
            if (result.isConfirmed) {
                button.prop('disabled', true).html('<i class="fas fa-spinner fa-spin"></i>');

                $.ajax({
                    url: '<?php echo BASE_URL; ?>/admin/api/toggle_schedule.php',
                    method: 'POST',
                    data: { schedule_id: id },
                    dataType: 'json',
                    success: function(response) {
                        if (response.success) {
                            showAlert(response.message);
                            // Update status badge
                            const statusBadge = button.closest('tr').find('.badge');
                            const badgeClass = response.new_status === 'Open' ? 'bg-success' : 'bg-secondary';
                            const iconClass = response.new_status === 'Open' ? 'fa-check-circle' : 'fa-times-circle';
                            statusBadge.removeClass('bg-success bg-secondary')
                                .addClass(badgeClass)
                                .html(`<i class="fas ${iconClass} me-1"></i>${response.new_status}`);
                            // Update button style, label, data-status, and title based on new status
                            const btnClassAdd = response.new_status === 'Open' ? 'btn-outline-warning' : 'btn-outline-success';
                            const btnLabel = response.new_status === 'Open' ? 'Close' : 'Open';
                            button.removeClass('btn-outline-warning btn-outline-success')
                                .addClass(btnClassAdd)
                                .attr('data-status', response.new_status.toLowerCase())
                                .attr('title', btnLabel + ' Schedule')
                                .html(`<span class="fw-bold">${btnLabel}</span> <i class="fas fa-exchange-alt ms-1"></i>`);

                            // Dynamically handle Scan button without reload
                            const row = button.closest('tr');
                            const btnGroup = row.find('.btn-group');

                            function formatTime(hms) {
                                if (!hms) return '';
                                const parts = hms.split(':');
                                let h = parseInt(parts[0], 10);
                                const m = parts[1];
                                const ampm = h >= 12 ? 'PM' : 'AM';
                                h = h % 12; if (h === 0) h = 12;
                                return `${h}:${m} ${ampm}`;
                            }
                            function buildScanLabelFromRow($row) {
                                const grade = $row.data('grade');
                                const section = $row.data('section');
                                const subject = $row.data('subject');
                                const day = $row.data('day');
                                const start = formatTime(($row.data('start') || '').toString());
                                const end = formatTime(($row.data('end') || '').toString());
                                return `Grade ${grade} - ${section} | ${subject} | ${day} ${start} - ${end}`;
                            }

                            if (response.new_status === 'Open') {
                                const teacherIdOfRow = parseInt(row.data('teacher-id'), 10);
                                if (CURRENT_USER_ROLE === 'teacher' && CURRENT_TEACHER_ID && teacherIdOfRow === CURRENT_TEACHER_ID) {
                                    if (btnGroup.find('.scan-qr').length === 0) {
                                        const scheduleId = row.data('schedule-id');
                                        const label = buildScanLabelFromRow(row);
                                        const $scanBtn = $('<button/>', {
                                            'class': 'btn btn-outline-dark border-0 shadow-sm scan-qr',
                                            'title': 'Scan QR for Attendance'
                                        })
                                        .attr('data-id', scheduleId)
                                        .attr('data-label', label)
                                        .append('Scan ')
                                        .append($('<i/>', { 'class': 'fas fa-qrcode' }));

                                        const $deleteBtn = btnGroup.find('.delete-schedule').last();
                                        if ($deleteBtn.length) {
                                            $scanBtn.insertBefore($deleteBtn);
                                        } else {
                                            btnGroup.append($scanBtn);
                                        }
                                    }
                                } else {
                                    btnGroup.find('.scan-qr').remove();
                                }
                            } else {
                                // Closed: remove scan button if present
                                btnGroup.find('.scan-qr').remove();
                            }
                        } else {
                            showAlert(response.error || 'Failed to toggle status', 'error');
                        }
                    },
                    error: function(xhr) {
                        const errorMsg = xhr.responseJSON?.error || 'Failed to toggle status';
                        showAlert(errorMsg, 'error');
                    },
                    complete: function() {
                        button.prop('disabled', false);
                    }
                });
            }
        });
    });

    // Edit Schedule
    $('.edit-schedule').click(function() {
        const id = $(this).data('id');
        const day = $(this).data('day');
        const start = $(this).data('start');
        const end = $(this).data('end');
        
        $('#edit_schedule_id').val(id);
        $('#edit_day_of_week').val(day);
        $('#edit_start_time').val(start);
        $('#edit_end_time').val(end);
        
        $('#editScheduleModal').modal('show');
    });
    
    $('#editScheduleForm').submit(function(e) {
        e.preventDefault();
        const form = this;
        // Client-side time validation
        const startTime = $('#edit_start_time').val();
        const endTime = $('#edit_end_time').val();
        if (!startTime || !endTime) {
            form.classList.add('was-validated');
            showAlert('Please select both start and end time.', 'error');
            return;
        }
        if (endTime <= startTime) {
            form.classList.add('was-validated');
            showAlert('End time must be after start time.', 'error');
            return;
        }
        // Optional: check required fields
        if (!form.checkValidity()) {
            e.stopPropagation();
            form.classList.add('was-validated');
            return;
        }
        const formData = $(this).serialize();
        
        $.ajax({
            url: '<?php echo BASE_URL; ?>/admin/api/update_schedule.php',
            method: 'POST',
            data: formData,
            dataType: 'json',
            success: function(response) {
                $('#editScheduleModal').modal('hide');
                if (response.success) {
                    showAlert('Schedule updated successfully!');
                    setTimeout(() => {
                        window.location.reload();
                    }, 2000);
                } else {
                    showAlert(response.error || 'Failed to update schedule', 'danger');
                }
            },
            error: function(xhr) {
                $('#editScheduleModal').modal('hide');
                const errorMsg = xhr.responseJSON?.error || 'Failed to update schedule';
                showAlert(errorMsg, 'danger');
            }
        });
    });
    
    // Handle Add Schedule Form Submission
    $('#addScheduleForm').on('submit', function(e) {
        e.preventDefault();
        
        const form = this;
        
        // Check form validity
        if (!form.checkValidity()) {
            e.stopPropagation();
            form.classList.add('was-validated');
            return;
        }
        
        // Show loading state
        const submitBtn = $(form).find('button[type="submit"]');
        const originalBtnText = submitBtn.html();
        submitBtn.prop('disabled', true).html('<span class="spinner-border spinner-border-sm" role="status" aria-hidden="true"></span> Saving...');
        
        // Get form data
        const formData = new FormData(form);
        const formDataObj = {};
        formData.forEach((value, key) => {
            formDataObj[key] = value;
        });
        
        // Log form data for debugging
        console.log('Sending form data:', formDataObj);
        
        // Submit form via AJAX
        $.ajax({
            url: '', // Submit to same page
            method: 'POST',
            data: formDataObj,
            dataType: 'json',
            headers: {
                'X-Requested-With': 'XMLHttpRequest',
                'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8'
            },
            success: function(response) {
                console.log('Server response:', response);
                
                if (response && response.success) {
                    // Show success message
                    showAlert(response.message, 'success');
                    
                    // Close the modal
                    const modal = bootstrap.Modal.getInstance(document.getElementById('addScheduleModal'));
                    modal.hide();
                    
                    // Reset the form
                    document.getElementById('addScheduleForm').reset();
                    document.getElementById('addScheduleForm').classList.remove('was-validated');
                    
                    // Reload the page to show the new schedule
                    setTimeout(() => {
                        window.location.reload();
                    }, 1000);
                } else {
                    // Show error message if any
                    submitBtn.prop('disabled', false).html(originalBtnText);
                    const errorMsg = response?.error || 'An unknown error occurred';
                    console.error('Error saving schedule:', errorMsg);
                    showAlert(errorMsg, 'error');
                }
            },
            error: function(xhr, status, error) {
                submitBtn.prop('disabled', false).html(originalBtnText);
                
                // Log detailed error information to console
                console.error('AJAX Error:', {
                    status: xhr.status,
                    statusText: xhr.statusText,
                    responseText: xhr.responseText,
                    error: error
                });
                
                let errorMsg = 'Failed to save schedule';
                let conflictData = null;
                
                try {
                    const response = JSON.parse(xhr.responseText);
                    errorMsg = response.error || errorMsg;
                    
                    // Try to parse the error message as JSON (conflict data)
                    try {
                        conflictData = JSON.parse(errorMsg);
                    } catch (e) {
                        // Not JSON, just a regular error message
                    }
                } catch (e) {
                    errorMsg = xhr.responseText || errorMsg;
                }
                
                // If we have conflict data, show the conflict modal
                if (conflictData && conflictData.conflict) {
                    showConflictModal(conflictData);
                } else {
                    showAlert(errorMsg, 'error');
                }
            }
        });
    });
    
    // Delete Schedule with SweetAlert2 confirmation
    $(document).on('click', '.delete-schedule', function() {
        const button = $(this);
        const scheduleId = button.data('id');
        
        Swal.fire({
            title: 'Delete Schedule',
            text: 'Are you sure you want to delete this schedule? This action cannot be undone!',
            icon: 'warning',
            showCancelButton: true,
            confirmButtonColor: '#d33',
            cancelButtonColor: '#6c757d',
            confirmButtonText: 'Yes, delete it!',
            cancelButtonText: 'Cancel',
            reverseButtons: true
        }).then((result) => {
            if (result.isConfirmed) {
                // Show loading state
                Swal.fire({
                    title: 'Deleting...',
                    text: 'Please wait while we delete the schedule.',
                    allowOutsideClick: false,
                    didOpen: () => {
                        Swal.showLoading();
                    }
                });
                
                // Disable button while processing
                button.prop('disabled', true).html('<i class="fas fa-spinner fa-spin"></i>');
                
                $.ajax({
                    url: '<?php echo BASE_URL; ?>/admin/api/delete_schedule.php',
                    method: 'POST',
                    contentType: 'application/json',
                    data: JSON.stringify({ schedule_id: scheduleId }),
                    dataType: 'json',
                    success: function(response) {
                        Swal.close();
                        if (response.success) {
                            showAlert(response.message);
                            // Remove the row with animation
                            button.closest('tr').fadeOut(400, function() {
                                $(this).remove();
                                // Show message if no more rows
                                if ($('#schedulesTable tbody tr').length === 0) {
                                    $('#schedulesTable tbody').html(
                                        '<tr><td colspan="7" class="text-center py-4">No schedules found</td></tr>'
                                    );
                                }
                            });
                        } else {
                            showAlert(response.error || 'Failed to delete schedule', 'error');
                            button.prop('disabled', false).html('<i class="fas fa-trash-alt"></i>');
                        }
                    },
                    error: function(xhr) {
                        Swal.close();
                        const errorMsg = xhr.responseJSON?.error || 'Failed to delete schedule';
                        showAlert(errorMsg, 'error');
                        button.prop('disabled', false).html('<i class="fas fa-trash-alt"></i>');
                    }
                });
            }
        });
    });
    
    // Search functionality
    $('#searchInput').on('keyup', function() {
        const value = $(this).val().toLowerCase();
        $('#schedulesTable tbody tr').filter(function() {
            $(this).toggle($(this).text().toLowerCase().indexOf(value) > -1);
        });
    });
    
    // -----------------------------
    // Teacher QR Scanning Handlers
    // -----------------------------
    let scanContext = {
        scheduleId: null,
        label: '',
        scanner: null,
        isActive: false,
        isPaused: false,
        lastPostAt: 0
    };

    function setScanMessage(html, type = 'info') {
        $('#scanResult').html(`<div class="alert alert-${type} py-2 px-3 mb-0">${html}</div>`);
    }

    // Ensure html5-qrcode library is loaded (fallback to dynamic load if footer didn't load it yet)
    let html5qrcodeLoadPromise = null;
    function ensureHtml5QrcodeLoaded() {
        if (window.Html5Qrcode) return Promise.resolve();
        if (html5qrcodeLoadPromise) return html5qrcodeLoadPromise;
        html5qrcodeLoadPromise = new Promise((resolve, reject) => {
            const script = document.createElement('script');
            // Use the same URL as in the working student dashboard
            script.src = 'https://unpkg.com/html5-qrcode';
            script.async = true;
            script.onload = () => resolve();
            script.onerror = () => reject(new Error('Failed to load html5-qrcode library'));
            document.head.appendChild(script);
        });
        return html5qrcodeLoadPromise;
    }

    async function startScanner(preferredCamId = null) {
        try {
            await ensureHtml5QrcodeLoaded();
            if (!window.Html5Qrcode) {
                setScanMessage('Scanner library not loaded. Please reload the page.', 'danger');
                return;
            }
            if (scanContext.scanner) {
                try { await scanContext.scanner.stop(); } catch (e) {}
                try { await scanContext.scanner.clear(); } catch (e) {}
                scanContext.scanner = null;
            }
            scanContext.scanner = new Html5Qrcode('qr-reader');
            const devices = await Html5Qrcode.getCameras();
            if (!devices || devices.length === 0) {
                setScanMessage('No camera devices found or permission denied.', 'danger');
                return;
            }
            // Decide best default camera (prefer physical/integrated over virtual) and populate UI
            let camId = preferredCamId || pickBestCamera(devices);
            populateCameraSelect(devices, camId);
            const selected = document.getElementById('cameraSelect');
            if (!preferredCamId && selected && selected.value) camId = selected.value;

            const config = { fps: 10, qrbox: { width: 280, height: 280 }, aspectRatio: 1.7778 };    
            await scanContext.scanner.start(
                camId ? { deviceId: { exact: camId } } : { facingMode: 'environment' },
                config,
                onScanSuccess,
                onScanError
            );
            scanContext.isActive = true;
            $('#scanPermissionBtn').hide();
            setScanMessage('Point the camera at a student QR (Student ID).', 'info');
        } catch (err) {
            setScanMessage('Unable to start camera: ' + (err?.message || err), 'danger');
        }
    }

    function populateCameraSelect(devices, selectedId = null) {
        const select = document.getElementById('cameraSelect');
        const refreshBtn = document.getElementById('refreshCamerasBtn');
        if (!select || !refreshBtn) return;
        select.innerHTML = '';
        devices.forEach((d, idx) => {
            const opt = document.createElement('option');
            opt.value = d.id;
            opt.textContent = d.label || `Camera ${idx + 1}`;
            if (selectedId && d.id === selectedId) opt.selected = true;
            select.appendChild(opt);
        });
        if (devices.length > 0) {
            select.style.display = '';
            refreshBtn.style.display = '';
        } else {
            select.style.display = 'none';
            refreshBtn.style.display = 'none';
        }
    }

    function pickBestCamera(devices) {
        if (!devices || devices.length === 0) return null;
        // Filter out common virtual cameras
        const isVirtual = (label) => /virtual|obs|snap|droidcam|manycam|epoccam|splitcam/i.test(label || '');
        const looksPhysical = (label) => /(integrated|built[- ]?in|webcam|camera|facetime|hd|ir)/i.test(label || '');
        // 1) Prefer back/rear cameras (mobile devices)
        const rear = devices.find(d => /back|rear/i.test(d.label));
        if (rear) return rear.id;
        // 2) Prefer physical-looking cams, excluding virtual
        const physical = devices.find(d => !isVirtual(d.label) && looksPhysical(d.label));
        if (physical) return physical.id;
        // 3) Any non-virtual
        const nonVirtual = devices.find(d => !isVirtual(d.label));
        if (nonVirtual) return nonVirtual.id;
        // 4) Fallback: first device
        return devices[0].id;
    }

    function pauseScanner() {
        if (scanContext.scanner && scanContext.isActive && !scanContext.isPaused) {
            try { scanContext.scanner.pause(true); scanContext.isPaused = true; } catch (e) {}
        }
    }
    function resumeScanner() {
        if (scanContext.scanner && scanContext.isActive && scanContext.isPaused) {
            try { scanContext.scanner.resume(); scanContext.isPaused = false; } catch (e) {}
        }
    }
    async function stopScanner() {
        if (scanContext.scanner) {
            try { await scanContext.scanner.stop(); } catch (e) {}
            try { await scanContext.scanner.clear(); } catch (e) {}
        }
        scanContext.scanner = null;
        scanContext.isActive = false;
        scanContext.isPaused = false;
    }

    function onScanError(err) {
        // ignore frequent decode errors
    }

    function onScanSuccess(decodedText) {
        // Debounce duplicate scans
        const now = Date.now();
        if (now - scanContext.lastPostAt < 1000) return;
        scanContext.lastPostAt = now;

        const code = (decodedText || '').trim();
        if (!/^[A-Za-z0-9-]+$/.test(code)) {
            setScanMessage('Invalid QR content. Expected student ID (digits and dashes).', 'warning');
            return;
        }

        pauseScanner();
        setScanMessage('Processing scan...', 'secondary');

        $.ajax({
            url: '<?php echo BASE_URL; ?>/admin/teacher/api/scan_attendance.php',
            method: 'POST',
            data: {
                schedule_id: scanContext.scheduleId,
                qr_code: code
            },
            dataType: 'json'
        }).done(function(resp){
            if (resp.success) {
                if (resp.action === 'in') {
                    showAlert('IN recorded (' + (resp.status || 'Present') + ')');
                    setScanMessage('IN recorded (' + (resp.status || 'Present') + '). Scan next student...', 'success');
                } else if (resp.action === 'out') {
                    showAlert('OUT recorded');
                    setScanMessage('OUT recorded. Attendance completed for this student. Continue scanning...', 'success');
                } else {
                    setScanMessage('Scan processed.', 'info');
                }
            } else {
                const err = resp.error || 'Failed to record attendance';
                showAlert(err, 'error');
                setScanMessage(err, 'danger');
            }
        }).fail(function(xhr){
            const err = xhr.responseJSON?.error || 'Server error while recording attendance';
            showAlert(err, 'error');
            setScanMessage(err, 'danger');
        }).always(function(){
            setTimeout(() => { resumeScanner(); }, 800);
        });
    }

    // Open modal and start scanner
    $(document).on('click', '.scan-qr', function(){
        const scheduleId = $(this).data('id');
        const label = $(this).data('label') || '';
        scanContext.scheduleId = scheduleId;
        scanContext.label = label;
        $('#scanScheduleLabel').text(label);
        $('#scanQrModal').modal('show');
    });

    // Camera permission button
    $('#scanPermissionBtn').on('click', async function(){
        try {
            const stream = await navigator.mediaDevices.getUserMedia({ video: { facingMode: 'environment' } });
            stream.getTracks().forEach(t => t.stop());
            await startScanner();
        } catch (e) {
            setScanMessage('Camera permission denied. Please enable camera access in browser settings.', 'warning');
        }
    });

    $('#refreshCamerasBtn').on('click', async function(){
        try {
            const devices = await Html5Qrcode.getCameras();
            populateCameraSelect(devices || []);
        } catch (e) {
            setScanMessage('Unable to refresh cameras: ' + (e?.message || e), 'warning');
        }
    });

    $('#cameraSelect').on('change', async function(){
        const camId = this.value;
        try { localStorage.setItem('qrPreferredCamId', camId); } catch (_) {}
        await stopScanner();
        await startScanner(camId);
    });

    $('#scanQrModal').on('shown.bs.modal', async function(){
        $('#scanPermissionBtn').show();
        if (!window.isSecureContext && !['localhost','127.0.0.1'].includes(location.hostname)) {
            setScanMessage('Camera access requires HTTPS when not on localhost.', 'warning');
            return;
        }
        try { 
            // Try to list cameras first to prompt permission in some browsers
            try {
                const devices = await Html5Qrcode.getCameras();
                let pref = null;
                try { pref = localStorage.getItem('qrPreferredCamId'); } catch(_) {}
                populateCameraSelect(devices || [], pref || undefined);
            } catch (_) {}
            let prefId = null; 
            try { prefId = localStorage.getItem('qrPreferredCamId'); } catch(_) {}
            await startScanner(prefId || undefined); 
        } catch(e) { /* fallback to permission button */ }
    });
    $('#scanQrModal').on('hidden.bs.modal', function(){
        stopScanner();
        $('#scanResult').empty();
        $('#scanScheduleLabel').empty();
        scanContext.scheduleId = null;
    });
    $('#restartScannerBtn').on('click', function(){
        stopScanner().then(startScanner);
    });

    // Initialize tooltips
    const tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'));
    tooltipTriggerList.map(function (tooltipTriggerEl) {
        return new bootstrap.Tooltip(tooltipTriggerEl);
    });  
    if(window.history.replaceState) {
        window.history.replaceState({}, document.title, window.location.pathname);
    }
});
</script>

<?php 
// Close database connection if it's not needed anymore
if (isset($conn)) {
    $conn->close();
}

// Include footer with additional JS
$GLOBALS['additionalFooterJS'] = $footerJS ?? [];
include '../includes/footer.php'; 
?>
