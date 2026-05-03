<?php
// signatory_action.php - Hina-handle ang Approve, Reject, at Bulk actions

// Session & Guard: Siguraduhin na naka-login at tama ang role
session_start();
if (!isset($_SESSION['role']) || $_SESSION['role'] != "signatory") {
    // Kung walang session, pilitin mag-login.
    header("Location: login.php");
    exit();
}

// Gamitin ang existing DB connection file na nagde-define ng $conn
include 'conn.php'; 

$signatory_name = $_SESSION['username'];
$reviewed_at = date('Y-m-d H:i:s');

// --- FUNCTION FOR NOTIFICATION ---
function sendNotification($conn, $username, $message, $type) {
    global $reviewed_at;
    // Tiyakin na mayroon kayong 'notifications' table sa database
    $insert_notif_sql = "INSERT INTO notifications (username, message, type, created_at) VALUES (?, ?, ?, ?)";
    $insert_notif_stmt = $conn->prepare($insert_notif_sql);
    // Tiyakin na ang 'created_at' column ay tumatanggap ng datetime format
    $insert_notif_stmt->bind_param("ssss", $username, $message, $type, $reviewed_at);
    $insert_notif_stmt->execute();
    $insert_notif_stmt->close();
}

// --- 1. SINGLE APPROVE ACTION ---
if (isset($_POST['approve']) && isset($_POST['id'])) {
    $application_id = (int)$_POST['id'];

    // Update application status to 'Approved' at i-reset ang count
    // Ginamit ang 'rejection_reason = NULL' para linisin ang column
    $sql = "UPDATE applications 
            SET status = 'Approved', reviewed_at = ?, rejection_reason = NULL, rejection_count = 0 
            WHERE id = ? AND signatory = ? AND status IN ('Pending', 'Requires Action')";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("sis", $reviewed_at, $application_id, $signatory_name);

    if ($stmt->execute() && $stmt->affected_rows > 0) {
        // Fetch student username for notification
        $user_sql = "SELECT username FROM applications WHERE id = ?";
        $user_stmt = $conn->prepare($user_sql);
        $user_stmt->bind_param("i", $application_id);
        $user_stmt->execute();
        $result = $user_stmt->get_result();
        $student_username = $result->fetch_assoc()['username'] ?? null;
        $user_stmt->close();

        // Send Success Notification to student
        if ($student_username) {
            $notification_message = "Your application (ID: {$application_id}) has been **Approved** by Signatory: {$signatory_name}. You are now cleared for this requirement.";
            sendNotification($conn, $student_username, $notification_message, "success");
        }
        
        $stmt->close();
        // SUCCESS: I-REDIRECT SA DASHBOARD WITH SUCCESS FLAG
        header("Location: signatory_dashboard.php?success=approved"); 
        exit();
    } else {
        $stmt->close();
        // Failed or already processed
        header("Location: signatory_dashboard.php?error=not_found_or_already_processed");
        exit();
    }
}

// --------------------------------------------------------------------------------------
// --- 2. REJECT ACTION (FINAL & CLEANED: Base sa Rejection Count) ---
// --------------------------------------------------------------------------------------
if (isset($_POST['reject']) && isset($_POST['id']) && isset($_POST['reason'])) {
    $application_id = (int)$_POST['id'];
    $rejection_reason = trim($_POST['reason']);
    // Tandaan: Ang require_resubmit ay hindi na ginagamit sa logic, pero optional pa rin sa form.
    // Ang logic ay nasa $new_count >= 2 na.
    
    if (empty($rejection_reason)) {
        header("Location: signatory_dashboard.php?error=reason_required");
        exit();
    }

    // 1. Fetch current rejection_count and username
    $fetch_sql = "SELECT username, rejection_count FROM applications WHERE id = ? AND signatory = ? AND status IN ('Pending', 'Requires Action')";
    $fetch_stmt = $conn->prepare($fetch_sql);
    $fetch_stmt->bind_param("is", $application_id, $signatory_name);
    $fetch_stmt->execute();
    $result = $fetch_stmt->get_result();
    
    if ($result->num_rows === 0) {
        // Application not found or already processed
        header("Location: signatory_dashboard.php?error=already_processed");
        exit();
    }
    
    $app_data = $result->fetch_assoc();
    $student_username = $app_data['username'];
    $current_count = (int)$app_data['rejection_count'];
    $fetch_stmt->close();
    
    $new_count = $current_count + 1;
    
    // 2. Determine Final Status (Ang Core Logic na Hiniling Mo)
    if ($new_count >= 2) {
        // Pangalawa o higit pa na reject: Permanent Reject
        $final_status = 'Rejected'; 
    } else {
        // Unang reject: Requires Action
        $final_status = 'Requires Action';
    }
    
    // 3. Update the database including rejection_count and REMARKS
    $update_sql = "UPDATE applications 
                   SET status = ?, reviewed_at = ?, remarks = ?, rejection_count = ?
                   WHERE id = ? AND signatory = ? AND status IN ('Pending', 'Requires Action')";
    $update_stmt = $conn->prepare($update_sql);
    
    // Type definition: sssiis (status, reviewed_at, remarks, rejection_count, id, signatory)
    $update_stmt->bind_param("sssiis", 
        $final_status, 
        $reviewed_at, 
        $rejection_reason, // Ito ang value na ipapasok sa 'remarks'
        $new_count, 
        $application_id, 
        $signatory_name
    );

    if ($update_stmt->execute() && $update_stmt->affected_rows > 0) {
        // Send Notification based on the final status
        if ($student_username) {
            $action_message = ($final_status == 'Requires Action') ? 
                "Your application (ID: {$application_id}) was **Returned** by Signatory: {$signatory_name}. Please **Resubmit** a revised document. Reason: {$rejection_reason}" : 
                "Your application (ID: {$application_id}) was **Rejected** (Final) and is now closed. Reason: {$rejection_reason}";
            
            sendNotification($conn, $student_username, $action_message, "danger");
        }
        
        $update_stmt->close();
        // SUCCESS: I-REDIRECT SA DASHBOARD WITH SUCCESS FLAG
        header("Location: signatory_dashboard.php?success=rejected");
        exit();
    } else {
        $update_stmt->close();
        header("Location: signatory_dashboard.php?error=db_error_reject");
        exit();
    }
}

// --- 3. BULK APPROVE ACTION ---
if (isset($_POST['accept_all'])) {
    // 1. Get all pending IDs for this signatory
    $ids_sql = "SELECT id, username FROM applications WHERE signatory = ? AND status = 'Pending'";
    $ids_stmt = $conn->prepare($ids_sql);
    $ids_stmt->bind_param("s", $signatory_name);
    $ids_stmt->execute();
    $pending_applications = $ids_stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $ids_stmt->close();

    if (!empty($pending_applications)) {
        $count_approved = 0;
        
        // Prepare bulk update
        $update_sql = "UPDATE applications 
                        SET status = 'Approved', reviewed_at = ?, rejection_reason = NULL, rejection_count = 0
                        WHERE id = ? AND signatory = ? AND status = 'Pending'";
        $update_stmt = $conn->prepare($update_sql);

        foreach ($pending_applications as $app) {
            $application_id = $app['id'];
            $student_username = $app['username'];

            $update_stmt->bind_param("sis", $reviewed_at, $application_id, $signatory_name);
            if ($update_stmt->execute() && $update_stmt->affected_rows > 0) {
                $count_approved++;
                // Send Notification for each approved application
                $notification_message = "Your application (ID: {$application_id}) has been **Approved** (Bulk Action) by Signatory: {$signatory_name}.";
                sendNotification($conn, $student_username, $notification_message, "success");
            }
        }
        $update_stmt->close();
        
        // SUCCESS: I-REDIRECT SA DASHBOARD WITH COUNT
        header("Location: signatory_dashboard.php?success=bulk_approved&count={$count_approved}");
        exit();
    } else {
        header("Location: signatory_dashboard.php?error=no_pending_found");
        exit();
    }
}

// Default redirect kung walang action
header("Location: signatory_dashboard.php");
exit();
?>