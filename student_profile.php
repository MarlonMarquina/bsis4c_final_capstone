<?php
// student_profile.php (FIXED VERSION)
session_start();
if (!isset($_SESSION['role']) || $_SESSION['role'] != "student") {
    header("Location: login.php");
    exit();
}

require 'vendor/autoload.php';
include 'conn.php';

$username = $_SESSION['username'];
$msg = '';

// --- 1. Fetch Existing Data (Users Table) ---
$stmt = $conn->prepare("SELECT full_name, sex, birthdate, contact, email, email_verified, password_last_updated, street, city, province, course, section, year, student_id, profile_pic, semester, school_year FROM users WHERE username=?");
$stmt->bind_param("s", $username);
$stmt->execute();
$result = $stmt->get_result();
$student = $result->fetch_assoc();
$stmt->close();

$current_profile_pic = htmlspecialchars($student['profile_pic'] ?: 'default.png');

if (!$student) {
    $msg = "Error: Student profile not found in the database.";
}

if (empty($student['semester']) || empty($student['school_year'])) {
    $sys = $conn->query("SELECT current_semester, current_school_year FROM system_settings WHERE id=1")->fetch_assoc();
    
    if (empty($student['semester'])) {
        $student['semester'] = $sys['current_semester'] ?? '1st Semester';
    }
    if (empty($student['school_year'])) {
        $student['school_year'] = $sys['current_school_year'] ?? date('Y').'-'.(date('Y')+1);
    }

    $fix = $conn->prepare("UPDATE users SET semester=?, school_year=? WHERE username=?");
    $fix->bind_param("sss", $student['semester'], $student['school_year'], $username);
    $fix->execute();
    $fix->close();
}

// --- 2. Handle Profile Picture Upload ---
if (isset($_POST['upload_picture']) && $student) {
    
    $profile_pic = $student['profile_pic'] ?? 'default.png'; 
    $targetDir = "uploads/";

    // Check if file is uploaded
    if (isset($_FILES['profile_pic']) && $_FILES['profile_pic']['error'] === UPLOAD_ERR_OK) {
        $fileInfo = $_FILES["profile_pic"];
        $allowedTypes = ['image/png', 'image/jpeg', 'image/jpg'];
        $maxFileSize = 5 * 1024 * 1024; // 5MB
        
        // Validate file type
        if (!in_array($fileInfo['type'], $allowedTypes)) {
            $msg = "Error: Only PNG, JPEG, and JPG files are allowed.";
        }
        // Validate file size
        elseif ($fileInfo['size'] > $maxFileSize) {
            $msg = "Error: File size must not exceed 5MB.";
        }
        else {
            // Create upload directory if not exists
            if (!is_dir($targetDir)) { 
                mkdir($targetDir, 0777, true); 
            }
            
            // Generate unique filename
            $fileName = time() . '_' . uniqid() . '_' . basename($fileInfo["name"]);
            $targetFilePath = $targetDir . $fileName;
            
            // Move uploaded file
            if (move_uploaded_file($fileInfo["tmp_name"], $targetFilePath)) {
                // Delete old profile pic if not default
                if ($student['profile_pic'] && $student['profile_pic'] !== 'default.png' && file_exists($targetDir . $student['profile_pic'])) {
                    unlink($targetDir . $student['profile_pic']);
                }
                $profile_pic = $fileName;
                
                // Update database
                $sql = "UPDATE users SET profile_pic=? WHERE username=?";
                $stmt = $conn->prepare($sql);
                $stmt->bind_param("ss", $profile_pic, $username);
                
                if ($stmt->execute()) {
                    $msg = "Profile picture updated successfully! 📸";
                    
                    // Reload data
$stmt_reload = $conn->prepare("SELECT full_name, sex, birthdate, contact, email, email_verified, password_last_updated, street, city, province, course, section, year, student_id, profile_pic, semester, school_year FROM users WHERE username=?");
                    $stmt_reload->bind_param("s", $username);
                    $stmt_reload->execute();
                    $result_reload = $stmt_reload->get_result();
                    $student = $result_reload->fetch_assoc();
                    $current_profile_pic = htmlspecialchars($student['profile_pic'] ?: 'default.png');
                    $stmt_reload->close();
                } else {
                    $msg = "Error updating profile picture: " . $stmt->error;
                }
                $stmt->close();
            } else {
                $msg = "Error: Failed to upload photo. Please try again.";
            }
        }
    } else {
        $msg = "Error: Please select a photo to upload.";
    }
}

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width,initial-scale=1.0"> 
    <title>Student Profile | Smart Clearance System</title>
    
    <link href='https://unpkg.com/boxicons@2.1.4/css/boxicons.min.css' rel='stylesheet'>
    <link rel="stylesheet" href="styles.css">

    <style>
    body {
      font-family: 'Poppins', sans-serif;
      background: #f3f7f0;
      margin: 0;
      padding: 0;
    }
    .container {
      width: 90%;
      max-width: 900px;
      margin: 30px auto;
      background: white;
      border-radius: 10px;
      box-shadow: 0 4px 12px rgba(0,0,0,0.1);
      padding: 30px;
    }
    h2 {
      text-align: center;
      color: darkgreen;
      margin-bottom: 10px;
    }
    .term-display {
      text-align: center;
      background: #e8f5e9;
      padding: 12px;
      border-radius: 8px;
      margin-bottom: 20px;
      border: 2px solid #2d5016;
    }
    .term-display strong {
      color: #2d5016;
      font-size: 16px;
    }
    .popup {
      position: fixed;
      top: 20px;
      right: 20px;
      background: darkgreen;
      color: white;
      padding: 10px 20px;
      border-radius: 8px;
      box-shadow: 0 4px 8px rgba(0,0,0,0.2);
      font-weight: 600;
      opacity: 0;
      transform: translateY(-10px);
      transition: all 0.5s ease;
      z-index: 1000;
    }
    .popup.show {
      opacity: 1;
      transform: translateY(0);
    }
    .popup.error {
      background: #e74c3c;
    }
    .profile-pic-container {
      text-align: center;
      margin-bottom: 20px;
      position: relative;
    }
    .profile-pic {
      width: 120px;
      height: 120px;
      border-radius: 50%;
      border: 3px solid darkgreen;
      object-fit: cover;
    }
    .sidebar .image img {
        width: 40px;
        height: 40px;
        border-radius: 50%;
        object-fit: cover;
    }
    
    #profile_pic {
        position: absolute;
        width: 1px;
        height: 1px;
        padding: 0;
        margin: -1px;
        overflow: hidden;
        clip: rect(0, 0, 0, 0);
        border: 0;
    }
    .upload-label {
        display: none; 
        margin-top: 8px;
        color: darkgreen;
        cursor: pointer;
        font-size: 14px;
        font-weight: 500;
        transition: color 0.3s;
    }
    .upload-label:hover {
        color: #0b4e12;
    }
    .editable-upload .upload-label {
        display: inline-block; 
    }

    form {
      display: grid;
      grid-template-columns: 1fr 1fr;
      gap: 15px;
    }
    label {
      font-size: 14px;
      font-weight: 500;
      display: block;
      margin-bottom: 5px;
    }
    input, select { 
      width: 100%;
      padding: 8px;
      border: 1px solid #ccc;
      border-radius: 6px;
      font-size: 14px;
      color: #333;
      box-sizing: border-box;
    }
    input:disabled, select:disabled {
      background: #f4f4f4;
      color: #555;
      cursor: not-allowed;
    }
    .read-only-display {
        padding: 8px 10px;
        background: #f4f4f4;
        border: 1px solid #e0e0e0;
        border-radius: 6px;
        font-size: 14px;
        color: #555;
        min-height: 34px;
        display: block; 
    }
    .full {
      grid-column: 1 / 3;
      text-align: center;
    }
    .btn {
      background: darkgreen;
      color: white;
      border: none;
      padding: 10px 25px;
      border-radius: 6px;
      cursor: pointer;
      font-weight: 600;
      transition: 0.3s;
      margin: 0 5px;
    }
    .btn:hover {
      background: #0b4e12;
    }
    .btn:disabled {
      background: #ccc;
      cursor: not-allowed;
    }
    .verification-input-group {
        display: flex;
        gap: 5px;
        align-items: flex-end;
    }
    .verification-input-group input {
        flex-grow: 1;
        width: auto;
    }
    .verify-btn {
        padding: 8px 10px;
        font-size: 13px;
        height: 36px;
        background: orange;
        color: white;
        border: none;
        border-radius: 6px;
        cursor: pointer;
        transition: 0.3s;
        display: none;
        white-space: nowrap;
    }
    .verify-btn:hover {
        background: #cc8400;
    }
    .verified-badge {
        color: green;
        font-weight: bold;
        padding: 8px 10px;
        height: 36px;
        display: none;
        align-items: center;
        position: relative; 
        line-height: 1;
        margin-top: 5px;
    }
    
    .modal {
        display: none;
        position: fixed;
        z-index: 5000;
        left: 0;
        top: 0;
        width: 100%;
        height: 100%;
        overflow: auto;
        background-color: rgba(0,0,0,0.4);
        justify-content: center;
        align-items: center;
    }
    .modal-content {
        background-color: #fefefe;
        margin: auto;
        padding: 20px;
        border: 1px solid #888;
        width: 80%;
        max-width: 400px;
        border-radius: 10px;
        box-shadow: 0 5px 15px rgba(0,0,0,0.3);
        text-align: center;
    }
    .modal-content h3 {
        color: darkgreen;
        margin-top: 0;
    }
    #otp-input {
        width: 100%;
        text-align: center;
        letter-spacing: 15px;
        font-size: 20px;
        padding: 12px;
        margin: 10px 0;
    }
    </style>
</head>

<body>
    <nav class="sidebar close">
        <header>
            <div class="image-text">
                <span class="image"><img src="bpc-logo.png" alt="logo"></span>

               <div class="text header-text">
    <span class="name"><?php echo htmlspecialchars($student['full_name'] ?? 'Student'); ?></span>
<span class="role"><?php echo htmlspecialchars($student['course'] ?? ''); ?> <?php echo htmlspecialchars($student['year'] ?? ''); ?> - <?php echo htmlspecialchars($student['section'] ?? ''); ?></span>
</div>
            </div>
            <i class='bx bx-chevron-right toggle'></i>
        </header>
        <div class="menu-bar">
            <div class="menu">
                <ul class="menu-links">
                    <li class="nav-link"><a href="student_dashboard.php"><i class='bx bx-home-alt icon'></i><span class="text nav-text">Dashboard</span></a></li>
                    <li class="nav-link active"><a href="student_profile.php"><i class='bx bx-user icon'></i><span class="text nav-text">Profile</span></a></li>
                    <li class="nav-link"><a href="student_history.php"><i class='bx bx-history icon'></i><span class="text nav-text">History</span></a></li>
                    <li class="nav-link"><a href="student_notifications.php"><i class='bx bx-bell icon'></i><span class="text nav-text">Notifications</span></a></li>
                </ul>
            </div>
            <div class="bottom-content">
                <li class="nav-link"><a href="logout.php" onclick="return confirm('Are you sure you want to logout?');"><i class='bx bx-log-out icon'></i><span class="text nav-text">Logout</span></a></li>
            </div>
        </div>
    </nav>

    <section class="home">
        <?php if (!empty($msg)): ?>
        <div id="popup" class="popup show <?php echo (strpos($msg, 'Error') !== false) ? 'error' : ''; ?>">
            <?= htmlspecialchars($msg) ?>
        </div>
        <?php endif; ?>

        <div class="container">
            <h2>STUDENT PROFILE</h2>
            
            <!-- Current Term Display -->
            <div class="term-display">
                <strong>Current Term:</strong> 
                <?php echo htmlspecialchars($student['semester'] ?? 'Not Set'); ?> | 
                <?php echo htmlspecialchars($student['school_year'] ?? 'Not Set'); ?>
            </div>
            
           <div class="profile-pic-container" id="picContainer">
    <img src="uploads/<?php echo $current_profile_pic; ?>" class="profile-pic" id="preview">
    <label for="profile_pic" id="changePhotoLabel" class="upload-label" style="display: inline-block;">
        <i class='bx bx-camera'></i> Change Photo
    </label>
</div>

<form method="POST" enctype="multipart/form-data" id="uploadForm" style="display: none;">
    <input type="file" name="profile_pic" id="profile_pic" accept="image/png,image/jpeg,image/jpg">
    <button type="submit" name="upload_picture" id="uploadBtn" style="display: none;"></button>
</form>
                
                <div>
                    <label>Full Name:</label>
                    <div class="read-only-display"><?= htmlspecialchars($student['full_name'] ?? 'N/A') ?></div>
                </div>
                         <div>
    <label>Course - Year - Section:</label>
    <div class="read-only-display">
        <?= htmlspecialchars($student['course'] ?? 'N/A') ?> - 
        <?= htmlspecialchars($student['year'] ?? 'N/A') ?> - 
        <?= htmlspecialchars($student['section'] ?? 'N/A') ?>
    </div>
</div>


<div>
    <label>Email Address:</label>
    <div class="read-only-display" style="display: flex; justify-content: space-between; align-items: center;">
        <span id="emailDisplay">
            <?= htmlspecialchars($student['email'] ?? 'N/A') ?>
            <?php if($student['email_verified'] == 1): ?>
                <span style="color: green; font-size: 12px; font-weight: 600; margin-left: 8px;">✅ Verified</span>
            <?php else: ?>
                <span style="color: #856404; font-size: 12px; font-weight: 600; margin-left: 8px;">⚠️ Not Verified</span>
            <?php endif; ?>
        </span>
        <button type="button" class="change-btn" onclick="openChangeEmailModal()" 
                style="background: #00a859; color: white; border: none; padding: 5px 12px; border-radius: 5px; cursor: pointer; font-size: 12px;">
            <i class='bx bx-edit'></i> Change
        </button>
    </div>
</div>

<div>
    <label>Student ID:</label>
    <div class="read-only-display">
        <span><?= htmlspecialchars($username ?? 'N/A') ?></span>
    </div>
</div>

<div>
    <label>Password:</label>
    <div class="read-only-display" style="display: flex; justify-content: space-between; align-items: center;">
        <span>
            ••••••••
            <?php if(!empty($student['password_last_updated'])): ?>
                <span style="font-size: 11px; color: #888; margin-left: 8px;">
                    Last changed: <?= date('M d, Y h:i A', strtotime($student['password_last_updated'])) ?>
                </span>
            <?php else: ?>
                <span style="font-size: 11px; color: #888; margin-left: 8px;">Never changed</span>
            <?php endif; ?>
        </span>
        <button type="button" class="change-btn" onclick="openChangePasswordModal()" 
                style="background: #00a859; color: white; border: none; padding: 5px 12px; border-radius: 5px; cursor: pointer; font-size: 12px;">
            <i class='bx bx-edit'></i> Change
        </button>
    </div>
</div>
                 <div>
    <label>Contact Number:</label>
    <div class="read-only-display" style="display: flex; justify-content: space-between; align-items: center;">
        <span id="contactDisplay"><?= htmlspecialchars($student['contact'] ?? 'N/A') ?></span>
        <button type="button" class="change-btn" onclick="openChangeContactModal()" 
                style="background: #00a859; color: white; border: none; padding: 5px 12px; border-radius: 5px; cursor: pointer; font-size: 12px;">
            <i class='bx bx-edit'></i> Change
        </button>
    </div>
</div>


            </form>
        </div>

        <!-- OTP Modal -->
        <div id="otpModal" class="modal">
            <div class="modal-content">
                <h3>Verify Email</h3>
                <p>An OTP has been sent to <strong id="otpDestinationDisplay"></strong>.</p>
                <input type="hidden" id="otp_type_hidden">
                <input type="text" id="otp-input" placeholder="Enter 6-digit OTP" maxlength="6" oninput="this.value=this.value.replace(/[^0-9]/g,'').slice(0,6)">
                <p id="otpTimer" style="font-size: 12px; color: #555; margin-top: 10px;">Resend in 60s</p>
                <button type="button" class="btn" id="verifyModalBtn" onclick="verifyOTP()">Verify Code</button>
                <button type="button" class="btn" style="background: #ccc;" onclick="closeModal('otpModal')">Cancel</button>
            </div>
        </div>


<!-- Username OTP Verification Modal -->
<div id="usernameOtpModal" class="modal">
    <div class="modal-content">
        <h3>Verify Username Change</h3>
        <p>An OTP has been sent to <strong id="usernameOtpDestination">your registered email</strong>.</p>
        <input type="text" id="usernameOtpInput" placeholder="Enter 6-digit OTP" maxlength="6" 
               oninput="this.value=this.value.replace(/[^0-9]/g,'').slice(0,6)" 
               style="width: 100%; padding: 12px; margin: 10px 0; border: 1px solid #ccc; border-radius: 5px; font-size: 18px; text-align: center; letter-spacing: 8px;">
        <p id="usernameOtpTimer" style="font-size: 12px; color: #555; margin-top: 10px;">Resend in 60s</p>
        <div style="display: flex; gap: 10px; margin-top: 15px;">
            <button type="button" class="btn" id="verifyUsernameBtn" onclick="verifyUsernameOTP()" style="flex: 1;">Verify</button>
            <button type="button" class="btn" style="background: #ccc; flex: 1;" onclick="closeUsernameOtpModal()">Cancel</button>
        </div>
    </div>
</div>
<!-- Contact Number Change Modal -->
<div id="contactModal" class="modal">
    <div class="modal-content">
        <h3>Change Contact Number</h3>
        <p>Enter your new contact number. An OTP will be sent to verify.</p>
        <input type="text" id="newContactInput" placeholder="Enter new contact number (11 digits)" 
               maxlength="11"
               oninput="this.value = this.value.replace(/[^0-9]/g, '').slice(0, 11)"
               style="width: 100%; padding: 10px; margin: 10px 0; border: 1px solid #ccc; border-radius: 5px; font-size: 14px;">
        <p style="font-size: 12px; color: #666;">11-digit Philippine mobile number</p>
        <div style="display: flex; gap: 10px; margin-top: 15px;">
            <button type="button" class="btn" onclick="sendContactOTP()" style="flex: 1;">Send OTP</button>
            <button type="button" class="btn" style="background: #ccc; flex: 1;" onclick="closeContactModal()">Cancel</button>
        </div>
    </div>
</div>

<!-- Contact OTP Verification Modal -->
<div id="contactOtpModal" class="modal">
    <div class="modal-content">
        <h3>Verify Contact Number</h3>
        <p>An OTP has been sent to <strong id="contactOtpDestination">your new number</strong>.</p>
        <input type="text" id="contactOtpInput" placeholder="Enter 6-digit OTP" maxlength="6" 
               oninput="this.value=this.value.replace(/[^0-9]/g,'').slice(0,6)" 
               style="width: 100%; padding: 12px; margin: 10px 0; border: 1px solid #ccc; border-radius: 5px; font-size: 18px; text-align: center; letter-spacing: 8px;">
        <p id="contactOtpTimer" style="font-size: 12px; color: #555; margin-top: 10px;">Resend in 60s</p>
        <div style="display: flex; gap: 10px; margin-top: 15px;">
            <button type="button" class="btn" id="verifyContactBtn" onclick="verifyContactOTP()" style="flex: 1;">Verify</button>
            <button type="button" class="btn" style="background: #ccc; flex: 1;" onclick="closeContactOtpModal()">Cancel</button>
        </div>
    </div>
</div>
<!-- Email Change Modal -->
<div id="emailModal" class="modal">
    <div class="modal-content">
        <h3>Change Email Address</h3>
        <p>Enter your new email address. An OTP will be sent to verify.</p>
        <input type="email" id="newEmailInput" placeholder="Enter new email address" 
               style="width: 100%; padding: 10px; margin: 10px 0; border: 1px solid #ccc; border-radius: 5px; font-size: 14px;">
        <p style="font-size: 12px; color: #666;">A verification code will be sent to this email</p>
        <div style="display: flex; gap: 10px; margin-top: 15px;">
            <button type="button" class="btn" onclick="sendEmailOTP()" style="flex: 1;">Send OTP</button>
            <button type="button" class="btn" style="background: #ccc; flex: 1;" onclick="closeEmailModal()">Cancel</button>
        </div>
    </div>
</div>

<!-- Email OTP Verification Modal -->
<div id="emailOtpModal" class="modal">
    <div class="modal-content">
        <h3>Verify Email Address</h3>
        <p>An OTP has been sent to <strong id="emailOtpDestination">your new email</strong>.</p>
        <input type="text" id="emailOtpInput" placeholder="Enter 6-digit OTP" maxlength="6" 
               oninput="this.value=this.value.replace(/[^0-9]/g,'').slice(0,6)" 
               style="width: 100%; padding: 12px; margin: 10px 0; border: 1px solid #ccc; border-radius: 5px; font-size: 18px; text-align: center; letter-spacing: 8px;">
        <p id="emailOtpTimer" style="font-size: 12px; color: #555; margin-top: 10px;">Resend in 60s</p>
        <div style="display: flex; gap: 10px; margin-top: 15px;">
            <button type="button" class="btn" id="verifyEmailBtn" onclick="verifyEmailOTP()" style="flex: 1;">Verify</button>
            <button type="button" class="btn" style="background: #ccc; flex: 1;" onclick="closeEmailOtpModal()">Cancel</button>
        </div>
    </div>
</div>
<!-- Password Change Modal - Step 1: Enter Passwords -->
<div id="passwordModal" class="modal">
    <div class="modal-content">
        <h3>Change Password</h3>
        <p>Enter your current and new password.</p>
        
 <div style="position:relative; margin: 10px 0;">
            <input type="password" id="currentPasswordInput" placeholder="Current Password"
                   style="width:100%; padding:10px; padding-right:40px; border:1px solid #ccc; border-radius:5px; font-size:14px; box-sizing:border-box;">
            <i class='bx bx-hide' onclick="togglePassword('currentPasswordInput', this)"
               style="position:absolute; right:10px; top:50%; transform:translateY(-50%); cursor:pointer; color:#888; font-size:18px;"></i>
        </div>

        <div style="position:relative; margin: 10px 0;">
            <input type="password" id="newPasswordInput" placeholder="New Password"
                   style="width:100%; padding:10px; padding-right:40px; border:1px solid #ccc; border-radius:5px; font-size:14px; box-sizing:border-box;">
            <i class='bx bx-hide' onclick="togglePassword('newPasswordInput', this)"
               style="position:absolute; right:10px; top:50%; transform:translateY(-50%); cursor:pointer; color:#888; font-size:18px;"></i>
        </div>

        <div style="position:relative; margin: 10px 0;">
            <input type="password" id="confirmPasswordInput" placeholder="Confirm New Password"
                   style="width:100%; padding:10px; padding-right:40px; border:1px solid #ccc; border-radius:5px; font-size:14px; box-sizing:border-box;">
            <i class='bx bx-hide' onclick="togglePassword('confirmPasswordInput', this)"
               style="position:absolute; right:10px; top:50%; transform:translateY(-50%); cursor:pointer; color:#888; font-size:18px;"></i>
        </div>
        <p style="font-size: 12px; color: #666;">Min. 8 characters with at least 1 uppercase, 1 number, and 1 special character</p>
        
        <div style="display: flex; gap: 10px; margin-top: 15px;">
            <button type="button" class="btn" onclick="sendPasswordOTP()" style="flex: 1;">Change Password</button>
            <button type="button" class="btn" style="background: #ccc; flex: 1;" onclick="closePasswordModal()">Cancel</button>
        </div>
    </div>
</div>

    </section>
    
    <script>


// UI Elements
const picContainer = document.getElementById('picContainer');
const preview = document.getElementById('preview');
const uploadLabel = document.getElementById('changePhotoLabel');
const profilePicInput = document.getElementById('profile_pic'); 
const uploadForm = document.getElementById('uploadForm');
const sidebarImage = document.querySelector('.sidebar .image img');

// Profile picture upload handler
if (uploadLabel) {
    uploadLabel.addEventListener('click', (e) => {
        e.preventDefault();
        profilePicInput.click(); 
    });
}

profilePicInput.addEventListener('change', function(event) {
    if (this.files.length > 0) {
        const file = this.files[0];
        const validTypes = ['image/png', 'image/jpeg', 'image/jpg'];
        const maxSize = 5 * 1024 * 1024; // 5MB
        
        if (!validTypes.includes(file.type)) {
            alert('Invalid file type! Please upload PNG, JPEG, or JPG only.');
            this.value = ''; 
            return;
        }
        
        if (file.size > maxSize) {
            alert('File size too large! Maximum 5MB allowed.');
            this.value = '';
            return;
        }
        
        // Preview image
        previewImage(event);
        
        // Ask for confirmation
        if (confirm('Upload this photo as your profile picture?')) {
    document.getElementById('uploadBtn').click();
        } else {
            // Reset if cancelled
            this.value = '';
            preview.src = '<?php echo "uploads/" . $current_profile_pic; ?>';
            sidebarImage.src = '<?php echo "uploads/" . $current_profile_pic; ?>';
        }
    }
});

function previewImage(event) {
    const file = event.target.files[0];
    if (file) {
        const reader = new FileReader();
        reader.onload = function(){
            preview.src = reader.result;
            sidebarImage.src = reader.result;
        }
        reader.readAsDataURL(file);
    }
}

const popup = document.getElementById('popup');
if (popup) {
    setTimeout(() => {
        popup.classList.remove('show');
    }, 3000);
}

const sidebar = document.querySelector('nav.sidebar');
const toggle = document.querySelector(".toggle"); 

if (toggle) {
    toggle.addEventListener("click", () => {
        sidebar.classList.toggle("close"); 
    });
}
    // ========== USERNAME CHANGE FUNCTIONS (NEW) ==========
let usernameResendTimer;

function openChangeUsernameModal() {
    document.getElementById('newUsernameInput').value = '';
    document.getElementById('usernameModal').style.display = 'flex';
}

function closeUsernameModal() {
    document.getElementById('usernameModal').style.display = 'none';
}

function closeUsernameOtpModal() {
    document.getElementById('usernameOtpModal').style.display = 'none';
    clearInterval(usernameResendTimer);
}

function isValidUsername(username) {
    return /^[a-zA-Z0-9_]{4,20}$/.test(username);
}

function sendUsernameOTP() {
    const newUsername = document.getElementById('newUsernameInput').value.trim();
    
    if (!newUsername) {
        alert('Please enter a username');
        return;
    }
    
    if (!isValidUsername(newUsername)) {
        alert('Username must be 4-20 characters (letters, numbers, underscore only)');
        return;
    }
    
    // Send OTP request
    fetch('handle_otp.php', {
        method: 'POST',
        headers: {'Content-Type': 'application/json'},
        body: JSON.stringify({
            action: 'send',
            type: 'username',
            destination: newUsername
        })
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            // Close username input modal, open OTP modal
            closeUsernameModal();
            document.getElementById('usernameOtpInput').value = '';
            document.getElementById('usernameOtpModal').style.display = 'flex';
            startUsernameOtpTimer(60);
            alert('OTP sent to your registered email!');
        } else {
            alert('Error: ' + data.message);
        }
    })
    .catch(error => {
        console.error('Error:', error);
        alert('Failed to send OTP. Please try again.');
    });
}

function verifyUsernameOTP() {
    const otp = document.getElementById('usernameOtpInput').value.trim();
    const newUsername = document.getElementById('newUsernameInput').value.trim();
    
    if (otp.length !== 6) {
        alert('Please enter the 6-digit OTP');
        return;
    }
    
    const verifyBtn = document.getElementById('verifyUsernameBtn');
    verifyBtn.textContent = 'Verifying...';
    verifyBtn.disabled = true;
    
    fetch('handle_otp.php', {
        method: 'POST',
        headers: {'Content-Type': 'application/json'},
        body: JSON.stringify({
            action: 'verify',
            type: 'username',
            destination: newUsername,
            otp: otp
        })
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            closeUsernameOtpModal();
            document.getElementById('usernameDisplay').textContent = data.new_username;
            alert('Username updated successfully! ✅');
            // Reload page to update session
            setTimeout(() => location.reload(), 1500);
        } else {
            alert('Error: ' + data.message);
        }
    })
    .catch(error => {
        console.error('Error:', error);
        alert('Verification failed. Please try again.');
    })
    .finally(() => {
        verifyBtn.textContent = 'Verify';
        verifyBtn.disabled = false;
    });
}

function startUsernameOtpTimer(duration) {
    clearInterval(usernameResendTimer);
    let timer = duration;
    const timerDisplay = document.getElementById('usernameOtpTimer');
    
    timerDisplay.textContent = `Resend in ${timer}s`;
    timerDisplay.style.color = '#555';
    
    usernameResendTimer = setInterval(() => {
        timer--;
        if (timer > 0) {
            timerDisplay.textContent = `Resend in ${timer}s`;
        } else {
            clearInterval(usernameResendTimer);
            timerDisplay.innerHTML = '<button onclick="resendUsernameOTP()" class="btn" style="background: #5cb85c; padding: 8px 15px; font-size: 13px;">Resend Code</button>';
        }
    }, 1000);
}

function resendUsernameOTP() {
    closeUsernameOtpModal();
    sendUsernameOTP();
}
// ========== PASSWORD CHANGE FUNCTIONS WITH OTP ==========
let passwordResendTimer;
let tempPasswordData = {}; // Store password data temporarily

function openChangePasswordModal() {
    document.getElementById('currentPasswordInput').value = '';
    document.getElementById('newPasswordInput').value = '';
    document.getElementById('confirmPasswordInput').value = '';
    document.getElementById('passwordModal').style.display = 'flex';
}

function closePasswordModal() {
    document.getElementById('passwordModal').style.display = 'none';
}

function closePasswordOtpModal() {
    document.getElementById('passwordOtpModal').style.display = 'none';
    clearInterval(passwordResendTimer);
}

function sendPasswordOTP() {
    const currentPassword = document.getElementById('currentPasswordInput').value.trim();
    const newPassword     = document.getElementById('newPasswordInput').value.trim();
    const confirmPassword = document.getElementById('confirmPasswordInput').value.trim();

    if (!currentPassword || !newPassword || !confirmPassword) {
        alert('Please fill in all password fields');
        return;
    }
   if (newPassword.length < 8) {
    alert('New password must be at least 8 characters');
    return;
}
if (!/[A-Z]/.test(newPassword)) {
    alert('Password must contain at least one uppercase letter');
    return;
}
if (!/[0-9]/.test(newPassword)) {
    alert('Password must contain at least one number');
    return;
}
if (!/[\W_]/.test(newPassword)) {
    alert('Password must contain at least one special character');
    return;
}
    if (newPassword !== confirmPassword) {
        alert('New passwords do not match');
        return;
    }
    if (currentPassword === newPassword) {
        alert('New password must be different from current password');
        return;
    }
    const defaultPasswords = ['@Student01', '@Signatory01'];
    if (defaultPasswords.includes(newPassword)) {
        alert('You cannot use the default system password. Please choose a different password.');
        return;
    }

    const btn = document.querySelector('#passwordModal .btn');
    btn.textContent = 'Saving...';
    btn.disabled = true;

    fetch('handle_otp.php', {
        method: 'POST',
        headers: {'Content-Type': 'application/json'},
        body: JSON.stringify({
            action: 'verify',
            type: 'password',
            otp: '000000',
            current_password: currentPassword,
            new_password: newPassword
        })
    })
    .then(r => r.json())
    .then(data => {
        if (data.success) {
            closePasswordModal();
            alert('Password changed successfully! ✅');
            setTimeout(() => location.reload(), 1000);
        } else {
            alert('Error: ' + data.message);
        }
    })
    .catch(() => alert('Failed. Please try again.'))
    .finally(() => {
        btn.textContent = 'Save Password';
        btn.disabled = false;
    });
}

function verifyPasswordOTP() {
    const otp = document.getElementById('passwordOtpInput').value.trim();
    
    if (otp.length !== 6) {
        alert('Please enter the 6-digit OTP');
        return;
    }
    
    const verifyBtn = document.getElementById('verifyPasswordBtn');
    verifyBtn.textContent = 'Verifying...';
    verifyBtn.disabled = true;
    
    fetch('handle_otp.php', {
        method: 'POST',
        headers: {'Content-Type': 'application/json'},
        body: JSON.stringify({
            action: 'verify',
            type: 'password',
            destination: '',
            otp: otp,
            current_password: tempPasswordData.current,
            new_password: tempPasswordData.new
        })
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            closePasswordOtpModal();
            tempPasswordData = {}; // Clear temp data
            alert('Password changed successfully! ✅');
        } else {
            alert('Error: ' + data.message);
        }
    })
    .catch(error => {
        console.error('Error:', error);
        alert('Verification failed. Please try again.');
    })
    .finally(() => {
        verifyBtn.textContent = 'Verify & Change';
        verifyBtn.disabled = false;
    });
}

function startPasswordOtpTimer(duration) {
    clearInterval(passwordResendTimer);
    let timer = duration;
    const timerDisplay = document.getElementById('passwordOtpTimer');
    
    timerDisplay.textContent = `Resend in ${timer}s`;
    timerDisplay.style.color = '#555';
    
    passwordResendTimer = setInterval(() => {
        timer--;
        if (timer > 0) {
            timerDisplay.textContent = `Resend in ${timer}s`;
        } else {
            clearInterval(passwordResendTimer);
            timerDisplay.innerHTML = '<button onclick="resendPasswordOTP()" class="btn" style="background: #5cb85c; padding: 8px 15px; font-size: 13px;">Resend Code</button>';
        }
    }, 1000);
}

function resendPasswordOTP() {
    closePasswordOtpModal();
    sendPasswordOTP();
}
// ========== CONTACT NUMBER CHANGE FUNCTIONS ==========
let contactResendTimer;

function openChangeContactModal() {
    document.getElementById('newContactInput').value = '';
    document.getElementById('contactModal').style.display = 'flex';
}

function closeContactModal() {
    document.getElementById('contactModal').style.display = 'none';
}

function closeContactOtpModal() {
    document.getElementById('contactOtpModal').style.display = 'none';
    clearInterval(contactResendTimer);
}

function sendContactOTP() {
    const newContact = document.getElementById('newContactInput').value.trim();
    
    if (!newContact) {
        alert('Please enter a contact number');
        return;
    }
    
    if (newContact.length !== 11) {
        alert('Contact number must be exactly 11 digits');
        return;
    }
    
    // Send OTP via SMS (you'll need to implement SMS sending in handle_otp.php)
    fetch('handle_otp.php', {
        method: 'POST',
        headers: {'Content-Type': 'application/json'},
        body: JSON.stringify({
            action: 'send',
            type: 'contact',
            destination: newContact
        })
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            closeContactModal();
            document.getElementById('contactOtpInput').value = '';
            document.getElementById('contactOtpDestination').textContent = newContact;
            document.getElementById('contactOtpModal').style.display = 'flex';
            startContactOtpTimer(60);
            alert('OTP sent to your new contact number!');
        } else {
            alert('Error: ' + data.message);
        }
    })
    .catch(error => {
        console.error('Error:', error);
        alert('Failed to send OTP. Please try again.');
    });
}

function verifyContactOTP() {
    const otp = document.getElementById('contactOtpInput').value.trim();
    const newContact = document.getElementById('newContactInput').value.trim();
    
    if (otp.length !== 6) {
        alert('Please enter the 6-digit OTP');
        return;
    }
    
    const verifyBtn = document.getElementById('verifyContactBtn');
    verifyBtn.textContent = 'Verifying...';
    verifyBtn.disabled = true;
    
    fetch('handle_otp.php', {
        method: 'POST',
        headers: {'Content-Type': 'application/json'},
        body: JSON.stringify({
            action: 'verify',
            type: 'contact',
            destination: newContact,
            otp: otp
        })
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            closeContactOtpModal();
            document.getElementById('contactDisplay').textContent = newContact;
            alert('Contact number updated successfully! ✅');
            setTimeout(() => location.reload(), 1500);
        } else {
            alert('Error: ' + data.message);
        }
    })
    .catch(error => {
        console.error('Error:', error);
        alert('Verification failed. Please try again.');
    })
    .finally(() => {
        verifyBtn.textContent = 'Verify';
        verifyBtn.disabled = false;
    });
}

function startContactOtpTimer(duration) {
    clearInterval(contactResendTimer);
    let timer = duration;
    const timerDisplay = document.getElementById('contactOtpTimer');
    
    timerDisplay.textContent = `Resend in ${timer}s`;
    timerDisplay.style.color = '#555';
    
    contactResendTimer = setInterval(() => {
        timer--;
        if (timer > 0) {
            timerDisplay.textContent = `Resend in ${timer}s`;
        } else {
            clearInterval(contactResendTimer);
            timerDisplay.innerHTML = '<button onclick="resendContactOTP()" class="btn" style="background: #5cb85c; padding: 8px 15px; font-size: 13px;">Resend Code</button>';
        }
    }, 1000);
}

function resendContactOTP() {
    closeContactOtpModal();
    sendContactOTP();
}

// ========== EMAIL CHANGE FUNCTIONS ==========
let emailResendTimer;

function openChangeEmailModal() {
    document.getElementById('newEmailInput').value = '';
    document.getElementById('emailModal').style.display = 'flex';
}

function closeEmailModal() {
    document.getElementById('emailModal').style.display = 'none';
}

function closeEmailOtpModal() {
    document.getElementById('emailOtpModal').style.display = 'none';
    clearInterval(emailResendTimer);
}

function sendEmailOTP() {
    const newEmail = document.getElementById('newEmailInput').value.trim();
    
    if (!newEmail) {
        alert('Please enter an email address');
        return;
    }
    
    if (!/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(newEmail)) {
        alert('Please enter a valid email address');
        return;
    }
    
    fetch('handle_otp.php', {
        method: 'POST',
        headers: {'Content-Type': 'application/json'},
        body: JSON.stringify({
            action: 'send',
            type: 'email',
            destination: newEmail
        })
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            closeEmailModal();
            document.getElementById('emailOtpInput').value = '';
            document.getElementById('emailOtpDestination').textContent = newEmail;
            document.getElementById('emailOtpModal').style.display = 'flex';
            startEmailOtpTimer(60);
            alert('OTP sent to your new email!');
        } else {
            alert('Error: ' + data.message);
        }
    })
    .catch(error => {
        console.error('Error:', error);
        alert('Failed to send OTP. Please try again.');
    });
}

function verifyEmailOTP() {
    const otp = document.getElementById('emailOtpInput').value.trim();
    const newEmail = document.getElementById('newEmailInput').value.trim();
    
    if (otp.length !== 6) {
        alert('Please enter the 6-digit OTP');
        return;
    }
    
    const verifyBtn = document.getElementById('verifyEmailBtn');
    verifyBtn.textContent = 'Verifying...';
    verifyBtn.disabled = true;
    
    fetch('handle_otp.php', {
        method: 'POST',
        headers: {'Content-Type': 'application/json'},
        body: JSON.stringify({
            action: 'verify',
            type: 'email',
            destination: newEmail,
            otp: otp
        })
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            closeEmailOtpModal();
            document.getElementById('emailDisplay').textContent = newEmail;
            alert('Email updated successfully! ✅');
            setTimeout(() => location.reload(), 1500);
        } else {
            alert('Error: ' + data.message);
        }
    })
    .catch(error => {
        console.error('Error:', error);
        alert('Verification failed. Please try again.');
    })
    .finally(() => {
        verifyBtn.textContent = 'Verify';
        verifyBtn.disabled = false;
    });
}

function startEmailOtpTimer(duration) {
    clearInterval(emailResendTimer);
    let timer = duration;
    const timerDisplay = document.getElementById('emailOtpTimer');
    
    timerDisplay.textContent = `Resend in ${timer}s`;
    timerDisplay.style.color = '#555';
    
    emailResendTimer = setInterval(() => {
        timer--;
        if (timer > 0) {
            timerDisplay.textContent = `Resend in ${timer}s`;
        } else {
            clearInterval(emailResendTimer);
            timerDisplay.innerHTML = '<button onclick="resendEmailOTP()" class="btn" style="background: #5cb85c; padding: 8px 15px; font-size: 13px;">Resend Code</button>';
        }
    }, 1000);
}

function resendEmailOTP() {
    closeEmailOtpModal();
    sendEmailOTP();
}
function togglePassword(inputId, icon) {
    const input = document.getElementById(inputId);
    if (input.type === 'password') {
        input.type = 'text';
        icon.classList.replace('bx-hide', 'bx-show');
    } else {
        input.type = 'password';
        icon.classList.replace('bx-show', 'bx-hide');
    }
}
</script>
</body>
</html>