<?php
// Tiyakin na naka-start ang session para ma-access ang $_SESSION['role']
session_start();

// I-include ang database connection file
include 'conn.php'; 

// Itakda ang header para magbalik ng JSON response
header('Content-Type: application/json');

// --- 1. ACCESS CONTROL CHECK ---
if (!isset($_SESSION['role']) || $_SESSION['role'] != "admin") {
    // Magbalik ng 403 Forbidden status code kung walang karapatan
    http_response_code(403); 
    echo json_encode(['success' => false, 'message' => 'Error: Access denied. Please ensure you are logged in as Admin.']);
    exit();
}
// ---------------------------------

$type = $_REQUEST['type'] ?? '';
$action = $_REQUEST['action'] ?? '';

// Check kung tama ang 'type' (course o section)
if (!in_array($type, ['course', 'section'])) {
    echo json_encode(['success' => false, 'message' => 'Invalid option type specified.']);
    exit();
}

// I-define ang table name at column name based sa 'type'
$table = $type . 's'; // 'courses' or 'sections'
$column = $type . '_name'; // 'course_name' or 'section_name'


// --- 2. FETCH Action (GET) ---
if ($action === 'fetch') {
    // Kumuha ng ID at Name ng lahat ng options
    $result = $conn->query("SELECT id, $column AS name FROM $table ORDER BY $column ASC");
    $data = [];
    while ($row = $result->fetch_assoc()) {
        $data[] = $row;
    }
    echo json_encode($data);
    exit();
}


// --- 3. POST Actions (Add/Delete) ---
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    
    // --- ADD Action ---
    if ($action === 'add') {
        $name = trim($_POST['name'] ?? '');
        if (empty($name)) {
            echo json_encode(['success' => false, 'message' => 'Name cannot be empty.']);
            exit();
        }
        
        // Gamitin ang prepared statement para maiwasan ang SQL injection
        $stmt = $conn->prepare("INSERT INTO $table ($column) VALUES (?)");
        $stmt->bind_param("s", $name);
        
        if ($stmt->execute()) {
            echo json_encode(['success' => true, 'message' => "$type added successfully."]);
        } else {
            // Error handling para sa duplicate entry (MySQL error code 1062)
            if ($conn->errno == 1062) {
                 echo json_encode(['success' => false, 'message' => "This $type already exists."]);
            } else {
                 echo json_encode(['success' => false, 'message' => "Database error: " . $stmt->error]);
            }
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
        
        // Gamitin ang prepared statement
        $stmt = $conn->prepare("DELETE FROM $table WHERE id = ?");
        $stmt->bind_param("i", $id);
        
        if ($stmt->execute()) {
            echo json_encode(['success' => true, 'message' => "$type deleted successfully."]);
        } else {
            echo json_encode(['success' => false, 'message' => "Error deleting $type: " . $stmt->error]);
        }
        $stmt->close();
        exit();
    }
}

// Default response if no action is matched
echo json_encode(['success' => false, 'message' => 'Invalid or missing action in request.']);
?>