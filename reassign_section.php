<?php
session_start();
include('conn.php');

if (!isset($_SESSION['role']) || $_SESSION['role'] != "admin") {
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit();
}

header('Content-Type: application/json');

$course_name = trim($_POST['course_name'] ?? '');
$year        = trim($_POST['year'] ?? '');

if (!$course_name || !$year) {
    echo json_encode(['success' => false, 'message' => 'Missing course or year.']);
    exit();
}

// Get course_id from course name
$cstmt = $conn->prepare("SELECT id FROM courses WHERE course_name = ? LIMIT 1");
$cstmt->bind_param("s", $course_name);
$cstmt->execute();
$crow = $cstmt->get_result()->fetch_assoc();
$cstmt->close();

if (!$crow) {
    echo json_encode(['success' => false, 'message' => 'Course not found.']);
    exit();
}
$course_id = $crow['id'];

// Get all existing section names for this course+year to find the gap
$existing_stmt = $conn->prepare("SELECT section_name FROM sections WHERE course_id = ? AND year = ? ORDER BY section_name ASC");
$existing_stmt->bind_param("is", $course_id, $year);
$existing_stmt->execute();
$existing_result = $existing_stmt->get_result();
$existing_sections = [];
while ($row = $existing_result->fetch_assoc()) {
    $existing_sections[] = strtoupper(trim($row['section_name']));
}
$existing_stmt->close();

// Find next available letter (fill gaps first, then continue)
$alphabet = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ';
$new_section_name = null;
for ($i = 0; $i < 26; $i++) {
    $letter = $alphabet[$i];
    if (!in_array($letter, $existing_sections)) {
        $new_section_name = $letter;
        break;
    }
}

if (!$new_section_name) {
    echo json_encode(['success' => false, 'message' => 'All 26 sections (A-Z) are already in use.']);
    exit();
}

// Insert new section
$ins = $conn->prepare("INSERT INTO sections (course_id, year, section_name) VALUES (?, ?, ?)");
$ins->bind_param("iss", $course_id, $year, $new_section_name);
if (!$ins->execute()) {
    echo json_encode(['success' => false, 'message' => 'Failed to create section: ' . $ins->error]);
    $ins->close();
    exit();
}
$ins->close();

// Move all students with this course+year whose section no longer exists in sections table
// (i.e., they are orphaned — their section value is not in the sections table for this course+year)
$upd = $conn->prepare("
    UPDATE users 
    SET section = ? 
    WHERE role = 'student' 
      AND course = ? 
      AND year = ?
      AND section NOT IN (
          SELECT section_name FROM sections WHERE course_id = ? AND year = ?
      )
");
$upd->bind_param("sssis", $new_section_name, $course_name, $year, $course_id, $year);
$upd->execute();
$moved_count = $upd->affected_rows;
$upd->close();

echo json_encode([
    'success'       => true,
    'new_section'   => $new_section_name,
    'moved_students'=> $moved_count,
    'message'       => "Section {$new_section_name} created. {$moved_count} student(s) moved automatically."
]);