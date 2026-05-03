<?php
session_start();
if (!isset($_SESSION['role']) || $_SESSION['role'] != "student") {
    header("Location: login.php");
    exit();
}

include('conn.php');

$username = $_SESSION['username'];
$student_stmt = $conn->prepare("SELECT full_name, course, year, section FROM users WHERE username = ? LIMIT 1");
$student_stmt->bind_param("s", $username);
$student_stmt->execute();
$student = $student_stmt->get_result()->fetch_assoc();
$student_stmt->close();

// --- 1. HANDLE NOTIFICATION READ STATUS ---
if (isset($_GET['read_notif'])) {
    $id = intval($_GET['read_notif']);
    $stmt = $conn->prepare("UPDATE notifications SET is_read = 1 WHERE id = ? AND username = ?"); 
    $stmt->bind_param("is", $id, $username);
    $stmt->execute();
    $stmt->close();
    header("Location: student_notifications.php");
    exit();
}

// --- 2. MARK ALL AS READ ---
if (isset($_GET['mark_all_read'])) {
    $stmt = $conn->prepare("UPDATE notifications SET is_read = 1 WHERE username = ? AND is_read = 0");
    $stmt->bind_param("s", $username);
    $stmt->execute();
    $stmt->close();
    header("Location: student_notifications.php?success=marked_all");
    exit();
}

// --- 3. DELETE ALL READ NOTIFICATIONS ---
if (isset($_GET['delete_read'])) {
    $stmt = $conn->prepare("DELETE FROM notifications WHERE username = ? AND is_read = 1");
    $stmt->bind_param("s", $username);
    $stmt->execute();
    $deleted_count = $stmt->affected_rows;
    $stmt->close();
    header("Location: student_notifications.php?success=deleted&count=$deleted_count");
    exit();
}

// --- 4. FETCH ALL NOTIFICATIONS ---
$notifications_sql = "SELECT id, message, is_read, created_at FROM notifications WHERE username = ? ORDER BY created_at DESC";
$notif_stmt = $conn->prepare($notifications_sql);
$notif_stmt->bind_param("s", $username);
$notif_stmt->execute();
$notifications_result = $notif_stmt->get_result();
$notif_stmt->close();

// --- 5. COUNT UNREAD ---
$unread_notif_sql = "SELECT COUNT(*) AS unread_count FROM notifications WHERE username = ? AND is_read = 0";
$unread_notif_stmt = $conn->prepare($unread_notif_sql);
$unread_notif_stmt->bind_param("s", $username);
$unread_notif_stmt->execute();
$unread_notif_count = $unread_notif_stmt->get_result()->fetch_assoc()['unread_count'];
$unread_notif_stmt->close();

// --- 6. COUNT READ ---
$read_notif_sql = "SELECT COUNT(*) AS read_count FROM notifications WHERE username = ? AND is_read = 1";
$read_notif_stmt = $conn->prepare($read_notif_sql);
$read_notif_stmt->bind_param("s", $username);
$read_notif_stmt->execute();
$read_notif_count = $read_notif_stmt->get_result()->fetch_assoc()['read_count'];
$read_notif_stmt->close();

// Announcements unread count
$unread_ann_count_res = $conn->query("SELECT COUNT(*) AS unread_count FROM announcements WHERE id NOT IN (SELECT announcement_id FROM announcement_reads WHERE username='$username')");
$unread_ann_count = $unread_ann_count_res->fetch_assoc()['unread_count'];

$total_unread_count = $unread_notif_count + $unread_ann_count;
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width,initial-scale=1.0">
    <title>Notifications | BPC Clearance</title>
    <link href='https://unpkg.com/boxicons@2.1.4/css/boxicons.min.css' rel='stylesheet'>
    <link rel="stylesheet" href="styles.css">
    <style>
        :root {
            --primary-color: #006400;
            --unread-bg: #f0f9ff;
            --text-main: #333;
            --text-muted: #666;
        }

        .home { padding: 20px; transition: all 0.5s ease; }
        
        .main-container {
            max-width: 900px;
            margin: 0 auto;
        }

        .section-title {
            display: flex;
            align-items: center;
            gap: 10px;
            color: var(--primary-color);
            font-size: 1.5rem;
            margin-bottom: 20px;
            padding-bottom: 10px;
            border-bottom: 2px solid #eee;
        }

        .notification-card {
            background: white;
            border-radius: 12px;
            padding: 18px;
            margin-bottom: 15px;
            box-shadow: 0 2px 5px rgba(0,0,0,0.05);
            border: 1px solid #eef0f2;
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            transition: 0.3s;
        }

        .notification-card.unread {
            border-left: 5px solid var(--primary-color);
            background-color: var(--unread-bg);
        }

        .notification-card:hover {
            transform: translateY(-3px);
            box-shadow: 0 5px 15px rgba(0,0,0,0.1);
        }

        .notif-content { flex: 1; }
        
        .notif-message {
            font-size: 0.95rem;
            color: var(--text-main);
            line-height: 1.5;
            margin-bottom: 8px
            }

        .notif-time {
            font-size: 0.8rem;
            color: var(--text-muted);
            display: flex;
            align-items: center;
            gap: 5px;
        }

        .btn-mark {
            background: #e2e8f0;
            color: #475569;
            padding: 6px 12px;
            border-radius: 6px;
            font-size: 0.8rem;
            text-decoration: none;
            font-weight: 600;
            transition: 0.2s;
        }

        .btn-mark:hover {
            background: var(--primary-color);
            color: white;
        }

        .announcement-card {
            background: #fff;
            border-radius: 12px;
            padding: 20px;
            margin-bottom: 20px;
            border: 1px solid #eef0f2;
        }

        .announcement-card h3 {
            margin: 0 0 10px 0;
            color: var(--primary-color);
            font-size: 1.2rem;
        }

        .badge {
            background: #ff4757;
            color: white;
            padding: 2px 6px;
            border-radius: 50%;
            font-size: 10px;
            position: absolute;
            top: 10px;
            right: 5px;
        }

        .nav-link { position: relative; }

        /* Action Buttons */
        .action-buttons {
            display: flex;
            gap: 10px;
            margin-bottom: 20px;
            flex-wrap: wrap;
        }

        .btn-action {
            padding: 10px 20px;
            border-radius: 8px;
            font-size: 0.9rem;
            font-weight: 600;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 8px;
            transition: 0.3s;
            border: none;
            cursor: pointer;
        }

        .btn-mark-all {
            background: var(--primary-color);
            color: white;
        }

        .btn-mark-all:hover {
            background: #004d00;
            transform: translateY(-2px);
            box-shadow: 0 4px 8px rgba(0,100,0,0.2);
        }

        .btn-delete-read {
            background: #dc3545;
            color: white;
        }

        .btn-delete-read:hover {
            background: #c82333;
            transform: translateY(-2px);
            box-shadow: 0 4px 8px rgba(220,53,69,0.2);
        }

        .btn-action:disabled {
            opacity: 0.5;
            cursor: not-allowed;
            transform: none !important;
        }

        .alert {
            padding: 12px 18px;
            border-radius: 8px;
            margin-bottom: 20px;
            font-size: 0.9rem;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .alert-success {
            background: #d4edda;
            color: #155724;
            border-left: 4px solid #28a745;
        }

        @media (max-width: 768px) {
            .notification-card { flex-direction: column; gap: 15px; }
            .action-buttons { flex-direction: column; }
            .btn-action { width: 100%; justify-content: center; }
        }
    </style>
</head>
<body>

<nav class="sidebar close">
    <header>
        <div class="image-text">
            <span class="image"><img src="bpc-logo.png" alt="logo"></span>
            <div class="text header-text">
    <span class="name"><?php echo htmlspecialchars($student['full_name'] ?? 'Student'); ?></span>
<span class="role"><?php echo htmlspecialchars($student['course'] ?? ''); ?> <?php echo htmlspecialchars($student['year'] ?? ''); ?> - <?php echo htmlspecialchars($student['section'] ?? ''); ?></span>
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
                <li class="nav-link">
                    <a href="student_notifications.php" style="background: var(--primary-color); color: white;">
                        <i class='bx bx-bell icon'></i>
                        <span class="text nav-text">Notifications
                            <?php if ($total_unread_count > 0): ?>
                                <span class="badge"><?php echo $total_unread_count; ?></span>
                            <?php endif; ?>
                        </span>
                    </a>
                </li>
            </ul>
        </div>
        <div class="bottom-content">
            <li class="nav-link"><a href="#" onclick="confirmLogout()"><i class='bx bx-log-out icon'></i><span class="text nav-text">Logout</span></a></li>
        </div>
    </div>
</nav>

<section class="home">
    <div class="main-container">
        
        <h2 class="section-title"><i class='bx bxs-bell-ring'></i> Clearance Updates</h2>

        <!-- Success Messages -->
        <?php if (isset($_GET['success'])): ?>
            <?php if ($_GET['success'] === 'marked_all'): ?>
                <div class="alert alert-success">
                    <i class='bx bx-check-circle' style="font-size: 1.2rem;"></i>
                    All notifications marked as read!
                </div>
            <?php elseif ($_GET['success'] === 'deleted'): ?>
                <div class="alert alert-success">
                    <i class='bx bx-trash' style="font-size: 1.2rem;"></i>
                    Deleted <?php echo intval($_GET['count'] ?? 0); ?> read notification(s)!
                </div>
            <?php endif; ?>
        <?php endif; ?>

        <!-- Action Buttons -->
        <div class="action-buttons">
            <a href="?mark_all_read" 
               class="btn-action btn-mark-all" 
               <?php echo $unread_notif_count == 0 ? 'style="opacity:0.5; pointer-events:none;"' : ''; ?>
               onclick="return confirm('Mark all <?php echo $unread_notif_count; ?> unread notification(s) as read?')">
                <i class='bx bx-check-double'></i>
                Mark All as Read (<?php echo $unread_notif_count; ?>)
            </a>
            
            <a href="?delete_read" 
               class="btn-action btn-delete-read"
               <?php echo $read_notif_count == 0 ? 'style="opacity:0.5; pointer-events:none;"' : ''; ?>
               onclick="return confirm('Permanently delete <?php echo $read_notif_count; ?> read notification(s)?\n\nThis action cannot be undone!')">
                <i class='bx bx-trash-alt'></i>
                Delete Read (<?php echo $read_notif_count; ?>)
            </a>
        </div>
        
        <?php if ($notifications_result->num_rows > 0): ?>
            <?php while ($row = $notifications_result->fetch_assoc()): ?>
                <div class="notification-card <?php echo $row['is_read'] ? '' : 'unread'; ?>">
                    <div class="notif-content">
                        <div class="notif-message">
                            <?php echo nl2br(htmlspecialchars($row['message'])); ?>
                        </div>
                        <div class="notif-time">
                            <i class='bx bx-time-five'></i> 
                            <?php echo date('M d, Y | h:i A', strtotime($row['created_at'])); ?>
                        </div>
                    </div>
                    <?php if (!$row['is_read']): ?>
                        <a href="?read_notif=<?php echo $row['id']; ?>" class="btn-mark">Mark as Read</a>
                    <?php endif; ?>
                </div>
            <?php endwhile; ?>
        <?php else: ?>
            <div style="text-align:center; padding: 40px; color: #999;">
                <i class='bx bx-notification-off' style="font-size: 3rem;"></i>
                <p>No clearance updates at the moment.</p>
            </div>
        <?php endif; ?>

        <h2 class="section-title" style="margin-top: 50px;"><i class='bx bxs-megaphone'></i> Announcements</h2>
        
        <?php 
        $announcements = $conn->query("SELECT * FROM announcements ORDER BY created_at DESC");
        if ($announcements->num_rows > 0): 
            while ($row = $announcements->fetch_assoc()):
                $ann_id = $row['id'];
                $is_ann_read = $conn->query("SELECT * FROM announcement_reads WHERE announcement_id=$ann_id AND username='$username'")->num_rows > 0;
        ?>
            <div class="announcement-card" style="<?php echo !$is_ann_read ? 'border-top: 4px solid orange;' : ''; ?>">
                <h3><?php echo htmlspecialchars($row['title']); ?></h3>
                <p style="color: #555;"><?php echo nl2br(htmlspecialchars($row['content'])); ?></p>
                <div class="notif-time">
                    <i class='bx bx-user-circle'></i> <?php echo htmlspecialchars($row['created_by']); ?> • <?php echo date('M d, Y', strtotime($row['created_at'])); ?>
                </div>
            </div>
        <?php endwhile; else: ?>
            <p style="text-align:center;color:#999; font-style: italic;">No general announcements posted.</p>
        <?php endif; ?>

    </div>
</section>

<script>
    const body = document.querySelector('body'),
          sidebar = body.querySelector('nav'),
          toggle = body.querySelector(".toggle");

    toggle.addEventListener("click" , () =>{
        sidebar.classList.toggle("close");
    });

    function confirmLogout(){
        if(confirm("Are you sure you want to logout?")){
            window.location.href='logout.php';
        }
    }
</script>
</body>
</html>