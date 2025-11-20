<?php
include '../../includes/header.php';
requireLogin();

// Allow both admin and teacher roles
if (getUserRole() !== 'teacher' && getUserRole() !== 'admin') {
    header("Location: " . BASE_URL . "/index.php");
    exit();
}

// Get teacher_id based on role
$userRole = getUserRole();
if ($userRole === 'teacher') {
    // Fix for reference_id - get teacher_id from users table
    $stmt = $conn->prepare("SELECT reference_id FROM users WHERE user_id = ?");
    $stmt->bind_param("i", $_SESSION['user_id']);
    $stmt->execute();
    $result = $stmt->get_result();
    $user = $result->fetch_assoc();
    $teacher_id = $user['reference_id'];
} else {
    // For admin, use the selected teacher_id from GET parameter
    $teacher_id = isset($_GET['teacher_id']) ? cleanInput($_GET['teacher_id']) : null;
}

$schedule_id = isset($_GET['schedule_id']) ? cleanInput($_GET['schedule_id']) : null;
$date = isset($_GET['date']) ? cleanInput($_GET['date']) : date('Y-m-d');
// Optional: deep link by student to auto-select latest attendance
$student_lookup_id = isset($_GET['student_id']) ? cleanInput($_GET['student_id']) : null;

// If visiting with ?student_id=... and no schedule explicitly chosen, pick the latest attendance
if ($student_lookup_id && !$schedule_id) {
    if ($userRole === 'teacher') {
        $stmt = $conn->prepare(
            "SELECT a.schedule_id, a.attendance_date 
             FROM attendance a 
             JOIN schedules s ON s.schedule_id = a.schedule_id 
             WHERE a.student_id = ? AND s.teacher_id = ? 
             ORDER BY a.attendance_date DESC, a.attendance_id DESC 
             LIMIT 1"
        );
        // student_id might be alphanumeric; bind as string
        $stmt->bind_param("si", $student_lookup_id, $teacher_id);
    } else {
        $stmt = $conn->prepare(
            "SELECT a.schedule_id, a.attendance_date 
             FROM attendance a 
             WHERE a.student_id = ? 
             ORDER BY a.attendance_date DESC, a.attendance_id DESC 
             LIMIT 1"
        );
        $stmt->bind_param("s", $student_lookup_id);
    }
    $stmt->execute();
    $latest = $stmt->get_result()->fetch_assoc();
    if ($latest) {
        $schedule_id = $latest['schedule_id'];
        $date = $latest['attendance_date'];
    }
}

// Verify this schedule belongs to the teacher (for teacher role)
if ($schedule_id) {
    $stmt = $conn->prepare("SELECT s.*, 
                           sub.subject_name,
                           sec.section_name,
                           sec.grade_level,
                           t.teacher_id
                           FROM schedules s
                           JOIN subjects sub ON s.subject_id = sub.subject_id
                           JOIN sections sec ON s.section_id = sec.section_id
                           JOIN teachers t ON s.teacher_id = t.teacher_id
                           WHERE s.schedule_id = ? " . 
                           ($userRole === 'teacher' ? "AND s.teacher_id = ?" : ""));
    
    if ($userRole === 'teacher') {
        $stmt->bind_param("ii", $schedule_id, $teacher_id);
    } else {
        $stmt->bind_param("i", $schedule_id);
    }
    $stmt->execute();
    $schedule = $stmt->get_result()->fetch_assoc();

    if (!$schedule) {
        header("Location: " . BASE_URL . "/admin/teacher/dashboard.php");
        exit();
    }
}

// Get all schedules for dropdown (filtered by teacher for teacher role)
$schedules_query = "SELECT s.*, 
                    sub.subject_name,
                    sec.section_name,
                    sec.grade_level
                    FROM schedules s
                    JOIN subjects sub ON s.subject_id = sub.subject_id
                    JOIN sections sec ON s.section_id = sec.section_id
                    WHERE 1=1 " .
                    ($userRole === 'teacher' ? "AND s.teacher_id = ? " : "") .
                    "ORDER BY s.day_of_week, s.start_time";

$stmt = $conn->prepare($schedules_query);
if ($userRole === 'teacher') {
    $stmt->bind_param("i", $teacher_id);
}
$stmt->execute();
$all_schedules = $stmt->get_result();

// Get students and their attendance for the selected schedule
if ($schedule_id) {
    $query = "SELECT s.*, 
              COALESCE(a.status, 'Absent') as attendance_status,
              a.attendance_id
              FROM students s
              JOIN sections sec ON s.section_id = sec.section_id
              LEFT JOIN attendance a ON s.student_id = a.student_id
                AND a.schedule_id = ? 
                AND a.attendance_date = ?
              JOIN schedules sch ON a.schedule_id = sch.schedule_id
              WHERE sec.section_id = ? " .
              ($userRole === 'teacher' ? "AND sch.teacher_id = ? " : "") .
              "ORDER BY s.lastname, s.firstname";
    
    if ($userRole === 'teacher') {
        $stmt = $conn->prepare($query);
        $stmt->bind_param("isii", $schedule_id, $date, $schedule['section_id'], $teacher_id);
    } else {
        $stmt = $conn->prepare($query);
        $stmt->bind_param("isi", $schedule_id, $date, $schedule['section_id']);
    }
    $stmt->execute();
    $students = $stmt->get_result();
}
?>

<style>
 /* Visual polish without changing functionality */
 .page-title { display: flex; align-items: center; gap: .5rem; }
 .page-title .subtitle { color: #6c757d; font-size: .95rem; }
 .status-legend .badge { margin-right: .25rem; }
 .status-badge { letter-spacing: .2px; }
 .table thead th { white-space: nowrap; }
 .table tbody td { vertical-align: middle; }
 .btn-group .btn { min-width: 82px; }
 .card { border-radius: .6rem; }
 .card-header { border-bottom: 0; }
</style>

<div class="row mb-4">
    <div class="col-md-12">
        <h2 class="page-title mb-1">Manage Attendance</h2>
        <div class="subtitle">Quickly mark Present, Late, or Absent and see changes instantly.</div>
        <nav aria-label="breadcrumb">
            <ol class="breadcrumb">
                <li class="breadcrumb-item"><a href="dashboard.php">Dashboard</a></li>
                <li class="breadcrumb-item active">Attendance</li>
            </ol>
        </nav>
    </div>
</div>

<!-- Schedule and Date Selection -->
<div class="card mb-4">
    <div class="card-body">
        <form method="GET" class="row g-3">
            <div class="col-md-6">
                <label class="form-label">Select Schedule</label>
                <select name="schedule_id" class="form-select" required onchange="this.form.submit()">
                    <option value="">Choose Schedule...</option>
                    <?php while ($sch = $all_schedules->fetch_assoc()): ?>
                        <option value="<?php echo $sch['schedule_id']; ?>" 
                                <?php echo ($schedule_id == $sch['schedule_id']) ? 'selected' : ''; ?>>
                            <?php echo $sch['day_of_week'] . ' - ' . 
                                     date('h:i A', strtotime($sch['start_time'])) . ' - ' .
                                     $sch['subject_name'] . ' (Grade ' . 
                                     $sch['grade_level'] . ' - ' . $sch['section_name'] . ')'; ?>
                        </option>
                    <?php endwhile; ?>
                </select>
            </div>
            <div class="col-md-4">
                <label class="form-label">Select Date</label>
                <input type="date" name="date" class="form-control" value="<?php echo $date; ?>" 
                       max="<?php echo date('Y-m-d'); ?>" required onchange="this.form.submit()">
            </div>
        </form>
    </div>
</div>

<?php if ($schedule_id && $students && $students->num_rows > 0): ?>
    <!-- Attendance List -->
    <div class="card">
        <div class="card-header d-flex justify-content-between align-items-center">
            <h5 class="mb-0">
                <?php echo $schedule['subject_name'] . ' - Grade ' . 
                          $schedule['grade_level'] . ' ' . $schedule['section_name']; ?>
            </h5>
            <span class="badge bg-<?php echo $schedule['status'] === 'Open' ? 'success' : 'secondary'; ?>">
                <?php echo $schedule['status']; ?>
            </span>
        </div>
        <div class="card-body">
            <form id="attendanceForm">
                <input type="hidden" name="schedule_id" value="<?php echo $schedule_id; ?>">
                <input type="hidden" name="date" value="<?php echo $date; ?>">
                
                <div class="d-flex align-items-center justify-content-between mb-2 status-legend">
                    <div>
                        <span class="badge rounded-pill bg-success">Present</span>
                        <span class="badge rounded-pill bg-warning text-dark">Late</span>
                        <span class="badge rounded-pill bg-danger">Absent</span>
                    </div>
                    <small class="text-muted">Tip: Use the buttons to update status per student.</small>
                </div>

                <div class="table-responsive">
                    <table class="table table-hover table-striped table-bordered align-middle">
                        <thead>
                            <tr>
                                <th>Student ID</th>
                                <th>Name</th>
                                <th>Status</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php while ($student = $students->fetch_assoc()): ?>
                                <tr>
                                    <td><?php echo $student['student_id']; ?></td>
                                    <td><?php echo $student['lastname'] . ', ' . $student['firstname']; ?></td>
                                    <td>
                                        <span class="badge rounded-pill px-3 py-2 fw-semibold status-badge bg-<?php 
                                            echo $student['attendance_status'] === 'Present' ? 'success' :
                                                ($student['attendance_status'] === 'Late' ? 'warning' : 'danger');
                                        ?>">
                                            <?php echo $student['attendance_status']; ?>
                                        </span>
                                    </td>
                                    <td>
                                        <div class="btn-group">
                                            <button type="button" class="btn btn-sm btn-success shadow-sm update-status"
                                                    data-student="<?php echo $student['student_id']; ?>"
                                                    data-status="Present" title="Mark Present" data-bs-toggle="tooltip">
                                                Present
                                            </button>
                                            <button type="button" class="btn btn-sm btn-warning shadow-sm update-status"
                                                    data-student="<?php echo $student['student_id']; ?>"
                                                    data-status="Late" title="Mark Late" data-bs-toggle="tooltip">
                                                Late
                                            </button>
                                            <button type="button" class="btn btn-sm btn-danger shadow-sm update-status"
                                                    data-student="<?php echo $student['student_id']; ?>"
                                                    data-status="Absent" title="Mark Absent" data-bs-toggle="tooltip">
                                                Absent
                                            </button>
                                        </div>
                                    </td>
                                </tr>
                            <?php endwhile; ?>
                        </tbody>
                    </table>
                </div>
            </form>
        </div>
    </div>

    <script>
    $(document).ready(function() {
        // Enable Bootstrap tooltips
        var tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'))
        tooltipTriggerList.map(function (tooltipTriggerEl) { return new bootstrap.Tooltip(tooltipTriggerEl) })

        $('.update-status').click(function() {
            const button = $(this);
            const studentId = button.data('student');
            const status = button.data('status');
            const row = button.closest('tr');
            
            $.ajax({
                url: '<?php echo BASE_URL; ?>/admin/teacher/api/update_attendance.php',
                method: 'POST',
                dataType: 'json',
                data: {
                    student_id: studentId,
                    schedule_id: <?php echo $schedule_id; ?>,
                    attendance_date: '<?php echo $date; ?>',
                    status: status
                },
                success: function(response) {
                    // Ensure JSON or try to parse string
                    let resp = response;
                    if (typeof response === 'string') {
                        try { resp = JSON.parse(response); } catch (e) { resp = {}; }
                    }

                    // Optimistically update UI if success true or undefined but HTTP 200
                    if (resp.success === true || typeof resp.success === 'undefined') {
                        const badge = row.find('.badge');
                        badge.removeClass('bg-success bg-warning bg-danger')
                             .addClass(status === 'Present' ? 'bg-success' : (status === 'Late' ? 'bg-warning' : 'bg-danger'))
                             .text(status);

                        // Soft highlight the row briefly to signal success
                        row.addClass('table-success');
                        setTimeout(() => { row.removeClass('table-success'); }, 800);

                        const alert = $('<div class="alert alert-success alert-dismissible fade show" role="alert">')
                            .text('Attendance updated successfully!')
                            .append('<button type="button" class="btn-close" data-bs-dismiss="alert"></button>');
                        $('.row:first').after(alert);
                        setTimeout(() => { alert.alert('close'); }, 3000);
                    } else {
                        const msg = resp.error || 'Failed to update attendance';
                        const alert = $('<div class="alert alert-danger alert-dismissible fade show" role="alert">')
                            .text(msg)
                            .append('<button type="button" class="btn-close" data-bs-dismiss="alert"></button>');
                        $('.row:first').after(alert);
                    }
                },
                error: function(xhr) {
                    const alert = $('<div class="alert alert-danger alert-dismissible fade show" role="alert">')
                        .text('Failed to update attendance')
                        .append('<button type="button" class="btn-close" data-bs-dismiss="alert"></button>');
                    
                    $('.row:first').after(alert);
                }
            });
        });
    });
    </script>
<?php endif; ?>

<?php include '../../includes/footer.php'; ?>