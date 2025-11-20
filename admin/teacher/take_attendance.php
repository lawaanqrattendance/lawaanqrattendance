<?php
require_once '../../includes/config.php';
require_once '../../config/database.php';
require_once '../../includes/functions.php';
require_once '../../includes/auth.php';

if (!isLoggedIn() || getUserRole() !== 'teacher') {
    exit('Unauthorized');
}

$section_id = isset($_GET['section_id']) ? (int)$_GET['section_id'] : 0;
$teacher_id = $_SESSION['teacher_id'];
$current_date = date('Y-m-d');
$day_of_week = date('l');

// Get today's schedules for this section
$schedule_query = "SELECT s.*, sub.subject_name 
                  FROM schedules s
                  JOIN subjects sub ON s.subject_id = sub.subject_id
                  WHERE s.section_id = ? 
                  AND s.teacher_id = ?
                  AND s.day_of_week = ?
                  ORDER BY s.start_time";

$stmt = $conn->prepare($schedule_query);
$stmt->bind_param("iis", $section_id, $teacher_id, $day_of_week);
$stmt->execute();
$schedules = $stmt->get_result();
?>

<div class="modal-header bg-light border-bottom-0 py-4 px-4">
    <h5 class="modal-title fw-semibold text-primary">
        <i class="fas fa-clipboard-check me-2"></i>
        Take Attendance - <?php echo date('l, F j, Y'); ?>
    </h5>
    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
</div>
<div class="modal-body p-4">
    <?php if ($schedules->num_rows === 0): ?>
        <div class="alert alert-info d-flex align-items-center">
            <i class="fas fa-info-circle me-2"></i>
            No schedules found for today.
        </div>
    <?php else: ?>
        <!-- Schedule Selection -->
        <div class="mb-4">
            <div class="input-group">
                <label class="input-group-text bg-light border-end-0" for="scheduleSelect">
                    <i class="fas fa-calendar-alt me-2 text-muted"></i>
                    Schedule
                </label>
                <select class="form-select py-2 ps-3" id="scheduleSelect">
                    <?php while ($schedule = $schedules->fetch_assoc()): ?>
                        <option value="<?php echo $schedule['schedule_id']; ?>">
                            <?php 
                            echo $schedule['subject_name'] . ' (' . 
                                 date('h:i A', strtotime($schedule['start_time'])) . ' - ' .
                                 date('h:i A', strtotime($schedule['end_time'])) . ')';
                            ?>
                        </option>
                    <?php endwhile; ?>
                </select>
            </div>
        </div>

        <!-- Student List -->
        <div id="studentList" class="border rounded-3 p-3 bg-light bg-opacity-10">
            <!-- Will be populated via AJAX -->
            <div class="d-flex justify-content-center py-4">
                <div class="spinner-border text-primary" role="status">
                    <span class="visually-hidden">Loading...</span>
                </div>
            </div>
        </div>
    <?php endif; ?>
</div>
<div class="modal-footer bg-light border-top-0 px-4 pb-4 pt-0">
    <button type="button" class="btn btn-outline-secondary rounded-pill px-4" data-bs-dismiss="modal">Close</button>
    <button type="button" class="btn btn-primary rounded-pill px-4" id="saveAttendance">
        <i class="fas fa-save me-1"></i> Save Attendance
    </button>
</div>

<script>
$(document).ready(function() {
    // Function to show alerts
    function showAlert(message, type = 'success') {
        const alert = $('<div class="alert alert-' + type + ' alert-dismissible fade show" role="alert">')
            .text(message)
            .append('<button type="button" class="btn-close" data-bs-dismiss="alert"></button>');
        
        $('.modal-body').prepend(alert);
        
        // Auto dismiss after 3 seconds
        setTimeout(() => {
            alert.alert('close');
        }, 3000);
    }

    const loadStudentList = () => {
        const scheduleId = $('#scheduleSelect').val();
        $.get('<?php echo BASE_URL; ?>/admin/teacher/get_attendance_list.php', {
            schedule_id: scheduleId,
            section_id: <?php echo $section_id; ?>,
            date: '<?php echo $current_date; ?>'
        }, function(data) {
            $('#studentList').html(`
                <style>
                    .student-attendance {
                        min-width: 120px;
                        border-radius: 20px;
                        padding: 0.25rem 1rem;
                    }
                    .student-attendance option {
                        padding: 0.5rem 1rem;
                    }
                    .student-attendance option[value="Present"] {
                        color: var(--bs-success);
                    }
                    .student-attendance option[value="Late"] {
                        color: var(--bs-warning);
                    }
                    .student-attendance option[value="Absent"] {
                        color: var(--bs-danger);
                    }
                    .student-attendance option[value="Excused"] {
                        color: var(--bs-info);
                    }
                </style>
                <div class="mb-3 d-flex gap-2">
                    <button class="btn btn-sm btn-outline-success bulk-action" data-status="Present">
                        <i class="fas fa-check-circle me-1"></i> Mark All Present
                    </button>
                    <button class="btn btn-sm btn-outline-warning bulk-action" data-status="Late">
                        <i class="fas fa-clock me-1"></i> Mark All Late
                    </button>
                    <button class="btn btn-sm btn-outline-danger bulk-action" data-status="Absent">
                        <i class="fas fa-times-circle me-1"></i> Mark All Absent
                    </button>
                </div>
                ${data}
            `);
            
            // Add bulk action handlers
            $('.bulk-action').click(function() {
                const status = $(this).data('status');
                $('.student-attendance').val(status).trigger('change');
            });
        });
    };

    // Load initial student list
    loadStudentList();

    // Reload when schedule changes
    $('#scheduleSelect').change(loadStudentList);

    // Save attendance
    $('#saveAttendance').click(function() {
        const btn = $(this);
        const originalText = btn.html();
        btn.prop('disabled', true).html('<i class="fas fa-spinner fa-spin me-1"></i> Saving...');

        const scheduleId = $('#scheduleSelect').val();
        const attendance = [];
        
        $('.student-attendance').each(function() {
            attendance.push({
                student_id: $(this).data('student'),
                status: $(this).val()
            });
        });

        $.post('<?php echo BASE_URL; ?>/admin/teacher/save_attendance.php', {
            schedule_id: scheduleId,
            date: '<?php echo $current_date; ?>',
            attendance: JSON.stringify(attendance)
        }, function(response) {
            if (response.success) {
                showAlert('Attendance saved successfully', 'success');
                $('#attendanceModal').modal('hide');
                setTimeout(() => location.reload(), 500);
            } else {
                showAlert('Failed to save attendance', 'danger');
                btn.prop('disabled', false).html(originalText);
            }
        }).fail(function() {
            showAlert('Failed to save attendance', 'danger');
            btn.prop('disabled', false).html(originalText);
        });
    });
});
</script> 