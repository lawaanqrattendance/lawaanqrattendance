<?php
require_once '../../config/database.php';
require_once '../../includes/functions.php';

if (!isset($_GET['teacher_id'])) {
    exit('No teacher ID provided');
}

$teacher_id = intval($_GET['teacher_id']);

// Get sections from teacher_sections table instead of schedules
$query = "SELECT section_id 
          FROM teacher_sections 
          WHERE teacher_id = ?";

$stmt = $conn->prepare($query);
$stmt->bind_param("i", $teacher_id);
$stmt->execute();
$result = $stmt->get_result();

$sections = [];
while ($row = $result->fetch_assoc()) {
    $sections[] = $row['section_id'];
}

echo json_encode($sections);