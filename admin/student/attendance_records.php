<?php
include '../../includes/header.php';
requireLogin();

if (getUserRole() !== 'student') {
    header("Location: " . BASE_URL . "/index.php");
    exit();
}

$student_id = $_SESSION['student_id'];
$current_page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$records_per_page = 10;
$offset = ($current_page - 1) * $records_per_page;

// Get total records for pagination
$total_query = "SELECT COUNT(*) as total FROM attendance a 
                JOIN schedules s ON a.schedule_id = s.schedule_id
                JOIN subjects sub ON s.subject_id = sub.subject_id
                WHERE a.student_id = ?";
$stmt = $conn->prepare($total_query);
$stmt->bind_param("s", $student_id);
$stmt->execute();
$total_records = $stmt->get_result()->fetch_assoc()['total'];
$total_pages = ceil($total_records / $records_per_page);

// Get attendance records
$query = "SELECT a.attendance_date, a.status, a.in_time, a.out_time, a.created_at as time_recorded,
          sub.subject_name, s.start_time, s.end_time,
          CONCAT(t.lastname, ', ', t.firstname) as teacher_name
          FROM attendance a 
          JOIN schedules s ON a.schedule_id = s.schedule_id
          JOIN subjects sub ON s.subject_id = sub.subject_id
          JOIN teachers t ON s.teacher_id = t.teacher_id
          WHERE a.student_id = ?
          ORDER BY a.attendance_date DESC, s.start_time DESC
          LIMIT ? OFFSET ?";

$stmt = $conn->prepare($query);
$stmt->bind_param("sii", $student_id, $records_per_page, $offset);
$stmt->execute();
$records = $stmt->get_result();
// Build reusable array for both table and mobile cards
$recordsData = [];
while ($row = $records->fetch_assoc()) { $recordsData[] = $row; }

?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Attendance Records</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/all.min.css">
</head>
<body>

<div class="container mt-4">
    <div class="row justify-content-center">
        <div class="col-md-10">
            <div class="d-flex justify-content-between align-items-center mb-4">
                <h4>My Attendance Records</h4>
                <a href="<?php echo BASE_URL; ?>/admin/student/dashboard.php" class="btn btn-primary">
                    <i class="fas fa-arrow-left me-2"></i>Back to Dashboard
                </a>
            </div>

            <div class="card">
                <div class="card-body">
                    <!-- Mobile Cards -->
                    <div class="d-md-none">
                        <?php if (empty($recordsData)): ?>
                            <div class="text-center text-muted">No records found.</div>
                        <?php endif; ?>
                        <?php foreach ($recordsData as $record): ?>
                            <div class="border rounded-3 p-3 mb-3">
                                <div class="d-flex justify-content-between align-items-start">
                                    <div>
                                        <div class="fw-bold"><?php echo htmlspecialchars($record['subject_name']); ?></div>
                                        <div class="text-muted small"><?php echo htmlspecialchars($record['teacher_name']); ?></div>
                                    </div>
                                    <span class="badge bg-<?php 
                                        echo $record['status'] === 'Present' ? 'success' : 
                                            ($record['status'] === 'Late' ? 'warning' : 'danger'); 
                                    ?>"><?php echo $record['status']; ?></span>
                                </div>
                                <div class="mt-2 small">
                                    <div><strong>Date:</strong> <?php echo date('M j, Y', strtotime($record['attendance_date'])); ?></div>
                                    <div><strong>Class Time:</strong> <?php echo date('g:i A', strtotime($record['start_time'])) . ' - ' . date('g:i A', strtotime($record['end_time'])); ?></div>
                                    <?php if (!empty($record['in_time'])): ?>
                                        <div><strong>IN:</strong> <?php echo date('g:i A', strtotime($record['in_time'])); ?></div>
                                    <?php endif; ?>
                                    <?php if (!empty($record['out_time'])): ?>
                                        <div><strong>OUT:</strong> <?php echo date('g:i A', strtotime($record['out_time'])); ?></div>
                                    <?php endif; ?>
                                    <div class="text-muted">Recorded: <?php echo date('g:i A', strtotime($record['time_recorded'])); ?></div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>

                    <!-- Desktop Table -->
                    <div class="table-responsive d-none d-md-block">
                        <table class="table table-hover">
                            <thead>
                                <tr>
                                    <th>Date</th>
                                    <th>Subject</th>
                                    <th>Time</th>
                                    <th>Teacher</th>
                                    <th>Status</th>
                                    <th>IN</th>
                                    <th>OUT</th>
                                    <th>Time Recorded</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($recordsData as $record): ?>
                                    <tr>
                                        <td><?php echo date('M j, Y', strtotime($record['attendance_date'])); ?></td>
                                        <td><?php echo htmlspecialchars($record['subject_name']); ?></td>
                                        <td><?php echo date('g:i A', strtotime($record['start_time'])) . ' - ' . date('g:i A', strtotime($record['end_time'])); ?></td>
                                        <td><?php echo htmlspecialchars($record['teacher_name']); ?></td>
                                        <td>
                                            <span class="badge bg-<?php 
                                                echo $record['status'] === 'Present' ? 'success' : 
                                                    ($record['status'] === 'Late' ? 'warning' : 'danger'); 
                                            ?>"><?php echo $record['status']; ?></span>
                                        </td>
                                        <td><?php echo !empty($record['in_time']) ? date('g:i A', strtotime($record['in_time'])) : '-'; ?></td>
                                        <td><?php echo !empty($record['out_time']) ? date('g:i A', strtotime($record['out_time'])) : '-'; ?></td>
                                        <td><?php echo date('g:i A', strtotime($record['time_recorded'])); ?></td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>

                    <!-- Pagination -->
                    <?php if ($total_pages > 1): ?>
                        <nav aria-label="Page navigation" class="mt-4">
                            <ul class="pagination justify-content-center">
                                <?php for ($i = 1; $i <= $total_pages; $i++): ?>
                                    <li class="page-item <?php echo $i === $current_page ? 'active' : ''; ?>">
                                        <a class="page-link" href="?page=<?php echo $i; ?>"><?php echo $i; ?></a>
                                    </li>
                                <?php endfor; ?>
                            </ul>
                        </nav>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>

<?php include '../../includes/footer.php'; ?> 