<?php
include 'conn.php';
session_start();

if (!isset($_SESSION['role']) || $_SESSION['role'] != "admin") {
    header("Location: login.php");
    exit();
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header("Location: manage_signatories.php");
    exit();
}

$signatory_id = intval($_POST['signatory_id']);
$full_name    = trim($_POST['full_name']);
$username     = trim($_POST['username']);
$email        = trim($_POST['email']);
$password     = trim($_POST['password'] ?? '');

// If signatory_type is missing (disabled select), fetch from DB
$signatory_type = trim($_POST['signatory_type'] ?? '');
if (empty($signatory_type)) {
    $orig = $conn->prepare("SELECT signatory_type FROM users WHERE id=? AND role='signatory'");
    $orig->bind_param("i", $signatory_id);
    $orig->execute();
    $origRow = $orig->get_result()->fetch_assoc();
    $signatory_type = $origRow['signatory_type'] ?? '';
}

$department = '';
$year       = '';
$section    = '';

// Fetch OLD section data before update
$oldStmt = $conn->prepare("SELECT section FROM users WHERE id=? AND role='signatory'");
$oldStmt->bind_param("i", $signatory_id);
$oldStmt->execute();
$oldRow = $oldStmt->get_result()->fetch_assoc();
$old_sections_raw = $oldRow['section'] ?? '';

// Handle Program Head
if ($signatory_type === 'Program Head') {
    if (isset($_POST['departments']) && is_array($_POST['departments'])) {
        $department = implode(',', array_map('trim', $_POST['departments']));
    }

    // --- PROGRAM HEAD REQUIREMENT CLEANUP ---
    // Get old departments
    $oldDeptStmt = $conn->prepare("SELECT department FROM users WHERE id=? AND role='signatory'");
    $oldDeptStmt->bind_param("i", $signatory_id);
    $oldDeptStmt->execute();
    $oldDeptRow = $oldDeptStmt->get_result()->fetch_assoc();
    $oldDeptStmt->close();

    $oldDepts = array_filter(array_map('trim', explode(',', $oldDeptRow['department'] ?? '')));
    $newDepts = array_filter(array_map('trim', explode(',', $department)));
    $removedDepts = array_diff($oldDepts, $newDepts);

    foreach ($removedDepts as $removedCourse) {
        // Look up course_id from courses table
        $courseStmt = $conn->prepare("SELECT id FROM courses WHERE course_name = ? LIMIT 1");
        $courseStmt->bind_param("s", $removedCourse);
        $courseStmt->execute();
        $courseRow = $courseStmt->get_result()->fetch_assoc();
        $courseStmt->close();

        if ($courseRow) {
            $removedCourseId = $courseRow['id'];
            // Delete all requirements this signatory set for that course
            $delReq = $conn->prepare("
                DELETE FROM course_requirements 
                WHERE signatory_id = ? 
                AND course_id = ?
            ");
            $delReq->bind_param("ii", $signatory_id, $removedCourseId);
            $delReq->execute();
            $delReq->close();
        }
    }
}

// Handle Class Adviser
elseif ($signatory_type === 'Class Adviser') {
    if (isset($_POST['adviser_courses']) && is_array($_POST['adviser_courses'])) {
        $department = implode(',', array_map('trim', $_POST['adviser_courses']));
    }

    if (isset($_POST['year_sections']) && is_array($_POST['year_sections'])) {
        $yearSections = array_map('trim', $_POST['year_sections']);

        // Set year column from first combo
        if (!empty($yearSections[0]) && strpos($yearSections[0], '|') !== false) {
            $parts = explode('|', $yearSections[0]);
            if (count($parts) === 3) {
                $year = trim($parts[1]);
            }
        }

        $section = implode(',', $yearSections);
    }

    // --- REQUIREMENT CLEANUP ---
    // Parse old combos: format = course|year|section
    $oldCombos = array_filter(array_map('trim', explode(',', $old_sections_raw)));
    $newCombos = array_filter(array_map('trim', explode(',', $section)));

    $removedCombos = array_diff($oldCombos, $newCombos);

    foreach ($removedCombos as $combo) {
        $parts = explode('|', $combo);
        if (count($parts) === 3) {
            list($rmCourse, $rmYear, $rmSection) = array_map('trim', $parts);

            // Delete from course_requirements by signatory_id + course + year_level + sections
            $delReq = $conn->prepare("
                DELETE FROM course_requirements 
                WHERE signatory_id = ? 
                AND year_level = ? 
                AND sections = ?
                AND course_id = (SELECT id FROM courses WHERE course_name = ? LIMIT 1)
            ");
            if ($delReq) {
                $delReq->bind_param("isss", $signatory_id, $rmYear, $rmSection, $rmCourse);
                $delReq->execute();
            }
        }
    }
}

// Build and execute update query
$sql    = "UPDATE users SET username=?, full_name=?, email=?, signatory_type=?, department=?, year=?, section=?";
$types  = "sssssss";
$params = [$username, $full_name, $email, $signatory_type, $department, $year, $section];

if (!empty($password)) {
    $sql   .= ", password=?";
    $types .= "s";
    $params[] = password_hash($password, PASSWORD_DEFAULT);
}

$sql   .= " WHERE id=?";
$types .= "i";
$params[] = $signatory_id;

$update = $conn->prepare($sql);
$update->bind_param($types, ...$params);

if ($update->execute()) {
    $_SESSION['message'] = "✅ Signatory updated successfully!";
} else {
    $_SESSION['message'] = "❌ Error: " . $conn->error;
}

header("Location: manage_signatories.php");
exit();
?>