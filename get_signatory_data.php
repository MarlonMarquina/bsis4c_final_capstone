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

$id = intval($_GET['id'] ?? 0);

if (!$id) {
    echo json_encode(['error' => 'Invalid ID']);
    exit();
}

$stmt = $conn->prepare("SELECT id, username, full_name, email, signatory_type, department, year, section FROM users WHERE id = ? AND role = 'signatory'");
$stmt->bind_param("i", $id);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows !== 1) {
    echo json_encode(['error' => 'Signatory not found']);
    exit();
}

$data = $result->fetch_assoc();
echo json_encode($data);
?>