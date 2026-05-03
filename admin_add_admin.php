<?php
// FILE: admin_add_admin.php
session_start();
include 'conn.php';
header('Content-Type: application/json');

if (!isset($_SESSION['role']) || $_SESSION['role'] != 'admin') {
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

// Check if current admin can add admins
$check = $conn->prepare("SELECT can_add_admin FROM users WHERE username = ? AND role = 'admin'");
$check->bind_param("s", $_SESSION['username']);
$check->execute();
$me = $check->get_result()->fetch_assoc();
$check->close();

if (!$me || $me['can_add_admin'] != 1) {
    echo json_encode(['success' => false, 'message' => 'You do not have permission to add admins.']);
    exit;
}

$input     = json_decode(file_get_contents('php://input'), true);
$full_name = trim($input['full_name'] ?? '');
$username  = trim($input['username'] ?? '');
$email     = trim($input['email'] ?? '');
$password  = trim($input['password'] ?? '');

if (empty($full_name) || empty($username) || empty($email) || empty($password)) {
    echo json_encode(['success' => false, 'message' => 'All fields are required.']);
    exit;
}

if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    echo json_encode(['success' => false, 'message' => 'Invalid email format.']);
    exit;
}

if (!preg_match('/^[a-zA-Z0-9_]{4,20}$/', $username)) {
    echo json_encode(['success' => false, 'message' => 'Username must be 4-20 characters (letters, numbers, underscore).']);
    exit;
}

if (strlen($password) < 8) {
    echo json_encode(['success' => false, 'message' => 'Password must be at least 8 characters.']);
    exit;
}

// Check if username or email already exists
$dup = $conn->prepare("SELECT username FROM users WHERE username = ? OR email = ?");
$dup->bind_param("ss", $username, $email);
$dup->execute();
if ($dup->get_result()->num_rows > 0) {
    echo json_encode(['success' => false, 'message' => 'Username or email already exists.']);
    $dup->close();
    exit;
}
$dup->close();

$hashed = password_hash($password, PASSWORD_DEFAULT);

// can_add_admin = 0 for new admins
$insert = $conn->prepare("
    INSERT INTO users (username, full_name, email, password, role, status, can_add_admin, final_clearance_status)
    VALUES (?, ?, ?, ?, 'admin', 'active', 0, 'not_requested')
");
$insert->bind_param("ssss", $username, $full_name, $email, $hashed);

if ($insert->execute()) {
    echo json_encode(['success' => true, 'message' => 'Admin account created successfully.']);
} else {
    echo json_encode(['success' => false, 'message' => 'Database error: ' . $insert->error]);
}
$insert->close();
?>