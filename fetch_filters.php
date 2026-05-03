<?php
// FILE: fetch_filters.php
include 'conn.php';

$courses_str = $_GET['courses'] ?? '';
$exclude_id = intval($_GET['exclude_id'] ?? 0);

if (empty($courses_str)) {
    echo json_encode(["years" => [], "sections" => []]);
    exit();
}

// 1. I-map at linisin ang input courses
$courses_arr = array_map('trim', explode(',', $courses_str));
$placeholders = implode(',', array_fill(0, count($courses_arr), '?'));
$types = str_repeat('s', count($courses_arr));

$response = ["years" => [], "sections" => []];

// 2. Kunin ang UNIQUE Years na may active students sa napiling courses
$stmt = $conn->prepare("SELECT DISTINCT year FROM users WHERE role = 'student' AND course IN ($placeholders) AND year IS NOT NULL AND year != '' ORDER BY year ASC");
$stmt->bind_param($types, ...$courses_arr);
$stmt->execute();
$res = $stmt->get_result();
while ($row = $res->fetch_assoc()) {
    $response['years'][] = $row['year'];
}

// 3. Kunin ang UNIQUE Sections na may students sa napiling courses
$stmt = $conn->prepare("SELECT DISTINCT section FROM users WHERE role = 'student' AND course IN ($placeholders) AND section IS NOT NULL AND section != '' ORDER BY section ASC");
$stmt->bind_param($types, ...$courses_arr);
$stmt->execute();
$res = $stmt->get_result();

while ($row = $res->fetch_assoc()) {
    $sec_name = trim($row['section']);
    
    // Check kung "Occupied" na ang section na ito ng ibang Class Adviser
    // Gagamit tayo ng FIND_IN_SET dahil ang 'section' field sa 'users' table ay comma-separated string
    $check_adv = $conn->prepare("SELECT id FROM users WHERE role = 'signatory' AND signatory_type = 'Class Adviser' AND FIND_IN_SET(?, section) AND id != ?");
    $check_adv->bind_param("si", $sec_name, $exclude_id);
    $check_adv->execute();
    $is_taken = ($check_adv->get_result()->num_rows > 0);

    $response['sections'][] = [
        "name" => $sec_name,
        "is_taken" => $is_taken
    ];
}

header('Content-Type: application/json');
echo json_encode($response);