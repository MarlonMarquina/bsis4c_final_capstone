<?php
session_start();
include('conn.php');

if (!isset($_SESSION['role']) || $_SESSION['role'] != "admin") {
    die(json_encode([]));
}

header('Content-Type: application/json');

$query = "SELECT id, full_name, username, email, course, year, section 
          FROM users 
          WHERE role='student' 
          ORDER BY course ASC, year ASC, section ASC, full_name ASC";

$result = $conn->query($query);
$students = [];

if ($result && $result->num_rows > 0) {
    while ($row = $result->fetch_assoc()) {
        $students[] = $row;
    }
}

echo json_encode($students);
exit;