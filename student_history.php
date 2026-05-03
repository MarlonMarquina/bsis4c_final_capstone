<?php
// FILE: student_history.php  
// UPDATED: Dynamic signatories in print template — only shows assigned ones
// FIXED: Proper date handling for auto-approved requirements and admin approval
session_start();
date_default_timezone_set('Asia/Manila');
if (!isset($_SESSION['role']) || $_SESSION['role'] != "student") {
    header("Location: login.php");
    exit();
}

include 'conn.php';
require_once 'clearance_template.php';
date_default_timezone_set('Asia/Manila');

$username = $_SESSION['username'];

// ==== HELPER FUNCTION to fetch student profile ====
function fetchStudentProfile($conn, $username) {
    $res = $conn->prepare("SELECT full_name, course, year, section, contact, email, final_clearance_status, admin_approved, student_id FROM users WHERE username = ? LIMIT 1");
    $res->bind_param("s", $username);
    $res->execute();
    $rres = $res->get_result();
    $data = [
        'full_name' => '',
        'course' => '',
        'year' => '',
        'section' => '',
        'contact' => '',
        'email' => '',
        'final_clearance_status' => 'not_requested',
        'admin_approved' => 0
    ];
    if ($rres && $rres->num_rows > 0) {
        $row = $rres->fetch_assoc();
        $data = [
            'full_name' => $row['full_name'] ?? '',
            'course' => $row['course'] ?? '',
            'year' => $row['year'] ?? '',
            'section' => $row['section'] ?? '',
            'contact' => $row['contact'] ?? '',
            'email' => $row['email'] ?? '',
            'final_clearance_status' => $row['final_clearance_status'] ?? 'not_requested',
            'admin_approved' => $row['admin_approved'] ?? 0
        ];
    }
    $res->close();
    return $data;
}

// ==== 1. Initial fetch of student profile ====
$student_data = fetchStudentProfile($conn, $username);
$full_name = $student_data['full_name'];
$user_course = $student_data['course'];
$user_year = $student_data['year'];
$user_section = $student_data['section'];
$user_contact = $student_data['contact'];
$user_email = $student_data['email'];
$final_clearance_status = $student_data['final_clearance_status'];
$admin_approved = $student_data['admin_approved'];
$admin_approved_raw = $admin_approved;

// === 2. Get ALL Assigned Signatories & Track Progress ===
$assigned_signatories_list = [];
$total_required = 0;
$approved_count = 0;

// Get approved application combos
$approved_from_apps = [];
$app_check = $conn->prepare("SELECT signatory, requirement_id FROM applications WHERE username = ? AND status = 'Approved'");
$app_check->bind_param("s", $username);
$app_check->execute();
$ac_res = $app_check->get_result();
while($a = $ac_res->fetch_assoc()){
    $approved_from_apps[] = $a['signatory'] . '_' . $a['requirement_id'];
}
$app_check->close();

// Get course_id for student's course
$cid_stmt = $conn->prepare("SELECT id FROM courses WHERE course_name = ? LIMIT 1");
$cid_stmt->bind_param("s", $user_course);
$cid_stmt->execute();
$cid_row = $cid_stmt->get_result()->fetch_assoc();
$cid_stmt->close();
$student_course_id = $cid_row['id'] ?? 0;

// Fetch all assigned signatories for this course (one per signatory, regardless of requirement count)
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
        $is_configured = (int)$row['is_configured'];
        $req_count = (int)$row['req_count'];

        // Get approved count for this signatory
        $approved_req = $conn->prepare("
            SELECT COUNT(DISTINCT a.requirement_id) as cnt
            FROM applications a
            WHERE a.username = ? AND a.signatory = ? AND a.status = 'Approved'
        ");
        $approved_req->bind_param("ss", $username, $sig_user);
        $approved_req->execute();
        $approved_cnt = (int)$approved_req->get_result()->fetch_assoc()['cnt'];
        $approved_req->close();

        $assigned_signatories_list[$sig_type] = [
            'username' => $sig_user,
            'type' => $sig_type,
            'full_name' => $row['full_name'],
            'total' => $is_configured ? $req_count : 0,
            'approved' => $approved_cnt,
            'is_cleared' => false,
            'not_configured' => ($is_configured == 0),
        ];
        $total_required++;
    }
    $assigned_q->close();
}

// Auto-inject Class Adviser (matched by course|year|section)
$adv_stmt = $conn->prepare("
    SELECT username, full_name, signatory_type,
           MAX(cr.requirements_configured) as is_configured,
           COUNT(cr.requirement_id) as req_count
    FROM users u
    LEFT JOIN course_requirements cr ON cr.signatory_id = u.id AND cr.course_id = ?
    WHERE u.role = 'signatory'
    AND u.signatory_type = 'Class Adviser'
    AND u.status = 'active'
    AND FIND_IN_SET(CONCAT(?, '|', ?, '|', ?), REPLACE(u.section, ', ', ',')) > 0
    GROUP BY u.id
    LIMIT 1
");
$adv_stmt->bind_param("isss", $student_course_id, $user_course, $user_year, $user_section);
$adv_stmt->execute();
$adv_row = $adv_stmt->get_result()->fetch_assoc();
$adv_stmt->close();

if ($adv_row) {
    $sig_user = $adv_row['username'];
    $is_configured = (int)$adv_row['is_configured'];
    $req_count = (int)$adv_row['req_count'];

    $approved_req = $conn->prepare("
        SELECT COUNT(DISTINCT a.requirement_id) as cnt
        FROM applications a
        WHERE a.username = ? AND a.signatory = ? AND a.status = 'Approved'
    ");
    $approved_req->bind_param("ss", $username, $sig_user);
    $approved_req->execute();
    $approved_cnt = (int)$approved_req->get_result()->fetch_assoc()['cnt'];
    $approved_req->close();

    $class_adviser_name = $adv_row['full_name'];
    $assigned_signatories_list['Class Adviser'] = [
        'username' => $sig_user,
        'type' => 'Class Adviser - ' . $class_adviser_name,
        'full_name' => $class_adviser_name,
        'total' => $is_configured ? $req_count : 0,
        'approved' => $approved_cnt,
        'is_cleared' => false,
        'not_configured' => ($is_configured == 0),
    ];
    $total_required++;
} else {
    $class_adviser_name = '';
}

// Auto-inject Program Head (matched by department containing student's course)
$ph_stmt = $conn->prepare("
    SELECT username, full_name, signatory_type,
           MAX(cr.requirements_configured) as is_configured,
           COUNT(cr.requirement_id) as req_count
    FROM users u
    LEFT JOIN course_requirements cr ON cr.signatory_id = u.id AND cr.course_id = ?
    WHERE u.role = 'signatory'
    AND u.signatory_type = 'Program Head'
    AND u.status = 'active'
    AND (
        u.department = ?
        OR u.department LIKE CONCAT(?, ',%')
        OR u.department LIKE CONCAT('%,', ?)
        OR u.department LIKE CONCAT('%,', ?, ',%')
    )
    GROUP BY u.id
    LIMIT 1
");
$ph_stmt->bind_param("issss", $student_course_id, $user_course, $user_course, $user_course, $user_course);
$ph_stmt->execute();
$ph_row = $ph_stmt->get_result()->fetch_assoc();
$ph_stmt->close();

if ($ph_row) {
    $sig_user = $ph_row['username'];
    $is_configured = (int)$ph_row['is_configured'];
    $req_count = (int)$ph_row['req_count'];

    $approved_req = $conn->prepare("
        SELECT COUNT(DISTINCT a.requirement_id) as cnt
        FROM applications a
        WHERE a.username = ? AND a.signatory = ? AND a.status = 'Approved'
    ");
    $approved_req->bind_param("ss", $username, $sig_user);
    $approved_req->execute();
    $approved_cnt = (int)$approved_req->get_result()->fetch_assoc()['cnt'];
    $approved_req->close();

    $assigned_signatories_list['Program Head'] = [
        'username' => $sig_user,
        'type' => 'Program Head',
        'full_name' => $ph_row['full_name'],
        'total' => $is_configured ? $req_count : 0,
        'approved' => $approved_cnt,
        'is_cleared' => false,
        'not_configured' => ($is_configured == 0),
    ];
    $total_required++;
}

// Mark cleared
foreach ($assigned_signatories_list as $type => &$data) {
    if (!$data['not_configured'] && $data['approved'] >= $data['total'] && $data['total'] > 0) {
        $data['is_cleared'] = true;
        $approved_count++;
    }
}
unset($data);

// Safety net: only handles the edge case where admin_approved=1 but requirements are incomplete
if ($admin_approved_raw == 1 && $approved_count < $total_required) {
    $admin_approved = 0;
    $final_clearance_status = 'not_requested';
    $fix_stmt = $conn->prepare("UPDATE users SET admin_approved = 0, final_clearance_status = 'not_requested' WHERE username = ?");
    $fix_stmt->bind_param("s", $username);
    $fix_stmt->execute();
    $fix_stmt->close();
}

$can_request_verification = ($approved_count >= $total_required && $total_required > 0);

// Get current academic year and semester
$sys = $conn->query("SELECT current_semester, current_school_year FROM system_settings WHERE id=1")->fetch_assoc();
$academic_year = $sys['current_school_year'] ?? date('Y').'-'.(date('Y')+1);
$semester = $sys['current_semester'] ?? '1st Semester';

function h($s){ return htmlspecialchars($s ?? '', ENT_QUOTES, 'UTF-8'); }

// === 3. Handle Post Actions ===
if (isset($_POST['request_verification'])) {
    $status_check = $conn->prepare("SELECT final_clearance_status FROM users WHERE username = ? LIMIT 1");
    $status_check->bind_param("s", $username);
    $status_check->execute();
    $status_result = $status_check->get_result();
    $current_status = $status_result->fetch_assoc()['final_clearance_status'] ?? 'pending';
    $status_check->close();
    
    if ($current_status === 'not_requested') {
        if ($can_request_verification) {
            $upd = $conn->prepare("UPDATE users SET final_clearance_status = 'pending' WHERE username = ?");
            $upd->bind_param("s", $username);
            
            if ($upd->execute()) {
                $upd->close();
                $student_data = fetchStudentProfile($conn, $username);
                $final_clearance_status = $student_data['final_clearance_status'];
                
                $admin_query = $conn->query("SELECT username FROM users WHERE role = 'admin' LIMIT 1");
                $admin_user = $admin_query->fetch_assoc()['username'] ?? 'adminkaren';
                
                $notification_msg = "Student " . $full_name . " (" . $username . ") has requested final clearance verification.";
                $notif_stmt = $conn->prepare("INSERT INTO notifications (username, message, type, created_at, is_read) VALUES (?, ?, 'info', NOW(), 0)");
                $notif_stmt->bind_param("ss", $admin_user, $notification_msg);
                $notif_stmt->execute();
                $notif_stmt->close();
                
                header("Location: student_history.php?msg=" . urlencode("✅ Verification request submitted successfully!"));
                exit();
            }
        } else {
            header("Location: student_history.php?msg=" . urlencode("⚠️ Complete all signatory requirements first."));
            exit();
        }
    } else {
        header("Location: student_history.php?msg=" . urlencode("⚠️ Request already submitted."));
        exit();
    }
}

// === 4. Signatory types map ===
$signatory_types_map = [];
$mapq = $conn->query("SELECT username, signatory_type FROM users WHERE role='signatory'");
if ($mapq) while($m = $mapq->fetch_assoc()) $signatory_types_map[$m['username']] = $m['signatory_type'];

// === 5. Fetch Full History ===
$applications = [];
$app_res = $conn->prepare("SELECT id, signatory, course, document, status, rejection_reason, submitted_at, reviewed_at FROM applications WHERE username = ? AND status != '' ORDER BY submitted_at DESC");
$app_res->bind_param("s", $username);
$app_res->execute();
$ar = $app_res->get_result();
if ($ar) while($row = $ar->fetch_assoc()) $applications[] = $row;
$app_res->close();

// === 6. DYNAMIC MAPPING LOGIC for Print ===
$signatory_names = [];
$name_query = $conn->query("SELECT username, full_name FROM users WHERE role='signatory'");
if ($name_query) {
    while($n = $name_query->fetch_assoc()) {
        $signatory_names[$n['username']] = $n['full_name'];
    }
}

// === 7. GET SIGNATORY APPROVAL DATES WITH FALLBACKS FOR AUTO-APPROVED ===
$sigs_data = [];
foreach ($assigned_signatories_list as $type => $data) {
    $stype = $data['type'];
    if (stripos($stype, 'Registrar') !== false) continue;

    $sig_username = $data['username'];

    // Get actual approval date - with fallbacks for auto-approved requirements
    $actual_date = '';
    if ($data['is_cleared']) {
        // METHOD 1: Try to get from applications.reviewed_at (manual approvals)
        $date_stmt = $conn->prepare("
            SELECT a.reviewed_at 
            FROM applications a
            WHERE a.username = ? 
            AND a.signatory = ? 
            AND a.status = 'Approved'
            ORDER BY a.reviewed_at DESC 
            LIMIT 1
        ");
        $date_stmt->bind_param("ss", $username, $sig_username);
        $date_stmt->execute();
        $date_result = $date_stmt->get_result();
        $date_row = $date_result->fetch_assoc();
        $date_stmt->close();
        
        if ($date_row && !empty($date_row['reviewed_at'])) {
            // Use reviewed_at if available (manual approval)
            $actual_date = date('m/d/Y', strtotime($date_row['reviewed_at']));
        } else {
            // METHOD 2: Try signatory_history.action_date (auto-approvals)
            $history_stmt = $conn->prepare("
                SELECT sh.action_date 
                FROM signatory_history sh
                WHERE sh.student_user = ? 
                AND sh.signatory_username = ?
                AND sh.action_taken = 'Approved'
                ORDER BY sh.action_date DESC 
                LIMIT 1
            ");
            $history_stmt->bind_param("ss", $username, $sig_username);
            $history_stmt->execute();
            $history_result = $history_stmt->get_result();
            $history_row = $history_result->fetch_assoc();
            $history_stmt->close();
            
            if ($history_row && !empty($history_row['action_date'])) {
                // Use action_date from signatory_history (auto-approval)
                $actual_date = date('m/d/Y', strtotime($history_row['action_date']));
            } else {
                // METHOD 3: Fallback to submitted_at
                $submitted_stmt = $conn->prepare("
                    SELECT submitted_at 
                    FROM applications 
                    WHERE username = ? 
                    AND signatory = ? 
                    AND status = 'Approved'
                    ORDER BY submitted_at DESC 
                    LIMIT 1
                ");
                $submitted_stmt->bind_param("ss", $username, $sig_username);
                $submitted_stmt->execute();
                $submitted_result = $submitted_stmt->get_result();
                $submitted_row = $submitted_result->fetch_assoc();
                $submitted_stmt->close();
                
                if ($submitted_row && !empty($submitted_row['submitted_at'])) {
                    // Fallback to submitted_at date
                    $actual_date = date('m/d/Y', strtotime($submitted_row['submitted_at']));
                } else {
                    // Final fallback to current date
                    $actual_date = date('m/d/Y');
                }
            }
        }
    }

    // Build label: if Class Adviser, append name
    $display_label = $stype;
    $sig_full_name = $signatory_names[$sig_username] ?? '';

    $sigs_data[$stype] = [
        'label' => $display_label,
        'approved' => $data['is_cleared'],
        'date' => $actual_date,
        'name' => $sig_full_name
    ];
}

// === 8. GET REGISTRAR/ADMIN APPROVAL DATE (use stored admin_approved_at) ===
$registrar_name = '';
$reg_query = $conn->query("SELECT full_name FROM users WHERE role='signatory' AND signatory_type LIKE '%Registrar%' LIMIT 1");
if ($reg_query && $reg_query->num_rows > 0) {
    $registrar_name = $reg_query->fetch_assoc()['full_name'];
}

// Get the actual admin approval date from users table
$registrar_approved_date = '';
$reg_date_stmt = $conn->prepare("SELECT admin_approved_at FROM users WHERE username = ? LIMIT 1");
$reg_date_stmt->bind_param("s", $username);
$reg_date_stmt->execute();
$user_date_data = $reg_date_stmt->get_result()->fetch_assoc();
$reg_date_stmt->close();

if ($user_date_data && !empty($user_date_data['admin_approved_at'])) {
    // Use the actual stored approval date from when admin approved
    $registrar_approved_date = date('m/d/Y', strtotime($user_date_data['admin_approved_at']));
} else {
    // Fallback to current date if no stored date exists
    $registrar_approved_date = date('m/d/Y');
}

$msg = $_GET['msg'] ?? '';
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width,initial-scale=1.0">
<title>History & Verification | Smart Clearance System</title>
<link href='https://unpkg.com/boxicons@2.1.4/css/boxicons.min.css' rel='stylesheet'>
<link rel="stylesheet" href="styles.css"> 
<style>
.clearance-container{width:90%;margin:30px auto;background:rgba(255,255,255,0.95);border-radius:10px;padding:20px;text-align:center;box-shadow:0 4px 8px rgba(0,0,0,0.1);}
.clearance-header{display:flex;justify-content:center;align-items:center;background:darkgreen;color:white;border-radius:25px;padding:10px 20px;margin-bottom:30px;}
.clearance-header h2{font-size:20px;font-weight:700;}
table{border-collapse:collapse;width:100%;margin-top:20px;}
table th,table td{padding:10px;border:1px solid #ddd;text-align:center;}
table th{background:darkgreen;color:white;}
.status{padding:5px 10px; border-radius: 5px; font-weight: bold;}
.status.Pending{background:#007bff;color:white;}
.status.Approved{background:#28a745;color:white;}

.verification-card {
    background: linear-gradient(135deg, #155724 0%, #0d3d1a 100%);
    color: white; padding: 25px; border-radius: 15px; margin-bottom: 30px; text-align: left;
    box-shadow: 0 10px 20px rgba(0,0,0,0.2);
}
.progress-bar { background: rgba(255,255,255,0.2); height: 25px; border-radius: 15px; overflow: hidden; margin: 15px 0; border: 1px solid rgba(255,255,255,0.3); }
.progress-fill { background: #28a745; height: 100%; display: flex; align-items: center; justify-content: center; font-weight: bold; transition: width 0.8s ease; }
.btn-request { background: #ffc107; color: #000; border: none; padding: 12px 25px; font-size: 16px; font-weight: 700; cursor: pointer; border-radius: 8px; transition: 0.3s; }
.btn-request:disabled { background: #6c757d; color: #fff; cursor: not-allowed; opacity: 0.5; }
.btn-request:hover:not(:disabled) { background: #e0a800; transform: translateY(-2px); }
.status-badge { display: inline-block; padding: 10px 20px; border-radius: 30px; font-weight: 700; margin-top: 10px; }
.badge-review { background: #17a2b8; color: white; }
.badge-approved { background: #28a745; color: white; }

.print-btn { background: #0069d9; color: white; border: none; padding: 12px 25px; font-size: 16px; cursor: pointer; border-radius: 8px; float: right; display: flex; align-items: center; gap: 10px; font-weight: bold; }
.print-btn:disabled { background: #6c757d; cursor: not-allowed; }
.print-btn:hover:not(:disabled) { background: #0056b3; transform: translateY(-2px); }

#printableForm { display: none; }
@media print {
    body * { visibility: hidden; }
    #printableForm, #printableForm * { visibility: visible; }
    #printableForm { display: block !important; position: absolute; left: 0; top: 0; width: 100%; }
    .sidebar, .home, .verification-card, .clearance-container { display: none !important; }
}

.alert-message {
    background: #d4edda; border: 1px solid #c3e6cb; color: #155724; padding: 15px; border-radius: 8px; margin-bottom: 20px; text-align: center;
    animation: slideDown 0.5s ease;
}
.alert-message.error { background: #f8d7da; border-color: #f5c6cb; color: #721c24; }
.alert-message.warning { background: #fff3cd; border-color: #ffeaa7; color: #856404; }
@keyframes slideDown { from { opacity: 0; transform: translateY(-20px); } to { opacity: 1; transform: translateY(0); } }
</style>
</head>
<body>

<?php include 'sidebar_student.php'; ?>

<section class="home">
    <div class="text">
        <div class="clearance-header">
            <h2>APPLICATION HISTORY & STATUS</h2>
        </div>
    </div>

    <div class="clearance-container" style="text-align: left;">
        <?php if (!empty($msg)): 
            $msgClass = 'alert-message';
            if (strpos($msg, '❌') !== false) $msgClass .= ' error';
            elseif (strpos($msg, '⚠️') !== false) $msgClass .= ' warning';
        ?>
        <div class="<?= $msgClass ?>">
            <i class='bx bx-info-circle' style="font-size: 20px; vertical-align: middle;"></i>
            <strong><?= h($msg) ?></strong>
        </div>
        <?php endif; ?>
        
        <div class="verification-card">
            <h3><i class='bx bx-badge-check'></i> Clearance Verification</h3>
            <p>To generate your final clearance, you must be cleared by all assigned signatories.</p>
            
            <div class="progress-bar">
                <?php 
                $progress_percent = ($total_required > 0) ? round(($approved_count / $total_required) * 100) : 0;
                ?>
                <div class="progress-fill" style="width: <?= $progress_percent ?>%;">
                    <?= $progress_percent ?>% Completed (<?= $approved_count ?> / <?= $total_required ?>)
                </div>
            </div>

            <div style="display: flex; align-items: center; justify-content: space-between; margin-top: 20px;">
                <div>
                    <?php if ($admin_approved == 1): ?>
                        <span class="status-badge badge-approved"><i class='bx bxs-check-circle'></i> FINAL CLEARANCE APPROVED</span>
                    <?php elseif ($final_clearance_status === 'pending'): ?>
                        <span class="status-badge badge-review"><i class='bx bx-sync bx-spin'></i> PENDING ADMIN VERIFICATION</span>
                    <?php else: ?>
                        <form method="POST" onsubmit="return confirm('Submit verification request to admin?');">
                            <button type="submit" name="request_verification" class="btn-request" <?= !$can_request_verification ? 'disabled' : '' ?>>
                                <i class='bx bx-send'></i> REQUEST VERIFICATION TO ADMIN
                            </button>
                        </form>
                    <?php endif; ?>
                </div>

                <?php if ($admin_approved == 1): ?>
                <button onclick="window.print()" class="print-btn">
                    <i class='bx bx-printer'></i> GENERATE CLEARANCE FORM
                </button>
                <?php endif; ?>
            </div>
            
            <?php if (!$can_request_verification && $final_clearance_status === 'not_requested'): ?>
                <p style="color: #ffc107; font-size: 13px; margin-top: 10px;">
                    <i class='bx bx-info-circle'></i> Complete all signatory requirements first.
                </p>
            <?php endif; ?>
        </div>

        <h3 style="color: darkgreen; margin-bottom: 15px;">Signatory Checklist</h3>
        <table>
            <thead>
                <tr>
                    <th>Signatory Type</th>
                    <th>Status</th>
                    <th>Note</th>
                </tr>
            </thead>
            <tbody>
<?php foreach($assigned_signatories_list as $type => $data): 
    $is_cleared = $data['is_cleared'];
    $approved = $data['approved'];
    $total = $data['total'];

    $rejected_count = 0;
    $check_rej = $conn->prepare("
        SELECT COUNT(DISTINCT a.requirement_id) as cnt 
        FROM applications a
        JOIN users u ON a.signatory = u.username
        WHERE a.username = ? 
        AND u.signatory_type = ?
        AND a.status = 'Totally Rejected'
    ");
    $check_rej->bind_param("ss", $username, $type);
    $check_rej->execute();
    $rejected_count = (int)$check_rej->get_result()->fetch_assoc()['cnt'];
    $check_rej->close();
?>
<tr>
    <?php
    // Check if this signatory is locked by prerequisites
    $hist_sig_locked = false;
    if ($student_course_id > 0 && !empty($data['username'])) {
        $lock_q = $conn->prepare("
            SELECT before_type
            FROM signatory_prerequisites
            WHERE course_id = ? AND signatory_type = ?
            AND admin_enabled = 1 AND signatory_enabled = 1
        ");
        $hist_sig_type = $data['type'] ?? '';
        // Strip name suffix if class adviser (e.g. "Class Adviser - John" → "Class Adviser")
        if (strpos($hist_sig_type, ' - ') !== false) {
            $hist_sig_type = explode(' - ', $hist_sig_type)[0];
        }
        $lock_q->bind_param("is", $student_course_id, $hist_sig_type);
        $lock_q->execute();
        $lock_res = $lock_q->get_result();
        while ($lk = $lock_res->fetch_assoc()) {
            $before_type = $lk['before_type'];
            // Find signatories of this type
            $lk_sigs = $conn->prepare("
                SELECT DISTINCT u.id, u.username FROM course_requirements cr
                JOIN users u ON cr.signatory_id = u.id
                WHERE cr.course_id = ? AND u.signatory_type = ?
                AND u.status = 'active' AND cr.requirements_configured = 1
            ");
            $lk_sigs->bind_param("is", $student_course_id, $before_type);
            $lk_sigs->execute();
            $lk_sigs_res = $lk_sigs->get_result();
            while ($lksig = $lk_sigs_res->fetch_assoc()) {
                $lk_total_q = $conn->prepare("SELECT COUNT(*) as cnt FROM course_requirements WHERE signatory_id = ? AND course_id = ? AND requirements_configured = 1");
                $lk_total_q->bind_param("ii", $lksig['id'], $student_course_id);
                $lk_total_q->execute();
                $lk_total = (int)$lk_total_q->get_result()->fetch_assoc()['cnt'];
                $lk_total_q->close();
                if ($lk_total === 0) continue;
                $lk_ap = $conn->prepare("SELECT COUNT(DISTINCT a.requirement_id) as cnt FROM applications a WHERE a.username = ? AND a.signatory = ? AND a.status = 'Approved'");
                $lk_ap->bind_param("ss", $username, $lksig['username']);
                $lk_ap->execute();
                $lk_approved = (int)$lk_ap->get_result()->fetch_assoc()['cnt'];
                $lk_ap->close();
                if ($lk_approved < $lk_total) { $hist_sig_locked = true; break 2; }
            }
            $lk_sigs->close();
        }
        $lock_q->close();
    }
    ?>
    <td><?= h($data['type']) ?><?= $hist_sig_locked ? ' <span style="color:#856404; font-size:12px;">🔒 Locked</span>' : '' ?></td>
    <td>
        <?php if ($data['not_configured']): ?>
            <span class="status" style="background:#856404; color:white; padding:4px 8px; border-radius:5px; font-weight:600; display:inline-block;">⚠️ Pending Setup</span>
        <?php elseif ($is_cleared): ?>
            <span class="status Approved">✅ CLEARED</span>
        <?php elseif ($rejected_count > 0 && $approved === 0 && $total === $rejected_count): ?>
            <span class="status" style="background:#8b0000; color:white; padding:4px 8px; border-radius:5px; font-weight:600; display:inline-block;">⛔ Permanently Rejected</span>
        <?php elseif ($rejected_count > 0 && $approved > 0): ?>
            <span class="status Pending"><?= $approved ?>/<?= $total ?> Completed</span>
            <span class="status" style="background:#8b0000; color:white; padding:4px 8px; border-radius:5px; font-weight:600; display:inline-block; margin-top:4px;">⛔ <?= $rejected_count ?> Permanently Rejected</span>
        <?php elseif ($approved > 0): ?>
            <span class="status Pending"><?= $approved ?>/<?= $total ?> Completed</span>
        <?php else: ?>
            <span class="status Pending">⏳ 0/<?= $total ?> Completed</span>
        <?php endif; ?>
    </td>
    <td>
        <small>
            <?php if ($data['not_configured']): ?>
                <span style="color:#856404; font-weight:600;">⚠️ Signatory has not yet set requirements</span>
            <?php elseif ($is_cleared): ?>
                All <?= $total ?> requirement(s) approved
            <?php elseif ($rejected_count > 0 && $approved === 0 && $total === $rejected_count): ?>
                <span style="color:#8b0000; font-weight:600;">Please contact your signatory</span>
            <?php elseif ($rejected_count > 0): ?>
                <?= $approved ?> of <?= $total ?> done — 
                <span style="color:#8b0000; font-weight:600;"><?= $rejected_count ?> permanently rejected, please contact your signatory</span>
            <?php else: ?>
                <?= $approved ?> of <?= $total ?> requirement(s) done
            <?php endif; ?>
        </small>
    </td>
</tr>
<?php endforeach; ?>
</tbody>
        </table>

        <h3 style="color: darkgreen; margin-top: 40px; margin-bottom: 15px;">Submission History</h3>
        <table>
            <thead>
                <tr>
                    <th>Date Submitted</th>
                    <th>Signatory</th>
                    <th>Document</th>
                    <th>Status</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($applications)): ?>
                    <tr><td colspan="4">No documents submitted yet.</td></tr>
                <?php else: foreach($applications as $app): 
                    $row_class = str_replace(' ', '', $app['status']);
                ?>
                    <tr>
                        <td><?= h(date('M d, Y h:i A', strtotime($app['submitted_at']))) ?></td>
                        <td><?= h($signatory_types_map[$app['signatory']] ?? $app['signatory']) ?></td>
                        <td>
                            <?php if($app['document'] === 'N/A'): ?>
                                <span style="color:#888; font-style: italic;">No file required</span>
                            <?php else: ?>
                                <a href="uploads/<?= h($app['document']) ?>" target="_blank" style="color: blue; text-decoration: underline;">View File</a>
                            <?php endif; ?>
                        </td>
                        <td>
    <?php if ($app['status'] === 'Totally Rejected'): ?>
        <span class="status" style="background:#8b0000; color:white; display:inline-block; padding:5px 10px; border-radius:5px; font-weight:bold;">
            ⛔ Permanently Rejected
        </span>
        <div style="font-size:12px; color:#8b0000; margin-top:4px; font-weight:600;">
            Please contact your signatory
        </div>
    <?php else: ?>
        <span class="status <?= h($row_class) ?>"><?= h($app['status']) ?></span>
    <?php endif; ?>
                        </td>
                    </tr>
                <?php endforeach; endif; ?>
            </tbody>
        </table>
    </div>
</section>

<!-- PRINTABLE CLEARANCE FORM -->
<div id="printableForm">
    <?php echo getClearanceHTML(
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
    ); ?>
</div>

<script>
    const body = document.querySelector("body"),
          sidebar = body.querySelector(".sidebar"),
          toggle = body.querySelector(".toggle");

    toggle.addEventListener("click", () => {
        sidebar.classList.toggle("close");
    });
</script>
</body>
</html>