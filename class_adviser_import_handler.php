<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);
ini_set('log_errors', 1);
ini_set('error_log', __DIR__ . '/import_errors.log');

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
    $_SESSION['import_status'] = $type;
}

/**
 * Parse flexible year-section input formats
 */
function parseYearSection($input) {
    $input = trim($input);
    
    // Format 1: "3rd Year|A" (standard format)
    if (strpos($input, '|') !== false) {
        list($year, $section) = array_map('trim', explode('|', $input, 2));
        return ['year' => $year, 'section' => $section];
    }
    
    // Format 2: "3-A" (with dash)
    if (preg_match('/^(\d+)\s*-\s*([A-Za-z]+)$/', $input, $matches)) {
        $yearNum = $matches[1];
        $section = strtoupper($matches[2]);
        $year = convertToYearLabel($yearNum);
        return ['year' => $year, 'section' => $section];
    }
    
    // Format 3: "3A" (no separator)
    if (preg_match('/^(\d+)([A-Za-z]+)$/', $input, $matches)) {
        $yearNum = $matches[1];
        $section = strtoupper($matches[2]);
        $year = convertToYearLabel($yearNum);
        return ['year' => $year, 'section' => $section];
    }
    
    return null;
}

function convertToYearLabel($num) {
    $yearMap = [
        '1' => '1st Year',
        '2' => '2nd Year',
        '3' => '3rd Year',
        '4' => '4th Year',
        '5' => '5th Year'
    ];
    
    return $yearMap[$num] ?? $num . 'th Year';
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_FILES['excel_file'])) {

    $file = $_FILES['excel_file'];

    // Basic file checks
    if ($file['error'] !== UPLOAD_ERR_OK) {
        set_flash("❌ Error uploading file. Please try again.", 'error');
        header("Location: manage_signatories.php");
        exit();
    }

    if ($file['size'] > 50 * 1024 * 1024) {
        set_flash("❌ File size exceeds 50MB limit.", 'error');
        header("Location: manage_signatories.php");
        exit();
    }

    $allowed = ['xlsx', 'xls', 'csv'];
    $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
    if (!in_array($ext, $allowed)) {
        set_flash("❌ Invalid file type. Only .xlsx, .xls, and .csv are allowed.", 'error');
        header("Location: manage_signatories.php");
        exit();
    }

    try {
        $spreadsheet = IOFactory::load($file['tmp_name']);
        $sheet = $spreadsheet->getSheetByName('Class Adviser Data');
        if (!$sheet) {
            $sheet = $spreadsheet->getActiveSheet();
        }
        $rows = $sheet->toArray();

        $success_count = 0;
        $skipped_count = 0;
        $errors = [];

        // Fetch existing Class Adviser assignments
        $takenAssignments = [];
        $taken_q = $conn->query("SELECT department, section FROM users WHERE role='signatory' AND signatory_type='Class Adviser'");
        while ($r = $taken_q->fetch_assoc()) {
            $department = trim($r['department'] ?? '');
            $combinations = explode(',', trim($r['section'] ?? ''));
            
            foreach ($combinations as $combo) {
                if (strpos($combo, '|') !== false) {
                    $parts = explode('|', $combo);
                    if (count($parts) === 3) {
                        $key = implode('|', $parts);
                        $takenAssignments[$key] = true;
                    }
                }
            }
        }

        foreach ($rows as $index => $row) {
            // Skip header row
            if ($index === 0) continue;

            // Skip completely empty rows
            if (empty(array_filter($row))) continue;

            // Column mapping: Username, Full Name, Email, Signatory Type, Course, Year-Section, Password
            $username = trim($row[0] ?? '');
            $full_name = trim($row[1] ?? '');
            $email = trim($row[2] ?? '');
            $signatory_type = trim($row[3] ?? '');
            $course = trim($row[4] ?? '');
            $yearSectionInput = trim($row[5] ?? '');
            $password = trim($row[6] ?? '');

            // Skip instruction rows or sample data
            if (empty($username) || 
    stripos($username, 'INSTRUCTION') !== false || 
    stripos($username, 'username') !== false) {
    continue;
}

            // Validate required fields
            if (empty($full_name) || empty($email) || empty($signatory_type) || empty($course) || empty($yearSectionInput)) {
                $skipped_count++;
                $missing = [];
                if (empty($full_name)) $missing[] = 'Full Name';
                if (empty($email)) $missing[] = 'Email';
                if (empty($signatory_type)) $missing[] = 'Signatory Type';
                if (empty($course)) $missing[] = 'Course';
                if (empty($yearSectionInput)) $missing[] = 'Year-Section';
                
                $errors[] = "Row " . ($index + 1) . ": Missing required fields: " . implode(', ', $missing) . ". Skipped.";
                continue;
            }

            // Validate Signatory Type (must be "Class Adviser")
            if (strcasecmp($signatory_type, 'Class Adviser') !== 0) {
                $skipped_count++;
                $errors[] = "Row " . ($index + 1) . ": Invalid Signatory Type '{$signatory_type}'. Must be 'Class Adviser'. Skipped.";
                continue;
            }

            // Use default password if not provided
            if (empty($password)) {
                $password = '@Signatory01';
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

            // Parse Year-Section combinations
            $yearSections = array_map('trim', explode(',', $yearSectionInput));
            $formattedYearSections = [];
            $conflicts = [];

            foreach ($yearSections as $ys) {
                $parsed = parseYearSection($ys);
                
                if ($parsed === null) {
                    $skipped_count++;
                    $errors[] = "Row " . ($index + 1) . ": Invalid Year-Section format '{$ys}'. Accepted formats: '3rd Year|A', '3-A', or '3A'. Skipped.";
                    continue 2;
                }
                
                $year = $parsed['year'];
                $section = $parsed['section'];
                
                $formatted = "{$course}|{$year}|{$section}";
                
                if (isset($takenAssignments[$formatted])) {
                    $conflicts[] = "{$year} - {$section}";
                } else {
                    $formattedYearSections[] = $formatted;
                }
            }

            if (!empty($conflicts)) {
                $skipped_count++;
                $errors[] = "Row " . ($index + 1) . ": Year-Section already occupied: " . implode(', ', $conflicts) . ". Skipped.";
                continue;
            }

            if (empty($formattedYearSections)) {
                $skipped_count++;
                $errors[] = "Row " . ($index + 1) . ": No valid year-sections to assign. Skipped.";
                continue;
            }

            // Validate course exists
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

            // Validate each section exists AND has at least one student
            $invalid_sections = [];
            $empty_sections = [];
            foreach ($formattedYearSections as $formatted) {
                $parts = explode('|', $formatted);
                $sec_year    = $parts[1] ?? '';
                $sec_section = $parts[2] ?? '';

                // Check section exists
                $sec_check = $conn->prepare("SELECT id FROM sections WHERE course_id = ? AND year = ? AND section_name = ? LIMIT 1");
                $sec_check->bind_param("iss", $course_row['id'], $sec_year, $sec_section);
                $sec_check->execute();
                $sec_row = $sec_check->get_result()->fetch_assoc();
                $sec_check->close();

                if (!$sec_row) {
                    $invalid_sections[] = "{$sec_year} - {$sec_section}";
                    continue;
                }

                // Check section has at least one student
                $student_check = $conn->prepare("SELECT COUNT(*) as cnt FROM users WHERE role = 'student' AND course = ? AND year = ? AND section = ?");
                $student_check->bind_param("sss", $course, $sec_year, $sec_section);
                $student_check->execute();
                $student_row = $student_check->get_result()->fetch_assoc();
                $student_check->close();

                if ($student_row['cnt'] == 0) {
                    $empty_sections[] = "{$sec_year} - {$sec_section}";
                }
            }

            if (!empty($invalid_sections)) {
                $skipped_count++;
                $errors[] = "Row " . ($index + 1) . ": Section(s) do not exist: " . implode(', ', $invalid_sections) . ". Skipped.";
                continue;
            }

            if (!empty($empty_sections)) {
                $skipped_count++;
                $errors[] = "Row " . ($index + 1) . ": Section(s) have no students: " . implode(', ', $empty_sections) . ". Cannot assign adviser to empty section. Skipped.";
                continue;
            }

            $conn->begin_transaction();
            try {
                $hashed_password = password_hash($password, PASSWORD_DEFAULT);
                $role = 'signatory';
                $signatory_type = 'Class Adviser';
                $department = $course;
                
                $firstYearSection = explode('|', $formattedYearSections[0]);
                $year = $firstYearSection[1] ?? '';
                
                $section = implode(',', $formattedYearSections);

                $stmt = $conn->prepare("
                    INSERT INTO users 
                        (username, full_name, email, year, section, password, role, signatory_type, department) 
                    VALUES 
                        (?, ?, ?, ?, ?, ?, ?, ?, ?)
                ");
                $stmt->bind_param(
                    "sssssssss",
                    $username, $full_name, $email,
                    $year, $section, $hashed_password,
                    $role, $signatory_type, $department
                );

                if (!$stmt->execute()) {
                    throw new Exception("Database error: " . $stmt->error);
                }
                $stmt->close();

                $conn->commit();
                $success_count++;

                foreach ($formattedYearSections as $formatted) {
                    $takenAssignments[$formatted] = true;
                }

            } catch (Exception $e) {
                $conn->rollback();
                $skipped_count++;
                $errors[] = "Row " . ($index + 1) . ": " . $e->getMessage();
            }
        }

        // Build result message
        $message = "📊 Import Complete!\n\n";
$message .= "✅ Successfully imported: {$success_count} Class Adviser(s)\n";
$message .= "⚠️ Skipped: {$skipped_count} row(s)\n";
$message .= "📋 Total rows processed: " . ($index) . "\n"; // Add this

if (!empty($errors)) {
    $message .= "\nDetails:\n";
    foreach ($errors as $error) {
        $message .= "• " . $error . "\n";
    }

        $msg_type = ($success_count > 0) ? "success" : "warning";
            if (count($errors) > 15) {
                $message .= "• ... and " . (count($errors) - 15) . " more error(s)\n";
            }
        }

        $msg_type = ($success_count > 0) ? "success" : "warning";
        set_flash($message, $msg_type);

    } catch (Exception $e) {
        set_flash("❌ Error processing file: " . $e->getMessage(), 'error');
    }

    header("Location: manage_signatories.php");
    exit();

} else {
    set_flash("❌ No file uploaded.", 'error');
    header("Location: manage_signatories.php");
    exit();
}
?>