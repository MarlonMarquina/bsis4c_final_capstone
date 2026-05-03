<?php
session_start();
if (!isset($_SESSION['role']) || $_SESSION['role'] != "teacher") {
    header("Location: login.php");
    exit();
}
?>
<!DOCTYPE html>
<html>
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width,initial-scale=1.0">

        <!----===== CSS ==== ---->
        <link rel="stylesheet" href="styles.css">
        <!----===== BOXICONS CSS ==== ---->
        <link href='https://unpkg.com/boxicons@2.1.4/css/boxicons.min.css' rel='stylesheet'>
        
        <title>Home | Dashboard</title>
    </head>
    <style>
        
    </style>
    <body>
        <nav class="sidebar close">
            <header>
                <div class="image-text">
                    <span class="image">
                        <img src="bpc-logo.png" alt="logo">
                    </span>

                    <div class="text header-text">
                        <span class="name">Marlony example</span>
                        <span class="role"><?php echo $_SESSION['username']; ?></span> 
                    </div>
                </div>

                <i class='bx bx-chevron-right toggle'></i>
            </header>
            <div class="menu-bar">
                <div class="menu">
                    <ul class="menu-links">
                        <li class="nav-link">
                            <a href="#">
                                <i class='bx bx-home-alt icon' ></i>
                                <span class="text nav-text">Dashboard</span>
                            </a>
                        </li>
                         <li class="nav-link">
                            <a href="#">
                                <i class='bx bx-user-circle icon'></i>
                                <span class="text nav-text">Profile</span>
                            </a>
                        </li>
                         <li class="nav-link">
                            <a href="#">
                                <i class='bx bx-history icon' ></i>
                                <span class="text nav-text">Hstory</span>
                            </a>
                        </li>
                         <li class="nav-link">
                            <a href="#">
                                <i class='bx bx-notification icon' ></i>
                                <span class="text nav-text">Notification</span>
                            </a>
                        </li>
                    </ul>
                </div>
                
                <div class="bottom-content">
                    <li class="nav-link">
                            <a href="#">
                                <i class='bx bx-log-out icon' ></i>
                                <span class="text nav-text">Logout</span>
                            </a>
                        </li>
                </div>
            </div>
        </nav>

        <section class="home">
            <div class="text">Dashboard</div>
        </section>
        <script src="script.js"></script>
    </body>
</html>