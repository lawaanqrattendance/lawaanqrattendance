<?php
include '../../includes/header.php';
requireLogin();

if (getUserRole() !== 'teacher') {
    header("Location: ../../index.php");
    exit();
}

$teacher_id = $_SESSION['teacher_id'];
$current_date = date('Y-m-d');
$current_time = date('H:i:s');
$day_of_week = date('l'); // Gets current day name (Monday, Tuesday, etc.)

// Get teacher's schedules for today
$query = "SELECT s.*, 
          sub.subject_name,
          sec.section_name,
          sec.grade_level
          FROM schedules s
          JOIN subjects sub ON s.subject_id = sub.subject_id
          JOIN sections sec ON s.section_id = sec.section_id
          WHERE s.teacher_id = ? 
          AND s.day_of_week = ?
          ORDER BY s.start_time";

$stmt = $conn->prepare($query);
$stmt->bind_param("is", $teacher_id, $day_of_week);
$stmt->execute();
$schedules = $stmt->get_result();

// Get recent attendance records
$recent_query = "SELECT a.*, 
                 s.firstname, s.lastname,
                 sub.subject_name,
                 sec.section_name
                 FROM attendance a
                 JOIN schedules sch ON a.schedule_id = sch.schedule_id
                 JOIN students s ON a.student_id = s.student_id
                 JOIN subjects sub ON sch.subject_id = sub.subject_id
                 JOIN sections sec ON sch.section_id = sec.section_id
                 WHERE sch.teacher_id = ?
                 AND a.attendance_date = ?
                 ORDER BY a.created_at DESC
                 LIMIT 10";

$stmt = $conn->prepare($recent_query);
$stmt->bind_param("is", $teacher_id, $current_date);
$stmt->execute();
$recent_attendance = $stmt->get_result();
?>

<style>
:root {
    --primary-color: #4361ee;
    --success-color: #06d6a0;
    --info-color: #4895ef;
    --warning-color: #f4a261;
    --danger-color: #ef476f;
    --dark-color: #1a1a2e;
    --light-color: #f8f9fa;
    --card-shadow: 0 4px 6px rgba(0, 0, 0, 0.05), 0 1px 3px rgba(0, 0, 0, 0.1);
    --card-hover-shadow: 0 10px 15px -3px rgba(0, 0, 0, 0.1), 0 4px 6px -2px rgba(0, 0, 0, 0.05);
    --transition: all 0.3s ease;
}

body {
    background-color: #99CFD0;
    color: #333;
    font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
}

.dashboard-header {
    margin-bottom: 30px;
    padding: 20px 0;
    border-bottom: 1px solid #e0e6ed;
}

.dashboard-header h2 {
    color: var(--dark-color);
    font-weight: 700;
    margin: 0;
    font-size: 1.8rem;
}

.dashboard-header p {
    color: #6c757d;
    margin: 8px 0 0;
    font-size: 1rem;
}

/* Action Buttons */
.btn-primary {
    background-color: var(--primary-color);
    border-color: var(--primary-color);
    padding: 0.6rem 1.25rem;
    border-radius: 8px;
    font-weight: 500;
    transition: var(--transition);
}

.btn-primary:hover {
    background-color: #4a6cf7;
    border-color: #4a6cf7;
    transform: translateY(-1px);
}

.btn-info {
    background-color: var(--info-color);
    border-color: var(--info-color);
    padding: 0.6rem 1.25rem;
    border-radius: 8px;
    font-weight: 500;
    transition: var(--transition);
}

.btn-info:hover {
    background-color: #3a559f;
    border-color: #3a559f;
    transform: translateY(-1px);
}

/* Card Styles */
.card {
    border: none;
    border-radius: 12px;
    box-shadow: var(--card-shadow);
    transition: var(--transition);
    margin-bottom: 25px;
    background: white;
}

.card:hover {
    transform: translateY(-5px);
    box-shadow: var(--card-hover-shadow);
}

.card-header {
    background-color: white;
    border-bottom: 1px solid rgba(0, 0, 0, 0.05);
    padding: 1.25rem 1.5rem;
    border-radius: 12px 12px 0 0 !important;
}

.card-header h5 {
    margin: 0;
    font-weight: 600;
    color: var(--dark-color);
    font-size: 1.1rem;
}

/* Table Styles */
.table {
    margin-bottom: 0;
}

.table th {
    font-weight: 600;
    color: #4a5568;
    background-color: white;
    border-bottom: 1px solid rgba(0, 0, 0, 0.05);
}

.table td {
    vertical-align: middle;
    padding: 1rem;
}

.table-active {
    background-color: rgba(67, 97, 238, 0.1) !important;
}

/* Badge Styles */
.badge {
    padding: 0.5em 1em;
    border-radius: 100px;
    font-weight: 500;
    font-size: 0.875rem;
}

.badge-success {
    background-color: var(--success-color);
}

.badge-secondary {
    background-color: #e2e8f0;
    color: #4a5568;
}

/* Modal Styles */
.modal-content {
    border: none;
    border-radius: 12px;
    overflow: hidden;
    box-shadow: 0 10px 30px rgba(0, 0, 0, 0.1);
}

.modal-header {
    padding: 1.25rem 1.5rem;
    border-bottom: 1px solid rgba(0, 0, 0, 0.05);
}

.modal-body {
    padding: 1.5rem;
}

.modal-footer {
    border-top: 1px solid rgba(0, 0, 0, 0.05);
    padding: 1rem 1.5rem;
}

/* Form Styles */
.form-control, .form-select {
    padding: 0.75rem 1rem;
    border-radius: 8px;
    border: 1px solid #e2e8f0;
    transition: all 0.2s ease;
}

.form-control:focus, .form-select:focus {
    border-color: var(--primary-color);
    box-shadow: 0 0 0 0.25rem rgba(67, 97, 238, 0.1);
}

.form-label {
    font-weight: 500;
    color: #4a5568;
    margin-bottom: 0.5rem;
}

/* Calendar Section */
.calendar-section .card {
    border: none;
    border-radius: 12px;
    box-shadow: var(--card-shadow);
    overflow: hidden;
}

.calendar-section .card-header {
    background-color: white;
    border-bottom: 1px solid rgba(0, 0, 0, 0.05);
    padding: 1.25rem 1.5rem;
}

.calendar-section .card-header h5 {
    margin: 0;
    font-weight: 600;
    color: var(--dark-color);
    font-size: 1.1rem;
}

/* FullCalendar Customization */
#calendar {
    background: white;
    border-radius: 8px;
    padding: 15px;
}

.fc {
    font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
}

.fc-header-toolbar {
    margin-bottom: 1.5em !important;
    flex-wrap: wrap;
    gap: 10px;
}

.fc-toolbar-title {
    font-size: 1.25rem !important;
    font-weight: 600;
    color: var(--dark-color);
}

.fc-button {
    background-color: white !important;
    border: 1px solid #e2e8f0 !important;
    color: #4a5568 !important;
    text-transform: capitalize !important;
    border-radius: 6px !important;
    padding: 6px 12px !important;
    font-size: 0.875rem !important;
    font-weight: 500 !important;
    box-shadow: none !important;
    transition: all 0.2s ease !important;
}

.fc-button:hover {
    background-color: #f8f9fa !important;
    border-color: #cbd5e0 !important;
}

.fc-button-active, .fc-button:active {
    background-color: var(--primary-color) !important;
    border-color: var(--primary-color) !important;
    color: white !important;
}

.fc-day-today {
    background-color: rgba(67, 97, 238, 0.05) !important;
}

.fc-day-today .fc-daygrid-day-number {
    color: var(--primary-color);
    font-weight: 600;
}

.fc-event {
    cursor: pointer;
    border: none !important;
    border-radius: 4px;
    padding: 2px 5px;
    font-size: 0.8rem;
    background-color: var(--primary-color);
    color: white;
}

.fc-event:hover {
    opacity: 0.9;
}

/* Modal Styles */
.modal-content {
    border: none;
    border-radius: 12px;
    overflow: hidden;
    box-shadow: 0 10px 30px rgba(0, 0, 0, 0.1);
}

.modal-header {
    padding: 1.25rem 1.5rem;
    border-bottom: 1px solid rgba(0, 0, 0, 0.05);
}

.modal-body {
    padding: 1.5rem;
}

.modal-footer {
    border-top: 1px solid rgba(0, 0, 0, 0.05);
    padding: 1rem 1.5rem;
}

.modal-icon-wrapper {
    width: 36px;
    height: 36px;
    border-radius: 10px;
    background-color: rgba(67, 97, 238, 0.1);
    display: flex;
    align-items: center;
    justify-content: center;
    color: var(--primary-color);
}

.form-control, .form-select {
    padding: 0.75rem 1rem;
    border-radius: 8px;
    border: 1px solid #e2e8f0;
    transition: all 0.2s ease;
}

.form-control:focus, .form-select:focus {
    border-color: var(--primary-color);
    box-shadow: 0 0 0 0.25rem rgba(67, 97, 238, 0.1);
}

.form-label {
    font-weight: 500;
    color: #4a5568;
    margin-bottom: 0.5rem;
}

.btn {
    padding: 0.6rem 1.25rem;
    border-radius: 8px;
    font-weight: 500;
    transition: all 0.2s ease;
    display: inline-flex;
    align-items: center;
    justify-content: center;
}

.btn i {
    font-size: 0.9em;
}

.btn-primary {
    background-color: var(--primary-color);
    border-color: var(--primary-color);
}

.btn-primary:hover {
    background-color: #4a6cf7;
    border-color: #4a6cf7;
    transform: translateY(-1px);
}

.btn-outline-secondary {
    border-color: #e2e8f0;
    color: #4a5568;
}

.btn-outline-secondary:hover {
    background-color: #f8f9fa;
    border-color: #cbd5e0;
    color: #2d3748;
}

.btn-outline-danger {
    color: #e53e3e;
    border-color: #fc8181;
}

.btn-outline-danger:hover {
    background-color: #fff5f5;
    border-color: #e53e3e;
    color: #e53e3e;
}

/* Custom Scrollbar */
::-webkit-scrollbar {
    width: 8px;
    height: 8px;
}

::-webkit-scrollbar-track {
    background: #f1f1f1;
    border-radius: 4px;
}

::-webkit-scrollbar-thumb {
    background: #c1c1c1;
    border-radius: 4px;
}

::-webkit-scrollbar-thumb:hover {
    background: #a8a8a8;
}

/* Toast Notifications */
.toast {
    border: none;
    border-radius: 8px;
    box-shadow: 0 4px 12px rgba(0, 0, 0, 0.1);
    transition: opacity 0.3s ease-out;
    opacity: 0;
}

.toast.success {
    border-left: 4px solid var(--success-color);
}

.toast.warning {
    border-left: 4px solid var(--warning-color);
}

.toast.danger {
    border-left: 4px solid var(--danger-color);
}

.toast-message {
    color: #333;
    font-size: 14px;
}

/* Responsive Adjustments */
@media (max-width: 768px) {
    .dashboard-header h2 {
        font-size: 1.5rem;
    }
    
    .btn {
        width: 100%;
        margin-bottom: 10px;
    }
    
    .fc-toolbar {
        flex-direction: column;
        flex-wrap: wrap;
        justify-content: center;
        gap: 0.5rem;
    }
}
</style>

<div class="container-fluid">
    <div class="row mb-4">
        <div class="col-12">
            <div class="dashboard-header text-center mb-4">
                <h2 class="mb-2">Teacher Dashboard</h2>
                <p class="text-muted">Here's what's happening with your classes today</p>
            </div>
        </div>
        
        <div class="col-12">
            <div class="d-flex flex-column flex-md-row justify-content-between align-items-center gap-3">
                <div class="flex-grow-1">
                    <p class="text-muted mb-0">Today is <?php echo date('l, F j, Y'); ?></p>
                </div>
                
                <div class="d-flex gap-2 flex-wrap action-buttons">
                    <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addStudentModal">
                        <i class="fas fa-plus me-2"></i> Add Student
                    </button>
                    <a href="<?php echo BASE_URL; ?>/admin/manage_schedules.php" class="btn btn-primary">
                        <i class="fas fa-calendar-alt me-2"></i> View Schedule
                    </a>
                    <a href="<?php echo BASE_URL; ?>/admin/teacher/attendance_reports.php" class="btn btn-info">
                        <i class="fas fa-chart-bar me-2"></i> View Reports
                    </a>
                    <div class="dropdown">
                        <a class="btn btn-light" href="#" role="button" data-bs-toggle="dropdown">
                            <i class="fas fa-cog"></i>
                        </a>
                        <ul class="dropdown-menu dropdown-menu-end" style="background-color: var(--secondary-color);">
                            <li>
                                <a class="dropdown-item text-white" href="#" data-bs-toggle="modal" data-bs-target="#changePasswordModal" id="changePasswordDropdown">
                                    <i class="fas fa-key me-2"></i>Change Password
                                </a>
                            </li>
                        </ul>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Add Student Modal -->
<div class="modal fade" id="addStudentModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-0 shadow-lg">
            <div class="modal-header bg-light border-0 py-3">
                <h5 class="modal-title text-primary fw-bold">
                    <i class="fas fa-user-plus me-2"></i>Add New Student
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form id="addStudentForm">
                <div class="modal-body p-4">
                    <div class="mb-4">
                        <label class="form-label fw-medium mb-2">Student ID <span class="text-danger">*</span></label>
                        <div class="input-group">
                            <span class="input-group-text bg-light border-end-0">
                                <i class="fas fa-id-card text-muted"></i>
                            </span>
                            <input type="text"
                                   class="form-control ps-2"
                                   name="student_id"
                                   id="studentId"
                                   required
                                   placeholder="Enter student ID">
                            <span class="input-group-text bg-light border-start-0" id="idAvailabilityIndicator"></span>
                        </div>
                        <div class="invalid-feedback" id="studentIdFeedback">
                            Please provide a valid student ID
                        </div>
                        <small id="idAvailabilityMessage" class="text-muted"></small>
                    </div>
                    
                    <div class="row g-3 mb-4">
                        <div class="col-md-6">
                            <label class="form-label fw-medium mb-2">First Name <span class="text-danger">*</span></label>
                            <div class="input-group">
                                <span class="input-group-text bg-light border-end-0">
                                    <i class="fas fa-user text-muted"></i>
                                </span>
                                <input type="text"
                                       class="form-control ps-2"
                                       name="firstname"
                                       required
                                       placeholder="John">
                            </div>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-medium mb-2">Last Name <span class="text-danger">*</span></label>
                            <div class="input-group">
                                <span class="input-group-text bg-light border-end-0">
                                    <i class="fas fa-user text-muted"></i>
                                </span>
                                <input type="text" 
                                       class="form-control ps-2" 
                                       name="lastname" 
                                       required
                                       placeholder="Doe">
                            </div>
                        </div>
                    </div>
                    
                    <div class="mb-4">
                        <label class="form-label fw-medium mb-2">Middle Name</label>
                        <div class="input-group">
                            <span class="input-group-text bg-light border-end-0">
                                <i class="fas fa-user text-muted"></i>
                            </span>
                            <input type="text" 
                                   class="form-control ps-2" 
                                   name="middlename"
                                   placeholder="(Optional)">
                        </div>
                    </div>

                    <div class="mb-4">
                        <label class="form-label fw-medium mb-2">Email Address <span class="text-danger">*</span></label>
                        <div class="input-group">
                            <span class="input-group-text bg-light border-end-0">
                                <i class="fas fa-envelope text-muted"></i>
                            </span>
                            <input type="email" 
                                   class="form-control ps-2" 
                                   name="email" 
                                   required
                                   placeholder="student@example.com">
                        </div>
                        <small class="text-muted">Student will receive login credentials at this email</small>
                    </div>
                    
                    <div class="mb-4">
                        <label class="form-label fw-medium mb-2">Guardian Email</label>
                        <div class="input-group">
                            <span class="input-group-text bg-light border-end-0">
                                <i class="fas fa-user-shield text-muted"></i>
                            </span>
                            <input type="email" 
                                   class="form-control ps-2" 
                                   name="guardian_email" 
                                   placeholder="guardian@example.com (optional)">
                        </div>
                        <small class="text-muted">Used for attendance notifications</small>
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label fw-medium mb-2">Section <span class="text-danger">*</span></label>
                        <div class="input-group">
                            <span class="input-group-text bg-light border-end-0">
                                <i class="fas fa-layer-group text-muted"></i>
                            </span>
                            <select class="form-select ps-2" name="section_id" required>
                                <option value="">Select Section</option>
                                <?php
                                // Get sections assigned to this teacher
                                $stmt = $conn->prepare("SELECT DISTINCT s.section_id, s.section_name, s.grade_level 
                                                      FROM sections s 
                                                      JOIN teacher_sections ts ON s.section_id = ts.section_id 
                                                      WHERE ts.teacher_id = ? 
                                                      ORDER BY s.grade_level, s.section_name");
                                $stmt->bind_param("i", $_SESSION['teacher_id']);
                                $stmt->execute();
                                $sections = $stmt->get_result();
                                
                                while ($section = $sections->fetch_assoc()): ?>
                                    <option value="<?php echo $section['section_id']; ?>">
                                        Grade <?php echo $section['grade_level']; ?> - <?php echo $section['section_name']; ?>
                                    </option>
                                <?php endwhile; ?>
                            </select>
                        </div>
                    </div>
                </div>
                <div class="modal-footer bg-light border-top-0 pt-0 px-4 pb-4">
                    <button type="button" class="btn btn-outline-secondary rounded-pill px-4" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary rounded-pill px-4">
                        <i class="fas fa-plus me-1"></i> Add Student
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<div class="row mb-4">
    <!-- Today's Schedule -->
    <div class="col-md-8">
        <div class="card">
            <div class="card-header">
                <h5 class="card-title mb-0">Today's Schedule</h5>
            </div>
            <div class="card-body">
                <?php if ($schedules->num_rows > 0): ?>
                    <div class="table-responsive">
                        <table class="table">
                            <thead>
                                <tr>
                                    <th>Time</th>
                                    <th>Subject</th>
                                    <th>Section</th>
                                    <th>Status</th>
                                    <th>Action</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php while ($schedule = $schedules->fetch_assoc()):
                                    $start_time = strtotime($schedule['start_time']);
                                    $end_time = strtotime($schedule['end_time']);
                                    $current = strtotime($current_time);
                                    $is_current = ($current >= $start_time && $current <= $end_time);
                                ?>
                                    <tr <?php echo $is_current ? 'class="table-active"' : ''; ?>>
                                        <td><?php echo date('h:i A', $start_time) . ' - ' . date('h:i A', $end_time); ?></td>
                                        <td><?php echo $schedule['subject_name']; ?></td>
                                        <td>Grade <?php echo $schedule['grade_level'] . ' - ' . $schedule['section_name']; ?></td>
                                        <td>
                                            <span class="badge bg-<?php echo $schedule['status'] === 'Open' ? 'success' : 'secondary'; ?>">
                                                <?php echo $schedule['status']; ?>
                                            </span>
                                        </td>
                                        <td>
                                            <a href="<?php echo BASE_URL; ?>/admin/teacher/view_section.php?section_id=<?php echo $schedule['section_id']; ?>" 
                                               class="btn btn-sm btn-primary">
                                                View Section
                                            </a>
                                        </td>
                                    </tr>
                                <?php endwhile; ?>
                            </tbody>
                        </table>
                    </div>
                <?php else: ?>
                    <p class="text-muted">No classes scheduled for today.</p>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- Recent Attendance -->
    <div class="col-md-4">
        <div class="card">
            <div class="card-header">
                <h5 class="card-title mb-0">Recent Attendance</h5>
            </div>
            <div class="card-body" style="height: 300px; overflow-y: auto;">
                <?php if ($recent_attendance->num_rows > 0): ?>
                    <div class="list-group">
                        <?php while ($record = $recent_attendance->fetch_assoc()): ?>
                            <div class="list-group-item">
                                <div class="d-flex w-100 justify-content-between">
                                    <h6 class="mb-1"><?php echo $record['lastname'] . ', ' . $record['firstname']; ?></h6>
                                    <small class="text-muted"><?php echo date('h:i A', strtotime($record['created_at'])); ?></small>
                                </div>
                                <p class="mb-1">
                                    <?php echo $record['subject_name'] . ' - ' . $record['section_name']; ?>
                                </p>
                                <small style="font-weight: bold;" class="text-<?php echo $record['status'] === 'Present' ? 'success' : 
                                    ($record['status'] === 'Late' ? 'warning' : 'danger'); ?>">
                                    <?php echo $record['status']; ?>
                                </small>
                            </div>
                        <?php endwhile; ?>
                    </div>
                <?php else: ?>
                    <p class="text-muted">No attendance records for today.</p>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<div class="container-fluid">
    <div class="row">
        <div class="col-md-12" style="padding: 0 !important;">
            <div class="card">
                <div class="card-header">
                    <h5 class="card-title mb-0">My Sections</h5>
                </div>
                <div class="card-body" >
                    <div class="table-responsive" style="height: 300px; overflow-y: auto;">
                        <table class="table table-hover">
                            <thead>
                                <tr style="position: sticky !important; top: 0 !important; background-color: white;">
                                    <th>Grade Level</th>
                                    <th>Section</th>
                                    <th>Total Students</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php
                                $query = "SELECT s.*, 
                                         COUNT(st.student_id) as student_count 
                                         FROM sections s 
                                         LEFT JOIN students st ON s.section_id = st.section_id 
                                         JOIN teacher_sections ts ON s.section_id = ts.section_id 
                                         WHERE ts.teacher_id = ? 
                                         GROUP BY s.section_id 
                                         ORDER BY s.grade_level, s.section_name";
                                
                                $stmt = $conn->prepare($query);
                                $stmt->bind_param("i", $_SESSION['teacher_id']);
                                $stmt->execute();
                                $sections = $stmt->get_result();
                                
                                while ($section = $sections->fetch_assoc()):
                                ?>
                                <tr>
                                    <td>Grade <?php echo $section['grade_level']; ?></td>
                                    <td><?php echo $section['section_name']; ?></td>
                                    <td><?php echo $section['student_count']; ?></td>
                                    <td>
                                        <a href="view_section.php?section_id=<?php echo $section['section_id']; ?>" 
                                           class="btn btn-primary btn-sm">
                                            View Attendance
                                        </a>
                                    </td>
                                </tr>
                                <?php endwhile; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

    <!-- Calendar Section -->
    <div class="row mb-4">
        <div class="col-12">
            <div class="calendar-section">
                <div class="card">
                    <div class="card-header">
                        <h5><i class="far fa-calendar-alt me-2"></i>Calendar & Notes</h5>
                    </div>
                    <div class="card-body">
                        <div id="calendar"></div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div> <!-- End of container-fluid -->

<!-- Add Note Modal -->
<div class="modal fade" id="addNoteModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <div class="d-flex align-items-center">
                    <div class="modal-icon-wrapper me-2">
                        <i class="fas fa-sticky-note"></i>
                    </div>
                    <h5 class="modal-title">Add New Note</h5>
                </div>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <form id="addNoteForm">
                    <input type="hidden" id="noteDate" name="note_date">
                    <div class="mb-4">
                        <label for="noteTitle" class="form-label fw-medium text-muted mb-2">Title</label>
                        <input type="text" name="note_title" class="form-control form-control-lg" id="noteTitle" placeholder="Enter note title" required>
                        <div class="form-text">A short title for your note (max 100 characters)</div>
                    </div>
                    <div class="mb-4">
                        <label for="noteContent" class="form-label fw-medium text-muted mb-2">Note Content</label>
                        <textarea name="note_content" class="form-control" id="noteContent" rows="4" placeholder="Type your note here..." required></textarea>
                        <div class="form-text">Add details about your note (supports markdown)</div>
                    </div>
                    <div class="d-flex justify-content-end align-items-center">
                        <!-- <div class="form-check form-switch">
                            <input class="form-check-input" type="checkbox" id="importantNote" style="width: 2.5em; height: 1.25em;">
                            <label class="form-check-label ms-2" for="importantNote">Mark as important</label>
                        </div> -->
                        <div>
                            <button type="button" class="btn btn-outline-secondary me-2" data-bs-dismiss="modal">
                                <i class="fas fa-times me-1"></i> Cancel
                            </button>
                            <button type="submit" class="btn btn-primary">
                                <i class="fas fa-save me-1"></i> Save Note
                            </button>
                        </div>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<!-- View Note Modal -->
<div class="modal fade" id="viewNoteModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <input type="hidden" id="editNoteId" />
            <div class="modal-header">
                <div class="d-flex align-items-center">
                    <div class="modal-icon-wrapper me-2" id="noteIconWrapper">
                        <i class="fas fa-sticky-note"></i>
                    </div>
                    <div>
                        <h5 class="modal-title mb-0" id="viewNoteTitle"></h5>
                        <input type="text" class="form-control mb-2" id="editNoteTitle" style="display:none;">
                        <div class="text-muted small" id="noteDateInfo"></div>
                    </div>
                </div>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body py-4">
                <div class="note-content p-3 rounded bg-light" id="viewNoteContent"></div>
                <textarea class="form-control mt-2" id="editNoteContent" rows="4" style="display:none;"></textarea>
                <div class="mt-3 d-flex align-items-center" id="noteTags">
                    <span class="badge bg-light text-muted me-2">
                        <i class="far fa-calendar me-1"></i>
                        <span id="noteCreatedAt"></span>
                    </span>
                    <span class="badge bg-light text-muted" id="importantBadge" style="display: none;">
                        <i class="fas fa-exclamation-circle me-1"></i> Important
                    </span>
                </div>
            </div>
            <div class="modal-footer bg-light">
                <button type="button" class="btn btn-outline-danger me-auto" id="deleteNoteBtn">
                    <i class="far fa-trash-alt me-1"></i> Delete
                </button>
                <button type="button" class="btn btn-warning me-2" id="editNoteBtn">
                    <i class="fas fa-edit me-1"></i> Edit
                </button>
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">
                    <i class="fas fa-times me-1"></i> Close
                </button>
                <button type="button" class="btn btn-primary" id="updateNoteBtn" style="display:none;">
                    <i class="fas fa-save me-1"></i> Update
                </button>
            </div>
        </div>
    </div>
</div>

<?php include '../../includes/footer.php'; ?>

<!-- Delete Note Modal -->
<?php include 'delete_note_modal.html'; ?>

<!-- Password Change Modal -->
<div class="modal fade" id="changePasswordModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Change Password</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form id="changePasswordForm">
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label">Current Password</label>
                        <input type="password" class="form-control" name="current_password" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">New Password</label>
                        <input type="password" class="form-control" name="new_password" minlength="8" required>
                        <div class="form-text">Password must be at least 8 characters long</div>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Confirm New Password</label>
                        <input type="password" class="form-control" name="confirm_password" minlength="8" required>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary">Change Password</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Toast Container -->
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

<!-- Loading Overlay -->
<div id="loadingOverlay" class="loading-overlay" style="display: none;">
    <div class="loading-content">
        <div class="spinner-border text-light mb-2" role="status">
            <span class="visually-hidden">Loading...</span>
        </div>
        <div class="text-light">Logging out...</div>
    </div>
</div>

<!-- Add these styles to your existing styles -->
<style>
.toast-container {
    position: fixed;
    bottom: 20px;
    left: 50%;
    transform: translateX(-50%);
    z-index: 1060;
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

.fc-event {
    cursor: pointer;
}
.fc-day-today {
    background-color: rgba(13, 110, 253, 0.1) !important;
}
.fc-header-toolbar {
    margin-bottom: 1rem !important;
}
</style>

<!-- Add this JavaScript before the closing </body> tag -->
<script>
// Modified showToast function with logout parameter
function showToast(message, type = 'success', shouldLogout = false) {
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
    
    // For success with logout, show loading overlay and redirect
    if (type === 'success' && shouldLogout) {
        setTimeout(() => {
            toast.style.opacity = '0';
            document.getElementById('loadingOverlay').style.display = 'flex';
            setTimeout(() => {
                window.location.href = '<?php echo BASE_URL; ?>/auth/logout.php';
            }, 1500);
        }, 2000);
    } else {
        // For other types or success without logout, just hide the toast
        setTimeout(() => {
            toast.style.opacity = '0';
            setTimeout(() => {
                toast.style.display = 'none';
            }, 300);
        }, 3000);
    }
}

$(document).ready(function() {
    $('#changePasswordForm').submit(function(e) {
        e.preventDefault();
        
        const formData = $(this).serialize();
        const form = $(this);
        const submitBtn = form.find('button[type="submit"]');
        
        // Basic validation
        const newPass = form.find('input[name="new_password"]').val();
        const confirmPass = form.find('input[name="confirm_password"]').val();
        
        if (newPass !== confirmPass) {
            showToast('New passwords do not match', 'warning');
            return;
        }
        
        // Disable form while processing
        submitBtn.prop('disabled', true);
        
        $.ajax({
            url: '<?php echo BASE_URL; ?>/admin/teacher/api/change_password.php',
            method: 'POST',
            data: formData,
            dataType: 'json',
            success: function(response) {
                if (response.success) {
                    $('#changePasswordModal').modal('hide');
                    form[0].reset();
                    showToast('Password changed successfully!', 'success', true);
                } else {
                    showToast(response.error || 'Failed to change password', 'danger');
                }
            },
            error: function() {
                showToast('Failed to change password', 'danger');
            },
            complete: function() {
                submitBtn.prop('disabled', false);
            }
        });
    });
});
</script>

<script>
// Add this after your existing JavaScript
$('#addStudentForm').submit(function(e) {
    e.preventDefault();
    const form = $(this);
    const submitButton = form.find('button[type="submit"]');
    const originalBtnText = submitButton.html();
    submitButton.prop('disabled', true).html('<i class="fas fa-spinner fa-spin me-1"></i> Adding...');

    $.ajax({
        url: `${BASE_URL}/admin/api/add_student.php`,
        method: 'POST',
        data: form.serialize(),
        success: function(response) {
            if (response.success) {
                $('#addStudentModal').modal('hide');
                showToast('Student added successfully!', 'success', false);
                setTimeout(function() {
                    window.location.reload();
                }, 2000);
            } else {
                showToast(response.error || 'Failed to add student', 'danger');
            }
            submitButton.prop('disabled', false).html(originalBtnText);
        },
        error: function(xhr, status, error) {
            console.error('AJAX Error:', error);
            showToast('Failed to add student: ' + error, 'danger');
            submitButton.prop('disabled', false).html(originalBtnText);
        }
    });
});

// Add this function if not already present
function showAlert(message, type = 'success') {
    const alert = $('<div class="alert alert-' + type + ' alert-dismissible fade show" role="alert">')
        .text(message)
        .append('<button type="button" class="btn-close" data-bs-dismiss="alert"></button>');
    
    $('.row:first').after(alert);
    
    setTimeout(() => {
        alert.alert('close');
    }, 3000);
}
</script> 

<!-- Required scripts -->
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
<script src='https://cdn.jsdelivr.net/npm/fullcalendar@6.1.15/index.global.min.js'></script>

<!-- Add this JavaScript -->
<script>
document.addEventListener('DOMContentLoaded', function() {
    const calendarEl = document.getElementById('calendar');
    const calendar = new FullCalendar.Calendar(calendarEl, {
        initialView: 'dayGridMonth',
        height: 'auto',
        selectable: true,
        headerToolbar: {
            left: 'prev,next today',
            center: 'title',
            right: 'dayGridMonth,timeGridWeek,timeGridDay'
        },
        events: function(info, successCallback, failureCallback) {
            $.ajax({
                url: '<?php echo BASE_URL; ?>/admin/teacher/api/get_notes.php',
                method: 'POST',
                data: {
                    teacher_id: '<?php echo $_SESSION['teacher_id']; ?>'
                },
                success: function(response) {
                    // Transform the response to match FullCalendar's event format
                    const events = response.map(note => ({
                        id: note.id,
                        title: note.title,
                        start: note.start,
                        end: note.start, // Set end to the same as start for single-day events
                        extendedProps: {
                            content: note.content,
                            updated_at: note.updated_at
                        },
                        display: 'block', // Show as block event
                        backgroundColor: '#4a90e2', // Light blue background
                        borderColor: '#2962ff', // Dark blue border
                        textColor: '#fff' // White text
                    }));
                    successCallback(events);
                },
                error: function() {
                    failureCallback();
                }
            });
        },
        select: function(info) {
            $('#noteDate').val(info.startStr);
            $('#addNoteModal').modal('show');
        },
        eventClick: function(info) {
            const event = info.event;
            const noteId = event.id;
            
            // Fetch the full note details from the API
            $.ajax({
                url: '<?php echo BASE_URL; ?>/admin/teacher/api/get_note.php',
                method: 'POST',
                data: {
                    teacher_id: '<?php echo $_SESSION['teacher_id']; ?>',
                    note_id: noteId
                },
                success: function(response) {
                    if (response.success) {
                        const note = response.note;
                        
                        // Update view mode
                        $('#editNoteId').val(note.note_id);
                        $('#viewNoteTitle').html(note.note_title); // Changed from .val() to .html()
                        $('#viewNoteContent').html(note.note_content);
                        $('#noteCreatedAt').text('Created: ' + note.created_at);
                        $('#noteDateInfo').text('Date: ' + note.note_date);
                        
                        // Update edit mode
                        $('#editNoteTitle').val(note.note_title);
                        $('#editNoteContent').val(note.note_content);
                        
                        // Show modal
                        $('#viewNoteModal').modal('show');
                        
                        // Set up edit mode toggle
                        $('#editNoteBtn').click(function() {
                            $('#viewNoteTitle').hide();
                            $('#viewNoteContent').hide();
                            $('#editNoteTitle').show();
                            $('#editNoteContent').show();
                            $('#updateNoteBtn').show();
                            $(this).hide();
                        });
                        
                        // Set up back to view mode toggle
                        $('#updateNoteBtn').click(function() {
                            $('#viewNoteTitle').show();
                            $('#viewNoteContent').show();
                            $('#editNoteTitle').hide();
                            $('#editNoteContent').hide();
                            $('#editNoteBtn').show();
                            $(this).hide();
                        });
                    } else {
                        showToast('Failed to load note details', 'danger');
                    }
                },
                error: function() {
                    showToast('Failed to load note details', 'danger');
                }
            });
        },
        eventDidMount: function(info) {
            // Add a tooltip to show note content when hovering over the event
            $(info.el).tooltip({
                title: info.event.title,
                placement: 'top',
                trigger: 'hover'
            });
        }
    });
    calendar.render();

    // Add Note Form Handler
    $('#addNoteForm').submit(function(e) {
        e.preventDefault();
        
        $.ajax({
            url: '<?php echo BASE_URL; ?>/admin/teacher/api/add_note.php',
            method: 'POST',
            data: $(this).serialize(),
            success: function(response) {
                if (response.success) {
                    $('#addNoteModal').modal('hide');
                    calendar.refetchEvents();
                    showToast('Note added successfully!', 'success', false);
                    setTimeout(function() {
                        window.location.reload();
                    }, 2000);
                } else {
                    showToast(response.error || 'Failed to add note', 'danger');
                }
            },
            error: function(xhr, status, error) {
                console.error('AJAX Error:', error);
                showToast('Failed to add note: ' + error, 'danger');
            }
        });
    });

    // Update Note Handler
    $('#updateNoteBtn').click(function() {
        const noteId = $('#editNoteId').val();
        const title = $('#editNoteTitle').val();  
        const content = $('#editNoteContent').val();  

        $.ajax({
            url: '<?php echo BASE_URL; ?>/admin/teacher/api/update_note.php',
            method: 'POST',
            data: {
                teacher_id: '<?php echo $_SESSION['teacher_id']; ?>',
                note_id: noteId,
                note_title: title,
                note_content: content
            },
            success: function(response) {
                if (response.success) {
                    $('#viewNoteModal').modal('hide');
                    calendar.refetchEvents();
                    showToast('Note updated successfully!', 'success', false);
                    setTimeout(function() {
                        window.location.reload();
                    }, 2000);
                } else {
                    showToast(response.error || 'Failed to update note', 'danger');
                }
            },
            error: function() {
                showToast('Failed to update note', 'danger');
            }
        });
    });

    // Delete Note Handler
    $('#deleteNoteBtn').click(function() {
        const noteId = $('#editNoteId').val();
        const noteTitle = $('#viewNoteTitle').text();
        
        // Show delete confirmation modal
        $('#deleteNoteModal').modal('show');
        
        // Set up delete confirmation
        $('#confirmDeleteBtn').click(function() {
            $.ajax({
                url: '<?php echo BASE_URL; ?>/admin/teacher/api/delete_note.php',
                method: 'POST',
                data: {
                    teacher_id: '<?php echo $_SESSION['teacher_id']; ?>',
                    note_id: noteId
                },
                success: function(response) {
                    if (response.success) {
                        $('#viewNoteModal').modal('hide');
                        $('#deleteNoteModal').modal('hide');
                        calendar.refetchEvents();
                        showToast('Note deleted successfully!', 'success', false);
                        setTimeout(function() {
                            window.location.reload();
                        }, 2000);
                    } else {
                        showToast(response.error || 'Failed to delete note', 'danger');
                    }
                },
                error: function() {
                    showToast('Failed to delete note', 'danger');
                }
            });
        });
    });
});
</script>

<!-- Student ID Availability Check -->
<script>
const BASE_URL = '<?php echo BASE_URL; ?>';
let checkIdTimeout;
document.addEventListener('DOMContentLoaded', function() {
    const studentIdInput = document.getElementById('studentId');
    const indicator = document.getElementById('idAvailabilityIndicator');
    const message = document.getElementById('idAvailabilityMessage');
    const feedback = document.getElementById('studentIdFeedback');
    const submitButton = document.querySelector('#addStudentModal button[type="submit"]');
    let idAvailable = false;

    if (!studentIdInput || !indicator || !message || !submitButton || !feedback) {
        console.warn('Required elements for student ID check are missing');
        if (submitButton) submitButton.disabled = true;
        if (feedback) {
            feedback.textContent = 'Student ID validation is not available';
            feedback.style.display = 'block';
        }
        return;
    }

    function setInvalid(msg) {
        studentIdInput.classList.remove('is-valid');
        studentIdInput.classList.add('is-invalid');
        feedback.textContent = msg;
        feedback.style.display = 'block';
        indicator.innerHTML = '<i class="fas fa-times text-danger"></i>';
        submitButton.disabled = true;
        idAvailable = false;
    }

    function setValid(msg) {
        studentIdInput.classList.remove('is-invalid');
        studentIdInput.classList.add('is-valid');
        feedback.textContent = msg || 'Looks good!';
        feedback.style.display = '';
        indicator.innerHTML = '<i class="fas fa-check text-success"></i>';
        submitButton.disabled = false;
        idAvailable = true;
    }

    studentIdInput.addEventListener('input', function() {
        const studentId = this.value.trim();
        clearTimeout(checkIdTimeout);

        idAvailable = false;
        submitButton.disabled = true;
        indicator.innerHTML = '<i class="fas fa-spinner fa-spin"></i>';
        message.textContent = '';
        feedback.textContent = 'Please provide a valid student ID';
        feedback.style.display = 'block';
        studentIdInput.classList.remove('is-invalid', 'is-valid');

        if (studentId.length > 0) {
            checkIdTimeout = setTimeout(() => {
                checkStudentIdAvailability(studentId);
            }, 500);
        } else {
            indicator.innerHTML = '';
            feedback.textContent = 'Please provide a valid student ID';
            feedback.style.display = 'block';
        }
    });

    function checkStudentIdAvailability(studentId) {
        fetch(`${BASE_URL}/admin/api/check_student_id.php?student_id=${encodeURIComponent(studentId)}`)
        .then(response => response.json())
        .then(data => {
            if (data && typeof data.available !== 'undefined') {
                if (data.available) {
                    setValid('Student ID is available');
                } else {
                    setInvalid('This Student ID is already taken');
                }
            } else {
                setInvalid('Error validating Student ID');
            }
        })
        .catch(error => {
            message.textContent = 'Error checking availability';
            message.className = 'text-warning small';
            setInvalid('Error checking ID availability');
        });
    }

    document.getElementById('addStudentForm').addEventListener('submit', function(e) {
        if (!idAvailable) {
            setInvalid('Please check the Student ID is available');
            e.preventDefault();
            e.stopPropagation();
        }
    });
});
</script>