<?php
session_start();
include('conn.php');

if (!isset($_SESSION['role']) || $_SESSION['role'] != "admin") {
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit();
}

header('Content-Type: application/json');

$id      = filter_var($_POST['id'] ?? null, FILTER_VALIDATE_INT);
$newName = trim($_POST['name'] ?? '');

if (!$id || $newName === '') {
    echo json_encode(['success' => false, 'message' => 'Missing ID or name.']);
    exit();
}

// Step 1: Get the old course name
$stmt = $conn->prepare("SELECT course_name FROM courses WHERE id = ?");
$stmt->bind_param("i", $id);
$stmt->execute();
$row = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$row) {
    echo json_encode(['success' => false, 'message' => 'Course not found.']);
    exit();
}

$oldName = $row['course_name'];

if ($oldName === $newName) {
    echo json_encode(['success' => true, 'message' => 'No change needed.']);
    exit();
}

// Step 2: Rename in courses table
$upd = $conn->prepare("UPDATE courses SET course_name = ? WHERE id = ?");
$upd->bind_param("si", $newName, $id);
if (!$upd->execute()) {
    echo json_encode(['success' => false, 'message' => 'Failed to rename course: ' . $upd->error]);
    $upd->close();
    exit();
}
$upd->close();

// Step 3: Update students — `course` column (plain text)
$updStudents = $conn->prepare("UPDATE users SET course = ? WHERE course = ? AND role = 'student'");
$updStudents->bind_param("ss", $newName, $oldName);
$updStudents->execute();
$studentsAffected = $updStudents->affected_rows;
$updStudents->close();

// Step 4: Update applications — `course` column
$updApps = $conn->prepare("UPDATE applications SET course = ? WHERE course = ?");
$updApps->bind_param("ss", $newName, $oldName);
$updApps->execute();
$appsAffected = $updApps->affected_rows;
$updApps->close();

// Step 5: Update announcements — `target_course` column
$updAnn = $conn->prepare("UPDATE announcements SET target_course = ? WHERE target_course = ?");
$updAnn->bind_param("ss", $newName, $oldName);
$updAnn->execute();
$updAnn->close();

// Step 6: Update Program Heads — `department` column is comma-separated (e.g. "ACT,BSIS")
$phResult = $conn->query("SELECT id, department FROM users WHERE role = 'signatory' AND signatory_type = 'Program Head'");
while ($ph = $phResult->fetch_assoc()) {
    $courses = array_map('trim', explode(',', $ph['department'] ?? ''));
    $updated = false;
    $newCourses = array_map(function($c) use ($oldName, $newName, &$updated) {
        if ($c === $oldName) {
            $updated = true;
            return $newName;
        }
        return $c;
    }, $courses);

    if ($updated) {
        $newDept = implode(',', $newCourses);
        $phUpd = $conn->prepare("UPDATE users SET department = ? WHERE id = ?");
        $phUpd->bind_param("si", $newDept, $ph['id']);
        $phUpd->execute();
        $phUpd->close();
    }
}

// Step 7: Update Class Advisers
// They have TWO fields that store the course:
//   `department` = plain course name (e.g. "BSIS")
//   `section`    = pipe-delimited combos (e.g. "BSIS|1st Year|A,BSIS|4th Year|B")
//   `year`       = some advisers incorrectly store course name here (e.g. "BSIS") — fix too
$caResult = $conn->query("SELECT id, department, section, year FROM users WHERE role = 'signatory' AND signatory_type = 'Class Adviser'");
while ($ca = $caResult->fetch_assoc()) {

    $newDept    = $ca['department'];
    $newSection = $ca['section'];
    $newYear    = $ca['year'];
    $changed    = false;

    // Update `department` if it matches old course name
    if (trim($ca['department']) === $oldName) {
        $newDept = $newName;
        $changed = true;
    }

    // Update `year` column — some advisers mistakenly have course stored here
    if (trim($ca['year']) === $oldName) {
        $newYear = $newName;
        $changed = true;
    }

    // Update `section` combos — format: "CourseName|Year|Section"
    $combos    = array_filter(array_map('trim', explode(',', $ca['section'] ?? '')));
    $newCombos = [];
    foreach ($combos as $combo) {
        $parts = explode('|', $combo);
        if (count($parts) === 3 && trim($parts[0]) === $oldName) {
            $newCombos[] = $newName . '|' . $parts[1] . '|' . $parts[2];
            $changed = true;
        } else {
            $newCombos[] = $combo;
        }
    }

    if ($changed) {
        $newSection = implode(',', $newCombos);
        $caUpd = $conn->prepare("UPDATE users SET department = ?, section = ?, year = ? WHERE id = ?");
        $caUpd->bind_param("sssi", $newDept, $newSection, $newYear, $ca['id']);
        $caUpd->execute();
        $caUpd->close();
    }
}

echo json_encode([
    'success'          => true,
    'message'          => "Course renamed from '{$oldName}' to '{$newName}'. {$studentsAffected} student(s) and {$appsAffected} application(s) updated.",
    'old_name'         => $oldName,
    'new_name'         => $newName,
    'students_updated' => $studentsAffected,
    'apps_updated'     => $appsAffected,
]);