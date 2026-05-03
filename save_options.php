<?php
// FILE: save_options.php
session_start();
include 'conn.php'; 

// Itakda ang header para magbalik ng JSON response
header('Content-Type: application/json');

// --- 1. ACCESS CONTROL CHECK ---
if (!isset($_SESSION['role']) || $_SESSION['role'] != "admin") {
    http_response_code(403); 
    echo json_encode(['success' => false, 'message' => 'Error: Access denied. Please ensure you are logged in as Admin.']);
    exit();
}

$type = $_POST['type'] ?? '';
$action = $_POST['action'] ?? '';

// Check kung tama ang 'type' (course o section)
if (!in_array($type, ['course', 'section'])) {
    echo json_encode(['success' => false, 'message' => 'Invalid option type specified.']);
    exit();
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Invalid request method. Only POST is allowed.']);
    exit();
}

$table = $type . 's'; // 'courses' or 'sections'
$column = $type . '_name'; // 'course_name' or 'section_name'


// --- ADD Action ---
if ($action === 'add') {
    $name = trim($_POST['name'] ?? '');
    if (empty($name)) {
        echo json_encode(['success' => false, 'message' => 'Name cannot be empty.']);
        exit();
    }
    
    $sql = "INSERT INTO $table ($column) VALUES (?)";
    $params = ["s", $name]; 
    
    if ($type === 'section') {
        $course_id = $_POST['course_id'] ?? null;
        $year = $_POST['year'] ?? null;
        
        if (empty($course_id) || empty($year)) {
             echo json_encode(['success' => false, 'message' => 'Course ID and Year are required to add a section.']);
             exit();
        }
        
        // Check for duplicate (section_name + course_id + year should be unique)
        $check = $conn->prepare("SELECT id FROM $table WHERE $column = ? AND course_id = ? AND year = ?");
        if ($check === false) {
             echo json_encode(['success' => false, 'message' => "Prepare failed (Duplicate Check): " . $conn->error]);
             exit();
        }
        $check->bind_param("sis", $name, $course_id, $year);
        $check->execute();
        if ($check->get_result()->num_rows > 0) {
            echo json_encode(['success' => false, 'message' => "The section '$name' already exists for this course and year."]);
            $check->close();
            exit();
        }
        $check->close();

        // Insert Query: name, course_id, year
        $sql = "INSERT INTO $table ($column, course_id, year) VALUES (?, ?, ?)";
        $params = ["sis", $name, $course_id, $year]; 
    }
    
    $stmt = $conn->prepare($sql);
    if ($stmt === false) {
         echo json_encode(['success' => false, 'message' => "Prepare failed (Insert): " . $conn->error]);
         exit();
    }
    
    // Helper function for dynamic binding
    call_user_func_array([$stmt, 'bind_param'], refValues(array_merge([$params[0]], array_slice($params, 1))));

    if ($stmt->execute()) {
        echo json_encode(['success' => true, 'message' => "$type added successfully."]);
    } else {
         echo json_encode(['success' => false, 'message' => "Database error (Execute): " . $stmt->error]);
    }
    $stmt->close();
    exit();
}


// --- UPDATE Action (Course ONLY) ---
if ($action === 'update' && $type === 'course') {
    $id = $_POST['id'] ?? null;
    $name = trim($_POST['name'] ?? '');
    
    if (empty($id) || empty($name)) {
        echo json_encode(['success' => false, 'message' => 'ID and name are required for update.']);
        exit();
    }
    
    $stmt = $conn->prepare("UPDATE courses SET course_name = ? WHERE id = ?");
    
    if ($stmt === false) {
         echo json_encode(['success' => false, 'message' => "Prepare failed (Update): " . $conn->error]);
         exit();
    }
    
    $stmt->bind_param("si", $name, $id);
    
    if ($stmt->execute()) {
        echo json_encode(['success' => true, 'message' => "Course updated successfully."]);
    } else {
        echo json_encode(['success' => false, 'message' => "Error updating course: " . $stmt->error]);
    }
    $stmt->close();
    exit();
}


// --- DELETE Action ---
if ($action === 'delete') {
    $id = $_POST['id'] ?? null;
    if (empty($id)) {
        echo json_encode(['success' => false, 'message' => 'ID is required for deletion.']);
        exit();
    }
    
    $stmt = $conn->prepare("DELETE FROM $table WHERE id = ?");
    
    if ($stmt === false) {
         echo json_encode(['success' => false, 'message' => "Prepare failed (Delete): " . $conn->error]);
         exit();
    }
    
    $stmt->bind_param("i", $id);
    
    if ($stmt->execute()) {
        echo json_encode(['success' => true, 'message' => "$type deleted successfully."]);
    } else {
        if ($conn->errno == 1451) { 
            echo json_encode(['success' => false, 'message' => "Cannot delete this $type because it is currently linked to one or more students/records. Please update or delete the linked records first."]);
        } else {
            echo json_encode(['success' => false, 'message' => "Error deleting $type: " . $stmt->error]);
        }
    }
    $stmt->close();
    exit();
}

// Helper function para sa bind_param
function refValues($arr){
    if (strnatcmp(phpversion(),'5.3') >= 0) //PHP 5.3 or higher
    {
        $refs = array();
        foreach($arr as $key => $value)
            $refs[$key] = &$arr[$key];
        return $refs;
    }
    return $arr;
}

// Default response if no action is matched
echo json_encode(['success' => false, 'message' => 'Invalid or missing action in request.']);
?>