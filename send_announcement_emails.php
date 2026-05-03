<?php
require_once 'conn.php';
require_once 'vendor/autoload.php';
use PHPMailer\PHPMailer\PHPMailer;

$title   = $argv[1] ?? '';
$content = $argv[2] ?? '';
$poster  = $argv[3] ?? '';

if (!$title) exit;

$emails_stmt = $conn->query("SELECT email, full_name FROM users WHERE role = 'student' AND status = 'active' AND email != ''");

$mail = new PHPMailer(true);
$mail->isSMTP();
$mail->Host       = 'smtp.gmail.com';
$mail->SMTPAuth   = true;
$mail->Username   = 'clearancebpc@gmail.com';
$mail->Password   = 'powe wgem hlsv ybyq';
$mail->SMTPSecure = 'tls';
$mail->Port       = 587;
$mail->SMTPKeepAlive = true;
$mail->setFrom('clearancebpc@gmail.com', 'BPC Clearance System');
$mail->isHTML(true);

while ($recipient = $emails_stmt->fetch_assoc()) {
    try {
        $mail->clearAddresses();
        $mail->addAddress($recipient['email'], $recipient['full_name']);
        $mail->Subject = ' New Announcement: ' . $title;
        $mail->Body = '
        <div style="font-family:Arial,sans-serif;max-width:600px;margin:0 auto;border-radius:12px;overflow:hidden;box-shadow:0 4px 20px rgba(0,0,0,0.1);">
            <div style="background:linear-gradient(135deg,#2d5016,#1a3409);padding:30px;text-align:center;">
                <h1 style="color:white;margin:0;font-size:22px;">📢 New Announcement</h1>
                <p style="color:rgba(255,255,255,0.85);margin:8px 0 0;">BPC Clearance System</p>
            </div>
            <div style="padding:30px;background:white;">
                <h2 style="color:#2d5016;margin-top:0;">' . htmlspecialchars($title) . '</h2>
                <p style="font-size:14px;color:#555;line-height:1.7;">' . nl2br(htmlspecialchars($content)) . '</p>
                <div style="background:#f0fff4;border-left:4px solid #27ae60;border-radius:6px;padding:12px;margin-top:20px;font-size:13px;color:#155724;">
                    Posted by: <strong>' . htmlspecialchars($poster) . '</strong>
                </div>
                <p style="font-size:12px;color:#aaa;margin-top:25px;">This is an automated message. Please do not reply.</p>
            </div>
        </div>';
        $mail->send();
    } catch (\Exception $e) {
        error_log("Announcement email failed for {$recipient['email']}: " . $e->getMessage());
    }
}
$mail->smtpClose();