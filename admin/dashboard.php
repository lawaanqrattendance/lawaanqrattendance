<?php
include '../includes/header.php';
requireLogin();

// Check if user is admin
if (getUserRole() !== 'admin') {
    header("Location: ../index.php");
    exit();
}

// Get counts for dashboard
$studentCount = $conn->query("SELECT COUNT(*) as count FROM students")->fetch_assoc()['count'];
$teacherCount = $conn->query("SELECT COUNT(*) as count FROM teachers")->fetch_assoc()['count'];
$sectionCount = $conn->query("SELECT COUNT(*) as count FROM sections")->fetch_assoc()['count'];
$subjectCount = $conn->query("SELECT COUNT(*) as count FROM subjects")->fetch_assoc()['count'];
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

/* Stats Cards */
.stats-card {
    border: none;
    border-radius: 12px;
    overflow: hidden;
    transition: var(--transition);
    margin-bottom: 25px;
    box-shadow: var(--card-shadow);
    border-left: 4px solid;
    height: 100%;
    background: white;
}

.stats-card:hover {
    transform: translateY(-5px);
    box-shadow: var(--card-hover-shadow);
}

.stats-card .card-body {
    padding: 1.5rem;
    position: relative;
    z-index: 1;
}

.stats-card .card-icon {
    position: absolute;
    right: 20px;
    top: 20px;
    font-size: 2.5rem;
    opacity: 0.75;
    z-index: -1;
}

.stats-card .card-title {
    font-size: 0.9rem;
    text-transform: uppercase;
    letter-spacing: 0.5px;
    margin-bottom: 10px;
    font-weight: 600;
    color: #6c757d;
}

.stats-card .card-value {
    font-size: 2rem;
    font-weight: 700;
    margin: 5px 0 15px;
    color: #2c3e50;
}

.stats-card .card-link {
    display: inline-flex;
    align-items: center;
    font-weight: 500;
    text-decoration: none;
    transition: var(--transition);
    padding: 5px 0;
    color: inherit;
    border-bottom: 1px solid transparent;
}

.stats-card .card-link:hover {
    border-bottom-color: currentColor;
}

.stats-card .card-link i {
    margin-left: 5px;
    transition: transform 0.3s ease;
}

.stats-card .card-link:hover i {
    transform: translateX(3px);
}

/* QR Code Section */
.qr-section {
   background: linear-gradient(135deg, #008080, #008080);
    border-radius: 12px;
    color: white;
    padding: 2rem;
    margin-bottom: 30px;
    position: relative;
    overflow: hidden;
    box-shadow: 0 10px 20px rgba(0, 0, 0, 0.2);
}

.qr-section::before {
    content: '';
    position: absolute;
    top: -50%;
    right: -50%;
    width: 100%;
    height: 200%;
    background: radial-gradient(circle, rgba(255,255,255,0.1) 0%, rgba(255,255,255,0) 70%);
    transform: rotate(30deg);
}

.qr-content {
    position: relative;
    z-index: 1;
}

.qr-content h5 {
    font-size: 1.5rem;
    font-weight: 600;
    margin-bottom: 10px;
}

.qr-content p {
    opacity: 0.9;
    margin-bottom: 20px;
    font-size: 1rem;
}

.qr-btn {
    display: inline-flex;
    align-items: center;
    background: rgba(255, 255, 255, 0.2);
    color: white;
    padding: 10px 24px;
    border-radius: 50px;
    text-decoration: none;
    font-weight: 500;
    transition: all 0.3s ease;
    border: 1px solid rgba(255, 255, 255, 0.2);
}

.qr-btn:hover {
    background: white;
    color: var(--primary-color);
    transform: translateY(-2px);
    box-shadow: 0 5px 15px rgba(0, 0, 0, 0.1);
}

.qr-btn i {
    margin-left: 8px;
    transition: transform 0.3s ease;
}

.qr-btn:hover i {
    transform: translateX(3px);
}

/* Activity Feed */
.activity-feed .card {
    border: none;
    border-radius: 12px;
    box-shadow: var(--card-shadow);
    height: 100%;
}

.activity-feed .card-header {
    background-color: white;
    border-bottom: 1px solid rgba(0, 0, 0, 0.05);
    padding: 1.25rem 1.5rem;
    border-radius: 12px 12px 0 0 !important;
}

.activity-feed .card-header h5 {
    margin: 0;
    font-weight: 600;
    color: var(--dark-color);
    font-size: 1.1rem;
}

.activity-item {
    padding: 1.25rem 1.5rem;
    border-bottom: 1px solid rgba(0, 0, 0, 0.05);
    transition: var(--transition);
}

.activity-item:last-child {
    border-bottom: none;
    border-radius: 0 0 12px 12px;
}

.activity-item:hover {
    background-color: #f8f9fa;
}

.activity-date {
    font-size: 0.75rem;
    color: #718096;
    margin-bottom: 5px;
    display: block;
}

.activity-text {
    margin: 0;
    color: #4a5568;
    line-height: 1.5;
}

.activity-text strong {
    color: var(--dark-color);
    font-weight: 600;
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
}

.toast-header {
    background-color: white;
    border-bottom: 1px solid rgba(0, 0, 0, 0.05);
    padding: 0.75rem 1rem;
}

.toast-body {
    padding: 1rem;
}

/* Responsive Adjustments */
@media (max-width: 768px) {
    .stats-card {
        margin-bottom: 15px;
    }
    
    .dashboard-header h2 {
        font-size: 1.5rem;
    }
    
    .qr-section {
        padding: 1.5rem;
    }
    
    .qr-content h5 {
        font-size: 1.25rem;
    }
    
    .fc-toolbar {
        flex-direction: column;
        align-items: flex-start;
        gap: 10px;
    }
    
    .fc-toolbar-chunk {
        display: flex;
        flex-wrap: wrap;
        gap: 5px;
    }
}
</style>

<div class="container-fluid py-4">
    <div class="dashboard-header">
        <h2>Welcome Back, Admin</h2>
        <p>Here's what's happening with your system today</p>
    </div>

    <!-- Stats Cards -->
    <div class="row mb-4">
        <div class="col-xl-3 col-lg-6 col-md-6 mb-4">
            <div class="stats-card" style="border-left-color: var(--primary-color);">
                <div class="card-body">
                    <i class="fas fa-user-graduate card-icon" style="color: var(--primary-color);"></i>
                    <h5 class="card-title">Total Students</h5>
                    <div class="card-value"><?php echo number_format($studentCount); ?></div>
                    <a href="manage_students.php" class="card-link">
                        View Details
                        <i class="fas fa-arrow-right"></i>
                    </a>
                </div>
            </div>
        </div>
        
        <div class="col-xl-3 col-lg-6 col-md-6 mb-4">
            <div class="stats-card" style="border-left-color: var(--success-color);">
                <div class="card-body">
                    <i class="fas fa-chalkboard-teacher card-icon" style="color: var(--success-color);"></i>
                    <h5 class="card-title">Total Teachers</h5>
                    <div class="card-value"><?php echo number_format($teacherCount); ?></div>
                    <a href="manage_teachers.php" class="card-link">
                        View Details
                        <i class="fas fa-arrow-right"></i>
                    </a>
                </div>
            </div>
        </div>
        
        <div class="col-xl-3 col-lg-6 col-md-6 mb-4">
            <div class="stats-card" style="border-left-color: var(--info-color);">
                <div class="card-body">
                    <i class="fas fa-layer-group card-icon" style="color: var(--info-color);"></i>
                    <h5 class="card-title">Total Sections</h5>
                    <div class="card-value"><?php echo number_format($sectionCount); ?></div>
                    <a href="manage_sections.php" class="card-link">
                        View Details
                        <i class="fas fa-arrow-right"></i>
                    </a>
                </div>
            </div>
        </div>
        
        <div class="col-xl-3 col-lg-6 col-md-6 mb-4">
            <div class="stats-card" style="border-left-color: var(--warning-color);">
                <div class="card-body">
                    <i class="fas fa-book card-icon" style="color: var(--warning-color);"></i>
                    <h5 class="card-title">Total Subjects</h5>
                    <div class="card-value"><?php echo number_format($subjectCount); ?></div>
                    <a href="manage_subjects.php" class="card-link">
                        View Details
                        <i class="fas fa-arrow-right"></i>
                    </a>
                </div>
            </div>
        </div>
    </div>

    <!-- QR Code Section -->
    <div class="row mb-4">
        <div class="col-12">
            <div class="qr-section">
                <div class="row align-items-center">
                    <div class="col-md-8">
                        <div class="qr-content">
                            <h5>Generate Student QR Codes</h5>
                            <p>Create and manage QR codes for student attendance tracking. Generate, download, and print QR codes for all your students in one click.</p>
                            <a href="generate_qr.php" class="qr-btn">
                                Generate QR Codes
                                <i class="fas fa-arrow-right"></i>
                            </a>
                        </div>
                    </div>
                    <div class="col-md-4 text-center d-none d-md-block">
                        <i class="fas fa-qrcode" style="font-size: 100px; opacity: 0.2;"></i>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Analytics Section -->
    <div class="row mb-4">
        <div class="col-12">
            <div class="card shadow-sm">
                <div class="card-header bg-white">
                    <h5><i class="fas fa-chart-line me-2"></i>Attendance Analytics</h5>
                </div>
                <div class="card-body">
                    <div class="row">
                        <!-- Status Distribution -->
                        <div class="col-lg-6 mb-4">
                            <div class="card h-100">
                                <div class="card-header bg-light">
                                    <h6 class="mb-0">Status Distribution</h6>
                                </div>
                                <div class="card-body">
                                    <canvas id="statusChart" height="250"></canvas>
                                </div>
                            </div>
                        </div>
                        
                        <!-- Attendance Trend -->
                        <div class="col-lg-6 mb-4">
                            <div class="card h-100">
                                <div class="card-header bg-light">
                                    <h6 class="mb-0">Attendance Trend (Last 30 Days)</h6>
                                </div>
                                <div class="card-body">
                                    <canvas id="trendChart" height="250"></canvas>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Top Sections -->
                    <div class="row mt-4">
                        <div class="col-12">
                            <div class="card">
                                <div class="card-header bg-light">
                                    <h6 class="mb-0">Top Sections by Attendance Rate</h6>
                                </div>
                                <div class="card-body">
                                    <div class="table-responsive">
                                        <table class="table table-hover" id="topSectionsTable">
                                            <thead>
                                                <tr>
                                                    <th>Section</th>
                                                    <th>Total Attendance</th>
                                                    <th>Present</th>
                                                    <th>Attendance Rate</th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                                <!-- Data will be populated by JavaScript -->
                                            </tbody>
                                        </table>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="row">
        <!-- Quick Actions -->
        <div class="col-lg-6 mb-4">
            <div class="quick-actions">
                <div class="card">
                    <div class="card-header">
                        <h5><i class="fas fa-bolt me-2"></i>Quick Actions</h5>
                    </div>
                    <div class="list-group list-group-flush">
                        <a href="manage_schedules.php" class="list-group-item">
                            <i class="fas fa-calendar-alt me-2"></i>
                            Manage Class Schedules
                        </a>
                        <a href="manage_users.php" class="list-group-item">
                            <i class="fas fa-users-cog me-2"></i>
                            Manage User Accounts
                        </a>
                        <a href="manage_subjects.php" class="list-group-item">
                            <i class="fas fa-book me-2"></i>
                            Manage Subjects
                        </a>
                        <a href="manage_sections.php" class="list-group-item">
                            <i class="fas fa-layer-group me-2"></i>
                            Manage Sections
                        </a>
                    </div>
                </div>
            </div>
        </div>
    
        <!-- Recent Activity -->
        <div class="col-lg-6 mb-4">
            <div class="activity-feed">
                <div class="card">
                    <div class="card-header">
                        <h5><i class="fas fa-history me-2"></i>Recent Activity</h5>
                    </div>
                    <div class="list-group list-group-flush">
                        <?php
                        $query = "SELECT a.attendance_date, s.firstname, s.lastname, sub.subject_name 
                                FROM attendance a 
                                JOIN students s ON a.student_id = s.student_id 
                                JOIN schedules sc ON a.schedule_id = sc.schedule_id 
                                JOIN subjects sub ON sc.subject_id = sub.subject_id 
                                ORDER BY a.created_at DESC LIMIT 5";
                        $result = $conn->query($query);
                        
                        if ($result && $result->num_rows > 0) {
                            while ($row = $result->fetch_assoc()) {
                                echo "<div class='activity-item'>";
                                echo "<span class='activity-date'><i class='far fa-calendar-alt me-1'></i>" . date('M d, Y', strtotime($row['attendance_date'])) . "</span>";
                                echo "<p class='activity-text mb-0'><strong>" . htmlspecialchars($row['firstname'] . ' ' . $row['lastname']) . "</strong> attended <strong>" . htmlspecialchars($row['subject_name']) . "</strong></p>";
                                echo "</div>";
                            }
                        } else {
                            echo "<div class='activity-item text-center py-4'>
                                    <i class='fas fa-inbox fa-2x mb-2 text-muted'></i>
                                    <p class='text-muted mb-0'>No recent activity found</p>
                                  </div>";
                        }
                        ?>
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

<?php include '../includes/footer.php'; ?>

<script src='https://cdn.jsdelivr.net/npm/fullcalendar@6.1.15/index.global.min.js'></script>

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
        events: 'api/get_notes.php',
        select: function(info) {
            $('#noteDate').val(info.startStr);
            $('#addNoteModal').modal('show');
        },
        eventClick: function(info) {
            const event = info.event;
            // Set hidden field for edit/delete
            $('#editNoteId').val(event.id);
            // Set modal fields for viewing (as text, not input values)
            $('#viewNoteTitle').text(event.title || '');
            $('#viewNoteContent').text(event.extendedProps.content || '');
            // Show note date in the modal (if you want to display it)
            if (event.start) {
                const dateStr = event.start.toLocaleDateString();
                $('#noteDateInfo').text('Note Date: ' + dateStr);
                $('#noteCreatedAt').text(dateStr);
            } else {
                $('#noteDateInfo').text('');
                $('#noteCreatedAt').text('');
            }
            $('#noteTimestamp').text('Last updated: ' + (event.extendedProps.updated_at || ''));
            $('#viewNoteModal').modal('show');
        }
    });
    calendar.render();

    // Add Note Form Handler
    $('#addNoteForm').submit(function(e) {
        e.preventDefault();
        const formData = $(this).serialize();
        
        $.ajax({
            url: 'api/add_note.php',
            method: 'POST',
            data: formData,
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
            error: function() {
                showToast('Failed to add note', 'danger');
            }
        });
    });

    // Edit Note Handler
    $('#editNoteBtn').click(function() {
        // Populate input fields with current note values
        $('#editNoteTitle').val($('#viewNoteTitle').text()).show();
        $('#editNoteContent').val($('#viewNoteContent').text()).show();
        $('#viewNoteTitle').hide();
        $('#viewNoteContent').hide();
        $('#updateNoteBtn').show();
        $('#editNoteBtn').hide();
    });

    // Update Note Handler
    $('#updateNoteBtn').click(function() {
        const noteId = $('#editNoteId').val();
        const title = $('#editNoteTitle').val();
        const content = $('#editNoteContent').val();

        $.ajax({
            url: 'api/update_note.php',
            method: 'POST',
            data: {
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
        // Revert to view mode after update
        $('#viewNoteTitle').text(title).show();
        $('#viewNoteContent').text(content).show();
        $('#editNoteTitle').hide();
        $('#editNoteContent').hide();
        $('#updateNoteBtn').hide();
        $('#editNoteBtn').show();
    });

    // Delete Note Handler
    $('#deleteNoteBtn').click(function() {
        // Set the note ID in the delete modal
        $('#deleteNoteId').val($('#editNoteId').val());
        $('#deleteNoteModal').modal('show');
    });

    // Confirm Delete in Modal
    $('#confirmDeleteNoteBtn').click(function() {
        const noteId = $('#deleteNoteId').val();
        $.ajax({
            url: 'api/delete_note.php',
            method: 'POST',
            data: { note_id: noteId },
            success: function(response) {
                if (response.success) {
                    $('#deleteNoteModal').modal('hide');
                    $('#viewNoteModal').modal('hide');
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

// Fetch analytics data
fetchAnalyticsData();

function fetchAnalyticsData() {
    $.ajax({
        url: 'api/get_attendance_stats.php',
        method: 'GET',
        data: { timeframe: 'month' },
        success: function(response) {
            renderStatusChart(response.status_counts);
            renderTrendChart(response.attendance_trend);
            renderTopSections(response.top_sections);
        },
        error: function() {
            console.error('Failed to fetch analytics data');
        }
    });
}

function renderStatusChart(statusCounts) {
    const ctx = document.getElementById('statusChart').getContext('2d');
    new Chart(ctx, {
        type: 'doughnut',
        data: {
            labels: ['Present', 'Late', 'Absent'],
            datasets: [{
                data: [statusCounts.Present, statusCounts.Late, statusCounts.Absent],
                backgroundColor: [
                    'rgba(6, 214, 160, 0.7)',
                    'rgba(244, 162, 97, 0.7)',
                    'rgba(239, 71, 111, 0.7)'
                ],
                borderColor: [
                    'rgba(6, 214, 160, 1)',
                    'rgba(244, 162, 97, 1)',
                    'rgba(239, 71, 111, 1)'
                ],
                borderWidth: 1
            }]
        },
        options: {
            responsive: true,
            plugins: {
                legend: { position: 'bottom' },
                tooltip: { callbacks: { label: ctx => `${ctx.label}: ${ctx.raw}` } }
            }
        }
    });
}

function renderTrendChart(trendData) {
    const ctx = document.getElementById('trendChart').getContext('2d');
    new Chart(ctx, {
        type: 'line',
        data: {
            labels: trendData.labels,
            datasets: [{
                label: 'Daily Attendance',
                data: trendData.data,
                fill: true,
                backgroundColor: 'rgba(67, 97, 238, 0.1)',
                borderColor: 'rgba(67, 97, 238, 1)',
                borderWidth: 2,
                pointBackgroundColor: 'rgba(67, 97, 238, 1)',
                tension: 0.3
            }]
        },
        options: {
            responsive: true,
            plugins: {
                legend: { display: false }
            },
            scales: {
                y: { beginAtZero: true }
            }
        }
    });
}

function renderTopSections(sections) {
    const tableBody = $('#topSectionsTable tbody');
    tableBody.empty();
    
    sections.forEach(section => {
        const row = `
            <tr>
                <td>${section.section_name}</td>
                <td>${section.total}</td>
                <td>${section.present_count}</td>
                <td>
                    <div class="progress">
                        <div class="progress-bar" role="progressbar" 
                            style="width: ${section.attendance_rate}%;" 
                            aria-valuenow="${section.attendance_rate}" 
                            aria-valuemin="0" 
                            aria-valuemax="100">
                            ${section.attendance_rate}%
                        </div>
                    </div>
                </td>
            </tr>
        `;
        tableBody.append(row);
    });
}

// Add Chart.js library
const chartScript = document.createElement('script');
chartScript.src = 'https://cdn.jsdelivr.net/npm/chart.js';
document.head.appendChild(chartScript);
</script>

<style>
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

<!-- Delete Note Modal -->
<?php include 'delete_note_modal.html'; ?>

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

<style>
/* Toast Styles */
.toast-container {
    position: fixed;
    top: 20px;
    right: 20px;
    z-index: 1060;
}

.toast {
    background: white;
    border-radius: 8px;
    padding: 16px 24px;
    margin-bottom: 10px;
    box-shadow: 0 4px 12px rgba(0, 0, 0, 0.15);
    display: flex;
    align-items: center;
    transition: opacity 0.3s ease-in-out;
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

.toast-message {
    color: #333;
    font-size: 14px;
}
</style>

<script>
// Toast function
function showToast(message, type = 'success', shouldLogout = false) {
    const toast = document.querySelector(`.toast.${type}`);
    if (!toast) return;
    
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
                window.location.href = '../auth/logout.php';
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
</script> 