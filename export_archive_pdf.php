<?php
include 'conn.php';
session_start();
date_default_timezone_set('Asia/Manila');
if (!isset($_SESSION['role']) || $_SESSION['role'] != "admin") {
    die("Access Denied.");
}

$sem = $_GET['sem'] ?? '';
$sy  = $_GET['sy']  ?? '';

if (empty($sem) || empty($sy)) die("Missing parameters.");

$sem_safe = $conn->real_escape_string($sem);
$sy_safe  = $conn->real_escape_string($sy);

// Fetch all data
$students = $conn->query("
    SELECT ua.username, ua.full_name, ua.course, ua.year, ua.section,
           ass.admin_approved, ass.final_clearance_status
    FROM archived_user_accounts ua
    LEFT JOIN archived_student_status ass ON ua.username = ass.username
        AND ass.semester='$sem_safe' AND ass.school_year='$sy_safe'
    WHERE ua.semester='$sem_safe' AND ua.school_year='$sy_safe' AND ua.role='student'
    ORDER BY ua.course ASC, ua.full_name ASC
");

$advisers = $conn->query("
    SELECT username, full_name, email, department, section
    FROM archived_user_accounts
    WHERE semester='$sem_safe' AND school_year='$sy_safe'
    AND role='signatory' AND signatory_type='Class Adviser'
    ORDER BY department ASC, full_name ASC
");

$applications = $conn->query("
    SELECT aa.username, aa.signatory, aa.course, rl.requirement_name,
           aa.status, aa.rejection_count, aa.submitted_at
    FROM archived_applications aa
    LEFT JOIN requirement_library rl ON aa.requirement_id = rl.id
    WHERE aa.semester='$sem_safe' AND aa.school_year='$sy_safe'
    ORDER BY aa.submitted_at DESC
");

// Summary counts
$total_students  = $students->num_rows;
$cleared_count   = 0;
$students_data   = [];
while ($s = $students->fetch_assoc()) {
    if ($s['admin_approved'] == 1) $cleared_count++;
    $students_data[] = $s;
}

$total_apps     = $applications->num_rows;
$approved_apps  = 0;
$apps_data      = [];
while ($a = $applications->fetch_assoc()) {
    if ($a['status'] === 'Approved') $approved_apps++;
    $apps_data[] = $a;
}

$total_advisers = $advisers->num_rows;
$advisers_data  = [];
while ($adv = $advisers->fetch_assoc()) {
    $advisers_data[] = $adv;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Archive Report — <?= htmlspecialchars($sem) ?> <?= htmlspecialchars($sy) ?></title>
    <style>
        * { box-sizing: border-box; margin: 0; padding: 0; }
        body { font-family: Arial, sans-serif; padding: 30px; color: #333; background: #f0f0f0; }
        .report-page { background: white; padding: 40px; width: 210mm; min-height: 297mm; margin: 0 auto 30px; box-shadow: 0 0 10px rgba(0,0,0,0.1); }

        /* Header */
        .report-header { text-align: center; border-bottom: 3px solid #1a3409; padding-bottom: 15px; margin-bottom: 25px; }
        .report-header h1 { color: #1a3409; font-size: 22px; margin-bottom: 4px; }
        .report-header h2 { color: #2d5016; font-size: 16px; margin-bottom: 8px; }
        .report-header p { font-size: 12px; color: #666; }

        /* Summary boxes */
        .summary-row { display: flex; gap: 12px; margin-bottom: 25px; }
        .summary-box { flex: 1; border: 1px solid #c8e6c9; border-radius: 8px; padding: 12px; text-align: center; background: #f1f8f1; }
        .summary-box .num { font-size: 28px; font-weight: bold; color: #1a3409; display: block; }
        .summary-box .lbl { font-size: 10px; color: #666; text-transform: uppercase; letter-spacing: 0.5px; }

        /* Section titles */
        .section-title { background: #1a3409; color: white; padding: 8px 14px; font-size: 13px; font-weight: bold; border-radius: 6px; margin: 25px 0 12px; letter-spacing: 0.5px; }

        /* Tables */
        table { width: 100%; border-collapse: collapse; font-size: 11px; margin-bottom: 10px; }
        th { background: #2d5016; color: white; padding: 8px 10px; text-align: left; font-size: 10px; text-transform: uppercase; letter-spacing: 0.3px; }
        td { padding: 7px 10px; border-bottom: 1px solid #eee; }
        tr:nth-child(even) td { background: #f9fdf9; }
        tr:last-child td { border-bottom: none; }

        /* Status badges */
        .badge { display: inline-block; padding: 2px 8px; border-radius: 10px; font-size: 10px; font-weight: bold; }
        .badge-green { background: #d4edda; color: #155724; }
        .badge-orange { background: #fff3cd; color: #856404; }
        .badge-red { background: #f8d7da; color: #721c24; }
        .badge-purple { background: #e8d5ff; color: #5a0080; }
        .badge-gray { background: #eee; color: #666; }

        /* Footer */
        .report-footer { text-align: center; font-size: 10px; color: #aaa; margin-top: 30px; padding-top: 15px; border-top: 1px solid #eee; }

        /* Print controls */
        .no-print { position: fixed; top: 20px; right: 20px; display: flex; gap: 10px; z-index: 999; }
        .btn { padding: 10px 20px; color: white; border: none; border-radius: 6px; cursor: pointer; font-weight: bold; font-size: 13px; }
        .btn-green { background: #2d5016; }
        .btn-gray { background: #666; }

        @media print {
            .no-print { display: none; }
            body { background: white; padding: 0; }
            .report-page { box-shadow: none; margin: 0; width: 100%; padding: 25px; }
        }
    </style>
</head>
<body>

<div class="no-print">
    <button class="btn btn-green" onclick="window.print()">🖨️ Print / Save as PDF</button>
    <button class="btn btn-gray" onclick="window.close()">✕ Close</button>
</div>

<div class="report-page">

    <!-- Header -->
    <div class="report-header">
        <h1>BPC Smart Clearance System</h1>
        <h2>Archived Term Report</h2>
        <p><?= htmlspecialchars($sem) ?> &nbsp;|&nbsp; School Year <?= htmlspecialchars($sy) ?></p>
        <p style="margin-top:4px;">Generated: <?= date('F d, Y h:i A') ?></p>
    </div>

    <!-- Summary -->
    <div class="summary-row">
        <div class="summary-box">
            <span class="num"><?= $total_students ?></span>
            <span class="lbl">Total Students</span>
        </div>
        <div class="summary-box">
            <span class="num"><?= $cleared_count ?></span>
            <span class="lbl">Fully Cleared</span>
        </div>
        <div class="summary-box">
            <span class="num"><?= $total_students - $cleared_count ?></span>
            <span class="lbl">Not Cleared</span>
        </div>
        <div class="summary-box">
            <span class="num"><?= $total_apps ?></span>
            <span class="lbl">Applications</span>
        </div>
        <div class="summary-box">
            <span class="num"><?= $total_advisers ?></span>
            <span class="lbl">Class Advisers</span>
        </div>
    </div>

    <!-- Students -->
    <div class="section-title">👨‍🎓 Student Clearance Status</div>
    <?php if (!empty($students_data)): ?>
    <table>
        <thead>
            <tr>
                <th>#</th>
                <th>Student ID</th>
                <th>Full Name</th>
                <th>Course</th>
                <th>Year & Section</th>
                <th>Clearance Status</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($students_data as $i => $s): ?>
            <tr>
                <td><?= $i + 1 ?></td>
                <td><?= htmlspecialchars($s['username']) ?></td>
                <td><strong><?= htmlspecialchars($s['full_name'] ?? '—') ?></strong></td>
                <td><?= htmlspecialchars($s['course'] ?? '—') ?></td>
                <td><?= htmlspecialchars(($s['year'] ?? '') . ' - ' . ($s['section'] ?? '')) ?></td>
                <td>
                    <?php if ($s['admin_approved'] == 1): ?>
                        <span class="badge badge-green">✅ CLEARED</span>
                    <?php elseif ($s['final_clearance_status'] === 'pending'): ?>
                        <span class="badge badge-orange">⏳ PENDING</span>
                    <?php else: ?>
                        <span class="badge badge-gray">NOT REQUESTED</span>
                    <?php endif; ?>
                </td>
            </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
    <?php else: ?>
    <p style="color:#aaa; font-size:12px; text-align:center; padding:20px;">No student records for this term.</p>
    <?php endif; ?>

    <!-- Class Advisers -->
    <div class="section-title">👨‍🏫 Class Advisers</div>
    <?php if (!empty($advisers_data)): ?>
    <table>
        <thead>
            <tr>
                <th>#</th>
                <th>Username</th>
                <th>Full Name</th>
                <th>Department</th>
                <th>Handled Sections</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($advisers_data as $i => $adv): ?>
            <tr>
                <td><?= $i + 1 ?></td>
                <td><?= htmlspecialchars($adv['username']) ?></td>
                <td><strong><?= htmlspecialchars($adv['full_name'] ?? '—') ?></strong></td>
                <td><?= htmlspecialchars($adv['department'] ?? '—') ?></td>
                <td style="font-size:10px;"><?= htmlspecialchars($adv['section'] ?? '—') ?></td>
            </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
    <?php else: ?>
    <p style="color:#aaa; font-size:12px; text-align:center; padding:20px;">No class advisers for this term.</p>
    <?php endif; ?>

    <!-- Applications -->
    <div class="section-title">📋 Applications Summary</div>
    <?php if (!empty($apps_data)): ?>
    <table>
        <thead>
            <tr>
                <th>#</th>
                <th>Student</th>
                <th>Signatory</th>
                <th>Course</th>
                <th>Requirement</th>
                <th>Status</th>
                <th>Rejections</th>
                <th>Submitted</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($apps_data as $i => $a): 
                $badge = 'badge-gray';
                if ($a['status'] === 'Approved') $badge = 'badge-green';
                elseif ($a['status'] === 'Totally Rejected') $badge = 'badge-red';
                elseif ($a['status'] === 'Requires Action') $badge = 'badge-purple';
                elseif ($a['status'] === 'Pending') $badge = 'badge-orange';
            ?>
            <tr>
                <td><?= $i + 1 ?></td>
                <td><?= htmlspecialchars($a['username']) ?></td>
                <td><?= htmlspecialchars($a['signatory']) ?></td>
                <td><?= htmlspecialchars($a['course']) ?></td>
                <td><?= htmlspecialchars($a['requirement_name'] ?? 'N/A') ?></td>
                <td><span class="badge <?= $badge ?>"><?= htmlspecialchars($a['status']) ?></span></td>
                <td style="text-align:center; color:<?= $a['rejection_count'] > 0 ? '#c62828' : '#888' ?>; font-weight:bold;">
                    <?= (int)$a['rejection_count'] ?>
                </td>
                <td><?= $a['submitted_at'] ? date('M d, Y', strtotime($a['submitted_at'])) : '—' ?></td>
            </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
    <?php else: ?>
    <p style="color:#aaa; font-size:12px; text-align:center; padding:20px;">No applications for this term.</p>
    <?php endif; ?>

    <!-- Footer -->
    <div class="report-footer">
        BPC Smart Clearance System &nbsp;•&nbsp; <?= htmlspecialchars($sem) ?> <?= htmlspecialchars($sy) ?> &nbsp;•&nbsp; Printed <?= date('F d, Y') ?>
    </div>

</div>
</body>
</html>