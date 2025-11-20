<?php
session_start();
require_once '../../config/database.php';
require_once '../../includes/functions.php';
require_once '../../includes/auth.php';

// Check authentication before including header
if (!isLoggedIn() || getUserRole() !== 'teacher') {
    header("Location: ../../index.php");
    exit();
}

include '../../includes/header.php';

$teacher_id = $_SESSION['teacher_id'];

// Get all sections taught by this teacher
$sections_query = "SELECT DISTINCT s.section_id, s.section_name, s.grade_level 
                  FROM sections s
                  JOIN schedules sch ON s.section_id = sch.section_id
                  WHERE sch.teacher_id = ?
                  ORDER BY s.grade_level, s.section_name";

$stmt = $conn->prepare($sections_query);
$stmt->bind_param("i", $teacher_id);
$stmt->execute();
$sections = $stmt->get_result();
?>

<div class="container-fluid py-4">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h2 class="fw-semibold text-primary mb-0">
            <i class="fas fa-chart-bar me-2"></i>Attendance Analytics
        </h2>
        <div class="d-flex gap-2">
            <a href="<?php echo BASE_URL; ?>/admin/teacher/dashboard.php" class="btn btn-outline-secondary rounded-pill px-4">
                <i class="fas fa-arrow-left me-1"></i> Dashboard
            </a>
            <button type="button" class="btn btn-success rounded-pill px-4" id="exportExcel">
                <i class="fas fa-file-excel me-1"></i> Export
            </button>
        </div>
    </div>

    <!-- Filters -->
    <div class="card shadow-sm mb-4">
        <div class="card-header bg-white border-bottom-0 py-3">
            <h5 class="card-title mb-0 fw-semibold text-primary">
                <i class="fas fa-filter me-2"></i>Report Filters
            </h5>
        </div>
        <div class="card-body">
            <form id="reportFilters">
                <div class="row g-3">
                    <div class="col-md-3">
                        <label class="form-label fw-medium mb-2">Section</label>
                        <select class="form-select py-2 ps-3" name="section_id">
                            <option value="">All Sections</option>
                            <?php while ($section = $sections->fetch_assoc()): ?>
                                <option value="<?php echo $section['section_id']; ?>">
                                    Grade <?php echo $section['grade_level'] . ' - ' . $section['section_name']; ?>
                                </option>
                            <?php endwhile; ?>
                        </select>
                    </div>
                    <div class="col-md-3">
                        <label class="form-label fw-medium mb-2">From Date</label>
                        <input type="date" class="form-control py-2 ps-3" name="start_date" value="<?php echo date('Y-m-d', strtotime('-30 days')); ?>">
                    </div>
                    <div class="col-md-3">
                        <label class="form-label fw-medium mb-2">To Date</label>
                        <input type="date" class="form-control py-2 ps-3" name="end_date" value="<?php echo date('Y-m-d'); ?>">
                    </div>
                    <div class="col-md-3">
                        <label class="form-label fw-medium mb-2">Status</label>
                        <select class="form-select py-2 ps-3" name="status">
                            <option value="">All Status</option>
                            <option value="Present">Present</option>
                            <option value="Late">Late</option>
                            <option value="Absent">Absent</option>
                        </select>
                    </div>
                </div>
                <div class="row mt-4">
                    <div class="col-12">
                        <button type="submit" class="btn btn-primary rounded-pill px-4">
                            <i class="fas fa-search me-1"></i> Generate Report
                        </button>
                    </div>
                </div>
            </form>
        </div>
    </div>

    <!-- Summary Stats -->
    <div class="row mb-4">
        <div class="col-md-3">
            <div class="card bg-primary bg-opacity-10 border-primary border-opacity-25 shadow-sm">
                <div class="card-body py-3">
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <h6 class="mb-1 text-primary fw-semibold">Total Students</h6>
                            <h3 class="mb-0" id="totalStudents">0</h3>
                            <span class="badge bg-primary" data-bs-toggle="tooltip" data-bs-placement="top" title="Total number of students in the selected section">?</span>
                        </div>
                        <div class="bg-primary bg-opacity-10 p-3 rounded-circle">
                            <i class="fas fa-users text-primary"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card bg-success bg-opacity-10 border-success border-opacity-25 shadow-sm">
                <div class="card-body py-3">
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <h6 class="mb-1 text-success fw-semibold">Present</h6>
                            <h3 class="mb-0" id="presentCount">0</h3>
                            <span class="badge bg-success" data-bs-toggle="tooltip" data-bs-placement="top" title="Number of students present in the selected date range">?</span>
                        </div>
                        <div class="bg-success bg-opacity-10 p-3 rounded-circle">
                            <i class="fas fa-check-circle text-success"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card bg-warning bg-opacity-10 border-warning border-opacity-25 shadow-sm">
                <div class="card-body py-3">
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <h6 class="mb-1 text-warning fw-semibold">Late</h6>
                            <h3 class="mb-0" id="lateCount">0</h3>
                            <span class="badge bg-warning" data-bs-toggle="tooltip" data-bs-placement="top" title="Number of students late in the selected date range">?</span>
                        </div>
                        <div class="bg-warning bg-opacity-10 p-3 rounded-circle">
                            <i class="fas fa-clock text-warning"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card bg-danger bg-opacity-10 border-danger border-opacity-25 shadow-sm">
                <div class="card-body py-3">
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <h6 class="mb-1 text-danger fw-semibold">Absent</h6>
                            <h3 class="mb-0" id="absentCount">0</h3>
                            <span class="badge bg-danger" data-bs-toggle="tooltip" data-bs-placement="top" title="Number of students absent in the selected date range">?</span>
                        </div>
                        <div class="bg-danger bg-opacity-10 p-3 rounded-circle">
                            <i class="fas fa-times-circle text-danger"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Charts -->
    <div class="row mb-4">
        <div class="col-md-6">
            <div class="card shadow-sm">
                <div class="card-header bg-white border-bottom-0 py-3">
                    <h5 class="card-title mb-0 fw-semibold text-primary">
                        <i class="fas fa-chart-bar me-2"></i>Attendance Chart
                    </h5>
                </div>
                <div class="card-body p-0">
                    <canvas id="attendanceChart"></canvas>
                </div>
            </div>
        </div>
        <div class="col-md-6">
            <div class="card shadow-sm">
                <div class="card-header bg-white border-bottom-0 py-3">
                    <h5 class="card-title mb-0 fw-semibold text-primary">
                        <i class="fas fa-chart-pie me-2"></i>Student Distribution Chart
                    </h5>
                </div>
                <div class="card-body p-0">
                    <canvas id="studentDistributionChart"></canvas>
                </div>
            </div>
        </div>
    </div>

    <!-- Report Results -->
    <div class="card shadow-sm">
        <div class="card-header bg-white border-bottom-0 py-3 d-flex justify-content-between align-items-center">
            <h5 class="card-title mb-0 fw-semibold text-primary">
                <i class="fas fa-table me-2"></i>Attendance Data
            </h5>
            <div>
                <button class="btn btn-outline-secondary btn-sm me-2" onclick="window.print()" data-bs-toggle="tooltip" title="Print Table"><i class="fas fa-print"></i></button>
                <button class="btn btn-outline-success btn-sm" id="downloadTable" data-bs-toggle="tooltip" title="Download as CSV"><i class="fas fa-file-csv"></i></button>
            </div>
        </div>
        <div class="card-body p-0 bg-light bg-opacity-25 rounded-bottom">
            <div id="reportResults" class="table-responsive" style="max-height: 500px;">
                <!-- Will be populated via AJAX -->
                <div class="d-flex flex-column align-items-center justify-content-center py-5">
                    <div class="spinner-border text-primary mb-3" role="status">
                        <span class="visually-hidden">Loading...</span>
                    </div>
                    <div class="fw-semibold text-primary">Loading attendance data...</div>
                </div>
            </div>
        </div>
    </div>
<script>
// CSV Download
$(document).on('click', '#downloadTable', function() {
    let csv = '';
    $('#reportResults table').each(function() {
        $(this).find('tr').each(function() {
            let row = [];
            $(this).find('th,td').each(function() {
                let text = $(this).text().replace(/\s+/g, ' ').trim();
                row.push('"' + text.replace(/"/g, '""') + '"');
            });
            csv += row.join(',') + '\n';
        });
    });
    const blob = new Blob([csv], {type: 'text/csv'});
    const url = window.URL.createObjectURL(blob);
    const a = document.createElement('a');
    a.href = url;
    a.download = 'attendance_report.csv';
    document.body.appendChild(a);
    a.click();
    document.body.removeChild(a);
    window.URL.revokeObjectURL(url);
});
</script>

</div>

<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script>
$(document).ready(function() {
    let attendancePieChart, attendanceBarChart;

    // Function to update summary stats and charts
    function updateSummaryStatsAndCharts(data) {
        const $data = $(data);
        const total = $data.find('tbody tr').length;
        const present = $data.find('.badge.bg-success').length;
        const late = $data.find('.badge.bg-warning').length;
        const absent = $data.find('.badge.bg-danger').length;
        $('#totalStudents').text(total);
        $('#presentCount').text(present);
        $('#lateCount').text(late);
        $('#absentCount').text(absent);
        // Calculate percentages
        const presentPercent = total ? Math.round((present/total)*100) : 0;
        const latePercent = total ? Math.round((late/total)*100) : 0;
        const absentPercent = total ? Math.round((absent/total)*100) : 0;
        $('#presentPercent').text(presentPercent + '%');
        $('#latePercent').text(latePercent + '%');
        $('#absentPercent').text(absentPercent + '%');

        // Pie Chart
        const pieData = {
            labels: ['Present', 'Late', 'Absent'],
            datasets: [{
                data: [present, late, absent],
                backgroundColor: [
                    'rgba(25, 135, 84, 0.8)', // green
                    'rgba(255, 193, 7, 0.8)', // yellow
                    'rgba(220, 53, 69, 0.8)'  // red
                ],
                borderWidth: 1
            }]
        };
        if (attendancePieChart) attendancePieChart.destroy();
        attendancePieChart = new Chart(document.getElementById('studentDistributionChart').getContext('2d'), {
            type: 'doughnut',
            data: pieData,
            options: {
                plugins: {
                    legend: { display: true, position: 'bottom' }
                }
            }
        });

        // Bar Chart (Attendance over time)
        // Collect dates and present/late/absent counts per date
        const dateMap = {};
        $data.find('tbody tr').each(function() {
            const tds = $(this).find('td');
            const date = $(tds[0]).text();
            const status = $(tds[5]).text().trim();
            if (!dateMap[date]) dateMap[date] = {Present:0, Late:0, Absent:0};
            if (status === 'Present' || status === 'Late' || status === 'Absent') {
                dateMap[date][status]++;
            }
        });
        const labels = Object.keys(dateMap).reverse();
        const presentArr = labels.map(date => dateMap[date].Present);
        const lateArr = labels.map(date => dateMap[date].Late);
        const absentArr = labels.map(date => dateMap[date].Absent);
        const barData = {
            labels: labels,
            datasets: [
                {
                    label: 'Present',
                    data: presentArr,
                    backgroundColor: 'rgba(25, 135, 84, 0.7)'
                },
                {
                    label: 'Late',
                    data: lateArr,
                    backgroundColor: 'rgba(255, 193, 7, 0.7)'
                },
                {
                    label: 'Absent',
                    data: absentArr,
                    backgroundColor: 'rgba(220, 53, 69, 0.7)'
                }
            ]
        };
        if (attendanceBarChart) attendanceBarChart.destroy();
        attendanceBarChart = new Chart(document.getElementById('attendanceChart').getContext('2d'), {
            type: 'bar',
            data: barData,
            options: {
                plugins: {
                    legend: { display: true, position: 'bottom' }
                },
                responsive: true,
                scales: {
                    x: { stacked: true },
                    y: { stacked: true, beginAtZero: true }
                }
            }
        });
    }

    // Handle form submission
    $('#reportFilters').submit(function(e) {
        e.preventDefault();
        const btn = $(this).find('button[type="submit"]');
        const originalText = btn.html();
        btn.prop('disabled', true).html('<i class="fas fa-spinner fa-spin me-1"></i> Generating...');

        $.get('<?php echo BASE_URL; ?>/admin/teacher/get_attendance_report.php', $(this).serialize(), function(data) {
            $('#reportResults').html(data);
            updateSummaryStatsAndCharts(data);
            btn.prop('disabled', false).html(originalText);
        }).fail(function() {
            $('#reportResults').html('<div class="alert alert-danger">Error loading report data</div>');
            btn.prop('disabled', false).html(originalText);
        });
    });

    // Export to Excel
    $('#exportExcel').click(function() {
        const formData = $('#reportFilters').serialize() + '&export=excel';
        window.location.href = '<?php echo BASE_URL; ?>/admin/teacher/export_attendance.php?' + formData;
    });

    // Enable Bootstrap tooltips
    var tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'));
    tooltipTriggerList.forEach(function (tooltipTriggerEl) {
        new bootstrap.Tooltip(tooltipTriggerEl);
    });

    // Trigger initial report load
    $('#reportFilters').trigger('submit');
});
</script>

<?php include '../../includes/footer.php'; ?>