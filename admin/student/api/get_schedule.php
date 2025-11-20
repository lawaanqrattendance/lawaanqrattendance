<?php
require_once '../../../config/database.php';
require_once '../../../includes/functions.php';
require_once '../../../includes/auth.php';

// Check if user is logged in and is a student
session_start();
if (!isLoggedIn() || getUserRole() !== 'student') {
    http_response_code(403);
    echo json_encode(['error' => 'Unauthorized']);
    exit();
}

$student_id = $_SESSION['student_id'];
$current_date = date('Y-m-d');
$day_of_week = date('l');

// Get student's schedule for today
$query = "SELECT s.*, 
          sub.subject_name,
          CONCAT(t.lastname, ', ', t.firstname) as teacher_name,
          a.status as attendance_status,
          a.created_at as attendance_time
          FROM schedules s
          JOIN subjects sub ON s.subject_id = sub.subject_id
          JOIN teachers t ON s.teacher_id = t.teacher_id
          LEFT JOIN attendance a ON s.schedule_id = a.schedule_id 
            AND a.student_id = ? 
            AND a.attendance_date = ?
          WHERE s.section_id = (SELECT section_id FROM students WHERE student_id = ?)
          AND s.day_of_week = ?
          ORDER BY s.start_time";

$stmt = $conn->prepare($query);
$stmt->bind_param("ssss", $student_id, $current_date, $student_id, $day_of_week);
$stmt->execute();
$schedules = $stmt->get_result();

ob_start();
while ($schedule = $schedules->fetch_assoc()): 
    $start_time = strtotime($schedule['start_time']);
    $end_time = strtotime($schedule['end_time']);
    $current_time = strtotime(date('H:i:s'));
    $is_current = ($current_time >= $start_time && $current_time <= $end_time);
?>
    <div class="card schedule-card <?php echo $is_current ? 'current' : ''; ?>">
        <div class="card-body">
            <div class="d-flex justify-content-between align-items-start">
                <div>
                    <h5 class="card-title mb-1"><?php echo $schedule['subject_name']; ?></h5>
                    <p class="text-muted mb-1">
                        <?php echo date('h:i A', $start_time) . ' - ' . date('h:i A', $end_time); ?>
                    </p>
                    <small class="text-muted"><?php echo $schedule['teacher_name']; ?></small>
                </div>
                <div class="text-end">
                    <span class="attendance-status" data-schedule-id="<?php echo $schedule['schedule_id']; ?>">
                        <?php if ($schedule['attendance_status']): ?>
                            <span class="badge bg-<?php 
                                echo $schedule['attendance_status'] === 'Present' ? 'success' : 
                                    ($schedule['attendance_status'] === 'Late' ? 'warning' : 'danger'); 
                            ?>">
                                <?php echo $schedule['attendance_status']; ?>
                            </span>
                            <br>
                            <small class="text-muted">
                                <?php echo date('h:i A', strtotime($schedule['attendance_time'])); ?>
                            </small>
                        <?php else: ?>
                            <span class="badge bg-secondary">Not Recorded</span>
                        <?php endif; ?>
                    </span>
                </div>
            </div>
        </div>
    </div>
<?php endwhile;

$html = ob_get_clean();
echo json_encode(['success' => true, 'html' => $html]);
?> 