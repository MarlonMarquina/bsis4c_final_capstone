<?php
session_start();

// --- CRITICAL PHPMailer REQUIREMENT ---
// Gumamit lang ng Composer Autoload. Siguraduhin na ang path ay tama.
require 'vendor/autoload.php';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;
use PHPMailer\PHPMailer\SMTP; // Added SMTP use statement


// 1. I-DECLARE ang mga variables
$step = isset($_SESSION['reset_step']) ? $_SESSION['reset_step'] : 'email';
$user_email = isset($_SESSION['user_email']) ? $_SESSION['user_email'] : '';
$message = ""; 
$error = "";

// 3. MUST INCLUDE conn.php to get the database connection ($conn)
include 'conn.php'; 

// --- CRITICAL EMAIL CONFIGURATION (UPDATE THESE VALUES) ---
// Tiyakin na ang GMAIL account ay naka-2FA at gumagamit ng App Password.
define('MAIL_HOST', 'smtp.gmail.com'); 
define('MAIL_USERNAME', 'clearancebpc@gmail.com'); 
define('MAIL_PASSWORD', 'powe wgem hlsv ybyq'); // PALITAN ITO NG APP PASSWORD
define('MAIL_PORT', 587); 
define('MAIL_SENDER_EMAIL', 'no-reply@yourdomain.com'); 
define('MAIL_SENDER_NAME', 'BPC Password Reset');


// Handle case where database connection failed
if (!isset($conn) || $conn === null) {
    $error = "System maintenance: Password reset service is temporarily unavailable.";
    $step = 'finished'; 
}

// --- EMAIL SENDING FUNCTION (REMOVED TEST MODE) ---

/**
 * Sends a 6-digit OTP code to the specified email address using PHPMailer.
 * Returns TRUE on success, FALSE on failure.
 */
function sendOTPByEmail($recipientEmail, $otpCode) {
    
    // Check if the PHPMailer class is available (should be, due to require statement)
    if (!class_exists('PHPMailer\PHPMailer\PHPMailer')) {
        error_log("CRITICAL: PHPMailer class not found. Check vendor/autoload.php path.");
        return false;
    }
    
    // --- REAL PHPMailer LOGIC ---
    $mail = new PHPMailer(true);
    try {
        // Server settings
        $mail->isSMTP();
        // $mail->SMTPDebug  = SMTP::DEBUG_SERVER; // Uncomment to debug SMTP connection issues
        $mail->Host       = MAIL_HOST;
        $mail->SMTPAuth   = true;
        $mail->Username   = MAIL_USERNAME;
        $mail->Password   = MAIL_PASSWORD;
        $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS; // Use ENCRYPTION_SMTPS for Port 465
        $mail->Port       = MAIL_PORT; // Use 587 for STARTTLS

        // Recipients
        $mail->setFrom(MAIL_SENDER_EMAIL, MAIL_SENDER_NAME);
        $mail->addAddress($recipientEmail);

        // Content
        $mail->isHTML(true);
        $mail->Subject = 'Your BPC Password Reset Code';
        $mail->Body    = '<h1>Password Reset Verification</h1><p>Your One-Time Password (OTP) for resetting your password is: <strong>' . $otpCode . '</strong></p><p>This code is valid for 15 minutes. Please enter it on the website to proceed.</p>';
        $mail->AltBody = 'Your OTP is: ' . $otpCode . '. It is valid for 15 minutes.';

        $mail->send();
        return true;
    } catch (Exception $e) {
        // Log the detailed PHPMailer error
        error_log("Message could not be sent. Mailer Error: {$mail->ErrorInfo}");
        return false;
    }
}


// --- DATABASE FUNCTIONS (REAL DB IMPLEMENTATION) ---

function getUserByEmail($email) {
    global $conn;
    if (!$conn) return null;

    $stmt = $conn->prepare("SELECT email, password FROM users WHERE email = ?");
    $stmt->bind_param("s", $email);
    $stmt->execute();
    $result = $stmt->get_result();
    $user = $result->fetch_assoc();
    $stmt->close();
    return $user;
}

function updatePassword($email, $new_password_hash) {
    global $conn, $message;
    if (!$conn) return false;
    
    $stmt = $conn->prepare("UPDATE users SET password = ? WHERE email = ?");
    $stmt->bind_param("ss", $new_password_hash, $email);
    
    if ($stmt->execute()) {
        $stmt->close();
        return true;
    } else {
        error_log("Password update failed for email $email: " . $conn->error);
        $stmt->close();
        return false;
    }
}


// --- STEP 1: REQUEST EMAIL / SEND CODE ---
if ($step === 'email' && isset($_POST['send_code'])) {
    $email = trim($_POST['email']);
    
    $user = getUserByEmail($email); 

    if ($user) {
        $otp = rand(100000, 999999); // 6-digit OTP
        $expiry = time() + (15 * 60); // 15 minutes from now

        // 1. ATTEMPT TO SEND EMAIL
        if (sendOTPByEmail($email, $otp)) {
            // 2. ONLY store OTP and details in session if email sending succeeded
            $_SESSION['reset_step'] = 'otp';
            $_SESSION['otp_code'] = $otp;
            $_SESSION['otp_expiry'] = $expiry;
            $_SESSION['user_email'] = $email;

            $message = "A verification code has been sent to **" . htmlspecialchars($email) . "**.";
            $step = 'otp'; // Move to the next step
        } else {
             $error = "We could not send the verification code. Please check your SMTP settings (App Password/Ports) and try again.";
        }
        
    } else {
        $error = "No account found with that email address.";
    }
}

// --- STEP 2: VERIFY OTP ---
if ($step === 'otp' && isset($_POST['verify_otp'])) {
    $input_otp = trim($_POST['otp']);

    if (time() > $_SESSION['otp_expiry']) {
        $error = "The code has expired. Please restart the reset process.";
        // Clear session data and restart
        unset($_SESSION['reset_step'], $_SESSION['otp_code'], $_SESSION['otp_expiry'], $_SESSION['user_email']);
        $step = 'email';
    } elseif ($input_otp == $_SESSION['otp_code']) {
        $message = "Code verified. You can now set your new password.";
        $_SESSION['reset_step'] = 'password';
        $step = 'password'; // Move to the final step
    } else {
        $error = "Invalid code. Please try again.";
        $step = 'otp'; // Stay on this step
    }
}

// --- STEP 3: SET NEW PASSWORD ---
if ($step === 'password' && isset($_POST['set_password'])) {
    $new_pass = $_POST['new_password'];
    $confirm_pass = $_POST['confirm_password'];
    $email_to_update = $_SESSION['user_email'] ?? '';

    if (empty($email_to_update)) {
         $error = "Session error. Please restart the reset process.";
         $step = 'email';
    } elseif (strlen($new_pass) < 8) {
        $error = "Password must be at least 8 characters long.";
        $step = 'password';
    } elseif ($new_pass !== $confirm_pass) {
        $error = "Passwords do not match.";
        $step = 'password';
    } else {
        // Securely hash the new password
        $hashed_pass = password_hash($new_pass, PASSWORD_BCRYPT);

        // Updates the password in the real database
        if (updatePassword($email_to_update, $hashed_pass)) { 
            // Success, clear all session data and go to final screen/login
            unset($_SESSION['reset_step'], $_SESSION['otp_code'], $_SESSION['otp_expiry'], $_SESSION['user_email']);
            $step = 'finished';
            $message = "Your password has been successfully reset! You may now return to the login page.";
        } else {
             $error = "An error occurred during password update. Please try again.";
             $step = 'password';
        }
    }
}

// Handle restart link
if (isset($_POST['restart_reset'])) {
    unset($_SESSION['reset_step'], $_SESSION['otp_code'], $_SESSION['otp_expiry'], $_SESSION['user_email']);
    $step = 'email';
    $message = "Password reset process restarted.";
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8" />
<meta name="viewport" content="width=device-width, initial-scale=1.0" />
<title>Password Reset | BPC</title>
<link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;600&display=swap" rel="stylesheet" />
<style>
/* Base Styles for aesthetics */
body {
    font-family: "Poppins", sans-serif;
    margin: 0;
    background: #f0f4f8; /* Soft background */
    height: 100vh;
    display: flex;
    justify-content: center;
    align-items: center;
}
.forgot-container {
    background: #ffffff;
    padding: 40px 50px;
    border-radius: 15px;
    box-shadow: 0 10px 30px rgba(0,0,0,0.1);
    width: 400px;
    max-width: 90%;
    text-align: center;
    transition: all 0.3s ease;
}
.forgot-container h2 {
    margin-bottom: 20px;
    color: #00a859; /* Primary BPC Color */
    font-weight: 700;
    border-bottom: 2px solid #e0e0e0;
    padding-bottom: 10px;
}
/* Form Elements */
.input-group {
    margin-bottom: 20px;
    text-align: left;
}
.input-group label {
    display: block;
    font-size: 14px;
    margin-bottom: 8px;
    font-weight: 600;
    color: #333;
}
.input-group input {
    width: 100%;
    padding: 12px;
    border: 1px solid #ddd;
    border-radius: 8px;
    font-size: 16px;
    box-sizing: border-box;
    transition: border-color 0.3s;
}
.input-group input:focus {
    outline: none;
    border-color: #00a859;
    box-shadow: 0 0 0 2px rgba(0, 168, 89, 0.2);
}
/* Buttons */
.btn {
    background: #00a859;
    color: white;
    border: none;
    padding: 12px 20px;
    border-radius: 8px;
    font-size: 16px;
    width: 100%;
    cursor: pointer;
    transition: background-color 0.3s, transform 0.1s;
    font-weight: 600;
}
.btn:hover {
    background: #008f4c;
    transform: translateY(-1px);
}
.btn:active {
    transform: translateY(0);
}

/* Messages */
.notification {
    margin-bottom: 15px;
    padding: 10px 15px;
    border-radius: 8px;
    font-size: 14px;
    line-height: 1.4;
    text-align: left;
}
.error-msg {
    color: #900;
    background-color: #ffe0e0;
    border: 1px solid #f0a0a0;
}
.success-msg {
    color: #007a43;
    background-color: #e6ffe6;
    border: 1px solid #a0e0a0;
}
.info-msg {
    color: #007bff;
    background-color: #e0f0ff;
    border: 1px solid #a0c0f0;
}

/* Back Link */
.back-link {
    margin-top: 20px;
    display: block;
    font-size: 14px;
}
.back-link a {
    color: #666;
    text-decoration: none;
}
.back-link a:hover {
    color: #00a859;
    text-decoration: underline;
}

/* --- MODAL CSS STYLES --- */
.modal-overlay {
    display: none; /* Hidden by default */
    position: fixed;
    z-index: 1000; /* High z-index to cover everything */
    left: 0;
    top: 0;
    width: 100%;
    height: 100%;
    overflow: auto;
    background-color: rgba(0,0,0,0.5); /* Black w/ opacity */
}
.modal-content {
    background-color: #fefefe;
    margin: 15% auto; /* 15% from the top and centered */
    padding: 20px;
    border: 1px solid #888;
    width: 80%; /* Could be more specific like 300px */
    max-width: 350px;
    border-radius: 10px;
    text-align: center;
    box-shadow: 0 4px 8px 0 rgba(0,0,0,0.2), 0 6px 20px 0 rgba(0,0,0,0.19);
}
.modal-content h3 {
    color: #cc0000;
    margin-top: 0;
    margin-bottom: 15px;
}
.modal-content p {
    margin-bottom: 20px;
    font-size: 14px;
    color: #333;
}

/* --- MODIFIED CSS FOR EQUAL BUTTONS --- */
.modal-btn-group {
    display: flex;
    justify-content: space-between; 
    align-items: center;
    gap: 10px; 
}

.modal-btn-group button {
    padding: 10px; 
    border: none;
    border-radius: 5px;
    cursor: pointer;
    font-weight: 600;
    flex-grow: 1; 
    white-space: nowrap;
}

.modal-btn-yes {
    background-color: #d9534f; /* Red for destructive action */
    color: white;
}
.modal-btn-no {
    background-color: #f0f0f0;
    color: #333;
}
</style>

<script>
    function openModal() {
        document.getElementById('confirmationModal').style.display = 'block';
    }

    function closeModal() {
        document.getElementById('confirmationModal').style.display = 'none';
    }

    function handleConfirmation(isConfirmed) {
        if (isConfirmed) {
            // If confirmed (Yes), redirect to login.php. 
            window.location.href = 'login.php';
        }
        // Close the modal whether 'Yes' or 'No' was clicked
        closeModal();
    }
    
    // Close the modal if the user clicks anywhere outside of the content
    window.onclick = function(event) {
        const modal = document.getElementById('confirmationModal');
        if (event.target == modal) {
            closeModal();
        }
    }
</script>

</head>
<body>
    <div class="forgot-container">
    
        <?php if (!empty($error)): ?>
            <div class="notification error-msg">
                <?php echo htmlspecialchars($error); ?>
            </div>
        <?php elseif (!empty($message)): ?>
            <?php 
                $class = 'info-msg';
                if (strpos($message, 'successfully reset') !== false) {
                    $class = 'success-msg';
                }
            ?>
            <div class="notification <?php echo $class; ?>">
                <?php echo $message; ?>
            </div>
        <?php endif; ?>

        <?php if ($step === 'email'): // --- STEP 1: REQUEST EMAIL --- ?>
            <h2>Request Verification Code</h2>
            <form method="POST">
                <div class="input-group">
                    <label for="email">Enter your Email Address</label>
                    <input type="email" name="email" id="email" required>
                </div>
                <button type="submit" name="send_code" class="btn">Send Code</button>
            </form>

        <?php elseif ($step === 'otp'): // --- STEP 2: VERIFY OTP --- ?>
            <h2>Enter Verification Code</h2>
            <p style="font-size: 14px; color: #555;">The code was sent to **<?php echo htmlspecialchars($user_email); ?>**.</p>
            
            <form method="POST">
                <div class="input-group">
                    <label for="otp">One-Time Password (OTP)</label>
                    <input type="text" name="otp" id="otp" required pattern="\d{6}" title="6-digit code" maxlength="6">
                </div>
                <button type="submit" name="verify_otp" class="btn">Verify Code</button>
            </form>
            <form method="POST" style="margin-top: 15px;">
                   <button type="submit" name="restart_reset" class="btn" style="background: #ccc; color: #333;">Restart Process</button>
            </form>

        <?php elseif ($step === 'password'): // --- STEP 3: SET NEW PASSWORD --- ?>
            <h2>Set New Password</h2>
            <p style="font-size: 14px; color: #555;">Setting password for **<?php echo htmlspecialchars($user_email); ?>**.</p>
            <form method="POST">
                <div class="input-group">
                    <label for="new_password">New Password</label>
                    <input type="password" name="new_password" id="new_password" required minlength="8">
                </div>
                <div class="input-group">
                    <label for="confirm_password">Confirm New Password</label>
                    <input type="password" name="confirm_password" id="confirm_password" required minlength="8">
                </div>
                <button type="submit" name="set_password" class="btn">Set Password</button>
            </form>

        <?php elseif ($step === 'finished'): // --- STEP 4: FINISHED --- ?>
            <h2>Password Reset Complete</h2>
            <p style="font-size: 16px; color: #00a859; font-weight: 600;">You can now log in with your new password.</p>
            <div class="back-link">
                <a href="login.php">← Go to Login Page</a>
            </div>
        <?php endif; ?>

        <?php if ($step !== 'finished'): ?>
            <div class="back-link">
                <a href="#" onclick="openModal()">← Back to Login</a>
            </div>
        <?php endif; ?>

    </div>
    
    <div id="confirmationModal" class="modal-overlay">
        <div class="modal-content">
            <h3>Confirm Navigation</h3>
            <p>Are you sure you want to go back to the Login Page? If you leave now, your current password reset session will be lost, and you will have to start over.</p>
            <div class="modal-btn-group">
                <button class="modal-btn-no" onclick="handleConfirmation(false)">No, Continue Reset</button>
                <button class="modal-btn-yes" onclick="handleConfirmation(true)">Yes, Go to Login</button>
            </div>
        </div>
    </div>
</body>
</html>