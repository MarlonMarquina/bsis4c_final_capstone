<?php
// FILE: delete_student.php
include 'conn.php';
session_start();

// 1. Authorization Check
if (!isset($_SESSION['role']) || $_SESSION['role'] != "admin") {
    header("Location: login.php");
    exit();
}

// 2. Get Student ID
$student_id = $_GET['id'] ?? null;

if (empty($student_id) || !is_numeric($student_id)) {
    $_SESSION['message'] = "❌ Error: Invalid student ID.";
    header("Location: manage_students.php");
    exit();
}

// 3. Delete Student Record (Permanent Deletion)
// Gumamit ng Prepared Statement para sa seguridad
$delete_stmt = $conn->prepare("DELETE FROM users WHERE id = ? AND role = 'student'");

if ($delete_stmt === false) {
    $_SESSION['message'] = "❌ Database Error: Prepare failed. " . $conn->error;
    header("Location: manage_students.php");
    exit();
}

$delete_stmt->bind_param("i", $student_id);

if ($delete_stmt->execute()) {
    // Check kung may naburang row
    if ($delete_stmt->affected_rows > 0) {
        $_SESSION['message'] = "✅ Student (ID: {$student_id}) permanently deleted successfully.";
    } else {
        $_SESSION['message'] = "⚠️ Warning: Student (ID: {$student_id}) not found or already deleted.";
    }
} else {
    // Check for Foreign Key constraint violation
    if ($conn->errno == 1451) {
        $_SESSION['message'] = "❌ Deletion Failed: Cannot delete student ID {$student_id}. Mayroon pa ring nakakonekta na records (e.g., clearance applications) sa ibang tables. Burahin muna ang mga konektadong records.";
    } else {
        $_SESSION['message'] = "❌ Database Error: Execution failed. " . $delete_stmt->error;
    }
}

$delete_stmt->close();
$conn->close();

// 4. Redirect back to the main management page
header("Location: manage_students.php");
exit();
?>