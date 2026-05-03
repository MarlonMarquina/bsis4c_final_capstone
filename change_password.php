<?php
session_start();
header('Content-Type: application/json');

if (!isset($_SESSION['role']) || $_SESSION['role'] != "student") {
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit();
}

include 'conn.php';

$input = json_decode(file_get_contents('php://input'), true);
$current_password = $input['current_password'] ?? '';
$new_password = $input['new_password'] ?? '';
$username = $_SESSION['username'];

if (empty($current_password) || empty($new_password)) {
    echo json_encode(['success' => false, 'message' => 'All fields are required']);
    exit();
}

// Verify current password
$stmt = $conn->prepare("SELECT password FROM users WHERE username = ?");
$stmt->bind_param("s", $username);
$stmt->execute();
$result = $stmt->get_result();
$user = $result->fetch_assoc();
$stmt->close();

if (!$user) {
    echo json_encode(['success' => false, 'message' => 'User not found']);
    exit();
}

// Check if current password is correct
if (!password_verify($current_password, $user['password'])) {
    echo json_encode(['success' => false, 'message' => 'Current password is incorrect']);
    exit();
}

// Hash new password
$new_password_hash = password_hash($new_password, PASSWORD_DEFAULT);

// Update password
$stmt = $conn->prepare("UPDATE users SET password = ? WHERE username = ?");
$stmt->bind_param("ss", $new_password_hash, $username);

if ($stmt->execute()) {
    echo json_encode(['success' => true, 'message' => 'Password changed successfully']);
} else {
    echo json_encode(['success' => false, 'message' => 'Failed to update password']);
}

$stmt->close();
$conn->close();
?>