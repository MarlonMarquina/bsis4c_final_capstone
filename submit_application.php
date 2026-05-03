<?php
session_start();
$conn = new mysqli("localhost", "root", "", "school_db");
if ($conn->connect_error) die("DB connection failed.");

if ($_SERVER["REQUEST_METHOD"] == "POST") {
  $username = $_SESSION['username'];
  $signatory = $_POST['signatory'];
  $course = $_POST['course'];
  $file = $_FILES['document']['name'];
  $target = "uploads/" . basename($file);

  if (move_uploaded_file($_FILES['document']['tmp_name'], $target)) {
    $stmt = $conn->prepare("INSERT INTO applications (username, signatory, course, document, status) VALUES (?, ?, ?, ?, 'Pending')");
    $stmt->bind_param("ssss", $username, $signatory, $course, $file);
    $stmt->execute();
    header("Location: student_dashboard.php?success=1");
  } else echo "File upload failed.";
}
?>
