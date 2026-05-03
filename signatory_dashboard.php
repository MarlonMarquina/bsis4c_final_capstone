<?php
session_start();
date_default_timezone_set('Asia/Manila');
if (!isset($_SESSION['role']) || $_SESSION['role'] != "signatory") {
    header("Location: login.php");
    exit();
}

include 'conn.php';

$signatory = $_SESSION['username'];
$view = isset($_GET['view']) ? $_GET['view'] : 'dashboard';

// --- FILTERS ---
$courseFilter  = isset($_GET['course'])  ? $_GET['course']  : '';
$yearFilter    = isset($_GET['year'])    ? $_GET['year']    : '';
$sectionFilter = isset($_GET['section']) ? $_GET['section'] : '';
$search        = isset($_GET['search'])  ? $_GET['search']  : '';
$page          = isset($_GET['page'])    ? max(1, (int)$_GET['page']) : 1;
$limit         = 10;
$offset        = ($page - 1) * $limit;

// --- FETCH SIGNATORY INFO ---
$signatoryInfo     = [];
$signatoryFullName = $signatory;
$current_sig_id    = 0;

$sigInfoStmt = $conn->prepare(
    "SELECT id, full_name, signatory_type, department, section FROM users WHERE username = ? AND role = 'signatory' LIMIT 1"
);
$sigInfoStmt->bind_param("s", $signatory);
$sigInfoStmt->execute();
$sigInfoResult = $sigInfoStmt->get_result();
if ($sigInfoResult && $sigInfoResult->num_rows > 0) {
    $signatoryInfo  = $sigInfoResult->fetch_assoc();
    $current_sig_id = $signatoryInfo['id'];
    if (!empty($signatoryInfo['full_name'])) {
        $signatoryFullName = $signatoryInfo['full_name'];
    }
}
$sigInfoStmt->close();
// Check email verification status + password change status
$email_check = $conn->prepare("SELECT email_verified, password_last_updated FROM users WHERE username = ? LIMIT 1");
$email_check->bind_param("s", $signatory);
$email_check->execute();
$email_check_row = $email_check->get_result()->fetch_assoc();
$email_check->close();
$email_not_verified = ($email_check_row['email_verified'] ?? 0) == 0;
$password_not_changed = is_null($email_check_row['password_last_updated'] ?? null);

// --- MANAGE REQUIREMENTS POST LOGIC ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    $action = $_POST['action'];

    if ($action === 'add_library') {
        $name = trim($_POST['new_requirement_name'] ?? '');
        if ($name !== '') {
            $chk = $conn->prepare("SELECT id FROM requirement_library WHERE requirement_name = ?");
            $chk->bind_param("s", $name);
            $chk->execute();
            if ($chk->get_result()->num_rows == 0) {
                $ins = $conn->prepare("INSERT INTO requirement_library (requirement_name) VALUES (?)");
                $ins->bind_param("s", $name);
                $ins->execute();
                $ins->close();
            }
            $chk->close();
        }
    }

    if ($action === 'assign_requirement') {
        $course_id      = intval($_POST['course_id'] ?? 0);
        $requirement_id = intval($_POST['requirement_id'] ?? 0);
        $doctype_id     = intval($_POST['document_type_id'] ?? 0);
        if ($course_id > 0 && $requirement_id > 0) {
            $ins = $conn->prepare(
                "INSERT INTO course_requirements (course_id, requirement_id, signatory_id, document_type_id) VALUES (?, ?, ?, ?)"
            );
            $ins->bind_param("iiii", $course_id, $requirement_id, $current_sig_id, $doctype_id);
            $ins->execute();
            $ins->close();
        }
    }
}

// --- DELETE ASSIGNMENT ---
if (isset($_GET['delete_assign'])) {
    $del_id = intval($_GET['delete_assign']);
    $del = $conn->prepare("DELETE FROM course_requirements WHERE id = ? AND signatory_id = ?");
    $del->bind_param("ii", $del_id, $current_sig_id);
    $del->execute();
    $del->close();
    header("Location: signatory_dashboard.php?view=manage");
    exit();
}

// --- DYNAMIC QUERY BUILDER ---
function bind_param_array($stmt, $types, &$params) {
    $args = [&$types];
    foreach ($params as $k => $v) {
        $params[$k] = $v;
        $args[] = &$params[$k];
    }
    call_user_func_array([$stmt, 'bind_param'], $args);
}

$whereClauses = ["a.signatory = ?", "a.status IN ('Pending', 'Requires Action')", "a.status != ''"];
$params = [$signatory];
$types  = "s";

if ($courseFilter !== '')  { $whereClauses[] = "a.course = ?";                              $params[] = $courseFilter;        $types .= "s";  }
if ($yearFilter !== '')    { $whereClauses[] = "u.year = ?";                                $params[] = $yearFilter;          $types .= "s";  }
if ($sectionFilter !== '') { $whereClauses[] = "u.section = ?";                             $params[] = $sectionFilter;       $types .= "s";  }
if ($search !== '')        { $whereClauses[] = "(u.full_name LIKE ? OR a.username LIKE ?)"; $params[] = "%$search%";          $types .= "ss";
                                                                                             $params[] = "%$search%"; }

$where = "WHERE " . implode(" AND ", $whereClauses);

// Count
$cntStmt   = $conn->prepare("SELECT COUNT(*) as total FROM applications a LEFT JOIN users u ON a.username = u.username $where");
$cntParams = array_values($params);
bind_param_array($cntStmt, $types, $cntParams);
$cntStmt->execute();
$total  = (int)$cntStmt->get_result()->fetch_assoc()['total'];
$pages  = $total > 0 ? ceil($total / $limit) : 1;
$cntStmt->close();

// Data
$dataStmt   = $conn->prepare(
    "SELECT a.id, a.username, a.course, a.document, a.status, a.submitted_at, a.rejection_count,
            u.full_name, u.year, u.section, u.student_id
     FROM applications a
     LEFT JOIN users u ON a.username = u.username
     $where
     ORDER BY a.submitted_at DESC
     LIMIT ? OFFSET ?"
);
$bindParams   = array_values($params);
$bindParams[] = $limit;
$bindParams[] = $offset;
bind_param_array($dataStmt, $types . "ii", $bindParams);
$dataStmt->execute();
$rows = $dataStmt->get_result()->fetch_all(MYSQLI_ASSOC);
$dataStmt->close();

// Filter dropdowns - Store results in arrays
$coursesStmt = $conn->prepare("SELECT DISTINCT a.course FROM applications a WHERE a.signatory = ? ORDER BY a.course");
$coursesStmt->bind_param("s", $signatory);
$coursesStmt->execute();
$coursesResult = $coursesStmt->get_result();
$courses_array = [];
while ($c = $coursesResult->fetch_assoc()) { $courses_array[] = $c; }
$coursesStmt->close();

$yearsStmt = $conn->prepare("SELECT DISTINCT u.year FROM applications a LEFT JOIN users u ON a.username = u.username WHERE a.signatory = ? AND u.year IS NOT NULL ORDER BY u.year");
$yearsStmt->bind_param("s", $signatory);
$yearsStmt->execute();
$yearsResult = $yearsStmt->get_result();
$years_array = [];
while ($y = $yearsResult->fetch_assoc()) { $years_array[] = $y; }
$yearsStmt->close();

$sectionsStmt = $conn->prepare("SELECT DISTINCT u.section FROM applications a LEFT JOIN users u ON a.username = u.username WHERE a.signatory = ? AND u.section IS NOT NULL ORDER BY u.section");
$sectionsStmt->bind_param("s", $signatory);
$sectionsStmt->execute();
$sectionsResult = $sectionsStmt->get_result();
$sections_array = [];
while ($s = $sectionsResult->fetch_assoc()) { $sections_array[] = $s; }
$sectionsStmt->close();
// Pending count
$pcStmt = $conn->prepare("SELECT COUNT(*) AS cnt FROM applications WHERE signatory = ? AND status = 'Pending'");
$pcStmt->bind_param("s", $signatory);
$pcStmt->execute();
$pendingCount = (int)$pcStmt->get_result()->fetch_assoc()['cnt'];
$pcStmt->close();

// Alert messages
$alert_message = $alert_bg = $alert_color = '';
if (isset($_GET['success'])) {
    switch ($_GET['success']) {
        case 'approved':
            $alert_message = '✅ Application successfully Approved.';
            $alert_bg = '#d4edda'; $alert_color = '#155724'; break;
        case 'rejected':
            $alert_message = '⚠️ Application returned for revision successfully.';
            $alert_bg = '#fff3cd'; $alert_color = '#856404'; break;
        case 'totally_rejected':
            $c = (int)($_GET['count'] ?? 0);
            $alert_message = "⛔ Application TOTALLY REJECTED after {$c} rejections.";
            $alert_bg = '#f8d7da'; $alert_color = '#721c24'; break;
        case 'bulk_approved':
            $c = (int)($_GET['count'] ?? 0);
            $alert_message = "✅ Approved {$c} applications via Bulk Action.";
            $alert_bg = '#d4edda'; $alert_color = '#155724'; break;
    }
}

// Rejection reasons — common (signatory_id IS NULL) + this signatory's own reasons
$reasons_stmt = $conn->prepare(
    "SELECT id, reason_text, category, requires_reupload 
     FROM rejection_reasons 
     WHERE signatory_id IS NULL OR signatory_id = ? 
     ORDER BY category, reason_text"
);
$reasons_stmt->bind_param("i", $current_sig_id);
$reasons_stmt->execute();
$reasons_query = $reasons_stmt->get_result();
$reasons_array = [];
while ($r = $reasons_query->fetch_assoc()) { $reasons_array[] = $r; }
$reasons_json = json_encode($reasons_array);
$reasons_stmt->close();
// Category list for modal dropdown — common + this signatory's categories
$categories_stmt = $conn->prepare(
    "SELECT DISTINCT category FROM rejection_reasons 
     WHERE signatory_id IS NULL OR signatory_id = ? 
     ORDER BY category"
);
$categories_stmt->bind_param("i", $current_sig_id);
$categories_stmt->execute();
$categories_result = $categories_stmt->get_result();
$categories_array = [];
while ($cat = $categories_result->fetch_assoc()) { $categories_array[] = $cat['category']; }
$categories_stmt->close();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Signatory Dashboard</title>
    <link href="https://unpkg.com/boxicons@2.1.4/css/boxicons.min.css" rel="stylesheet">
    <link rel="stylesheet" href="styles.css">
    <style>
        /* ── Layout ── */
        .home { padding: 25px; background: #f4f7f6; min-height: 100vh; box-sizing: border-box; }

        /* ── Header ── */
        .dashboard-header { margin-bottom: 30px; border-bottom: 2px solid #eee; padding-bottom: 15px; }
        .dashboard-header h1 { color: #0b4e12; font-size: 32px; margin: 0 0 8px; font-weight: 800; }
        .badge-info {
            display: inline-flex; align-items: center; gap: 5px;
            background: #e8f5e9; color: #0b4e12; padding: 5px 14px;
            border-radius: 20px; font-size: 13px; font-weight: 600;
            border: 1px solid #c8e6c9; margin-right: 6px; margin-top: 6px;
        }

        /* ── Alert ── */
        .alert-box { padding: 14px 18px; margin-bottom: 20px; border-radius: 8px; font-size: 14px; font-weight: 500; }

        /* ── Filter bar ── */
        .filter-bar { display: flex; flex-wrap: wrap; align-items: center; gap: 10px; margin-bottom: 20px; }
        .filter-form { display: flex; flex-wrap: wrap; gap: 8px; flex: 1; }
        .input-style { padding: 9px 12px; border: 1px solid #ccc; border-radius: 6px; font-size: 14px; background: #fff; }
        .filter-form select,
        .filter-form input[type="search"] { min-width: 120px; flex: 1 1 120px; }

        /* ── Buttons ── */
        .confirm-btn {
            background: #0b5d27; color: #fff; padding: 9px 16px;
            border-radius: 6px; border: none; cursor: pointer;
            font-weight: 600; font-size: 14px; white-space: nowrap;
        }
        .confirm-btn:hover { background: #0a4f22; }
        .btn-danger  { background: crimson; }
        .btn-danger:hover  { background: #b71c1c; }
        .btn-success { background: #28a745; }
        .btn-success:hover { background: #1e7e34; }

        /* ── Table ── */
        .table-container { overflow-x: auto; -webkit-overflow-scrolling: touch; border-radius: 8px; box-shadow: 0 2px 8px rgba(0,0,0,0.07); }
        .table { width: 100%; min-width: 650px; border-collapse: collapse; background: white; }
        .table th { background: darkgreen; color: white; padding: 12px 14px; text-align: center; font-size: 14px; white-space: nowrap; }
        .table td { padding: 11px 14px; border: 1px solid #e0e0e0; text-align: center; font-size: 14px; vertical-align: middle; }
        .table tbody tr:hover { background: #f9fbe7; }

        .status-badge { display: inline-block; padding: 4px 10px; border-radius: 5px; font-size: 12px; font-weight: 600; color: white; white-space: nowrap; }
        .status-Pending         { background: orange; }
        .status-Requires_Action { background: #dc3545; }

        .action-btns { display: flex; gap: 6px; justify-content: center; }
        .action-btns .confirm-btn { padding: 6px 12px; font-size: 15px; }

        /* ── Pagination ── */
        .pagination { display: flex; flex-wrap: wrap; gap: 5px; justify-content: center; margin-top: 20px; }
        .page-link { padding: 7px 13px; border: 1px solid #ddd; border-radius: 4px; text-decoration: none; color: #333; font-size: 14px; }
        .page-link.active { background: darkgreen; color: white; border-color: darkgreen; }

        /* ── Manage cards ── */
        .card-management { background: #fff; padding: 24px; border-radius: 12px; border: 1px solid #ddd; margin-bottom: 24px; box-shadow: 0 4px 6px rgba(0,0,0,0.05); }
        .card-management h3 { margin-top: 0; color: #0b4e12; font-size: 17px; }
        .form-row-manage { display: flex; gap: 12px; align-items: flex-end; flex-wrap: wrap; }
        .form-group-manage { display: flex; flex-direction: column; flex: 1; min-width: 180px; }
        .form-group-manage label { font-weight: 600; margin-bottom: 5px; color: #333; font-size: 13px; }
        .delete-link { color: #dc3545; text-decoration: none; font-weight: 700; font-size: 13px; }

        /* ── MODAL ── */
        .modal-overlay {
            display: none;
            position: fixed;
            inset: 0;
            z-index: 9999;
            background: rgba(0,0,0,0.55);
            overflow-y: auto;
            padding: 20px;
            box-sizing: border-box;
        }
        .modal-box {
            background: white;
            margin: 0 auto;
            padding: 28px;
            width: 100%;
            max-width: 520px;
            border-radius: 12px;
            box-shadow: 0 8px 24px rgba(0,0,0,0.18);
            position: relative;
        }
        .modal-box h3 { margin-top: 0; color: #dc3545; }
        .modal-field { margin-bottom: 16px; }
        .modal-field label { display: block; font-weight: 600; margin-bottom: 7px; color: #333; font-size: 14px; }
        .modal-field select,
        .modal-field textarea { width: 100%; box-sizing: border-box; padding: 10px 12px; border-radius: 6px; border: 1px solid #ccc; font-size: 14px; }
        .modal-field textarea { height: 80px; resize: vertical; }
        .modal-info { display: none; padding: 11px 14px; border-radius: 6px; font-size: 13px; margin-bottom: 14px; }
        .modal-info.reupload   { background: #e8f5e9; border-left: 4px solid #4caf50; color: #2e7d32; }
        .modal-info.noreupload { background: #fff3cd; border-left: 4px solid #ffc107; color: #856404; }
        .modal-warning { display: none; background: #ffebee; color: #c62828; padding: 11px 14px; border-radius: 6px; font-size: 13px; margin-bottom: 14px; border-left: 4px solid #c62828; }
        .modal-footer { display: flex; justify-content: flex-end; gap: 10px; flex-wrap: wrap; }
        .btn-cancel { padding: 10px 20px; border: none; background: #e0e0e0; border-radius: 6px; cursor: pointer; font-weight: 600; font-size: 14px; }
        .btn-cancel:hover { background: #ccc; }

        /* Keep sidebar behind modal */
        nav { z-index: 100 !important; }

        /* ── Mobile ── */
        @media (max-width: 600px) {
            .home { padding: 14px; }
            .dashboard-header h1 { font-size: 22px; }
            .filter-form { flex-direction: column; }
            .filter-form select,
            .filter-form input[type="search"] { width: 100%; }
            .filter-bar .bulk-actions { width: 100%; }
            .filter-bar .bulk-actions .confirm-btn { width: 100%; }
            .modal-footer { flex-direction: column; }
            .modal-footer button { width: 100%; }
        }
        .welcome-modal { display:none; position:fixed; z-index:9999; left:0; top:0; width:100%; height:100%; background:rgba(0,0,0,0.6); justify-content:center; align-items:center; }
.welcome-modal-content { background:#fff; padding:30px; border-radius:14px; width:90%; max-width:480px; text-align:center; box-shadow:0 10px 30px rgba(0,0,0,0.3); }
.welcome-modal-content h3 { color:darkgreen; margin-bottom:10px; }
.welcome-modal-content p { color:#444; font-size:14px; margin-bottom:8px; }
.welcome-modal-btns { display:flex; gap:10px; margin-top:20px; justify-content:center; }
.wm-btn-later { background:#ddd; color:#333; border:none; padding:10px 20px; border-radius:8px; cursor:pointer; font-weight:600; }
.wm-btn-now { background:darkgreen; color:#fff; border:none; padding:10px 20px; border-radius:8px; cursor:pointer; font-weight:600; }
.wm-btn-later:hover { background:#bbb; }
.wm-btn-now:hover { background:#0b4e12; }
    </style>
</head>
<body>
    <?php include 'sidebar_signa.php'; ?>

    <section class="home">

    <?php if ($view === 'manage'): ?>
    <!-- ===================== MANAGE REQUIREMENTS ===================== -->
    <div class="dashboard-header">
        <h1><i class='bx bx-cog'></i> Manage Requirements</h1>
        <p style="margin:6px 0 0; color:#555; font-size:14px;">Add to library and assign requirements to specific courses.</p>
    </div>

    <div class="card-management">
        <h3><i class='bx bx-library'></i> Requirement Library</h3>
        <form method="POST">
            <input type="hidden" name="action" value="add_library">
            <div class="form-row-manage">
                <div class="form-group-manage">
                    <label>New Requirement Name</label>
                    <input type="text" name="new_requirement_name" class="input-style" placeholder="e.g. Lab Clearance" required>
                </div>
                <button type="submit" class="confirm-btn" style="height:40px;">Add to Library</button>
            </div>
        </form>
    </div>

    <div class="card-management">
        <h3><i class='bx bx-plus-circle'></i> Assign Requirement to Course</h3>
        <form method="POST">
            <input type="hidden" name="action" value="assign_requirement">
            <div class="form-row-manage">
                <div class="form-group-manage">
                    <label>Select Requirement</label>
                    <select name="requirement_id" class="input-style" required>
                        <option value="">-- Select from Library --</option>
                        <?php $lib = $conn->query("SELECT * FROM requirement_library ORDER BY requirement_name ASC");
                        while ($l = $lib->fetch_assoc()): ?>
                        <option value="<?= $l['id'] ?>"><?= htmlspecialchars($l['requirement_name']) ?></option>
                        <?php endwhile; ?>
                    </select>
                </div>
                <div class="form-group-manage">
                    <label>Target Course</label>
                    <select name="course_id" class="input-style" required>
                        <option value="">-- Select Course --</option>
                        <?php $crs = $conn->query("SELECT * FROM courses ORDER BY course_name ASC");
                        while ($c = $crs->fetch_assoc()): ?>
                        <option value="<?= $c['id'] ?>"><?= htmlspecialchars($c['course_name']) ?></option>
                        <?php endwhile; ?>
                    </select>
                </div>
                <div class="form-group-manage">
                    <label>Document Type</label>
                    <select name="document_type_id" class="input-style" required>
                        <?php $dts = $conn->query("SELECT * FROM document_types ORDER BY type_name ASC");
                        while ($dt = $dts->fetch_assoc()): ?>
                        <option value="<?= $dt['id'] ?>"><?= htmlspecialchars($dt['type_name']) ?></option>
                        <?php endwhile; ?>
                    </select>
                </div>
                <button type="submit" class="confirm-btn" style="height:40px;">Assign Requirement</button>
            </div>
        </form>
    </div>

    <div class="card-management">
        <h3>Current Assignments</h3>
        <div class="table-container">
            <table class="table">
                <thead>
                    <tr><th>Requirement</th><th>Course</th><th>Type</th><th>Action</th></tr>
                </thead>
                <tbody>
                    <?php
                    $stmt_list = $conn->prepare(
                        "SELECT cr.id, rl.requirement_name, c.course_name, dt.type_name
                         FROM course_requirements cr
                         JOIN requirement_library rl ON cr.requirement_id = rl.id
                         JOIN courses c             ON cr.course_id        = c.id
                         JOIN document_types dt     ON cr.document_type_id = dt.id
                         WHERE cr.signatory_id = ?"
                    );
                    $stmt_list->bind_param("i", $current_sig_id);
                    $stmt_list->execute();
                    $res_list = $stmt_list->get_result();
                    if ($res_list->num_rows > 0):
                        while ($row = $res_list->fetch_assoc()): ?>
                    <tr>
                        <td><?= htmlspecialchars($row['requirement_name']) ?></td>
                        <td><?= htmlspecialchars($row['course_name']) ?></td>
                        <td><?= htmlspecialchars($row['type_name']) ?></td>
                        <td>
                            <a href="?view=manage&delete_assign=<?= $row['id'] ?>" class="delete-link"
                               onclick="return confirm('Remove this assignment?')">Remove</a>
                        </td>
                    </tr>
                    <?php endwhile; else: ?>
                    <tr><td colspan="4" style="color:#888;">No requirements assigned yet.</td></tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>

    <?php else: ?>
    <!-- ===================== MAIN DASHBOARD ===================== -->
    <div class="dashboard-header">
        <h1><?= htmlspecialchars($signatoryFullName) ?></h1>
        <div>
            <span class="badge-info">
                <i class='bx bx-id-card'></i>
                <?= htmlspecialchars($signatoryInfo['signatory_type'] ?? 'Signatory') ?>
            </span>
            <?php if (!empty($signatoryInfo['department']) && $signatoryInfo['signatory_type'] !== 'Class Adviser'): ?>
            <span class="badge-info">
                <i class='bx bx-buildings'></i>
                <?= htmlspecialchars($signatoryInfo['department']) ?>
            </span>
            <?php endif; ?>
                        <?php if (!empty($signatoryInfo['section'])): 
                $sectionParts = array_map('trim', explode(',', $signatoryInfo['section']));
                $courseGroups = [];
                foreach ($sectionParts as $part) {
                    $bits = array_map('trim', explode('|', $part));
                    if (count($bits) === 3) {
                        [$course, $year, $sec] = $bits;
                        $courseGroups[$course][] = $year . ' Sec ' . $sec;
                    } elseif (count($bits) === 2) {
                        [$year, $sec] = $bits;
                        $courseGroups[''][] = $year . ' Sec ' . $sec;
                    } else {
                        $courseGroups[''][] = $part;
                    }
                }
            ?>
            <?php foreach ($courseGroups as $course => $sections): ?>
            <span class="badge-info">
                <i class='bx bx-group'></i>
                <?php if ($course !== ''): ?>
                <strong><?= htmlspecialchars($course) ?>:</strong>
                <?php endif; ?>
                <?= htmlspecialchars(implode(', ', $sections)) ?>
            </span>
            <?php endforeach; ?>
            <?php endif; ?>
        </div>
    </div>

    <?php if ($alert_message): ?>
    <div class="alert-box" style="background:<?= $alert_bg ?>; color:<?= $alert_color ?>;">
        <?= htmlspecialchars($alert_message) ?>
    </div>
    <?php endif; ?>

    <!-- Filter Bar -->
    <div class="filter-bar">
        <form method="get" class="filter-form">
            <input type="hidden" name="view" value="dashboard">

            <select name="course" onchange="this.form.submit()" class="input-style">
    <option value="">All Courses</option>
    <?php foreach ($courses_array as $c): ?>
    <option value="<?= htmlspecialchars($c['course']) ?>" <?= $courseFilter === $c['course'] ? 'selected' : '' ?>>
        <?= htmlspecialchars($c['course']) ?>
    </option>
    <?php endforeach; ?>
</select>

            <select name="year" onchange="this.form.submit()" class="input-style">
    <option value="">All Years</option>
    <?php foreach ($years_array as $y): ?>
    <option value="<?= htmlspecialchars($y['year']) ?>" <?= $yearFilter === $y['year'] ? 'selected' : '' ?>>
        <?= htmlspecialchars($y['year']) ?>
    </option>
    <?php endforeach; ?>
</select>

        <select name="section" onchange="this.form.submit()" class="input-style">
    <option value="">All Sections</option>
    <?php foreach ($sections_array as $s): ?>
    <option value="<?= htmlspecialchars($s['section']) ?>" <?= $sectionFilter === $s['section'] ? 'selected' : '' ?>>
        <?= htmlspecialchars($s['section']) ?>
    </option>
    <?php endforeach; ?>
</select>

            <input type="search" name="search" placeholder="Search student..."
                   value="<?= htmlspecialchars($search) ?>" class="input-style">
            <button type="submit" class="confirm-btn">Search</button>
        </form>

        <div class="bulk-actions">
            <button type="button" class="confirm-btn btn-success" id="bulkApproveBtn" onclick="confirmBulkApprove()" style="display:none;">
                <i class='bx bx-check-double'></i> Approve Selected (<span id="selectedCount">0</span>)
            </button>
        </div>
    </div>

    <!-- Applications Table -->
    <div class="table-container">
        <table class="table">
           <thead>
                <tr>
                    <th>Student Name</th>
                    <th>Student ID</th>
                    <th>Year &amp; Section</th>
                    <th>Course</th>
                    <th>File</th>
                    <th>Rejections</th>
                    <th>Status</th>
                    <th>Action</th>
<th style="white-space:nowrap;">
    <div style="font-size:11px; margin-bottom:3px;">All</div>
    <input type="checkbox" id="selectAll" title="Select All" style="width:16px;height:16px;cursor:pointer;">
</th>                </tr>
            </thead>
            <tbody>
                <?php if (count($rows) > 0): foreach ($rows as $row):
                    $doc_path     = 'uploads/' . rawurlencode($row['document']);
                    $status_class = str_replace(' ', '_', $row['status']);
                ?>
                <tr>
                    <td style="text-align:left; font-weight:600;">
                        <?= htmlspecialchars($row['full_name'] ?? $row['username']) ?>
                    </td>
                    <td><?= htmlspecialchars(!empty($row['student_id']) ? $row['student_id'] : $row['username']) ?></td>
                    <td><?= htmlspecialchars($row['year'] . ' - ' . $row['section']) ?></td>
                    <td><?= htmlspecialchars($row['course']) ?></td>
                    <td>
                        <a href="<?= $doc_path ?>" target="_blank" style="color:#007bff; text-decoration:none;">
                            <i class='bx bx-file'></i> View File
                        </a>
                    </td>
                    <td><span style="color:red; font-weight:700;"><?= (int)$row['rejection_count'] ?></span></td>
                    <td>
                        <span class="status-badge status-<?= htmlspecialchars($status_class) ?>">
                            <?= htmlspecialchars($row['status']) ?>
                        </span>
                    </td>
                    <td>
                        <?php if ($row['status'] === 'Requires Action'): ?>
                        <span style="
                            display:inline-block;
                            background:#fff3cd;
                            color:#856404;
                            border:1px solid #ffc107;
                            border-radius:6px;
                            padding:6px 10px;
                            font-size:12px;
                            font-weight:600;
                            white-space:nowrap;">
                            ⏳ Awaiting Resubmission
                        </span>
                        <?php else: ?>
                        <div class="action-btns">
                            <button type="button" class="confirm-btn"
                                    onclick="confirmApprove(<?= (int)$row['id'] ?>)"
                                    title="Approve">✅</button>
                            <button type="button" class="confirm-btn btn-danger"
                                    onclick="openRejectModal(<?= (int)$row['id'] ?>, '<?= htmlspecialchars(addslashes($row['username'])) ?>', <?= (int)$row['rejection_count'] ?>)"
                                    title="Return for Revision">❌</button>
                        </div>
                        <?php endif; ?>
                    </td>
                    <td>
                        <?php if ($row['status'] === 'Pending'): ?>
                        <input type="checkbox" class="row-checkbox" value="<?= (int)$row['id'] ?>"
                               style="width:16px;height:16px;cursor:pointer;" onchange="updateBulkBtn()">
                        <?php endif; ?>
                    </td>
                </tr>
                <?php endforeach; else: ?>
                <tr>
                    <td colspan="9" style="color:#888; padding:20px;">No pending applications matching your criteria.</td>
                </tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>

    <!-- Pagination -->
    <?php if ($pages > 1): ?>
    <div class="pagination">
        <?php for ($i = 1; $i <= $pages; $i++): ?>
        <a href="?view=dashboard&page=<?= $i ?>&course=<?= urlencode($courseFilter) ?>&year=<?= urlencode($yearFilter) ?>&section=<?= urlencode($sectionFilter) ?>&search=<?= urlencode($search) ?>"
           class="page-link <?= $i === $page ? 'active' : '' ?>"><?= $i ?></a>
        <?php endfor; ?>
    </div>
    <?php endif; ?>

    <?php endif; ?>
    </section>

    <!-- ===================== REJECT MODAL ===================== -->
    <div id="rejectModal" class="modal-overlay">
        <div class="modal-box">
            <h3>Return Application for Revision</h3>
            <form method="post" action="signatory_action.php" id="rejectForm">
                <input type="hidden" name="id"     id="rejectId">
                <input type="hidden" name="reject" value="1">

                <p style="font-size:13px; color:#666; margin:0 0 16px;">Select the reason for returning this application:</p>

                <div class="modal-field">
                    <label>Reason Category</label>
                    <select id="reasonCategory">
                        <option value="">-- Select Category --</option>
                        <?php foreach ($categories_array as $cat): ?>
                        <option value="<?= htmlspecialchars($cat) ?>"><?= htmlspecialchars($cat) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="modal-field">
                    <label>Specific Reason</label>
                    <select name="reason" id="reasonSelect" required>
                        <option value="">-- Select a category first --</option>
                    </select>
                </div>

                <div id="requiresReuploadInfo" class="modal-info reupload">
                    <strong>📄 File Re-upload Required:</strong> Student will need to upload a corrected document.
                </div>
                

                <div class="modal-field">
                    <label>Additional Notes (Optional)</label>
                    <textarea name="additional_notes" id="additionalNotes"
                              placeholder="Add any specific instructions or clarifications..."></textarea>
                </div>

                <div id="rejectionWarning" class="modal-warning">
                    <strong>⚠️ Warning:</strong> This student has already been rejected 3+ times.
                </div>

               <!-- Add custom reason inline -->
                <div id="addReasonPanel" style="display:none; background:#f9f9f9; border:1px solid #ddd; border-radius:8px; padding:14px; margin-bottom:14px;">
                    <p style="margin:0 0 10px; font-weight:600; color:#333; font-size:14px;"><i class='bx bx-plus-circle'></i> Add New Reason</p>
                    <div style="display:flex; flex-wrap:wrap; gap:8px; margin-bottom:8px;">
                        <input type="text" id="newReasonCategory" placeholder="Category (e.g. Document Issues)"
                               style="flex:1; min-width:140px; padding:8px 10px; border:1px solid #ccc; border-radius:5px; font-size:13px;">
                        <input type="text" id="newReasonText" placeholder="Reason text"
                               style="flex:2; min-width:180px; padding:8px 10px; border:1px solid #ccc; border-radius:5px; font-size:13px;">
                    </div>
                    <div style="display:flex; align-items:center; gap:10px; flex-wrap:wrap;">
                        <label style="font-size:13px; display:flex; align-items:center; gap:5px; cursor:pointer;">
                            <input type="checkbox" id="newReasonReupload"> Requires file re-upload
                        </label>
                        <button type="button" class="confirm-btn btn-success" style="padding:7px 14px; font-size:13px;" onclick="saveNewReason()">
                            Save Reason
                        </button>
                        <button type="button" class="btn-cancel" style="padding:7px 14px; font-size:13px;" onclick="toggleAddReasonPanel()">
                            Cancel
                        </button>
                    </div>
                    <p id="addReasonMsg" style="margin:8px 0 0; font-size:12px; color:green; display:none;"></p>
                </div>

                <div class="modal-footer">
                    <button type="button" class="btn-cancel" onclick="closeRejectModal()">Cancel</button>
                    <button type="button" class="confirm-btn" style="background:#6c757d; padding:9px 14px; font-size:13px;" onclick="toggleAddReasonPanel()">
                        <i class='bx bx-plus'></i> Add Reason
                    </button>
                    <button type="submit" class="confirm-btn btn-danger" id="rejectSubmitBtn">Return for Revision</button>
                </div>
            </form>
        </div>
    </div>
    
    <?php if ($password_not_changed): ?>
<div class="welcome-modal" id="forcePasswordModal" style="display:flex; z-index:99999;">
    <div class="welcome-modal-content">
        <i class='bx bx-lock-alt' style="font-size:48px; color:darkgreen; margin-bottom:10px;"></i>
        <h3>Action Required: Change Your Password</h3>
        <p style="color:#856404; font-weight:600; background:#fff3cd; padding:10px; border-radius:8px; border:1px solid #ffc107;">
            ⚠️ You are using the default system password. You must change it before continuing.
        </p>
        <p>Please set a new personal password now to secure your account.</p>
        <div class="welcome-modal-btns">
            <button class="wm-btn-later" onclick="window.location.href='logout.php'">Logout</button>
            <button class="wm-btn-now" onclick="window.location.href='signatory_profile.php'">Change Password →</button>
        </div>
    </div>
</div>
<?php endif; ?>
<!-- EMAIL VERIFICATION REMINDER MODAL -->
<?php if ($email_not_verified): ?>
<div class="welcome-modal" id="emailReminderModal">
    <div class="welcome-modal-content">
        <i class='bx bx-envelope' style="font-size:48px; color:darkgreen; margin-bottom:10px;"></i>
        <h3>Hello, <?= htmlspecialchars($signatoryFullName) ?>! 👋</h3>
        <p style="color:#856404; font-weight:600; background:#fff3cd; padding:10px; border-radius:8px; border:1px solid #ffc107;">
            ⚠️ Your email address has not been verified yet.
        </p>
        <p>Please head to your profile to verify your email address to ensure you receive important notifications.</p>
        <p style="color:#888; font-size:13px;">You may also update your password while you're there.</p>
        <div class="welcome-modal-btns">
            <button class="wm-btn-later" onclick="closeEmailReminder()">Do Later</button>
            <button class="wm-btn-now" onclick="window.location.href='signatory_profile.php'">Go to Profile →</button>
        </div>
    </div>
</div>

<script>
function closeEmailReminder() {
    document.getElementById('emailReminderModal').style.display = 'none';
}
<?php if ($email_not_verified && !$password_not_changed): ?>
window.addEventListener('load', function() {
    document.getElementById('emailReminderModal').style.display = 'flex';
});
<?php endif; ?>
</script>
<?php endif; ?>
    <script>
        
        // Sidebar toggle
        const sidebar = document.querySelector('nav');
        const toggle  = document.querySelector('.toggle');
        if (toggle && sidebar) {
            toggle.addEventListener('click', () => sidebar.classList.toggle('close'));
        }

        // Rejection reasons
        const reasonsData    = <?= $reasons_json ?>;
        const categorySelect = document.getElementById('reasonCategory');
        const reasonSelect   = document.getElementById('reasonSelect');
        const reuploadInfo   = document.getElementById('requiresReuploadInfo');

        categorySelect.addEventListener('change', function () {
            const sel = this.value;
            reasonSelect.innerHTML = '<option value="">-- Select a reason --</option>';
            reuploadInfo.style.display = 'none';
            if (sel) {
                reasonsData.filter(r => r.category === sel).forEach(r => {
                    const opt = document.createElement('option');
                    opt.value = r.reason_text;
                    opt.textContent = r.reason_text;
                    opt.dataset.requiresReupload = r.requires_reupload;
                    reasonSelect.appendChild(opt);
                });
            }
        });

        reasonSelect.addEventListener('change', function () {
            const opt = this.selectedOptions[0];
            reuploadInfo.style.display = 'none';
            if (opt && opt.value !== '' && opt.dataset.requiresReupload == '1') {
                reuploadInfo.style.display = 'block';
            }
        });


        function openRejectModal(id, name, count) {
            document.getElementById('rejectId').value = id;
            document.getElementById('rejectForm').reset();
            categorySelect.value = '';
            reasonSelect.innerHTML = '<option value="">-- Select a category first --</option>';
            reuploadInfo.style.display = 'none';
            document.getElementById('rejectionWarning').style.display = count >= 3 ? 'block' : 'none';
            document.getElementById('rejectModal').style.display = 'block';

            // If this is the 3rd rejection (meaning next = 4th = final), change button label
            const submitBtn = document.getElementById('rejectSubmitBtn');
            if (count >= 3) {
                submitBtn.textContent = '⛔ Reject Permanently';
                submitBtn.style.background = '#7b0000';
            } else {
                submitBtn.textContent = 'Return for Revision';
                submitBtn.style.background = '';
            }
        }

        function closeRejectModal() {
            document.getElementById('rejectModal').style.display = 'none';
        }

        // Click outside modal to close
        document.getElementById('rejectModal').addEventListener('click', function (e) {
            if (e.target === this) closeRejectModal();
        });

        function confirmApprove(id) {
            if (!confirm('Approve this application?')) return;
            const f = document.createElement('form');
            f.method = 'POST'; f.action = 'signatory_action.php';
            const i1 = document.createElement('input'); i1.type = 'hidden'; i1.name = 'id';      i1.value = id;  f.appendChild(i1);
            const i2 = document.createElement('input'); i2.type = 'hidden'; i2.name = 'approve'; i2.value = '1'; f.appendChild(i2);
            document.body.appendChild(f); f.submit();
        }

        function updateBulkBtn() {
            const checked = document.querySelectorAll('.row-checkbox:checked');
            const btn     = document.getElementById('bulkApproveBtn');
            const counter = document.getElementById('selectedCount');
            counter.textContent = checked.length;
            btn.style.display   = checked.length > 0 ? 'inline-flex' : 'none';

            // Sync the "select all" checkbox state
            const all = document.querySelectorAll('.row-checkbox');
            const selectAllCb = document.getElementById('selectAll');
            if (selectAllCb) {
                selectAllCb.indeterminate = checked.length > 0 && checked.length < all.length;
                selectAllCb.checked       = all.length > 0 && checked.length === all.length;
            }
        }

        document.addEventListener('DOMContentLoaded', function () {
            const selectAllCb = document.getElementById('selectAll');
            if (selectAllCb) {
                selectAllCb.addEventListener('change', function () {
                    document.querySelectorAll('.row-checkbox').forEach(cb => cb.checked = this.checked);
                    updateBulkBtn();
                });
            }
        });

        function confirmBulkApprove() {
            const checked = document.querySelectorAll('.row-checkbox:checked');
            if (checked.length === 0) return;
            if (!confirm('Approve ' + checked.length + ' selected application(s)?')) return;

            const f = document.createElement('form');
            f.method = 'POST'; f.action = 'signatory_action.php';

            const i = document.createElement('input');
            i.type = 'hidden'; i.name = 'bulk_approve'; i.value = '1';
            f.appendChild(i);

            checked.forEach(cb => {
                const inp = document.createElement('input');
                inp.type  = 'hidden';
                inp.name  = 'bulk_ids[]';
                inp.value = cb.value;
                f.appendChild(inp);
            });

            document.body.appendChild(f);
            f.submit();
        }

       function confirmLogout() {
            if (confirm('Are you sure you want to logout?')) window.location.href = 'logout.php';
        }

        // ── Add custom rejection reason ──
        function toggleAddReasonPanel() {
            const panel = document.getElementById('addReasonPanel');
            panel.style.display = panel.style.display === 'none' ? 'block' : 'none';
            document.getElementById('newReasonCategory').value = '';
            document.getElementById('newReasonText').value     = '';
            document.getElementById('newReasonReupload').checked = false;
            document.getElementById('addReasonMsg').style.display = 'none';
        }

        function saveNewReason() {
            const category   = document.getElementById('newReasonCategory').value.trim();
            const reasonText = document.getElementById('newReasonText').value.trim();
            const reupload   = document.getElementById('newReasonReupload').checked ? 1 : 0;
            const msgEl      = document.getElementById('addReasonMsg');

            if (!category || !reasonText) {
                msgEl.style.color   = 'red';
                msgEl.textContent   = 'Please fill in both the category and reason text.';
                msgEl.style.display = 'block';
                return;
            }

            fetch('save_rejection_reason.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ category, reason_text: reasonText, requires_reupload: reupload })
            })
            .then(r => r.json())
            .then(data => {
                if (data.success) {
                    // Add to live reasonsData so it's usable immediately without reload
                    reasonsData.push({
                        id: data.id,
                        reason_text: reasonText,
                        category: category,
                        requires_reupload: reupload
                    });

                    // If the category dropdown doesn't have this category yet, add it
                    const exists = Array.from(categorySelect.options).some(o => o.value === category);
                    if (!exists) {
                        const opt = document.createElement('option');
                        opt.value = category;
                        opt.textContent = category;
                        categorySelect.appendChild(opt);
                    }

                    msgEl.style.color   = 'green';
                    msgEl.textContent   = '✅ Reason saved! You can now select it from the dropdowns.';
                    msgEl.style.display = 'block';

                    document.getElementById('newReasonCategory').value   = '';
                    document.getElementById('newReasonText').value       = '';
                    document.getElementById('newReasonReupload').checked = false;
                } else {
                    msgEl.style.color   = 'red';
                    msgEl.textContent   = 'Error: ' + data.message;
                    msgEl.style.display = 'block';
                }
            })
            .catch(() => {
                msgEl.style.color   = 'red';
                msgEl.textContent   = 'Failed to save. Please try again.';
                msgEl.style.display = 'block';
            });
        }
    </script>
</body>
</html>