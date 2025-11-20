<?php
require_once '../../includes/config.php';
require_once '../../config/database.php';
require_once '../../includes/functions.php';
require_once '../../includes/auth.php';

if (!isLoggedIn() || getUserRole() !== 'teacher') {
    exit('Unauthorized');
}

$schedule_id = $_GET['schedule_id'] ?? 0;
$section_id = $_GET['section_id'] ?? 0;
$date = $_GET['date'] ?? date('Y-m-d');

// Get all students and their attendance status
$query = "SELECT s.*, 
          COALESCE(a.status, 'Not Recorded') as attendance_status,
          a.created_at as attendance_time
          FROM students s
          LEFT JOIN attendance a ON s.student_id = a.student_id 
            AND a.schedule_id = ? 
            AND a.attendance_date = ?
          WHERE s.section_id = ?
          ORDER BY s.lastname, s.firstname";

$stmt = $conn->prepare($query);
$stmt->bind_param("isi", $schedule_id, $date, $section_id);
$stmt->execute();
$students = $stmt->get_result();
?>

<div class="table-responsive">
    <table class="table">
        <thead>
            <tr>
                <th>Student ID</th>
                <th>Student Name</th>
                <th>Status</th>
                <th>Time</th>
                <th>Action</th>
            </tr>
        </thead>
        <tbody>
            <?php while ($student = $students->fetch_assoc()): ?>
                <tr>
                    <td><?php echo $student['student_id']; ?></td>
                    <td><?php echo $student['lastname'] . ', ' . $student['firstname']; ?></td>
                    <td>
                        <span class="badge bg-<?php 
                            echo $student['attendance_status'] === 'Present' ? 'success' : 
                                ($student['attendance_status'] === 'Late' ? 'warning' : 
                                ($student['attendance_status'] === 'Absent' ? 'danger' : 'secondary')); 
                        ?>">
                            <?php echo $student['attendance_status']; ?>
                        </span>
                    </td>
                    <td>
                        <?php 
                        echo $student['attendance_time'] ? 
                            date('h:i A', strtotime($student['attendance_time'])) : 
                            '-'; 
                        ?>
                    </td>
                    <td>
                        <select class="form-select form-select-sm student-attendance" 
                                data-student="<?php echo $student['student_id']; ?>">
                            <option value="Not Recorded" <?php echo $student['attendance_status'] === 'Not Recorded' ? 'selected' : ''; ?>>
                                Not Recorded
                            </option>
                            <option value="Present" <?php echo $student['attendance_status'] === 'Present' ? 'selected' : ''; ?>>
                                Present
                            </option>
                            <option value="Late" <?php echo $student['attendance_status'] === 'Late' ? 'selected' : ''; ?>>
                                Late
                            </option>
                            <option value="Absent" <?php echo $student['attendance_status'] === 'Absent' ? 'selected' : ''; ?>>
                                Absent
                            </option>
                        </select>
                    </td>
                </tr>
            <?php endwhile; ?>
        </tbody>
    </table>
</div> 