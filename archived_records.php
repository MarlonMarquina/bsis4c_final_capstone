<?php
include 'conn.php';
session_start();
date_default_timezone_set('Asia/Manila');
if (!isset($_SESSION['role']) || $_SESSION['role'] != "admin") {
    header("Location: login.php"); exit();
}

$admin_user = $_SESSION['username'];
$admin_stmt = $conn->prepare("SELECT full_name FROM users WHERE username = ? AND role = 'admin'");
$admin_stmt->bind_param("s", $admin_user);
$admin_stmt->execute();
$admin_data = $admin_stmt->get_result()->fetch_assoc();
$signatoryFullName = !empty($admin_data['full_name']) ? $admin_data['full_name'] : $admin_user;
$userRole = "Administrator";

// Fetch all unique archived terms (school_year + semester)
// Get current active term to exclude it from archive view
$current_term = $conn->query("SELECT current_semester, current_school_year FROM system_settings WHERE id=1")->fetch_assoc();
$active_sem = $current_term['current_semester'];
$active_sy = $current_term['current_school_year'];

$terms_query = $conn->query("
    SELECT DISTINCT school_year, semester FROM (
        SELECT school_year, semester FROM archived_applications
        UNION
        SELECT school_year, semester FROM archived_course_requirements
        UNION
        SELECT school_year, semester FROM archived_draft_requirements
        UNION
        SELECT school_year, semester FROM archived_signatory_history
        UNION
        SELECT school_year, semester FROM archived_student_status
    ) AS all_terms
    WHERE NOT (semester = '$active_sem' AND school_year = '$active_sy')
    ORDER BY school_year DESC, FIELD(semester, '1st Semester', '2nd Semester', 'Summer')
");

$terms = [];
while ($t = $terms_query->fetch_assoc()) {
    $terms[] = $t;
}

// Group by school year
$grouped = [];
foreach ($terms as $t) {
    $grouped[$t['school_year']][] = $t['semester'];
}

// Fetch counts per term
function getTermCounts($conn, $sem, $sy) {
    $counts = [];

    $r = $conn->query("SELECT COUNT(*) as c FROM archived_applications WHERE semester='$sem' AND school_year='$sy'");
    $counts['applications'] = (int)$r->fetch_assoc()['c'];

    $r = $conn->query("SELECT COUNT(*) as c FROM archived_course_requirements WHERE semester='$sem' AND school_year='$sy'");
    $counts['requirements'] = (int)$r->fetch_assoc()['c'];

    $r = $conn->query("SELECT COUNT(*) as c FROM archived_draft_requirements WHERE semester='$sem' AND school_year='$sy'");
    $counts['drafts'] = (int)$r->fetch_assoc()['c'];

    $r = $conn->query("SELECT COUNT(*) as c FROM archived_signatory_history WHERE semester='$sem' AND school_year='$sy'");
    $counts['history'] = (int)$r->fetch_assoc()['c'];

    $r = $conn->query("SELECT COUNT(*) as c FROM archived_student_status WHERE semester='$sem' AND school_year='$sy'");
    $counts['students'] = (int)$r->fetch_assoc()['c'];

    $r = $conn->query("SELECT COUNT(*) as c FROM archived_user_accounts WHERE semester='$sem' AND school_year='$sy' AND role='student'");
    $counts['student_accounts'] = (int)$r->fetch_assoc()['c'];

    $r = $conn->query("SELECT COUNT(*) as c FROM archived_user_accounts WHERE semester='$sem' AND school_year='$sy' AND role='signatory' AND signatory_type='Class Adviser'");
    $counts['adviser_accounts'] = (int)$r->fetch_assoc()['c'];

    return $counts;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Archived Records | Smart Clearance</title>
    <link href='https://unpkg.com/boxicons@2.1.4/css/boxicons.min.css' rel='stylesheet'>
    <link href="https://fonts.googleapis.com/css2?family=DM+Serif+Display&family=DM+Sans:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="styles.css">
    <style>
        :root {
            --forest: #1a3409;
            --green: #2d5016;
            --mint: #e8f5e9;
            --gold: #c8960c;
            --gold-light: #fff8e1;
            --slate: #37474f;
            --light: #f4f6f4;
            --border: #dde5dd;
            --shadow: 0 4px 24px rgba(26,52,9,0.08);
        }

        body {
            font-family: 'Poppins', sans-serif;
            background: var(--light);
            margin: 0;
            padding: 0;
            color: var(--slate);
        }
.home {
    position: relative;
    left: 250px;
    width: calc(100% - 250px);
    padding: 30px;
    min-height: 100vh;
    box-sizing: border-box;
    transition: all 0.3s ease;
}

.sidebar.close ~ .home {
    left: 88px;
    width: calc(100% - 88px);
}

        /* ── Page Header ── */
        .page-header {
            background: linear-gradient(135deg, var(--forest) 0%, #2d5016 60%, #3a6b1a 100%);
            border-radius: 16px;
            padding: 32px 36px;
            margin-bottom: 30px;
            color: white;
            display: flex;
            align-items: center;
            justify-content: space-between;
            box-shadow: var(--shadow);
            position: relative;
            overflow: hidden;
        }
        .page-header::before {
            content: '';
            position: absolute;
            top: -40px; right: -40px;
            width: 200px; height: 200px;
            border-radius: 50%;
            background: rgba(255,255,255,0.04);
        }
        .page-header::after {
            content: '';
            position: absolute;
            bottom: -60px; right: 120px;
            width: 280px; height: 280px;
            border-radius: 50%;
            background: rgba(255,255,255,0.03);
        }
        .page-header-left h1 {
            font-family: 'Poppins', sans-serif;
            font-size: 28px;
            margin: 0 0 6px;
            letter-spacing: 0.3px;
        }
        .page-header-left p {
            margin: 0;
            font-size: 14px;
            opacity: 0.75;
            font-weight: 300;
        }
        .page-header-right {
            font-size: 72px;
            opacity: 0.12;
            position: relative;
            z-index: 0;
        }

        /* ── Empty State ── */
        .empty-archive {
            text-align: center;
            padding: 80px 40px;
            background: white;
            border-radius: 16px;
            border: 2px dashed var(--border);
            color: #aaa;
        }
        .empty-archive i { font-size: 64px; display: block; margin-bottom: 16px; }
        .empty-archive h3 { font-family: 'Poppins', sans-serif; font-size: 22px; color: #bbb; margin: 0 0 8px; }
        .empty-archive p { font-size: 14px; margin: 0; }

        /* ── School Year Accordion ── */
        .sy-block { margin-bottom: 20px; }

        .sy-header {
            background: white;
            border: 2px solid var(--border);
            border-radius: 12px;
            padding: 18px 24px;
            cursor: pointer;
            display: flex;
            align-items: center;
            gap: 14px;
            transition: all 0.25s ease;
            box-shadow: var(--shadow);
            user-select: none;
        }
        .sy-header:hover {
            border-color: var(--green);
            background: var(--mint);
        }
        .sy-header.open {
            border-color: var(--green);
            border-bottom-left-radius: 0;
            border-bottom-right-radius: 0;
            background: var(--forest);
            color: white;
            box-shadow: none;
        }
        .sy-icon {
            width: 42px; height: 42px;
            background: var(--mint);
            border-radius: 10px;
            display: flex; align-items: center; justify-content: center;
            font-size: 20px;
            color: var(--green);
            flex-shrink: 0;
            transition: 0.25s;
        }
        .sy-header.open .sy-icon { background: rgba(255,255,255,0.15); color: white; }
        .sy-title {
            flex: 1;
            font-family: 'Poppins', sans-serif;
            font-size: 20px;
            letter-spacing: 0.3px;
        }
        .sy-meta { font-size: 13px; opacity: 0.65; font-weight: 400; font-family: 'Poppins', sans-serif; }
        .sy-chevron {
            font-size: 22px;
            transition: transform 0.25s ease;
            opacity: 0.6;
        }
        .sy-header.open .sy-chevron { transform: rotate(180deg); opacity: 1; }
        .sy-count-badge {
            background: var(--mint);
            color: var(--green);
            border: 1px solid #c8e6c9;
            padding: 4px 12px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: 700;
        }
        .sy-header.open .sy-count-badge { background: rgba(255,255,255,0.15); color: white; border-color: rgba(255,255,255,0.2); }

        .sy-body {
            display: none;
            background: #fafcfa;
            border: 2px solid var(--green);
            border-top: none;
            border-bottom-left-radius: 12px;
            border-bottom-right-radius: 12px;
            padding: 16px;
        }
        .sy-body.open { display: block; }

        /* ── Semester Accordion ── */
        .sem-block { margin-bottom: 12px; }

        .sem-header {
            background: white;
            border: 1px solid var(--border);
            border-radius: 10px;
            padding: 14px 20px;
            cursor: pointer;
            display: flex;
            align-items: center;
            gap: 12px;
            transition: all 0.2s ease;
            user-select: none;
        }
        .sem-header:hover { border-color: #81c784; background: #f1f8f1; }
        .sem-header.open {
            background: linear-gradient(135deg, #2d5016, #3a6b1a);
            color: white;
            border-color: transparent;
            border-bottom-left-radius: 0;
            border-bottom-right-radius: 0;
        }
        .sem-dot {
            width: 10px; height: 10px;
            border-radius: 50%;
            background: var(--gold);
            flex-shrink: 0;
        }
        .sem-header.open .sem-dot { background: white; }
        .sem-label {
            flex: 1;
            font-weight: 600;
            font-size: 15px;
        }
        .sem-chevron {
            font-size: 18px;
            transition: transform 0.2s ease;
            opacity: 0.5;
        }
        .sem-header.open .sem-chevron { transform: rotate(180deg); opacity: 1; }

        .sem-body {
            display: none;
            border: 1px solid #c8e6c9;
            border-top: none;
            border-bottom-left-radius: 10px;
            border-bottom-right-radius: 10px;
            padding: 16px;
            background: white;
        }
        .sem-body.open { display: block; }

        /* ── Category Tabs ── */
        .cat-tabs {
            display: flex;
            gap: 8px;
            margin-bottom: 16px;
            flex-wrap: wrap;
        }
        .cat-tab {
            padding: 8px 16px;
            border-radius: 8px;
            border: 2px solid var(--border);
            background: white;
            font-size: 13px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.2s;
            display: flex;
            align-items: center;
            gap: 6px;
            color: var(--slate);
        }
        .cat-tab:hover { border-color: var(--green); color: var(--green); }
        .cat-tab.active { background: var(--green); color: white; border-color: var(--green); }
        .cat-tab .tab-count {
            background: rgba(255,255,255,0.25);
            padding: 1px 7px;
            border-radius: 10px;
            font-size: 11px;
        }
        .cat-tab:not(.active) .tab-count { background: #eee; color: #666; }

        /* ── Data Panels ── */
        .data-panel { display: none; }
        .data-panel.active { display: block; }

        /* ── Tables ── */
        .arch-table-wrap { overflow-x: auto; border-radius: 10px; border: 1px solid var(--border); }
        .arch-table {
            width: 100%;
            border-collapse: collapse;
            font-size: 13px;
            min-width: 600px;
        }
        .arch-table thead th {
            background: var(--forest);
            color: white;
            padding: 12px 14px;
            text-align: left;
            font-weight: 600;
            font-size: 12px;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            white-space: nowrap;
        }
        .arch-table tbody tr { border-bottom: 1px solid #f0f0f0; transition: background 0.15s; }
        .arch-table tbody tr:hover { background: var(--mint); }
        .arch-table tbody td { padding: 11px 14px; color: var(--slate); }
        .arch-table tbody tr:last-child { border-bottom: none; }

        /* Status Pills */
        .pill {
            display: inline-block;
            padding: 3px 10px;
            border-radius: 20px;
            font-size: 11px;
            font-weight: 700;
            text-transform: uppercase;
        }
        .pill-approved { background: #d4edda; color: #155724; }
        .pill-pending { background: #fff3cd; color: #856404; }
        .pill-rejected { background: #f8d7da; color: #721c24; }
        .pill-requires { background: #e8d5ff; color: #5a0080; }
        .pill-configured { background: #d4edda; color: #155724; }
        .pill-unconfigured { background: #f8d7da; color: #721c24; }

        /* Summary Cards */
        .summary-cards {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(140px, 1fr));
            gap: 12px;
            margin-bottom: 20px;
        }
        .summary-card {
            background: var(--mint);
            border: 1px solid #c8e6c9;
            border-radius: 10px;
            padding: 14px 16px;
            text-align: center;
        }
        .summary-card .sc-num {
            font-family: 'Poppins', sans-serif;
            font-size: 28px;
            color: var(--forest);
            line-height: 1;
            display: block;
            margin-bottom: 4px;
        }
        .summary-card .sc-label {
            font-size: 11px;
            color: #666;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        /* No data */
        .no-data {
            text-align: center;
            padding: 30px;
            color: #aaa;
            font-size: 14px;
        }
        .no-data i { font-size: 32px; display: block; margin-bottom: 8px; }

        /* Pagination */
        .table-info {
            font-size: 12px;
            color: #888;
            margin-top: 10px;
            text-align: right;
        }
    </style>
</head>
<body>

<?php include 'sidebar_admin.php'; ?>

<section class="home">

    <div class="page-header">
        <div class="page-header-left">
            <h1><i class='bx bx-archive'></i> Archived Records</h1>
            <p>Browse historical clearance data organized by school year and semester</p>
        </div>
        <div class="page-header-right">
            <i class='bx bx-server'></i>
        </div>
    </div>

    <?php if (empty($grouped)): ?>
    <div class="empty-archive">
        <i class='bx bx-archive-in'></i>
        <h3>No Archives Yet</h3>
        <p>Archived data will appear here after a term switch is performed in System Settings.</p>
    </div>

    <?php else: ?>

    <?php foreach ($grouped as $sy => $semesters): ?>
    <div class="sy-block">
        <div class="sy-header" onclick="toggleSY(this)">
            <div class="sy-icon"><i class='bx bx-calendar-alt'></i></div>
            <div style="flex:1;">
                <div class="sy-title">School Year <?= htmlspecialchars($sy) ?></div>
                <div class="sy-meta"><?= count($semesters) ?> semester<?= count($semesters) > 1 ? 's' : '' ?> archived</div>
            </div>
            <span class="sy-count-badge"><?= count($semesters) ?> term<?= count($semesters) > 1 ? 's' : '' ?></span>
            <i class='bx bx-chevron-down sy-chevron'></i>
        </div>
        <div class="sy-body">
            <?php foreach ($semesters as $sem): 
                $counts = getTermCounts($conn, $sem, $sy);
                $total = array_sum($counts);
                $panel_id = 'panel_' . preg_replace('/[^a-z0-9]/i', '_', $sy . '_' . $sem);

                // Fetch data for this term
                // Applications
                $apps = $conn->query("
                    SELECT aa.*, rl.requirement_name 
                    FROM archived_applications aa
                    LEFT JOIN requirement_library rl ON aa.requirement_id = rl.id
                    WHERE aa.semester = '{$conn->real_escape_string($sem)}' 
                    AND aa.school_year = '{$conn->real_escape_string($sy)}'
                    ORDER BY aa.submitted_at DESC
                ");

                // Requirements
                $reqs = $conn->query("
                    SELECT acr.*, c.course_name, u.full_name AS signatory_name, u.signatory_type,
                           rl.requirement_name
                    FROM archived_course_requirements acr
                    LEFT JOIN courses c ON acr.course_id = c.id
                    LEFT JOIN users u ON acr.signatory_id = u.id
                    LEFT JOIN requirement_library rl ON acr.requirement_id = rl.id
                    WHERE acr.semester = '{$conn->real_escape_string($sem)}' 
                    AND acr.school_year = '{$conn->real_escape_string($sy)}'
                    ORDER BY c.course_name ASC, u.full_name ASC
                ");

                // Signatory History
                $history = $conn->query("
    SELECT ash.*, ua.full_name AS student_name
    FROM archived_signatory_history ash
    LEFT JOIN archived_user_accounts ua 
        ON ash.student_user = ua.username 
        AND ua.semester = '{$conn->real_escape_string($sem)}'
        AND ua.school_year = '{$conn->real_escape_string($sy)}'
        AND ua.role = 'student'
    WHERE ash.semester = '{$conn->real_escape_string($sem)}' 
    AND ash.school_year = '{$conn->real_escape_string($sy)}'
    ORDER BY ash.created_at DESC
");

               // Student Statuses — join to archived_user_accounts instead of users
$statuses = $conn->query("
    SELECT ass.*, ua.full_name, ua.course, ua.year, ua.section
    FROM archived_student_status ass
    LEFT JOIN archived_user_accounts ua 
        ON ass.username = ua.username 
        AND ua.semester = '{$conn->real_escape_string($sem)}'
        AND ua.school_year = '{$conn->real_escape_string($sy)}'
        AND ua.role = 'student'
    WHERE ass.semester = '{$conn->real_escape_string($sem)}' 
    AND ass.school_year = '{$conn->real_escape_string($sy)}'
    ORDER BY ua.course ASC, ua.full_name ASC
");

                // Approved count for summary
                $appr_q = $conn->query("SELECT COUNT(*) as c FROM archived_applications WHERE semester='{$conn->real_escape_string($sem)}' AND school_year='{$conn->real_escape_string($sy)}' AND status='Approved'");
                $appr_count = (int)$appr_q->fetch_assoc()['c'];

                $cleared_q = $conn->query("SELECT COUNT(*) as c FROM archived_student_status WHERE semester='{$conn->real_escape_string($sem)}' AND school_year='{$conn->real_escape_string($sy)}' AND admin_approved=1");
                $cleared_count = (int)$cleared_q->fetch_assoc()['c'];
            ?>
            <div class="sem-block">
                <div class="sem-header" onclick="toggleSEM(this)">
                    <div class="sem-dot"></div>
                    <span class="sem-label">
                        <i class='bx bx-book-open'></i> <?= htmlspecialchars($sem) ?>
                    </span>
                    <span style="font-size:12px; color:inherit; opacity:0.7; margin-right:10px;">
                        <?= $total ?> records
                    </span>
                    <i class='bx bx-chevron-down sem-chevron'></i>
                </div>
                <div class="sem-body">
    <div style="display:flex; justify-content:flex-end; margin-bottom:16px;">
        <a href="export_archive_pdf.php?sem=<?= urlencode($sem) ?>&sy=<?= urlencode($sy) ?>" 
           target="_blank"
           style="display:inline-flex; align-items:center; gap:6px; padding:9px 18px; background:linear-gradient(135deg,#c0392b,#e74c3c); color:white; border-radius:8px; font-size:13px; font-weight:700; text-decoration:none;">
            🖨️ Download Full PDF Report
        </a>
    </div>

                    <!-- Summary Cards -->
                    <div class="summary-cards">
                        <div class="summary-card">
                            <span class="sc-num"><?= $counts['applications'] ?></span>
                            <span class="sc-label">Applications</span>
                        </div>
                        <div class="summary-card">
                            <span class="sc-num"><?= $appr_count ?></span>
                            <span class="sc-label">Approved Apps</span>
                        </div>
                        <div class="summary-card">
                            <span class="sc-num"><?= $counts['requirements'] ?></span>
                            <span class="sc-label">Requirements</span>
                        </div>
                        <div class="summary-card">
                            <span class="sc-num"><?= $counts['students'] ?></span>
                            <span class="sc-label">Students</span>
                        </div>
                        <div class="summary-card">
                            <span class="sc-num"><?= $cleared_count ?></span>
                            <span class="sc-label">Fully Cleared</span>
                        </div>
                        <div class="summary-card">
                            <span class="sc-num"><?= $counts['history'] ?></span>
                            <span class="sc-label">Sig. Actions</span>
                        </div>
                    </div>

                    <!-- Category Tabs -->
                    <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:12px; flex-wrap:wrap; gap:10px;">
    <div class="cat-tabs" style="margin-bottom:0;">
        <button class="cat-tab active" onclick="switchTab(this, '<?= $panel_id ?>_apps', '<?= $panel_id ?>')">
            <i class='bx bx-file'></i> Applications
            <span class="tab-count"><?= $counts['applications'] ?></span>
        </button>
        <button class="cat-tab" onclick="switchTab(this, '<?= $panel_id ?>_reqs', '<?= $panel_id ?>')">
            <i class='bx bx-list-check'></i> Requirements
            <span class="tab-count"><?= $counts['requirements'] ?></span>
        </button>
        <button class="cat-tab" onclick="switchTab(this, '<?= $panel_id ?>_students', '<?= $panel_id ?>')">
            <i class='bx bx-group'></i> Student Status
            <span class="tab-count"><?= $counts['students'] ?></span>
        </button>
        <button class="cat-tab" onclick="switchTab(this, '<?= $panel_id ?>_history', '<?= $panel_id ?>')">
            <i class='bx bx-history'></i> Signatory Log
            <span class="tab-count"><?= $counts['history'] ?></span>
        </button>
        <button class="cat-tab" onclick="switchTab(this, '<?= $panel_id ?>_advisers', '<?= $panel_id ?>')">
            <i class='bx bx-chalkboard'></i> Class Advisers
            <span class="tab-count"><?= $counts['adviser_accounts'] ?></span>
        </button>
    </div>
    <a id="<?= $panel_id ?>_excel_btn"
       href="export_archive_tab.php?sem=<?= urlencode($sem) ?>&sy=<?= urlencode($sy) ?>&tab=applications"
       style="display:inline-flex; align-items:center; gap:6px; padding:9px 16px; background:#1d6f42; color:white; border-radius:8px; font-size:12px; font-weight:700; text-decoration:none; white-space:nowrap;">
        📥 Export Tab to Excel
    </a>
</div>
                    <!-- Applications Panel -->
                    <div id="<?= $panel_id ?>_apps" class="data-panel active">
                        <?php if ($apps && $apps->num_rows > 0): ?>
                        <div class="arch-table-wrap">
                            <table class="arch-table">
                                <thead>
                                    <tr>
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
                                    <?php while ($a = $apps->fetch_assoc()): 
                                        $pill = 'pill-pending';
                                        if ($a['status'] === 'Approved') $pill = 'pill-approved';
                                        elseif ($a['status'] === 'Rejected' || $a['status'] === 'Totally Rejected') $pill = 'pill-rejected';
                                        elseif ($a['status'] === 'Requires Action') $pill = 'pill-requires';
                                    ?>
                                    <tr>
                                        <td><?= htmlspecialchars($a['username']) ?></td>
                                        <td><?= htmlspecialchars($a['signatory']) ?></td>
                                        <td><?= htmlspecialchars($a['course']) ?></td>
                                        <td><?= htmlspecialchars($a['requirement_name'] ?? 'N/A') ?></td>
                                        <td><span class="pill <?= $pill ?>"><?= htmlspecialchars($a['status']) ?></span></td>
                                        <td style="text-align:center; color:<?= $a['rejection_count'] > 0 ? '#c62828' : '#888' ?>; font-weight:700;">
                                            <?= (int)$a['rejection_count'] ?>
                                        </td>
                                        <td><?= $a['submitted_at'] ? date('M d, Y', strtotime($a['submitted_at'])) : '—' ?></td>
                                    </tr>
                                    <?php endwhile; ?>
                                </tbody>
                            </table>
                        </div>
                        <div style="display:flex; justify-content:space-between; align-items:center; margin-top:10px;">
    <span class="table-info" style="margin:0;"><?= $counts['applications'] ?> total applications archived</span>
    <a href="export_archive_tab.php?sem=<?= urlencode($sem) ?>&sy=<?= urlencode($sy) ?>&tab=applications" style="font-size:12px; color:#1d6f42; font-weight:700; text-decoration:none;">📥 Download Excel</a>
</div>
                        <?php else: ?>
                        <div class="no-data"><i class='bx bx-file-blank'></i> No applications archived for this term.</div>
                        <?php endif; ?>
                    </div>

                    <!-- Requirements Panel -->
                    <div id="<?= $panel_id ?>_reqs" class="data-panel">
                        <?php if ($reqs && $reqs->num_rows > 0): ?>
                        <div class="arch-table-wrap">
                            <table class="arch-table">
                                <thead>
                                    <tr>
                                        <th>Signatory</th>
                                        <th>Type</th>
                                        <th>Course</th>
                                        <th>Requirement</th>
                                        <th>Year Level</th>
                                        <th>Sections</th>
                                        <th>Configured</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php while ($r = $reqs->fetch_assoc()): ?>
                                    <tr>
                                        <td><?= htmlspecialchars($r['signatory_name'] ?? '—') ?></td>
                                        <td><?= htmlspecialchars($r['signatory_type'] ?? '—') ?></td>
                                        <td><?= htmlspecialchars($r['course_name'] ?? '—') ?></td>
                                        <td><?= htmlspecialchars($r['requirement_name'] ?? 'No Requirement') ?></td>
                                        <td><?= htmlspecialchars($r['year_level'] ?? '—') ?></td>
                                        <td><?= htmlspecialchars($r['sections'] ?? '—') ?></td>
                                        <td>
                                            <span class="pill <?= $r['requirements_configured'] ? 'pill-configured' : 'pill-unconfigured' ?>">
                                                <?= $r['requirements_configured'] ? 'Yes' : 'No' ?>
                                            </span>
                                        </td>
                                    </tr>
                                    <?php endwhile; ?>
                                </tbody>
                            </table>
                        </div>
                        <div class="table-info"><?= $counts['requirements'] ?> total requirement assignments archived</div>
                        <?php else: ?>
                        <div class="no-data"><i class='bx bx-list-ul'></i> No requirements archived for this term.</div>
                        <?php endif; ?>
                    </div>

                    <!-- Student Status Panel -->
                    <div id="<?= $panel_id ?>_students" class="data-panel">
                        <?php if ($statuses && $statuses->num_rows > 0): ?>
                        <div class="arch-table-wrap">
                            <table class="arch-table">
                                <thead>
                                    <tr>
                                        <th>Student</th>
                                        <th>Student ID</th>
                                        <th>Course</th>
                                        <th>Year & Section</th>
                                        <th>Admin Approved</th>
                                        <th>Clearance Status</th>
                                        <th>Messaged</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php while ($s = $statuses->fetch_assoc()): ?>
                                    <tr>
                                        <td style="font-weight:600;"><?= htmlspecialchars($s['full_name'] ?? $s['username']) ?></td>
                                        <td><?= htmlspecialchars($s['username']) ?></td>
                                        <td><?= htmlspecialchars($s['course'] ?? '—') ?></td>
                                        <td><?= htmlspecialchars(($s['year'] ?? '') . ' - ' . ($s['section'] ?? '')) ?></td>
                                        <td>
                                            <span class="pill <?= $s['admin_approved'] ? 'pill-approved' : 'pill-pending' ?>">
                                                <?= $s['admin_approved'] ? '✅ Approved' : 'Not Yet' ?>
                                            </span>
                                        </td>
                                        <td><?= htmlspecialchars($s['final_clearance_status'] ?? 'N/A') ?></td>
                                        <td>
                                            <?php if ($s['admin_messaged']): ?>
                                                <span class="pill pill-configured" title="<?= htmlspecialchars($s['admin_message_text'] ?? '') ?>">✉ Yes</span>
                                            <?php else: ?>
                                                <span style="color:#aaa; font-size:12px;">No</span>
                                            <?php endif; ?>
                                        </td>
                                    </tr>
                                    <?php endwhile; ?>
                                </tbody>
                            </table>
                        </div>
                        <div class="table-info"><?= $counts['students'] ?> student records archived</div>
                        <?php else: ?>
                        <div class="no-data"><i class='bx bx-group'></i> No student records archived for this term.</div>
                        <?php endif; ?>
                    </div>

                    <!-- Signatory History Panel -->
                    <div id="<?= $panel_id ?>_history" class="data-panel">
                        <?php if ($history && $history->num_rows > 0): ?>
                        <div class="arch-table-wrap">
                            <table class="arch-table">
                                <thead>
                                    <tr>
                                        <th>Signatory</th>
                                        <th>Student</th>
                                        <th>Action</th>
                                        <th>Reason</th>
                                        <th>Date</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php while ($h = $history->fetch_assoc()): 
                                        $apill = $h['action'] === 'Approved' ? 'pill-approved' : 'pill-rejected';
                                    ?>
                                    <tr>
                                        <td><?= htmlspecialchars($h['signatory_username']) ?></td>
                                        <td><?= htmlspecialchars($h['student_name'] ?? $h['student_user']) ?></td>
                                        <td><span class="pill <?= $apill ?>"><?= htmlspecialchars($h['action']) ?></span></td>
                                        <td style="max-width:200px; font-size:12px; color:#666;">
                                            <?= htmlspecialchars(substr($h['reason'] ?? '—', 0, 80)) ?>
                                            <?= strlen($h['reason'] ?? '') > 80 ? '...' : '' ?>
                                        </td>
                                        <td><?= $h['created_at'] ? date('M d, Y', strtotime($h['created_at'])) : '—' ?></td>
                                    </tr>
                                    <?php endwhile; ?>
                                </tbody>
                            </table>
                        </div>
                        <div class="table-info"><?= $counts['history'] ?> signatory actions archived</div>
                        <?php else: ?>
                        <div class="no-data"><i class='bx bx-history'></i> No signatory history archived for this term.</div>
                        <?php endif; ?>
                   </div>

                    <!-- Class Advisers Panel -->
                    <?php
                    $advisers_archived = $conn->query("
                        SELECT * FROM archived_user_accounts
                        WHERE semester = '{$conn->real_escape_string($sem)}'
                        AND school_year = '{$conn->real_escape_string($sy)}'
                        AND role = 'signatory'
                        AND signatory_type = 'Class Adviser'
                        ORDER BY department ASC, full_name ASC
                    ");
                    ?>
                    <div id="<?= $panel_id ?>_advisers" class="data-panel">
                        <?php if ($advisers_archived && $advisers_archived->num_rows > 0): ?>
                        <div class="arch-table-wrap">
                            <table class="arch-table">
                                <thead>
                                    <tr>
                                        <th>Username</th>
                                        <th>Full Name</th>
                                        <th>Email</th>
                                        <th>Department</th>
                                        <th>Handled Sections</th>
                                        <th>Status</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php while ($adv = $advisers_archived->fetch_assoc()): ?>
                                    <tr>
                                        <td><?= htmlspecialchars($adv['username']) ?></td>
                                        <td style="font-weight:600;"><?= htmlspecialchars($adv['full_name'] ?? '—') ?></td>
                                        <td><?= htmlspecialchars($adv['email'] ?? '—') ?></td>
                                        <td><?= htmlspecialchars($adv['department'] ?? '—') ?></td>
                                        <td style="font-size:12px; color:#555;"><?= htmlspecialchars($adv['section'] ?? '—') ?></td>
                                        <td>
                                            <span class="pill <?= $adv['status'] === 'active' ? 'pill-approved' : 'pill-rejected' ?>">
                                                <?= ucfirst($adv['status']) ?>
                                            </span>
                                        </td>
                                    </tr>
                                    <?php endwhile; ?>
                                </tbody>
                            </table>
                        </div>
                        <div class="table-info"><?= $counts['adviser_accounts'] ?> class adviser(s) archived</div>
                        <?php else: ?>
                        <div class="no-data"><i class='bx bx-chalkboard'></i> No class advisers archived for this term.</div>
                        <?php endif; ?>
                    </div>

                </div><!-- /sem-body -->
            </div><!-- /sem-block -->
            <?php endforeach; ?>
        </div><!-- /sy-body -->
    </div><!-- /sy-block -->
    <?php endforeach; ?>

    <?php endif; ?>

</section>

<script>

function toggleSY(header) {
    const body = header.nextElementSibling;
    const isOpen = header.classList.contains('open');
    // Close all
    document.querySelectorAll('.sy-header').forEach(h => h.classList.remove('open'));
    document.querySelectorAll('.sy-body').forEach(b => b.classList.remove('open'));
    if (!isOpen) {
        header.classList.add('open');
        body.classList.add('open');
    }
}

function toggleSEM(header) {
    const body = header.nextElementSibling;
    const isOpen = header.classList.contains('open');
    // Close siblings within same sy-body
    const parent = header.closest('.sy-body');
    parent.querySelectorAll('.sem-header').forEach(h => h.classList.remove('open'));
    parent.querySelectorAll('.sem-body').forEach(b => b.classList.remove('open'));
    if (!isOpen) {
        header.classList.add('open');
        body.classList.add('open');
    }
}

function switchTab(btn, panelId, prefix) {
    const semBody = btn.closest('.sem-body');
    semBody.querySelectorAll('.cat-tab').forEach(t => t.classList.remove('active'));
    semBody.querySelectorAll('.data-panel').forEach(p => p.classList.remove('active'));
    btn.classList.add('active');
    document.getElementById(panelId).classList.add('active');

    // Update the Excel export button URL to match active tab
    const tabMap = {
        '_apps':     'applications',
        '_reqs':     'requirements',
        '_students': 'students',
        '_history':  'history',
        '_advisers': 'advisers'
    };
    const excelBtn = document.getElementById(prefix + '_excel_btn');
    if (excelBtn) {
        const suffix = panelId.replace(prefix, '');
        const tab = tabMap[suffix] || 'applications';
        const url = new URL(excelBtn.href, window.location.origin);
        url.searchParams.set('tab', tab);
        excelBtn.href = url.toString();
    }
}
</script>

</body>
</html>
