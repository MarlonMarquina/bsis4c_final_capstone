<?php
session_start();
include('conn.php'); 

// Security check: Only allow 'admin' role to access
if (!isset($_SESSION['role']) || $_SESSION['role'] != "admin") {
    header("Location: login.php");
    exit();
}

// Ensure $conn is loaded from conn.php
if (!isset($conn)) {
    die("Database connection error: \$conn variable not set in conn.php");
}

$msg = '';

/**
 * DATABASE FUNCTIONS
 */
function fetchCourses($conn) {
    $result = $conn->query("SELECT id, course_name, duration FROM courses ORDER BY course_name ASC");
    $courses = [];
    if ($result) {
        while ($row = $result->fetch_assoc()) {
            $courses[] = $row; 
        }
    }
    return $courses;
}

/**
 * AJAX HANDLER SECTION - MUST BE BEFORE ANY HTML
 */
if (isset($_GET['ajax_action'])) {
    header('Content-Type: application/json');
    $response = ["success" => false, "message" => "Invalid operation."];
    
    $type = $_REQUEST['type'] ?? '';
    $action = $_REQUEST['action'] ?? '';
    $name = trim($_REQUEST['name'] ?? '');
    $id = $_REQUEST['id'] ?? null;
    
    $name = filter_var($name, FILTER_SANITIZE_STRING); 
    $id = filter_var($id, FILTER_VALIDATE_INT);
    
    try {
        if ($action === 'fetch' && $type === 'course') {
            $stmt = $conn->prepare("SELECT id, course_name AS name, duration FROM courses ORDER BY course_name ASC");
            $stmt->execute();
            $result = $stmt->get_result();
            $output = [];
            while ($row = $result->fetch_assoc()) {
                $c_id = $row['id'];
                $sec_stmt = $conn->prepare("SELECT year, section_name FROM sections WHERE course_id = ? ORDER BY year ASC, section_name ASC");
                $sec_stmt->bind_param("i", $c_id);
                $sec_stmt->execute();
                $sec_res = $sec_stmt->get_result();
                $sections_list = [];
                while($s = $sec_res->fetch_assoc()){
                    $sections_list[] = $s;
                }
                $row['structure'] = $sections_list; 
                $output[] = $row;
                $sec_stmt->close();
            }
            echo json_encode($output);
            exit();
            
        } elseif ($action === 'fetch' && $type === 'section') {
            $courseId = filter_var($_GET['course_id'] ?? null, FILTER_VALIDATE_INT);
            $year = filter_var($_GET['year'] ?? '', FILTER_SANITIZE_STRING);
            
            if ($courseId && $year !== '') {
                $sql = "SELECT id, section_name AS name FROM sections WHERE course_id = ? AND year = ? ORDER BY section_name ASC";
                $stmt = $conn->prepare($sql);
                $stmt->bind_param("is", $courseId, $year);
                $stmt->execute();
                $result = $stmt->get_result();
                $output = [];
                while ($row = $result->fetch_assoc()) {
                    $output[] = $row;
                }
                echo json_encode($output);
                exit();
            } else {
                echo json_encode([]); 
                exit();
            }
            
        } elseif ($_SERVER['REQUEST_METHOD'] === 'POST') {
           if ($action === 'add' && $name !== '') {
                if ($type === 'course') {
                    $duration = filter_var($_POST['duration'] ?? 4, FILTER_VALIDATE_INT);
                    $stmt = $conn->prepare("INSERT INTO courses (course_name, duration) VALUES (?, ?)");
                    $stmt->bind_param("si", $name, $duration);
                    if ($stmt->execute()) {
                        $response = ["success" => true, "message" => "Course added."];
                    } else {
                        $response = ["success" => false, "message" => "Error adding course: " . $stmt->error];
                    }
                    $stmt->close();
                } elseif ($type === 'section') {
                    $courseId = filter_var($_POST['course_id'] ?? null, FILTER_VALIDATE_INT);
                    $year = filter_var($_POST['year'] ?? '', FILTER_SANITIZE_STRING);
                    if ($courseId && $year !== '') {
                        $stmt = $conn->prepare("INSERT INTO sections (course_id, year, section_name) VALUES (?, ?, ?)");
                        $stmt->bind_param("iss", $courseId, $year, $name);
                        if ($stmt->execute()) {
                            $response = ["success" => true, "message" => "Section added."];
                        } else {
                            $error_message = strpos($stmt->error, 'Duplicate entry') !== false ? "Section already exists for this Course and Year." : $stmt->error;
                            $response = ["success" => false, "message" => "Error adding section: " . $error_message];
                        }
                        $stmt->close();
                    } else {
                        $response = ["success" => false, "message" => "Missing Course ID or Year for section."];
                    }
                }

            } elseif ($action === 'delete' && $id) {
                if ($type === 'section') {
                    $sec_info = $conn->prepare("SELECT s.section_name, s.year, c.course_name FROM sections s JOIN courses c ON s.course_id = c.id WHERE s.id = ?");
                    $sec_info->bind_param("i", $id);
                    $sec_info->execute();
                    $sec_row = $sec_info->get_result()->fetch_assoc();
                    $sec_info->close();

                    $stmt = $conn->prepare("DELETE FROM sections WHERE id = ?");
                    $stmt->bind_param("i", $id);
                    if ($stmt->execute()) {
                        if ($sec_row) {
                            $combo_to_remove = "{$sec_row['course_name']}|{$sec_row['year']}|{$sec_row['section_name']}";
                            $ca_result = $conn->query("SELECT id, section FROM users WHERE role = 'signatory' AND signatory_type = 'Class Adviser'");
                            while ($ca = $ca_result->fetch_assoc()) {
                                $combos = array_filter(array_map('trim', explode(',', $ca['section'] ?? '')));
                                $new_combos = array_values(array_filter($combos, fn($c) => $c !== $combo_to_remove));
                                if (count($new_combos) !== count($combos)) {
                                    $new_section_val = implode(',', $new_combos);
                                    $ca_upd = $conn->prepare("UPDATE users SET section = ? WHERE id = ?");
                                    $ca_upd->bind_param("si", $new_section_val, $ca['id']);
                                    $ca_upd->execute();
                                    $ca_upd->close();
                                }
                            }
                        }
                        $response = ["success" => true, "message" => "Section deleted."];
                    } else {
                        $response = ["success" => false, "message" => "Error deleting section: " . $stmt->error];
                    }
                    $stmt->close();
                }
            }
        }
    } catch (Exception $e) {
        $response = ["success" => false, "message" => "Server exception: " . $e->getMessage()];
    }
    echo json_encode($response);
    exit(); 

}
/**
 * POST HANDLER: ADD STUDENT
 */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_student_user'])) {
    $username = trim($_POST['username']);
    $full_name = trim($_POST['full_name']);
    $email = trim($_POST['email']); 
    $role = 'student';
    
    $raw_password = trim($_POST['password'] ?? '');
    if (empty($raw_password)) {
        $raw_password = '@Student01';
    }
    $password = password_hash($raw_password, PASSWORD_DEFAULT);
    
    $course = filter_input(INPUT_POST, 'course_name_hidden', FILTER_SANITIZE_STRING) ?? ''; 
$year = filter_input(INPUT_POST, 'year', FILTER_SANITIZE_STRING) ?? ''; 
$section = filter_input(INPUT_POST, 'section', FILTER_SANITIZE_STRING) ?? '';

$signatory_type = ''; 
$department = '';

// Auto-fetch active term from system_settings
$term_row = $conn->query("SELECT current_semester, current_school_year FROM system_settings WHERE id=1")->fetch_assoc();
$auto_semester   = $term_row['current_semester']   ?? '1st Semester';
$auto_school_year = $term_row['current_school_year'] ?? '2025-2026';

    $check = $conn->prepare("SELECT username FROM users WHERE username = ? OR email = ?"); 
    $check->bind_param("ss", $username, $email); 
    $check->execute();
    $res = $check->get_result();
    $check->close();

    if ($res->num_rows > 0) {
        $msg = "⚠️ Username or Email already exists!";
    } else {
        $insert = $conn->prepare("INSERT INTO users (username, full_name, email, course, year, section, password, role, signatory_type, department, semester, school_year) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
if ($insert === false) {
    $msg = "❌ Prepare failed: " . $conn->error;
} else {
    $insert->bind_param("ssssssssssss", $username, $full_name, $email, $course, $year, $section, $password, $role, $signatory_type, $department, $auto_semester, $auto_school_year);
            if ($insert->execute()) {
                $msg = "✅ Student added successfully!";
            } else {
                $msg = "❌ Error adding student: " . $insert->error; 
            }
            $insert->close();
        }
    }
}
/**
 * AJAX HANDLER: EDIT STUDENT
 */
// --- AJAX: Reset Student Password ---
if (isset($_POST['ajax_action']) && $_POST['ajax_action'] === 'reset_password') {
    header('Content-Type: application/json');

    $student_id = filter_var($_POST['student_id'] ?? null, FILTER_VALIDATE_INT);

    if (!$student_id) {
        echo json_encode(['success' => false, 'message' => 'Invalid student ID.']);
        exit;
    }

    // Verify student exists
    $check = $conn->prepare("SELECT id, full_name FROM users WHERE id = ? AND role = 'student' LIMIT 1");
    $check->bind_param("i", $student_id);
    $check->execute();
    $student_row = $check->get_result()->fetch_assoc();
    $check->close();

    if (!$student_row) {
        echo json_encode(['success' => false, 'message' => 'Student not found.']);
        exit;
    }

    $default_password = password_hash('@Student01', PASSWORD_DEFAULT);
    $upd = $conn->prepare("UPDATE users SET password = ?, password_last_updated = NOW() WHERE id = ? AND role = 'student'");
    $upd->bind_param("si", $default_password, $student_id);

    if ($upd->execute()) {
        echo json_encode(['success' => true, 'message' => "Password for \"{$student_row['full_name']}\" has been reset to @Student01."]);
    } else {
        echo json_encode(['success' => false, 'message' => 'Database error: ' . $upd->error]);
    }
    $upd->close();
    exit;
}
if (isset($_POST['ajax_action']) && $_POST['ajax_action'] === 'edit_student') {
    header('Content-Type: application/json');

    $edit_id       = filter_var($_POST['edit_id'] ?? null, FILTER_VALIDATE_INT);
    $full_name     = trim($_POST['full_name'] ?? '');
    $username      = trim($_POST['username'] ?? '');
    $email         = trim($_POST['email'] ?? '');
    $new_course    = trim($_POST['course'] ?? '');
    $new_year      = trim($_POST['year'] ?? '');
    $new_section   = trim($_POST['section'] ?? '');

    if (!$edit_id || !$full_name || !$username || !$email) {
        echo json_encode(['success' => false, 'message' => 'Missing required fields.']);
        exit;
    }

    // Get current student data to detect changes
    $cur = $conn->prepare("SELECT username, course, year, section FROM users WHERE id = ? AND role = 'student' LIMIT 1");
    $cur->bind_param("i", $edit_id);
    $cur->execute();
    $cur_data = $cur->get_result()->fetch_assoc();
    $cur->close();

    if (!$cur_data) {
        echo json_encode(['success' => false, 'message' => 'Student not found.']);
        exit;
    }

    // Check username/email conflict (exclude current student)
    $chk = $conn->prepare("SELECT id FROM users WHERE (username = ? OR email = ?) AND id != ?");
    $chk->bind_param("ssi", $username, $email, $edit_id);
    $chk->execute();
    $chk_res = $chk->get_result();
    $chk->close();

    if ($chk_res->num_rows > 0) {
        echo json_encode(['success' => false, 'message' => 'Username or Email already in use by another user.']);
        exit;
    }

    // Update the student
    $upd = $conn->prepare("UPDATE users SET full_name=?, username=?, email=?, course=?, year=?, section=? WHERE id=? AND role='student'");
    $upd->bind_param("ssssssi", $full_name, $username, $email, $new_course, $new_year, $new_section, $edit_id);

    if (!$upd->execute()) {
        echo json_encode(['success' => false, 'message' => 'Error updating student: ' . $upd->error]);
        $upd->close();
        exit;
    }
    $upd->close();

    // If course/year/section changed, clear stale drafts
    $section_changed = (
        $cur_data['course']  !== $new_course ||
        $cur_data['year']    !== $new_year   ||
        $cur_data['section'] !== $new_section
    );

    $drafts_cleared = 0;
    if ($section_changed) {
        $old_username = $cur_data['username'];
        $del_drafts = $conn->prepare("DELETE FROM draft_requirements WHERE username = ?");
        $del_drafts->bind_param("s", $old_username);
        $del_drafts->execute();
        $drafts_cleared = $del_drafts->affected_rows;
        $del_drafts->close();
    }

    echo json_encode([
        'success'         => true,
        'message'         => 'Student updated successfully.',
        'section_changed' => $section_changed,
        'drafts_cleared'  => $drafts_cleared,
        'student'         => [
            'id'       => $edit_id,
            'full_name'=> $full_name,
            'username' => $username,
            'email'    => $email,
            'course'   => $new_course,
            'year'     => $new_year,
            'section'  => $new_section,
        ]
    ]);
    exit;
}

$allCourses = fetchCourses($conn); 
$yearOptionsList = ["1st Year", "2nd Year", "3rd Year", "4th Year", "5th Year"]; 

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Students | Smart Clearance System</title>
    <link href='https://unpkg.com/boxicons@2.1.4/css/boxicons.min.css' rel='stylesheet'>
    <link rel="stylesheet" href="styles.css">
    <style>
        body { font-family: 'Poppins', sans-serif;  background: #E4E9F7;; margin: 0; padding: 0; }
        
      .home { 
    position: relative; 
    left: 320px; 
    width: calc(100% - 320px); 
    padding: 30px; 
    min-height: 100vh;
    display: flex;
    flex-direction: column;
    align-items: center;
    transition: all 0.5s ease;
     background: #E4E9F7;
}
.sidebar.close ~ .home { 
    left: 88px; 
    width: calc(100% - 88px); 
}

        .dashboard-container { 
            width: 100%; 
            max-width: 1400px;
            background: #fff; 
            border-radius: 15px; 
            padding: 35px; 
            box-shadow: 0 10px 30px rgba(0,0,0,0.1); 
            box-sizing: border-box;
        }

        .dashboard-header { 
            background: linear-gradient(135deg, #2d5016 0%, #1a3409 100%);
            color: white; 
            padding: 30px; 
            border-radius: 12px; 
            text-align: center; 
            margin-bottom: 30px;
            width: 100%;
            max-width: 1400px;
            box-shadow: 0 4px 15px rgba(0,0,0,0.2);
        }
        .dashboard-header h2 { margin: 0; font-size: 28px; font-weight: 700; letter-spacing: 1px; text-transform: uppercase; }

        /* Accordion Hierarchy Styles */
        .hierarchy-container {
            width: 100%;
            margin-top: 20px;
        }

        /* Course Level (Top Level) */
        .course-row {
            background: #f8f9fa;
            padding: 18px 20px;
            margin-bottom: 8px;
            border-radius: 10px;
            cursor: pointer;
            transition: all 0.3s ease;
            border: 2px solid #e9ecef;
            display: flex;
            align-items: center;
            gap: 12px;
        }
        .course-row:hover {
            background: #e9ecef;
            border-color: #2d5016;
        }
        .course-row.expanded {
            background: linear-gradient(135deg, #2d5016 0%, #1a3409 100%);
            color: white;
            border-color: #2d5016;
        }
        .course-row .toggle-icon {
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
        .course-row.expanded .toggle-icon {
            transform: rotate(90deg);
            background-color: white;
            color: #2d5016;
        }
        .course-row .course-name {
            font-weight: 700;
            font-size: 16px;
            flex: 1;
        }
        .course-row .course-badge {
            background: #2d5016;
            color: white;
            padding: 6px 14px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: 600;
        }
        .course-row.expanded .course-badge {
            background: white;
            color: #2d5016;
        }

        /* Year Level Container */
        .year-container {
            display: none;
            padding-left: 40px;
            margin-top: 8px;
        }
        .year-container.active {
            display: block;
        }

        /* Year Level Row */
        .year-row {
            background: #fff;
            padding: 15px 18px;
            margin-bottom: 6px;
            border-radius: 8px;
            cursor: pointer;
            transition: all 0.3s ease;
            border: 2px solid #e9ecef;
            display: flex;
            align-items: center;
            gap: 10px;
            margin-left: 0;
        }
        .year-row:hover {
            background: #f8f9fa;
            border-color: #3498db;
        }
        .year-row.expanded {
            background: #3498db;
            color: white;
            border-color: #3498db;
        }
        .year-row .toggle-icon {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            width: 24px;
            height: 24px;
            border-radius: 50%;
            background-color: #e9ecef;
            color: #3498db;
            transition: all 0.3s ease;
            font-size: 16px;
        }
        .year-row.expanded .toggle-icon {
            transform: rotate(90deg);
            background-color: white;
            color: #3498db;
        }
        .year-row .year-label {
            font-weight: 600;
            font-size: 15px;
            flex: 1;
        }
        .year-row .year-badge {
            background: #3498db;
            color: white;
            padding: 4px 12px;
            border-radius: 15px;
            font-size: 11px;
            font-weight: 600;
        }
        .year-row.expanded .year-badge {
            background: white;
            color: #3498db;
        }

        /* Section Container */
        .section-container {
            display: none;
            padding-left: 40px;
            margin-top: 6px;
        }
        .section-container.active {
            display: block;
        }

        /* Section Row */
        .section-row {
            background: #fff;
            padding: 12px 16px;
            margin-bottom: 5px;
            border-radius: 6px;
            cursor: pointer;
            transition: all 0.3s ease;
            border: 2px solid #dee2e6;
            display: flex;
            align-items: center;
            gap: 8px;
        }
        .section-row:hover {
            background: #f1f8f1;
            border-color: #27ae60;
        }
        .section-row.expanded {
            background: #27ae60;
            color: white;
            border-color: #27ae60;
        }
        .section-row .toggle-icon {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            width: 22px;
            height: 22px;
            border-radius: 50%;
            background-color: #e9ecef;
            color: #27ae60;
            transition: all 0.3s ease;
            font-size: 14px;
        }
        .section-row.expanded .toggle-icon {
            transform: rotate(90deg);
            background-color: white;
            color: #27ae60;
        }
        .section-row .section-label {
            font-weight: 600;
            font-size: 14px;
            flex: 1;
        }
        .section-row .section-badge {
            background: #27ae60;
            color: white;
            padding: 3px 10px;
            border-radius: 12px;
            font-size: 10px;
            font-weight: 600;
        }
        .section-row.expanded .section-badge {
            background: white;
            color: #27ae60;
        }

        /* Students Container */
        .students-container {
            display: none;
            padding-left: 40px;
            margin-top: 5px;
            margin-bottom: 10px;
        }
        .students-container.active {
            display: block;
        }

        /* Students Table */
        .students-table {
            background: white;
            border-radius: 8px;
            overflow: hidden;
            border: 1px solid #e9ecef;
            margin-bottom: 10px;
        }
        .students-table table {
            width: 100%;
            border-collapse: collapse;
        }
        .students-table th {
            background: #f8f9fa;
            padding: 12px 15px;
            text-align: left;
            font-size: 12px;
            font-weight: 700;
            color: #495057;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            border-bottom: 2px solid #dee2e6;
        }
        .students-table td {
            padding: 12px 15px;
            border-bottom: 1px solid #f1f3f5;
            font-size: 13px;
            color: #495057;
        }
        .students-table tr:last-child td {
            border-bottom: none;
        }
        .students-table tr:hover {
            background-color: #f8f9fa;
        }

        /* Empty State */
        .empty-students {
            padding: 30px;
            text-align: center;
            color: #adb5bd;
            font-size: 14px;
            background: #f8f9fa;
            border-radius: 8px;
            border: 2px dashed #dee2e6;
        }
        .empty-students i {
            font-size: 40px;
            display: block;
            margin-bottom: 10px;
            opacity: 0.5;
        }

        /* Buttons */
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
        .add-btn { 
            background: linear-gradient(135deg, #2d5016 0%, #1a3409 100%); 
            color: white; 
            box-shadow: 0 4px 10px rgba(45, 80, 22, 0.3);
        }
        .add-btn:hover { transform: translateY(-2px); box-shadow: 0 6px 20px rgba(45, 80, 22, 0.4); }
        
        .btn-edit { 
            background-color: #4CAF50; 
            color: white; 
            padding: 6px 12px;
            font-size: 12px;
        }
        .btn-delete { 
            background-color: #e74c3c; 
            color: white;
            padding: 6px 12px;
            font-size: 12px;
        }
        .btn-delete:hover { background-color: #c0392b; }
        
        .import-btn { 
            background: #3498db; 
            color: white; 
            width: 100%;
            justify-content: center;
            margin-top: 15px;
        }
        .import-btn:hover { background: #2980b9; }

        /* Search Box */
        .search-container {
            margin-bottom: 20px;
            display: flex;
            gap: 10px;
            align-items: center;
        }
        .search-box {
            flex: 1;
            max-width: 400px;
            position: relative;
        }
        .search-box input {
            width: 100%;
            padding: 12px 40px 12px 15px;
            border: 2px solid #e9ecef;
            border-radius: 8px;
            font-size: 14px;
            transition: all 0.3s ease;
        }
        .search-box input:focus {
            border-color: #2d5016;
            outline: none;
            box-shadow: 0 0 0 3px rgba(45, 80, 22, 0.1);
        }
        .search-box i {
            position: absolute;
            right: 15px;
            top: 50%;
            transform: translateY(-50%);
            color: #adb5bd;
            font-size: 18px;
        }

        /* Statistics Cards */
        .stats-container {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 15px;
            margin-bottom: 25px;
        }
        .stat-card {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 20px;
            border-radius: 10px;
            box-shadow: 0 4px 15px rgba(0,0,0,0.1);
        }
        .stat-card.green { background: linear-gradient(135deg, #2d5016 0%, #1a3409 100%); }
        .stat-card.blue { background: linear-gradient(135deg, #3498db 0%, #2980b9 100%); }
        .stat-card.orange { background: linear-gradient(135deg, #f39c12 0%, #e67e22 100%); }
        .stat-card h4 {
            margin: 0 0 10px 0;
            font-size: 14px;
            opacity: 0.9;
            font-weight: 500;
        }
        .stat-card .stat-number {
            font-size: 32px;
            font-weight: 700;
            margin: 0;
        }

        /* Modals - Keep your existing modal styles */
        .modal { display: none; position: fixed; z-index: 1000; left: 0; top: 0; width: 100%; height: 100%; background-color: rgba(0,0,0,0.6); backdrop-filter: blur(5px); overflow-y: auto; }
        .modal-content { 
            background-color: #fff; 
            margin: 3% auto; 
            padding: 30px; 
            width: 90%; 
            max-width: 550px; 
            border-radius: 15px; 
            box-shadow: 0 15px 40px rgba(0,0,0,0.2); 
            animation: slideDown 0.4s ease;
            max-height: 90vh;
            overflow-y: auto;
        }
        @keyframes slideDown { from { transform: translateY(-50px); opacity: 0; } to { transform: translateY(0); opacity: 1; } }
        
        .close-btn { 
            color: #888; 
            float: right; 
            font-size: 30px; 
            font-weight: bold; 
            cursor: pointer; 
            transition: 0.3s;
            line-height: 20px;
        }
        .close-btn:hover { color: #e74c3c; }
        
        .modal-content h3 { 
            color: #2d5016; 
            margin-bottom: 20px; 
            margin-top: 5px;
            font-size: 22px; 
            border-bottom: 2px solid #f0f0f0; 
            padding-bottom: 10px; 
        }
        .modal-content label { 
            display: block; 
            margin: 12px 0 6px; 
            text-align: left; 
            font-weight: 600; 
            color: #555; 
            font-size: 14px;
        }
        .modal-content input, 
        .modal-content select { 
            width: 100%; 
            padding: 12px; 
            border: 2px solid #eee; 
            border-radius: 8px; 
            box-sizing: border-box; 
            font-family: inherit;
            font-size: 14px;
        }
        .modal-content input:focus, 
        .modal-content select:focus { 
            border-color: #2d5016; 
            outline: none; 
        }
        
        .form-divider {
            border-top: 2px solid #eee;
            margin: 20px 0 15px 0;
            padding-top: 15px;
        }
        
        .hierarchy-details { display: none; background: #f9f9f9; padding: 15px; border-radius: 10px; margin-top: 10px; border-left: 5px solid #2d5016; }
        .year-label { font-weight: bold; color: #2d5016; text-decoration: none; font-size: 14px; }
        .section-pill { background: #2d5016; color: white; padding: 4px 12px; border-radius: 20px; font-size: 11px; font-weight: 500; }
        .view-info-btn { color: #2d5016; font-weight: 600; background: none; border: none; cursor: pointer; text-decoration: underline; padding: 10px 0; }

        .message { padding: 15px; border-radius: 8px; margin-bottom: 20px; font-weight: 500; border-left: 5px solid; }
        .message.success { background: #e8f5e9; color: #2e7d32; border-color: #2e7d32; }
        .message.error { background: #ffebee; color: #c62828; border-color: #c62828; }
        .disabled-field { background-color: #f9f9f9 !important; border-color: #eee !important; color: #bbb !important; }
        
        .view-section { display: none; }
        .view-section.active { display: block; }
        
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
            margin-top: 15px;
        }
        .back-button:hover {
            background: #7f8c8d;
        }

        /* Loading Spinner */
        .loading-spinner {
            text-align: center;
            padding: 40px;
            color: #adb5bd;
        }
        .loading-spinner i {
            font-size: 40px;
            animation: spin 1s linear infinite;
        }
        @keyframes spin {
            from { transform: rotate(0deg); }
            to { transform: rotate(360deg); }
        }
    </style>
</head>
<body>
    <?php 
    $admin_username = $_SESSION['username'];
    $stmt = $conn->prepare("SELECT full_name FROM users WHERE username = ? AND role = 'admin'");
    $stmt->bind_param("s", $admin_username);
    $stmt->execute();
    $admin_data = $stmt->get_result()->fetch_assoc();
    $signatoryFullName = !empty($admin_data['full_name']) ? $admin_data['full_name'] : $admin_username;
    $userRole = "Administrator"; 
    include 'sidebar_admin.php'; 
    ?>

    <section class="home">
        <div class="dashboard-header">
            <h2><i class='bx bxs-group'></i> Manage Students</h2>
        </div>

        <div class="dashboard-container">
            <?php if (!empty($msg)): ?>
    <div class="message <?= (strpos($msg, 'Error') !== false || strpos($msg, '⚠️') !== false || strpos($msg, '❌') !== false) ? 'error' : 'success' ?>">
        <?= htmlspecialchars($msg) ?>
    </div>
<?php endif; ?>

<?php if (isset($_SESSION['import_message'])): ?>
    <div class="message <?= $_SESSION['import_status'] === 'success' ? 'success' : ($_SESSION['import_status'] === 'warning' ? 'warning' : 'error') ?>">
        <?= nl2br(htmlspecialchars($_SESSION['import_message'])) ?>
    </div>
    <?php
    unset($_SESSION['import_message']);
    unset($_SESSION['import_status']);
    ?>
<?php endif; ?>

            <!-- Statistics Cards -->
            <div class="stats-container">
                <div class="stat-card green">
                    <h4>Total Students</h4>
                    <p class="stat-number" id="totalStudents">0</p>
                </div>
                <div class="stat-card blue">
                    <h4>Total Courses</h4>
                    <p class="stat-number" id="totalCourses">0</p>
                </div>
                <div class="stat-card orange">
                    <h4>Total Sections</h4>
                    <p class="stat-number" id="totalSections">0</p>
                </div>
            </div>

            <!-- Action Buttons -->
            <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px; flex-wrap: wrap; gap: 15px;">
                <div class="search-container">
                    <div class="search-box">
                        <input type="text" id="searchStudents" placeholder="Search students by name, username, or section...">
                        <i class='bx bx-search'></i>
                    </div>
                </div>
                <div style="display: flex; gap: 12px; flex-wrap: wrap;">
                    <a href="#" class="btn add-btn" style="background: #3498db;" onclick="openModal('manageCoursesModal'); loadCourses(); return false;"><i class='bx bx-book-bookmark'></i> Manage Courses</a>
                    <a href="#" class="btn add-btn" style="background: #f39c12;" onclick="openModal('manageSectionsUnifiedModal'); return false;"><i class='bx bx-category'></i> Manage Sections</a>
                    <a href="#" class="btn add-btn" onclick="openModal('addStudentModal'); showAddStudentView(); return false;"><i class='bx bx-user-plus'></i> Add Student</a>
                </div>
            </div>
            
            <!-- Hierarchical Student List -->
            <div class="hierarchy-container" id="studentHierarchy">
                <div class="loading-spinner">
                    <i class='bx bx-loader-alt'></i>
                    <p>Loading student data...</p>
                </div>
            </div>
        </div>
    </section>

    <!-- Keep all your existing modals (addStudentModal, manageSectionsUnifiedModal, manageCoursesModal) -->
    <!-- Add/Import Student Modal with View Switching -->
    <div id="addStudentModal" class="modal">
        <div class="modal-content">
            <span class="close-btn" onclick="closeModal('addStudentModal')">&times;</span>
            
            <!-- Add Student View -->
            <div id="addStudentView" class="view-section active">
                <h3><i class='bx bx-user-plus'></i> Add New Student</h3>
                <form method="POST">
                    <label>Full Name:</label>
                    <input type="text" name="full_name" placeholder="Enter Full Name" required>
                    
                    <label>Student ID:</label>
                    <input type="text" name="username" placeholder="Enter Student ID" required>
                    
                    <label>Email Address:</label>
                    <input type="email" name="email" placeholder="email@domain.com" required>
                    
                    <label>Password: <small style="color:#888;">(Optional - defaults to @Student01)</small></label>
                    <input type="password" name="password" placeholder="Leave blank for default password">
                    
                    <label>Course:</label>
                    <select name="course_id_selector" id="as_course" onchange="handleStudentFlow('course')" required>
                        <option value="">Select Course</option>
                    </select>
                    <input type="hidden" name="course_name_hidden" id="as_course_hidden">

                    <label>Year Level:</label>
                    <select name="year" id="as_year" class="disabled-field" disabled onchange="handleStudentFlow('year')" required>
                        <option value="">Select Course First</option>
                    </select>

                    <label>Section:</label>
                    <select name="section" id="as_section" class="disabled-field" disabled required>
                        <option value="">Select Year First</option>
                    </select>

                    <div style="margin-top:20px; display:flex; gap:10px;">
                        <button type="submit" name="add_student_user" class="btn add-btn" style="flex:2; justify-content:center;">Register Student</button>
                        <button type="button" class="btn" style="background:#eee; color:#333; flex:1; justify-content:center;" onclick="closeModal('addStudentModal')">Cancel</button>
                    </div>
                    
                    <div class="form-divider"></div>
                    
                    <button type="button" class="btn import-btn" onclick="showImportView()">
                        <i class='bx bx-upload'></i> Import Students from Excel
                    </button>
                </form>
            </div>
            
            <!-- Import Student View -->
            <div id="importStudentView" class="view-section">
                <h3><i class='bx bx-upload'></i> Import Students from Excel</h3>
                
                <form id="importForm" action="student_import_handler.php" method="POST" enctype="multipart/form-data">
    <label>Select Excel/CSV File:</label>
    <input type="file" name="excel_file" accept=".xlsx, .xls, .csv" required>
    
    <div style="background: #f4f4ff; border: 1px solid #ccc; padding: 15px; border-radius: 8px; margin: 15px 0; font-size: 13px;">
        <strong style="color: #3498db;">📋 Template Format:</strong>
        <p style="margin: 8px 0 5px 0;">Columns: Student ID | Full Name | Email | Course | Year | Section | Password (optional)</p>
        <p style="margin: 5px 0; color: #666;">If Password column is empty, default @Student01 will be used.</p>
        <a href="download_student_template.php" style="color: #3498db; text-decoration: none;">
            <i class='bx bx-download'></i> Download Template
        </a>
    </div>
    
    <div style="margin-top:20px; display:flex; gap:10px;">
        <button type="submit" class="btn add-btn" style="flex:2; justify-content:center;">
            <i class='bx bx-data'></i> Start Import
        </button>
        <button type="button" class="back-button" onclick="showAddStudentView()" style="flex:1; justify-content:center;">
            <i class='bx bx-arrow-back'></i> Back
        </button>
    </div>
</form>
            </div>
        </div>
    </div>

    <div id="manageSectionsUnifiedModal" class="modal">
    <div class="modal-content">
        <span class="close-btn" onclick="closeModal('manageSectionsUnifiedModal')">&times;</span>
        <h3><i class='bx bx-category'></i> Manage Sections</h3>
        <label>1. Select Course:</label>
        <select id="ms_course" onchange="handleSectionFlow('course')">
            <option value="">-- Select Course --</option>
        </select>

        <label>2. Select Year:</label>
        <select id="ms_year" class="disabled-field" disabled onchange="handleSectionFlow('year')">
            <option value="">-- Select Course First --</option>
        </select>

        <div id="ms_input_area" style="display:none; margin-top:20px; border-top:1px solid #eee; padding-top:20px;">
            <label>3. Current Sections:</label>
            <div id="ms_list" style="max-height:150px; overflow-y:auto; margin-bottom:15px; border:1px solid #eee; padding:10px; border-radius:10px; background:#fafafa;"></div>
            
            <label>4. How many sections to add?</label>
            <div style="display:flex; gap:8px; align-items:center;">
                <input type="number" id="ms_section_count" min="1" max="26" value="1" 
                       style="width: 100px; margin-bottom:0; text-align: center; font-weight: 600;">
                <button type="button" class="btn add-btn" onclick="submitMultipleSections()" style="flex: 1;">
                    <i class='bx bx-plus-circle'></i> Add Sections
                </button>
            </div>
            <div id="ms_preview" style="margin-top: 10px; padding: 10px; background: #f0f9ff; border-radius: 8px; font-size: 13px; display: none;">
                <strong style="color: #2563eb;">Preview:</strong> 
                <span id="ms_preview_text"></span>
            </div>
        </div>
        <button type="button" class="btn" style="background:#eee; color:#333; width:100%; margin-top:20px;" onclick="closeModal('manageSectionsUnifiedModal')">Close</button>
    </div>
</div>

    <div id="manageCoursesModal" class="modal">
        <div class="modal-content" style="max-width: 550px;">
            <span class="close-btn" onclick="closeModal('manageCoursesModal')">&times;</span>
            <h3><i class='bx bx-book-bookmark'></i> Manage Courses</h3>
            <div style="background:#fcfcfc; padding:15px; border-radius:10px; border:1px solid #eee; margin-bottom:20px;">
                <label>New Course Name:</label>
                <input type="text" id="c_name" placeholder="E.g., BSIS" required>
                <label>Duration (Years):</label>
                <select id="c_duration">
                    <option value="1">1 Year</option>
                    <option value="2">2 Years</option>
                    <option value="3">3 Years</option>
                    <option value="4" selected>4 Years</option>
                    <option value="5">5 Years</option>
                </select>
                <button type="button" class="btn add-btn" style="width:100%; margin-top:15px; justify-content:center;" onclick="submitNewCourse()">Save New Course</button>
            </div>
            
            <p style="text-align:left; font-weight:700; color:#2d5016; margin-bottom:10px;">Existing Courses:</p>
            <div id="c_list" style="max-height:300px; overflow-y:auto; padding-right:5px;"></div>
            <button type="button" class="btn" style="background:#eee; color:#333; width:100%; margin-top:20px;" onclick="closeModal('manageCoursesModal')">Close</button>
        </div>
    </div>
<!-- Edit Student Modal -->
<div id="editStudentModal" class="modal">
    <div class="modal-content">
        <span class="close-btn" onclick="closeModal('editStudentModal')">&times;</span>
        <h3><i class='bx bx-edit'></i> Edit Student</h3>

        <div id="editStudentMsg" style="display:none; padding:12px; border-radius:8px; margin-bottom:15px; font-weight:500; border-left:5px solid;"></div>

        <label>Full Name:</label>
        <input type="text" id="edit_full_name" placeholder="Full Name" required>

        <label>Student ID:</label>
        <input type="text" id="edit_username" placeholder="Username" required>

        <label>Email:</label>
        <input type="email" id="edit_email" placeholder="Email" required>

        <label>Course:</label>
        <select id="edit_course" onchange="handleEditStudentFlow('course')">
            <option value="">Select Course</option>
        </select>

        <label>Year Level:</label>
        <select id="edit_year" class="disabled-field" disabled onchange="handleEditStudentFlow('year')">
            <option value="">Select Course First</option>
        </select>

        <label>Section:</label>
        <select id="edit_section" class="disabled-field" disabled>
            <option value="">Select Year First</option>
        </select>

        <input type="hidden" id="edit_student_id">
        <div style="margin-top: 15px; padding: 15px; background: #fff3cd; border: 1px solid #ffc107; border-radius: 8px;">
    <div style="display: flex; justify-content: space-between; align-items: center;">
        <div>
            <strong style="color: #856404; font-size: 14px;"><i class='bx bx-lock'></i> Password</strong>
            <div style="font-size: 12px; color: #856404; margin-top: 3px;">Reset student's password back to default (@Student01)</div>
        </div>
        <button type="button" onclick="resetStudentPassword()" 
                style="background: #e67e22; color: white; border: none; padding: 8px 15px; border-radius: 8px; cursor: pointer; font-weight: 600; font-size: 13px; display: flex; align-items: center; gap: 6px;">
            <i class='bx bx-refresh'></i> Reset Password
        </button>
    </div>
    <div id="resetPasswordMsg" style="display:none; margin-top:10px; padding:8px 12px; border-radius:6px; font-size:13px; font-weight:500;"></div>
</div>

        <div id="editSectionWarning" style="display:none; background:#fff3cd; border:1px solid #ffc107; border-radius:8px; padding:12px; margin-top:12px; font-size:13px; color:#856404;">
            <i class='bx bx-info-circle'></i> <strong>Note:</strong> Changing the course, year, or section will clear this student's pending draft requirements.
        </div>

        <div style="margin-top:20px; display:flex; gap:10px;">
            <button type="button" class="btn add-btn" id="saveEditStudentBtn" onclick="saveEditStudent()" style="flex:2; justify-content:center;">
                <i class='bx bx-save'></i> Save Changes
            </button>
            <button type="button" class="btn" style="background:#eee; color:#333; flex:1; justify-content:center;" onclick="closeModal('editStudentModal')">Cancel</button>
        </div>
    </div>
</div>
    <script>
    // Global data storage
  let hierarchyData = {};
let allStudentsData = [];
let allSignatoriesData = [];

    // View switching functions
    function showAddStudentView() {
        document.getElementById('addStudentView').classList.add('active');
        document.getElementById('importStudentView').classList.remove('active');
    }
    
    function showImportView() {
        document.getElementById('addStudentView').classList.remove('active');
        document.getElementById('importStudentView').classList.add('active');
    }

function loadStudentHierarchy() {
    fetch('manage_students.php?ajax_action=1&type=course&action=fetch')
    .then(r => r.json())
    .then(courses => {
        // Fetch all students
        return fetch('get_all_students.php')
        .then(r => r.json())
        .then(students => {
            allStudentsData = students;
            return fetch('get_all_users.php')
            .then(r => r.json())
            .then(users => {
                allSignatoriesData = users.filter(u => u.role === 'signatory' && u.signatory_type === 'Class Adviser');
                buildHierarchy(courses, students);
                updateStatistics(courses, students);
            });
        });
    })
    .catch(err => {
        console.error('Error loading data:', err);
        document.getElementById('studentHierarchy').innerHTML = `
            <div class="empty-students">
                <i class='bx bx-error-circle'></i>
                <p>Error loading student data. Please refresh the page.</p>
            </div>
        `;
    });
}

function buildHierarchy(courses, students) {
    const container = document.getElementById('studentHierarchy');
    
    if (courses.length === 0) {
        container.innerHTML = `
            <div class="empty-students">
                <i class='bx bx-inbox'></i>
                <p>No courses found. Click "Manage Courses" to add one.</p>
            </div>
        `;
        return;
    }

    let html = '';
    
    courses.forEach((course, courseIndex) => {
        // Group students by year and section for this course
        const courseStudents = students.filter(s => s.course === course.name);
        const yearGroups = {};
        
        // FIRST: Add students to their respective year/section groups
        courseStudents.forEach(student => {
            const studentYear = student.year;
            const studentSection = student.section;
            
            if (studentYear && studentSection) {
                if (!yearGroups[studentYear]) {
                    yearGroups[studentYear] = {};
                }
                if (!yearGroups[studentYear][studentSection]) {
                    yearGroups[studentYear][studentSection] = [];
                }
                yearGroups[studentYear][studentSection].push(student);
            }
        });
        
        // SECOND: Add empty sections from course.structure (if any)
        course.structure.forEach(item => {
            if (!yearGroups[item.year]) {
                yearGroups[item.year] = {};
            }
            if (!yearGroups[item.year][item.section_name]) {
                yearGroups[item.year][item.section_name] = [];
            }
        });

        // THIRD: Mark orphaned sections (students whose section no longer exists in course.structure)
        const validSections = new Set(course.structure.map(item => item.section_name));
        Object.keys(yearGroups).forEach(yr => {
            Object.keys(yearGroups[yr]).forEach(sec => {
                if (!validSections.has(sec) && yearGroups[yr][sec].length > 0) {
                    yearGroups[yr][sec]._isOrphaned = true;
                }
            });
        });
            const totalCourseStudents = courseStudents.length;

            // Course Row
            html += `
                <div class="course-row" data-course-index="${courseIndex}" onclick="toggleCourse(${courseIndex})">
                    <i class='bx bx-chevron-right toggle-icon'></i>
                    <span class="course-name">
                        <i class='bx bx-book-bookmark'></i> ${course.name}
                    </span>
                    <span class="course-badge">${totalCourseStudents} Students</span>
                </div>
                <div class="year-container" data-course-index="${courseIndex}">
            `;

            // Year Level Rows
            const yearLabels = ["1st Year", "2nd Year", "3rd Year", "4th Year", "5th Year"];
            const yearOrder = ["1st Year","2nd Year","3rd Year","4th Year","5th Year"];
Object.keys(yearGroups).sort((a,b) => yearOrder.indexOf(a) - yearOrder.indexOf(b)).forEach((year, yearIndex) => {
                const yearStudents = Object.values(yearGroups[year]).flat();
                const yearKey = `${courseIndex}-${yearIndex}`;
                
                html += `
                    <div class="year-row" data-year-key="${yearKey}" onclick="event.stopPropagation(); toggleYear('${yearKey}')">
                        <i class='bx bx-chevron-right toggle-icon'></i>
                        <span class="year-label">
                            <i class='bx bx-graduation'></i> ${year}
                        </span>
                        <span class="year-badge">${yearStudents.length} Students</span>
                    </div>
                    <div class="section-container" data-year-key="${yearKey}">
                `;

// Section Rows
               // Section Rows
Object.keys(yearGroups[year]).sort((a, b) => a.localeCompare(b)).forEach((section, sectionIndex) => {
                    const sectionStudents = yearGroups[year][section].filter(s => typeof s === 'object' && s.id);
                    const sectionKey = `${yearKey}-${sectionIndex}`;
                    const isOrphaned = yearGroups[year][section]._isOrphaned;

                    if (isOrphaned) {
                        html += `
                            <div class="section-row" data-section-key="${sectionKey}" onclick="event.stopPropagation(); toggleSection('${sectionKey}')" style="background:#fff3cd; border-color:#ffc107; color:#856404;">
                                <i class='bx bx-error-circle toggle-icon' style="background:#ffc107; color:white;"></i>
                                <span class="section-label">
                                    <i class='bx bx-error-circle'></i> Section Deleted — Please create a new one
                                </span>
                                <span class="section-badge" style="background:#ffc107; color:#856404;">${sectionStudents.length} Students</span>
                            </div>
                            <div class="students-container active" data-section-key="${sectionKey}">
                                <div style="padding:12px 16px; background:#fff8e1; border-radius:8px; border:1px solid #ffc107; margin-bottom:10px; display:flex; align-items:center; gap:12px; flex-wrap:wrap;">
                                    <span style="color:#856404; font-size:13px; font-weight:600; flex:1;">
                                        <i class='bx bx-info-circle'></i> These students have no valid section. Create a new section to automatically move them.
                                    </span>
                                    <button class="btn" style="background:#2d5016; color:white; padding:8px 16px; font-size:12px;"
                                        onclick="event.stopPropagation(); createSectionForOrphans('${course.name}', '${year}', this)">
                                        <i class='bx bx-plus-circle'></i> Create New Section &amp; Move Students
                                    </button>
                                </div>
                        `;
                    } else {
                        const adviser = allSignatoriesData.find(u =>
                            u.signatory_type === 'Class Adviser' &&
                            u.section && u.section.split(',').map(s => s.trim()).some(combo => {
                                const parts = combo.split('|').map(p => p.trim());
                                return parts.length === 3 && parts[0] === course.name && parts[1] === year && parts[2] === section;
                            })
                        );
                        html += `
                            <div class="section-row" data-section-key="${sectionKey}" onclick="event.stopPropagation(); toggleSection('${sectionKey}')">
                                <i class='bx bx-chevron-right toggle-icon'></i>
                                <span class="section-label">
                                    <i class='bx bx-group'></i> ${section}
                                </span>
                                ${adviser
                                    ? `<span style="font-size:14px; font-weight:700; flex:2; text-align:center;"><i class='bx bx-chalkboard'></i> Class Adviser: ${adviser.full_name}</span>`
                                    : `<span style="flex:2;"></span>`}
                                <span class="section-badge">${sectionStudents.length} Students</span>
                            </div>
                            <div class="students-container" data-section-key="${sectionKey}">
                        `;
                    }

                    if (sectionStudents.length > 0) {
                        html += `
                            <div class="students-table">
                                <table>
                                    <thead>
                                        <tr>
                                            <th>Full Name</th>
                                            <th>Student ID</th>
                                            <th>Email</th>
                                            <th style="text-align: center;">Actions</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                        `;
                        sectionStudents.forEach(student => {
                            html += `
                                <tr class="student-row" data-student-name="${student.full_name.toLowerCase()}" data-student-username="${student.username.toLowerCase()}" data-student-section="${section.toLowerCase()}">
                                    <td style="font-weight:600;">${student.full_name}</td>
                                    <td>${student.username}</td>
                                    <td>${student.email || 'N/A'}</td>
                                    <td style="text-align:center;">
                                        <button class="btn btn-edit" onclick="event.stopPropagation(); openEditStudent(${student.id}, '${student.full_name.replace(/'/g, "\\'")}', '${student.username.replace(/'/g, "\\'")}', '${(student.email||'').replace(/'/g, "\\'")}', '${student.course.replace(/'/g, "\\'")}', '${student.year.replace(/'/g, "\\'")}', '${student.section.replace(/'/g, "\\'")}')"><i class='bx bx-edit'></i> Edit</button>
                                        <a href="delete_student.php?id=${student.id}" class="btn btn-delete" onclick="event.stopPropagation(); return confirm('Are you sure?')"><i class='bx bx-trash'></i> Delete</a>
                                    </td>
                                </tr>
                            `;
                        });
                        html += `</tbody></table></div>`;
                    } else {
                        html += `
                            <div class="empty-students">
                                <i class='bx bx-user-x'></i>
                                <p>No students in this section yet</p>
                            </div>
                        `;
                    }

                    html += `</div>`; // Close students-container
                });

                html += `</div>`; // Close section-container
            });

            html += `</div>`; // Close year-container
        });

        container.innerHTML = html;
    }

    function updateStatistics(courses, students) {
        document.getElementById('totalStudents').textContent = students.length;
        document.getElementById('totalCourses').textContent = courses.length;
        
        let totalSections = 0;
        courses.forEach(course => {
            totalSections += course.structure.length;
        });
        document.getElementById('totalSections').textContent = totalSections;
    }

    function toggleCourse(index) {
        const courseRow = document.querySelector(`.course-row[data-course-index="${index}"]`);
        const yearContainer = document.querySelector(`.year-container[data-course-index="${index}"]`);
        
        // Close all other courses
        document.querySelectorAll('.course-row').forEach(row => {
            if (row.dataset.courseIndex !== String(index)) {
                row.classList.remove('expanded');
            }
        });
        document.querySelectorAll('.year-container').forEach(container => {
            if (container.dataset.courseIndex !== String(index)) {
                container.classList.remove('active');
            }
        });
        
        // Close all year levels
        document.querySelectorAll('.year-row').forEach(row => row.classList.remove('expanded'));
        document.querySelectorAll('.section-container').forEach(container => container.classList.remove('active'));
        
        // Close all sections
        document.querySelectorAll('.section-row').forEach(row => row.classList.remove('expanded'));
        document.querySelectorAll('.students-container').forEach(container => container.classList.remove('active'));
        
        // Toggle current course
        const isExpanded = courseRow.classList.contains('expanded');
        if (!isExpanded) {
            courseRow.classList.add('expanded');
            yearContainer.classList.add('active');
        } else {
            courseRow.classList.remove('expanded');
            yearContainer.classList.remove('active');
        }
    }

    function toggleYear(yearKey) {
        const yearRow = document.querySelector(`.year-row[data-year-key="${yearKey}"]`);
        const sectionContainer = document.querySelector(`.section-container[data-year-key="${yearKey}"]`);
        
        // Close all other years in the same course
        const courseIndex = yearKey.split('-')[0];
        document.querySelectorAll(`.year-row`).forEach(row => {
            if (row.dataset.yearKey !== yearKey && row.dataset.yearKey.startsWith(courseIndex + '-')) {
                row.classList.remove('expanded');
            }
        });
        document.querySelectorAll(`.section-container`).forEach(container => {
            if (container.dataset.yearKey !== yearKey && container.dataset.yearKey.startsWith(courseIndex + '-')) {
                container.classList.remove('active');
            }
        });
        
        // Close all sections
        document.querySelectorAll('.section-row').forEach(row => row.classList.remove('expanded'));
        document.querySelectorAll('.students-container').forEach(container => container.classList.remove('active'));
        
        // Toggle current year
        const isExpanded = yearRow.classList.contains('expanded');
        if (!isExpanded) {
            yearRow.classList.add('expanded');
            sectionContainer.classList.add('active');
        } else {
            yearRow.classList.remove('expanded');
            sectionContainer.classList.remove('active');
        }
    }

    function toggleSection(sectionKey) {
        const sectionRow = document.querySelector(`.section-row[data-section-key="${sectionKey}"]`);
        const studentsContainer = document.querySelector(`.students-container[data-section-key="${sectionKey}"]`);
        
        // Close all other sections in the same year
        const yearKey = sectionKey.substring(0, sectionKey.lastIndexOf('-'));
        document.querySelectorAll(`.section-row`).forEach(row => {
            if (row.dataset.sectionKey !== sectionKey && row.dataset.sectionKey.startsWith(yearKey + '-')) {
                row.classList.remove('expanded');
            }
        });
        document.querySelectorAll(`.students-container`).forEach(container => {
            if (container.dataset.sectionKey !== sectionKey && container.dataset.sectionKey.startsWith(yearKey + '-')) {
                container.classList.remove('active');
            }
        });
        
        // Toggle current section
        const isExpanded = sectionRow.classList.contains('expanded');
        if (!isExpanded) {
            sectionRow.classList.add('expanded');
            studentsContainer.classList.add('active');
        } else {
            sectionRow.classList.remove('expanded');
            studentsContainer.classList.remove('active');
        }
    }

// Search functionality
document.getElementById('searchStudents').addEventListener('input', function() {
    const searchTerm = this.value.toLowerCase().trim();
    
    if (searchTerm === '') {
        // Reset view - collapse all
        document.querySelectorAll('.course-row, .year-row, .section-row').forEach(row => {
            row.classList.remove('expanded');
            row.style.display = ''; // Reset display
        });
        document.querySelectorAll('.year-container, .section-container, .students-container').forEach(container => {
            container.classList.remove('active');
            container.style.display = ''; // Reset display
        });
        document.querySelectorAll('.student-row').forEach(row => {
            row.style.display = '';
        });
        return;
    }

    // Expand all and filter
    document.querySelectorAll('.course-row').forEach(row => {
        row.classList.add('expanded');
        row.style.display = ''; // Show all courses
    });
    document.querySelectorAll('.year-container').forEach(container => {
        container.classList.add('active');
        container.style.display = ''; // Show all year containers
    });
    document.querySelectorAll('.year-row').forEach(row => {
        row.classList.add('expanded');
        row.style.display = ''; // Show all years
    });
    document.querySelectorAll('.section-container').forEach(container => {
        container.classList.add('active');
        container.style.display = ''; // Show all section containers
    });
    document.querySelectorAll('.section-row').forEach(row => {
        row.classList.add('expanded');
        row.style.display = ''; // Show all sections initially
    });
    document.querySelectorAll('.students-container').forEach(container => {
        container.classList.add('active');
        container.style.display = ''; // Show all student containers initially
    });

    // Filter students
    document.querySelectorAll('.student-row').forEach(row => {
        const name = row.dataset.studentName || '';
        const username = row.dataset.studentUsername || '';
        const section = row.dataset.studentSection || '';
        
        const matches = name.includes(searchTerm) || 
                      username.includes(searchTerm) || 
                      section.includes(searchTerm);
        
        row.style.display = matches ? '' : 'none';
    });

    // Hide empty sections and their containers
    document.querySelectorAll('.students-container').forEach(container => {
        const visibleRows = Array.from(container.querySelectorAll('.student-row')).filter(row => row.style.display !== 'none');
        const sectionKey = container.dataset.sectionKey;
        const sectionRow = document.querySelector(`.section-row[data-section-key="${sectionKey}"]`);
        
        if (visibleRows.length === 0) {
            if (sectionRow) sectionRow.style.display = 'none';
            container.style.display = 'none';
        } else {
            if (sectionRow) sectionRow.style.display = '';
            container.style.display = '';
        }
    });
    
    // Hide empty year sections
    document.querySelectorAll('.section-container').forEach(container => {
        const visibleSections = Array.from(container.querySelectorAll('.section-row')).filter(row => row.style.display !== 'none');
        const yearKey = container.dataset.yearKey;
        const yearRow = document.querySelector(`.year-row[data-year-key="${yearKey}"]`);
        
        if (visibleSections.length === 0) {
            if (yearRow) yearRow.style.display = 'none';
            container.style.display = 'none';
        } else {
            if (yearRow) yearRow.style.display = '';
            container.style.display = '';
        }
    });
    
    // Hide empty courses
    document.querySelectorAll('.year-container').forEach(container => {
        const visibleYears = Array.from(container.querySelectorAll('.year-row')).filter(row => row.style.display !== 'none');
        const courseIndex = container.dataset.courseIndex;
        const courseRow = document.querySelector(`.course-row[data-course-index="${courseIndex}"]`);
        
        if (visibleYears.length === 0) {
            if (courseRow) courseRow.style.display = 'none';
            container.style.display = 'none';
        } else {
            if (courseRow) courseRow.style.display = '';
            container.style.display = '';
        }
    });
});
    
    function syncAllDropdowns() {
        fetch('manage_students.php?ajax_action=1&type=course&action=fetch')
        .then(r => r.json()).then(data => {
            const dropdowns = ['as_course', 'ms_course'];
            dropdowns.forEach(id => {
                const el = document.getElementById(id);
                if(!el) return;
                const saved = el.value;
                el.innerHTML = '<option value="">Select Course</option>';
                data.forEach(c => {
                    let opt = new Option(c.name, c.id);
                    opt.dataset.duration = c.duration;
                    el.add(opt);
                });
                el.value = saved;
            });
        });
    }

    function handleStudentFlow(step) {
        const c = document.getElementById('as_course');
        const y = document.getElementById('as_year');
        const s = document.getElementById('as_section');
        const hidden = document.getElementById('as_course_hidden');

        if(step === 'course') {
            if(!c.value) {
                y.disabled = s.disabled = true;
                y.classList.add('disabled-field'); s.classList.add('disabled-field');
                return;
            }
            hidden.value = c.options[c.selectedIndex].text;
            const dur = parseInt(c.options[c.selectedIndex].dataset.duration);
            y.innerHTML = '<option value="">Select Year</option>';
            const labels = ["1st Year", "2nd Year", "3rd Year", "4th Year", "5th Year"];
            for(let i=0; i<dur; i++) y.add(new Option(labels[i], labels[i]));
            y.disabled = false; y.classList.remove('disabled-field');
            s.disabled = true; s.classList.add('disabled-field');
        } 
        else if(step === 'year') {
            if(!y.value) return;
            fetch(`manage_students.php?ajax_action=1&type=section&action=fetch&course_id=${c.value}&year=${y.value}`)
            .then(r => r.json()).then(data => {
                s.innerHTML = '<option value="">Select Section</option>';
                data.forEach(item => s.add(new Option(item.name, item.name)));
                s.disabled = false; s.classList.remove('disabled-field');
            });
        }
    }

    function handleSectionFlow(step) {
        const c = document.getElementById('ms_course');
        const y = document.getElementById('ms_year');
        const area = document.getElementById('ms_input_area');

        if(step === 'course') {
            if(!c.value) {
                y.disabled = true; y.classList.add('disabled-field');
                area.style.display = 'none'; return;
            }
            const dur = parseInt(c.options[c.selectedIndex].dataset.duration);
            y.innerHTML = '<option value="">Select Year</option>';
            const labels = ["1st Year", "2nd Year", "3rd Year", "4th Year", "5th Year"];
            for(let i=0; i<dur; i++) y.add(new Option(labels[i], labels[i]));
            y.disabled = false; y.classList.remove('disabled-field');
            area.style.display = 'none';
        }
        else if(step === 'year') {
            if(!y.value) { area.style.display = 'none'; return; }
            area.style.display = 'block';
            refreshSectionList(c.value, y.value);
        }
    }

    function refreshSectionList(cid, year) {
        fetch(`manage_students.php?ajax_action=1&type=section&action=fetch&course_id=${cid}&year=${year}`)
        .then(r => r.json()).then(data => {
            const container = document.getElementById('ms_list');
            container.innerHTML = data.map(s => `
                <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:8px; background:#fff; padding:8px 12px; border-radius:8px; border:1px solid #eee;">
                    <span style="font-weight:500;">${s.name}</span>
                    <button type="button" onclick="deleteSectionItem(${s.id})" style="color:#e74c3c; background:none; border:none; cursor:pointer; font-size:18px;"><i class='bx bx-trash'></i></button>
                </div>
            `).join('') || '<small style="color:#999;">No sections created yet.</small>';
        });
    }

    // Get next section letter based on existing sections
function getNextSectionLetters(existingSections, count) {
    const alphabet = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ';
    // Build set of used letters
    const usedLetters = new Set();
    existingSections.forEach(section => {
        const sectionName = (section.name || section.section_name || section + '').toUpperCase().trim();
        if (sectionName.length === 1 && /^[A-Z]$/.test(sectionName)) {
            usedLetters.add(sectionName);
        }
    });

    // Fill gaps first, then continue past max
    const nextLetters = [];
    for (let i = 0; i < 26 && nextLetters.length < count; i++) {
        if (!usedLetters.has(alphabet[i])) {
            nextLetters.push(alphabet[i]);
        }
    }
    return nextLetters;
}
// Update preview when count changes
function updateSectionPreview() {
    const count = parseInt(document.getElementById('ms_section_count').value) || 0;
    const preview = document.getElementById('ms_preview');
    const previewText = document.getElementById('ms_preview_text');
    
    if (count > 0 && count <= 26) {
        // Get existing sections
        const existingSections = [];
        document.querySelectorAll('#ms_list span').forEach(span => {
            const sectionName = span.textContent.trim();
            if (sectionName) existingSections.push({ name: sectionName });
        });
        
        const nextLetters = getNextSectionLetters(existingSections, count);
        
        if (nextLetters.length > 0) {
            preview.style.display = 'block';
            previewText.innerHTML = `Will create sections: <strong>${nextLetters.join(', ')}</strong>`;
        } else {
            preview.style.display = 'none';
        }
    } else {
        preview.style.display = 'none';
    }
}

// Add event listener for real-time preview
document.addEventListener('DOMContentLoaded', function() {
    const countInput = document.getElementById('ms_section_count');
    if (countInput) {
        countInput.addEventListener('input', updateSectionPreview);
    }
});

// Submit multiple sections
function submitMultipleSections() {
    const cid = document.getElementById('ms_course').value;
    const year = document.getElementById('ms_year').value;
    const count = parseInt(document.getElementById('ms_section_count').value);
    
    if (!count || count < 1) {
        alert("Please enter a valid number of sections (minimum 1)");
        return;
    }
    
    if (count > 26) {
        alert("Maximum 26 sections can be added at once (A-Z)");
        return;
    }
    
    // Get existing sections first
    fetch(`manage_students.php?ajax_action=1&type=section&action=fetch&course_id=${cid}&year=${year}`)
    .then(r => r.json())
    .then(existingSections => {
        const nextLetters = getNextSectionLetters(existingSections, count);
        
        if (nextLetters.length === 0) {
            alert("Cannot add more sections. Maximum of 26 sections (A-Z) reached.");
            return;
        }
        
        if (nextLetters.length < count) {
            if (!confirm(`Can only add ${nextLetters.length} more section(s) before reaching the limit (Z). Continue?`)) {
                return;
            }
        }
        
        // Add sections one by one
        let addedCount = 0;
        let errorCount = 0;
        
        const addNextSection = (index) => {
            if (index >= nextLetters.length) {
                // All done
                if (addedCount > 0) {
                    alert(`✅ Successfully added ${addedCount} section(s)!`);
                    document.getElementById('ms_section_count').value = '1';
                    refreshSectionList(cid, year);
                    loadStudentHierarchy();
                } else if (errorCount > 0) {
                    alert(`❌ Failed to add sections. Please try again.`);
                }
                return;
            }
            
            const sectionName = nextLetters[index];
            let fd = new FormData();
            fd.append('course_id', cid);
            fd.append('year', year);
            fd.append('name', sectionName);
            
            fetch('manage_students.php?ajax_action=1&type=section&action=add', { method: 'POST', body: fd })
            .then(r => r.json())
            .then(res => {
                if (res.success) {
                    addedCount++;
                } else {
                    errorCount++;
                    console.error(`Failed to add section ${sectionName}:`, res.message);
                }
                // Add next section
                addNextSection(index + 1);
            })
            .catch(err => {
                errorCount++;
                console.error(`Error adding section ${sectionName}:`, err);
                // Continue with next section
                addNextSection(index + 1);
            });
        };
        
        // Start adding sections
        addNextSection(0);
    })
    .catch(err => {
        console.error('Error fetching existing sections:', err);
        alert('Error: Could not fetch existing sections. Please try again.');
    });
}

// Update the refreshSectionList to trigger preview update

// Update handleSectionFlow to trigger preview

    function submitNewCourse() {
        const name = document.getElementById('c_name').value.trim();
        const dur = document.getElementById('c_duration').value;
        if(!name) return alert("Name is required");
        let fd = new FormData();
        fd.append('name', name); fd.append('duration', dur);
        fetch('manage_students.php?ajax_action=1&type=course&action=add', { method: 'POST', body: fd })
        .then(r => r.json()).then(res => {
            if(res.success) {
                document.getElementById('c_name').value = '';
                loadCourses(); 
                syncAllDropdowns();
                loadStudentHierarchy(); // Refresh the main view
            } else alert(res.message);
        });
    }

    function loadCourses() {
        fetch('manage_students.php?ajax_action=1&type=course&action=fetch')
        .then(r => r.json()).then(data => {
            document.getElementById('c_list').innerHTML = data.map(c => {
                const yearsMap = {};
                c.structure.forEach(item => {
                    if(!yearsMap[item.year]) yearsMap[item.year] = [];
                    yearsMap[item.year].push(item.section_name);
                });
                let hierarchyHtml = '';
                for (const yr in yearsMap) {
                    hierarchyHtml += `
                        <div class="year-block" style="margin-bottom:12px;">
                            <span class="year-label">${yr}</span>
                            <div class="section-pills" style="display:flex; flex-wrap:wrap; gap:6px; margin-top:5px;">
                                ${yearsMap[yr].map(sec => `<span class="section-pill">${sec}</span>`).join('')}
                            </div>
                        </div>
                    `;
                }
                if(hierarchyHtml === '') hierarchyHtml = '<i style="color:gray; font-size:12px;">No sections mapped.</i>';
                return `
                <div class="list-manager-item" style="border:1px solid #eee; margin-bottom:10px; border-radius:10px; padding:15px;">
                    <div class="list-header" style="display:flex; justify-content:space-between; align-items:center;">
                        <span style="font-weight:700; color:#333;">${c.name} <small style="color:#888;">(${c.duration} Yrs)</small></span>
                        <div style="display:flex; gap:8px;">
    <button onclick="startEditCourse(${c.id}, '${c.name.replace(/'/g,"\\'")}') " style="color:#3498db; background:none; border:none; cursor:pointer;"><i class='bx bx-edit' style='font-size:20px;'></i></button>
</div>
                    </div>
                    <div id="courseEditArea_${c.id}" style="display:none; margin-top:10px;">
                        <input type="text" id="courseEditInput_${c.id}" value="${c.name.replace(/'/g,"\\'")} " style="width:100%; padding:8px 12px; border:2px solid #3498db; border-radius:8px; font-size:14px; box-sizing:border-box;">
                        <div style="display:flex; gap:8px; margin-top:8px;">
                            <button onclick="saveEditCourse(${c.id})" style="flex:1; background:#2d5016; color:white; border:none; padding:8px; border-radius:8px; font-weight:600; cursor:pointer;">Save</button>
                            <button onclick="cancelEditCourse(${c.id}, '${c.name.replace(/'/g,"\\'")} ')" style="flex:1; background:#eee; color:#333; border:none; padding:8px; border-radius:8px; font-weight:600; cursor:pointer;">Cancel</button>
                        </div>
                    </div>
                    <button class="view-info-btn" onclick="toggleHierarchy(this)">View Year and Section<i class='bx bx-chevron-down'></i></button>
                    <div class="hierarchy-details">${hierarchyHtml}</div>
                </div>
                `;
            }).join('');
        });
    }

    function toggleHierarchy(btn) {
        const details = btn.nextElementSibling;
        if(details.style.display === 'block') {
            details.style.display = 'none';
            btn.innerHTML = "View Year and Section <i class='bx bx-chevron-down'></i>";
        } else {
            details.style.display = 'block';
            btn.innerHTML = "Compress  <i class='bx bx-chevron-up'></i>";
        }
    }


    function deleteSectionItem(id) {
        if(!confirm("Delete this section?")) return;
        let fd = new FormData(); fd.append('id', id);
        fetch('manage_students.php?ajax_action=1&type=section&action=delete', { method: 'POST', body: fd })
        .then(() => {
            refreshSectionList(document.getElementById('ms_course').value, document.getElementById('ms_year').value);
            loadStudentHierarchy(); // Refresh the main view
        });
    }

    function openModal(id) { 
        document.getElementById(id).style.display = 'block'; 
        syncAllDropdowns(); 
    }
    
    function closeModal(id) { 
        document.getElementById(id).style.display = 'none';
        if(id === 'addStudentModal') {
            showAddStudentView();
        }
    }
    
    window.onclick = function(event) { 
        if (event.target.className === 'modal') { 
            event.target.style.display = 'none'; 
            if(event.target.id === 'addStudentModal') {
                showAddStudentView();
            }
        } 
    }

    // Initialize on page load
    document.addEventListener('DOMContentLoaded', function() {
        loadStudentHierarchy();
        syncAllDropdowns();
    });
    // ---- Edit Student ----
function openEditStudent(id, fullName, username, email, course, year, section) {
    document.getElementById('edit_student_id').value  = id;
    document.getElementById('edit_full_name').value   = fullName;
    document.getElementById('edit_username').value    = username;
    document.getElementById('edit_email').value       = email;
    document.getElementById('editStudentMsg').style.display = 'none';
    document.getElementById('editSectionWarning').style.display = 'none';

    // Store originals to detect changes
    document.getElementById('edit_student_id').dataset.origCourse   = course;
    document.getElementById('edit_student_id').dataset.origYear     = year;
    document.getElementById('edit_student_id').dataset.origSection  = section;

    // Populate course dropdown from synced data, then set value
    fetch('manage_students.php?ajax_action=1&type=course&action=fetch')
    .then(r => r.json())
    .then(data => {
        const el = document.getElementById('edit_course');
        el.innerHTML = '<option value="">Select Course</option>';
        data.forEach(c => {
            let opt = new Option(c.name, c.id);
            opt.dataset.duration = c.duration;
            opt.dataset.name = c.name;
            el.add(opt);
        });

        // Set the course value by matching course name
        for (let i = 0; i < el.options.length; i++) {
            if (el.options[i].dataset.name === course) {
                el.selectedIndex = i;
                break;
            }
        }

        // Populate year dropdown
        const dur = parseInt(el.options[el.selectedIndex]?.dataset.duration || 4);
        const yearEl = document.getElementById('edit_year');
        const labels = ["1st Year", "2nd Year", "3rd Year", "4th Year", "5th Year"];
        yearEl.innerHTML = '<option value="">Select Year</option>';
        for (let i = 0; i < dur; i++) yearEl.add(new Option(labels[i], labels[i]));
        yearEl.disabled = false;
        yearEl.classList.remove('disabled-field');
        yearEl.value = year;

        // Populate section dropdown
        const courseId = el.value;
        if (courseId && year) {
            fetch(`manage_students.php?ajax_action=1&type=section&action=fetch&course_id=${courseId}&year=${encodeURIComponent(year)}`)
            .then(r => r.json())
            .then(sections => {
                const secEl = document.getElementById('edit_section');
                secEl.innerHTML = '<option value="">Select Section</option>';
                sections.forEach(s => secEl.add(new Option(s.name, s.name)));
                secEl.disabled = false;
                secEl.classList.remove('disabled-field');
                secEl.value = section;
            });
        }
    });

    document.getElementById('editStudentModal').style.display = 'block';
}

function handleEditStudentFlow(step) {
    const c = document.getElementById('edit_course');
    const y = document.getElementById('edit_year');
    const s = document.getElementById('edit_section');

    // Show warning if any field changed
    const origCourse  = document.getElementById('edit_student_id').dataset.origCourse;
    const origYear    = document.getElementById('edit_student_id').dataset.origYear;
    const origSection = document.getElementById('edit_student_id').dataset.origSection;

    const warn = document.getElementById('editSectionWarning');

    if (step === 'course') {
        if (!c.value) {
            y.disabled = s.disabled = true;
            y.classList.add('disabled-field'); s.classList.add('disabled-field');
            return;
        }
        const dur = parseInt(c.options[c.selectedIndex].dataset.duration);
        const labels = ["1st Year", "2nd Year", "3rd Year", "4th Year", "5th Year"];
        y.innerHTML = '<option value="">Select Year</option>';
        for (let i = 0; i < dur; i++) y.add(new Option(labels[i], labels[i]));
        y.disabled = false; y.classList.remove('disabled-field');
        s.innerHTML = '<option value="">Select Year First</option>';
        s.disabled = true; s.classList.add('disabled-field');
    }
    else if (step === 'year') {
        if (!y.value) return;
        fetch(`manage_students.php?ajax_action=1&type=section&action=fetch&course_id=${c.value}&year=${encodeURIComponent(y.value)}`)
        .then(r => r.json())
        .then(data => {
            s.innerHTML = '<option value="">Select Section</option>';
            data.forEach(item => s.add(new Option(item.name, item.name)));
            s.disabled = false; s.classList.remove('disabled-field');
        });
    }

    // Check if anything changed from originals
    const courseChanged  = c.options[c.selectedIndex]?.dataset.name !== origCourse;
    const yearChanged    = y.value !== origYear;
    const sectionChanged = s.value !== origSection;
    warn.style.display = (courseChanged || yearChanged || sectionChanged) ? 'block' : 'none';
}

function saveEditStudent() {
    const id       = document.getElementById('edit_student_id').value;
    const fullName = document.getElementById('edit_full_name').value.trim();
    const username = document.getElementById('edit_username').value.trim();
    const email    = document.getElementById('edit_email').value.trim();
    const courseEl = document.getElementById('edit_course');
    const year     = document.getElementById('edit_year').value;
    const section  = document.getElementById('edit_section').value;
    const course   = courseEl.options[courseEl.selectedIndex]?.dataset.name || '';

    const msgEl = document.getElementById('editStudentMsg');

    if (!fullName || !username || !email || !course || !year || !section) {
        msgEl.textContent = '⚠️ All fields are required.';
        msgEl.style.cssText = 'display:block; background:#fff3cd; color:#856404; border-color:#ffc107; padding:12px; border-radius:8px; margin-bottom:15px; font-weight:500; border-left:5px solid;';
        return;
    }

    const btn = document.getElementById('saveEditStudentBtn');
    btn.disabled = true;
    btn.innerHTML = '<i class="bx bx-loader-alt"></i> Saving...';

    const fd = new FormData();
    fd.append('ajax_action', 'edit_student');
    fd.append('edit_id',    id);
    fd.append('full_name',  fullName);
    fd.append('username',   username);
    fd.append('email',      email);
    fd.append('course',     course);
    fd.append('year',       year);
    fd.append('section',    section);

    fetch('manage_students.php', { method: 'POST', body: fd })
    .then(r => r.json())
    .then(res => {
        if (res.success) {
            msgEl.textContent = res.section_changed
                ? `✅ Student updated! ${res.drafts_cleared > 0 ? res.drafts_cleared + ' stale draft(s) cleared.' : 'No drafts were affected.'}`
                : '✅ Student updated successfully!';
            msgEl.style.cssText = 'display:block; background:#e8f5e9; color:#2e7d32; border-color:#2e7d32; padding:12px; border-radius:8px; margin-bottom:15px; font-weight:500; border-left:5px solid;';

            // Refresh hierarchy after short delay
            setTimeout(() => {
                closeModal('editStudentModal');
                loadStudentHierarchy();
            }, 1500);
        } else {
            msgEl.textContent = '❌ ' + res.message;
            msgEl.style.cssText = 'display:block; background:#ffebee; color:#c62828; border-color:#c62828; padding:12px; border-radius:8px; margin-bottom:15px; font-weight:500; border-left:5px solid;';
            btn.disabled = false;
            btn.innerHTML = '<i class="bx bx-save"></i> Save Changes';
        }
    })
    .catch(() => {
        msgEl.textContent = '❌ Network error. Please try again.';
        msgEl.style.cssText = 'display:block; background:#ffebee; color:#c62828; border-color:#c62828; padding:12px; border-radius:8px; margin-bottom:15px; font-weight:500; border-left:5px solid;';
        btn.disabled = false;
        btn.innerHTML = '<i class="bx bx-save"></i> Save Changes';
    });
}
function startEditCourse(id, currentName) {
    document.getElementById(`courseEditArea_${id}`).style.display = 'block';
    document.getElementById(`courseEditInput_${id}`).focus();
}

function cancelEditCourse(id, originalName) {
    document.getElementById(`courseEditArea_${id}`).style.display = 'none';
    document.getElementById(`courseEditInput_${id}`).value = originalName;
}

function saveEditCourse(id) {
    const newName = document.getElementById(`courseEditInput_${id}`).value.trim();
    if (!newName) { alert('Course name cannot be empty.'); return; }

    const saveBtn = document.querySelector(`#courseEditArea_${id} button`);
    if (saveBtn) { saveBtn.disabled = true; saveBtn.textContent = 'Saving...'; }

    let fd = new FormData();
    fd.append('id', id);
    fd.append('name', newName);

    fetch('rename_course.php', { method: 'POST', body: fd })
    .then(r => r.json())
    .then(res => {
        if (res.success) {
            // Show a toast notification with full details
            showRenameToast(res);
            loadCourses();
            syncAllDropdowns();
            loadStudentHierarchy();
        } else {
            alert('❌ Error: ' + res.message);
            if (saveBtn) { saveBtn.disabled = false; saveBtn.textContent = 'Save'; }
        }
    })
    .catch(() => {
        alert('❌ Request failed. Please try again.');
        if (saveBtn) { saveBtn.disabled = false; saveBtn.textContent = 'Save'; }
    });
}

function showRenameToast(res) {
    // Remove existing toast if any
    const existing = document.getElementById('renameToast');
    if (existing) existing.remove();

    const lines = [
        `✅ Course renamed: <strong>"${res.old_name}"</strong> → <strong>"${res.new_name}"</strong>`,
        `👨‍🎓 ${res.students_updated} student(s) updated`,
        `📄 ${res.apps_updated} application(s) updated`,
        `🏫 Program heads & class advisers updated`,
    ];

    const toast = document.createElement('div');
    toast.id = 'renameToast';
    toast.innerHTML = lines.join('<br>');
    toast.style.cssText = `
        position: fixed;
        bottom: 30px;
        right: 30px;
        background: #1a3409;
        color: white;
        padding: 18px 24px;
        border-radius: 12px;
        font-size: 14px;
        line-height: 1.8;
        box-shadow: 0 8px 25px rgba(0,0,0,0.3);
        z-index: 9999;
        animation: slideInRight 0.4s ease;
        max-width: 360px;
    `;

    // Add animation keyframes if not already present
    if (!document.getElementById('toastStyle')) {
        const style = document.createElement('style');
        style.id = 'toastStyle';
        style.textContent = `
            @keyframes slideInRight {
                from { transform: translateX(120%); opacity: 0; }
                to   { transform: translateX(0);    opacity: 1; }
            }
        `;
        document.head.appendChild(style);
    }

    document.body.appendChild(toast);

    // Auto-dismiss after 5 seconds
    setTimeout(() => {
        toast.style.transition = 'opacity 0.5s ease';
        toast.style.opacity = '0';
        setTimeout(() => toast.remove(), 500);
    }, 5000);
}
function createSectionForOrphans(courseName, year, btn) {
    btn.disabled = true;
    btn.innerHTML = "<i class='bx bx-loader-alt'></i> Creating...";

    const fd = new FormData();
    fd.append('course_name', courseName);
    fd.append('year', year);

    fetch('reassign_section.php', { method: 'POST', body: fd })
    .then(r => r.json())
    .then(res => {
        if (res.success) {
            alert(`✅ ${res.message}`);
            loadStudentHierarchy();
        } else {
            alert('❌ ' + res.message);
            btn.disabled = false;
            btn.innerHTML = "<i class='bx bx-plus-circle'></i> Create New Section &amp; Move Students";
        }
    })
    .catch(() => {
        alert('❌ Network error. Please try again.');
        btn.disabled = false;
        btn.innerHTML = "<i class='bx bx-plus-circle'></i> Create New Section &amp; Move Students";
    });
}
function resetStudentPassword() {
    const studentId = document.getElementById('edit_student_id').value;
    const studentName = document.getElementById('edit_full_name').value;

    if (!studentId) {
        alert('No student selected.');
        return;
    }

    if (!confirm(`Reset password for "${studentName}" to default (@Student01)?\n\nThe student will need to use this password on their next login.`)) {
        return;
    }

    const msgEl = document.getElementById('resetPasswordMsg');
    msgEl.style.display = 'none';

    const fd = new FormData();
    fd.append('ajax_action', 'reset_password');
    fd.append('student_id', studentId);

    fetch('manage_students.php', { method: 'POST', body: fd })
    .then(r => r.json())
    .then(res => {
        msgEl.style.display = 'block';
        if (res.success) {
            msgEl.textContent = '✅ ' + res.message;
            msgEl.style.cssText = 'display:block; background:#d4edda; color:#155724; border:1px solid #c3e6cb; padding:8px 12px; border-radius:6px; font-size:13px; font-weight:500; margin-top:10px;';
        } else {
            msgEl.textContent = '❌ ' + res.message;
            msgEl.style.cssText = 'display:block; background:#f8d7da; color:#721c24; border:1px solid #f5c6cb; padding:8px 12px; border-radius:6px; font-size:13px; font-weight:500; margin-top:10px;';
        }
        // Auto-hide after 3 seconds
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