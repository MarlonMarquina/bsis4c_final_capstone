<?php
session_start();
include('conn.php'); 

// 🔑 Session Clearer para sa Password Reset
if (isset($_SESSION['reset_step'])) {
    unset($_SESSION['reset_step']);
    unset($_SESSION['otp_code']);
    unset($_SESSION['otp_expiry']);
    unset($_SESSION['user_email']);
    unset($_SESSION['test_otp_display']); 
}

// PHP Logic: Secure Login Check
$error = '';
if (isset($_POST['login'])) {
    $username = trim($_POST['username']);
    $password = $_POST['password'];

    if (empty($username) || empty($password)) {
        $error = "Please fill in all fields.";
    } else {
        $stmt = $conn->prepare("SELECT username, password, role, status FROM users WHERE username = ? OR (email IS NOT NULL AND email = ?)");
        $stmt->bind_param("ss", $username, $username);
        $stmt->execute();
        $result = $stmt->get_result();

        if ($result->num_rows === 1) {
            $row = $result->fetch_assoc();

            if ($row['status'] === 'inactive') {
                $error = "⚠️ Your account has been deactivated. Please contact the administrator.";
            } elseif (password_verify($password, $row['password'])) {
                $_SESSION['username'] = $row['username'];
                $_SESSION['role'] = $row['role'];

                if ($row['role'] == 'student') {
                    header("Location: student_dashboard.php");
                } elseif ($row['role'] == 'signatory') {
                    header("Location: signatory_dashboard.php");
                } else {
                    header("Location: admin_dashboard.php");
                }
                exit();
            } else {
                $error = "Invalid username or password!";
            }
        } else {
            $error = "Invalid username or password!";
        }
        $stmt->close();
    }
}

$success_message = '';
if (isset($_SESSION['success_message'])) {
    $success_message = $_SESSION['success_message'];
    unset($_SESSION['success_message']); 
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <title>Login | Bulacan Polytechnic College</title>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;600;700&display=swap" rel="stylesheet" />
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
  <style>
    * {
        box-sizing: border-box;
    }

    body {
        font-family: "Poppins", sans-serif;
        margin: 0;
        min-height: 100vh;
        display: flex;
        flex-direction: column;
        justify-content: center;
        align-items: center;
        position: relative;
        background-color: #333;
        padding: 20px 15px 50px;
    }

    body::before {
        content: "";
        position: fixed;
        top: 0;
        left: 0;
        width: 100%;
        height: 100%;
        background: url('bpclogin.jpg') no-repeat center center/cover;
        opacity: 0.4;
        z-index: -1;
    }

    /* ==== LOGIN CONTAINER ==== */
    .login-container {
        display: flex;
        flex-direction: row;
        background: rgba(255, 255, 255, 0.75);
        border-radius: 15px;
        box-shadow: 0 5px 20px rgba(0,0,0,0.2);
        overflow: hidden;
        width: 850px;
        max-width: 100%;
    }

    .login-left {
        flex: 1;
        background: rgba(255, 255, 255, 0.5);
        display: flex;
        flex-direction: column;
        justify-content: center;
        align-items: center;
        padding: 30px 20px;
        text-align: center;
    }

    .tagline-top {
        font-size: 14px;
        font-weight: 700;
        color: #006400;
        text-transform: uppercase;
        margin-bottom: 15px;
        letter-spacing: 0.5px;
        text-align: center;
        width: 100%;
    }

    .login-left img {
        width: 150px;
        height: auto;
        margin-bottom: 15px;
        display: block;
        margin-left: auto;
        margin-right: auto;
    }

    .tagline-bottom {
        font-size: 11px;
        color: #333;
        line-height: 1.5;
        max-width: 90%;
        font-style: italic;
        font-weight: 500;
        text-align: center;
    }

    .login-right {
        flex: 1;
        padding: 30px;
        background: rgba(248, 248, 248, 0.6);
        display: flex;
        flex-direction: column;
        justify-content: center;
    }

    .login-right h2 {
        text-align: center;
        margin-bottom: 15px;
        font-weight: 700;
        color: #222;
        margin-top: 0;
    }

    .input-group, .input-group1 {
        margin-bottom: 10px;
    }

    .input-group label {
        display: block;
        margin-bottom: 4px;
        font-weight: 500;
        font-size: 13px;
    }

    .input-group input {
        width: 100%;
        padding: 8px 10px;
        border: 1px solid #ccc;
        border-radius: 8px;
        font-size: 14px;
        background: rgba(255, 255, 255, 0.9);
    }

    .input-group input:focus {
        outline: none;
        border-color: #00a859;
    }

    .show-pass {
        position: relative;
    }

    .show-pass i {
        position: absolute;
        top: 30px;
        right: 10px;
        cursor: pointer;
        color: #777;
        z-index: 99;
        display: none;
    }

    .show-pass i.show {
        display: block;
    }

    .btn-login {
        background: #00a859;
        color: white;
        border: none;
        padding: 10px;
        border-radius: 8px;
        font-size: 16px;
        width: 100%;
        cursor: pointer;
        transition: 0.3s;
        margin-top: 5px;
    }

    .btn-login:disabled {
        opacity: 0.6;
        cursor: not-allowed;
    }

    .btn-login:hover:not(:disabled) {
        background: #007a43;
    }

    .error {
        color: #d8000c;
        background-color: #ffbaba;
        border: 1px solid #d8000c;
        padding: 10px;
        border-radius: 5px;
        text-align: center;
        margin-bottom: 10px;
        font-size: 13px;
        font-weight: 500;
    }

    .success {
        color: #007a43;
        background-color: rgba(230, 255, 230, 0.8);
        border: 1px solid #a0e0a0;
        padding: 8px;
        border-radius: 5px;
        text-align: center;
        margin-bottom: 8px;
        font-weight: 500;
        font-size: 13px;
    }

    .extras {
        text-align: center;
        margin-top: 10px;
    }

    .extras a {
        color: #007bff;
        text-decoration: none;
        font-size: 13px;
    }

    .extras a:hover {
        text-decoration: underline;
    }

    .footer-credits {
        position: fixed;
        bottom: 15px;
        width: 100%;
        text-align: center;
        color: white;
        font-size: 12px;
        letter-spacing: 1px;
        text-shadow: 1px 1px 3px rgba(0,0,0,0.9);
        z-index: 10;
        padding: 0 10px;
    }

    .footer-credits span {
        font-weight: 600;
        color: #a0e0a0;
    }

    /* ===========================
       TABLET (max-width: 768px)
    =========================== */
    @media (max-width: 768px) {
        body {
            padding: 15px 12px 55px;
        }

        .login-container {
            flex-direction: column;
            width: 100%;
            max-width: 460px;
        }

        .login-left {
            padding: 25px 20px 20px;
            flex-direction: column;
            gap: 10px;
            text-align: center;
            align-items: center;
        }

        .login-left img {
            width: 130px;
            margin-bottom: 5px;
            flex-shrink: 0;
        }

        .login-left-text {
            display: flex;
            flex-direction: column;
            align-items: center;
        }

        .tagline-top {
            font-size: 12px;
            margin-bottom: 6px;
            text-align: center;
        }

        .tagline-bottom {
            font-size: 10px;
            max-width: 100%;
            text-align: center;
        }

        .login-right {
            padding: 20px 25px 25px;
        }

        .login-right h2 {
            font-size: 20px;
            margin-bottom: 12px;
        }

        .footer-credits {
            font-size: 10px;
        }
    }

    /* ===========================
       MOBILE (max-width: 480px)
    =========================== */
    @media (max-width: 480px) {
        body {
            padding: 12px 10px 60px;
        }

        .login-container {
            border-radius: 12px;
        }

        .login-left {
            padding: 18px 15px;
            gap: 8px;
        }

        .login-left img {
            width: 120px;
        }

        .tagline-top {
            font-size: 11px;
            margin-bottom: 4px;
        }

        .tagline-bottom {
            font-size: 9.5px;
        }

        .login-right {
            padding: 18px 18px 22px;
        }

        .login-right h2 {
            font-size: 18px;
            margin-bottom: 10px;
        }

        .input-group label {
            font-size: 12px;
        }

        .input-group input {
            font-size: 13px;
            padding: 8px 10px;
        }

        .input-group1 label {
            font-size: 12px;
        }

        .btn-login {
            font-size: 15px;
            padding: 9px;
        }

        .extras a {
            font-size: 12px;
        }

        .footer-credits {
            font-size: 9.5px;
            letter-spacing: 0.5px;
        }
    }

    /* ===========================
       VERY SMALL (max-width: 360px)
    =========================== */
    @media (max-width: 360px) {
        .login-left {
            padding: 15px 12px;
            gap: 8px;
        }

        .login-left img {
            width: 100px;
        }

        .tagline-bottom {
            max-width: 100%;
        }

        .login-right {
            padding: 15px 15px 20px;
        }
    }

    /* MODAL STYLES */
    .modal {
        display: none;
        position: fixed;
        z-index: 2000;
        left: 0;
        top: 0;
        width: 100%;
        height: 100%;
        background-color: rgba(0,0,0,0.6);
        overflow: auto;
    }

    .modal-content {
        background-color: #fff;
        margin: 5% auto;
        padding: 25px 30px;
        border-radius: 10px;
        width: 90%;
        max-width: 700px;
        color: #222;
        line-height: 1.6;
        font-size: 14px;
        box-shadow: 0 4px 15px rgba(0,0,0,0.2);
        max-height: 75vh;
        overflow-y: auto;
    }

    .modal-content h2 {
        text-align: center;
        margin-bottom: 10px;
        color: darkgreen;
        font-size: 18px;
    }

    .modal-content h3 {
        text-align: center;
        margin-bottom: 20px;
        color: #444;
        font-size: 15px;
    }

    .close {
        color: #aaa;
        float: right;
        font-size: 24px;
        font-weight: bold;
        cursor: pointer;
    }

    .close:hover {
        color: black;
    }

    .modal-buttons {
        display: flex;
        justify-content: center;
        gap: 15px;
        margin-top: 20px;
    }

    .modal-buttons button {
        padding: 8px 18px;
        border: none;
        border-radius: 8px;
        font-size: 15px;
        cursor: pointer;
        transition: 0.3s;
    }

    #acceptTerms {
        background-color: #00a859;
        color: white;
    }

    #acceptTerms:disabled {
        opacity: 0.5;
        cursor: not-allowed;
    }

    #acceptTerms:hover:not(:disabled) {
        background-color: #007a43;
    }

    #rejectTerms {
        background-color: #ccc;
    }

    #rejectTerms:hover {
        background-color: #bbb;
    }

    @media (max-width: 480px) {
        .modal-content {
            margin: 10% auto;
            padding: 20px 18px;
            font-size: 13px;
            max-height: 80vh;
        }

        .modal-content h2 {
            font-size: 16px;
        }

        .modal-content h3 {
            font-size: 13px;
        }

        .modal-buttons button {
            padding: 8px 14px;
            font-size: 13px;
        }
    }
</style>
</head>
<body>

    <div class="login-container">
        <div class="login-left">
            <div class="login-left-text">
                <div class="tagline-top">"Your Partner to reach the World"</div>
                </div>
            <img src="bpc-logo.png" alt="BPC Logo">
            <!-- Wrap text in a div for tablet horizontal layout -->
            
                <div class="tagline-bottom">
                    A leading quality polytechnic college nurturing highly employable, globally-competitive, excellently skilled and competent graduates
                
            </div>
        </div>

        <div class="login-right">
            <h2>LOGIN</h2>
            <?php if (!empty($success_message)) echo "<p class='success'>$success_message</p>"; ?>
            <?php if (!empty($error)) echo "<p class='error'>$error</p>"; ?>

            <form method="POST">
                <div class="input-group">
                    <label for="username">USERNAME / EMAIL</label>
                    <input type="text" name="username" id="username"
                           value="<?= isset($_POST['username']) ? htmlspecialchars($_POST['username']) : '' ?>"
                           required>
                </div>

                <div class="input-group show-pass">
                    <label for="password">PASSWORD</label>
                    <input type="password" name="password" id="password" required>
                    <i id="togglePass" class="fa fa-eye"></i>
                </div>

                <div class="input-group1">
                    <label>
                        <input type="checkbox" id="agreeTerms" required> I agree to the
                        <a href="#" id="openTerms">Terms &amp; Conditions</a>
                    </label>
                </div>

                <button type="submit" name="login" class="btn-login" disabled>Login</button>
            </form>

            <div class="extras">
                <a href="forgotpassword.php">Forgot Password?</a>
            </div>
        </div>
    </div>

    <div class="footer-credits">
        Developed by <span>BSIS 4C - GROUP 4</span> (A.Y. 2025-2026) | All rights reserved.
    </div>

    <div id="termsModal" class="modal">
        <div class="modal-content" id="termsContent">
            <span class="close">&times;</span>
            <h2>TERMS AND CONDITIONS</h2>
            <h3>Smart Student Clearance System</h3>
            <p><strong>1. General Provisions</strong><br>
            1.1 The Smart Student Clearance System (SSCS) is an online platform designed to streamline and digitize the clearance process of students within the institution.<br>
            1.2 By accessing and using the SSCS, all users (Students, Signatories, and Administrators) agree to comply with the terms and conditions outlined herein.<br>
            1.3 The institution reserves the right to update or revise these Terms and Conditions at any time without prior notice.</p>

            <p><strong>2. Students</strong><br>
            - Must provide accurate and updated personal and academic information when using the system. Responsible for monitoring clearance status and complying with requirements set by different departments.<br>
            - Must settle all financial, academic, and administrative obligations before requesting final clearance.</p>

            <p><strong>3. Signatories</strong><br>
            - Must verify student records accurately before granting clearance.<br>
            - Ensure all conditions (e.g. payment of dues, compliance with academic requirements) are validated.<br>
            - Update clearance statuses promptly to avoid processing delays.<br>
            - Misuse of authority to favor or disadvantage students is prohibited.</p>

            <p><strong>4. Administrators</strong><br>
            - Manage user accounts, maintain system security, and ensure smooth operation.<br>
            - Safeguard sensitive student and institutional data in compliance with the Data Privacy Act of 2012 (RA 10173). Resolve technical issues and ensure transparency of records.<br>
            - Unauthorized modification of student records or misuse of privileges is prohibited.</p>

            <p><strong>5. Data Privacy and Security</strong><br>
            - All users must protect their login credentials and are accountable for any activity under their accounts.<br>
            - The system collects, stores, and processes personal information solely for academic and clearance purposes.<br>
            - Any breach of confidentiality, unauthorized access, or misuse of data may result in disciplinary and/or legal action.</p>

            <p><strong>6. Accountability and Liability</strong><br>
            - The institution is not liable for delays caused by user negligence, incomplete requirements, or noncompliance with clearance procedures.<br>
               - Developer Accountability: The Developers are held accountable for the reliability of the system’s architecture and its compliance with the agreed-upon technical specifications. <br>
            - Violations of these Terms and Conditions may result in sanctions such as suspension of access, academic holds, or disciplinary action.</p>
            
            <p><strong>7. Acceptance</strong><br>
            By using the Smart Student Clearance System, all parties acknowledge that they have read, understood, and agreed to abide by these Terms and Conditions.</p>

            <div class="modal-buttons">
                <button id="acceptTerms" disabled>Accept</button>
                <button id="rejectTerms">Reject</button>
            </div>
        </div>
    </div>

    <script>
        const togglePass = document.getElementById("togglePass");
        const password = document.getElementById("password");
        const modal = document.getElementById("termsModal");
        const openBtn = document.getElementById("openTerms");
        const closeBtn = document.getElementsByClassName("close")[0];
        const termsContent = document.getElementById("termsContent");
        const agreeCheckbox = document.getElementById("agreeTerms");
        const loginButton = document.querySelector(".btn-login");
        const acceptBtn = document.getElementById("acceptTerms");
        const rejectBtn = document.getElementById("rejectTerms");

        function toggleLoginButton() {
            loginButton.disabled = !agreeCheckbox.checked;
        }

        agreeCheckbox.addEventListener("change", toggleLoginButton);

        password.addEventListener("input", function() {
            if (this.value.length > 0) {
                togglePass.classList.add("show");
            } else {
                togglePass.classList.remove("show");
                this.type = "password";
                togglePass.classList.remove("fa-eye-slash");
                togglePass.classList.add("fa-eye");
            }
        });

        window.addEventListener("load", function() {
            if (password.value.length > 0) {
                togglePass.classList.add("show");
            }
            toggleLoginButton();
        });

        togglePass.addEventListener("click", () => {
            if (password.type === "password") {
                password.type = "text";
                togglePass.classList.replace("fa-eye", "fa-eye-slash");
            } else {
                password.type = "password";
                togglePass.classList.replace("fa-eye-slash", "fa-eye");
            }
        });

        openBtn.onclick = e => {
            e.preventDefault();
            modal.style.display = "block";
            document.body.style.overflow = "hidden";
        };
        closeBtn.onclick = () => {
            modal.style.display = "none";
            document.body.style.overflow = "auto";
        };
        window.onclick = e => {
            if (e.target == modal) {
                modal.style.display = "none";
                document.body.style.overflow = "auto";
            }
        };

        termsContent.addEventListener("scroll", () => {
            const isScrolledToBottom = termsContent.scrollTop + termsContent.clientHeight >= termsContent.scrollHeight - 10;
            if (isScrolledToBottom) {
                acceptBtn.disabled = false;
            }
        });

        acceptBtn.onclick = () => {
            modal.style.display = "none";
            document.body.style.overflow = "auto";
            agreeCheckbox.checked = true;
            toggleLoginButton();
            acceptBtn.disabled = true;
            termsContent.scrollTop = 0;
        };

        rejectBtn.onclick = () => {
            modal.style.display = "none";
            document.body.style.overflow = "auto";
            agreeCheckbox.checked = false;
            toggleLoginButton();
            acceptBtn.disabled = true;
            termsContent.scrollTop = 0;
        };
    </script>
</body>
</html>