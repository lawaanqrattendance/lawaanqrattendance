<?php
session_start();
require_once '../../config/database.php';
require_once '../../includes/functions.php';
require_once '../../includes/auth.php';
require_once '../../vendor/autoload.php'; // For PhpSpreadsheet

use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

if (!isLoggedIn() || getUserRole() !== 'teacher') {
    exit('Unauthorized access');
}

$teacher_id = $_SESSION['teacher_id'];
$section_id = isset($_GET['section_id']) ? intval($_GET['section_id']) : 0;
$start_date = isset($_GET['start_date']) ? $_GET['start_date'] : date('Y-m-d', strtotime('-30 days'));
$end_date = isset($_GET['end_date']) ? $_GET['end_date'] : date('Y-m-d');
$status = isset($_GET['status']) ? $_GET['status'] : '';

// Get teacher's name for the report header
$stmt = $conn->prepare("SELECT CONCAT(firstname, ' ', lastname) as teacher_name FROM teachers WHERE teacher_id = ?");
$stmt->bind_param("i", $teacher_id);
$stmt->execute();
$teacher_result = $stmt->get_result();
$teacher_name = $teacher_result->fetch_assoc()['teacher_name'];

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
          AND a.attendance_date BETWEEN ? AND ?";

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

$query .= " ORDER BY sec.grade_level, sec.section_name, a.attendance_date, s.lastname, s.firstname";

$stmt = $conn->prepare($query);
$stmt->bind_param($types, ...$params);
$stmt->execute();
$result = $stmt->get_result();

// Create new Spreadsheet object
$spreadsheet = new Spreadsheet();
$sheet = $spreadsheet->getActiveSheet();

// Set headers
$sheet->setCellValue('A1', 'Attendance Report');
$sheet->setCellValue('A2', 'Teacher: ' . $teacher_name);
$sheet->setCellValue('A3', 'Period: ' . date('M d, Y', strtotime($start_date)) . ' to ' . date('M d, Y', strtotime($end_date)));

// Fetch all data and create nested grouping
$grouped_data = [];
while ($data = $result->fetch_assoc()) {
    $section_key = 'Grade ' . $data['grade_level'] . ' - ' . $data['section_name'];
    $date_key = $data['attendance_date'];
    $subject_key = $data['subject_name'];

    if (!isset($grouped_data[$section_key])) {
        $grouped_data[$section_key] = [];
    }
    if (!isset($grouped_data[$section_key][$date_key])) {
        $grouped_data[$section_key][$date_key] = [];
    }
    if (!isset($grouped_data[$section_key][$date_key][$subject_key])) {
        $grouped_data[$section_key][$date_key][$subject_key] = [];
    }
    
    $grouped_data[$section_key][$date_key][$subject_key][] = $data;
}

// Starting row
$row = 6;

// Column headers template
$headers = ['Student ID', 'Student Name', 'Schedule', 'Status', 'Time Recorded'];

foreach ($grouped_data as $section => $dates) {
    // Add section header
    $sheet->setCellValue('A' . $row, $section);
    $sheet->mergeCells('A' . $row . ':E' . $row);
    $sheet->getStyle('A' . $row)->getFont()->setBold(true);
    $sheet->getStyle('A' . $row)->getFill()
          ->setFillType(\PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID)
          ->getStartColor()->setRGB('CCCCCC');
    $row++;

    foreach ($dates as $date => $subjects) {
        // Add date header
        $sheet->setCellValue('A' . $row, 'Date: ' . date('M d, Y', strtotime($date)));
        $sheet->mergeCells('A' . $row . ':E' . $row);
        $sheet->getStyle('A' . $row)->getFont()->setBold(true);
        $sheet->getStyle('A' . $row)->getFill()
              ->setFillType(\PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID)
              ->getStartColor()->setRGB('E6E6E6');
        $row++;

        foreach ($subjects as $subject => $records) {
            // Add subject header
            $sheet->setCellValue('A' . $row, 'Subject: ' . $subject);
            $sheet->mergeCells('A' . $row . ':E' . $row);
            $sheet->getStyle('A' . $row)->getFont()->setItalic(true);
            $row++;

            // Add column headers
            foreach (range('A', 'E') as $i => $col) {
                $sheet->setCellValue($col . $row, $headers[$i]);
                $sheet->getStyle($col . $row)->getFont()->setBold(true);
                $sheet->getColumnDimension($col)->setAutoSize(true);
            }
            $row++;

            // Format Student ID column as text
            $sheet->getStyle('A' . $row . ':A' . ($row + count($records)))
                  ->getNumberFormat()->setFormatCode('@');

            // Add attendance records
            foreach ($records as $data) {
                $sheet->setCellValueExplicit(
                    'A' . $row, 
                    $data['student_id'],
                    \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_STRING
                );
                $sheet->setCellValue('B' . $row, $data['lastname'] . ', ' . $data['firstname']);
                $sheet->setCellValue('C' . $row, $data['day_of_week'] . ' ' . $data['schedule_time']);
                $sheet->setCellValue('D' . $row, $data['status']);
                $sheet->setCellValue('E' . $row, date('h:i A', strtotime($data['created_at'])));
                $row++;
            }

            // Add spacing after each subject
            $row++;
        }

        // Add spacing after each date
        $row++;
    }

    // Add spacing after each section
    $row += 2;
}

// Clean any output buffers
ob_end_clean();

// Set headers for download
header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
header('Content-Disposition: attachment; filename="attendance_report.xlsx"');
header('Cache-Control: max-age=0');

// Create Excel writer
$writer = new Xlsx($spreadsheet);

// Save to PHP output
ob_start();
$writer->save('php://output');
$xlsData = ob_get_contents();
ob_end_clean();

// Output the file
echo $xlsData;
exit; 