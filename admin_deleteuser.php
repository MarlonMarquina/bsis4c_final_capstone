<?php
session_start();
include 'conn.php';

// Only allow admins
if (!isset($_SESSION['role']) || $_SESSION['role'] != "admin") {
    header("Location: login.php");
    exit();
}

if (isset($_GET['username'])) {
    $username = $_GET['username'];

    // Prevent deleting self (optional)
    if ($username === $_SESSION['username']) {
        header("Location: admin_users.php?msg=❌ You cannot delete your own account!");
        exit();
    }

    $stmt = $conn->prepare("DELETE FROM users WHERE username = ?");
    $stmt->bind_param("s", $username);

    if ($stmt->execute()) {
        header("Location: admin_users.php?msg=✅ User deleted successfully!");
    } else {
        header("Location: admin_users.php?msg=❌ Error deleting user!");
    }

    $stmt->close();
}
$conn->close();
?>
