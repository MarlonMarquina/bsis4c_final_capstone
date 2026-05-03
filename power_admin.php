<?php
include 'conn.php';
session_start();
date_default_timezone_set('Asia/Manila');
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;
require 'vendor/autoload.php';
if (!isset($_SESSION['role']) || $_SESSION['role'] != "admin") {
    header("Location: login.php"); exit();
}

$msg = '';

// --- PERMISSION CHECK ---
$admin_user = $_SESSION['username'];
$cap_stmt = $conn->prepare("SELECT can_add_admin, email FROM users WHERE username = ? AND role = 'admin'");
$cap_stmt->bind_param("s", $admin_user);
$cap_stmt->execute();
$cap_row = $cap_stmt->get_result()->fetch_assoc();
$cap_stmt->close();
$can_switch_term = ($cap_row && $cap_row['can_add_admin'] == 1);
$admin_email = $cap_row['email'] ?? '';

// --- ARCHIVE CURRENT TERM DATA ---
function archiveCurrentTerm($conn, $sem, $sy) {
    $conn->query("UPDATE users SET semester='$sem', school_year='$sy' 
                  WHERE role='student' AND (semester IS NULL OR school_year IS NULL)");
    $conn->query("DELETE FROM archived_applications WHERE semester='$sem' AND school_year='$sy'");
    $conn->query("DELETE FROM archived_course_requirements WHERE semester='$sem' AND school_year='$sy'");
    $conn->query("DELETE FROM archived_draft_requirements WHERE semester='$sem' AND school_year='$sy'");
    $conn->query("DELETE FROM archived_signatory_history WHERE semester='$sem' AND school_year='$sy'");
    $conn->query("DELETE FROM archived_student_status WHERE semester='$sem' AND school_year='$sy'");
    $conn->query("DELETE FROM archived_user_accounts WHERE semester='$sem' AND school_year='$sy'");

    $conn->query("INSERT INTO archived_applications 
        (semester, school_year, orig_id, username, signatory, course, requirement_id, document, status, rejection_reason, rejection_count, submitted_at, reviewed_at)
        SELECT '$sem', '$sy', id, username, signatory, course, requirement_id, document, status, rejection_reason, rejection_count, submitted_at, reviewed_at
        FROM applications");
    $conn->query("INSERT INTO archived_course_requirements
        (semester, school_year, orig_id, course_id, requirement_id, signatory_id, document_type_id, year_level, sections, requirements_configured)
        SELECT '$sem', '$sy', id, course_id, requirement_id, signatory_id, document_type_id, year_level, sections, requirements_configured
        FROM course_requirements");
    $conn->query("INSERT INTO archived_draft_requirements
        (semester, school_year, orig_id, username, requirement_id, signatory_id, document_type_id, file_name, created_at)
        SELECT '$sem', '$sy', id, username, requirement_id, signatory_id, document_type_id, file_name, created_at
        FROM draft_requirements");
    $conn->query("INSERT INTO archived_signatory_history
        (semester, school_year, orig_id, signatory_username, student_user, action, reason, remarks, created_at)
        SELECT '$sem', '$sy', id, signatory_username, student_user, action, reason, remarks, action_date
        FROM signatory_history");
    $conn->query("INSERT INTO archived_student_status
        (semester, school_year, username, admin_approved, final_clearance_status, admin_messaged, admin_message_text)
        SELECT '$sem', '$sy', username, admin_approved, final_clearance_status, admin_messaged, admin_message_text
        FROM users WHERE role = 'student'");

    // Archive full user accounts: students + class advisers
    $conn->query("INSERT INTO archived_user_accounts
        (semester, school_year, orig_id, username, full_name, sex, email, email_verified,
         password_last_updated, course_id, year, section, adviser_username, password, role,
         profile_pic, signatory_type, department, course, student_id, birthdate, contact,
         street, city, province, semester_field, school_year_field, final_clearance_status,
         status, admin_approved, admin_messaged, admin_message_text, admin_message_sent_at,
         admin_approved_at, admin_approved_by, can_add_admin)
        SELECT '$sem', '$sy', id, username, full_name, sex, email, email_verified,
         password_last_updated, course_id, year, section, adviser_username, password, role,
         profile_pic, signatory_type, department, course, student_id, birthdate, contact,
         street, city, province, semester, school_year, final_clearance_status,
         status, admin_approved, admin_messaged, admin_message_text, admin_message_sent_at,
         admin_approved_at, admin_approved_by, can_add_admin
        FROM users
        WHERE role = 'student' OR (role = 'signatory' AND signatory_type = 'Class Adviser')");

    // Delete students and class advisers from live users table
    $conn->query("DELETE FROM users WHERE role = 'student'");
    $conn->query("DELETE FROM users WHERE role = 'signatory' AND signatory_type = 'Class Adviser'");
}

// --- CLEAR CURRENT TERM DATA ---
function clearCurrentTerm($conn) {
    $conn->query("DELETE FROM applications");
    $conn->query("DELETE FROM course_requirements");
    $conn->query("DELETE FROM draft_requirements");
    $conn->query("DELETE FROM signatory_history");
    // Students and class advisers already deleted inside archiveCurrentTerm()
}

// --- RESTORE ARCHIVED TERM DATA ---
function restoreArchivedTerm($conn, $sem, $sy) {
    $conn->query("SET FOREIGN_KEY_CHECKS=0");
    $conn->query("DELETE FROM applications");
    $conn->query("DELETE FROM course_requirements");
    $conn->query("DELETE FROM draft_requirements");
    $conn->query("DELETE FROM signatory_history");
    // Remove current students and class advisers before restoring
    $conn->query("DELETE FROM users WHERE role = 'student'");
    $conn->query("DELETE FROM users WHERE role = 'signatory' AND signatory_type = 'Class Adviser'");
    $conn->query("ALTER TABLE applications AUTO_INCREMENT=1");
    $conn->query("ALTER TABLE course_requirements AUTO_INCREMENT=1");
    $conn->query("ALTER TABLE draft_requirements AUTO_INCREMENT=1");
    $conn->query("ALTER TABLE signatory_history AUTO_INCREMENT=1");
    $conn->query("SET FOREIGN_KEY_CHECKS=1");

    $conn->query("INSERT IGNORE INTO applications (id, username, signatory, course, requirement_id, document, status, rejection_reason, rejection_count, submitted_at, reviewed_at)
        SELECT orig_id, username, signatory, course, requirement_id, document, status, rejection_reason, rejection_count, submitted_at, reviewed_at
        FROM archived_applications WHERE semester='$sem' AND school_year='$sy'");
    $conn->query("INSERT IGNORE INTO course_requirements (id, course_id, requirement_id, signatory_id, document_type_id, year_level, sections, requirements_configured)
        SELECT orig_id, course_id, requirement_id, signatory_id, document_type_id, year_level, sections, requirements_configured
        FROM archived_course_requirements WHERE semester='$sem' AND school_year='$sy'");
    $conn->query("INSERT INTO draft_requirements (id, username, requirement_id, signatory_id, document_type_id, file_name, created_at)
        SELECT orig_id, username, requirement_id, signatory_id, document_type_id, file_name, created_at
        FROM archived_draft_requirements WHERE semester='$sem' AND school_year='$sy'");
    $conn->query("INSERT INTO signatory_history (id, signatory_username, student_user, action, reason, remarks, action_date)
        SELECT orig_id, signatory_username, student_user, action, reason, remarks, created_at
        FROM archived_signatory_history WHERE semester='$sem' AND school_year='$sy'");

    // Restore full user accounts (students + class advisers)
    $conn->query("INSERT IGNORE INTO users
        (id, username, full_name, sex, email, email_verified, password_last_updated,
         course_id, year, section, adviser_username, password, role, profile_pic,
         signatory_type, department, course, student_id, birthdate, contact,
         street, city, province, semester, school_year, final_clearance_status,
         status, admin_approved, admin_messaged, admin_message_text, admin_message_sent_at,
         admin_approved_at, admin_approved_by, can_add_admin)
        SELECT orig_id, username, full_name, sex, email, email_verified, password_last_updated,
         course_id, year, section, adviser_username, password, role, profile_pic,
         signatory_type, department, course, student_id, birthdate, contact,
         street, city, province, semester_field, school_year_field, final_clearance_status,
         status, admin_approved, admin_messaged, admin_message_text, admin_message_sent_at,
         admin_approved_at, admin_approved_by, can_add_admin
        FROM archived_user_accounts
        WHERE semester='$sem' AND school_year='$sy'");
}

// --- CHECK IF ARCHIVE EXISTS ---
function archiveExists($conn, $sem, $sy) {
    $r = $conn->query("SELECT COUNT(*) as cnt FROM archived_applications WHERE semester='$sem' AND school_year='$sy'");
    return (int)$r->fetch_assoc()['cnt'] > 0;
}

// --- HANDLE OTP SEND FOR TERM SWITCH ---
if (isset($_POST['send_term_otp'])) {
    if (!$can_switch_term) { die(json_encode(['success' => false, 'message' => 'Permission denied.'])); }
    $otp = str_pad(random_int(0, 999999), 6, '0', STR_PAD_LEFT);
    $_SESSION['term_switch_otp']         = $otp;
    $_SESSION['term_switch_otp_exp']     = time() + 300;
    $_SESSION['term_switch_pending_sem'] = $_POST['semester'];
    $_SESSION['term_switch_pending_sy']  = $_POST['school_year'];

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
        $mail->addAddress($admin_email);
        $mail->isHTML(true);
        $mail->Subject = '🔄 Term Switch OTP';
        $mail->Body = '
        <div style="font-family:Arial,sans-serif; max-width:500px; margin:0 auto;">
            <div style="background:linear-gradient(135deg,#2d5016,#1a3409); padding:25px; text-align:center; border-radius:12px 12px 0 0;">
                <h2 style="color:white; margin:0;">🔄 Term Switch Confirmation</h2>
            </div>
            <div style="background:#f9f9f9; padding:25px; border:1px solid #ddd; border-radius:0 0 12px 12px;">
                <p>Your OTP to confirm the academic term switch is:</p>
                <h1 style="text-align:center; color:#2d5016; letter-spacing:10px;">' . $otp . '</h1>
                <p style="color:#888; font-size:12px;">This OTP expires in 5 minutes. Do not share it with anyone.</p>
            </div>
        </div>';
        $mail->send();
        echo json_encode(['success' => true, 'message' => 'OTP sent.']);
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'message' => 'Failed to send OTP: ' . $mail->ErrorInfo]);
    }
    exit;
}

// --- HANDLE TERM SWITCH (OTP-protected) ---
if (isset($_POST['sync_students'])) {
    if (!$can_switch_term) {
        $msg = '❌ You do not have permission to switch terms.';
    } elseif (
        !isset($_SESSION['term_switch_otp']) ||
        !isset($_SESSION['term_switch_otp_exp']) ||
        time() > $_SESSION['term_switch_otp_exp'] ||
        trim($_POST['otp'] ?? '') !== $_SESSION['term_switch_otp']
    ) {
        $msg = '❌ Invalid or expired OTP. Please try again.';
        unset($_SESSION['term_switch_otp'], $_SESSION['term_switch_otp_exp']);
    } else {
        unset($_SESSION['term_switch_otp'], $_SESSION['term_switch_otp_exp']);
        $new_sem = $_SESSION['term_switch_pending_sem'];
        $new_sy  = $_SESSION['term_switch_pending_sy'];
        unset($_SESSION['term_switch_pending_sem'], $_SESSION['term_switch_pending_sy']);

        $cur     = $conn->query("SELECT current_semester, current_school_year FROM system_settings WHERE id=1")->fetch_assoc();
        $cur_sem = $cur['current_semester'];
        $cur_sy  = $cur['current_school_year'];
        $is_same_term = ($new_sem === $cur_sem && $new_sy === $cur_sy);

        if (!$is_same_term) {
            archiveCurrentTerm($conn, $cur_sem, $cur_sy);
            clearCurrentTerm($conn);
            if (archiveExists($conn, $new_sem, $new_sy)) {
                restoreArchivedTerm($conn, $new_sem, $new_sy);
                $msg = "✅ Switched to $new_sem $new_sy — previous data restored from archive!";
            } else {
                $msg = "✅ Switched to $new_sem $new_sy — fresh start for this term!";
            }
            $stmt = $conn->prepare("UPDATE system_settings SET current_semester=?, current_school_year=? WHERE id=1");
            $stmt->bind_param("ss", $new_sem, $new_sy);
            $stmt->execute();
            $stmt2 = $conn->prepare("UPDATE users SET semester=?, school_year=? WHERE role='student'");
            $stmt2->bind_param("ss", $new_sem, $new_sy);
            $stmt2->execute();
        } else {
            $stmt = $conn->prepare("UPDATE users SET semester=?, school_year=? WHERE role='student'");
            $stmt->bind_param("ss", $new_sem, $new_sy);
            $stmt->execute();
            $msg = "✅ Students synced to current term $cur_sem $cur_sy!";
        }
    }
}

$settings = $conn->query("SELECT * FROM system_settings WHERE id=1")->fetch_assoc();
if (!$settings) {
    $settings = ['current_semester' => '1st Semester', 'current_school_year' => '2025-2026'];
}

$courses = $conn->query("SELECT DISTINCT course_name FROM courses ORDER BY course_name ASC");

// Set Sidebar Variables
$admin_stmt = $conn->prepare("SELECT full_name FROM users WHERE username = ? AND role = 'admin'");
$admin_stmt->bind_param("s", $admin_user);
$admin_stmt->execute();
$admin_data = $admin_stmt->get_result()->fetch_assoc();
$signatoryFullName = !empty($admin_data['full_name']) ? $admin_data['full_name'] : $admin_user;
$userRole = "Administrator";
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>System Settings | Power Admin</title>
    <link href='https://unpkg.com/boxicons@2.1.4/css/boxicons.min.css' rel='stylesheet'>
    <link rel="stylesheet" href="styles.css">
    <style>
        body { font-family: 'Poppins', sans-serif;  background: #E4E9F7; margin: 0; padding: 0; }
        
        .home { 
    padding: 20px;
    box-sizing: border-box;
    min-height: 100vh;
     background: #E4E9F7;
}

        .dashboard-header { 
            background: linear-gradient(135deg, #2d5016 0%, #1a3409 100%);
            color: white; 
            padding: 20px; 
            border-radius: 12px; 
            text-align: center; 
            margin-bottom: 30px;
            box-shadow: 0 4px 15px rgba(0,0,0,0.1);
        }

        .dashboard-header h2 { margin: 0; font-size: 24px; letter-spacing: 1px; }

        .card { 
            background: #fff; 
            padding: 30px; 
            border-radius: 15px; 
            box-shadow: 0 10px 30px rgba(0,0,0,0.05); 
            margin-bottom: 25px; 
            border: 1px solid #eef0f2;
        }

        .section-title { 
            color: #2d5016; 
            font-size: 18px;
            font-weight: 700;
            margin-bottom: 20px; 
            display: flex; 
            align-items: center; 
            gap: 10px;
            border-bottom: 2px solid #f0f2f5;
            padding-bottom: 10px;
        }

        .grid-settings { display: grid; grid-template-columns: 1fr 1fr; gap: 20px; }
        
        label { font-weight: 600; display: block; margin-bottom: 8px; color: #333; font-size: 13px; text-transform: uppercase; }
        
        select, input { 
            width: 100%; 
            padding: 12px; 
            border-radius: 8px; 
            border: 2px solid #e0e0e0; 
            background: #fff; 
            outline: none; 
            transition: 0.3s;
        }

        select:focus, input:focus { border-color: #2d5016; }
        
        .button-row { display: flex; gap: 15px; margin-top: 20px; }

        .btn {
            padding: 12px 25px;
            border-radius: 8px;
            cursor: pointer;
            font-weight: 700;
            display: inline-flex;
            align-items: center;
            gap: 8px;
            transition: 0.3s;
            border: none;
            font-size: 14px;
        }

        .btn-save { background: #2d5016; color: white; }
        .btn-save:hover { background: #1a3409; transform: translateY(-2px); box-shadow: 0 5px 15px rgba(45,80,22,0.3); }
        
        .btn-sync { background: #3498db; color: white; }
        .btn-sync:hover { background: #2980b9; transform: translateY(-2px); box-shadow: 0 5px 15px rgba(52,152,219,0.3); }

        .btn-report { 
            background: linear-gradient(135deg, #f39c12 0%, #e67e22 100%); 
            color: white; 
            width: 100%; 
            justify-content: center;
            margin-top: 25px;
            padding: 15px;
            font-size: 16px;
        }
        .btn-report:hover { transform: translateY(-2px); box-shadow: 0 8px 20px rgba(230,126,34,0.4); }
        
        .report-filter { display: grid; grid-template-columns: repeat(auto-fit, minmax(250px, 1fr)); gap: 20px; }
        .info-txt { font-size: 13px; color: #7f8c8d; margin-bottom: 15px; display: block; line-height: 1.5; }
        
        .alert {
            padding: 15px;
            border-radius: 8px;
            margin-bottom: 20px;
            font-weight: 500;
            border: 1px solid transparent;
        }
        .alert-success { background: #d4edda; color: #155724; border-color: #c3e6cb; }
    </style>
</head>
<body>

<?php include 'sidebar_admin.php'; ?>

<section class="home">
    <div style="max-width: 1000px; margin: 0 auto;">
        
        <div class="dashboard-header">
            <h2><i class='bx bx-shield-quarter'></i> SYSTEM CONTROL PANEL</h2>
        </div>

        <?php if($msg): ?>
            <div class="alert alert-success">
                <i class='bx bx-check-circle'></i> <?= $msg ?>
            </div>
        <?php endif; ?>

        <div class="card">
            <div class="section-title"><i class='bx bx-cog'></i> Global System Settings</div>
            <span class="info-txt">Update the active academic term. Use the "Sync" button to force update all existing student profiles to this term.</span>

            <form id="termSwitchForm">
    <div class="grid-settings">
        <div>
            <label>Active Semester</label>
            <select name="semester" id="termSem" <?= !$can_switch_term ? 'disabled' : '' ?>>
                <option value="1st Semester" <?= ($settings['current_semester'] == '1st Semester') ? 'selected' : '' ?>>1st Semester</option>
                <option value="2nd Semester" <?= ($settings['current_semester'] == '2nd Semester') ? 'selected' : '' ?>>2nd Semester</option>
                <option value="Summer" <?= ($settings['current_semester'] == 'Summer') ? 'selected' : '' ?>>Summer</option>
            </select>
        </div>
        <div>
            <label>Active School Year</label>
            <?php
            $current_sy = $settings['current_school_year'] ?? '2024-2025';
            preg_match('/(\d{4})-(\d{4})/', $current_sy, $m);
            $start_year = isset($m[1]) ? (int)$m[1] : (int)date('Y');
            $sy_options = [
                ($start_year - 1) . '-' . $start_year,
                $start_year . '-' . ($start_year + 1),
                ($start_year + 1) . '-' . ($start_year + 2),
            ];
            ?>
            <select name="school_year" id="termSy" <?= !$can_switch_term ? 'disabled' : '' ?>>
                <?php foreach ($sy_options as $opt): ?>
                    <option value="<?= $opt ?>" <?= $settings['current_school_year'] === $opt ? 'selected' : '' ?>>
                        <?= $opt ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>
    </div>
    <div style="margin-top: 20px;">
        <?php if ($can_switch_term): ?>
            <button type="button" class="btn btn-sync" onclick="openTermOtpModal()">
                <i class='bx bx-refresh'></i> Switch / Sync Term
            </button>
        <?php else: ?>
            <button type="button" class="btn btn-sync" disabled style="opacity:0.5; cursor:not-allowed;" title="You do not have permission to switch terms.">
                <i class='bx bx-lock-alt'></i> Switch / Sync Term (No Permission)
            </button>
        <?php endif; ?>
    </div>
</form>

<!-- ===== TERM SWITCH OTP MODAL ===== -->
<div id="termOtpModal" style="display:none; position:fixed; z-index:10000; left:0; top:0; width:100%; height:100%; background:rgba(0,0,0,0.6); backdrop-filter:blur(3px); justify-content:center; align-items:center;">
    <div style="background:white; width:440px; max-width:95vw; border-radius:15px; overflow:hidden; box-shadow:0 10px 40px rgba(0,0,0,0.25);">
        <div style="background:linear-gradient(135deg,#2d5016,#1a3409); padding:20px 25px; display:flex; justify-content:space-between; align-items:center;">
            <h3 style="color:white; margin:0; font-size:16px;"><i class='bx bx-refresh'></i> Confirm Term Switch</h3>
            <button onclick="closeTermOtpModal()" style="background:rgba(255,255,255,0.2); border:none; color:white; width:30px; height:30px; border-radius:50%; cursor:pointer; font-size:18px; display:flex; align-items:center; justify-content:center;">×</button>
        </div>
        <div style="padding:25px;">
            <!-- Step 1 -->
            <div id="termStep1">
                <div style="background:#fff3cd; border-left:4px solid #f39c12; border-radius:8px; padding:12px 15px; margin-bottom:18px; font-size:13px; color:#856404;">
<strong><i class='bx bx-error'></i> Warning:</strong> This will archive and remove all current students and class advisers from the system, then load the selected term. Global signatories and program heads will remain. An OTP will be sent to your registered email to confirm.
                </div>
                <p style="font-size:13px; color:#555; margin-bottom:6px;"><strong>Switching to:</strong></p>
                <p style="font-size:15px; font-weight:700; color:#2d5016; margin-bottom:18px;" id="termSwitchSummary">—</p>
                <button onclick="sendTermOtp()" id="termSendOtpBtn" style="width:100%; background:#2d5016; color:white; border:none; padding:13px; border-radius:8px; font-weight:700; cursor:pointer; font-size:14px; display:flex; align-items:center; justify-content:center; gap:8px;">
                    <i class='bx bx-envelope'></i> Send OTP to My Email
                </button>
            </div>
            <!-- Step 2 -->
            <div id="termStep2" style="display:none;">
                <p style="font-size:13px; color:#555; margin-bottom:15px;">Enter the 6-digit OTP sent to <strong><?= htmlspecialchars($admin_email) ?></strong>:</p>
                <input type="text" id="termOtpInput" maxlength="6" placeholder="000000"
                    style="width:100%; padding:13px; text-align:center; letter-spacing:12px; font-size:24px; font-weight:700; border:2px solid #e9ecef; border-radius:8px; box-sizing:border-box; margin-bottom:10px; transition:border-color 0.2s;"
                    oninput="this.value=this.value.replace(/[^0-9]/g,'').slice(0,6); this.style.borderColor=this.value.length===6?'#2d5016':'#e9ecef'">
                <div id="termOtpTimer" style="font-size:12px; color:#888; text-align:center; margin-bottom:18px;"></div>
                <button onclick="submitTermSwitch()" id="termVerifyBtn" style="width:100%; background:#2d5016; color:white; border:none; padding:13px; border-radius:8px; font-weight:700; cursor:pointer; font-size:14px;">
                    <i class='bx bx-check-shield'></i> Verify & Switch Term
                </button>
            </div>
            <div id="termOtpError" style="color:#c0392b; font-size:13px; margin-top:12px; text-align:center; font-weight:500;"></div>
        </div>
    </div>
</div>
        </div>

        <div class="card">
            <div class="section-title"><i class='bx bx-printer'></i> Clearance Summary Report</div>
            <span class="info-txt">Generate a professional PDF summary. You can filter by status, course, or year level.</span>
            
            <form action="generate_report_pdf.php" method="GET" target="_blank">
                <div class="report-filter">
                    <div>
                        <label>Semester</label>
                        <select name="rep_sem">
                            <option value="all">All Semesters</option>
                            <option value="1st Semester">1st Semester</option>
                            <option value="2nd Semester">2nd Semester</option>
                            <option value="Summer">Summer</option>
                        </select>
                    </div>
                      <div>
                        <label>School Year</label>
                        <input type="text" name="rep_sy" value="<?= htmlspecialchars($settings['current_school_year']) ?>" readonly style="background:#f5f5f5; cursor:not-allowed; color:#666;">
                    </div>
                    <div>
                        <label>Department / Course</label>
                        <select name="rep_course">
                            <option value="all">All Courses</option>
                            <?php 
                            $courses->data_seek(0);
                            while($c = $courses->fetch_assoc()): ?>
                                <option value="<?= htmlspecialchars($c['course_name']) ?>"><?= htmlspecialchars($c['course_name']) ?></option>
                            <?php endwhile; ?>
                        </select>
                    </div>
                    <div>
                        <label>Year Level</label>
                        <select name="rep_year">
                            <option value="all">All Years</option>
                            <option value="1st Year">1st Year</option>
                            <option value="2nd Year">2nd Year</option>
                            <option value="3rd Year">3rd Year</option>
                            <option value="4th Year">4th Year</option>
                        </select>
                    </div>
                    <div style="grid-column: span 1;">
                        <label>Clearance Status</label>
                        <select name="rep_status">
                            <option value="all">All Students</option>
                            <option value="cleared">Fully Cleared</option>
                            <option value="pending">Pending/Uncleared</option>
                        </select>
                    </div>
                </div>

                <button type="submit" class="btn btn-report">
                    <i class='bx bxs-file-pdf'></i> GENERATE SUMMARY PDF
                </button>
            </form>
        </div>
    </div>
</section>
<script>
let termOtpTimerInterval;

function openTermOtpModal() {
    const sem = document.getElementById('termSem').value;
    const sy  = document.getElementById('termSy').value;
    document.getElementById('termSwitchSummary').textContent = sem + ' · ' + sy;
    document.getElementById('termStep1').style.display = 'block';
    document.getElementById('termStep2').style.display = 'none';
    document.getElementById('termOtpInput').value = '';
    document.getElementById('termOtpError').textContent = '';
    const btn = document.getElementById('termSendOtpBtn');
    btn.disabled = false;
    btn.innerHTML = "<i class='bx bx-envelope'></i> Send OTP to My Email";
    document.getElementById('termOtpModal').style.display = 'flex';
}

function closeTermOtpModal() {
    document.getElementById('termOtpModal').style.display = 'none';
    clearInterval(termOtpTimerInterval);
}

function sendTermOtp() {
    const sem = document.getElementById('termSem').value;
    const sy  = document.getElementById('termSy').value;
    const btn = document.getElementById('termSendOtpBtn');
    btn.disabled = true;
    btn.innerHTML = "<i class='bx bx-loader-alt' style='animation:spin 1s linear infinite'></i> Sending...";
    document.getElementById('termOtpError').textContent = '';

    fetch('', {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body: 'send_term_otp=1&semester=' + encodeURIComponent(sem) + '&school_year=' + encodeURIComponent(sy)
    })
    .then(r => r.json())
    .then(data => {
        if (data.success) {
            document.getElementById('termStep1').style.display = 'none';
            document.getElementById('termStep2').style.display = 'block';
            startTermOtpTimer(300);
        } else {
            document.getElementById('termOtpError').textContent = data.message || 'Failed to send OTP.';
            btn.disabled = false;
            btn.innerHTML = "<i class='bx bx-envelope'></i> Send OTP to My Email";
        }
    })
    .catch(() => {
        document.getElementById('termOtpError').textContent = 'Request failed. Please try again.';
        btn.disabled = false;
        btn.innerHTML = "<i class='bx bx-envelope'></i> Send OTP to My Email";
    });
}

function submitTermSwitch() {
    const otp = document.getElementById('termOtpInput').value.trim();
    if (otp.length !== 6) {
        document.getElementById('termOtpError').textContent = 'Please enter the 6-digit OTP.';
        return;
    }
    document.getElementById('termOtpError').textContent = '';
    const btn = document.getElementById('termVerifyBtn');
    btn.disabled = true;
    btn.textContent = 'Verifying...';

    // Submit a hidden form with the OTP
    const form = document.createElement('form');
    form.method = 'POST';
    form.action = '';
    [['sync_students', '1'], ['otp', otp],
     ['semester', document.getElementById('termSem').value],
     ['school_year', document.getElementById('termSy').value]
    ].forEach(([n, v]) => {
        const i = document.createElement('input');
        i.type = 'hidden'; i.name = n; i.value = v;
        form.appendChild(i);
    });
    document.body.appendChild(form);
    form.submit();
}

function startTermOtpTimer(duration) {
    clearInterval(termOtpTimerInterval);
    let t = duration;
    const el = document.getElementById('termOtpTimer');
    el.textContent = `OTP expires in ${Math.floor(t/60)}:${String(t%60).padStart(2,'0')}`;
    termOtpTimerInterval = setInterval(() => {
        t--;
        if (t > 0) {
            el.textContent = `OTP expires in ${Math.floor(t/60)}:${String(t%60).padStart(2,'0')}`;
        } else {
            clearInterval(termOtpTimerInterval);
            el.innerHTML = '<span style="color:#c0392b;">OTP expired. Please close and try again.</span>';
            document.getElementById('termVerifyBtn').disabled = true;
        }
    }, 1000);
}
</script>
</body>
</html>