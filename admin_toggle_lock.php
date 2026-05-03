<?php
session_start();
include 'conn.php';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;
require 'vendor/autoload.php';

if (!isset($_SESSION['role']) || $_SESSION['role'] != 'admin') {
    echo json_encode(['success' => false, 'message' => 'Unauthorized']); exit();
}

$admin_user = $_SESSION['username'];

// Check can_add_admin permission
$cap_stmt = $conn->prepare("SELECT can_add_admin, email FROM users WHERE username = ? AND role = 'admin'");
$cap_stmt->bind_param("s", $admin_user);
$cap_stmt->execute();
$cap_row = $cap_stmt->get_result()->fetch_assoc();
$cap_stmt->close();

if (!$cap_row || $cap_row['can_add_admin'] != 1) {
    echo json_encode(['success' => false, 'message' => 'You do not have permission to toggle this setting.']); exit();
}

$action = $_POST['action'] ?? '';

// SEND OTP
if ($action === 'send_otp') {
    $otp = strval(rand(100000, 999999));
    $_SESSION['lock_otp'] = $otp;
    $_SESSION['lock_otp_expires'] = time() + 300;

    try {
        $mail = new PHPMailer(true);
        $mail->isSMTP();
        $mail->Host       = 'smtp.gmail.com';
        $mail->SMTPAuth   = true;
        $mail->Username   = 'clearancebpc@gmail.com';
        $mail->Password   = 'powe wgem hlsv ybyq';
        $mail->SMTPSecure = 'tls';
        $mail->Port       = 587;

        $mail->setFrom('clearancebpc@gmail.com', 'BPC Clearance System');
        $mail->addAddress($cap_row['email']);
        $mail->isHTML(true);
        $mail->Subject = '🔒 Requirement Lock OTP';
        $mail->Body = '
        <div style="font-family:Arial,sans-serif; max-width:500px; margin:0 auto;">
            <div style="background:linear-gradient(135deg,#2d5016,#1a3409); padding:25px; text-align:center; border-radius:12px 12px 0 0;">
                <h2 style="color:white; margin:0;">🔒 Requirement Lock OTP</h2>
            </div>
            <div style="background:#f9f9f9; padding:25px; border:1px solid #ddd; border-radius:0 0 12px 12px;">
                <p>Your OTP to toggle the requirement lock is:</p>
                <h1 style="text-align:center; color:#2d5016; letter-spacing:10px;">' . $otp . '</h1>
                <p style="color:#888; font-size:12px;">This OTP expires in 5 minutes.</p>
            </div>
        </div>';
        $mail->send();
        echo json_encode(['success' => true]);
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'message' => 'Failed to send OTP: ' . $mail->ErrorInfo]);
    }
    exit();
}

// VERIFY OTP AND TOGGLE
if ($action === 'verify_and_toggle') {
    $otp_input = trim($_POST['otp'] ?? '');

    if (empty($_SESSION['lock_otp']) || time() > ($_SESSION['lock_otp_expires'] ?? 0)) {
        echo json_encode(['success' => false, 'message' => 'OTP expired. Please request a new one.']); exit();
    }

    if ($otp_input !== $_SESSION['lock_otp']) {
        echo json_encode(['success' => false, 'message' => 'Invalid OTP.']); exit();
    }

    // Get current lock status
$get_stmt = $conn->prepare("SELECT requirement_lock FROM system_settings WHERE id = 1");
$get_stmt->execute();
$current = $get_stmt->get_result()->fetch_assoc()['requirement_lock'] ?? 0;
$get_stmt->close();

$new_value = ($current == 1) ? 0 : 1;

$upd_stmt = $conn->prepare("UPDATE system_settings SET requirement_lock = ? WHERE id = 1");
$upd_stmt->bind_param("i", $new_value);
$upd_stmt->execute();
$upd_stmt->close();

// Also fix the json response to return string for consistency
echo json_encode(['success' => true, 'new_status' => strval($new_value)]);
exit();

    unset($_SESSION['lock_otp'], $_SESSION['lock_otp_expires']);

    echo json_encode(['success' => true, 'new_status' => $new_value]);
    exit();
}

echo json_encode(['success' => false, 'message' => 'Invalid action.']);
?>