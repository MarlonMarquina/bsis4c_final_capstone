<?php
// FILE: manage_applications.php
include 'conn.php';
session_start();

if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
    header("Location: login.php");
    exit();
}

function e($s){ return htmlspecialchars($s ?? '', ENT_QUOTES, 'UTF-8'); }

$filter_course_id = intval($_GET['course_filter'] ?? 0);
$where_clause = ($filter_course_id > 0) ? " AND c.id = {$filter_course_id} " : "";

// --- POST HANDLERS ---
if (($_POST['action'] ?? null) === 'assign_signatory') {
    $course_id = intval($_POST['course_id'] ?? 0);
    $signatory_id = intval($_POST['signatory_id'] ?? 0);

    if ($course_id > 0 && $signatory_id > 0) {
        $dup = $conn->prepare("SELECT id FROM course_requirements WHERE course_id=? AND signatory_id=?");
        $dup->bind_param("ii", $course_id, $signatory_id);
        $dup->execute();
        if ($dup->get_result()->num_rows == 0) {
            // Admin creates the slot. requirements_configured=0 means signatory hasn't set up yet.
            // Student will NOT see this signatory until signatory configures in requirementssigna.php
            $stmt = $conn->prepare("INSERT INTO course_requirements (course_id, signatory_id, requirements_configured) VALUES (?, ?, 0)");
            $stmt->bind_param("ii", $course_id, $signatory_id); 
            $stmt->execute();
            $stmt->close();
        }
        $dup->close();
    }
    header("Location: manage_applications.php?course_filter={$course_id}");
    exit();
}

if (isset($_GET['delete_assigned'])) {
    $del = intval($_GET['delete_assigned']);
    $redirect_cid = intval($_GET['redirect_cid'] ?? 0);
    if ($del > 0) {
        $stmt = $conn->prepare("DELETE FROM course_requirements WHERE id = ?");
        $stmt->bind_param("i", $del);
        $stmt->execute();
        $stmt->close();
    }
    header("Location: manage_applications.php?course_filter={$redirect_cid}");
    exit();
}
// --- PREREQUISITE HANDLERS ---
if (($_POST['action'] ?? null) === 'save_prerequisites') {
    $prereq_course_id = intval($_POST['prereq_course_id'] ?? 0);
    $signatory_type_target = trim($_POST['prereq_signatory_type'] ?? '');
    $selected_before_types = $_POST['before_types'] ?? [];

    if ($prereq_course_id > 0 && $signatory_type_target !== '') {
        // Delete existing rules for this course + signatory_type
        $del = $conn->prepare("DELETE FROM signatory_prerequisites WHERE course_id = ? AND signatory_type = ?");
        $del->bind_param("is", $prereq_course_id, $signatory_type_target);
        $del->execute();
        $del->close();

        // Insert new selected before_types
        foreach ($selected_before_types as $before_type) {
            $before_type = trim($before_type);
            if ($before_type === '' || $before_type === $signatory_type_target) continue;
            $ins = $conn->prepare("INSERT IGNORE INTO signatory_prerequisites (course_id, before_type, signatory_type, admin_enabled, signatory_enabled) VALUES (?, ?, ?, 1, 0)");
            $ins->bind_param("iss", $prereq_course_id, $before_type, $signatory_type_target);
            $ins->execute();
            $ins->close();
        }
    }
    header("Location: manage_applications.php?course_filter={$prereq_course_id}");
    exit();
}
// --- DATA FETCHING ---
$courses_res = $conn->query("SELECT id, course_name FROM courses ORDER BY course_name ASC");

$signatories_res = $conn->query("SELECT id, full_name, signatory_type, department FROM users WHERE role='signatory' ORDER BY full_name ASC");

$assigned_list = [];
$assigned_signatory_ids = [];

// Fetch manually assigned signatories (globals/others), grouped to avoid duplicates
$q = $conn->query("
    SELECT MIN(cr.id) AS assignment_id, cr.signatory_id, c.id AS course_id, c.course_name,
           u.full_name, u.signatory_type, u.department,
           MAX(cr.requirements_configured) AS is_configured,
           COUNT(cr.requirement_id) AS req_count
    FROM course_requirements cr
    JOIN users u ON cr.signatory_id = u.id
    JOIN courses c ON cr.course_id = c.id
    WHERE u.signatory_type NOT IN ('Class Adviser', 'Program Head')
    {$where_clause}
    GROUP BY cr.signatory_id, c.id
    ORDER BY c.course_name ASC, u.full_name ASC
");

if ($q) {
    while ($row = $q->fetch_assoc()) {
        $cid = intval($row['course_id']);
        $assigned_list[$cid][] = $row;
        $assigned_signatory_ids[$cid][] = intval($row['signatory_id']);
    }
}

// Fetch all courses for auto-inject lookups
$all_courses_res = $conn->query("SELECT id, course_name FROM courses ORDER BY course_name ASC");
$all_courses = [];
while ($c = $all_courses_res->fetch_assoc()) {
    $all_courses[$c['id']] = $c['course_name'];
}

// Fetch Program Heads (auto-shown per course by department match)
$program_heads = [];
$ph_res = $conn->query("SELECT id, full_name, signatory_type, department FROM users WHERE role='signatory' AND signatory_type='Program Head' AND status='active'");
while ($ph = $ph_res->fetch_assoc()) {
    $depts = array_map('trim', explode(',', strtolower($ph['department'] ?? '')));
    foreach ($all_courses as $cid => $cname) {
        foreach ($depts as $d) {
            if (!empty($d) && strtolower(trim($cname)) === $d) {
                // Check if configured
                $ph_cfg = $conn->query("SELECT MAX(requirements_configured) as cfg, COUNT(requirement_id) as cnt FROM course_requirements WHERE signatory_id = {$ph['id']} AND course_id = {$cid}");
                $ph_cfg_row = $ph_cfg->fetch_assoc();
                $program_heads[$cid][] = [
                    'full_name' => $ph['full_name'],
                    'signatory_type' => $ph['signatory_type'],
                    'department' => $ph['department'],
                    'is_configured' => (int)($ph_cfg_row['cfg'] ?? 0),
                    'req_count' => (int)($ph_cfg_row['cnt'] ?? 0),
                ];
                break;
            }
        }
    }
}

// Fetch Class Advisers (auto-shown per course by section match)
$class_advisers = [];
$ca_res = $conn->query("SELECT id, full_name, signatory_type, department, section FROM users WHERE role='signatory' AND signatory_type='Class Adviser' AND status='active'");
while ($ca = $ca_res->fetch_assoc()) {
    $classes = array_map('trim', explode(',', $ca['section'] ?? ''));
    $sections_per_course = [];

    foreach ($classes as $cls) {
        $parts = explode('|', $cls);
        if (count($parts) == 3) {
            $cls_course = trim($parts[0]);
            $cls_year = trim($parts[1]);
            $cls_section = trim($parts[2]);
            foreach ($all_courses as $cid => $cname) {
                if (strtolower($cname) === strtolower($cls_course)) {
                    $sections_per_course[$cid][] = $cls_year . ' - Section ' . $cls_section;
                }
            }
        }
    }

    foreach ($sections_per_course as $cid => $sections) {
        $ca_cfg = $conn->query("SELECT MAX(requirements_configured) as cfg, COUNT(requirement_id) as cnt FROM course_requirements WHERE signatory_id = {$ca['id']} AND course_id = {$cid}");
        $ca_cfg_row = $ca_cfg->fetch_assoc();
        $class_advisers[$cid][] = [
            'full_name' => $ca['full_name'],
            'signatory_type' => 'Class Adviser',
            'handled' => implode(', ', $sections),
            'is_configured' => (int)($ca_cfg_row['cfg'] ?? 0),
            'req_count' => (int)($ca_cfg_row['cnt'] ?? 0),
        ];
    }
}

/**
 * FIXED LOGIC:
 * Ginagamit na ngayon ang explode() para suportahan ang maraming department (e.g., "ACT, BSIS").
 */
function canAssignThisSignatory($s, $courseName, $alreadyAssignedIds) {
    // Check kung assigned na
    if (in_array(intval($s['id']), $alreadyAssignedIds)) return false;

    $type = strtolower($s['signatory_type'] ?? '');
    
    // I-explode ang department string (gawing array) para sa multiple departments
    $deptRaw = strtolower(trim($s['department'] ?? ''));
    $deptsArray = array_map('trim', explode(',', $deptRaw)); 
    
    $course = strtolower(trim($courseName));

    // Listahan ng Restricted Roles
    $restricted = ['program head', 'class adviser', 'department head'];
    
    $isRestricted = false;
    foreach($restricted as $r) {
        if(strpos($type, $r) !== false) {
            $isRestricted = true;
            break;
        }
    }

    if ($isRestricted) {
        if (empty($deptRaw)) return false;
        
        // I-check ang bawat department na nakalista sa user
        foreach ($deptsArray as $d) {
            if (empty($d)) continue;
            // Match kung exact match OR kung ang department code ay nasa loob ng course name
            if ($d === $course || strpos($course, $d) !== false) {
                return true;
            }
        }
        return false; 
    }

    // Global roles (Librarian, Registrar, etc.)
    return true;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <link rel="stylesheet" href="styles.css">
    <meta charset="utf-8">
    <title>Course Assignment | Smart Clearance</title>
    <link href='https://unpkg.com/boxicons@2.1.4/css/boxicons.min.css' rel='stylesheet'>
    <style>
        body { font-family: 'Poppins', sans-serif; background: #f4f7f6; margin: 0; padding: 0; }
.home {
    padding: 30px;
    min-height: 100vh;
    display: flex;
    flex-direction: column;
    align-items: center;
    position: relative;
    left: 250px;
    width: calc(100% - 250px);
    transition: left 0.3s ease, width 0.3s ease;
}

.sidebar.close ~ .home {
    left: 88px;
    width: calc(100% - 88px);
}
        .dashboard-container { width: 100%; max-width: 1100px; background: #fff; border-radius: 12px; padding: 30px; box-shadow: 0 5px 20px rgba(0,0,0,0.05); }
        .dashboard-header { background: linear-gradient(135deg, #2d5016 0%, #1a3409 100%); color: white; padding: 25px; border-radius: 12px; text-align: center; margin-bottom: 25px; width: 100%; max-width: 1100px; }
        .dashboard-header h2 { margin: 0; font-size: 24px; font-weight: 700; }
        .flex-between { display: flex; justify-content: space-between; align-items: center; margin-bottom: 25px; }
        select { padding: 12px; border-radius: 8px; border: 1px solid #ddd; min-width: 250px; outline: none; }
        .btn { padding: 10px 20px; border-radius: 8px; border: none; cursor: pointer; font-weight: 600; display: inline-flex; align-items: center; gap: 8px; text-decoration: none; transition: 0.3s; }
        .btn-primary { background: #2d5016; color: white; }
        .btn-danger { background: #dc3545; color: white; }
        .btn-danger:hover { background: #a71d2a; }
        table { width: 100%; border-collapse: collapse; }
        table th { background: #f8f9fa; color: #2d5016; padding: 15px; text-align: left; border-bottom: 2px solid #eee; font-size: 13px; }
        table td { padding: 15px; border-bottom: 1px solid #eee; font-size: 14px; }
        .course-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(250px, 1fr)); gap: 20px; width: 100%; }
        .course-card { background: #fff; border: 1px solid #eee; padding: 25px; border-radius: 12px; text-align: center; cursor: pointer; transition: 0.3s; }
        .course-card:hover { border-color: #2d5016; transform: translateY(-5px); box-shadow: 0 5px 15px rgba(0,0,0,0.05); }
        .course-card i { font-size: 40px; color: #2d5016; margin-bottom: 15px; }
        .assign-box { background: #f9f9f9; padding: 20px; border-radius: 10px; border: 1px solid #eee; margin-top: 30px; display: flex; justify-content: space-between; align-items: center; }
   .btn-prereq { background: #3730a3; color: white; border: none; padding: 8px 16px; border-radius: 8px; cursor: pointer; font-size: 13px; font-weight: 600; display: inline-flex; align-items: center; gap: 6px; }
.btn-prereq:hover { background: #312e81; }
.prereq-modal { display: none; position: fixed; z-index: 9999; left: 0; top: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.5); }
.prereq-modal-content { background: #fff; margin: 5% auto; padding: 30px; border-radius: 16px; width: 90%; max-width: 650px; max-height: 80vh; overflow-y: auto; box-shadow: 0 10px 30px rgba(0,0,0,0.2); }
.prereq-modal-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px; }
.prereq-modal-header h3 { color: #1e1b4b; font-size: 18px; font-weight: 700; }
.prereq-modal-close { font-size: 28px; color: #aaa; cursor: pointer; line-height: 1; }
.prereq-modal-close:hover { color: #000; }
.prereq-type-block { background: #f8f9fa; border: 1px solid #e3e8ee; border-radius: 10px; padding: 15px; margin-bottom: 15px; }
.prereq-type-block strong { color: #1e1b4b; font-size: 14px; display: block; margin-bottom: 10px; }
.prereq-checkbox-row { display: flex; align-items: center; gap: 10px; padding: 8px 0; font-size: 13px; color: #444; border-bottom: 1px solid #f0f0f0; }
.prereq-checkbox-row:last-child { border-bottom: none; }
.btn-prereq-save { background: #0b4e12; color: white; border: none; padding: 10px 24px; border-radius: 8px; cursor: pointer; font-size: 14px; font-weight: 600; margin-top: 15px; width: 100%; }
.btn-prereq-save:hover { background: #083a0d; }
        .prereq-section { background: #f0f4ff; border: 1px solid #c7d2fe; border-radius: 10px; padding: 20px; margin-top: 20px; }
.prereq-section h4 { color: #3730a3; margin-bottom: 15px; font-size: 15px; }
.prereq-grid { display: grid; gap: 15px; }
.prereq-signatory-block { background: #fff; border: 1px solid #e0e7ff; border-radius: 8px; padding: 15px; }
.prereq-signatory-block strong { color: #1e1b4b; font-size: 14px; display: block; margin-bottom: 10px; }
.prereq-checkbox-row { display: flex; align-items: center; gap: 10px; padding: 6px 0; font-size: 13px; color: #444; border-bottom: 1px solid #f0f0f0; }
.prereq-checkbox-row:last-child { border-bottom: none; }
.btn-prereq-save { background: #3730a3; color: white; border: none; padding: 8px 18px; border-radius: 6px; cursor: pointer; font-size: 13px; font-weight: 600; margin-top: 12px; }
.btn-prereq-save:hover { background: #312e81; }
    </style>
    <script>
const coursePrereqData = {};
</script>
</head>
<body>
<?php 
    $admin_user = $_SESSION['username'];
    $admin_stmt = $conn->prepare("SELECT full_name FROM users WHERE username = ? AND role = 'admin'");
    $admin_stmt->bind_param("s", $admin_user);
    $admin_stmt->execute();
    $admin_data = $admin_stmt->get_result()->fetch_assoc();
    $signatoryFullName = !empty($admin_data['full_name']) ? $admin_data['full_name'] : $admin_user;
    $userRole = "Administrator";
    include 'sidebar_admin.php'; 
?>

<section class="home">
    <div class="dashboard-header">
        <h2>COURSE ASSIGNMENT MANAGEMENT</h2>
    </div>

    <div class="dashboard-container">
        <div class="flex-between">
            <form method="GET">
                <select name="course_filter" onchange="this.form.submit()">
                    <option value="0">--- All Courses ---</option>
                    <?php $courses_res->data_seek(0); while($c = $courses_res->fetch_assoc()): ?>
                        <option value="<?= $c['id'] ?>" <?= $filter_course_id == $c['id'] ? 'selected' : '' ?>><?= e($c['course_name']) ?></option>
                    <?php endwhile; ?>
                </select>
            </form>
            <?php if($filter_course_id > 0): ?>
                <a href="manage_applications.php" class="btn btn-danger"><i class='bx bx-grid-alt'></i> Grid View</a>
            <?php endif; ?>
        </div>

        <?php if($filter_course_id == 0): ?>
            <div class="course-grid">
                <?php $courses_res->data_seek(0); while($course = $courses_res->fetch_assoc()): ?>
                    <div class="course-card" onclick="location.href='?course_filter=<?= $course['id'] ?>'">
                        <i class='bx bx-folder'></i>
                        <div style="font-weight: 700;"><?= e($course['course_name']) ?></div>
                        <div style="font-size: 12px; color: #777; margin-top: 5px;">
                            <?= count($assigned_list[$course['id']] ?? []) + count($program_heads[$course['id']] ?? []) + count($class_advisers[$course['id']] ?? []) ?> Assigned
                        </div>
                    </div>
                <?php endwhile; ?>
            </div>
        <?php else: ?>
            <?php 
            $courses_res->data_seek(0);
            while($course = $courses_res->fetch_assoc()):
                if($course['id'] != $filter_course_id) continue;
                $currentCourseName = $course['course_name'];
                $alreadyAssigned = $assigned_signatory_ids[$course['id']] ?? [];
            ?>
                <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:15px;">
    <h3 style="margin:0;">Selected: <?= e($course['course_name']) ?></h3>
    <button class="btn-prereq" onclick="openPrereqModal(<?= $course['id'] ?>, '<?= e($course['course_name']) ?>')">
        <i class='bx bx-sort-alt-2'></i> Set Prerequisites
    </button>
</div>
                <table>
    <thead>
        <tr>
            <th>Signatory Name</th>
            <th>Type</th>
            <th>Details</th>
            <th>Status</th>
            <th style="text-align: right;">Action</th>
        </tr>
    </thead>
    <tbody>
        <?php
        $has_rows = false;

        // Auto-shown: Program Heads
        if (!empty($program_heads[$course['id']])):
            foreach ($program_heads[$course['id']] as $ph):
                $has_rows = true;
        ?>
            <tr style="background:#f0fff4;">
                <td><strong><?= e($ph['full_name']) ?></strong></td>
                <td><span style="background:#c6f6d5; color:#276749; padding:3px 8px; border-radius:5px; font-size:12px;"><?= e($ph['signatory_type']) ?></span></td>
                <td style="font-size:12px; color:#555;"><?= e($ph['department']) ?></td>
                <td>
                    <?php if($ph['is_configured']): ?>
                        <span style="color:green; font-size:12px; font-weight:600;">✅ Configured (<?= $ph['req_count'] ?> req)</span>
                    <?php else: ?>
                        <span style="color:#856404; font-size:12px; font-weight:600;">⚠️ Not yet configured</span>
                    <?php endif; ?>
                </td>
                <td style="text-align:right;">
                    <span style="font-size:11px; color:#aaa; font-style:italic;">Auto-assigned</span>
                </td>
            </tr>
        <?php endforeach; endif; ?>

        <?php
        // Auto-shown: Class Advisers
        if (!empty($class_advisers[$course['id']])):
            foreach ($class_advisers[$course['id']] as $ca):
                $has_rows = true;
        ?>
            <tr style="background:#f0fff4;">
                <td><strong><?= e($ca['full_name']) ?></strong></td>
                <td><span style="background:#c6f6d5; color:#276749; padding:3px 8px; border-radius:5px; font-size:12px;">Class Adviser</span></td>
                <td style="font-size:12px; color:#555;"><?= e($ca['handled']) ?></td>
                <td>
                    <?php if($ca['is_configured']): ?>
                        <span style="color:green; font-size:12px; font-weight:600;">✅ Configured (<?= $ca['req_count'] ?> req)</span>
                    <?php else: ?>
                        <span style="color:#856404; font-size:12px; font-weight:600;">⚠️ Not yet configured</span>
                    <?php endif; ?>
                </td>
                <td style="text-align:right;">
                    <span style="font-size:11px; color:#aaa; font-style:italic;">Auto-assigned</span>
                </td>
            </tr>
        <?php endforeach; endif; ?>

        <?php
        // Manually assigned (globals/others) with delete button
        if (!empty($assigned_list[$course['id']])):
            foreach ($assigned_list[$course['id']] as $row):
                $has_rows = true;
        ?>
            <tr>
                <td><strong><?= e($row['full_name']) ?></strong></td>
                <td><span style="background:#e8f5e9; color:#2d5016; padding:3px 8px; border-radius:5px; font-size:12px;"><?= e($row['signatory_type']) ?></span></td>
                <td style="font-size:12px; color:#555;"><?= e($row['department'] ?: 'Global') ?></td>
                <td>
                    <?php if($row['is_configured']): ?>
                        <span style="color:green; font-size:12px; font-weight:600;">✅ Configured (<?= $row['req_count'] ?> req)</span>
                    <?php else: ?>
                        <span style="color:#856404; font-size:12px; font-weight:600;">⚠️ Not yet configured</span>
                    <?php endif; ?>
                </td>
                <td style="text-align: right;">
                    <a href="?delete_assigned=<?= $row['assignment_id'] ?>&redirect_cid=<?= $course['id'] ?>" class="btn btn-danger" style="padding: 5px 10px;" onclick="return confirm('Remove this signatory from this course?')"><i class='bx bx-trash'></i></a>
                </td>
            </tr>
        <?php endforeach; endif; ?>

        <?php if (!$has_rows): ?>
            <tr><td colspan="5" style="text-align:center; padding: 30px; color: #999;">No signatories for this course yet.</td></tr>
        <?php endif; ?>
    </tbody>
</table>

                <div class="assign-box">
                    <div style="font-weight: 600; color: #555;">Assign New Signatory</div>
                    <form method="POST" style="display: flex; gap: 10px;">
                        <input type="hidden" name="action" value="assign_signatory">
                        <input type="hidden" name="course_id" value="<?= $course['id'] ?>">
                        <select name="signatory_id" required style="min-width: 300px;">
                            <option value="">-- Choose Signatory --</option>
                            <?php 
                            $signatories_res->data_seek(0);
$found = false;
while($s = $signatories_res->fetch_assoc()):
    if (in_array($s['signatory_type'], ['Class Adviser', 'Program Head'])) continue;
    if(canAssignThisSignatory($s, $currentCourseName, $alreadyAssigned)):
        $found = true;
?>
    <option value="<?= $s['id'] ?>"><?= e($s['full_name']) ?> (<?= e($s['signatory_type']) ?>)</option>
<?php endif; endwhile; ?>
                            <?php if(!$found): ?>
                                <option disabled>No available signatories</option>
                            <?php endif; ?>
                        </select>
                        <button type="submit" class="btn btn-primary"><i class='bx bx-plus'></i> Assign to Course</button>
                    </form>
                </div>
<?php
// Collect distinct signatory types for this course
$types_for_course = [];

// From manually assigned
if (!empty($assigned_list[$course['id']])) {
    foreach ($assigned_list[$course['id']] as $row) {
        $t = trim($row['signatory_type']);
        if ($t && !in_array($t, $types_for_course)) $types_for_course[] = $t;
    }
}
// From Program Heads
if (!empty($program_heads[$course['id']])) {
    if (!in_array('Program Head', $types_for_course)) $types_for_course[] = 'Program Head';
}
// From Class Advisers
if (!empty($class_advisers[$course['id']])) {
    if (!in_array('Class Adviser', $types_for_course)) $types_for_course[] = 'Class Adviser';
}
// From manually assigned
if (!empty($assigned_list[$course['id']])) {
    foreach ($assigned_list[$course['id']] as $row) {
        $t = trim($row['signatory_type']);
        if ($t && !in_array($t, $types_for_course)) $types_for_course[] = $t;
    }
}

// Fetch existing prereq rules for this course
$existing_prereqs = [];
$ep_q = $conn->prepare("SELECT before_type, signatory_type FROM signatory_prerequisites WHERE course_id = ? AND admin_enabled = 1");
$ep_q->bind_param("i", $course['id']);
$ep_q->execute();
$ep_res = $ep_q->get_result();
while ($ep = $ep_res->fetch_assoc()) {
    $existing_prereqs[$ep['signatory_type']][] = $ep['before_type'];
}
$ep_q->close();

// Build JS data for modal
$modal_data_js = [];
$can_have_prereq = ['Class Adviser', 'Program Head', 'Student Government', 'Student Government (SG)', 'SG'];

// Define display order: Program Head → SG → Class Adviser
$prereq_order = ['Program Head', 'Student Government (SG)', 'Student Government', 'SG', 'Class Adviser'];

foreach ($types_for_course as $stype) {
    if (!in_array($stype, $can_have_prereq)) continue;
    $options = [];
    foreach ($types_for_course as $other) {
        if ($other === $stype) continue;
        $options[] = [
            'type' => $other,
            'checked' => isset($existing_prereqs[$stype]) && in_array($other, $existing_prereqs[$stype])
        ];
    }
    $modal_data_js[] = ['type' => $stype, 'options' => $options];
}

// Sort by preferred order
usort($modal_data_js, function($a, $b) use ($prereq_order) {
    $posA = array_search($a['type'], $prereq_order);
    $posB = array_search($b['type'], $prereq_order);
    $posA = ($posA === false) ? 999 : $posA;
    $posB = ($posB === false) ? 999 : $posB;
    return $posA - $posB;
});
?>
<script>
coursePrereqData[<?= $course['id'] ?>] = <?= json_encode($modal_data_js, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT) ?>;
console.log('Course <?= $course['id'] ?> types:', <?= json_encode($types_for_course) ?>);
console.log('Modal data:', coursePrereqData[<?= $course['id'] ?>]);
</script>
            <?php endwhile; ?>
        <?php endif; ?>
    </div>
</section>
<!-- PREREQUISITE MODAL -->
<div id="prereqModal" class="prereq-modal">
    <div class="prereq-modal-content">
        <div class="prereq-modal-header">
            <h3 id="prereqModalTitle">Set Prerequisites</h3>
            <span class="prereq-modal-close" onclick="closePrereqModal()">&times;</span>
        </div>
        <p style="font-size:13px; color:#555; margin-bottom:20px;">
            Select which signatory types must be fully cleared <strong>before</strong> students can comply with each type. Signatories can then enable/disable these on their end.
        </p>
        <div id="prereqModalBody"></div>
    </div>
</div>

<script>

function openPrereqModal(courseId, courseName) {
    document.getElementById('prereqModalTitle').textContent = 'Prerequisites — ' + courseName;
    const data = coursePrereqData[courseId] || [];
    let html = '';

    if (data.length < 2) {
        html = '<p style="color:#aaa; text-align:center; padding:20px;">Not enough signatory types assigned to set prerequisites.</p>';
    } else {
        data.forEach(function(sig) {
            if (sig.options.length === 0) return;
            html += '<div class="prereq-type-block">';
            html += '<strong>' + sig.type + ' must be preceded by:</strong>';
            html += '<form method="POST">';
            html += '<input type="hidden" name="action" value="save_prerequisites">';
            html += '<input type="hidden" name="prereq_course_id" value="' + courseId + '">';
            html += '<input type="hidden" name="prereq_signatory_type" value="' + sig.type + '">';
            sig.options.forEach(function(opt) {
    const checked = opt.checked ? 'checked' : '';
    // Check if the reverse relationship already exists (conflict lock)
    const reverseEntry = data.find(d => d.type === opt.type);
    const isConflict = reverseEntry && reverseEntry.options.some(o => o.type === sig.type && o.checked);
    const disabled = isConflict ? 'disabled' : '';
    const conflictNote = isConflict ? ' <span style="color:#e53e3e; font-size:11px;">(locked — reverse rule exists)</span>' : '';
    html += '<div class="prereq-checkbox-row">';
    html += '<input type="checkbox" name="before_types[]" value="' + opt.type + '" ' + checked + ' ' + disabled + '>';
    html += '<label style="' + (isConflict ? 'color:#aaa;' : '') + '">' + opt.type + conflictNote + '</label>';
    html += '</div>';
});
            html += '<button type="submit" class="btn-prereq-save">Save</button>';
            html += '</form>';
            html += '</div>';
        });
    }

    document.getElementById('prereqModalBody').innerHTML = html;
    document.getElementById('prereqModal').style.display = 'block';
}

function closePrereqModal() {
    document.getElementById('prereqModal').style.display = 'none';
}

window.addEventListener('click', function(e) {
    const modal = document.getElementById('prereqModal');
    if (e.target === modal) closePrereqModal();
});
</script>
</body>
</html>