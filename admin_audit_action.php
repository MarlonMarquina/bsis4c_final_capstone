<?php
/**
 * FILE: admin_audit_action.php
 * DESCRIPTION: Handles activation and deactivation of users with strict session checking.
 */

// Simulan ang session sa pinakataas ng file (walang spaces bago ito)
session_start();
include 'conn.php';

// --- AUTHORIZATION CHECK ---
// Ginagamit ang strtolower para tanggapin ang 'Admin' o 'admin'
if (!isset($_SESSION['role']) || strtolower($_SESSION['role']) !== "admin") {
    // Debugging hint (optional): 
    // die("Unauthorized. Role is: " . ($_SESSION['role'] ?? 'Not Set'));
    die("Unauthorized access. Admin privileges required.");
}

// --- LOGIC HANDLER ---
if (isset($_GET['id']) && isset($_GET['status'])) {
    // Linisin ang inputs
    $username = trim($_GET['id']);
    $newStatus = trim($_GET['status']);
    
    // 1. Siguraduhin na valid ang status na ipinasa
    $valid_statuses = ['active', 'inactive'];
    if (!in_array($newStatus, $valid_statuses)) {
        header("Location: admin_users.php?msg=" . urlencode("Invalid status value provided."));
        exit();
    }
    
    // 2. I-prepare ang SQL para iwas SQL Injection
    $stmt = $conn->prepare("UPDATE users SET status = ? WHERE username = ?");
    
    if ($stmt) {
        $stmt->bind_param("ss", $newStatus, $username);
        
        if ($stmt->execute()) {
            if ($stmt->affected_rows > 0) {
                // --- GET SIGNATORY ID AND ROLE ---
                $infoStmt = $conn->prepare("SELECT id, role FROM users WHERE username = ?");
                $infoStmt->bind_param("s", $username);
                $infoStmt->execute();
                $infoRow = $infoStmt->get_result()->fetch_assoc();
                $infoStmt->close();

                if ($infoRow && $infoRow['role'] === 'signatory') {
                    $signatoryId = $infoRow['id'];

                    if ($newStatus === 'inactive') {
                        // HIDE: set requirements_configured = 0 for this signatory
                        $hideStmt = $conn->prepare("
                            UPDATE course_requirements 
                            SET requirements_configured = 0 
                            WHERE signatory_id = ?
                        ");
                        $hideStmt->bind_param("i", $signatoryId);
                        $hideStmt->execute();
                        $hideStmt->close();
                    } else {
                        // RESTORE: set requirements_configured = 1 for this signatory
                        $restoreStmt = $conn->prepare("
                            UPDATE course_requirements 
                            SET requirements_configured = 1 
                            WHERE signatory_id = ?
                        ");
                        $restoreStmt->bind_param("i", $signatoryId);
                        $restoreStmt->execute();
                        $restoreStmt->close();
                    }
                }

                $actionLabel = ($newStatus === 'active') ? 'activated' : 'deactivated';
                $msg = "Success: Account [" . htmlspecialchars($username) . "] has been {$actionLabel}.";
            } else {
                $msg = "Notice: Account status was already " . htmlspecialchars($newStatus) . " or user not found.";
            }
            
            header("Location: admin_users.php?msg=" . urlencode($msg));
        } else {
            header("Location: admin_users.php?msg=" . urlencode("Execution Error: " . $stmt->error));
        }
        
        $stmt->close();
    } else {
        // SQL preparation error
        header("Location: admin_users.php?msg=" . urlencode("SQL Error: " . $conn->error));
    }
} else {
    // Missing URL parameters
    header("Location: admin_users.php?msg=" . urlencode("Error: Missing required parameters (ID or Status)."));
}

// Isara ang koneksyon at tapusin ang script
$conn->close();
exit();
?>