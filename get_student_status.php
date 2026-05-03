<?php
// FILE: get_student_status.php
include 'conn.php';
header('Content-Type: application/json');

$studentId = $_GET['id'] ?? '';

if (empty($studentId)) {
    echo json_encode(['error' => 'Student ID is required']);
    exit;
}

$stmt = $conn->prepare("
    SELECT full_name, username, student_id, course, year, section, 
           final_clearance_status, admin_approved, admin_messaged, admin_message_text
    FROM users 
    WHERE username = ? AND role = 'student'
");
$stmt->bind_param("s", $studentId);
$stmt->execute();
$student = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$student) {
    echo json_encode(['error' => 'Student not found']);
    exit;
}

$clearance_class = 'status-not-requested';
$display_status = 'Not Requested';

if ($student['admin_approved'] == 1) {
    $clearance_class = 'status-approved';
    $display_status = 'Approved';
} elseif ($student['final_clearance_status'] == 'pending') {
    $clearance_class = 'status-under-review';
    $display_status = 'Under Review';
}

$requirements = [];
$course = $student['course'];

$req_stmt = $conn->prepare("
    SELECT u.signatory_type, u.username as sig_user,
           COUNT(cr.requirement_id) as total_reqs
    FROM course_requirements cr
    JOIN users u ON cr.signatory_id = u.id
    WHERE cr.course_id = (SELECT id FROM courses WHERE course_name = ? LIMIT 1)
    AND u.status = 'active'
    AND cr.requirements_configured = 1
    GROUP BY u.id
    ORDER BY u.signatory_type ASC
");
$req_stmt->bind_param("s", $course);
$req_stmt->execute();
$res = $req_stmt->get_result();

while ($row = $res->fetch_assoc()) {
    $check_app = $conn->prepare("
        SELECT COUNT(DISTINCT requirement_id) as approved_count
        FROM applications 
        WHERE username = ? AND signatory = ? AND status = 'Approved'
    ");
    $check_app->bind_param("ss", $studentId, $row['sig_user']);
    $check_app->execute();
    $approved_count = (int)$check_app->get_result()->fetch_assoc()['approved_count'];
    $check_app->close();

    $total = (int)$row['total_reqs'];
    $is_cleared = ($approved_count >= $total && $total > 0);

    $requirements[] = [
        'office'  => $row['signatory_type'],
        'status'  => $is_cleared ? 'cleared' : 'pending',
        'progress' => $approved_count . '/' . $total
    ];
}
$req_stmt->close();
$conn->close();

echo json_encode([
    'student_info' => [
        'full_name'        => $student['full_name'],
        'student_id'       => !empty($student['student_id']) ? $student['student_id'] : $student['username'],
        'username'         => $student['username'],
        'course'           => $student['course'],
        'year'             => $student['year'],
        'section'          => $student['section'],
        'clearance_status' => $display_status,
        'clearance_class'  => $clearance_class,
        'admin_approved'   => $student['admin_approved'],
        'admin_messaged'   => $student['admin_messaged'],
        'admin_message_text' => $student['admin_message_text']
    ],
    'requirements' => $requirements
]);
?>