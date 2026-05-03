<?php
// signatory_profile.php
session_start();
if (!isset($_SESSION['role']) || $_SESSION['role'] != "signatory") {
    header("Location: login.php");
    exit();
}

require 'vendor/autoload.php';
include 'conn.php';

$username = trim($_SESSION['username']);
$msg = '';

// --- 1. Fetch Existing Data (Users Table) ---
// FIX: was using $stmt_reload to prepare but then $stmt->bind_param (wrong variable — now unified as $stmt)
// FIX: replaced assigned_course with section (actual column in users table)
$stmt = $conn->prepare("SELECT full_name, contact, email, email_verified, password_last_updated, signatory_type, department, course, year, section, profile_pic FROM users WHERE username=?");
$stmt->bind_param("s", $username);
$stmt->execute();
$result = $stmt->get_result();
$signatory = $result->fetch_assoc();
$stmt->close();

$current_profile_pic = htmlspecialchars($signatory['profile_pic'] ?: 'default.png');

if (!$signatory) {
    $msg = "Error: Signatory profile not found in the database.";
}

// Helper: parse section field (e.g. "BSIS|1st Year|A,BSIS|4th Year|A") into readable labels
function parseAssignedSections($raw) {
    if (empty($raw)) return 'N/A';
    $groups = explode(',', $raw);
    $labels = [];
    foreach ($groups as $group) {
        $parts = explode('|', trim($group));
        if (count($parts) === 3) {
            $labels[] = $parts[0] . ' — ' . $parts[1] . ', Section ' . $parts[2];
        } elseif (!empty(trim($group))) {
            $labels[] = trim($group);
        }
    }
    return implode(' | ', $labels);
}

// --- 2. Handle Profile Picture Upload ---
if (isset($_POST['upload_picture']) || (isset($_FILES['profile_pic']) && $_FILES['profile_pic']['error'] === UPLOAD_ERR_OK && $signatory)) {
    
    $profile_pic = $signatory['profile_pic'] ?? 'default.png'; 
    $targetDir = "uploads/";

    $msg = "DEBUG: POST reached | FILES error code=" . ($_FILES['profile_pic']['error'] ?? 'NO FILE') . 
           " | signatory=" . ($signatory ? 'found' : 'null') .
           " | upload_picture=" . (isset($_POST['upload_picture']) ? 'set' : 'not set');

    if (isset($_FILES['profile_pic']) && $_FILES['profile_pic']['error'] === UPLOAD_ERR_OK) {
        $fileInfo = $_FILES["profile_pic"];
        $allowedTypes = ['image/png', 'image/jpeg', 'image/jpg'];
        $maxFileSize = 5 * 1024 * 1024; // 5MB
        
        if (!in_array($fileInfo['type'], $allowedTypes)) {
            $msg = "Error: Only PNG, JPEG, and JPG files are allowed.";
        } elseif ($fileInfo['size'] > $maxFileSize) {
            $msg = "Error: File size must not exceed 5MB.";
        } else {
            if (!is_dir($targetDir)) { 
                mkdir($targetDir, 0777, true); 
            }
            
            $fileName = time() . '_' . uniqid() . '_' . basename($fileInfo["name"]);
            $targetFilePath = $targetDir . $fileName;
            
            if (move_uploaded_file($fileInfo["tmp_name"], $targetFilePath)) {
                // Delete old profile pic if not default
                if ($signatory['profile_pic'] && $signatory['profile_pic'] !== 'default.png' && file_exists($targetDir . $signatory['profile_pic'])) {
                    unlink($targetDir . $signatory['profile_pic']);
                }
                $profile_pic = $fileName;
                
                $sql = "UPDATE users SET profile_pic=? WHERE username=?";
$stmt = $conn->prepare($sql);
$stmt->bind_param("ss", $profile_pic, $username);
$conn->autocommit(true);

$execute_result = $stmt->execute();
$affected = $stmt->affected_rows;
$msg = "DEBUG: execute=" . ($execute_result ? 'true' : 'false') . 
       " | affected_rows=" . $affected . 
       " | profile_pic=" . $profile_pic . 
       " | username=" . $username . 
       " | error=" . $stmt->error;

if ($execute_result && $affected > 0) {
    $msg = "Profile picture updated successfully! 📸";
                    
                    // FIX: reload query now uses section instead of assigned_course
$stmt_reload = $conn->prepare("SELECT full_name, contact, email, email_verified, password_last_updated, signatory_type, department, course, year, section, profile_pic FROM users WHERE username=?");
                    $stmt_reload->bind_param("s", $username);
                    $stmt_reload->execute();
                    $result_reload = $stmt_reload->get_result();
                    $signatory = $result_reload->fetch_assoc();
                    $current_profile_pic = htmlspecialchars($signatory['profile_pic'] ?: 'default.png');
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
<?php
$signatoryInfo = $signatoryInfo ?? $signatory ?? [];
$signatoryFullName = $signatoryFullName ?? ($signatoryInfo['full_name'] ?? 'Signatory');
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width,initial-scale=1.0"> 
    <title>Signatory Profile | Smart Clearance System</title>
    
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
    .role-display {
      text-align: center;
      background: #e8f5e9;
      padding: 12px;
      border-radius: 8px;
      margin-bottom: 20px;
      border: 2px solid #2d5016;
    }
    .role-display strong {
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
        display: inline-block; 
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
    </style>
</head>

<body>
    <?php include 'sidebar_signa.php'; ?>
    <section class="home">
        <?php if (!empty($msg)): ?>
        <div id="popup" class="popup show <?php echo (strpos($msg, 'Error') !== false) ? 'error' : ''; ?>">
            <?= htmlspecialchars($msg) ?>
        </div>
        <?php endif; ?>

        <div class="container">
            <h2>SIGNATORY PROFILE</h2>
            
            <!-- Signatory Type Display -->
            <div class="role-display">
                <strong>Role:</strong> 
                <?php echo htmlspecialchars($signatory['signatory_type'] ?? 'Not Set'); ?>
                <?php
                    // FIX: use section instead of assigned_course
                    $parsedSections = parseAssignedSections($signatory['section'] ?? '');
                    if ($parsedSections !== 'N/A'):
                ?>
                    | <strong>Assigned:</strong> <?php echo htmlspecialchars($parsedSections); ?>
                <?php endif; ?>
            </div>
            
            <div class="profile-pic-container" id="picContainer">
                <img src="uploads/<?php echo $current_profile_pic; ?>" class="profile-pic" id="preview">
                <label for="profile_pic" id="changePhotoLabel" class="upload-label">
                    <i class='bx bx-camera'></i> Change Photo
                </label>
            </div>

            <form method="POST" enctype="multipart/form-data" id="uploadForm" style="display: none;">
                <input type="file" name="profile_pic" id="profile_pic" accept="image/png,image/jpeg,image/jpg">
                <button type="submit" name="upload_picture" id="uploadBtn" style="display: none;"></button>
            </form>
                
            <form>
                <div>
                    <label>Full Name:</label>
                    <div class="read-only-display"><?= htmlspecialchars($signatory['full_name'] ?? 'N/A') ?></div>
                </div>

                <div>
                    <label>Signatory Type:</label>
                    <div class="read-only-display"><?= htmlspecialchars($signatory['signatory_type'] ?? 'N/A') ?></div>
                </div>

                <div>
    <label>Email Address:</label>
    <div class="read-only-display" style="display: flex; justify-content: space-between; align-items: center;">
        <span id="emailDisplay">
            <?= htmlspecialchars($signatory['email'] ?? 'N/A') ?>
            <?php if($signatory['email_verified'] == 1): ?>
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
                    <label>Contact Number:</label>
                    <div class="read-only-display" style="display: flex; justify-content: space-between; align-items: center;">
                        <span id="contactDisplay"><?= htmlspecialchars($signatory['contact'] ?? 'N/A') ?></span>
                        <button type="button" class="change-btn" onclick="openChangeContactModal()" 
                                style="background: #00a859; color: white; border: none; padding: 5px 12px; border-radius: 5px; cursor: pointer; font-size: 12px;">
                            <i class='bx bx-edit'></i> Change
                        </button>
                    </div>
                </div>

                <div>
                    <label>Username:</label>
                    <div class="read-only-display" style="display: flex; justify-content: space-between; align-items: center;">
                        <span id="usernameDisplay"><?= htmlspecialchars($username ?? 'N/A') ?></span>
                        <button type="button" class="change-btn" onclick="openChangeUsernameModal()" 
                                style="background: #00a859; color: white; border: none; padding: 5px 12px; border-radius: 5px; cursor: pointer; font-size: 12px;">
                            <i class='bx bx-edit'></i> Change
                        </button>
                    </div>
                </div>

              <div>
    <label>Password:</label>
    <div class="read-only-display" style="display: flex; justify-content: space-between; align-items: center;">
        <span>
            ••••••••
            <?php if(!empty($signatory['password_last_updated'])): ?>
                <span style="font-size: 11px; color: #888; margin-left: 8px;">
                    Last changed: <?= date('M d, Y h:i A', strtotime($signatory['password_last_updated'])) ?>
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

                <?php
                    // FIX: use section column; display only if not empty
                    $rawSection = $signatory['section'] ?? '';
                    $parsedSectionsDisplay = parseAssignedSections($rawSection);
                    if (!empty($rawSection)):
                ?>
                <div style="grid-column: 1 / 3;">
                    <label>Assigned Sections:</label>
                    <div class="read-only-display"><?= htmlspecialchars($parsedSectionsDisplay) ?></div>
                </div>
                <?php endif; ?>
            </form>
        </div>

        <!-- Username Change Modal -->
        <div id="usernameModal" class="modal">
            <div class="modal-content">
                <h3>Change Username</h3>
                <p>Enter your new username. An OTP will be sent to your registered email.</p>
                <input type="text" id="newUsernameInput" placeholder="Enter new username" 
                       style="width: 100%; padding: 10px; margin: 10px 0; border: 1px solid #ccc; border-radius: 5px; font-size: 14px;">
                <p style="font-size: 12px; color: #666;">4-20 characters (letters, numbers, underscore only)</p>
                <div style="display: flex; gap: 10px; margin-top: 15px;">
                    <button type="button" class="btn" onclick="sendUsernameOTP()" style="flex: 1;">Send OTP</button>
                    <button type="button" class="btn" style="background: #ccc; flex: 1;" onclick="closeUsernameModal()">Cancel</button>
                </div>
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

        <!-- Password Change Modal -->
        <div id="passwordModal" class="modal">
            <div class="modal-content">
                <h3>Change Password</h3>
                <p>Enter your current and new password. An OTP will be sent to verify.</p>
                
                <div style="position:relative; margin: 10px 0;">
    <input type="password" id="currentPasswordInput" placeholder="Current Password"
           style="width: 100%; padding: 10px; padding-right: 40px; border: 1px solid #ccc; border-radius: 5px; font-size: 14px; box-sizing: border-box;">
    <i class='bx bx-hide' onclick="togglePassword('currentPasswordInput', this)"
       style="position:absolute; right:10px; top:50%; transform:translateY(-50%); cursor:pointer; color:#888; font-size:18px;"></i>
</div>

<div style="position:relative; margin: 10px 0;">
    <input type="password" id="newPasswordInput" placeholder="New Password"
           style="width: 100%; padding: 10px; padding-right: 40px; border: 1px solid #ccc; border-radius: 5px; font-size: 14px; box-sizing: border-box;">
    <i class='bx bx-hide' onclick="togglePassword('newPasswordInput', this)"
       style="position:absolute; right:10px; top:50%; transform:translateY(-50%); cursor:pointer; color:#888; font-size:18px;"></i>
</div>

<div style="position:relative; margin: 10px 0;">
    <input type="password" id="confirmPasswordInput" placeholder="Confirm New Password"
           style="width: 100%; padding: 10px; padding-right: 40px; border: 1px solid #ccc; border-radius: 5px; font-size: 14px; box-sizing: border-box;">
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
            if (sidebarImage) sidebarImage.src = '<?php echo "uploads/" . $current_profile_pic; ?>';
        }
    }
});

function previewImage(event) {
    const file = event.target.files[0];
    if (file) {
        const reader = new FileReader();
        reader.onload = function(){
            preview.src = reader.result;
            if (sidebarImage) sidebarImage.src = reader.result;
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

// ========== USERNAME CHANGE FUNCTIONS ==========
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


function openChangePasswordModal() {
    document.getElementById('currentPasswordInput').value = '';
    document.getElementById('newPasswordInput').value = '';
    document.getElementById('confirmPasswordInput').value = '';
    document.getElementById('passwordModal').style.display = 'flex';
}

function closePasswordModal() {
    document.getElementById('passwordModal').style.display = 'none';
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
        alert('You cannot use the default system password.');
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
        btn.textContent = 'Change Password';
        btn.disabled = false;
    });
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