<?php
session_start();
date_default_timezone_set('Asia/Manila');

if (!isset($_SESSION['role']) || $_SESSION['role'] != "signatory") {
    header("Location: login.php");
    exit();
}

include 'conn.php';

$signatory_name = $_SESSION['username'];
$reviewed_at    = date('Y-m-d H:i:s');

if (!isset($_POST['app_id'])) {
    header("Location: signatory_history.php");
    exit();
}

$app_id = intval($_POST['app_id']);

// Fetch the application — must belong to this signatory and be Totally Rejected
$fetch = $conn->prepare("
    SELECT a.id, a.username, a.document, a.course, u.email, u.full_name, rl.requirement_name
    FROM applications a
    LEFT JOIN users u ON a.username = u.username
    LEFT JOIN requirement_library rl ON a.requirement_id = rl.id
    WHERE a.id = ? AND a.signatory = ? AND a.status = 'Totally Rejected'
    LIMIT 1
");
$fetch->bind_param("is", $app_id, $signatory_name);
$fetch->execute();
$app = $fetch->get_result()->fetch_assoc();
$fetch->close();

if (!$app) {
    header("Location: signatory_history.php?error=not_found");
    exit();
}

// Update status to Approved
$update = $conn->prepare("
    UPDATE applications 
    SET status = 'Approved', reviewed_at = ?, rejection_reason = NULL, rejection_count = 0
    WHERE id = ? AND signatory = ? AND status = 'Totally Rejected'
");
$update->bind_param("sis", $reviewed_at, $app_id, $signatory_name);
$update->execute();
$affected = $update->affected_rows;
$update->close();

if ($affected > 0) {
    // Log to signatory_history
    $log = $conn->prepare("
        INSERT INTO signatory_history (application_id, signatory_username, student_user, action, reason, remarks)
        VALUES (?, ?, ?, 'Approved', 'Overturned — manually approved by signatory', ?)
    ");
    $log->bind_param("isss", $app_id, $signatory_name, $app['username'], $app['document']);
    $log->execute();
    $log->close();

    // Send in-app notification to student
    $msg = "Your application (ID: {$app_id}) for {$app['requirement_name']} has been manually approved by your signatory after review.";
    $notif = $conn->prepare("
        INSERT INTO notifications (username, message, type, created_at)
        VALUES (?, ?, 'success', ?)
    ");
    $notif->bind_param("sss", $app['username'], $msg, $reviewed_at);
    $notif->execute();
    $notif->close();
}

$conn->close();
header("Location: signatory_history.php?success=overturned");
exit();