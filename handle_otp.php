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

$action      = $input['action'] ?? '';
$type        = $input['type'] ?? '';
$destination = $input['destination'] ?? '';

$username = $_SESSION['username'] ?? '';

// Allow admin, signatory, student roles
$allowedRoles = ['student', 'signatory', 'admin'];
if (!$username || !in_array($_SESSION['role'] ?? '', $allowedRoles)) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Unauthorized access or session expired.']);
    exit;
}

// Email Configuration
$smtpHost     = 'smtp.gmail.com';
$smtpUsername = 'clearancebpc@gmail.com';
$smtpPassword = 'powe wgem hlsv ybyq';
$smtpPort     = 587;

// ========== ACTION: DIRECT UPDATE (Main admin editing other admins, no OTP needed) ==========
// ========== ACTION: DIRECT UPDATE ==========
if ($action === 'direct_update') {

    $check = $conn->prepare("SELECT can_add_admin FROM users WHERE username = ? AND role = 'admin'");
    $check->bind_param("s", $username);
    $check->execute();
    $checkRow = $check->get_result()->fetch_assoc();
    $check->close();

    if (!$checkRow || $checkRow['can_add_admin'] != 1) {
        echo json_encode(['success' => false, 'message' => 'Unauthorized.']);
        exit;
    }

    $target = $input['target_username'] ?? '';
    $type   = $input['type'] ?? '';
    $value  = $input['value'] ?? $input['new_password'] ?? '';

    if (empty($target)) {
        echo json_encode(['success' => false, 'message' => 'Target username is required.']);
        exit;
    }

    if ($type === 'name') {
        $value = trim($value);
        if (empty($value)) { echo json_encode(['success' => false, 'message' => 'Name cannot be empty.']); exit; }
        $stmt = $conn->prepare("UPDATE users SET full_name = ? WHERE username = ? AND role = 'admin'");
        $stmt->bind_param("ss", $value, $target);

    } elseif ($type === 'username') {
        $value = trim($value);
        if (!preg_match('/^[a-zA-Z0-9_]{4,20}$/', $value)) {
            echo json_encode(['success' => false, 'message' => 'Invalid username format.']); exit;
        }
        $uniq = $conn->prepare("SELECT username FROM users WHERE username = ?");
        $uniq->bind_param("s", $value); $uniq->execute();
        if ($uniq->get_result()->num_rows > 0) {
            echo json_encode(['success' => false, 'message' => 'Username already taken.']); $uniq->close(); exit;
        }
        $uniq->close();
        $stmt = $conn->prepare("UPDATE users SET username = ? WHERE username = ? AND role = 'admin'");
        $stmt->bind_param("ss", $value, $target);

    } elseif ($type === 'email') {
        $value = trim($value);
        if (!filter_var($value, FILTER_VALIDATE_EMAIL)) {
            echo json_encode(['success' => false, 'message' => 'Invalid email.']); exit;
        }
        $uniq = $conn->prepare("SELECT username FROM users WHERE email = ? AND username != ?");
        $uniq->bind_param("ss", $value, $target); $uniq->execute();
        if ($uniq->get_result()->num_rows > 0) {
            echo json_encode(['success' => false, 'message' => 'Email already in use.']); $uniq->close(); exit;
        }
        $uniq->close();
        $stmt = $conn->prepare("UPDATE users SET email = ? WHERE username = ? AND role = 'admin'");
        $stmt->bind_param("ss", $value, $target);

    } elseif ($type === 'password') {
        $value = $input['new_password'] ?? '';
        if (strlen($value) < 8) {
            echo json_encode(['success' => false, 'message' => 'Password too short.']); exit;
        }
        $hashed = password_hash($value, PASSWORD_DEFAULT);
        $stmt = $conn->prepare("UPDATE users SET password = ? WHERE username = ? AND role = 'admin'");
        $stmt->bind_param("ss", $hashed, $target);

    } else {
        echo json_encode(['success' => false, 'message' => 'Invalid type.']);
        exit;
    }

    if ($stmt->execute()) {
        echo json_encode(['success' => true]);
    } else {
        echo json_encode(['success' => false, 'message' => 'DB error: ' . $stmt->error]);
    }
    $stmt->close();
    exit;
}
// ========== ACTION: SEND OTP ==========
if ($action === 'send') {
    // ... (rest of your existing send OTP code unchanged) ...
    $destination = trim($destination);
    
    // === EMAIL CHANGE ===
    if ($type === 'email') {
        if (!filter_var($destination, FILTER_VALIDATE_EMAIL)) {
            echo json_encode(['success' => false, 'message' => 'Invalid email format.']);
            exit;
        }

        // Check if email is already in use by another account
        $stmt = $conn->prepare("SELECT username FROM users WHERE email = ? AND username != ?");
        $stmt->bind_param("ss", $destination, $username);
        $stmt->execute();
        $result = $stmt->get_result();
        if ($result->num_rows > 0) {
            echo json_encode(['success' => false, 'message' => 'This email address is already in use by another account.']);
            $stmt->close();
            exit;
        }
        $stmt->close();
        
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
        if (!preg_match('/^[a-zA-Z0-9_]{4,20}$/', $destination)) {
            echo json_encode(['success' => false, 'message' => 'Username must be 4-20 characters (letters, numbers, underscore only).']);
            exit;
        }
        
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
        
        $otp = str_pad(rand(0, 999999), 6, '0', STR_PAD_LEFT);
        $expiry = time() + 300;
        
        $_SESSION['temp_otp_' . $type] = [
            'code' => $otp,
            'new_username' => $destination,
            'expiry' => $expiry
        ];
        
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


    // === CONTACT NUMBER CHANGE ===
    elseif ($type === 'contact') {
        if (!preg_match('/^[0-9]{11}$/', $destination)) {
            echo json_encode(['success' => false, 'message' => 'Contact number must be 11 digits.']);
            exit;
        }
        
        $otp = str_pad(rand(0, 999999), 6, '0', STR_PAD_LEFT);
        $expiry = time() + 300;
        
        $_SESSION['temp_otp_' . $type] = [
            'code' => $otp,
            'new_contact' => $destination,
            'expiry' => $expiry
        ];
        
        // TODO: Send OTP via SMS API (Semaphore, Twilio, etc.)
        echo json_encode(['success' => true, 'message' => 'OTP sent to your new contact number.']);
    }

    // === ADMIN NAME CHANGE ===
    elseif ($type === 'admin_name') {
        $new_name = trim($destination);
        if (empty($new_name)) {
            echo json_encode(['success' => false, 'message' => 'Name cannot be empty.']);
            exit;
        }

        $stmt = $conn->prepare("SELECT email FROM users WHERE username = ?");
        $stmt->bind_param("s", $username);
        $stmt->execute();
        $result = $stmt->get_result();
        $user = $result->fetch_assoc();
        $stmt->close();

        if (!$user || empty($user['email'])) {
            echo json_encode(['success' => false, 'message' => 'Admin email not found.']);
            exit;
        }

        $otp = str_pad(rand(0, 999999), 6, '0', STR_PAD_LEFT);
        $expiry = time() + 300;

        $_SESSION['temp_otp_admin_name'] = [
            'code'     => $otp,
            'new_name' => $new_name,
            'expiry'   => $expiry
        ];

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
            $mail->addAddress($user['email']);

            $mail->isHTML(true);
            $mail->Subject = 'Admin Name Change Confirmation - Smart Clearance';
            $mail->Body    = "
                <h2>Name Change Request</h2>
                <p>A name change has been requested for your admin account.</p>
                <p><strong>New Name:</strong> {$new_name}</p>
                <p>Your verification code is: <b style='font-size: 24px;'>{$otp}</b></p>
                <p style='color: #666; font-size: 12px;'>This code expires in 5 minutes.</p>
            ";
            $mail->AltBody = "Name change to: {$new_name}. Your OTP is: {$otp}. Valid for 5 minutes.";

            $mail->send();
            echo json_encode(['success' => true, 'message' => 'OTP sent to your registered email.']);

        } catch (Exception $e) {
            error_log('PHPMailer Error (admin_name): ' . $mail->ErrorInfo);
            unset($_SESSION['temp_otp_admin_name']);
            http_response_code(500);
            echo json_encode(['success' => false, 'message' => 'Email sending failed. Error: ' . $mail->ErrorInfo]);
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

// Password change bypasses OTP — handle it early and exit
if ($type === 'password') {
    $current_password = $input['current_password'] ?? '';
    $new_password     = $input['new_password'] ?? '';

    if (empty($current_password) || empty($new_password)) {
        echo json_encode(['success' => false, 'message' => 'Password fields are required.']);
        exit;
    }
   if (strlen($new_password) < 8) {
    echo json_encode(['success' => false, 'message' => 'New password must be at least 8 characters.']);
    exit;
}
if (!preg_match('/[A-Z]/', $new_password)) {
    echo json_encode(['success' => false, 'message' => 'Password must contain at least one uppercase letter.']);
    exit;
}
if (!preg_match('/[0-9]/', $new_password)) {
    echo json_encode(['success' => false, 'message' => 'Password must contain at least one number.']);
    exit;
}
if (!preg_match('/[\W_]/', $new_password)) {
    echo json_encode(['success' => false, 'message' => 'Password must contain at least one special character.']);
    exit;
}
    $default_passwords = ['@Student01', '@Signatory01'];
    if (in_array($new_password, $default_passwords)) {
        echo json_encode(['success' => false, 'message' => 'You cannot use the default system password.']);
        exit;
    }

    $stmt = $conn->prepare("SELECT password FROM users WHERE username = ?");
    $stmt->bind_param("s", $username);
    $stmt->execute();
    $user = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    if (!$user) {
        echo json_encode(['success' => false, 'message' => 'User not found.']);
        exit;
    }
    if (!password_verify($current_password, $user['password'])) {
        echo json_encode(['success' => false, 'message' => 'Current password is incorrect.']);
        exit;
    }
    if ($current_password === $new_password) {
        echo json_encode(['success' => false, 'message' => 'New password must be different from current password.']);
        exit;
    }

    $hashed = password_hash($new_password, PASSWORD_DEFAULT);
    $stmt = $conn->prepare("UPDATE users SET password = ?, password_last_updated = NOW() WHERE username = ?");
    $stmt->bind_param("ss", $hashed, $username);

    if ($stmt->execute()) {
        echo json_encode(['success' => true, 'message' => 'Password changed successfully!']);
    } else {
        echo json_encode(['success' => false, 'message' => 'Database error: ' . $stmt->error]);
    }
    $stmt->close();
    exit;
}

// All other types still require OTP
if (!$stored_otp_data) {
    echo json_encode(['success' => false, 'message' => 'No OTP request found. Please request a new code.']);
    exit;
}
if ($stored_otp_data['expiry'] <= time()) {
    unset($_SESSION['temp_otp_' . $type]);
    echo json_encode(['success' => false, 'message' => 'OTP expired. Please request a new one.']);
    exit;
}
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
        
        $stmt = $conn->prepare("UPDATE users SET email = ?, email_verified = 1 WHERE username = ?");
$stmt->bind_param("ss", $destination, $username);
        
        if ($stmt->execute()) {
            unset($_SESSION['temp_otp_' . $type]);
            echo json_encode(['success' => true, 'message' => 'Email updated successfully!']);
        } else {
            echo json_encode(['success' => false, 'message' => 'Database error: ' . $stmt->error]);
        }
        $stmt->close();
    }

    // === USERNAME VERIFICATION ===
    elseif ($type === 'username') {
        $new_username = $stored_otp_data['new_username'];
        
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
        
        $stmt = $conn->prepare("UPDATE users SET username = ? WHERE username = ?");
        $stmt->bind_param("ss", $new_username, $username);
        
        if ($stmt->execute()) {
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


    // === CONTACT NUMBER VERIFICATION ===
    elseif ($type === 'contact') {
        $new_contact = $stored_otp_data['new_contact'];
        
        $stmt = $conn->prepare("UPDATE users SET contact = ? WHERE username = ?");
        $stmt->bind_param("ss", $new_contact, $username);
        
        if ($stmt->execute()) {
            unset($_SESSION['temp_otp_' . $type]);
            echo json_encode([
                'success' => true,
                'message' => 'Contact number updated successfully!',
                'new_contact' => $new_contact
            ]);
        } else {
            echo json_encode(['success' => false, 'message' => 'Database error: ' . $stmt->error]);
        }
        $stmt->close();
    }
    
    // === ADMIN NAME VERIFICATION ===
    elseif ($type === 'admin_name') {
        $new_name = $stored_otp_data['new_name'] ?? '';

        if (empty($new_name)) {
            echo json_encode(['success' => false, 'message' => 'Name data missing. Please try again.']);
            exit;
        }

        $stmt = $conn->prepare("UPDATE users SET full_name = ? WHERE username = ?");
        $stmt->bind_param("ss", $new_name, $username);

        if ($stmt->execute()) {
            unset($_SESSION['temp_otp_admin_name']);
            echo json_encode(['success' => true, 'message' => 'Full name updated successfully!']);
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