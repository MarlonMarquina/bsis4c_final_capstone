<?php

include 'conn.php';
session_start();

// Authorization
if (!isset($_SESSION['role']) || $_SESSION['role'] != "admin") {
    header("Location: login.php");
    exit();
}

$msg = '';
if (isset($_SESSION['message'])) {
    $msg = $_SESSION['message'];
    unset($_SESSION['message']);
}

// --- Dynamic Options ---
$departmentOptions = [];
$dept_query = $conn->query("SELECT DISTINCT course_name FROM courses ORDER BY course_name ASC");
if ($dept_query) {
    while ($row = $dept_query->fetch_assoc()) {
        if (!empty(trim($row['course_name']))) {
            $departmentOptions[] = trim($row['course_name']);
        }
    }
}
if (empty($departmentOptions)) $departmentOptions[] = 'No Courses Found';

// Fetch ACTUAL year/section combinations per course from students
$courseYearSections = [];
$student_query = $conn->query("
    SELECT DISTINCT u.course AS course_name, u.year, u.section 
    FROM users u
    INNER JOIN courses c ON u.course = c.course_name
    WHERE u.role = 'student' 
    AND u.year IS NOT NULL 
    AND u.year != '' 
    AND u.section IS NOT NULL 
    AND u.section != ''
    ORDER BY u.course, u.year, u.section
");

if ($student_query) {
    while ($row = $student_query->fetch_assoc()) {
        $course = trim($row['course_name']);
        $year = trim($row['year']);
        $section = trim($row['section']);
        
        if (!isset($courseYearSections[$course])) {
            $courseYearSections[$course] = [];
        }
        if (!isset($courseYearSections[$course][$year])) {
            $courseYearSections[$course][$year] = [];
        }
        if (!in_array($section, $courseYearSections[$course][$year])) {
            $courseYearSections[$course][$year][] = $section;
        }
    }
}

// Signatory options organized by hierarchy
$globalSignatories = ["Student Government (SG)", "PTCA", "Research Office", "Scholarship Office", "Registrar", "Librarian"];
$departmentSignatories = ["Program Head", "Class Adviser"];
$signatoryOptions = array_merge($globalSignatories, $departmentSignatories);

// ========================================
// --- FETCH TAKEN ROLES FOR CONFLICT CHECK ---
// ========================================
$taken = [];
$programHeadDepts = []; // Track Program Head departments separately
$taken_q = $conn->query("SELECT signatory_type, department, year, section FROM users WHERE role='signatory'");
while ($r = $taken_q->fetch_assoc()) {
    $key = $r['signatory_type'];
    if ($key === 'Program Head') {
        // Program Head can have multiple departments (comma-separated)
        $depts = explode(',', trim($r['department'] ?? ''));
        foreach ($depts as $dept) {
            $dept = trim($dept);
            if (!empty($dept)) {
                $programHeadDepts[$dept] = true;
            }
        }
    } elseif ($key === 'Class Adviser') {
    $combinations = array_filter(array_map('trim', explode(',', $r['section'] ?? '')));

    foreach ($combinations as $combo) {
        $parts = explode('|', $combo);
        if (count($parts) === 3) {
            // Current format: course|year|section
            list($combo_course, $combo_year, $combo_section) = array_map('trim', $parts);
            $taken["Class Adviser|{$combo_course}|{$combo_year}|{$combo_section}"] = true;
        } elseif (count($parts) === 2) {
            // Legacy format: year|section — use department as course
            $dept = trim($r['department'] ?? '');
            list($combo_year, $combo_section) = array_map('trim', $parts);
            $taken["Class Adviser|{$dept}|{$combo_year}|{$combo_section}"] = true;
        }
    }
    } else {
        // Global roles
        $taken[$key] = true;
    }
}
// --- AJAX: Reset Signatory Password ---
if (isset($_POST['ajax_action']) && $_POST['ajax_action'] === 'reset_signatory_password') {
    header('Content-Type: application/json');

    $signatory_id = filter_var($_POST['signatory_id'] ?? null, FILTER_VALIDATE_INT);

    if (!$signatory_id) {
        echo json_encode(['success' => false, 'message' => 'Invalid signatory ID.']);
        exit;
    }

    $check = $conn->prepare("SELECT id, full_name FROM users WHERE id = ? AND role = 'signatory' LIMIT 1");
    $check->bind_param("i", $signatory_id);
    $check->execute();
    $sig_row = $check->get_result()->fetch_assoc();
    $check->close();

    if (!$sig_row) {
        echo json_encode(['success' => false, 'message' => 'Signatory not found.']);
        exit;
    }

    $default_password = password_hash('@Signatory01', PASSWORD_DEFAULT);
    $upd = $conn->prepare("UPDATE users SET password = ?, password_last_updated = NOW() WHERE id = ? AND role = 'signatory'");
    $upd->bind_param("si", $default_password, $signatory_id);

    if ($upd->execute()) {
        echo json_encode(['success' => true, 'message' => "Password for \"{$sig_row['full_name']}\" has been reset to @Signatory01."]);
    } else {
        echo json_encode(['success' => false, 'message' => 'Database error: ' . $upd->error]);
    }
    $upd->close();
    exit;
}
// --- Add Signatory Handler ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_signatory'])) {
    $username = trim($_POST['username']);
$full_name = trim($_POST['full_name']);
$email = trim($_POST['email']); 
$role = 'signatory';

// Set default password if empty
$raw_password = trim($_POST['password'] ?? '');
if (empty($raw_password)) {
    $raw_password = '@Signatory01';
}
$password = password_hash($raw_password, PASSWORD_DEFAULT);
    
    $signatory_type = trim($_POST['signatory_type']);
    $department = ''; 
    $year = ''; 
    $section = ''; 

    // Handle Program Head - Multiple Departments
    if ($signatory_type === 'Program Head') {
        if (isset($_POST['departments']) && is_array($_POST['departments'])) {
            $department = implode(',', array_map('trim', $_POST['departments']));
        }
        if (empty($department)) {
            $msg = "⚠️ Error: Pumili ng kahit isang department para sa Program Head.";
        }
    } 
    // Handle Class Adviser - Course, Year, Section (Multiselect)
    elseif ($signatory_type === 'Class Adviser') {
if (isset($_POST['adviser_courses']) && is_array($_POST['adviser_courses'])) {
    $department = implode(',', array_map('trim', $_POST['adviser_courses']));
}        
        if (empty($department)) {
            $msg = "⚠️ Error: Pumili ng Course para sa Class Adviser.";
        } elseif (!isset($_POST['year_sections']) || !is_array($_POST['year_sections']) || empty($_POST['year_sections'])) {
            $msg = "⚠️ Error: Pumili ng kahit isang Year-Section para sa Class Adviser.";
        } else {
            // Store year-section combinations as comma-separated
            // Format: "1st Year|A,1st Year|B,2nd Year|A"
            $yearSections = array_map('trim', $_POST['year_sections']);
            
            // Check if any of the selected combinations are already taken
            $conflicts = [];
            foreach ($yearSections as $ys) {
                $parts = explode('|', $ys);
                if (count($parts) === 3) {
                    list($ys_course, $ys_year, $ys_section) = array_map('trim', $parts);
                    $check_key = "Class Adviser|{$ys_course}|{$ys_year}|{$ys_section}";
                    if (isset($taken[$check_key])) {
                        $conflicts[] = "[{$ys_course}] {$ys_year} - Section {$ys_section}";
                    }
                }
            }
            
            if (!empty($conflicts)) {
                $msg = "⚠️Already occupied: <strong>" . implode(', ', $conflicts) . "</strong>!";
            } else {
                // Extract year from first combo (format: course|year|section)
                $firstParts = explode('|', $yearSections[0]);
                $year = (count($firstParts) === 3) ? trim($firstParts[1]) : '';

                // Store all combinations in section field
                $section = implode(',', $yearSections);
            }
        }
    }

    // Proceed if no errors
    if (empty($msg)) {
        // Check Username/Email uniqueness
        $check = $conn->prepare("SELECT username FROM users WHERE username = ? OR email = ?"); 
        $check->bind_param("ss", $username, $email); 
        $check->execute();
        
        if ($check->get_result()->num_rows > 0) {
            $msg = "⚠️ Error: The email or username is already taken."; 
        } else {
            $insert = $conn->prepare("INSERT INTO users (username, full_name, email, year, section, password, role, signatory_type, department) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)");
            $insert->bind_param("sssssssss", $username, $full_name, $email, $year, $section, $password, $role, $signatory_type, $department);
            
            if ($insert->execute()) {
                $_SESSION['message'] = "✅ Signatory added successfully!";
                header("Location: manage_signatories.php");
                exit();
            } else {
                $msg = "❌ Error: " . $insert->error;
            }
        }
    }
}

if (isset($_GET['delete'])) {
    $id = intval($_GET['delete']);
    $conn->query("DELETE FROM users WHERE id=$id AND role='signatory'");
    $_SESSION['message'] = "✅ Signatory deleted successfully!";
    header("Location: manage_signatories.php");
    exit();
}

$result = $conn->query("SELECT * FROM users WHERE role='signatory' ORDER BY full_name ASC");
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title>Manage Signatories | Admin</title>
    <link href='https://unpkg.com/boxicons@2.1.4/css/boxicons.min.css' rel='stylesheet'>
    <link rel="stylesheet" href="styles.css"> 
    <style>
        body { 
    font-family: 'Poppins', sans-serif; 
     background: #E4E9F7;
    margin: 0;
    padding: 0;
}
        .home { 
    position: relative; 
    left: 320px; 
    width: calc(100% - 320px); 
    transition: all 0.5s ease; 
    padding: 20px; 
    min-height: 100vh;
    background: #E4E9F7;
}
        .sidebar.close ~ .home { 
            left: 88px; 
            width: calc(100% - 88px); 
        }
        .dashboard-container { 
            width: 95%; 
            margin: 20px auto; 
            background: #fff; 
            border-radius: 15px; 
            padding: 30px; 
            box-shadow: 0 10px 30px rgba(0,0,0,0.2); 
        }
        .dashboard-header { 
            background: linear-gradient(135deg, #2d5016 0%, #1a3409 100%);
            color: white; 
            padding: 20px; 
            border-radius: 12px; 
            text-align: center; 
            margin-bottom: 30px;
            box-shadow: 0 4px 15px rgba(0,0,0,0.1);
        }
        .dashboard-header h2 {
            margin: 0;
            font-size: 28px;
            font-weight: 700;
            letter-spacing: 1px;
        }
        .button-group { 
            display: flex; 
            gap: 15px; 
            margin-bottom: 25px; 
            flex-wrap: wrap;
        }
        .add-btn { 
            background: linear-gradient(135deg, #2d5016 0%, #1a3409 100%);
            color: white; 
            padding: 12px 25px; 
            border: none; 
            border-radius: 8px; 
            cursor: pointer; 
            font-weight: 600; 
            display: flex; 
            align-items: center; 
            gap: 8px;
            transition: all 0.3s ease;
            box-shadow: 0 4px 10px rgba(45, 80, 22, 0.3);
            text-decoration: none;
        }
        .add-btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 6px 20px rgba(45, 80, 22, 0.4);
        }
        .add-btn i {
            font-size: 20px;
        }
        .import-btn {
            background: linear-gradient(135deg, #007bff 0%, #0056b3 100%);
        }
        .import-btn:hover {
            box-shadow: 0 6px 20px rgba(0, 123, 255, 0.4);
        }
        
        table { 
            width: 100%; 
            border-collapse: collapse; 
            margin-top: 20px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.05);
        }
        table th, table td { 
            padding: 15px; 
            border: 1px solid #e0e0e0; 
            text-align: center; 
        }
        table th { 
            background: linear-gradient(135deg, #2d5016 0%, #1a3409 100%);
            color: white; 
            font-weight: 600;
            text-transform: uppercase;
            font-size: 13px;
            letter-spacing: 0.5px;
        }
        table tr:nth-child(even) {
            background-color: #f8f9fa;
        }
        table tr:hover {
            background-color: #e8f5e9;
            transition: background-color 0.3s ease;
        }
        .action-link {
            color: #2d5016;
            text-decoration: none;
            margin: 0 8px;
            font-weight: 600;
            transition: all 0.3s ease;
            display: inline-flex;
            align-items: center;
            gap: 5px;
        }
        .action-link:hover {
            color: #1a3409;
            transform: scale(1.1);
        }
        .action-link.delete {
            color: #dc3545;
        }
        .action-link.delete:hover {
            color: #a71d2a;
        }
        
        .modal { 
            display: none; 
            position: fixed; 
            z-index: 1000; 
            left: 0; 
            top: 0; 
            width: 100%; 
            height: 100%; 
            background: rgba(0,0,0,0.5); 
            overflow-y: auto;
        }
        .modal-content { 
            background: white; 
            margin: 2% auto; 
            padding: 25px; 
            width: 420px; 
            max-width: 90%;
            border-radius: 12px;
            box-shadow: 0 10px 30px rgba(0,0,0,0.2);
            animation: slideDown 0.3s ease;
        }
        @keyframes slideDown {
            from {
                transform: translateY(-50px);
                opacity: 0;
            }
            to {
                transform: translateY(0);
                opacity: 1;
            }
        }
        .modal-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 20px;
            padding-bottom: 12px;
            border-bottom: 2px solid #e0e0e0;
        }
        .modal-header h3 {
            margin: 0;
            color: #2d5016;
            font-size: 20px;
            font-weight: 700;
            display: flex;
            align-items: center;
            gap: 8px;
        }
        .close-modal {
            cursor: pointer;
            font-size: 24px;
            color: #999;
            transition: color 0.3s ease;
            background: none;
            border: none;
            padding: 0;
        }
        .close-modal:hover {
            color: #333;
        }
        
        .form-group {
            margin-bottom: 15px;
        }
        .form-group label {
            display: block;
            margin-bottom: 6px;
            color: #333;
            font-weight: 600;
            font-size: 13px;
        }
        .form-group label i {
            margin-right: 5px;
            color: #2d5016;
        }
        
        /* Password Input with Toggle */
        .password-wrapper {
            position: relative;
            display: flex;
            align-items: center;
        }
        .password-wrapper input {
            width: 100%;
            padding-right: 45px;
        }
        .password-toggle {
            position: absolute;
            right: 12px;
            cursor: pointer;
            color: #666;
            font-size: 18px;
            transition: color 0.3s ease;
        }
        .password-toggle:hover {
            color: #2d5016;
        }
        
        input, select, .submit-btn { 
            width: 100%; 
            padding: 10px 12px; 
            border-radius: 6px; 
            border: 2px solid #e0e0e0; 
            box-sizing: border-box;
            font-size: 13px;
            transition: all 0.3s ease;
        }
        input:focus, select:focus {
            outline: none;
            border-color: #2d5016;
            box-shadow: 0 0 0 3px rgba(45, 80, 22, 0.1);
        }
        
        .submit-btn { 
            background: linear-gradient(135deg, #2d5016 0%, #1a3409 100%);
            color: white; 
            border: none; 
            font-weight: 600; 
            cursor: pointer;
            margin-top: 8px;
            font-size: 14px;
            transition: all 0.3s ease;
            box-shadow: 0 4px 15px rgba(45, 80, 22, 0.3);
        }
        .submit-btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 6px 20px rgba(45, 80, 22, 0.4);
        }
        
        option:disabled { 
            color: #999; 
            font-style: italic; 
            background: #f5f5f5; 
        }
        
        .checkbox-group {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 10px;
            padding: 12px;
            background: #f8f9fa;
            border-radius: 6px;
            border: 2px solid #e0e0e0;
            max-height: 300px;
            overflow-y: auto;
        }
        .checkbox-item {
            display: flex;
            align-items: center;
            padding: 6px;
            background: white;
            border-radius: 4px;
            transition: all 0.3s ease;
        }
        .checkbox-item:hover {
            background: #e8f5e9;
        }
        .checkbox-item input[type="checkbox"] {
            width: auto;
            margin-right: 8px;
            cursor: pointer;
            transform: scale(1.2);
        }
        .checkbox-item label {
            margin: 0;
            cursor: pointer;
            font-weight: 500;
            font-size: 13px;
        }
        .checkbox-item input[type="checkbox"]:disabled + label {
            color: #999;
            text-decoration: line-through;
        }
        
        .alert {
            padding: 15px 20px;
            margin-bottom: 20px;
            border-radius: 8px;
            display: flex;
            align-items: center;
            gap: 10px;
            animation: slideIn 0.3s ease;
        }
        @keyframes slideIn {
            from {
                transform: translateX(-20px);
                opacity: 0;
            }
            to {
                transform: translateX(0);
                opacity: 1;
            }
        }
        .alert-warning {
            background: #fff3cd;
            color: #856404;
            border: 1px solid #ffeaa7;
        }
        .alert-success {
            background: #d4edda;
            color: #155724;
            border: 1px solid #c3e6cb;
        }
        .alert i {
            font-size: 20px;
        }
        .alert div {
    flex: 1;
    line-height: 1.6;
}
        /* Hierarchical Accordion Styles */
.hierarchy-container {
    width: 100%;
    margin-top: 20px;
}

.category-header {
    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
    color: white;
    padding: 15px 20px;
    border-radius: 10px;
    margin-bottom: 15px;
    font-weight: 700;
    font-size: 18px;
    letter-spacing: 0.5px;
    display: flex;
    align-items: center;
    gap: 10px;
}

.category-header.global {
    background: linear-gradient(135deg, #3498db 0%, #2980b9 100%);
}

.category-header.program-head {
    background: linear-gradient(135deg, #f39c12 0%, #e67e22 100%);
}

.category-header.class-adviser {
    background: linear-gradient(135deg, #2d5016 0%, #1a3409 100%);
}

/* Global Signatories List */
.global-list {
    background: #f8f9fa;
    border-radius: 8px;
    padding: 15px;
    margin-bottom: 25px;
}

.global-item {
    background: white;
    padding: 15px 20px;
    margin-bottom: 10px;
    border-radius: 8px;
    border: 2px solid #e9ecef;
    display: flex;
    justify-content: space-between;
    align-items: center;
    transition: all 0.3s ease;
}

.global-item:hover {
    border-color: #3498db;
    box-shadow: 0 2px 8px rgba(52, 152, 219, 0.2);
}

.global-item .role-name {
    font-weight: 600;
    color: #2c3e50;
    display: flex;
    align-items: center;
    gap: 10px;
}

.global-item .user-name {
    color: #3498db;
    font-weight: 500;
}

.global-item.vacant {
    opacity: 0.6;
    border-style: dashed;
}

.global-item.vacant .user-name {
    color: #95a5a6;
    font-style: italic;
}

/* Department Level (for Program Heads & Class Advisers) */
.dept-row {
    background: #f8f9fa;
    padding: 15px 18px;
    margin-bottom: 8px;
    border-radius: 10px;
    cursor: pointer;
    transition: all 0.3s ease;
    border: 2px solid #e9ecef;
    display: flex;
    align-items: center;
    gap: 12px;
}

.dept-row:hover {
    background: #e9ecef;
    border-color: #2d5016;
}

.dept-row.expanded {
    background: linear-gradient(135deg, #2d5016 0%, #1a3409 100%);
    color: white;
    border-color: #2d5016;
}

.dept-row .toggle-icon {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    width: 28px;
    height: 28px;
    border-radius: 50%;
    background-color: #dee2e6;
    color: #2d5016;
    transition: all 0.3s ease;
    font-size: 18px;
}

.dept-row.expanded .toggle-icon {
    transform: rotate(90deg);
    background-color: white;
    color: #2d5016;
}

.dept-row .dept-name {
    font-weight: 700;
    font-size: 16px;
    flex: 1;
}

.dept-row .dept-badge {
    background: #2d5016;
    color: white;
    padding: 4px 12px;
    border-radius: 15px;
    font-size: 11px;
    font-weight: 600;
}

.dept-row.expanded .dept-badge {
    background: white;
    color: #2d5016;
}

/* Year/Section Container */
.dept-content {
    display: none;
    padding-left: 40px;
    margin-top: 8px;
    margin-bottom: 10px;
}

.dept-content.active {
    display: block;
}

/* Signatory Cards inside departments */
.signatory-card {
    background: white;
    padding: 15px 20px;
    margin-bottom: 8px;
    border-radius: 8px;
    border: 2px solid #e9ecef;
    display: flex;
    justify-content: space-between;
    align-items: center;
    transition: all 0.3s ease;
}

.signatory-card:hover {
    border-color: #2d5016;
    box-shadow: 0 2px 8px rgba(45, 80, 22, 0.2);
}

.signatory-card .sig-info {
    flex: 1;
}

.signatory-card .sig-name {
    font-weight: 600;
    color: #2c3e50;
    font-size: 15px;
    margin-bottom: 4px;
}

.signatory-card .sig-details {
    font-size: 13px;
    color: #7f8c8d;
}

.signatory-card .sig-actions {
    display: flex;
    gap: 10px;
}

/* Empty State */
.empty-section {
    padding: 30px;
    text-align: center;
    color: #adb5bd;
    font-size: 14px;
    background: #f8f9fa;
    border-radius: 8px;
    border: 2px dashed #dee2e6;
    margin: 15px 0;
}

.empty-section i {
    font-size: 40px;
    display: block;
    margin-bottom: 10px;
    opacity: 0.5;
}

/* Year/Section rows for Class Advisers */
.year-section-row {
    background: white;
    padding: 12px 16px;
    margin-bottom: 6px;
    border-radius: 6px;
    border: 2px solid #e9ecef;
    display: flex;
    justify-content: space-between;
    align-items: center;
}

.year-section-row:hover {
    border-color: #3498db;
    background: #f8f9fa;
}

.year-section-label {
    font-weight: 600;
    color: #2c3e50;
    font-size: 14px;
}

.badge-small {
    background: #3498db;
    color: white;
    padding: 3px 10px;
    border-radius: 12px;
    font-size: 11px;
    font-weight: 600;
}
/* View Section Styles (for modal view switching) */
.view-section {
    display: none;
}
.view-section.active {
    display: block;
}

.form-divider {
    border-top: 2px solid #eee;
    margin: 20px 0 15px 0;
    padding-top: 15px;
}

.back-button {
    background: #95a5a6;
    color: white;
    border: none;
    padding: 10px 20px;
    border-radius: 8px;
    cursor: pointer;
    font-size: 14px;
    font-weight: 600;
    display: inline-flex;
    align-items: center;
    gap: 8px;
}
.back-button:hover {
    background: #7f8c8d;
}

.btn {
    padding: 10px 18px;
    border-radius: 8px;
    border: none;
    cursor: pointer;
    text-decoration: none;
    font-size: 14px;
    font-weight: 600;
    display: inline-flex;
    align-items: center;
    gap: 8px;
    transition: all 0.3s ease;
}

.btn.import-btn {
    background: #3498db;
    color: white;
    width: 100%;
    justify-content: center;
    margin-top: 15px;
}
.btn.import-btn:hover {
    background: #2980b9;
}
    </style>
</head>
<body>

<?php 
    $admin_user = $_SESSION['username'];
    $admin_stmt = $conn->prepare("SELECT full_name FROM users WHERE username = ? AND role = 'admin'");
    $admin_stmt->bind_param("s", $admin_user);
    $admin_stmt->execute();
    $admin_res = $admin_stmt->get_result()->fetch_assoc();

    $signatoryFullName = !empty($admin_res['full_name']) ? $admin_res['full_name'] : $admin_user;
    $userRole = "Administrator"; 
    include 'sidebar_admin.php'; 
?>

<section class="home">
    <div class="dashboard-header">
        <h2><i class='bx bx-shield-alt-2'></i> MANAGE SIGNATORIES</h2>
    </div>
    
    <div class="dashboard-container">
        <?php if($msg): ?>
            <div class="alert <?= strpos($msg, '✅') !== false ? 'alert-success' : 'alert-warning' ?>">
                <i class='bx <?= strpos($msg, '✅') !== false ? 'bx-check-circle' : 'bx-error-circle' ?>'></i>
                <span><?= $msg ?></span>
            </div>
        <?php endif; ?>
        <?php if (isset($_SESSION['import_message'])): ?>
    <div class="alert <?= $_SESSION['import_status'] === 'success' ? 'alert-success' : 'alert-warning' ?>" style="white-space: pre-line;">
        <i class='bx <?= $_SESSION['import_status'] === 'success' ? 'bx-check-circle' : 'bx-error-circle' ?>'></i>
        <div><?= nl2br(htmlspecialchars($_SESSION['import_message'])) ?></div>
    </div>
    <?php
    unset($_SESSION['import_message']);
    unset($_SESSION['import_status']);
    ?>
<?php endif; ?>

        <div class="button-group">
    <button class="add-btn" onclick="openModal('addSignatoryModal')">
        <i class='bx bx-user-plus'></i> Add Signatory
    </button>
</div>
<!-- Search Box -->
<div style="margin-bottom: 20px;">
    <div style="position: relative; max-width: 400px;">
        <input type="text" id="searchSignatories" placeholder="Search signatories by name, username, or type..."
               style="width:100%; padding:12px 40px 12px 15px; border:2px solid #e9ecef; border-radius:8px; font-size:14px; box-sizing:border-box;">
        <i class='bx bx-search' style="position:absolute; right:15px; top:50%; transform:translateY(-50%); color:#adb5bd; font-size:18px;"></i>
    </div>
</div>
       <!-- Hierarchical Signatory Display -->
<div class="hierarchy-container" id="signatoryHierarchy">
    <div class="loading-spinner" style="text-align: center; padding: 40px; color: #adb5bd;">
        <i class='bx bx-loader-alt' style="font-size: 40px; animation: spin 1s linear infinite;"></i>
        <p>Loading signatories...</p>
    </div>
</div>

<style>
@keyframes spin {
    from { transform: rotate(0deg); }
    to { transform: rotate(360deg); }
}
</style>
    </div>
</section>

<div id="addSignatoryModal" class="modal">
    <div class="modal-content">
        <span class="close-btn" onclick="closeModal('addSignatoryModal')">&times;</span>
        
        <!-- Add Signatory View (Default) -->
        <div id="addSignatoryView" class="view-section active">
            <h3><i class='bx bx-user-plus'></i> Add New Signatory</h3>
            
            <form method="POST" id="signatoryForm">
                <div class="form-group">
                    <label><i class='bx bx-user'></i> Full Name</label>
                    <input type="text" name="full_name" placeholder="Enter full name" required>
                </div>

                <div class="form-group">
                    <label><i class='bx bx-id-card'></i> Username</label>
                    <input type="text" name="username" placeholder="Enter username" required>
                </div>
                
                <div class="form-group">
                    <label><i class='bx bx-envelope'></i> Email</label>
                    <input type="email" name="email" placeholder="Enter email address" required>
                </div>

                <div class="form-group">
                    <label><i class='bx bx-lock'></i> Password <small style="font-weight:400; color:#888;">(Optional - defaults to @Signatory01)</small></label>
                    <div class="password-wrapper">
                        <input type="password" name="password" id="passwordInput" placeholder="Leave blank for default password">
                        <i class='bx bx-hide password-toggle' id="togglePassword" onclick="togglePasswordVisibility()"></i>
                    </div>
                </div>

                <div class="form-group">
                    <label><i class='bx bx-briefcase'></i> Signatory Type</label>
                    <select name="signatory_type" id="mainType" onchange="handleTypeChange()" required>
                        <option value="">-- Select Signatory Type --</option>
                        <?php foreach($signatoryOptions as $opt): 
                            $disabled = (isset($taken[$opt]) && $opt !== 'Program Head' && $opt !== 'Class Adviser') ? 'disabled' : '';
                        ?>
                            <option value="<?= $opt ?>" <?= $disabled ?>>
                                <?= $opt ?><?= $disabled ? ' ✖ (Occupied)' : '' ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <!-- For Program Head - Multiple Departments -->
                <div id="programHeadContainer" style="display:none;">
                    <div class="form-group">
                        <label><i class='bx bx-building-house'></i> Select Departments</label>
                        <div class="checkbox-group">
                            <?php foreach($departmentOptions as $d): 
                                $isOccupied = isset($programHeadDepts[$d]);
                            ?>
                                <div class="checkbox-item">
                                    <input type="checkbox" 
                                           name="departments[]" 
                                           value="<?= $d ?>" 
                                           id="dept_<?= md5($d) ?>"
                                           <?= $isOccupied ? 'disabled' : '' ?>>
                                    <label for="dept_<?= md5($d) ?>">
                                        <?= $d ?><?= $isOccupied ? ' (Occupied)' : '' ?>
                                    </label>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                </div>

                <!-- For Class Adviser -->
                <div id="adviserExtras" style="display:none;">
                    <div class="form-group">
                        <label><i class='bx bx-building'></i> Select Courses </label>
                        <div class="checkbox-group">
                            <?php foreach($departmentOptions as $d): ?>
                                <div class="checkbox-item">
                                    <input type="checkbox" name="adviser_courses[]" value="<?= $d ?>" class="adviser-course-check" onchange="updateAdviserYearSections()">
                                    <label><?= $d ?></label>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </div>

                    <div class="form-group" id="yearSectionCheckboxContainer" style="display:none;">
                        <label><i class='bx bx-calendar'></i> Select Year Level & Sections (Multiselect)</label>
                        <div id="yearSectionCheckboxes" class="checkbox-group">
                            <!-- Dynamically populated via JavaScript -->
                        </div>
                    </div>
                </div>

                <button type="submit" name="add_signatory" class="submit-btn">
                    <i class='bx bx-check-circle'></i> Save Signatory
                </button>
                
                <div class="form-divider"></div>
                
                <button type="button" class="btn import-btn" onclick="showImportSignatoryView()">
                    <i class='bx bx-upload'></i> Import Class Advisers from Excel
                </button>
            </form>
        </div>
        
        <!-- Import Signatory View -->
        <div id="importSignatoryView" class="view-section">
            <div class="modal-header">
                <h3><i class='bx bx-upload'></i> Import Class Advisers from Excel</h3>
            </div>
            
            <form id="importSignatoryForm" action="class_adviser_import_handler.php" method="POST" enctype="multipart/form-data">
                <label>Select Excel/CSV File:</label>
                <input type="file" name="excel_file" accept=".xlsx, .xls, .csv" required>
                
                <div style="background: #f4f4ff; border: 1px solid #ccc; padding: 15px; border-radius: 8px; margin: 15px 0; font-size: 13px;">
                    <strong style="color: #3498db;">📋 Template Format:</strong>
                    <p style="margin: 8px 0 5px 0;">Columns: Username | Full Name | Email | Signatory Type | Course | Year-Section | Password (optional)</p>
                    <p style="margin: 5px 0; color: #666;">Signatory Type must be "Class Adviser"</p>
                    <p style="margin: 5px 0; color: #666;">If Password column is empty, default @Signatory01 will be used.</p>
                    <a href="download_class_adviser_template.php" style="color: #3498db; text-decoration: none;">
                        <i class='bx bx-download'></i> Download Template
                    </a>
                </div>
                
                <div style="margin-top:20px; display:flex; gap:10px;">
                    <button type="submit" class="btn add-btn" style="flex:2; justify-content:center;">
                        <i class='bx bx-data'></i> Start Import
                    </button>
                    <button type="button" class="back-button" onclick="showAddSignatoryView()" style="flex:1; justify-content:center;">
                        <i class='bx bx-arrow-back'></i> Back
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>
<!-- Edit Signatory Modal -->
<div id="editSignatoryModal" class="modal">
    <div class="modal-content" style="max-width: 700px;">
        <div class="modal-header">
            <h3><i class='bx bx-edit-alt'></i> Edit Signatory</h3>
            <button class="close-modal" onclick="closeModal('editSignatoryModal')">&times;</button>
        </div>
        
        <form method="POST" id="editSignatoryForm" action="update_signatory.php">
            <input type="hidden" name="signatory_id" id="edit_signatory_id">
            
            <div class="form-group">
                <label><i class='bx bx-user'></i> Full Name</label>
                <input type="text" name="full_name" id="edit_full_name" placeholder="Enter full name" required>
            </div>

            <div class="form-group">
                <label><i class='bx bx-id-card'></i> Username</label>
                <input type="text" name="username" id="edit_username" placeholder="Enter username" required>
            </div>
            
            <div class="form-group">
                <label><i class='bx bx-envelope'></i> Email</label>
                <input type="email" name="email" id="edit_email" placeholder="Enter email address" required>
            </div>

            <div class="form-group">
                <label><i class='bx bx-lock'></i> Password <small style="font-weight:400; color:#888;">(Leave blank to keep current)</small></label>
                <div class="password-wrapper">
                    <input type="password" name="password" id="edit_passwordInput" placeholder="Leave blank to keep current password">
                    <i class='bx bx-hide password-toggle' id="edit_togglePassword" onclick="toggleEditPasswordVisibility()"></i>
                </div>
            </div>

            <div class="form-group">
                <label><i class='bx bx-briefcase'></i> Signatory Type</label>
                <select name="signatory_type" id="edit_mainType" onchange="handleEditTypeChange()" required>
                    <option value="">-- Select Signatory Type --</option>
                </select>
            </div>

            <!-- For Program Head - Multiple Departments -->
            <div id="edit_programHeadContainer" style="display:none;">
                <div class="form-group">
                    <label><i class='bx bx-building-house'></i> Select Departments (Multiselect)</label>
                    <div class="checkbox-group" id="edit_departmentCheckboxes">
                        <!-- Dynamically populated -->
                    </div>
                </div>
            </div>

            <!-- For Class Adviser -->
            <div id="edit_adviserExtras" style="display:none;">
                <div class="form-group">
                    <label><i class='bx bx-building'></i> Select Courses (Multiselect)</label>
                    <div class="checkbox-group" id="edit_adviserCourseCheckboxes">
                        <!-- Dynamically populated -->
                    </div>
                </div>

                <div class="form-group" id="edit_yearSectionCheckboxContainer" style="display:none;">
                    <label><i class='bx bx-calendar'></i> Select Year Level & Sections (Multiselect)</label>
                    <div id="edit_yearSectionCheckboxes" class="checkbox-group">
                        <!-- Dynamically populated via JavaScript -->
                    </div>
                </div>
            </div>
<div style="margin-top: 15px; padding: 15px; background: #fff3cd; border: 1px solid #ffc107; border-radius: 8px;">
    <div style="display: flex; justify-content: space-between; align-items: center;">
        <div>
            <strong style="color: #856404; font-size: 14px;"><i class='bx bx-lock'></i> Password</strong>
            <div style="font-size: 12px; color: #856404; margin-top: 3px;">Reset signatory's password back to default (@Signatory01)</div>
        </div>
        <button type="button" onclick="resetSignatoryPassword()" 
                style="background: #e67e22; color: white; border: none; padding: 8px 15px; border-radius: 8px; cursor: pointer; font-weight: 600; font-size: 13px; display: flex; align-items: center; gap: 6px;">
            <i class='bx bx-refresh'></i> Reset Password
        </button>
    </div>
    <div id="resetSignatoryPasswordMsg" style="display:none; margin-top:10px; padding:8px 12px; border-radius:6px; font-size:13px; font-weight:500;"></div>
</div>
            <button type="submit" name="update_signatory" class="submit-btn">
                <i class='bx bx-check-circle'></i> Update Signatory
            </button>
        </form>
    </div>
</div>

<script>
const takenAssignments = <?= json_encode($taken) ?>;
const courseYearSections = <?= json_encode($courseYearSections) ?>;

function openModal(id) { 
    document.getElementById(id).style.display = 'block'; 
}

function closeModal(id) { 
    document.getElementById(id).style.display = 'none';
}

function togglePasswordVisibility() {
    const passwordInput = document.getElementById('passwordInput');
    const toggleIcon = document.getElementById('togglePassword');
    
    if (passwordInput.type === 'password') {
        passwordInput.type = 'text';
        toggleIcon.classList.remove('bx-hide');
        toggleIcon.classList.add('bx-show');
    } else {
        passwordInput.type = 'password';
        toggleIcon.classList.remove('bx-show');
        toggleIcon.classList.add('bx-hide');
    }
}

function handleTypeChange() {
    const type = document.getElementById('mainType').value;
    const programHeadContainer = document.getElementById('programHeadContainer');
    const adviserExtras = document.getElementById('adviserExtras');
    
    programHeadContainer.style.display = (type === 'Program Head') ? 'block' : 'none';
    adviserExtras.style.display = (type === 'Class Adviser') ? 'block' : 'none';
    
    // Reset Class Adviser fields
    if (type === 'Class Adviser') {
        document.querySelectorAll('.adviser-course-check').forEach(cb => cb.checked = false);
        document.getElementById('yearSectionCheckboxContainer').style.display = 'none';
        document.getElementById('yearSectionCheckboxes').innerHTML = '';
    }
}

function updateAdviserYearSections() {
    const checkedCourses = Array.from(document.querySelectorAll('.adviser-course-check:checked')).map(cb => cb.value);
    const container = document.getElementById('yearSectionCheckboxContainer');
    const checkboxesDiv = document.getElementById('yearSectionCheckboxes');

    checkboxesDiv.innerHTML = '';

    if (checkedCourses.length === 0) {
        container.style.display = 'none';
        return;
    }

    container.style.display = 'block';

    checkedCourses.forEach(course => {
        if (courseYearSections[course]) {
            const years = Object.keys(courseYearSections[course]).sort();

            years.forEach(year => {
                courseYearSections[course][year].forEach(section => {
                    const val = `${course}|${year}|${section}`;
                    // Matches the fixed $taken key format: course|year|section
                    const isOccupied = !!takenAssignments[`Class Adviser|${val}`];
                    const uniqueId = `ys_${btoa(unescape(encodeURIComponent(val))).replace(/=/g, '')}`;

                    const div = document.createElement('div');
                    div.className = 'checkbox-item';
                    div.innerHTML = `
                        <input type="checkbox" name="year_sections[]" value="${val}" id="${uniqueId}"
                            ${isOccupied ? 'disabled' : ''}>
                        <label for="${uniqueId}">
                            <strong>[${course}]</strong> ${year} - Section ${section}
                            ${isOccupied ? '<span style="color:#e74c3c;font-size:11px;"> ✖ Occupied</span>' : ''}
                        </label>
                    `;
                    checkboxesDiv.appendChild(div);
                });
            });
        }
    });
}
window.onclick = function(event) {
    if (event.target.className === 'modal') {
        event.target.style.display = "none";
    }
}
// ============================================
// HIERARCHICAL DISPLAY FUNCTIONS
// ============================================

const globalRoles = ["Student Government (SG)", "PTCA", "Research Office", "Scholarship Office", "Registrar", "Librarian"];
function loadSignatoryHierarchy() {
    fetch('get_all_signatories.php')
    .then(r => r.json())
    .then(signatories => {
        buildSignatoryHierarchy(signatories);
    })
    .catch(err => {
        console.error('Error loading signatories:', err);
        document.getElementById('signatoryHierarchy').innerHTML = `
            <div class="empty-section">
                <i class='bx bx-error-circle'></i>
                <p>Error loading signatories. Please refresh the page.</p>
            </div>
        `;
    });
}

function buildSignatoryHierarchy(signatories) {
    const container = document.getElementById('signatoryHierarchy');
    let html = '';
    
    // 1. GLOBAL SIGNATORIES (Flat List)
    html += `
        <div class="category-header global">
            <i class='bx bx-world'></i> Global Signatories
        </div>
        <div class="global-list">
    `;
    
    globalRoles.forEach(role => {
        const signatory = signatories.find(s => s.signatory_type === role);
        if (signatory) {
            html += `
                <div class="global-item searchable-sig" data-sig-name="${signatory.full_name.toLowerCase()}" data-sig-username="${signatory.username.toLowerCase()}" data-sig-type="${role.toLowerCase()}">
                    <div>
                        <div class="role-name">
                            <i class='bx bx-shield-alt-2'></i>
                            <strong>${role}</strong>
                        </div>
                        <div class="user-name">${signatory.full_name}</div>
                    </div>
                    <div style="display: flex; gap: 10px;">
                        <a href="#" onclick="openEditModal(${signatory.id}); return false;" class="action-link">
                            <i class='bx bx-edit-alt'></i> Edit
                        </a>
                        <a href="manage_signatories.php?delete=${signatory.id}" class="action-link delete" onclick="return confirm('Delete this signatory?')">
                            <i class='bx bx-trash'></i> Delete
                        </a>
                    </div>
                </div>
            `;
        } else {
            html += `
                <div class="global-item vacant searchable-sig" data-sig-name="" data-sig-username="" data-sig-type="${role.toLowerCase()}">
                    <div>
                        <div class="role-name">
                            <i class='bx bx-shield-alt-2'></i>
                            <strong>${role}</strong>
                        </div>
                        <div class="user-name">No one assigned</div>
                    </div>
                </div>
            `;
        }
    });
    
    html += `</div>`; // Close global-list
    
    // 2. PROGRAM HEADS (Flat List - No Accordion)
    const programHeads = signatories.filter(s => s.signatory_type === 'Program Head');
    
    html += `
        <div class="category-header program-head">
            <i class='bx bx-building'></i> Program Heads
        </div>
        <div class="global-list">
    `;
    
    if (programHeads.length > 0) {
        programHeads.forEach(ph => {
            const depts = ph.department ? ph.department.split(',').map(d => d.trim()).join(', ') : 'No departments';
            html += `
                <div class="global-item searchable-sig" data-sig-name="${ph.full_name.toLowerCase()}" data-sig-username="${ph.username.toLowerCase()}" data-sig-type="program head">
                    <div>
                        <div class="role-name">
                            <i class='bx bx-user-tie'></i>
                            <strong>${ph.full_name}</strong>
                        </div>
                        <div class="user-name">
                            <i class='bx bx-building'></i> ${depts}
                        </div>
                        <div class="user-name" style="font-size: 12px; color: #95a5a6; margin-top: 4px;">
                            <i class='bx bx-user'></i> ${ph.username} | <i class='bx bx-envelope'></i> ${ph.email || 'N/A'}
                        </div>
                    </div>
                    <div style="display: flex; gap: 10px;">
                        <a href="#" onclick="openEditModal(${ph.id}); return false;" class="action-link">
                            <i class='bx bx-edit-alt'></i> Edit
                        </a>
                        <a href="manage_signatories.php?delete=${ph.id}" class="action-link delete" onclick="return confirm('Delete this signatory?')">
                            <i class='bx bx-trash'></i> Delete
                        </a>
                    </div>
                </div>
            `;
        });
    } else {
        html += `
            <div class="global-item vacant searchable-sig" data-sig-name="" data-sig-username="" data-sig-type="program head">
                <div>
                    <div class="role-name">
                        <i class='bx bx-user-tie'></i>
                        <strong>No Program Heads</strong>
                    </div>
                    <div class="user-name">Click "Add Signatory" to assign</div>
                </div>
            </div>
        `;
    }
    
    html += `</div>`; // Close global-list for Program Heads
    
    // 3. CLASS ADVISERS (Accordion by Department)
    const classAdvisers = signatories.filter(s => s.signatory_type === 'Class Adviser');
    
    if (classAdvisers.length > 0) {
        html += `
            <div class="category-header class-adviser">
                <i class='bx bx-chalkboard'></i> Class Advisers
            </div>
        `;
        
        // Group by department
        const adviserDeptGroups = {};
        classAdvisers.forEach(ca => {
            const depts = ca.department ? ca.department.split(',').map(d => d.trim()) : [];
            depts.forEach(dept => {
                if (!adviserDeptGroups[dept]) adviserDeptGroups[dept] = [];
                adviserDeptGroups[dept].push(ca);
            });
        });
        
        Object.keys(adviserDeptGroups).sort().forEach((dept, index) => {
            const advisers = adviserDeptGroups[dept];
            html += `
                <div class="dept-row" data-dept-index="ca-${index}" onclick="toggleDept('ca-${index}')">
                    <i class='bx bx-chevron-right toggle-icon'></i>
                    <span class="dept-name">
                        <i class='bx bx-book-bookmark'></i> ${dept}
                    </span>
                    <span class="dept-badge">${advisers.length} Adviser(s)</span>
                </div>
                <div class="dept-content" data-dept-index="ca-${index}">
            `;
            
            advisers.forEach(adviser => {
                const courseGroups = {};
                if (adviser.section) {
                    const combinations = adviser.section.split(',').map(c => c.trim());
                    combinations.forEach(combo => {
                        if (combo.includes('|')) {
                            const parts = combo.split('|').map(p => p.trim());
                            if (parts.length === 3) {
                                const [crs, yr, sec] = parts;
                                if (!courseGroups[crs]) courseGroups[crs] = [];
                                courseGroups[crs].push(`${yr} - Section ${sec}`);
                            } else if (parts.length === 2) {
                                const [yr, sec] = parts;
                                const crs = dept;
                                if (!courseGroups[crs]) courseGroups[crs] = [];
                                courseGroups[crs].push(`${yr} - Section ${sec}`);
                            }
                        }
                    });
                }

                let sectionDisplay = 'No sections assigned';
                const courseKeys = Object.keys(courseGroups);
                if (courseKeys.length > 0) {
                    const parts = [];
                    if (courseGroups[dept]) {
                        parts.push(`${dept}: ${courseGroups[dept].join(', ')}`);
                    }
                    courseKeys.forEach(crs => {
                        if (crs !== dept) {
                            parts.push(`(${crs}: ${courseGroups[crs].join(', ')})`);
                        }
                    });
                    sectionDisplay = parts.join('  ');
                }
                
                html += `
                    <div class="signatory-card searchable-sig" data-sig-name="${adviser.full_name.toLowerCase()}" data-sig-username="${adviser.username.toLowerCase()}" data-sig-type="class adviser">
                        <div class="sig-info">
                            <div class="sig-name">${adviser.full_name}</div>
                            <div class="sig-details">
                                <i class='bx bx-calendar'></i> ${sectionDisplay}
                            </div>
                            <div class="sig-details" style="margin-top: 4px;">
                                <i class='bx bx-user'></i> ${adviser.username} | <i class='bx bx-envelope'></i> ${adviser.email || 'N/A'}
                            </div>
                        </div>
                        <div class="sig-actions">
                            <a href="#" onclick="openEditModal(${adviser.id}); return false;" class="action-link">
                                <i class='bx bx-edit-alt'></i> Edit
                            </a>
                            <a href="manage_signatories.php?delete=${adviser.id}" class="action-link delete" onclick="return confirm('Delete this signatory?')">
                                <i class='bx bx-trash'></i> Delete
                            </a>
                        </div>
                    </div>
                `;
            });
            
            html += `</div>`; // Close dept-content
        });
    }
    
    // If no signatories at all
    if (signatories.length === 0) {
        html = `
            <div class="empty-section">
                <i class='bx bx-user-x'></i>
                <p>No signatories found. Click "Add Signatory" to create one.</p>
            </div>
        `;
    }
    
    container.innerHTML = html;
}

function toggleDept(deptIndex) {
    const deptRow = document.querySelector(`.dept-row[data-dept-index="${deptIndex}"]`);
    const deptContent = document.querySelector(`.dept-content[data-dept-index="${deptIndex}"]`);
    
    const isExpanded = deptRow.classList.contains('expanded');

    // Close all open accordions first
    document.querySelectorAll('.dept-row.expanded').forEach(row => {
        row.classList.remove('expanded');
    });
    document.querySelectorAll('.dept-content.active').forEach(content => {
        content.classList.remove('active');
    });

    // If it wasn't open before, open it now
    if (!isExpanded) {
        deptRow.classList.add('expanded');
        deptContent.classList.add('active');
    }
}
function openEditModal(id) {
    // Fetch signatory data
    fetch('get_signatory_data.php?id=' + id)
    .then(r => r.json())
    .then(data => {
        if (data.error) {
            alert(data.error);
            return;
        }
        
        // Populate form fields
        document.getElementById('edit_signatory_id').value = data.id;
        document.getElementById('edit_full_name').value = data.full_name;
        document.getElementById('edit_username').value = data.username;
        document.getElementById('edit_email').value = data.email;
        document.getElementById('edit_passwordInput').value = '';
        
        // Populate signatory type dropdown
const typeSelect = document.getElementById('edit_mainType');
typeSelect.innerHTML = '<option value="">-- Select Signatory Type --</option>';
<?php foreach($signatoryOptions as $opt): ?>
typeSelect.add(new Option('<?= $opt ?>', '<?= $opt ?>'));
<?php endforeach; ?>
typeSelect.value = data.signatory_type;

// Check if this is a global, program head, or class adviser
const globalRolesArray = ["Student Government (SG)", "PTCA", "Research Office", "Scholarship Office", "Registrar", "Librarian"];
const isGlobal = globalRolesArray.includes(data.signatory_type);
const isProgramHead = data.signatory_type === 'Program Head';
const isClassAdviser = data.signatory_type === 'Class Adviser';

// Disable signatory type dropdown for globals, program heads, and class advisers
if (isGlobal || isProgramHead || isClassAdviser) {
    typeSelect.disabled = true;
    typeSelect.style.backgroundColor = '#f9f9f9';
    typeSelect.style.cursor = 'not-allowed';
    typeSelect.style.color = '#999';

    // Disabled selects don't submit — mirror value to hidden input
    let hiddenType = document.getElementById('edit_signatoryType_hidden');
    if (!hiddenType) {
        hiddenType = document.createElement('input');
        hiddenType.type = 'hidden';
        hiddenType.id = 'edit_signatoryType_hidden';
        hiddenType.name = 'signatory_type';
        document.getElementById('editSignatoryForm').appendChild(hiddenType);
    }
    hiddenType.value = data.signatory_type;
} else {
    typeSelect.disabled = false;
    typeSelect.style.backgroundColor = '';
    typeSelect.style.cursor = '';
    typeSelect.style.color = '';
    // Remove hidden input if exists
    const hiddenType = document.getElementById('edit_signatoryType_hidden');
    if (hiddenType) hiddenType.remove();
}
// Store current data for later use
window.currentEditData = data;
        
        // Handle type-specific fields
        handleEditTypeChange();
        
        // Open modal
        openModal('editSignatoryModal');
    })
    .catch(err => {
        console.error('Error loading signatory data:', err);
        alert('Error loading signatory data. Please try again.');
    });
}

function handleEditTypeChange() {
    const type = document.getElementById('edit_mainType').value;
    const programHeadContainer = document.getElementById('edit_programHeadContainer');
    const adviserExtras = document.getElementById('edit_adviserExtras');
    
    programHeadContainer.style.display = (type === 'Program Head') ? 'block' : 'none';
    adviserExtras.style.display = (type === 'Class Adviser') ? 'block' : 'none';
    
    if (type === 'Program Head' && window.currentEditData) {
        populateEditDepartments();
    } else if (type === 'Class Adviser' && window.currentEditData) {
        populateEditAdviserCourses();
    }
}

function populateEditDepartments() {
    const container = document.getElementById('edit_departmentCheckboxes');
    const currentDepts = window.currentEditData.department ? window.currentEditData.department.split(',').map(d => d.trim()) : [];
    
    let html = '';
    <?php foreach($departmentOptions as $d): ?>
    {
        const dept = '<?= $d ?>';
        const isChecked = currentDepts.includes(dept);
        const checkboxId = 'edit_dept_' + btoa(dept).replace(/=/g, '');
        html += `
            <div class="checkbox-item">
                <input type="checkbox" 
                       name="departments[]" 
                       value="${dept}" 
                       id="${checkboxId}"
                       ${isChecked ? 'checked' : ''}>
                <label for="${checkboxId}">${dept}</label>
            </div>
        `;
    }
    <?php endforeach; ?>
    container.innerHTML = html;
}

function populateEditAdviserCourses() {
    const container = document.getElementById('edit_adviserCourseCheckboxes');
    const currentDepts = window.currentEditData.department ? window.currentEditData.department.split(',').map(d => d.trim()) : [];
    
    let html = '';
    <?php foreach($departmentOptions as $d): ?>
    {
        const dept = '<?= $d ?>';
        const isChecked = currentDepts.includes(dept);
        const checkboxId = 'edit_adviser_course_' + btoa(dept).replace(/=/g, '');
        html += `
            <div class="checkbox-item">
                <input type="checkbox" 
                       name="adviser_courses[]" 
                       value="${dept}" 
                       class="edit-adviser-course-check"
                       id="${checkboxId}"
                       onchange="updateEditAdviserYearSections()"
                       ${isChecked ? 'checked' : ''}>
                <label for="${checkboxId}">${dept}</label>
            </div>
        `;
    }
    <?php endforeach; ?>
    container.innerHTML = html;
    
    // Trigger year-section population
    if (currentDepts.length > 0) {
        updateEditAdviserYearSections();
    }
}

function updateEditAdviserYearSections() {
    const checkedCourses = Array.from(document.querySelectorAll('.edit-adviser-course-check:checked')).map(cb => cb.value);
    const container = document.getElementById('edit_yearSectionCheckboxContainer');
    const checkboxesDiv = document.getElementById('edit_yearSectionCheckboxes');
    
    checkboxesDiv.innerHTML = '';
    
    if (checkedCourses.length === 0) {
        container.style.display = 'none';
        return;
    }
    
    container.style.display = 'block';
    
    // Get current year-sections from edit data
    let currentYearSections = [];
    if (window.currentEditData.section) {
        const combinations = window.currentEditData.section.split(',').map(c => c.trim());
        combinations.forEach(combo => {
            if (combo.includes('|')) {
                const parts = combo.split('|').map(p => p.trim());
                if (parts.length === 3) {
                    // Format: Course|Year|Section
                    currentYearSections.push(combo);
                }
            }
        });
    }
    
    checkedCourses.forEach(course => {
        if (courseYearSections[course]) {
            const years = Object.keys(courseYearSections[course]).sort();
            
            years.forEach(year => {
                courseYearSections[course][year].forEach(section => {
                    const val = `${course}|${year}|${section}`;
                    const isChecked = currentYearSections.includes(val);
                   const isOccupied = !!takenAssignments[`Class Adviser|${val}`] && !isChecked;
                    const uniqueId = `edit_ys_${btoa(unescape(encodeURIComponent(val))).replace(/=/g, '')}`;
                    
                    const div = document.createElement('div');
                    div.className = 'checkbox-item';
                    div.innerHTML = `
                        <input type="checkbox" 
                               name="year_sections[]" 
                               value="${val}" 
                               id="${uniqueId}" 
                               ${isChecked ? 'checked' : ''}
                               ${isOccupied ? 'disabled' : ''}>
                        <label for="${uniqueId}">
                            <strong>[${course}]</strong> ${year} - ${section} ${isOccupied ? '(Occupied)' : ''}
                        </label>
                    `;
                    checkboxesDiv.appendChild(div);
                });
            });
        }
    });
}

function toggleEditPasswordVisibility() {
    const passwordInput = document.getElementById('edit_passwordInput');
    const toggleIcon = document.getElementById('edit_togglePassword');
    
    if (passwordInput.type === 'password') {
        passwordInput.type = 'text';
        toggleIcon.classList.remove('bx-hide');
        toggleIcon.classList.add('bx-show');
    } else {
        passwordInput.type = 'password';
        toggleIcon.classList.remove('bx-show');
        toggleIcon.classList.add('bx-hide');
    }
}
function handleSignatoryFileUpload(input) {
    if (input.files && input.files[0]) {
        const file = input.files[0];
        const fileName = file.name.toLowerCase();
        
        // Validate file type
        if (!fileName.endsWith('.xlsx') && !fileName.endsWith('.xls') && !fileName.endsWith('.csv')) {
            alert('❌ Invalid file type. Please select an Excel or CSV file.');
            input.value = '';
            return;
        }
        
        // Validate file size (5MB)
        if (file.size > 5 * 1024 * 1024) {
            alert('❌ File size exceeds 5MB. Please select a smaller file.');
            input.value = '';
            return;
        }
        
        // Show loading state (optional)
        const importButton = document.querySelector('button[onclick*="signatoryImportFile"]');
        if (importButton) {
            importButton.innerHTML = '<i class="bx bx-loader-alt bx-spin"></i> Uploading...';
            importButton.disabled = true;
        }
        
        // Submit form instantly
        input.form.submit();
    }
}
// Load hierarchy on page load
document.addEventListener('DOMContentLoaded', function() {
    loadSignatoryHierarchy();
});
// View switching functions for import modal
function showAddSignatoryView() {
    document.getElementById('addSignatoryView').classList.add('active');
    document.getElementById('importSignatoryView').classList.remove('active');
}

function showImportSignatoryView() {
    document.getElementById('addSignatoryView').classList.remove('active');
    document.getElementById('importSignatoryView').classList.add('active');
}

// Update closeModal to reset view
function closeModal(id) { 
    document.getElementById(id).style.display = 'none';
    if (id === 'addSignatoryModal') {
        showAddSignatoryView(); // Reset to add view when closing
    }
}
document.getElementById('searchSignatories').addEventListener('input', function() {
    const term = this.value.toLowerCase().trim();

    if (term === '') {
        document.querySelectorAll('.searchable-sig').forEach(el => el.style.display = '');
        document.querySelectorAll('.dept-row, .dept-content').forEach(el => {
            el.classList.remove('expanded', 'active');
            el.style.display = '';
        });
        return;
    }

    // Expand all dept accordions
    document.querySelectorAll('.dept-row').forEach(row => row.classList.add('expanded'));
    document.querySelectorAll('.dept-content').forEach(content => content.classList.add('active'));

    // Filter cards
    document.querySelectorAll('.searchable-sig').forEach(el => {
        const name = el.dataset.sigName || '';
        const username = el.dataset.sigUsername || '';
        const type = el.dataset.sigType || '';
        el.style.display = (name.includes(term) || username.includes(term) || type.includes(term)) ? '' : 'none';
    });

    // Hide empty dept accordions
    document.querySelectorAll('.dept-content').forEach(content => {
        const visible = Array.from(content.querySelectorAll('.searchable-sig')).some(el => el.style.display !== 'none');
        const deptIndex = content.dataset.deptIndex;
        const deptRow = document.querySelector(`.dept-row[data-dept-index="${deptIndex}"]`);
        if (!visible) {
            content.style.display = 'none';
            if (deptRow) deptRow.style.display = 'none';
        } else {
            content.style.display = '';
            if (deptRow) deptRow.style.display = '';
        }
    });
});
function resetSignatoryPassword() {
    const signatoryId = document.getElementById('edit_signatory_id').value;
    const signatoryName = document.getElementById('edit_full_name').value;

    if (!signatoryId) {
        alert('No signatory selected.');
        return;
    }

    if (!confirm(`Reset password for "${signatoryName}" to default (@Signatory01)?\n\nThe signatory will need to use this password on their next login.`)) {
        return;
    }

    const msgEl = document.getElementById('resetSignatoryPasswordMsg');
    msgEl.style.display = 'none';

    const fd = new FormData();
    fd.append('ajax_action', 'reset_signatory_password');
    fd.append('signatory_id', signatoryId);

    fetch('manage_signatories.php', { method: 'POST', body: fd })
    .then(r => r.json())
    .then(res => {
        if (res.success) {
            msgEl.textContent = '✅ ' + res.message;
            msgEl.style.cssText = 'display:block; background:#d4edda; color:#155724; border:1px solid #c3e6cb; padding:8px 12px; border-radius:6px; font-size:13px; font-weight:500; margin-top:10px;';
        } else {
            msgEl.textContent = '❌ ' + res.message;
            msgEl.style.cssText = 'display:block; background:#f8d7da; color:#721c24; border:1px solid #f5c6cb; padding:8px 12px; border-radius:6px; font-size:13px; font-weight:500; margin-top:10px;';
        }
        setTimeout(() => { msgEl.style.display = 'none'; }, 3000);
    })
    .catch(() => {
        msgEl.textContent = '❌ Network error. Please try again.';
        msgEl.style.cssText = 'display:block; background:#f8d7da; color:#721c24; border:1px solid #f5c6cb; padding:8px 12px; border-radius:6px; font-size:13px; font-weight:500; margin-top:10px;';
        msgEl.style.display = 'block';
    });
}
</script>
</body>
</html>