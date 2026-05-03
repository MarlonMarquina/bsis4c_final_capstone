<?php
// FILE: admin_verify_action.php
session_start();
date_default_timezone_set('Asia/Manila');
include 'conn.php';

if (!isset($_SESSION['role']) || $_SESSION['role'] != 'admin') {
    header("Location: login.php");
    exit();
}

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;
require 'vendor/autoload.php';
require_once 'clearance_template.php';

$action     = $_GET['action'] ?? '';
$student_id = $_GET['id'] ?? '';

if ($action === 'approve' && !empty($student_id)) {

    // --- 1. Get student record ---
    $check_stmt = $conn->prepare("SELECT username, full_name, email, final_clearance_status, course, year, section, contact FROM users WHERE username = ? AND role = 'student'");
    $check_stmt->bind_param("s", $student_id);
    $check_stmt->execute();
    $student = $check_stmt->get_result()->fetch_assoc();
    $check_stmt->close();

    if (!$student) {
        header("Location: admin_users.php?msg=" . urlencode("❌ Student not found"));
        exit();
    }

    if ($student['final_clearance_status'] !== 'pending') {
        header("Location: admin_users.php?msg=" . urlencode("⚠️ Student has not requested verification yet"));
        exit();
    }

    // --- 2. Check all requirements cleared ---
    $student_course = $student['course'];

  $req_stmt = $conn->prepare("
    SELECT u.username as sig_user, COUNT(cr.requirement_id) as total_reqs
    FROM course_requirements cr
    JOIN users u ON cr.signatory_id = u.id
    WHERE cr.course_id = (SELECT id FROM courses WHERE course_name = ? LIMIT 1)
    AND u.status = 'active'
    AND cr.requirements_configured = 1
    GROUP BY u.id
");
$req_stmt->bind_param("s", $student_course);
$req_stmt->execute();
$req_result = $req_stmt->get_result();

$all_cleared = true;
while ($req = $req_result->fetch_assoc()) {
    $total = (int)$req['total_reqs'];
    $check_app = $conn->prepare("SELECT COUNT(DISTINCT requirement_id) as cnt FROM applications WHERE username = ? AND signatory = ? AND status = 'Approved'");
    $check_app->bind_param("ss", $student_id, $req['sig_user']);
    $check_app->execute();
    $approved_cnt = (int)$check_app->get_result()->fetch_assoc()['cnt'];
    $check_app->close();
    if ($approved_cnt < $total) {
        $all_cleared = false;
        break;
    }
}
$req_stmt->close();

    if (!$all_cleared) {
        header("Location: admin_users.php?msg=" . urlencode("❌ Student has incomplete requirements. Cannot approve."));
        exit();
    }

    // --- 3. Approve ---
    $approve_stmt = $conn->prepare("UPDATE users SET admin_approved = 1, final_clearance_status = 'cleared', admin_approved_at = NOW() WHERE username = ?");
    $approve_stmt->bind_param("s", $student_id);

    if ($approve_stmt->execute()) {
        $approve_stmt->close();

        // --- 4. Notification ---
        $notification_msg = "🎉 Congratulations! Your final clearance has been approved by the admin. You can now generate your clearance form.";
        $notif_stmt = $conn->prepare("INSERT INTO notifications (username, message, type, created_at, is_read) VALUES (?, ?, 'success', NOW(), 0)");
        $notif_stmt->bind_param("ss", $student_id, $notification_msg);
        $notif_stmt->execute();
        $notif_stmt->close();

        // --- 5. Build clearance data for email ---
        if (!empty($student['email'])) {

            // Academic year & semester
            $sys = $conn->query("SELECT current_semester, current_school_year FROM system_settings WHERE id=1")->fetch_assoc();
$academic_year = $sys['current_school_year'] ?? date('Y').'-'.(date('Y')+1);
$semester = $sys['current_semester'] ?? '1st Semester';

            $user_course   = $student['course'];
            $user_year     = $student['year'];
            $user_section  = $student['section'];
            $user_email    = $student['email'];
            $full_name     = $student['full_name'];
            $username      = $student_id;

            // Get course_id
            $cid_stmt = $conn->prepare("SELECT id FROM courses WHERE course_name = ? LIMIT 1");
            $cid_stmt->bind_param("s", $user_course);
            $cid_stmt->execute();
            $cid_row = $cid_stmt->get_result()->fetch_assoc();
            $cid_stmt->close();
            $student_course_id = $cid_row['id'] ?? 0;

            // Build sigs_data (same logic as student_history.php)
            $sigs_data = [];
            $signatory_names = [];
            $name_q = $conn->query("SELECT username, full_name FROM users WHERE role='signatory'");
            if ($name_q) while ($n = $name_q->fetch_assoc()) $signatory_names[$n['username']] = $n['full_name'];

            // Course signatories
            if ($student_course_id > 0) {
                $assigned_q = $conn->prepare("
                    SELECT u.username, u.signatory_type, u.full_name,
                           MAX(cr.requirements_configured) as is_configured,
                           COUNT(cr.requirement_id) as req_count
                    FROM course_requirements cr
                    JOIN users u ON cr.signatory_id = u.id
                    WHERE cr.course_id = ?
                    AND u.status = 'active'
                    AND u.signatory_type NOT IN ('Class Adviser', 'Program Head')
                    GROUP BY u.id
                ");
                $assigned_q->bind_param("i", $student_course_id);
                $assigned_q->execute();
                $assigned_res = $assigned_q->get_result();

                while ($row = $assigned_res->fetch_assoc()) {
                    $sig_user  = $row['username'];
                    $sig_type  = $row['signatory_type'];
                    if (stripos($sig_type, 'Registrar') !== false) continue;

                    $is_configured = (int)$row['is_configured'];
                    $req_count     = (int)$row['req_count'];

                    $approved_req = $conn->prepare("SELECT COUNT(DISTINCT a.requirement_id) as cnt FROM applications a WHERE a.username = ? AND a.signatory = ? AND a.status = 'Approved'");
                    $approved_req->bind_param("ss", $username, $sig_user);
                    $approved_req->execute();
                    $approved_cnt = (int)$approved_req->get_result()->fetch_assoc()['cnt'];
                    $approved_req->close();

                    $is_cleared = (!$is_configured == 0 && $approved_cnt >= $req_count && $req_count > 0);
                    $actual_date = '';
                    if ($is_cleared) {
                        $date_stmt = $conn->prepare("SELECT a.reviewed_at FROM applications a WHERE a.username = ? AND a.signatory = ? AND a.status = 'Approved' ORDER BY a.reviewed_at DESC LIMIT 1");
                        $date_stmt->bind_param("ss", $username, $sig_user);
                        $date_stmt->execute();
                        $date_row = $date_stmt->get_result()->fetch_assoc();
                        $date_stmt->close();
                        $actual_date = $date_row ? date('m/d/Y', strtotime($date_row['reviewed_at'])) : date('m/d/Y');
                    }

                    $sigs_data[$sig_type] = [
                        'label'    => $sig_type,
                        'approved' => $is_cleared,
                        'date'     => $actual_date,
                        'name'     => $signatory_names[$sig_user] ?? '',
                    ];
                }
                $assigned_q->close();
            }

            // Class Adviser
            $adv_stmt = $conn->prepare("
                SELECT username, full_name,
                       MAX(cr.requirements_configured) as is_configured,
                       COUNT(cr.requirement_id) as req_count
                FROM users u
                LEFT JOIN course_requirements cr ON cr.signatory_id = u.id AND cr.course_id = ?
                WHERE u.role = 'signatory'
                AND u.signatory_type = 'Class Adviser'
                AND u.status = 'active'
                AND FIND_IN_SET(CONCAT(?, '|', ?, '|', ?), REPLACE(u.section, ', ', ',')) > 0
                GROUP BY u.id LIMIT 1
            ");
            $adv_stmt->bind_param("isss", $student_course_id, $user_course, $user_year, $user_section);
            $adv_stmt->execute();
            $adv_row = $adv_stmt->get_result()->fetch_assoc();
            $adv_stmt->close();

            if ($adv_row) {
                $sig_user      = $adv_row['username'];
                $is_configured = (int)$adv_row['is_configured'];
                $req_count     = (int)$adv_row['req_count'];

                $approved_req = $conn->prepare("SELECT COUNT(DISTINCT a.requirement_id) as cnt FROM applications a WHERE a.username = ? AND a.signatory = ? AND a.status = 'Approved'");
                $approved_req->bind_param("ss", $username, $sig_user);
                $approved_req->execute();
                $approved_cnt = (int)$approved_req->get_result()->fetch_assoc()['cnt'];
                $approved_req->close();

                $is_cleared  = ($is_configured != 0 && $approved_cnt >= $req_count && $req_count > 0);
                $actual_date = '';
                if ($is_cleared) {
                    $date_stmt = $conn->prepare("SELECT a.reviewed_at FROM applications a WHERE a.username = ? AND a.signatory = ? AND a.status = 'Approved' ORDER BY a.reviewed_at DESC LIMIT 1");
                    $date_stmt->bind_param("ss", $username, $sig_user);
                    $date_stmt->execute();
                    $date_row = $date_stmt->get_result()->fetch_assoc();
                    $date_stmt->close();
                    $actual_date = $date_row ? date('m/d/Y', strtotime($date_row['reviewed_at'])) : date('m/d/Y');
                }

                $sigs_data['Class Adviser'] = [
                    'label'    => 'Class Adviser',
                    'approved' => $is_cleared,
                    'date'     => $actual_date,
                    'name'     => $adv_row['full_name'],
                ];
            }

            // Program Head
            $ph_stmt = $conn->prepare("
                SELECT username, full_name,
                       MAX(cr.requirements_configured) as is_configured,
                       COUNT(cr.requirement_id) as req_count
                FROM users u
                LEFT JOIN course_requirements cr ON cr.signatory_id = u.id AND cr.course_id = ?
                WHERE u.role = 'signatory'
                AND u.signatory_type = 'Program Head'
                AND u.status = 'active'
                AND FIND_IN_SET(?, REPLACE(u.department, ' ', ''))
                GROUP BY u.id LIMIT 1
            ");
            $ph_stmt->bind_param("is", $student_course_id, $user_course);
            $ph_stmt->execute();
            $ph_row = $ph_stmt->get_result()->fetch_assoc();
            $ph_stmt->close();

            if ($ph_row) {
                $sig_user      = $ph_row['username'];
                $is_configured = (int)$ph_row['is_configured'];
                $req_count     = (int)$ph_row['req_count'];

                $approved_req = $conn->prepare("SELECT COUNT(DISTINCT a.requirement_id) as cnt FROM applications a WHERE a.username = ? AND a.signatory = ? AND a.status = 'Approved'");
                $approved_req->bind_param("ss", $username, $sig_user);
                $approved_req->execute();
                $approved_cnt = (int)$approved_req->get_result()->fetch_assoc()['cnt'];
                $approved_req->close();

                $is_cleared  = ($is_configured != 0 && $approved_cnt >= $req_count && $req_count > 0);
                $actual_date = '';
                if ($is_cleared) {
                    $date_stmt = $conn->prepare("SELECT a.reviewed_at FROM applications a WHERE a.username = ? AND a.signatory = ? AND a.status = 'Approved' ORDER BY a.reviewed_at DESC LIMIT 1");
                    $date_stmt->bind_param("ss", $username, $sig_user);
                    $date_stmt->execute();
                    $date_row = $date_stmt->get_result()->fetch_assoc();
                    $date_stmt->close();
                    $actual_date = $date_row ? date('m/d/Y', strtotime($date_row['reviewed_at'])) : date('m/d/Y');
                }

                $sigs_data['Program Head'] = [
                    'label'    => 'Program Head',
                    'approved' => $is_cleared,
                    'date'     => $actual_date,
                    'name'     => $ph_row['full_name'],
                ];
            }

            // Registrar
            $registrar_name = '';
            $reg_q = $conn->query("SELECT full_name FROM users WHERE role='signatory' AND signatory_type LIKE '%Registrar%' LIMIT 1");
            if ($reg_q && $reg_q->num_rows > 0) $registrar_name = $reg_q->fetch_assoc()['full_name'];

            // Use today as approved date (we just approved right now)
            $registrar_approved_date = date('m/d/Y');
            $admin_approved          = 1;

            // --- 6. Build clearance HTML ---
            $clearanceHTML = getClearanceHTML(
                $full_name,
                $user_course,
                $user_year,
                $user_section,
                $user_email,
                $username,
                $academic_year,
                $semester,
                $sigs_data,
                $registrar_name,
                $registrar_approved_date,
                $admin_approved
            );

            // --- 7. Send email with clearance in body ---
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
                $mail->addAddress($student['email'], $student['full_name']);
                $mail->isHTML(true);
                $mail->Subject = '🎉 Your BPC Clearance Has Been Approved!';

                $mail->Body = '
                <div style="font-family:Arial,sans-serif; max-width:850px; margin:0 auto;">

                    <!-- Top Banner -->
                    <div style="background:linear-gradient(135deg,#2d5016,#1a3409); padding:25px 30px; text-align:center; border-radius:12px 12px 0 0;">
                        <h1 style="color:white; margin:0; font-size:22px;">🎉 Clearance Approved!</h1>
                        <p style="color:rgba(255,255,255,0.85); margin:6px 0 0; font-size:13px;">BPC Clearance System</p>
                    </div>

                    <!-- Intro message -->
                    <div style="background:#f9f9f9; padding:20px 30px; border-left:4px solid #2d5016; border-right:4px solid #2d5016;">
                        <p style="font-size:14px; color:#333; margin:0 0 8px;">
                            Dear <strong>' . htmlspecialchars($full_name) . '</strong>,
                        </p>
                        <p style="font-size:13px; color:#555; line-height:1.7; margin:0;">
                            Your final clearance has been <strong style="color:#27ae60;">officially approved</strong>.
                            Your clearance form is below for your reference. If you wish to print or download it,
                            you may log in to the BPC Clearance System anytime.
                        </p>
                    </div>

                    <!-- Clearance Form -->
                    <div style="background:white; border:2px solid #dee2e6; border-radius:0 0 12px 12px; padding:10px;">
                        ' . $clearanceHTML . '
                    </div>

                    <!-- Footer note -->
                    <p style="font-size:11px; color:#aaa; text-align:center; margin-top:15px;">
                        This is an automated message from the BPC Clearance System. Please do not reply to this email.
                    </p>

                </div>';

                $mail->send();

            } catch (Exception $e) {
                error_log("Clearance email failed for $student_id: " . $mail->ErrorInfo);
            }
        }

        $conn->close();
        header("Location: admin_users.php?msg=" . urlencode("✅ Final clearance approved and emailed to " . $student['full_name']));
        exit();

    } else {
        $approve_stmt->close();
        $conn->close();
        header("Location: admin_users.php?msg=" . urlencode("❌ Error approving clearance. Please try again."));
        exit();
    }

} else {
    $conn->close();
    header("Location: admin_users.php?msg=" . urlencode("❌ Invalid action"));
    exit();
}
?>