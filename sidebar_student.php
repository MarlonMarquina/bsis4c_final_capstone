<?php
$stmt = $conn->prepare("SELECT full_name, course, year, section FROM users WHERE username = ?");
$stmt->bind_param("s", $_SESSION['username']);
$stmt->execute();
$row = $stmt->get_result()->fetch_assoc();
$full_name = $row['full_name'];
$course    = $row['course'];
$year      = $row['year'];
$section   = $row['section'];
?>
<link href='https://unpkg.com/boxicons@2.1.4/css/boxicons.min.css' rel='stylesheet'>
    <link rel="stylesheet" href="styles.css">
    <nav class="sidebar close">
 <header>
<div class="image-text">
<span class="image"><img src="bpc-logo.png" alt="logo"></span>
<div class="text header-text">
<span class="name"><?= h($full_name) ?></span>
<span class="role"><?= h($course) ?> <?= h($year) ?> - <?= h($section) ?></span>
</div>
</div>
<i class='bx bx-chevron-right toggle'></i>
</header>
<div class="menu-bar">
<div class="menu">
<ul class="menu-links">
<li class="nav-link"><a href="student_dashboard.php"><i class='bx bx-home-alt icon'></i><span class="text nav-text">Dashboard</span></a></li>
<li class="nav-link"><a href="student_profile.php"><i class='bx bx-user icon'></i><span class="text nav-text">Profile</span></a></li>
<li class="nav-link"><a href="student_history.php"><i class='bx bx-history icon'></i><span class="text nav-text">History</span></a></li>
<li class="nav-link"><a href="student_notifications.php"><i class='bx bx-bell icon'></i><span class="text nav-text">Notifications</span></a></li>
</ul>
</div>
<div class="bottom-content">
<li class="nav-link"><a href="logout.php" onclick="return confirm('Are you sure you want to logout?');"><i class='bx bx-log-out icon'></i><span class="text nav-text">Logout</span></a></li>
</div>
</div>
</nav>