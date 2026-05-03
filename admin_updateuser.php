<?php
session_start();
include 'conn.php';

if (!isset($_SESSION['role']) || $_SESSION['role'] != "admin") {
    header("Location: login.php");
    exit();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_user'])) {
    $original_username = $_POST['original_username'];
    $username = trim($_POST['username']);
    $full_name = trim($_POST['full_name']);
    $year = trim($_POST['year']);
    $section = trim($_POST['section']);
    $role = $_POST['role'];
    $password = $_POST['password'];

    if (!empty($password)) {
        $hashed = password_hash($password, PASSWORD_DEFAULT);
        $sql = "UPDATE users SET username=?, full_name=?, year=?, section=?, password=?, role=? WHERE username=?";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("sssssss", $username, $full_name, $year, $section, $hashed, $role, $original_username);
    } else {
        $sql = "UPDATE users SET username=?, full_name=?, year=?, section=?, role=? WHERE username=?";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("ssssss", $username, $full_name, $year, $section, $role, $original_username);
    }

    if ($stmt->execute()) {
        header("Location: admin_users.php?msg=✅ User updated successfully!");
    } else {
        header("Location: admin_users.php?msg=❌ Failed to update user!");
    }

    $stmt->close();
    $conn->close();
}
?>
