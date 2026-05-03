<?php
// student_resubmit_action.php
session_start();
date_default_timezone_set('Asia/Manila');
include 'conn.php'; 

if (!isset($_SESSION['role']) || $_SESSION['role'] != "student") {
    header("Location: login.php");
    exit();
}

$username = $_SESSION['username'];
$upload_dir = __DIR__ . '/uploads/';

if (isset($_POST['app_id']) && isset($_FILES['document'])) {
    $application_id = (int)$_POST['app_id'];
    $current_document = $_POST['current_document'];

    // 1. Check if the application belongs to the student and status is 'Requires Action'
    $check_sql = "SELECT document FROM applications WHERE id = ? AND username = ? AND status = 'Requires Action'";
    $check_stmt = $conn->prepare($check_sql);
    $check_stmt->bind_param("is", $application_id, $username);
    $check_stmt->execute();
    $current_app = $check_stmt->get_result()->fetch_assoc();
    $check_stmt->close();

    if (!$current_app) {
        echo "<script>alert('Invalid resubmit request or application status not correct.'); window.location.href='student_dashboard.php';</script>";
        exit;
    }
    
    // --- 2. File Handling for Resubmission ---
    $file = $_FILES['document'];
    $file_name = $file['name'];
    $file_tmp = $file['tmp_name'];
    $file_ext = strtolower(pathinfo($file_name, PATHINFO_EXTENSION));
    $maxFileSize = 25 * 1024 * 1024; // 25MB

    if ($file['size'] > $maxFileSize) {
        echo "<script>alert('File too large (max 25MB).'); window.location.href='student_dashboard.php';</script>";
        exit;
    }

    // Create a new unique file name (e.g., username_resubmitted_timestamp.ext)
    $base = pathinfo($file_name, PATHINFO_FILENAME);
    $base = preg_replace('/[^A-Za-z0-9_\-]/','_',$base);
    $new_file_name = $base . '_RESUBMITTED_' . time() . '.' . $file_ext;
    $upload_path = $upload_dir . $new_file_name;

    if (move_uploaded_file($file_tmp, $upload_path)) {
        // Delete old file if it exists and is not 'N/A'
        if ($current_document !== 'N/A' && file_exists($upload_dir . $current_document)) {
            @unlink($upload_dir . $current_document); // Delete old document
        }

        // --- 3. Database Update ---
        $new_status = 'Pending'; // Set back to pending for review
        $current_time = date('Y-m-d H:i:s');

        // Note: Resetting reviewed_at and rejection_reason for a fresh review cycle
        $update_sql = "UPDATE applications 
                       SET status = ?, document = ?, submitted_at = ?, reviewed_at = NULL, rejection_reason = NULL 
                       WHERE id = ? AND username = ?";
        $update_stmt = $conn->prepare($update_sql);
        $update_stmt->bind_param("sssis", $new_status, $new_file_name, $current_time, $application_id, $username);

        if ($update_stmt->execute()) {
            echo "<script>alert('Application successfully resubmitted. Waiting for review.'); window.location.href='student_dashboard.php?success=resubmitted';</script>";
            exit;
        } else {
            echo "<script>alert('Database update failed.'); window.location.href='student_dashboard.php?error=db_update_failed';</script>";
            exit;
        }
    } else {
        echo "<script>alert('File upload failed.'); window.location.href='student_dashboard.php?error=file_upload_failed';</script>";
        exit;
    }
}

header("Location: student_dashboard.php");
exit();
?>