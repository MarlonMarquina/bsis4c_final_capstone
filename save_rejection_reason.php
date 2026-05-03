<?php
session_start();
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'signatory') {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Unauthorized.']);
    exit;
}

include 'conn.php';
header('Content-Type: application/json');

$input = json_decode(file_get_contents('php://input'), true);

$category         = trim($input['category']    ?? '');
$reason_text      = trim($input['reason_text'] ?? '');
$requires_reupload = isset($input['requires_reupload']) ? (int)$input['requires_reupload'] : 0;

if (empty($category) || empty($reason_text)) {
    echo json_encode(['success' => false, 'message' => 'Category and reason text are required.']);
    exit;
}

// Get signatory ID
$stmt = $conn->prepare("SELECT id FROM users WHERE username = ? AND role = 'signatory'");
$stmt->bind_param("s", $_SESSION['username']);
$stmt->execute();
$user = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$user) {
    echo json_encode(['success' => false, 'message' => 'Signatory not found.']);
    exit;
}

$signatory_id = $user['id'];

// Check for duplicate for this signatory
$chk = $conn->prepare(
    "SELECT id FROM rejection_reasons WHERE signatory_id = ? AND reason_text = ? AND category = ?"
);
$chk->bind_param("iss", $signatory_id, $reason_text, $category);
$chk->execute();
if ($chk->get_result()->num_rows > 0) {
    $chk->close();
    echo json_encode(['success' => false, 'message' => 'This reason already exists in your list.']);
    exit;
}
$chk->close();

$ins = $conn->prepare(
    "INSERT INTO rejection_reasons (signatory_id, reason_text, category, requires_reupload) VALUES (?, ?, ?, ?)"
);
$ins->bind_param("issi", $signatory_id, $reason_text, $category, $requires_reupload);

if ($ins->execute()) {
    echo json_encode(['success' => true, 'id' => $conn->insert_id]);
} else {
    echo json_encode(['success' => false, 'message' => 'Database error: ' . $ins->error]);
}
$ins->close();
?>