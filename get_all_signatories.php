<?php
include 'conn.php';
session_start();

// Security check
if (!isset($_SESSION['role']) || $_SESSION['role'] != "admin") {
    http_response_code(403);
    echo json_encode(['error' => 'Unauthorized']);
    exit();
}

header('Content-Type: application/json');

$query = "SELECT id, username, full_name, email, signatory_type, department, year, section 
          FROM users 
          WHERE role = 'signatory' 
          ORDER BY signatory_type ASC, full_name ASC";

$result = $conn->query($query);

$signatories = [];
while ($row = $result->fetch_assoc()) {
    $signatories[] = $row;
}

echo json_encode($signatories);
?>