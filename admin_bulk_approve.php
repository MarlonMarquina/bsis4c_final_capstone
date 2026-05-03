<?php
// FILE: admin_bulk_approve.php
session_start();
date_default_timezone_set('Asia/Manila');
include 'conn.php';
header('Content-Type: application/json');
require_once 'vendor/autoload.php';
require_once 'clearance_template.php';
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

if (!isset($_SESSION['role']) || $_SESSION['role'] != 'admin') {
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

$input     = json_decode(file_get_contents('php://input'), true);
$usernames = $input['usernames'] ?? [];

if (empty($usernames) || !is_array($usernames)) {
    echo json_encode(['success' => false, 'message' => 'No students selected.']);
    exit;
}

$approved_by = $_SESSION['username'];
$approved    = 0;

foreach ($usernames as $username) {
    $username = trim($username);
    if (empty($username)) continue;

    // Only approve students who have pending final clearance and are not yet approved
    $check = $conn->prepare("
        SELECT username FROM users 
        WHERE username = ? AND role = 'student' 
        AND final_clearance_status = 'pending' 
        AND admin_approved = 0
    ");
    $check->bind_param("s", $username);
    $check->execute();
    $valid = $check->get_result()->num_rows > 0;
    $check->close();

    if (!$valid) continue;

    $update = $conn->prepare("
        UPDATE users 
        SET admin_approved = 1, admin_approved_at = NOW(), admin_approved_by = ?,
            final_clearance_status = 'cleared'
        WHERE username = ? AND role = 'student'
    ");
    $update->bind_param("ss", $approved_by, $username);
    if ($update->execute()) {
        $approved++;

       // Add notification
    $notif_msg = '🎉 Congratulations! Your final clearance has been approved by the admin. You can now generate your clearance form.';
    $notif = $conn->prepare("INSERT INTO notifications (username, message, type) VALUES (?, ?, 'success')");
    $notif->bind_param("ss", $username, $notif_msg);
    $notif->execute();
    $notif->close();

    // Send approval email
    $email_stmt = $conn->prepare("SELECT full_name, email FROM users WHERE username = ?");
    $email_stmt->bind_param("s", $username);
    $email_stmt->execute();
    $student_data = $email_stmt->get_result()->fetch_assoc();
    $email_stmt->close();

   if ($student_data && !empty($student_data['email'])) {
    try {
        // Get student full details for clearance
        $full_stmt = $conn->prepare("SELECT course, year, section, email FROM users WHERE username = ?");
        $full_stmt->bind_param("s", $username);
        $full_stmt->execute();
        $full_data = $full_stmt->get_result()->fetch_assoc();
        $full_stmt->close();

        $user_course  = $full_data['course'];
        $user_year    = $full_data['year'];
        $user_section = $full_data['section'];
        $full_name    = $student_data['full_name'];

        $current_year  = date('Y');
        $academic_year = $current_year . '-' . ($current_year + 1);
        $current_month = date('n');
        $semester      = ($current_month >= 6 && $current_month <= 10) ? '1st Semester' : '2nd Semester';

        // Get course_id
        $cid_stmt = $conn->prepare("SELECT id FROM courses WHERE course_name = ? LIMIT 1");
        $cid_stmt->bind_param("s", $user_course);
        $cid_stmt->execute();
        $cid_row = $cid_stmt->get_result()->fetch_assoc();
        $cid_stmt->close();
        $student_course_id = $cid_row['id'] ?? 0;

        // Build sigs_data
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
                $sig_user = $row['username'];
                $sig_type = $row['signatory_type'];
                if (stripos($sig_type, 'Registrar') !== false) continue;
                $req_count = (int)$row['req_count'];
                $approved_req = $conn->prepare("SELECT COUNT(DISTINCT a.requirement_id) as cnt FROM applications a WHERE a.username = ? AND a.signatory = ? AND a.status = 'Approved'");
                $approved_req->bind_param("ss", $username, $sig_user);
                $approved_req->execute();
                $approved_cnt = (int)$approved_req->get_result()->fetch_assoc()['cnt'];
                $approved_req->close();
                $is_cleared = ($approved_cnt >= $req_count && $req_count > 0);
                $actual_date = '';
                if ($is_cleared) {
                    $date_stmt = $conn->prepare("SELECT a.reviewed_at FROM applications a WHERE a.username = ? AND a.signatory = ? AND a.status = 'Approved' ORDER BY a.reviewed_at DESC LIMIT 1");
                    $date_stmt->bind_param("ss", $username, $sig_user);
                    $date_stmt->execute();
                    $date_row = $date_stmt->get_result()->fetch_assoc();
                    $date_stmt->close();
                    $actual_date = $date_row ? date('m/d/Y', strtotime($date_row['reviewed_at'])) : date('m/d/Y');
                }
                $sigs_data[$sig_type] = ['label' => $sig_type, 'approved' => $is_cleared, 'date' => $actual_date, 'name' => $signatory_names[$sig_user] ?? ''];
            }
            $assigned_q->close();
        }

        // Class Adviser
        $adv_stmt = $conn->prepare("
            SELECT username, full_name, MAX(cr.requirements_configured) as is_configured, COUNT(cr.requirement_id) as req_count
            FROM users u
            LEFT JOIN course_requirements cr ON cr.signatory_id = u.id AND cr.course_id = ?
            WHERE u.role = 'signatory' AND u.signatory_type = 'Class Adviser' AND u.status = 'active'
            AND FIND_IN_SET(CONCAT(?, '|', ?, '|', ?), REPLACE(u.section, ', ', ',')) > 0
            GROUP BY u.id LIMIT 1
        ");
        $adv_stmt->bind_param("isss", $student_course_id, $user_course, $user_year, $user_section);
        $adv_stmt->execute();
        $adv_row = $adv_stmt->get_result()->fetch_assoc();
        $adv_stmt->close();
        if ($adv_row) {
            $sig_user  = $adv_row['username'];
            $req_count = (int)$adv_row['req_count'];
            $approved_req = $conn->prepare("SELECT COUNT(DISTINCT a.requirement_id) as cnt FROM applications a WHERE a.username = ? AND a.signatory = ? AND a.status = 'Approved'");
            $approved_req->bind_param("ss", $username, $sig_user);
            $approved_req->execute();
            $approved_cnt = (int)$approved_req->get_result()->fetch_assoc()['cnt'];
            $approved_req->close();
            $is_cleared = ($approved_cnt >= $req_count && $req_count > 0);
            $actual_date = '';
            if ($is_cleared) {
                $date_stmt = $conn->prepare("SELECT a.reviewed_at FROM applications a WHERE a.username = ? AND a.signatory = ? AND a.status = 'Approved' ORDER BY a.reviewed_at DESC LIMIT 1");
                $date_stmt->bind_param("ss", $username, $sig_user);
                $date_stmt->execute();
                $date_row = $date_stmt->get_result()->fetch_assoc();
                $date_stmt->close();
                $actual_date = $date_row ? date('m/d/Y', strtotime($date_row['reviewed_at'])) : date('m/d/Y');
            }
            $sigs_data['Class Adviser'] = ['label' => 'Class Adviser', 'approved' => $is_cleared, 'date' => $actual_date, 'name' => $adv_row['full_name']];
        }

        // Program Head
        $ph_stmt = $conn->prepare("
            SELECT username, full_name, MAX(cr.requirements_configured) as is_configured, COUNT(cr.requirement_id) as req_count
            FROM users u
            LEFT JOIN course_requirements cr ON cr.signatory_id = u.id AND cr.course_id = ?
            WHERE u.role = 'signatory' AND u.signatory_type = 'Program Head' AND u.status = 'active'
            AND FIND_IN_SET(?, REPLACE(u.department, ' ', ''))
            GROUP BY u.id LIMIT 1
        ");
        $ph_stmt->bind_param("is", $student_course_id, $user_course);
        $ph_stmt->execute();
        $ph_row = $ph_stmt->get_result()->fetch_assoc();
        $ph_stmt->close();
        if ($ph_row) {
            $sig_user  = $ph_row['username'];
            $req_count = (int)$ph_row['req_count'];
            $approved_req = $conn->prepare("SELECT COUNT(DISTINCT a.requirement_id) as cnt FROM applications a WHERE a.username = ? AND a.signatory = ? AND a.status = 'Approved'");
            $approved_req->bind_param("ss", $username, $sig_user);
            $approved_req->execute();
            $approved_cnt = (int)$approved_req->get_result()->fetch_assoc()['cnt'];
            $approved_req->close();
            $is_cleared = ($approved_cnt >= $req_count && $req_count > 0);
            $actual_date = '';
            if ($is_cleared) {
                $date_stmt = $conn->prepare("SELECT a.reviewed_at FROM applications a WHERE a.username = ? AND a.signatory = ? AND a.status = 'Approved' ORDER BY a.reviewed_at DESC LIMIT 1");
                $date_stmt->bind_param("ss", $username, $sig_user);
                $date_stmt->execute();
                $date_row = $date_stmt->get_result()->fetch_assoc();
                $date_stmt->close();
                $actual_date = $date_row ? date('m/d/Y', strtotime($date_row['reviewed_at'])) : date('m/d/Y');
            }
            $sigs_data['Program Head'] = ['label' => 'Program Head', 'approved' => $is_cleared, 'date' => $actual_date, 'name' => $ph_row['full_name']];
        }

        // Registrar
        $registrar_name = '';
        $reg_q = $conn->query("SELECT full_name FROM users WHERE role='signatory' AND signatory_type LIKE '%Registrar%' LIMIT 1");
        if ($reg_q && $reg_q->num_rows > 0) $registrar_name = $reg_q->fetch_assoc()['full_name'];

        $clearanceHTML = getClearanceHTML(
            $full_name, $user_course, $user_year, $user_section,
            $student_data['email'], $username,
            $academic_year, $semester,
            $sigs_data, $registrar_name, date('m/d/Y'), 1
        );

        $mail = new PHPMailer(true);
        $mail->isSMTP();
        $mail->Host       = 'smtp.gmail.com';
        $mail->SMTPAuth   = true;
        $mail->Username   = 'clearancebpc@gmail.com';
        $mail->Password   = 'powe wgem hlsv ybyq';
        $mail->SMTPSecure = 'tls';
        $mail->Port       = 587;
        $mail->setFrom('clearancebpc@gmail.com', 'BPC Clearance System');
        $mail->addAddress($student_data['email'], $full_name);
        $mail->isHTML(true);
        $mail->Subject = '🎉 Your BPC Clearance Has Been Approved!';
        $mail->Body = '
        <div style="font-family:Arial,sans-serif; max-width:850px; margin:0 auto;">
            <div style="background:linear-gradient(135deg,#2d5016,#1a3409); padding:25px 30px; text-align:center; border-radius:12px 12px 0 0;">
                <h1 style="color:white; margin:0; font-size:22px;">🎉 Clearance Approved!</h1>
                <p style="color:rgba(255,255,255,0.85); margin:6px 0 0; font-size:13px;">BPC Clearance System</p>
            </div>
            <div style="background:#f9f9f9; padding:20px 30px; border-left:4px solid #2d5016; border-right:4px solid #2d5016;">
                <p style="font-size:14px; color:#333; margin:0 0 8px;">Dear <strong>' . htmlspecialchars($full_name) . '</strong>,</p>
                <p style="font-size:13px; color:#555; line-height:1.7; margin:0;">
                    Your final clearance has been <strong style="color:#27ae60;">officially approved</strong>.
                    Your clearance form is below for your reference.
                </p>
            </div>
            <div style="background:white; border:2px solid #dee2e6; border-radius:0 0 12px 12px; padding:10px;">
                ' . $clearanceHTML . '
            </div>
            <p style="font-size:11px; color:#aaa; text-align:center; margin-top:15px;">
                This is an automated message. Please do not reply to this email.
            </p>
        </div>';
        $mail->send();

    } catch (\Exception $e) {
        error_log("Bulk approval email failed for $username: " . $e->getMessage());
    }
}
    }
    $update->close();
}

echo json_encode(['success' => true, 'approved' => $approved, 'message' => "$approved student(s) approved."]);
?>