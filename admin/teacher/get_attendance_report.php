<?php
session_start();
require_once '../../config/database.php';
require_once '../../includes/functions.php';
require_once '../../includes/auth.php';

if (!isLoggedIn() || getUserRole() !== 'teacher') {
    exit('Unauthorized access');
}

$teacher_id = $_SESSION['teacher_id'];
$section_id = isset($_GET['section_id']) ? intval($_GET['section_id']) : 0;
$start_date = isset($_GET['start_date']) ? $_GET['start_date'] : date('Y-m-d', strtotime('-30 days'));
$end_date = isset($_GET['end_date']) ? $_GET['end_date'] : date('Y-m-d');
$status = isset($_GET['status']) ? $_GET['status'] : '';

// Base query
$query = "SELECT 
            a.attendance_date,
            a.status,
            a.created_at,
            s.student_id,
            s.firstname,
            s.lastname,
            sec.section_name,
            sec.grade_level,
            sub.subject_name,
            CONCAT(TIME_FORMAT(sch.start_time, '%h:%i %p'), ' - ', 
                   TIME_FORMAT(sch.end_time, '%h:%i %p')) as schedule_time,
            sch.day_of_week
          FROM attendance a
          JOIN students s ON a.student_id = s.student_id
          JOIN schedules sch ON a.schedule_id = sch.schedule_id
          JOIN sections sec ON s.section_id = sec.section_id
          JOIN subjects sub ON sch.subject_id = sub.subject_id
          WHERE sch.teacher_id = ? 
          AND a.attendance_date BETWEEN ? AND ?
          AND a.status IN ('Present', 'Late', 'Absent')";

$params = [$teacher_id, $start_date, $end_date];
$types = "iss";

if ($section_id) {
    $query .= " AND s.section_id = ?";
    $params[] = $section_id;
    $types .= "i";
}

if ($status) {
    $query .= " AND a.status = ?";
    $params[] = $status;
    $types .= "s";
}

$query .= " ORDER BY a.attendance_date DESC, s.lastname, s.firstname";

$stmt = $conn->prepare($query);
$stmt->bind_param($types, ...$params);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows === 0) {
    echo '<div class="alert alert-info">No attendance records found for the selected criteria.</div>';
    exit;
}

// Calculate statistics
$total_records = $result->num_rows;
$status_counts = [
    'Present' => 0,
    'Late' => 0,
    'Absent' => 0
];

$data = [];
while ($row = $result->fetch_assoc()) {
    // Add validation to ensure status is not empty and is valid
    if (!empty($row['status']) && isset($status_counts[$row['status']])) {
        $status_counts[$row['status']]++;
    }
    $data[] = $row;
}


// Output the list with its own div
echo '<div id="list">';
// Detailed Report Table
echo '<div class="table-responsive bg-white rounded-3 shadow-sm p-2">
    <table class="table table-striped table-hover align-middle">
        <thead class="table-light sticky-top">
            <tr>
                <th>#</th>
                <th>Date</th>
                <th>Student</th>
                <th>Section</th>
                <th>Subject</th>
                <th>Schedule</th>
                <th>Status</th>
                <th>Time Recorded</th>
            </tr>
        </thead>
        <tbody>';
$rowNum = 1;
foreach ($data as $row):
    echo '<tr>';
    // Row number
    echo '<td class="text-muted">' . $rowNum++ . '</td>';
    // Date
    echo '<td>' . date('M d, Y', strtotime($row['attendance_date'])) . '</td>';
    // Student
    echo '<td>' . htmlspecialchars($row['lastname'] . ', ' . $row['firstname']) . '</td>';
    // Section
    echo '<td>Grade ' . $row['grade_level'] . ' - ' . $row['section_name'] . '</td>';
    // Subject
    echo '<td>' . htmlspecialchars($row['subject_name']) . '</td>';
    // Schedule
    echo '<td><span data-bs-toggle="tooltip" title="' . htmlspecialchars($row['day_of_week'] . ' ' . $row['schedule_time']) . '"><i class="fas fa-calendar-alt text-primary me-1"></i>' . $row['day_of_week'] . ' ' . $row['schedule_time'] . '</span></td>';
    // Status with icon and tooltip
    $statusIcon = $row['status'] === 'Present' ? 'fa-check-circle text-success' : ($row['status'] === 'Late' ? 'fa-clock text-warning' : 'fa-times-circle text-danger');
    $statusTooltip = $row['status'];
    echo '<td>';
    echo '<span class="p-2 badge bg-' . ($row['status'] === 'Present' ? 'success' : ($row['status'] === 'Late' ? 'warning' : 'danger')) . '" data-bs-toggle="tooltip" title="' . $statusTooltip . '">';
    echo '<i class="fas ' . $statusIcon . ' me-1" style="color: white !important;"></i>';
    echo $row['status'];
    echo '</span>';
    echo '</td>';
    // Time Recorded
    echo '<td>' . date('h:i A', strtotime($row['created_at'])) . '</td>';
    echo '</tr>';
endforeach;
if (empty($data)) {
    echo '<tr><td colspan="8" class="text-center text-muted">No records found.</td></tr>';
}
echo '</tbody>';
echo '</table>';
echo '</div>';
echo '</div>'; 