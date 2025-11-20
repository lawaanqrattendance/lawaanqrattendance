<?php
include '../../includes/header.php';
requireLogin();

if (getUserRole() !== 'teacher') {
    header("Location: ../index.php");
    exit();
}

$teacher_id = $_SESSION['teacher_id'];
$section_id = isset($_GET['section_id']) ? (int)$_GET['section_id'] : 0;

// Get teacher's sections
$sections_query = "SELECT s.* 
                  FROM sections s 
                  JOIN teacher_sections ts ON s.section_id = ts.section_id 
                  WHERE ts.teacher_id = ?
                  ORDER BY s.grade_level, s.section_name";
$stmt = $conn->prepare($sections_query);
$stmt->bind_param("i", $teacher_id);
$stmt->execute();
$sections = $stmt->get_result();

// If section_id is provided, get students in that section
if ($section_id) {
    $students_query = "SELECT s.*, 
                      COALESCE(
                          (SELECT COUNT(*) FROM attendance a 
                           JOIN schedules sch ON a.schedule_id = sch.schedule_id 
                           WHERE a.student_id = s.student_id 
                           AND sch.teacher_id = ? 
                           AND a.status = 'Present'), 0
                      ) as present_count,
                      COALESCE(
                          (SELECT COUNT(*) FROM attendance a 
                           JOIN schedules sch ON a.schedule_id = sch.schedule_id 
                           WHERE a.student_id = s.student_id 
                           AND sch.teacher_id = ? 
                           AND a.status = 'Late'), 0
                      ) as late_count
                      FROM students s 
                      WHERE s.section_id = ?
                      ORDER BY s.lastname, s.firstname";
    $stmt = $conn->prepare($students_query);
    $stmt->bind_param("iii", $teacher_id, $teacher_id, $section_id);
    $stmt->execute();
    $students = $stmt->get_result();
}
?>

<div class="container-fluid">
    <div class="row g-4 align-items-start">
        <!-- Section List Sidebar -->
        <div class="col-md-3">
            <div class="card shadow-sm">
                <div class="card-header bg-white border-bottom-0 py-3">
                    <h5 class="card-title mb-0 fw-semibold text-primary">
                        <i class="fas fa-layer-group me-2"></i>My Sections
                    </h5>
                </div>
                <div class="list-group list-group-flush rounded-bottom overflow-auto" style="max-height: calc(100vh - 200px);">
                    <?php while ($section = $sections->fetch_assoc()): ?>
                        <a href="?section_id=<?php echo $section['section_id']; ?>" 
                           class="list-group-item list-group-item-action py-3 px-4 <?php echo ($section_id == $section['section_id']) ? 'active bg-primary border-primary' : ''; ?>">
                            <div class="d-flex justify-content-between align-items-center">
                                <span>
                                    <i class="fas fa-graduation-cap me-2 <?php echo ($section_id == $section['section_id']) ? 'text-white' : 'text-muted'; ?>"></i>
                                    <span class="fw-medium">Grade <?php echo $section['grade_level']; ?></span> - <?php echo $section['section_name']; ?>
                                </span>
                                <?php if ($section_id == $section['section_id']): ?>
                                    <i class="fas fa-chevron-right text-white"></i>
                                <?php endif; ?>
                            </div>
                        </a>
                    <?php endwhile; ?>
                </div>
            </div>
        </div>

        <!-- Student List / Attendance Area -->
        <div class="col-md-9">
            <?php if ($section_id): ?>
                <div class="card shadow-sm">
                    <div class="card-header bg-white border-bottom-0 py-3">
                        <h5 class="card-title mb-0 fw-semibold text-primary">Student List</h5>
                        <button class="btn btn-primary rounded-pill px-4 mt-4" onclick="showAttendanceModal()">
                            <i class="fas fa-clipboard-check me-1"></i> Take Attendance
                        </button>
                    </div>
                    <div class="card-body p-4">
                        <div class="table-responsive">
                            <table class="table table-hover align-middle">
                                <thead class="table-light">
                                    <tr>
                                        <th>Student ID</th>
                                        <th>Name</th>
                                        <th class="text-center">Present</th>
                                        <th class="text-center">Late</th>
                                        <th class="text-end">Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php while ($student = $students->fetch_assoc()): ?>
                                        <tr class="border-top">
                                            <td class="fw-medium"><?php echo $student['student_id']; ?></td>
                                            <td><?php echo $student['lastname'] . ', ' . $student['firstname']; ?></td>
                                            <td class="text-center">
                                                <span class="badge bg-success bg-opacity-10 text-success">
                                                    <?php echo $student['present_count']; ?>
                                                </span>
                                            </td>
                                            <td class="text-center">
                                                <span class="badge bg-warning bg-opacity-10 text-warning">
                                                    <?php echo $student['late_count']; ?>
                                                </span>
                                            </td>
                                            <td class="text-end">
                                                <button class="btn btn-sm btn-outline-primary rounded-pill px-3" 
                                                        onclick="viewAttendance('<?php echo $student['student_id']; ?>')">
                                                    <i class="fas fa-eye me-1"></i> View
                                                </button>
                                            </td>
                                        </tr>
                                    <?php endwhile; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            <?php else: ?>
                <div class="alert alert-info">
                    Please select a section from the list.
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<!-- Take Attendance Modal -->
<div class="modal fade" id="attendanceModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <!-- Modal content will be loaded dynamically -->
        </div>
    </div>
</div>

<script>
function showAttendanceModal() {
    $('#attendanceModal').modal('show');
    $('#attendanceModal .modal-content').load(
        '<?php echo BASE_URL; ?>/admin/teacher/take_attendance.php?section_id=<?php echo $section_id; ?>'
    );
}

function viewAttendance(studentId) {
    window.location.href = '<?php echo BASE_URL; ?>/admin/teacher/view_attendance.php?student_id=' + studentId;
}
</script>

<?php include '../../includes/footer.php'; ?> 