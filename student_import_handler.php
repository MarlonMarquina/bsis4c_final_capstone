<?php
session_start();

if (!isset($_SESSION['role']) || $_SESSION['role'] != "admin") {
    header("Location: login.php");
    exit();
}

include('conn.php');
require 'vendor/autoload.php';

use PhpOffice\PhpSpreadsheet\IOFactory;

function set_flash($msg, $type = 'success') {
    $_SESSION['import_message'] = $msg;
    $_SESSION['import_status']  = $type;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_FILES['excel_file'])) {

    $file = $_FILES['excel_file'];

    // --- Basic file checks ---
    if ($file['error'] !== UPLOAD_ERR_OK) {
        set_flash("Error uploading file. Please try again.", "error");
        header("Location: manage_students.php");
        exit();
    }

    if ($file['size'] > 50 * 1024 * 1024) {
        set_flash("File size exceeds 50MB limit.", "error");
        header("Location: manage_students.php");
        exit();
    }

    $allowed = ['xlsx', 'xls', 'csv'];
    $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
    if (!in_array($ext, $allowed)) {
        set_flash("Invalid file type. Only .xlsx, .xls, and .csv are allowed.", "error");
        header("Location: manage_students.php");
        exit();
    }

    try {
      $spreadsheet = IOFactory::load($file['tmp_name']);
$sheet       = $spreadsheet->getSheetByName('Student Data');
if (!$sheet) {
    $sheet = $spreadsheet->getActiveSheet();
}
$rows = $sheet->toArray();

$success_count = 0;
$skipped_count = 0;
$errors        = [];
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Auto-fetch active term once before processing rows
$term_row = $conn->query("SELECT current_semester, current_school_year FROM system_settings WHERE id=1")->fetch_assoc();
$auto_semester    = $term_row['current_semester']    ?? '1st Semester';
$auto_school_year = $term_row['current_school_year'] ?? '2025-2026';

foreach ($rows as $index => $row) {

            // Skip header row
            if ($index === 0) continue;

            // Skip completely empty rows
if (empty(array_filter($row))) continue;


            // Column mapping:
            // A=Username, B=Full Name, C=Email, D=Course, E=Year, F=Section, G=Password
            $username  = trim($row[0] ?? '');
            $full_name = trim($row[1] ?? '');
            $email     = trim($row[2] ?? '');
            $course    = trim($row[3] ?? '');
            $year      = trim($row[4] ?? '');
            $section   = trim($row[5] ?? '');
            $password  = trim($row[6] ?? '') ?? '';

            // Skip rows that look like instruction/sample rows
            // Skip rows that look like instruction/sample rows
if (empty($username) ||
    stripos($username, 'INSTRUCTION') !== false ||
    stripos($username, 'Template')    !== false) {
    continue;
}

            // Validate required fields
            if (empty($full_name) || empty($email) || empty($course)) {
                $skipped_count++;
                $errors[] = "Row " . ($index + 1) . ": Missing required fields (Full Name, Email, or Course). Skipped.";
                continue;
            }

            // Use default password if not provided
            if (empty($password) || $password === 'null') {
    $password = '@Student01';
}

            // Check for duplicate username or email
            $check = $conn->prepare("SELECT username FROM users WHERE username = ? OR email = ?");
            $check->bind_param("ss", $username, $email);
            $check->execute();
            if ($check->get_result()->num_rows > 0) {
                $skipped_count++;
                $errors[] = "Row " . ($index + 1) . ": Username '{$username}' or Email '{$email}' already exists. Skipped.";
                $check->close();
                continue;
            }
            $check->close();

            // Validate that course exists
            $course_check = $conn->prepare("SELECT id FROM courses WHERE course_name = ? LIMIT 1");
            $course_check->bind_param("s", $course);
            $course_check->execute();
            $course_row = $course_check->get_result()->fetch_assoc();
            $course_check->close();

            if (!$course_row) {
                $skipped_count++;
                $errors[] = "Row " . ($index + 1) . ": Course '{$course}' does not exist. Skipped.";
                continue;
            }

            // Validate that section exists for this course and year
            $sec_check = $conn->prepare("SELECT id FROM sections WHERE course_id = ? AND year = ? AND section_name = ? LIMIT 1");
            $sec_check->bind_param("iss", $course_row['id'], $year, $section);
            $sec_check->execute();
            $sec_exists = $sec_check->get_result()->num_rows > 0;
            $sec_check->close();

            if (!$sec_exists) {
                $skipped_count++;
                $errors[] = "Row " . ($index + 1) . ": Section '{$section}' does not exist for Course '{$course}' / Year '{$year}'. Skipped.";
                continue;
            }

            // Fixed fields for students
$role           = 'student';
$signatory_type = '';
$department     = '';

$conn->begin_transaction();

            try {
                $hashed_password = password_hash($password, PASSWORD_DEFAULT);

                $stmt = $conn->prepare("
    INSERT INTO users 
        (username, full_name, email, course, year, section, password, role, signatory_type, department, semester, school_year) 
    VALUES 
        (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
");
$stmt->bind_param(
    "ssssssssssss",
    $username, $full_name, $email,
    $course, $year, $section,
    $hashed_password, $role,
    $signatory_type, $department,
    $auto_semester, $auto_school_year
);

                if (!$stmt->execute()) {
                    throw new Exception("Database error: " . $stmt->error);
                }
                $stmt->close();

                $conn->commit();
                $success_count++;

            } catch (Exception $e) {
    $conn->rollback();
    $skipped_count++;
    $errors[] = "Row " . ($index + 1) . ": " . $e->getMessage() . " | DB Error: " . $conn->error;
}
        }

        // Build result flash message
        $message  = "Import Complete!\n\n";
        $message .= "✅ Successfully imported: {$success_count} student(s)\n";

        if ($skipped_count > 0) {
            $message .= "⚠️ Skipped: {$skipped_count} row(s)\n";
        }

        if (!empty($errors)) {
            $message .= "\nDetails:\n";
            foreach (array_slice($errors, 0, 10) as $error) {
                $message .= "• " . $error . "\n";
            }
            if (count($errors) > 10) {
                $message .= "• ... and " . (count($errors) - 10) . " more error(s)\n";
            }
        }

        $msg_type = ($success_count > 0) ? "success" : "warning";
        set_flash($message, $msg_type);

    } catch (Exception $e) {
        set_flash("Error processing file: " . $e->getMessage(), "error");
    }

    header("Location: manage_students.php");
    exit();

} else {
    set_flash("No file uploaded.", "error");
    header("Location: manage_students.php");
    exit();
}
?>