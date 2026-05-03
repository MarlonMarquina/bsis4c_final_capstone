<?php
session_start();
if (!isset($_SESSION['role']) || ($_SESSION['role'] != "signatory" && $_SESSION['role'] != "admin")) {
    header("Location: login.php");
    exit();
}

include('conn.php');
$username = $_SESSION['username'];

// Fetch signatory info
$sigInfoStmt = $conn->prepare("SELECT full_name, signatory_type, department, section FROM users WHERE username = ? LIMIT 1");
$sigInfoStmt->bind_param("s", $username);
$sigInfoStmt->execute();
$signatoryData = $sigInfoStmt->get_result()->fetch_assoc();
$sigInfoStmt->close();

$signatoryFullName = $signatoryData['full_name'] ?? $username;
$sig_type = strtolower($signatoryData['signatory_type'] ?? '');
$sig_dept = trim($signatoryData['department'] ?? $signatoryData['section'] ?? '');

$is_class_adviser = strpos($sig_type, 'class adviser') !== false;
$is_program_head  = strpos($sig_type, 'program head') !== false;

// Parse program head departments
$program_head_depts = [];
if ($is_program_head && !empty($sig_dept)) {
    foreach (explode(',', $sig_dept) as $d) $program_head_depts[] = trim($d);
}

// Parse class adviser classes
$adviser_classes = [];
if ($is_class_adviser && !empty($signatoryData['section'])) {
    foreach (explode(',', $signatoryData['section']) as $class) {
        $parts = explode('|', trim($class));
        if (count($parts) == 3) {
            [$course, $year, $section] = array_map('trim', $parts);
        } elseif (count($parts) == 2) {
            [$year, $section] = array_map('trim', $parts);
            $course = $sig_dept;
        } else continue;
        $adviser_classes[$course][$year][] = $section;
    }
}

// Handle new post
if (isset($_POST['post'])) {
    $title          = $_POST['title'];
    $content        = $_POST['content'];
    $target_course  = ($_POST['target_course']  ?? 'All') === 'All' ? null : $_POST['target_course'];
    $target_year    = ($_POST['target_year']    ?? 'All') === 'All' ? null : $_POST['target_year'];
    $target_section = ($_POST['target_section'] ?? 'All') === 'All' ? null : $_POST['target_section'];

    $stmt = $conn->prepare("INSERT INTO announcements (title, content, created_by, target_course, target_year, target_section) VALUES (?, ?, ?, ?, ?, ?)");
   $stmt->bind_param("ssssss", $title, $content, $signatoryFullName, $target_course, $target_year, $target_section);
    $stmt->execute();
    $stmt->close();

    // Build targeted student query
    $where  = "role = 'student' AND status = 'active'";
    $types  = '';
    $params = [];
    if ($target_course)  { $where .= " AND course = ?";  $types .= 's'; $params[] = $target_course; }
    if ($target_year)    { $where .= " AND year = ?";    $types .= 's'; $params[] = $target_year; }
    if ($target_section) { $where .= " AND section = ?"; $types .= 's'; $params[] = $target_section; }

    if ($types) {
        $students_stmt = $conn->prepare("SELECT username, email, full_name FROM users WHERE $where");
        $students_stmt->bind_param($types, ...$params);
        $students_stmt->execute();
        $students_result = $students_stmt->get_result();
    } else {
        $students_result = $conn->query("SELECT username, email, full_name FROM users WHERE $where");
    }

    // Load all into array so we can loop twice (notif + email)
    $students_data = [];
    while ($stu = $students_result->fetch_assoc()) $students_data[] = $stu;

    // Insert notifications
    $notif_msg    = "New Announcement: $title";
    $notif_insert = $conn->prepare("INSERT INTO notifications (username, message, is_read, created_at) VALUES (?, ?, 0, NOW())");
    foreach ($students_data as $stu) {
        $notif_insert->bind_param("ss", $stu['username'], $notif_msg);
        $notif_insert->execute();
    }
    $notif_insert->close();

    // Send emails
    require_once 'vendor/autoload.php';
    $mail = new \PHPMailer\PHPMailer\PHPMailer(true);
    $mail->isSMTP();
    $mail->Host          = 'smtp.gmail.com';
    $mail->SMTPAuth      = true;
    $mail->Username      = 'clearancebpc@gmail.com';
    $mail->Password      = 'powe wgem hlsv ybyq';
    $mail->SMTPSecure    = 'tls';
    $mail->Port          = 587;
    $mail->SMTPKeepAlive = true;
    $mail->setFrom('clearancebpc@gmail.com', 'BPC Clearance System');
    $mail->isHTML(true);

    foreach ($students_data as $stu) {
        if (empty($stu['email'])) continue;
        try {
            $mail->clearAddresses();
            $mail->addAddress($stu['email'], $stu['full_name']);
            $mail->Subject = ' New Announcement: ' . $title;
            $mail->Body    = '<div style="font-family:Arial,sans-serif;max-width:600px;margin:0 auto;">
                <div style="background:linear-gradient(135deg,#2d5016,#1a3409);padding:30px;text-align:center;">
                    <h1 style="color:white;margin:0;">📢 ' . htmlspecialchars($title) . '</h1>
                </div>
                <div style="padding:30px;background:white;">
                    <p style="color:#555;line-height:1.7;">' . nl2br(htmlspecialchars($content)) . '</p>
                    <div style="background:#f0fff4;border-left:4px solid #27ae60;border-radius:6px;padding:12px;margin-top:20px;font-size:13px;color:#155724;">
                        Posted by: <strong>' . htmlspecialchars($signatoryFullName) . '</strong>
                    </div>
                    <p style="font-size:12px;color:#aaa;margin-top:15px;">This is an automated message. Please do not reply.</p>
                </div>
            </div>';
            $mail->send();
        } catch (\Exception $e) {
            error_log("Announcement email failed for {$stu['email']}: " . $e->getMessage());
        }
    }
    $mail->smtpClose();
}

// Handle delete
if (isset($_GET['delete'])) {
    $id = intval($_GET['delete']);
    $conn->query("DELETE FROM announcements WHERE id=$id");
    header("Location: announcements.php");
    exit();
}

$result = $conn->query("SELECT * FROM announcements ORDER BY created_at DESC");
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width,initial-scale=1.0">
    <title>Announcements | BPC Clearance</title>
    <link href='https://unpkg.com/boxicons@2.1.4/css/boxicons.min.css' rel='stylesheet'>
    <link rel="stylesheet" href="styles.css">
    <style>
        .home { padding:25px; transition: all 0.5s ease; }
        .container {
            background:white; padding:25px; border-radius:10px;
            box-shadow:0 4px 10px rgba(0,0,0,0.1);
            width:90%; margin:20px auto;
        }
        h2 { color:darkgreen; margin-bottom:15px; }
        form { display:flex; flex-direction:column; gap:10px; }
        input[type=text], textarea {
            width:100%; padding:10px; border:1px solid #ccc; border-radius:6px;
            font-family:'Poppins',sans-serif;
        }
        textarea { resize:none; height:100px; }
        button {
            background:darkgreen; color:white; border:none; padding:10px;
            border-radius:6px; cursor:pointer; transition:0.3s;
        }
        button:hover { background:#0b4e12; }
        .announcement {
            border:1px solid #ddd; border-radius:8px; padding:15px;
            margin-top:15px; background:#f9f9f9;
        }
        .announcement h3 { margin:0; color:#333; }
        .announcement small { color:#666; font-size:13px; }
        .delete-btn { color:red; text-decoration:none; float:right; font-size:14px; }
        .delete-btn:hover { text-decoration:underline; }
    </style>
</head>
<body>
  
<?php include 'sidebar_signa.php'; ?>

<section class="home">
    <div class="container">
        <h2>Create Announcement</h2>
        <form method="POST">
            <input type="text" name="title" placeholder="Announcement Title" required>
            <textarea name="content" placeholder="Write your announcement..." required></textarea>
            
            <div style="background:#f0fff4; border:1px solid #c3e6cb; border-radius:8px; padding:15px; margin-top:10px;">
                <strong style="color:#2d5016; font-size:13px;">🎯 Target Audience (optional — leave All to send to everyone)</strong>
                <div style="display:grid; grid-template-columns:1fr 1fr 1fr; gap:10px; margin-top:10px;">
                    <div>
                        <label style="font-size:12px; color:#555;">Course</label>
                        <select name="target_course" id="ann_course" onchange="loadAnnYears()" style="width:100%; padding:8px; border:1px solid #ccc; border-radius:6px; font-size:13px;">
                            <option value="All">All Courses</option>
                            <?php
                            // Show courses based on signatory type (same logic as requirementssigna.php)
                            $sql_ann_c = "SELECT course_name, duration FROM courses";
                            if ($is_class_adviser && !empty($adviser_classes)) {
                                $allowed = array_keys($adviser_classes);
                                $in = "'" . implode("','", array_map([$conn, 'real_escape_string'], $allowed)) . "'";
                                $sql_ann_c .= " WHERE course_name IN ($in)";
                            } elseif ($is_program_head && !empty($program_head_depts)) {
                                $conds = array_map(fn($d) => "course_name = '" . $conn->real_escape_string($d) . "'", $program_head_depts);
                                $sql_ann_c .= " WHERE " . implode(' OR ', $conds);
                            }
                            $ann_courses = $conn->query($sql_ann_c . " ORDER BY course_name ASC");
                            while ($ac = $ann_courses->fetch_assoc()):
                            ?>
                               <option value="<?= htmlspecialchars($ac['course_name']) ?>" data-duration="<?= intval($ac['duration'] ?? 4) ?>"><?= htmlspecialchars($ac['course_name']) ?></option>
                            <?php endwhile; ?>
                        </select>
                    </div>
                    <div>
                        <label style="font-size:12px; color:#555;">Year Level</label>
                        <select name="target_year" id="ann_year" onchange="loadAnnSections()" style="width:100%; padding:8px; border:1px solid #ccc; border-radius:6px; font-size:13px;">
    <option value="All">All Years</option>
</select>
                    </div>
                    <div>
                        <label style="font-size:12px; color:#555;">Section</label>
                        <select name="target_section" id="ann_section" style="width:100%; padding:8px; border:1px solid #ccc; border-radius:6px; font-size:13px;">
                            <option value="All">All Sections</option>
                        </select>
                    </div>
                </div>
            </div>

            <button type="submit" name="post" style="margin-top:10px;">Post Announcement</button>
        </form>

        <h2 style="margin-top:30px;">Recent Announcements</h2>
        <?php if ($result && $result->num_rows > 0): while ($row = $result->fetch_assoc()): ?>
            <div class="announcement">
                <h3><?= htmlspecialchars($row['title']) ?></h3>
                <small>
    Posted by <?= htmlspecialchars($row['created_by']) ?> • <?= $row['created_at'] ?>
    <?php if ($row['target_course'] || $row['target_year'] || $row['target_section']): ?>
        | 🎯 
        <?= $row['target_course'] ?? 'All Courses' ?> — 
        <?= $row['target_year']   ?? 'All Years' ?> — 
        Section <?= $row['target_section'] ?? 'All' ?>
    <?php endif; ?>
</small>
                <p><?= nl2br(htmlspecialchars($row['content'])) ?></p>
                <?php if ($row['created_by'] == $username): ?>
                    <a href="?delete=<?= $row['id'] ?>" class="delete-btn" onclick="return confirm('Delete this announcement?')">Delete</a>
                <?php endif; ?>
            </div>
        <?php endwhile; else: ?>
            <p>No announcements posted yet.</p>
        <?php endif; ?>
    </div>
</section>

<script>
    document.addEventListener("DOMContentLoaded", () => {
        // Hanapin ang elements
        const body = document.querySelector('body'),
              sidebar = document.querySelector('nav'), // Siguraduhin na 'nav' ang tag ng sidebar mo
              toggle = document.querySelector(".toggle"); 

        // Toggle Logic
        if(toggle && sidebar) {
            toggle.addEventListener("click" , () => {
                sidebar.classList.toggle("close");
            });
        }
    });

    function confirmLogout(){
        if(confirm("Are you sure you want to logout?"))
            window.location.href='logout.php';
    }
    function loadAnnYears() {
    const course = document.getElementById('ann_course');
    const yearSel = document.getElementById('ann_year');
    const secSel = document.getElementById('ann_section');

    yearSel.innerHTML = '<option value="All">All Years</option>';
    secSel.innerHTML = '<option value="All">All Sections</option>';

    if (course.value === 'All') return;

    const duration = parseInt(course.options[course.selectedIndex].dataset.duration || 4);
    const labels = ["1st Year", "2nd Year", "3rd Year", "4th Year", "5th Year"];
    for (let i = 0; i < duration; i++) {
        const opt = document.createElement('option');
        opt.value = labels[i];
        opt.textContent = labels[i];
        yearSel.appendChild(opt);
    }
}

function loadAnnSections() {
    const course = document.getElementById('ann_course').value;
    const year   = document.getElementById('ann_year').value;
    const secSel = document.getElementById('ann_section');
    secSel.innerHTML = '<option value="All">All Sections</option>';
    if (course === 'All' || year === 'All') return;
    fetch(`get_sections.php?course=${encodeURIComponent(course)}&year=${encodeURIComponent(year)}`)
    .then(r => r.json())
    .then(sections => {
        sections.forEach(s => {
            const opt = document.createElement('option');
            opt.value = s; opt.textContent = 'Section ' + s;
            secSel.appendChild(opt);
        });
    });
}
</script>

</body>
</html>