<?php
session_start();
if (!isset($_SESSION['role']) || $_SESSION['role'] != "admin") {
    header("Location: login.php");
    exit();
}

include ('conn.php');

if (isset($_GET['username'])) {
    $username = $_GET['username'];
    $stmt = $conn->prepare("DELETE FROM users WHERE username = ?");
    $stmt->bind_param("s", $username);
    if ($stmt->execute()) {
        header("Location: admin_users.php?msg=User+deleted+successfully");
    } else {
        header("Location: admin_users.php?msg=Failed+to+delete+user");
    }
    $stmt->close();
}
?>
