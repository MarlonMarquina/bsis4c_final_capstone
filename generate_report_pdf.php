<?php
// FILE: generate_report_pdf.php
include 'conn.php';
session_start();
date_default_timezone_set('Asia/Manila');
if (!isset($_SESSION['role']) || $_SESSION['role'] != "admin") {
    die("Access Denied.");
}

// 1. Kunin ang mga filters mula sa URL (GET)
$sem    = $_GET['rep_sem'] ?? 'all';
$sy     = $_GET['rep_sy']  ?? '';
$course = $_GET['rep_course'] ?? 'all';
$year   = $_GET['rep_year']   ?? 'all';
$status = $_GET['rep_status'] ?? 'all';

// 2. Buuin ang SQL Query
// Sinisigurado natin na tama ang column names dito
$query = "SELECT u.* FROM users u WHERE u.role = 'student'";

if ($sem    != 'all') $query .= " AND u.semester = '" . $conn->real_escape_string($sem) . "'";
if (!empty($sy))      $query .= " AND u.school_year = '" . $conn->real_escape_string($sy) . "'";
if ($course != 'all') $query .= " AND u.course = '" . $conn->real_escape_string($course) . "'";
if ($year   != 'all') $query .= " AND u.year = '" . $conn->real_escape_string($year) . "'";

// Status Logic: 'cleared' means 0 pending signatures
if ($status == 'cleared') {
    $query .= " AND u.admin_approved = 1";
} elseif ($status == 'pending') {
    $query .= " AND (u.admin_approved = 0 AND u.final_clearance_status = 'pending')";
} elseif ($status == 'not_requested') {
    $query .= " AND u.admin_approved = 0 AND (u.final_clearance_status = 'not_requested' OR u.final_clearance_status IS NULL OR u.final_clearance_status = '')";
}

$query .= " ORDER BY u.course ASC, u.full_name ASC";
$result = $conn->query($query);

// I-check kung may error sa query para hindi mag-Fatal Error
if (!$result) {
    die("SQL Error: " . $conn->error);
}
echo "<!-- DEBUG QUERY: " . htmlspecialchars($query) . " -->";
echo "<!-- STATUS VALUE: " . htmlspecialchars($status) . " -->";
echo "<!-- ROWS FOUND: " . $result->num_rows . " -->";
?>


<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Clearance Summary Report</title>
    <style>
        body { font-family: 'Arial', sans-serif; padding: 40px; color: #333; background: #f0f0f0; }
        .report-page { background: white; padding: 40px; width: 210mm; min-height: 297mm; margin: 0 auto; box-shadow: 0 0 10px rgba(0,0,0,0.1); }
        .header { text-align: center; border-bottom: 2px solid #006400; padding-bottom: 10px; margin-bottom: 20px; }
        table { width: 100%; border-collapse: collapse; margin-top: 20px; }
        th, td { border: 1px solid #ddd; padding: 10px; text-align: left; font-size: 12px; }
        th { background: #006400; color: white; }
        .no-print { position: fixed; top: 20px; right: 20px; }
        .btn { padding: 10px 20px; background: #006400; color: white; border: none; border-radius: 5px; cursor: pointer; text-decoration: none; }
        @media print { .no-print { display: none; } body { background: white; padding: 0; } .report-page { box-shadow: none; margin: 0; width: 100%; } }
    </style>
</head>
<body>

<div class="no-print">
    <button class="btn" onclick="window.print()">Print to PDF</button>
    <button class="btn" style="background: #666;" onclick="window.close()">Close</button>
</div>

<div class="report-page">
    <div class="header">
        <h1 style="color: #006400; margin: 0;">Smart Clearance System</h1>
        <h2 style="margin: 5px 0;">Student Clearance Summary</h2>
        <p>Semester: <?= htmlspecialchars($sem) ?> | School Year: <?= htmlspecialchars($sy) ?></p>
        <p><small>Filters: Course: <?= $course ?> | Year: <?= $year ?> | Status: <?= $status ?></small></p>
    </div>

    <table>
        <thead>
            <tr>
                <th>Student ID</th>
                <th>Full Name</th>
                <th>Course</th>
                <th>Year & Section</th>
                <th>Status</th>
            </tr>
        </thead>
        <tbody>
            <?php if ($result->num_rows > 0): ?>
                <?php while($row = $result->fetch_assoc()): ?>
                    <tr>
                        <td><?= htmlspecialchars($row['username']) ?></td>
                        <td><?= htmlspecialchars($row['full_name']) ?></td>
                        <td><?= htmlspecialchars($row['course']) ?></td>
                        <td><?= htmlspecialchars($row['year']) ?> - <?= htmlspecialchars($row['section']) ?></td>
                        <td>
    <?php
    if ($row['admin_approved'] == 1) {
        echo "<b style='color:green;'>✅ FULLY CLEARED</b>";
    } elseif ($row['final_clearance_status'] === 'pending') {
        echo "<span style='color:orange; font-weight:600;'>⏳ Awaiting Admin Approval</span>";
    } else {
        echo "<span style='color:#888;'>Not Yet Requested</span>";
    }
    ?>
</td>
                    </tr>
                <?php endwhile; ?>
            <?php else: ?>
                <tr><td colspan="5" style="text-align:center;">No students found for this filter.</td></tr>
            <?php endif; ?>
        </tbody>
    </table>
</div>

</body>
</html>