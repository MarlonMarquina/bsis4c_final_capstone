<?php
// FILE: admin_send_message.php
// Saves admin message and emails it to the student

ini_set('display_errors', 0);
require 'vendor/autoload.php';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

session_start();
include 'conn.php';

header('Content-Type: application/json');

if (!isset($_SESSION['role']) || $_SESSION['role'] != 'admin') {
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

$input = json_decode(file_get_contents('php://input'), true);
$student_username = trim($input['username'] ?? '');
$message_text     = trim($input['message'] ?? '');

if (empty($student_username) || empty($message_text)) {
    echo json_encode(['success' => false, 'message' => 'Username and message are required.']);
    exit;
}

// Get student email and name
$stmt = $conn->prepare("SELECT full_name, email, admin_approved, final_clearance_status FROM users WHERE username = ? AND role = 'student'");
$stmt->bind_param("s", $student_username);
$stmt->execute();
$student = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$student || empty($student['email'])) {
    echo json_encode(['success' => false, 'message' => 'Student not found or has no email.']);
    exit;
}

// Save message to database
$update = $conn->prepare("
    UPDATE users 
    SET admin_messaged = 1, admin_message_text = ?, admin_message_sent_at = NOW()
    WHERE username = ? AND role = 'student'
");
$update->bind_param("ss", $message_text, $student_username);
if (!$update->execute()) {
    echo json_encode(['success' => false, 'message' => 'Database error: ' . $update->error]);
    $update->close();
    exit;
}
$update->close();

// Send email via PHPMailer
$smtpHost     = 'smtp.gmail.com';
$smtpUsername = 'clearancebpc@gmail.com';
$smtpPassword = 'powe wgem hlsv ybyq';
$smtpPort     = 587;

$mail = new PHPMailer(true);
try {
    $mail->isSMTP();
    $mail->Host       = $smtpHost;
    $mail->SMTPAuth   = true;
    $mail->Username   = $smtpUsername;
    $mail->Password   = $smtpPassword;
    $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
    $mail->Port       = $smtpPort;

    $mail->setFrom($smtpUsername, 'Smart Clearance System');
    $mail->addAddress($student['email'], $student['full_name']);

    $mail->isHTML(true);
    $mail->Subject = 'Clearance Notice from Admin — Smart Clearance System';
    $mail->Body    = "
        <div style='font-family: Arial, sans-serif; max-width: 600px; margin: auto;'>
            <div style='background: linear-gradient(135deg, #2d5016, #1a3409); padding: 25px; border-radius: 10px 10px 0 0;'>
                <h2 style='color: white; margin: 0;'>📋 Clearance Notice</h2>
            </div>
            <div style='background: #f9f9f9; padding: 25px; border-radius: 0 0 10px 10px; border: 1px solid #ddd;'>
                <p>Dear <strong>{$student['full_name']}</strong>,</p>
                <p>The admin has sent you the following message regarding your clearance:</p>
                <div style='background: white; border-left: 4px solid #2d5016; padding: 15px 20px; border-radius: 5px; margin: 15px 0;'>
                    " . nl2br(htmlspecialchars($message_text)) . "
                </div>
                <p style='color: #666; font-size: 13px; margin-top: 20px;'>
                    Please log in to the Smart Clearance System to check on your clearance status.<br>
                    Do not reply to this email.
                </p>
            </div>
        </div>
    ";
    $mail->AltBody = "Dear {$student['full_name']},\n\nAdmin message:\n{$message_text}\n\nPlease log in to the Smart Clearance System to check your clearance status.";

    $mail->send();

    // Insert notification based on student's current state
   if ($student['admin_approved'] == 1) {
    $notif_label = 'Follow-up from Admin:';
} elseif ($student['final_clearance_status'] === 'pending') {
    $notif_label = 'Pending Notice from Admin:';
} else {
    $notif_label = 'Reminder from Admin:';
}
    $notif_message = $notif_label . ' ' . $message_text;
    $notif_stmt = $conn->prepare("INSERT INTO notifications (username, message, created_at, is_read) VALUES (?, ?, NOW(), 0)");
    $notif_stmt->bind_param("ss", $student_username, $notif_message);
    $notif_stmt->execute();
    $notif_stmt->close();

    echo json_encode(['success' => true, 'message' => 'Message sent to student successfully.']);

} catch (Exception $e) {
    // Email failed but DB was already saved — still report partial success
    echo json_encode(['success' => true, 'message' => 'Message saved but email failed: ' . $mail->ErrorInfo]);
}
?>