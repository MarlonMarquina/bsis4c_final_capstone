<?php
// signatory_action.php - CLEAN REWRITE WITH EMAIL NOTIFICATIONS
session_start();
date_default_timezone_set('Asia/Manila');
if (!isset($_SESSION['role']) || $_SESSION['role'] != "signatory") {
    header("Location: login.php");
    exit();
}

include 'conn.php';
include 'send_email_notification.php'; // ← ADD THIS LINE

$signatory_name = $_SESSION['username'];
$reviewed_at    = date('Y-m-d H:i:s');

// Get signatory full name for email
$sigStmt = $conn->prepare("SELECT full_name FROM users WHERE username = ? LIMIT 1");
$sigStmt->bind_param("s", $signatory_name);
$sigStmt->execute();
$sigResult = $sigStmt->get_result();
$signatoryFullName = $sigResult->num_rows > 0 ? $sigResult->fetch_assoc()['full_name'] : $signatory_name;
$sigStmt->close();

// ── HELPER: Send notification ──────────────────────────────────────────────
function sendNotification($conn, $username, $message, $type) {
    global $reviewed_at;
    $stmt = $conn->prepare(
        "INSERT INTO notifications (username, message, type, created_at) VALUES (?, ?, ?, ?)"
    );
    $stmt->bind_param("ssss", $username, $message, $type, $reviewed_at);
    $stmt->execute();
    $stmt->close();
}

function logSignatoryHistory($conn, $app_id, $student, $signatory, $action, $reason, $document) {
    $stmt = $conn->prepare("INSERT INTO `signatory_history` (`application_id`, `signatory_username`, `student_user`, `action`, `reason`, `remarks`) VALUES (?, ?, ?, ?, ?, ?)");
    $stmt->bind_param("isssss", $app_id, $signatory, $student, $action, $reason, $document);
    $stmt->execute();
    $stmt->close();
}

// ── 1. SINGLE APPROVE ─────────────────────────────────────────────────────
if (isset($_POST['approve'], $_POST['id'])) {
    $app_id = (int)$_POST['id'];

    // Fetch student details for email
    $fetch = $conn->prepare("
        SELECT a.username, a.document, a.course, u.email, u.full_name, rl.requirement_name
        FROM applications a
        LEFT JOIN users u ON a.username = u.username
        LEFT JOIN requirement_library rl ON a.requirement_id = rl.id
        WHERE a.id = ?
    ");
    $fetch->bind_param("i", $app_id);
    $fetch->execute();
    $app = $fetch->get_result()->fetch_assoc();
    $fetch->close();

    $student_username = $app['username'] ?? null;
    $document = $app['document'] ?? 'no_file';

    $stmt = $conn->prepare(
        "UPDATE applications
         SET status = 'Approved', reviewed_at = ?, rejection_reason = NULL, rejection_count = 0
         WHERE id = ? AND signatory = ? AND status IN ('Pending', 'Requires Action')"
    );
    $stmt->bind_param("sis", $reviewed_at, $app_id, $signatory_name);
    $stmt->execute();
    $affected = $stmt->affected_rows;
    $stmt->close();

    if ($affected > 0) {
        logSignatoryHistory($conn, $app_id, $student_username, $signatory_name, 'Approved', 'Requirement met and validated', $document);
        
        if ($student_username) {
            // Send in-app notification
            sendNotification($conn, $student_username, "Your application (ID: {$app_id}) has been Approved.", "success");
            
            // Send email notification
            if (!empty($app['email'])) {
                sendApplicationEmail(
                    $app['email'],
                    $app['full_name'] ?? $student_username,
                    'approved',
                    [
                        'signatory' => $signatoryFullName,
                        'course' => $app['course'],
                        'requirement' => $app['requirement_name'] ?? 'N/A'
                    ]
                );
            }
        }
        
        header("Location: signatory_dashboard.php?success=approved");
    } else {
        header("Location: signatory_dashboard.php?error=process_error");
    }
    exit();
}

// ── 2. REJECT (Rule of 3) ─────────────────────────────────────────────────
if (isset($_POST['reject'], $_POST['id'], $_POST['reason'])) {
    $app_id = (int)$_POST['id'];
    $rejection_reason = trim($_POST['reason']);
    $additional_notes = trim($_POST['additional_notes'] ?? '');

    $full_rejection_reason = $rejection_reason;
    if ($additional_notes !== '') {
        $full_rejection_reason .= " | Notes: " . $additional_notes;
    }

    // Fetch current rejection count, student details, and requirement info
    $fetch = $conn->prepare("
        SELECT a.username, a.rejection_count, a.document, a.course, u.email, u.full_name, rl.requirement_name
        FROM applications a
        LEFT JOIN users u ON a.username = u.username
        LEFT JOIN requirement_library rl ON a.requirement_id = rl.id
        WHERE a.id = ? AND a.signatory = ?
    ");
    $fetch->bind_param("is", $app_id, $signatory_name);
    $fetch->execute();
    $result = $fetch->get_result();

    if ($result->num_rows === 0) {
        $fetch->close();
        header("Location: signatory_dashboard.php?error=not_found");
        exit();
    }

    $app = $result->fetch_assoc();
    $student_username = $app['username'];
    $current_count = (int)$app['rejection_count'];
    $document = $app['document'];
    $fetch->close();

    $new_count = $current_count + 1;

    // Check if requires reupload
    $reupload_stmt = $conn->prepare("SELECT requires_reupload FROM rejection_reasons WHERE reason_text = ? LIMIT 1");
    $reupload_stmt->bind_param("s", $rejection_reason);
    $reupload_stmt->execute();
    $reupload_result = $reupload_stmt->get_result()->fetch_assoc();
    $requires_reupload = $reupload_result['requires_reupload'] ?? 0;
    $reupload_stmt->close();

    // Rule of 3: on the 4th rejection, totally reject
    if ($new_count >= 4) {
        $final_status = 'Totally Rejected';
        $redirect = 'totally_rejected&count=' . $new_count;
        $email_status = 'totally_rejected';
    } else {
        $final_status = 'Requires Action';
        $redirect = 'rejected';
        $email_status = 'rejected';
    }

    $stmt = $conn->prepare(
        "UPDATE applications
         SET status = ?, reviewed_at = ?, rejection_reason = ?, rejection_count = ?
         WHERE id = ? AND signatory = ?"
    );
    $stmt->bind_param("sssiis", $final_status, $reviewed_at, $full_rejection_reason, $new_count, $app_id, $signatory_name);
    $stmt->execute();
    $stmt->close();

    // Log this rejection permanently
    $logIns = $conn->prepare(
        "INSERT INTO application_rejection_log
         (application_id, signatory, rejection_reason, additional_notes, rejection_number)
         VALUES (?, ?, ?, ?, ?)"
    );
    $logIns->bind_param("isssi", $app_id, $signatory_name, $rejection_reason, $additional_notes, $new_count);
    $logIns->execute();
    $logIns->close();

    logSignatoryHistory($conn, $app_id, $student_username, $signatory_name, $final_status, $full_rejection_reason, $document);

    if ($student_username) {
        // Send in-app notification
        if ($final_status === 'Requires Action') {
            $msg = "Application (ID: {$app_id}) returned for revision (Attempt {$new_count}/3). Reason: {$full_rejection_reason}";
            $type = "warning";
        } else {
            $msg = "Application (ID: {$app_id}) has been TOTALLY REJECTED after {$new_count} attempts.";
            $type = "danger";
        }
        sendNotification($conn, $student_username, $msg, $type);
        
        // Send email notification
        if (!empty($app['email'])) {
            sendApplicationEmail(
                $app['email'],
                $app['full_name'] ?? $student_username,
                $email_status,
                [
                    'signatory' => $signatoryFullName,
                    'course' => $app['course'],
                    'requirement' => $app['requirement_name'] ?? 'N/A',
                    'reason' => $rejection_reason,
                    'additional_notes' => $additional_notes,
                    'requires_reupload' => $requires_reupload,
                    'rejection_count' => $new_count
                ]
            );
        }
    }

    header("Location: signatory_dashboard.php?success=" . $redirect);
    exit();
}
// ── 3a. BULK APPROVE SELECTED (checkbox-based) ────────────────────────────
if (isset($_POST['bulk_approve']) && !empty($_POST['bulk_ids'])) {
    $ids = array_map('intval', $_POST['bulk_ids']);
    $count_approved = 0;

    foreach ($ids as $app_id) {
        // Fetch app details
        $fetch = $conn->prepare("
            SELECT a.username, a.document, a.course, u.email, u.full_name, rl.requirement_name
            FROM applications a
            LEFT JOIN users u ON a.username = u.username
            LEFT JOIN requirement_library rl ON a.requirement_id = rl.id
            WHERE a.id = ? AND a.signatory = ? AND a.status = 'Pending'
        ");
        $fetch->bind_param("is", $app_id, $signatory_name);
        $fetch->execute();
        $app = $fetch->get_result()->fetch_assoc();
        $fetch->close();

        if (!$app) continue; // skip if not found or not this signatory's

        $up = $conn->prepare(
            "UPDATE applications SET status = 'Approved', reviewed_at = ?, rejection_count = 0
             WHERE id = ? AND signatory = ? AND status = 'Pending'"
        );
        $up->bind_param("sis", $reviewed_at, $app_id, $signatory_name);
        $up->execute();
        $affected = $up->affected_rows;
        $up->close();

        if ($affected > 0) {
            $count_approved++;
            logSignatoryHistory($conn, $app_id, $app['username'], $signatory_name, 'Approved (Bulk)', 'Requirement met', $app['document']);

            // In-app notification
            sendNotification($conn, $app['username'], "Your application (ID: {$app_id}) was approved via Bulk Action.", "success");

            // Email notification
            if (!empty($app['email'])) {
                sendApplicationEmail(
                    $app['email'],
                    $app['full_name'] ?? $app['username'],
                    'approved',
                    [
                        'signatory'   => $signatoryFullName,
                        'course'      => $app['course'],
                        'requirement' => $app['requirement_name'] ?? 'N/A'
                    ]
                );
            }
        }
    }

    header("Location: signatory_dashboard.php?success=bulk_approved&count={$count_approved}");
    exit();
}

// ── 3. BULK APPROVE ───────────────────────────────────────────────────────
if (isset($_POST['accept_all'])) {
    $fetch = $conn->prepare("
        SELECT a.id, a.username, a.document, a.course, u.email, u.full_name, rl.requirement_name
        FROM applications a
        LEFT JOIN users u ON a.username = u.username
        LEFT JOIN requirement_library rl ON a.requirement_id = rl.id
        WHERE a.signatory = ? AND a.status = 'Pending'
    ");
    $fetch->bind_param("s", $signatory_name);
    $fetch->execute();
    $pending_apps = $fetch->get_result()->fetch_all(MYSQLI_ASSOC);
    $fetch->close();

    $count_approved = 0;

    foreach ($pending_apps as $app) {
        $up = $conn->prepare(
            "UPDATE applications SET status = 'Approved', reviewed_at = ?, rejection_count = 0 WHERE id = ?"
        );
        $up->bind_param("si", $reviewed_at, $app['id']);
        if ($up->execute()) {
            $count_approved++;
            logSignatoryHistory($conn, $app['id'], $app['username'], $signatory_name, 'Approved (Bulk)', 'Requirement met', $app['document']);
            
            // Send in-app notification
            sendNotification($conn, $app['username'], "Your application (ID: {$app['id']}) was approved via Bulk Action.", "success");
            
            // Send email notification
            if (!empty($app['email'])) {
                sendApplicationEmail(
                    $app['email'],
                    $app['full_name'] ?? $app['username'],
                    'approved',
                    [
                        'signatory' => $signatoryFullName,
                        'course' => $app['course'],
                        'requirement' => $app['requirement_name'] ?? 'N/A'
                    ]
                );
            }
        }
        $up->close();
    }

    header("Location: signatory_dashboard.php?success=bulk_approved&count={$count_approved}");
    exit();
}

// Fallback
header("Location: signatory_dashboard.php");
exit();