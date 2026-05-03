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
$stmt = $conn->prepare("SELECT full_name, sex, birthdate, contact, email, street, city, province, course, section, year, student_id, profile_pic, semester, school_year FROM users WHERE username=?");
$stmt->bind_param("s", $username);
$stmt->execute();
$result = $stmt->get_result();
$student = $result->fetch_assoc();
$stmt->close();

$current_profile_pic = htmlspecialchars($student['profile_pic'] ?: 'default.png');

if (!$student) {
    $msg = "Error: Student profile not found in the database.";
}

// --- 2. Handle Save/Update ---
if (isset($_POST['save']) && $student) {
    
    $contact = preg_replace('/[^0-9]/', '', trim($_POST['contact']));
    $new_email = trim($_POST['email']);
    $old_email = $student['email'];
    
    // Check kung nagbago ang email
    $is_email_changed = $new_email !== $old_email;

    // Validate email if changed
    if ($is_email_changed && (!isset($_SESSION['email_otp_validated']) || $_SESSION['email_otp_validated'] !== true)) {
        $msg = "Error: New email must be verified via OTP before saving.";
    } 
    
    // Proceed with update if NO error message was set
    if ($msg === '') {
        
        $email = $new_email; 
        $street = trim($_POST['street']);
        $city = trim($_POST['city']); 
        $province = trim($_POST['province']);

        // Profile Picture Handling
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
                } else {
                    $msg = "Error: Failed to upload photo. Please try again.";
                }
            }
        }
        
        // Only update if no errors
        if ($msg === '') {
            $sql = "UPDATE users SET 
                contact=?, email=?, street=?, city=?, province=?, profile_pic=?
                WHERE username=?";
            $stmt = $conn->prepare($sql);
            
            $stmt->bind_param("sssssss", 
                $contact, $email, $street, $city, $province, $profile_pic, $username
            );
            
            if ($stmt->execute()) {
                $msg = "Profile updated successfully! 💾";
                
                // Reload data
                $stmt_reload = $conn->prepare("SELECT full_name, sex, birthdate, contact, email, street, city, province, course, section, year, student_id, profile_pic, semester, school_year FROM users WHERE username=?");
                $stmt_reload->bind_param("s", $username);
                $stmt_reload->execute();
                $result_reload = $stmt_reload->get_result();
                $student = $result_reload->fetch_assoc();
                $current_profile_pic = htmlspecialchars($student['profile_pic'] ?: 'default.png');
                $stmt_reload->close();
                
                // Reset OTP validation flag
                unset($_SESSION['email_otp_validated']); 
            } else {
                $msg = "Error updating profile: " . $stmt->error;
            }
            $stmt->close();
        }
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
    <span class="role">Student</span>
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
                <li class="nav-link">
                    <a href="logout.php">
                        <i class='bx bx-log-out icon'></i>
                        <span class="text nav-text">Logout</span>
                    </a>
                </li>
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
                <label for="profile_pic" id="changePhotoLabel" class="upload-label">
                    <i class='bx bx-camera'></i> Change Photo
                </label> 
            </div>

            <form method="POST" enctype="multipart/form-data" id="profileForm">
                <input type="hidden" name="email_otp_validated" id="email_otp_validated" value="false"> 
                
                <input type="file" name="profile_pic" id="profile_pic" accept="image/png,image/jpeg,image/jpg">
                
                <div>
                    <label>Full Name:</label>
                    <div class="read-only-display"><?= htmlspecialchars($student['full_name'] ?? 'N/A') ?></div>
                </div>
                <div>
                 <label>Sex:</label>
                    <div class="read-only-display"><?= htmlspecialchars($student['sex'] ?? 'N/A') ?></div>
                </div>
                <div>
                    <label>Birthdate:</label>
                    <div class="read-only-display"><?= htmlspecialchars($student['birthdate'] ?? 'N/A') ?></div>
                </div>
             
                <div>
                    <label>Contact Number:</label>
                    <div class="verification-input-group">
                        <input type="text" name="contact" id="contact" 
                            value="<?= htmlspecialchars($student['contact'] ?? '') ?>" 
                            disabled required pattern="[0-9]{11}" maxlength="11"
                            oninput="this.value = this.value.replace(/[^0-9]/g, '').slice(0, 11);">
                    </div>
                </div>
                <div id="emailGroup">
                    <label>Email Address:</label>
                    <div class="verification-input-group">
                        <input type="email" name="email" id="email" 
                            value="<?= htmlspecialchars($student['email'] ?? '') ?>" 
                            disabled required oninput="emailChanged()">
                        
                        <button type="button" id="emailVerifyBtn" class="verify-btn" 
                                onclick="requestOTP('email')">
                            Verify
                        </button>
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
                    <span id="emailVerifiedBadge" class="verified-badge">
                        <i class='bx bx-check-circle'></i> Verified
                    </span>
                </div>
                <div>
                    <label>Street Address:</label>
                    <input type="text" name="street" id="street" value="<?= htmlspecialchars($student['street'] ?? '') ?>" disabled required>
                </div>

                <div>
                    <label for="province">Province:</label>
                    <select name="province" id="province" disabled required>
                        <option value="<?= htmlspecialchars($student['province'] ?? '') ?>" selected><?= htmlspecialchars($student['province'] ?? 'N/A') ?></option>
                    </select>
                </div>
                <div>
                    <label for="city">City / Municipality:</label>
                    <select name="city" id="city" disabled required>
                        <option value="<?= htmlspecialchars($student['city'] ?? '') ?>" selected><?= htmlspecialchars($student['city'] ?? 'N/A') ?></option>
                    </select>
                </div>
                
              
                <div>
                    <label>Course:</label>
                    <div class="read-only-display"><?= htmlspecialchars($student['course'] ?? 'N/A') ?></div>
                </div>
                <div>
                    <label>Year:</label>
                    <div class="read-only-display"><?= htmlspecialchars($student['year'] ?? 'N/A') ?></div>
                </div>
                <div>
                    <label>Section:</label>
                    <div class="read-only-display"><?= htmlspecialchars($student['section'] ?? 'N/A') ?></div>
                </div>
                
                <div class="full">
                    <button type="button" id="editBtn" class="btn"><i class='bx bx-edit'></i> EDIT PROFILE</button>
                    <button type="submit" name="save" id="saveBtn" class="btn" style="display:none;"><i class='bx bx-save'></i> SAVE CHANGES</button>
                    <button type="button" id="cancelBtn" class="btn" style="background: #e74c3c; display:none;"><i class='bx bx-x'></i> CANCEL</button>
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
    </section>
    
    <script>
    // Address Data
    const PH_ADDRESS_DATA = { 
        "Abra": { "cities": ["Bangued", "Tayum", "Lagangilang", "Pidigan"] },
        "Bataan": { "cities": ["Balanga (Capital)", "Dinalupihan", "Orani", "Pilar", "Hermosa"] },
        "Metro Manila": { "cities": ["Manila", "Quezon City", "Makati", "Pasig", "Taguig", "Pasay", "Mandaluyong"] },
        "Rizal": { "cities": ["Antipolo City", "Cainta", "Taytay", "Angono", "Binangonan"] },
        "Pangasinan": { "cities": ["Dagupan City", "Urdaneta City", "Lingayen", "San Carlos City"] }
    }; 

    // UI Elements
    const editBtn = document.getElementById('editBtn');
    const saveBtn = document.getElementById('saveBtn');
    const cancelBtn = document.getElementById('cancelBtn');
    const picContainer = document.getElementById('picContainer');
    const formInputs = document.querySelectorAll('form input:not([type="hidden"]), form select'); 
    const preview = document.getElementById('preview');
    const provinceSelect = document.getElementById('province');
    const citySelect = document.getElementById('city'); 
    
    const contactInput = document.getElementById('contact');
    const emailInput = document.getElementById('email');
    const emailVerifyBtn = document.getElementById('emailVerifyBtn');
    const emailVerifiedBadge = document.getElementById('emailVerifiedBadge');
    
    const uploadLabel = document.getElementById('changePhotoLabel');
    const profilePicInput = document.getElementById('profile_pic'); 
    const sidebarImage = document.querySelector('.sidebar .image img');

    const originalValues = {};
    let initialProvinceValue = provinceSelect.value;
    let initialCityValue = citySelect.value;
    let originalPicSrc = preview.src;
    let originalEmail = emailInput.value;
    
    // OTP Variables
    const otpModal = document.getElementById('otpModal');
    const otpInput = document.getElementById('otp-input');
    const otpDestinationDisplay = document.getElementById('otpDestinationDisplay');
    const otpTimerDisplay = document.getElementById('otpTimer');
    const otpTypeHidden = document.getElementById('otp_type_hidden');
    const verifyModalBtn = document.getElementById('verifyModalBtn');
    let resendTimer;

    function storeOriginalValues() {
        formInputs.forEach(input => {
            originalValues[input.id] = input.value; 
        });
        initialProvinceValue = provinceSelect.value;
        initialCityValue = citySelect.value;
        originalPicSrc = preview.src;
        originalEmail = emailInput.value;
    }
    
    function populateProvinces(isInitialLoad = false) {
        let selectedProvince = isInitialLoad ? initialProvinceValue : provinceSelect.value;
        provinceSelect.innerHTML = `<option value="">-- Select Province --</option>`;
        let provinces = Object.keys(PH_ADDRESS_DATA).sort();
        
        provinces.forEach(province => {
            const option = document.createElement('option');
            option.value = province;
            option.textContent = province;
            if (province === selectedProvince) {
                option.selected = true;
            }
            provinceSelect.appendChild(option);
        });
        if (provinceSelect.querySelector(`option[value="${selectedProvince}"]`)) {
            provinceSelect.value = selectedProvince;
        }
        populateCities(provinceSelect.value, isInitialLoad);
    }
    
    function populateCities(selectedProvince, isInitialLoad = false) {
        let selectedCity = isInitialLoad ? initialCityValue : citySelect.value;
        citySelect.innerHTML = `<option value="">-- Select City / Municipality --</option>`;
        if (selectedProvince && PH_ADDRESS_DATA[selectedProvince]) {
            let cities = PH_ADDRESS_DATA[selectedProvince].cities.sort();
            cities.forEach(city => {
                const option = document.createElement('option');
                option.value = city;
                option.textContent = city;
                if (city === selectedCity) {
                    option.selected = true;
                }
                citySelect.appendChild(option);
            });
        }
        if (selectedCity && citySelect.querySelector(`option[value="${selectedCity}"]`)) {
            citySelect.value = selectedCity;
        } else {
            citySelect.value = ""; 
        }
    }

    provinceSelect.addEventListener('change', function() {
        populateCities(this.value, false);
    });

    function setEditMode(isEditing) {
        formInputs.forEach(input => {
            // Only enable editable fields
            if (['contact', 'email', 'street', 'province', 'city'].includes(input.id)) {
                input.disabled = !isEditing;
            }
        });
        
        editBtn.style.display = isEditing ? 'none' : 'inline-block';
        saveBtn.style.display = isEditing ? 'inline-block' : 'none';
        cancelBtn.style.display = isEditing ? 'inline-block' : 'none';
        picContainer.classList.toggle('editable-upload', isEditing); 

        if (isEditing) {
            emailChanged(true);
        } else {
            emailVerifyBtn.style.display = 'none';
            emailVerifiedBadge.style.display = 'none';
        }
    }
    
    function isValidEmail(email) {
        return /^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(email);
    }

    function emailChanged(isInitial = false) {
        const currentEmail = emailInput.value.trim();
        const emailValid = isValidEmail(currentEmail);
        const emailIsDifferent = currentEmail !== originalEmail;
        
        document.getElementById('email_otp_validated').value = 'false';

        if (emailValid && emailIsDifferent) {
            emailVerifyBtn.style.display = 'inline-block';
            emailVerifiedBadge.style.display = 'none';
        } else {
            emailVerifyBtn.style.display = 'none';
            emailVerifiedBadge.style.display = 'none';
        }
    }
    
    function requestOTP(type) {
        if (type !== 'email') {
            alert("Invalid verification type.");
            return;
        }

        const destination = emailInput.value.trim();

        if (!isValidEmail(destination)) {
            alert("Please enter a valid email address.");
            return;
        }

        emailVerifyBtn.textContent = 'Sending...';
        emailVerifyBtn.disabled = true;

        fetch('handle_otp.php', {
            method: 'POST',
            headers: {'Content-Type': 'application/json'},
            body: JSON.stringify({ action: 'send', type: type, destination: destination })
        })
        .then(response => {
             if (!response.ok) {
                 return response.json().then(errorData => {
                     if (response.status === 500 && !errorData) {
                          throw new Error(`Server Error: 500 (Check PHP logs for handle_otp.php)`);
                     }
                     throw new Error(errorData.message || `Server Error: ${response.status}`);
                 }).catch(e => {
                     if (e instanceof TypeError) throw new Error("Network Error or Empty/Malformed Response from handle_otp.php");
                     throw e;
                 });
            }
            return response.json();
        })
        .then(data => {
            if (data.success) {
                otpDestinationDisplay.textContent = destination;
                otpTypeHidden.value = type; 
                openModal('otpModal');
                startResendTimer(60, type);
                alert('OTP sent successfully! Check your email.');
            } else {
                alert('Failed to send OTP: ' + data.message);
            }
        })
        .catch(error => {
            console.error('OTP Request Error:', error);
            alert('Error requesting OTP: ' + error.message);
        })
        .finally(() => {
            emailVerifyBtn.textContent = 'Verify';
            emailVerifyBtn.disabled = false;
        });
    }
    
    function verifyOTP() {
        const otpCode = otpInput.value.trim();
        const type = otpTypeHidden.value;
        const destination = emailInput.value.trim();
        
        if (otpCode.length !== 6) {
            alert("Please enter the 6-digit OTP.");
            return;
        }

        verifyModalBtn.textContent = 'Verifying...';
        verifyModalBtn.disabled = true;
        
        fetch('handle_otp.php', {
            method: 'POST',
            headers: {'Content-Type': 'application/json'},
            body: JSON.stringify({ action: 'verify', type: type, destination: destination, otp: otpCode })
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                closeModal('otpModal');
                emailVerifiedBadge.style.display = 'flex';
                emailVerifyBtn.style.display = 'none';
                document.getElementById('email_otp_validated').value = 'true'; 
                alert('Email verified successfully! You can now save changes.');
            } else {
                alert(data.message || 'Invalid or expired OTP. Please try again.');
            }
        })
        .catch(error => {
            console.error('OTP Verification Error:', error);
            alert('Error verifying OTP. Please try again.');
        })
        .finally(() => {
            verifyModalBtn.textContent = 'Verify Code';
            verifyModalBtn.disabled = false;
        });
    }
    
    function startResendTimer(duration, type) {
        clearInterval(resendTimer);
        let timer = duration;
        
        const existingResendBtn = otpTimerDisplay.querySelector('#resendBtn');
        if (existingResendBtn) existingResendBtn.remove();
        
        otpTimerDisplay.textContent = `Resend in ${timer}s`;
        otpTimerDisplay.style.color = '#555';
        
        resendTimer = setInterval(() => {
            timer--;
            if (timer > 0) {
                otpTimerDisplay.textContent = `Resend in ${timer}s`;
            } else {
                clearInterval(resendTimer);
                
                const resendBtn = document.createElement('button');
                resendBtn.textContent = 'Resend Code';
                resendBtn.className = 'btn';
                resendBtn.id = 'resendBtn';
                resendBtn.style.backgroundColor = '#5cb85c';
                resendBtn.style.marginTop = '10px';
                resendBtn.onclick = () => {
                    closeModal('otpModal');
                    requestOTP(type);
                };
                
                otpTimerDisplay.innerHTML = '';
                otpTimerDisplay.appendChild(resendBtn);
            }
        }, 1000);
    }
    
    function openModal(id) {
        document.getElementById(id).style.display = 'flex';
        otpInput.value = '';
    }

    function closeModal(id) {
        document.getElementById(id).style.display = 'none';
        clearInterval(resendTimer);
    }
    
    cancelBtn.addEventListener('click', () => {
        formInputs.forEach(input => {
            if (originalValues[input.id] !== undefined) {
                input.value = originalValues[input.id];
            }
        });
        initialProvinceValue = originalValues['province'];
        initialCityValue = originalValues['city'];
        
        preview.src = originalPicSrc;
        sidebarImage.src = originalPicSrc;
        profilePicInput.value = '';
        
        setEditMode(false);
        populateProvinces(true); 
    });
    
    editBtn.addEventListener('click', () => {
        storeOriginalValues();
        populateProvinces(false); 
        setEditMode(true);
    });

    document.addEventListener('DOMContentLoaded', () => {
        storeOriginalValues();
        populateProvinces(true);
        setEditMode(false); 
        
        if (uploadLabel) {
             uploadLabel.addEventListener('click', (e) => {
                e.preventDefault();
                if (picContainer.classList.contains('editable-upload')) {
                    profilePicInput.click(); 
                }
            });
        }
    });
    
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
</script>
</body>
</html>