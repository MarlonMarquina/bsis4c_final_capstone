<?php
// handle_otp.php - Email and Username OTP Verification

ini_set('display_errors', 0);
error_reporting(E_ALL);

require 'vendor/autoload.php';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\SMTP;
use PHPMailer\PHPMailer\Exception;

session_start();
include 'conn.php';

header('Content-Type: application/json');
$input = json_decode(file_get_contents('php://input'), true);

$action = $input['action'] ?? '';
$type = $input['type'] ?? ''; 
$destination = $input['destination'] ?? ''; 

$username = $_SESSION['username'] ?? '';
if (!$username || $_SESSION['role'] !== 'student') {
    http_response_code(403); 
    echo json_encode(['success' => false, 'message' => 'Unauthorized access or session expired.']);
    exit;
}

// Email Configuration
$smtpHost = 'smtp.gmail.com'; 
$smtpUsername = 'clearancebpc@gmail.com';
$smtpPassword = 'powe wgem hlsv ybyq';
$smtpPort = 587;

// ========== ACTION: SEND OTP ==========
if ($action === 'send') {
    $destination = trim($destination);
    
    // === EMAIL CHANGE ===
    if ($type === 'email') {
        if (!filter_var($destination, FILTER_VALIDATE_EMAIL)) {
            echo json_encode(['success' => false, 'message' => 'Invalid email format.']);
            exit;
        }
        
        // Generate OTP
        $otp = str_pad(rand(0, 999999), 6, '0', STR_PAD_LEFT);
        $expiry = time() + 300; // 5 minutes

        // Store OTP in session
        $_SESSION['temp_otp_' . $type] = [
            'code' => $otp, 
            'destination' => $destination, 
            'expiry' => $expiry
        ]; 
        unset($_SESSION['email_otp_validated']); 

        // Send OTP to NEW email
        $mail = new PHPMailer(true);
        try {
            $mail->isSMTP();
            $mail->Host = $smtpHost;
            $mail->SMTPAuth = true;
            $mail->Username = $smtpUsername;
            $mail->Password = $smtpPassword;
            $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS; 
            $mail->Port = $smtpPort;

            $mail->setFrom($smtpUsername, 'Smart Clearance System');
            $mail->addAddress($destination);

            $mail->isHTML(true);
            $mail->Subject = 'Smart Clearance OTP Verification';
            $mail->Body = "Your one-time password (OTP) is: <b>$otp</b>. This code expires in 5 minutes.";
            $mail->AltBody = "Your one-time password (OTP) is: $otp. This code expires in 5 minutes.";

            $mail->send();
            echo json_encode(['success' => true, 'message' => 'OTP sent successfully to ' . $destination . '.']);

        } catch (Exception $e) {
            error_log('PHPMailer Error (email): ' . $mail->ErrorInfo);
            unset($_SESSION['temp_otp_' . $type]);
            http_response_code(500);
            echo json_encode(['success' => false, 'message' => "Email sending failed. Error: " . $mail->ErrorInfo]);
        }
    } 
    
    // === USERNAME CHANGE ===
    elseif ($type === 'username') {
        // Validate username format
        if (!preg_match('/^[a-zA-Z0-9_]{4,20}$/', $destination)) {
            echo json_encode(['success' => false, 'message' => 'Username must be 4-20 characters (letters, numbers, underscore only).']);
            exit;
        }
        
        // Check if username already exists
        $stmt = $conn->prepare("SELECT username FROM users WHERE username = ?");
        $stmt->bind_param("s", $destination);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($result->num_rows > 0) {
            echo json_encode(['success' => false, 'message' => 'This username is already taken.']);
            $stmt->close();
            exit;
        }
        $stmt->close();
        
        // Get current user's email
        $stmt = $conn->prepare("SELECT email FROM users WHERE username = ?");
        $stmt->bind_param("s", $username);
        $stmt->execute();
        $result = $stmt->get_result();
        $user = $result->fetch_assoc();
        $stmt->close();
        
        if (!$user) {
            echo json_encode(['success' => false, 'message' => 'User not found.']);
            exit;
        }
        
        // Generate OTP
        $otp = str_pad(rand(0, 999999), 6, '0', STR_PAD_LEFT);
        $expiry = time() + 300;
        
        // Store OTP and new username in session
        $_SESSION['temp_otp_' . $type] = [
            'code' => $otp,
            'new_username' => $destination,
            'expiry' => $expiry
        ];
        
        // Send OTP to CURRENT email
        $mail = new PHPMailer(true);
        try {
            $mail->isSMTP();
            $mail->Host = $smtpHost;
            $mail->SMTPAuth = true;
            $mail->Username = $smtpUsername;
            $mail->Password = $smtpPassword;
            $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
            $mail->Port = $smtpPort;
            
            $mail->setFrom($smtpUsername, 'Smart Clearance System');
            $mail->addAddress($user['email']);
            
            $mail->isHTML(true);
            $mail->Subject = 'Username Change Confirmation - Smart Clearance';
            $mail->Body = "
                <h2>Username Change Request</h2>
                <p>A username change has been requested for your account.</p>
                <p><strong>New Username:</strong> {$destination}</p>
                <p>Your verification code is: <b style='font-size: 24px;'>{$otp}</b></p>
                <p style='color: #666; font-size: 12px;'>This code expires in 5 minutes.</p>
            ";
            $mail->AltBody = "Username change to: {$destination}. Your OTP is: {$otp}. Valid for 5 minutes.";
            
            $mail->send();
            echo json_encode(['success' => true, 'message' => 'OTP sent to your registered email.']);
            
        } catch (Exception $e) {
            error_log('PHPMailer Error (username): ' . $mail->ErrorInfo);
            unset($_SESSION['temp_otp_' . $type]);
            http_response_code(500);
            echo json_encode(['success' => false, 'message' => "Email sending failed. Error: " . $mail->ErrorInfo]);
        }
    } 
    
    else {
        echo json_encode(['success' => false, 'message' => 'Invalid request type.']);
        exit;
    }
} 

// ========== ACTION: VERIFY OTP ==========
elseif ($action === 'verify') {
    $submitted_otp = $input['otp'] ?? '';
    $type = $input['type'] ?? ''; 
    $destination = $input['destination'] ?? ''; 
    
    $stored_otp_data = $_SESSION['temp_otp_' . $type] ?? null;

    if (!$stored_otp_data) {
        echo json_encode(['success' => false, 'message' => 'No OTP request found. Please request a new code.']);
        exit;
    }

    // Check expiry
    if ($stored_otp_data['expiry'] <= time()) {
        unset($_SESSION['temp_otp_' . $type]);
        echo json_encode(['success' => false, 'message' => 'OTP expired. Please request a new one.']);
        exit;
    }

    // Check OTP code
    if ($stored_otp_data['code'] !== $submitted_otp) {
        echo json_encode(['success' => false, 'message' => 'Invalid OTP. Please try again.']);
        exit;
    }

    // === EMAIL VERIFICATION ===
    if ($type === 'email') {
        if ($stored_otp_data['destination'] !== $destination) {
            echo json_encode(['success' => false, 'message' => 'Email mismatch. Please try again.']);
            exit;
        }
        
        $_SESSION['email_otp_validated'] = true; 
        unset($_SESSION['temp_otp_' . $type]);
        echo json_encode(['success' => true, 'message' => 'Email verified successfully.']);
    }
    
    // === USERNAME VERIFICATION ===
    elseif ($type === 'username') {
        $new_username = $stored_otp_data['new_username'];
        
        // Double-check username availability
        $stmt = $conn->prepare("SELECT username FROM users WHERE username = ?");
        $stmt->bind_param("s", $new_username);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($result->num_rows > 0) {
            echo json_encode(['success' => false, 'message' => 'Username no longer available.']);
            $stmt->close();
            unset($_SESSION['temp_otp_' . $type]);
            exit;
        }
        $stmt->close();
        
        // Update username in database
        $stmt = $conn->prepare("UPDATE users SET username = ? WHERE username = ?");
        $stmt->bind_param("ss", $new_username, $username);
        
        if ($stmt->execute()) {
            // Update session username
            $_SESSION['username'] = $new_username;
            unset($_SESSION['temp_otp_' . $type]);
            
            echo json_encode([
                'success' => true,
                'message' => 'Username updated successfully!',
                'new_username' => $new_username
            ]);
        } else {
            echo json_encode(['success' => false, 'message' => 'Database error: ' . $stmt->error]);
        }
        $stmt->close();
    }
    
    else {
        echo json_encode(['success' => false, 'message' => 'Invalid verification type.']);
    }
} 

else {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Invalid action.']);
}
?>