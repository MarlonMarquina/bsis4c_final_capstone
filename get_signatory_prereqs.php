<?php
session_start();
if (!isset($_SESSION['role']) || $_SESSION['role'] != 'signatory') { echo json_encode([]); exit(); }
include 'conn.php';

$signatory = $_SESSION['username'];
$sigStmt = $conn->prepare("SELECT id, signatory_type, department FROM users WHERE username = ? AND role = 'signatory' LIMIT 1");
$sigStmt->bind_param("s", $signatory);
$sigStmt->execute();
$sigData = $sigStmt->get_result()->fetch_assoc();
$sigStmt->close();

$sig_id = $sigData['id'] ?? 0;
$sig_type_display = $sigData['signatory_type'] ?? '';
$sig_type = strtolower($sig_type_display);
$sig_dept = trim($sigData['department'] ?? '');

// Build course restriction based on signatory type
$is_program_head = strpos($sig_type, 'program head') !== false;
$is_restricted = (strpos($sig_type, 'adviser') !== false || strpos($sig_type, 'program head') !== false || strpos($sig_type, 'department head') !== false);

$allowed_courses = [];
if ($is_program_head && !empty($sig_dept)) {
    $allowed_courses = array_map('trim', explode(',', $sig_dept));
} elseif ($is_restricted && !empty($sig_dept)) {
    $allowed_courses = array_map('trim', explode(',', $sig_dept));
}

// Build the query with optional course filter
if (!empty($allowed_courses)) {
    $placeholders = implode(',', array_fill(0, count($allowed_courses), '?'));
    $q = $conn->prepare("
        SELECT DISTINCT c.id AS course_id, c.course_name
        FROM signatory_prerequisites sp
        JOIN courses c ON c.id = sp.course_id
        WHERE sp.signatory_type = ? AND sp.admin_enabled = 1
        AND c.course_name IN ($placeholders)
        ORDER BY c.course_name ASC
    ");
    $types = 's' . str_repeat('s', count($allowed_courses));
    $params = array_merge([$sig_type_display], $allowed_courses);
    $q->bind_param($types, ...$params);
} else {
    // Global signatory — no course restriction
    $q = $conn->prepare("
        SELECT DISTINCT c.id AS course_id, c.course_name
        FROM signatory_prerequisites sp
        JOIN courses c ON c.id = sp.course_id
        WHERE sp.signatory_type = ? AND sp.admin_enabled = 1
        ORDER BY c.course_name ASC
    ");
    $q->bind_param("s", $sig_type_display);
}

$q->execute();
$courses_res = $q->get_result();
$q->close();

$result = [];
while ($course = $courses_res->fetch_assoc()) {
    $rq = $conn->prepare("SELECT id, before_type, signatory_enabled FROM signatory_prerequisites WHERE course_id = ? AND signatory_type = ? AND admin_enabled = 1");
    $rq->bind_param("is", $course['course_id'], $sig_type_display);
    $rq->execute();
    $rules_res = $rq->get_result();
    $rules = [];
    while ($r = $rules_res->fetch_assoc()) $rules[] = $r;
    $rq->close();

    if (!empty($rules)) {
        $result[] = [
            'course_id' => $course['course_id'],
            'course_name' => $course['course_name'],
            'rules' => $rules
        ];
    }
}

header('Content-Type: application/json');
echo json_encode($result);