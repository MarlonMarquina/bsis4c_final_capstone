<?php
/**
 * FILE: get_all_users.php
 */
include 'conn.php';
session_start();

if (!isset($_SESSION['role']) || $_SESSION['role'] != "admin") {
    http_response_code(403);
    echo json_encode(['error' => 'Unauthorized']);
    exit();
}

header('Content-Type: application/json');

try {
    $sql = "SELECT 
                username, 
                full_name, 
                email, 
                course,
                year, 
                section, 
                role, 
                signatory_type, 
                department, 
                status, 
                final_clearance_status, 
                admin_approved,
                admin_messaged,
                admin_message_text,
                student_id,
                can_add_admin
            FROM users 
            ORDER BY 
                CASE role
                    WHEN 'admin' THEN 1
                    WHEN 'signatory' THEN 2
                    WHEN 'student' THEN 3
                    ELSE 4
                END,
                full_name ASC";
    
    $result = $conn->query($sql);
    
    if (!$result) {
        throw new Exception("Database query failed: " . $conn->error);
    }
    
    $users = [];
    while ($row = $result->fetch_assoc()) {
        $users[] = $row;
    }
    
    echo json_encode($users);
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Server error: ' . $e->getMessage()]);
}
?>