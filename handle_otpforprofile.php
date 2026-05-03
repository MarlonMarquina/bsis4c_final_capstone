<?php
// handle_otp.php - OTP Management for Email/Username Changes
session_start();

require 'vendor/autoload.php';
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;
use PHPMailer\PHPMailer\SMTP;

include 'conn.php';

// Email Configuration (same as forgot_password.php)
define('MAIL_HOST', 'smtp.gmail.com'); 
define('MAIL_USERNAME', 'clearancebpc@gmail.com'); 
define('MAIL_PASSWORD', 'powe wgem hlsv ybyq'); // APP PASSWORD
define('MAIL_PORT', 587); 
define('MAIL_SENDER_EMAIL', 'no-reply@yourdomain.com'); 
define('MAIL_SENDER_NAME', 'BPC Account Security');

// Set JSON response header
header('Content-Type: application/json');

// Get JSON input
$input = json_decode(file_get_contents('php://input'), true);

if (!$input) {
    echo json_encode(['success' => false, 'message' => 'Invalid request format']);
    exit();
}

$action = $input['action'] ?? '';
$type = $input['type'] ?? ''; // 'email' or 'username'
$destination = $input['destination'] ?? ''; // email address or new username
$otp = $input['otp'] ?? '';

// Validate session
if (!isset($_SESSION['username']) || !isset($_SESSION['role'])) {
    echo json_encode(['success' => false, 'message' => 'Session expired. Please login again.']);
    exit();
}

$current_username = $_SESSION['username'];

// --- ACTION: SEND OTP ---
if ($action === 'send') {
    
    if ($type === 'email') {
        // Validate email format
        if (!filter_var($destination, FILTER_VALIDATE_EMAIL)) {
            echo json_encode(['success' => false, 'message' => 'Invalid email format']);
            exit();
        }
        
        // Check if email already exists in database (for another user)
        $stmt = $conn->prepare("SELECT username FROM users WHERE email = ? AND username != ?");
        $stmt->bind_param("ss", $destination, $current_username);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($result->num_rows > 0) {
            echo json_encode(['success' => false, 'message' => 'This email is already in use by another account']);
            $stmt->close();
            exit();
        }
        $stmt->close();
        
        // Generate OTP
        $otp_code = rand(100000, 999999);
        $expiry = time() + (15 * 60); // 15 minutes
        
        // Send OTP to NEW email
        if (sendOTPByEmail($destination, $otp_code, 'email_change')) {
            $_SESSION['email_otp'] = $otp_code;
            $_SESSION['email_otp_expiry'] = $expiry;
            $_SESSION['email_otp_destination'] = $destination;
            
            echo json_encode(['success' => true, 'message' => 'OTP sent to new email address']);
        } else {
            echo json_encode(['success' => false, 'message' => 'Failed to send OTP. Please check SMTP settings.']);
        }
        
    } elseif ($type === 'username') {
        // Validate username (alphanumeric, 4-20 chars)
        if (!preg_match('/^[a-zA-Z0-9_]{4,20}$/', $destination)) {
            echo json_encode(['success' => false, 'message' => 'Username must be 4-20 characters (letters, numbers, underscore only)']);
            exit();
        }
        
        // Check if username already exists
        $stmt = $conn->prepare("SELECT username FROM users WHERE username = ?");
        $stmt->bind_param("s", $destination);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($result->num_rows > 0) {
            echo json_encode(['success' => false, 'message' => 'This username is already taken']);
            $stmt->close();
            exit();
        }
        $stmt->close();
        
        // Get current user's email to send OTP
        $stmt = $conn->prepare("SELECT email FROM users WHERE username = ?");
        $stmt->bind_param("s", $current_username);
        $stmt->execute();
        $result = $stmt->get_result();
        $user = $result->fetch_assoc();
        $stmt->close();
        
        if (!$user) {
            echo json_encode(['success' => false, 'message' => 'User not found']);
            exit();
        }
        
        // Generate OTP
        $otp_code = rand(100000, 999999);
        $expiry = time() + (15 * 60); // 15 minutes
        
        // Send OTP to CURRENT email
        if (sendOTPByEmail($user['email'], $otp_code, 'username_change')) {
            $_SESSION['username_otp'] = $otp_code;
            $_SESSION['username_otp_expiry'] = $expiry;
            $_SESSION['username_otp_new'] = $destination; // Store new username
            
            echo json_encode(['success' => true, 'message' => 'OTP sent to your registered email']);
        } else {
            echo json_encode(['success' => false, 'message' => 'Failed to send OTP. Please check SMTP settings.']);
        }
        
    } else {
        echo json_encode(['success' => false, 'message' => 'Invalid verification type']);
    }
    
    exit();
}

// --- ACTION: VERIFY OTP ---
if ($action === 'verify') {
    
    if ($type === 'email') {
        // Check if OTP session exists
        if (!isset($_SESSION['email_otp']) || !isset($_SESSION['email_otp_expiry'])) {
            echo json_encode(['success' => false, 'message' => 'No OTP request found. Please request a new code.']);
            exit();
        }
        
        // Check if OTP expired
        if (time() > $_SESSION['email_otp_expiry']) {
            unset($_SESSION['email_otp'], $_SESSION['email_otp_expiry'], $_SESSION['email_otp_destination']);
            echo json_encode(['success' => false, 'message' => 'OTP has expired. Please request a new code.']);
            exit();
        }
        
        // Verify OTP
        if ($otp == $_SESSION['email_otp']) {
            $new_email = $_SESSION['email_otp_destination'];
            
            // Update email in database
            $stmt = $conn->prepare("UPDATE users SET email = ? WHERE username = ?");
            $stmt->bind_param("ss", $new_email, $current_username);
            
            if ($stmt->execute()) {
                // Clear OTP session
                unset($_SESSION['email_otp'], $_SESSION['email_otp_expiry'], $_SESSION['email_otp_destination']);
                
                echo json_encode([
                    'success' => true, 
                    'message' => 'Email updated successfully',
                    'new_email' => $new_email
                ]);
            } else {
                echo json_encode(['success' => false, 'message' => 'Database error: ' . $stmt->error]);
            }
            $stmt->close();
            
        } else {
            echo json_encode(['success' => false, 'message' => 'Invalid OTP code']);
        }
        
    } elseif ($type === 'username') {
        // Check if OTP session exists
        if (!isset($_SESSION['username_otp']) || !isset($_SESSION['username_otp_expiry'])) {
            echo json_encode(['success' => false, 'message' => 'No OTP request found. Please request a new code.']);
            exit();
        }
        
        // Check if OTP expired
        if (time() > $_SESSION['username_otp_expiry']) {
            unset($_SESSION['username_otp'], $_SESSION['username_otp_expiry'], $_SESSION['username_otp_new']);
            echo json_encode(['success' => false, 'message' => 'OTP has expired. Please request a new code.']);
            exit();
        }
        
        // Verify OTP
        if ($otp == $_SESSION['username_otp']) {
            $new_username = $_SESSION['username_otp_new'];
            
            // Double-check username availability
            $stmt = $conn->prepare("SELECT username FROM users WHERE username = ?");
            $stmt->bind_param("s", $new_username);
            $stmt->execute();
            $result = $stmt->get_result();
            
            if ($result->num_rows > 0) {
                echo json_encode(['success' => false, 'message' => 'Username no longer available']);
                $stmt->close();
                exit();
            }
            $stmt->close();
            
            // Update username in database
            $stmt = $conn->prepare("UPDATE users SET username = ? WHERE username = ?");
            $stmt->bind_param("ss", $new_username, $current_username);
            
            if ($stmt->execute()) {
                // Update session username
                $_SESSION['username'] = $new_username;
                
                // Clear OTP session
                unset($_SESSION['username_otp'], $_SESSION['username_otp_expiry'], $_SESSION['username_otp_new']);
                
                echo json_encode([
                    'success' => true, 
                    'message' => 'Username updated successfully',
                    'new_username' => $new_username
                ]);
            } else {
                echo json_encode(['success' => false, 'message' => 'Database error: ' . $stmt->error]);
            }
            $stmt->close();
            
        } else {
            echo json_encode(['success' => false, 'message' => 'Invalid OTP code']);
        }
        
    } else {
        echo json_encode(['success' => false, 'message' => 'Invalid verification type']);
    }
    
    exit();
}

// Invalid action
echo json_encode(['success' => false, 'message' => 'Invalid action']);
exit();

// --- EMAIL SENDING FUNCTION ---
function sendOTPByEmail($recipientEmail, $otpCode, $purpose = 'verification') {
    
    if (!class_exists('PHPMailer\PHPMailer\PHPMailer')) {
        error_log("CRITICAL: PHPMailer class not found.");
        return false;
    }
    
    $mail = new PHPMailer(true);
    try {
        // Server settings
        $mail->isSMTP();
        $mail->Host       = MAIL_HOST;
        $mail->SMTPAuth   = true;
        $mail->Username   = MAIL_USERNAME;
        $mail->Password   = MAIL_PASSWORD;
        $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
        $mail->Port       = MAIL_PORT;

        // Recipients
        $mail->setFrom(MAIL_SENDER_EMAIL, MAIL_SENDER_NAME);
        $mail->addAddress($recipientEmail);

        // Content based on purpose
        $mail->isHTML(true);
        
        if ($purpose === 'email_change') {
            $mail->Subject = 'Verify Your New Email Address - BPC';
            $mail->Body    = '
                <div style="font-family: Arial, sans-serif; padding: 20px; background: #f4f4f4;">
                    <div style="max-width: 600px; margin: 0 auto; background: white; padding: 30px; border-radius: 10px;">
                        <h2 style="color: #00a859;">Email Change Verification</h2>
                        <p>You are attempting to change your email address to this email.</p>
                        <p>Your One-Time Password (OTP) is:</p>
                        <div style="background: #f0f0f0; padding: 15px; border-radius: 5px; text-align: center; font-size: 28px; font-weight: bold; color: #333; letter-spacing: 5px;">
                            ' . $otpCode . '
                        </div>
                        <p style="color: #666; font-size: 14px; margin-top: 20px;">This code is valid for 15 minutes.</p>
                        <p style="color: #999; font-size: 12px;">If you did not request this change, please ignore this email.</p>
                    </div>
                </div>
            ';
            $mail->AltBody = 'Your email change verification code is: ' . $otpCode . '. Valid for 15 minutes.';
            
        } elseif ($purpose === 'username_change') {
            $mail->Subject = 'Confirm Username Change - BPC';
            $mail->Body    = '
                <div style="font-family: Arial, sans-serif; padding: 20px; background: #f4f4f4;">
                    <div style="max-width: 600px; margin: 0 auto; background: white; padding: 30px; border-radius: 10px;">
                        <h2 style="color: #00a859;">Username Change Confirmation</h2>
                        <p>A username change has been requested for your account.</p>
                        <p>Your One-Time Password (OTP) is:</p>
                        <div style="background: #f0f0f0; padding: 15px; border-radius: 5px; text-align: center; font-size: 28px; font-weight: bold; color: #333; letter-spacing: 5px;">
                            ' . $otpCode . '
                        </div>
                        <p style="color: #666; font-size: 14px; margin-top: 20px;">This code is valid for 15 minutes.</p>
                        <p style="color: #999; font-size: 12px;">If you did not request this change, please secure your account immediately.</p>
                    </div>
                </div>
            ';
            $mail->AltBody = 'Your username change verification code is: ' . $otpCode . '. Valid for 15 minutes.';
        }

        $mail->send();
        return true;
        
    } catch (Exception $e) {
        error_log("PHPMailer Error: {$mail->ErrorInfo}");
        return false;
    }
}
?>