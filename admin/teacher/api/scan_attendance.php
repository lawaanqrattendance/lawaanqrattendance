<?php
session_start();
require_once '../../../config/database.php';
require_once '../../../includes/functions.php';
require_once '../../../includes/auth.php';
require_once '../../../includes/mailer.php';

header('Content-Type: application/json; charset=utf-8');

try {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        http_response_code(405);
        echo json_encode(['success' => false, 'error' => 'Method not allowed']);
        exit();
    }

    if (!isLoggedIn() || getUserRole() !== 'teacher') {
        http_response_code(403);
        echo json_encode(['success' => false, 'error' => 'Unauthorized']);
        exit();
    }

    if (!isset($_SESSION['teacher_id'])) {
        // Fallback: look up teacher_id from users table
        if (!isset($_SESSION['user_id'])) {
            http_response_code(403);
            echo json_encode(['success' => false, 'error' => 'Missing teacher session']);
            exit();
        }
        $stmt = $conn->prepare("SELECT reference_id FROM users WHERE user_id = ? AND role = 'teacher'");
        $stmt->bind_param('i', $_SESSION['user_id']);
        $stmt->execute();
        $res = $stmt->get_result();
        if ($row = $res->fetch_assoc()) {
            $_SESSION['teacher_id'] = (int)$row['reference_id'];
        } else {
            http_response_code(403);
            echo json_encode(['success' => false, 'error' => 'Teacher not found']);
            exit();
        }
    }

    $teacher_id = (int)$_SESSION['teacher_id'];

    $schedule_id = isset($_POST['schedule_id']) ? (int)$_POST['schedule_id'] : 0;
    $qr_code = isset($_POST['qr_code']) ? trim($_POST['qr_code']) : '';

    if ($schedule_id <= 0 || $qr_code === '') {
        http_response_code(400);
        echo json_encode(['success' => false, 'error' => 'Missing parameters']);
        exit();
    }

    // QR code expected to be the student_id (allow digits and dashes)
    if (!preg_match('/^[A-Za-z0-9-]+$/', $qr_code)) {
        http_response_code(400);
        echo json_encode(['success' => false, 'error' => 'Invalid QR code']);
        exit();
    }
    $student_id = $qr_code;

    // Verify schedule ownership and fetch schedule info
    $stmt = $conn->prepare("SELECT schedule_id, section_id, day_of_week, start_time, end_time, status FROM schedules WHERE schedule_id = ? AND teacher_id = ?");
    $stmt->bind_param('ii', $schedule_id, $teacher_id);
    $stmt->execute();
    $sched = $stmt->get_result()->fetch_assoc();
    if (!$sched) {
        http_response_code(403);
        echo json_encode(['success' => false, 'error' => 'Schedule not found or not owned by you']);
        exit();
    }

    if ($sched['status'] !== 'Open') {
        http_response_code(400);
        echo json_encode(['success' => false, 'error' => 'Schedule is not open']);
        exit();
    }

    // Optional time/day enforcement: only allow on matching day and within period
    $today = new DateTime('now');
    $dayName = $today->format('l'); // e.g., Monday
    if (strcasecmp($dayName, $sched['day_of_week']) !== 0) {
        http_response_code(400);
        echo json_encode(['success' => false, 'error' => 'Scan is only allowed on the scheduled day']);
        exit();
    }

    // Build DateTime objects for today with schedule times
    $startDT = DateTime::createFromFormat('Y-m-d H:i:s', $today->format('Y-m-d') . ' ' . $sched['start_time']);
    $endDT = DateTime::createFromFormat('Y-m-d H:i:s', $today->format('Y-m-d') . ' ' . $sched['end_time']);
    $nowDT = new DateTime('now');
    if (!$startDT || !$endDT) {
        http_response_code(500);
        echo json_encode(['success' => false, 'error' => 'Invalid schedule time configuration']);
        exit();
    }
    if ($nowDT < $startDT || $nowDT > $endDT) {
        http_response_code(400);
        echo json_encode(['success' => false, 'error' => 'Scan is only allowed during the scheduled time']);
        exit();
    }

    // Verify student belongs to the schedule's section
    $stmt = $conn->prepare("SELECT student_id FROM students WHERE student_id = ? AND section_id = ?");
    $stmt->bind_param('si', $student_id, $sched['section_id']);
    $stmt->execute();
    $stu = $stmt->get_result()->fetch_assoc();
    if (!$stu) {
        http_response_code(404);
        echo json_encode(['success' => false, 'error' => 'Student not found in this section']);
        exit();
    }

    // Fetch student details for email notifications (best-effort)
    $stmt = $conn->prepare("SELECT firstname, lastname, email, COALESCE(guardian_email, '') AS guardian_email FROM students WHERE student_id = ?");
    $stmt->bind_param('s', $student_id);
    $stmt->execute();
    $studentRow = $stmt->get_result()->fetch_assoc();
    $studentName = $studentRow ? trim(($studentRow['firstname'] ?? '') . ' ' . ($studentRow['lastname'] ?? '')) : 'Student';
    $studentEmail = $studentRow['email'] ?? '';
    $guardianEmail = $studentRow['guardian_email'] ?? '';

    // Build a human-friendly schedule/class label (best-effort)
    $classLabel = '';
    $stmt = $conn->prepare("SELECT s.day_of_week, s.start_time, s.end_time, 
                                    subj.subject_name, sec.section_name
                             FROM schedules s
                             LEFT JOIN subjects subj ON subj.subject_id = s.subject_id
                             LEFT JOIN sections sec ON sec.section_id = s.section_id
                             WHERE s.schedule_id = ?");
    $stmt->bind_param('i', $schedule_id);
    $stmt->execute();
    if ($info = $stmt->get_result()->fetch_assoc()) {
        $classLabel = trim((string)($info['subject_name'] ?? ''));
        if ($classLabel === '') { $classLabel = 'Class'; }
        if (!empty($info['section_name'])) { $classLabel .= ' - Section ' . $info['section_name']; }
        $classLabel .= ' (' . $info['day_of_week'] . ' ' . substr($info['start_time'],0,5) . '-' . substr($info['end_time'],0,5) . ')';
    }

    $attendance_date = (new DateTime('now'))->format('Y-m-d');

    // Fetch existing attendance row
    $stmt = $conn->prepare("SELECT attendance_id, in_time, out_time, status FROM attendance WHERE student_id = ? AND schedule_id = ? AND attendance_date = ?");
    $stmt->bind_param('sis', $student_id, $schedule_id, $attendance_date);
    $stmt->execute();
    $existing = $stmt->get_result()->fetch_assoc();

    // Determine Present/Late on first scan (15-min grace)
    $graceMinutes = 15;
    $graceCutoff = (clone $startDT)->modify("+{$graceMinutes} minutes");
    $statusOnIn = ($nowDT <= $graceCutoff) ? 'Present' : 'Late';
    // Minimum time gap to prevent accidental double-scan immediately recording OUT
    $minOutGapSeconds = 30; // adjust as needed

    if (!$existing) {
        // Insert new row with IN time
        $stmt = $conn->prepare("INSERT INTO attendance (student_id, schedule_id, attendance_date, status, in_time) VALUES (?, ?, ?, ?, NOW())");
        $stmt->bind_param('siss', $student_id, $schedule_id, $attendance_date, $statusOnIn);
        if (!$stmt->execute()) {
            http_response_code(500);
            echo json_encode(['success' => false, 'error' => 'Failed to record IN']);
            exit();
        }
        // Email: send to guardian if available, otherwise student (best-effort, non-blocking)
        $nowStr = (new DateTime('now'))->format('Y-m-d H:i:s');
        $notifyEmail = !empty($guardianEmail) ? $guardianEmail : $studentEmail;
        if (!empty($notifyEmail)) {
            @sendEmail(
                $notifyEmail,
                'Attendance IN recorded',
                renderAttendanceEmailBody([
                    'student_name' => $studentName,
                    'action' => 'in',
                    'status' => $statusOnIn,
                    'schedule_label' => $classLabel,
                    'timestamp' => $nowStr,
                ])
            );
        }

        echo json_encode([
            'success' => true,
            'action' => 'in',
            'status' => $statusOnIn,
            'message' => 'IN recorded'
        ]);
        exit();
    }

    // If in_time is null, set it now
    if (empty($existing['in_time'])) {
        $stmt = $conn->prepare("UPDATE attendance SET in_time = NOW(), status = ? WHERE attendance_id = ?");
        $stmt->bind_param('si', $statusOnIn, $existing['attendance_id']);
        if (!$stmt->execute()) {
            http_response_code(500);
            echo json_encode(['success' => false, 'error' => 'Failed to record IN']);
            exit();
        }
        // Email: send to guardian if available, otherwise student (best-effort, non-blocking)
        $nowStr = (new DateTime('now'))->format('Y-m-d H:i:s');
        $notifyEmail = !empty($guardianEmail) ? $guardianEmail : $studentEmail;
        if (!empty($notifyEmail)) {
            @sendEmail(
                $notifyEmail,
                'Attendance IN recorded',
                renderAttendanceEmailBody([
                    'student_name' => $studentName,
                    'action' => 'in',
                    'status' => $statusOnIn,
                    'schedule_label' => $classLabel,
                    'timestamp' => $nowStr,
                ])
            );
        }
        echo json_encode([
            'success' => true,
            'action' => 'in',
            'status' => $statusOnIn,
            'message' => 'IN recorded'
        ]);
        exit();
    }

    // Else if out_time is null, set OUT now (unless it's a duplicate scan too soon after IN)
    if (empty($existing['out_time'])) {
        if (!empty($existing['in_time'])) {
            $inDT = new DateTime($existing['in_time']);
            $diffSeconds = $nowDT->getTimestamp() - $inDT->getTimestamp();
            if ($diffSeconds < $minOutGapSeconds) {
                echo json_encode([
                    'success' => false,
                    'error' => 'Duplicate scan ignored. Please wait a moment and try again.',
                    'action' => 'none'
                ]);
                exit();
            }
        }
        $stmt = $conn->prepare("UPDATE attendance SET out_time = NOW() WHERE attendance_id = ?");
        $stmt->bind_param('i', $existing['attendance_id']);
        if (!$stmt->execute()) {
            http_response_code(500);
            echo json_encode(['success' => false, 'error' => 'Failed to record OUT']);
            exit();
        }
        // Email: send to guardian if available, otherwise student (best-effort, non-blocking)
        $nowStr = (new DateTime('now'))->format('Y-m-d H:i:s');
        $notifyEmail = !empty($guardianEmail) ? $guardianEmail : $studentEmail;
        if (!empty($notifyEmail)) {
            @sendEmail(
                $notifyEmail,
                'Attendance OUT recorded',
                renderAttendanceEmailBody([
                    'student_name' => $studentName,
                    'action' => 'out',
                    'status' => $existing['status'],
                    'schedule_label' => $classLabel,
                    'timestamp' => $nowStr,
                ])
            );
        }

        echo json_encode([
            'success' => true,
            'action' => 'out',
            'status' => $existing['status'],
            'message' => 'OUT recorded'
        ]);
        exit();
    }

    // Both IN and OUT already recorded
    echo json_encode([
        'success' => false,
        'error' => 'Attendance already completed for this student',
        'action' => 'none'
    ]);
    exit();

} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'Server error', 'details' => $e->getMessage()]);
    exit();
}
