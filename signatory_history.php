<?php
// signatory_history.php

session_start();
date_default_timezone_set('Asia/Manila');
if (!isset($_SESSION['role']) || $_SESSION['role'] != "signatory") {
    header("Location: login.php");
    exit();
}

include 'conn.php';

$signatory = $_SESSION['username'];

$sigQuery = $conn->prepare("SELECT full_name FROM users WHERE username = ? LIMIT 1");
$sigQuery->bind_param("s", $signatory);
$sigQuery->execute();
$sigRes = $sigQuery->get_result();
$sigData = $sigRes->fetch_assoc();
$signatoryFullName = $sigData['full_name'] ?? $signatory; 
$sigQuery->close();
$courseFilter = isset($_GET['course']) ? $_GET['course'] : '';
$yearFilter = isset($_GET['year']) ? $_GET['year'] : '';
$sectionFilter = isset($_GET['section']) ? $_GET['section'] : '';
$search = isset($_GET['search']) ? $_GET['search'] : '';
$page = isset($_GET['page']) ? max(1, (int)$_GET['page']) : 1;
$limit = 10;
$offset = ($page - 1) * $limit;

// --- Dynamic Binding Helper Function ---
function bind_param_array($stmt, $types, $params) {
    $bind_names[] = $types;
    for ($i=0; $i<count($params); $i++) {
        $bind_names[] = &$params[$i];
    }
    call_user_func_array(array($stmt, 'bind_param'), $bind_names);
}

// Include ALL completed statuses (Approved, Rejected)
$whereClauses = ["a.signatory = ?", "(a.status = 'Approved' OR a.status = 'Rejected' OR a.status = 'Totally Rejected' OR a.status = 'Requires Action' OR (a.status = 'Pending' AND a.rejection_count > 0))", "a.status != ''"];
$params = [$signatory];
$types = "s";

if ($courseFilter) {
    $whereClauses[] = "a.course = ?";
    $params[] = $courseFilter;
    $types .= "s";
}

if ($yearFilter !== '') {
    $whereClauses[] = "u.year = ?";
    $params[] = $yearFilter;
    $types .= "s";
}

if ($sectionFilter !== '') {
    $whereClauses[] = "u.section = ?";
    $params[] = $sectionFilter;
    $types .= "s";
}

if ($search) {
    $whereClauses[] = "(u.full_name LIKE ? OR a.username LIKE ?)";
    $params[] = "%$search%";
    $params[] = "%$search%";
    $types .= "ss";
}

$where = "WHERE " . implode(" AND ", $whereClauses);

// Count total records with user join
$countSql = "SELECT COUNT(*) as total 
             FROM applications a 
             LEFT JOIN users u ON a.username = u.username 
             $where";
$stmtTotal = $conn->prepare($countSql);
$tempParams = $params;
bind_param_array($stmtTotal, $types, $tempParams);
$stmtTotal->execute();
$totalRes = $stmtTotal->get_result();
$total = $totalRes->fetch_assoc()['total'];
$stmtTotal->close();

$pages = ceil($total / $limit);
if ($page > $pages) {
    $page = $pages > 0 ? $pages : 1;
    $offset = ($page - 1) * $limit;
}


// Fetch current application statuses (Approved / Requires Action)
$sql = "SELECT 
            a.*, 
            u.full_name, 
            u.year, 
            u.section,
            (SELECT adv.full_name 
             FROM users adv 
             WHERE adv.role = 'signatory' 
               AND adv.signatory_type = 'Class Adviser'
               AND adv.status = 'active'
               AND FIND_IN_SET(
                   CONCAT(a.course, '|', u.year, '|', u.section),
                   REPLACE(adv.section, ', ', ',')
               ) > 0
             LIMIT 1
            ) AS adviser_name
        FROM applications a
        LEFT JOIN users u ON a.username = u.username
        $where 
        ORDER BY a.reviewed_at DESC, a.submitted_at DESC 
        LIMIT ? OFFSET ?";
$stmt = $conn->prepare($sql);

$bindParams = $params;
$bindTypes = $types . "ii";
$bindParams[] = $limit;
$bindParams[] = $offset;

bind_param_array($stmt, $bindTypes, $bindParams);
$stmt->execute();
$result = $stmt->get_result();
$applicationRows = $result->fetch_all(MYSQLI_ASSOC);

// For each application, fetch its rejection log entries and merge into rows
$rows = [];
foreach ($applicationRows as $app) {
    // Always add the current application row
    $rows[] = $app;

    // Always fetch rejection log entries regardless of current status
    $logStmt = $conn->prepare(
        "SELECT * FROM application_rejection_log 
         WHERE application_id = ? 
         ORDER BY rejection_number ASC"
    );
    $logStmt->bind_param("i", $app['id']);
    $logStmt->execute();
    $logRows = $logStmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $logStmt->close();

    foreach ($logRows as $log) {
        $rows[] = array_merge($app, [
            '_is_rejection_log' => true,
            'status'            => 'Rejected',
            'rejection_reason'  => $log['rejection_reason'],
            'remarks'           => $log['additional_notes'],
            'reviewed_at'       => $log['rejected_at'],
            '_rejection_number' => $log['rejection_number'],
        ]);
    }
}

// Get distinct courses for filter dropdown
$coursesStmt = $conn->prepare("SELECT DISTINCT a.course FROM applications a WHERE a.signatory = ? ORDER BY a.course");
$coursesStmt->bind_param("s", $signatory);
$coursesStmt->execute();
$courses = $coursesStmt->get_result();
$coursesArray = [];
while($c = $courses->fetch_assoc()) {
    $coursesArray[] = $c;
}
$coursesStmt->close();

// Get distinct years for filter dropdown
$yearsStmt = $conn->prepare("SELECT DISTINCT u.year FROM applications a LEFT JOIN users u ON a.username = u.username WHERE a.signatory = ? AND u.year IS NOT NULL ORDER BY u.year");
$yearsStmt->bind_param("s", $signatory);
$yearsStmt->execute();
$years = $yearsStmt->get_result();
$yearsArray = [];
while($y = $years->fetch_assoc()) {
    $yearsArray[] = $y;
}
$yearsStmt->close();

// Get distinct sections for filter dropdown
$sectionsStmt = $conn->prepare("SELECT DISTINCT u.section FROM applications a LEFT JOIN users u ON a.username = u.username WHERE a.signatory = ? AND u.section IS NOT NULL AND u.section != '' ORDER BY u.section");
$sectionsStmt->bind_param("s", $signatory);
$sectionsStmt->execute();
$sections = $sectionsStmt->get_result();
$sectionsArray = [];
while($s = $sections->fetch_assoc()) {
    $sectionsArray[] = $s;
}
$sectionsStmt->close();

$stmt->close();
$conn->close();
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Signatory History</title>
<link href='https://unpkg.com/boxicons@2.1.4/css/boxicons.min.css' rel='stylesheet'>
<link rel="stylesheet" href="styles.css">
<style>
.home { padding:25px; }   

.header-section {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 20px;
}

.header-section h2 {
    color: darkgreen;
    margin: 0;
}

.download-btn {
    background: #0b4e12;
    color: white;
    border: none;
    padding: 10px 20px;
    border-radius: 8px;
    cursor: pointer;
    font-weight: 600;
    transition: 0.3s;
    display: flex;
    align-items: center;
    gap: 8px;
}
.download-btn:hover {
    background: #008a47;
    transform: translateY(-2px);
    box-shadow: 0 4px 8px rgba(0,0,0,0.2);
}

.filter-bar {
    display:flex; justify-content:space-between; align-items:center;
    margin-bottom:20px; gap: 10px; flex-wrap: wrap;
}
.filter-group-inline {
    display: flex;
    gap: 10px;
    align-items: center;
    flex-wrap: wrap;
}
select, input[type=search] {
    padding:8px; border:1px solid #ccc; border-radius:6px; min-width: 120px;
}
.table {
    width:100%; 
    border-collapse:collapse; 
    background:white;
    table-layout: fixed; /* Added for better column control */
}
.table th, .table td {
    padding:10px; 
    border:1px solid #ddd; 
    text-align:center; 
    vertical-align:middle;
    word-wrap: break-word; /* Allows long text to wrap */
}
.table th { background:darkgreen; color:white; }
.status {
    font-weight:600; border-radius:5px; padding:4px 8px;
    display: inline-block;
}
.status.Approved { background:green; color:white; }
.status.Rejected { background:red; color:white; }
.pagination {
    text-align:center; margin-top:15px;
}
.pagination a {
    margin:0 4px; padding:6px 12px; background:darkgreen; color:#fff;
    border-radius:4px; text-decoration:none;
}
.pagination a.active { background:#0b4e12; }
.remarks {
    font-size:13px; color:#555;
    text-align: left;
    max-width: 200px;
    overflow: hidden;
    text-overflow: ellipsis;
}
.file-cell {
    max-width: 140px;
    overflow: hidden;
    white-space: nowrap;
    text-overflow: ellipsis;
}
.file-link {
    display: block;
    overflow: hidden;
    white-space: nowrap;
    text-overflow: ellipsis;
    max-width: 100%;
}

.file-link {
    cursor: pointer;
    color: darkgreen;
    font-weight: 600;
    position: relative; 
    text-decoration: none;
    display: inline-block;
}

.file-link:hover {
    text-decoration: underline;
}

.file-link-container {
    position: relative;
    display: inline-block;
}

#hoverPreviewPanel {
    visibility: hidden;
    position: absolute;
    z-index: 5000;
    width: 450px; 
    height: 350px;
    background: #fff;
    border: 1px solid #ddd;
    border-radius: 8px;
    box-shadow: 0 4px 15px rgba(0, 0, 0, 0.3);
    overflow: hidden;
    opacity: 0;
    transition: opacity 0.2s ease, visibility 0.2s ease;
    pointer-events: none; 
    margin-top: 10px;
}

#hoverPreviewPanel.active {
    visibility: visible;
    opacity: 1;
    pointer-events: auto;
}

#hoverPreviewIframe {
    width: 100%;
    height: 100%;
    border: none;
    display: block;
}

#hoverPreviewLoading {
    position: absolute;
    top: 0;
    left: 0;
    width: 100%;
    height: 100%;
    background: rgba(255, 255, 255, 0.9);
    display: flex;
    justify-content: center;
    align-items: center;
    font-size: 14px;
    color: #555;
    flex-direction: column;
}

.modal {
    display: none;
    position: fixed;
    z-index: 3000;
    left: 0;
    top: 0;
    width: 100%;
    height: 100%;
    background: rgba(0,0,0,0.6);
    justify-content: center;
    align-items: center;
}

#filePreviewModal {
    z-index: 4000; 
}
.file-preview-content {
    background: white;
    padding: 0;
    border-radius: 12px;
    width: 95%;
    max-width: 900px; 
    height: 90%;
    max-height: 90vh;
    box-shadow: 0 5px 20px rgba(0,0,0,0.3);
    display: flex;
    flex-direction: column;
}

.file-preview-header {
    padding: 15px 20px;
    border-bottom: 1px solid #eee;
    display: flex;
    justify-content: space-between;
    align-items: center;
}

.file-preview-header h4 {
    margin: 0;
    color: darkgreen;
    font-weight: 600;
    font-size: 1.1em;
    overflow: hidden;
    white-space: nowrap;
    text-overflow: ellipsis;
    max-width: 80%;
}

.file-preview-iframe {
    flex-grow: 1; 
    border: none;
    width: 100%;
    height: 100%;
}

.close {
    float: right;
    font-size: 28px;
    font-weight: bold;
    color: #555;
    cursor: pointer;
    margin-top: -10px;
}
.close:hover { color: #000; }

.modal-content {
    background: white;
    padding: 30px;
    border-radius: 12px;
    width: 90%;
    max-width: 500px;
    box-shadow: 0 5px 20px rgba(0,0,0,0.3);
    animation: slideIn 0.3s ease;
    max-height: 90vh;
    overflow-y: auto;
}

@keyframes slideIn {
    from { transform: translateY(-30px); opacity: 0; }
    to { transform: translateY(0); opacity: 1; }
}

.modal-content h3 {
    color: darkgreen;
    margin-bottom: 20px;
    text-align: center;
}

.filter-group {
    margin-bottom: 15px;
}

.filter-group label {
    display: block;
    font-weight: 600;
    margin-bottom: 5px;
    color: #333;
}

.filter-group select,
.filter-group input {
    width: 100%;
    padding: 10px;
    border: 1px solid #ccc;
    border-radius: 6px;
    font-size: 14px;
}

.btn-group {
    display: flex;
    gap: 10px;
    margin-top: 25px;
}

.cancel-btn, .export-btn {
    flex: 1;
    padding: 12px;
    border: none;
    border-radius: 8px;
    font-weight: 600;
    cursor: pointer;
    transition: 0.3s;
}

.cancel-btn {
    background: #ddd;
    color: #222;
}
.cancel-btn:hover {
    background: #bbb;
}

.export-btn {
    background: #0b4e12;
    color: white;
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 5px;
}
.export-btn:hover {
    background: #0b4e12;
}

.format-options {
    display: flex;
    gap: 15px;
    margin-top: 10px;
}

.format-options label {
    display: flex;
    align-items: center;
    gap: 5px;
    cursor: pointer;
}

.format-options input[type="radio"] {
    width: auto;
    cursor: pointer;
}
.confirm-btn { background:#0b5d27; color:#fff; padding:6px 10px; border-radius:6px; border:none; cursor:pointer; }
.status.Totally_Rejected { background:#8b0000; color:white; }
.status.Requires_Action  { background:#e65100; color:white; }
.rejection-badge {
    display: block;
    font-size: 11px;
    font-weight: 500;
    margin-top: 3px;
    opacity: 0.9;
}
.rejection-log-row { display: none; background: #fff5f5; }
.rejection-log-row.visible { display: table-row; }
.main-app-row { cursor: pointer; }
.main-app-row:hover { background: #f0f7f0 !important; }
.expand-icon { font-size: 11px; margin-left: 6px; color: #fff; }
</style>
</head>
<body>
<?php include 'sidebar_signa.php'; ?>

<section class="home">
    <div class="header-section">
      <h2>📜 Processed Applications (History)</h2>
      <button class="download-btn" onclick="openDownloadModal()">
        <i class='bx bx-download'></i>
        Export History
      </button>
    </div>

    <div class="filter-bar">
      <form method="get" action="signatory_history.php" class="filter-group-inline">
        <select name="course" onchange="this.form.submit()">
          <option value="">All Courses</option>
          <?php foreach($coursesArray as $c): ?>
            <option value="<?= htmlspecialchars($c['course']) ?>" <?= $courseFilter == $c['course'] ? 'selected' : '' ?>>
              <?= htmlspecialchars($c['course']) ?>
            </option>
          <?php endforeach; ?>
        </select>
        
        <select name="year" onchange="this.form.submit()">
          <option value="">All Years</option>
          <?php foreach($yearsArray as $y): ?>
            <option value="<?= htmlspecialchars($y['year']) ?>" <?= $yearFilter == $y['year'] ? 'selected' : '' ?>>
              <?= htmlspecialchars($y['year']) ?>
            </option>
          <?php endforeach; ?>
        </select>
        
        <select name="section" onchange="this.form.submit()">
          <option value="">All Sections</option>
          <?php foreach($sectionsArray as $s): ?>
            <option value="<?= htmlspecialchars($s['section']) ?>" <?= $sectionFilter == $s['section'] ? 'selected' : '' ?>>
              <?= htmlspecialchars($s['section']) ?>
            </option>
          <?php endforeach; ?>
        </select>
        
        <input type="search" name="search" placeholder="Search student..." value="<?= htmlspecialchars($search) ?>">
        <button type="submit" class="confirm-btn">Search</button>
        
        <?php if ($courseFilter || $yearFilter || $sectionFilter || $search): ?>
          <a href="signatory_history.php" class="confirm-btn" style="text-decoration:none; display:inline-block;">Clear Filters</a>
        <?php endif; ?>
      </form>
    </div>

    <table class="table">
      <thead>
        <tr>
          <th>Student Name</th>
          <th>Student ID</th>
          <th>Course</th>
          <th>Year</th>
          <th>Section</th>
          <th>Adviser</th> <th>File</th>
          <th>Status</th>
          <th>Remarks</th>
          <th>Date Processed</th>
        </tr>
      </thead>
      <tbody>
        <?php if (count($rows) > 0): ?>
  <?php 
  // Group rows: main app rows and their rejection logs
  $grouped = [];
  foreach ($rows as $row) {
      if (empty($row['_is_rejection_log'])) {
          $grouped[$row['id']] = [
              'main' => $row,
              'logs' => []
          ];
      }
  }
  foreach ($rows as $row) {
      if (!empty($row['_is_rejection_log'])) {
          $aid = $row['id'];
          if (isset($grouped[$aid])) {
              $grouped[$aid]['logs'][] = $row;
          }
      }
  }
  ?>
  <?php foreach ($grouped as $app_id => $group): 
      $row          = $group['main'];
      $logs         = $group['logs'];
      $student_name = !empty($row['full_name']) ? $row['full_name'] : $row['username'];
      $adviser_name = !empty($row['adviser_name']) ? $row['adviser_name'] : 'N/A';
      $rejection_count = (int)$row['rejection_count'];
      $has_logs     = count($logs) > 0;
      $group_id     = 'group_' . $app_id;
  ?>
    <!-- MAIN ROW -->
    <?php $student_id_display = !empty($row['student_id']) ? $row['student_id'] : $row['username']; ?>
    <tr class="main-app-row" onclick="<?= $has_logs ? "toggleRejectionLogs('{$group_id}')" : '' ?>">
      <td><?= htmlspecialchars($student_name) ?></td>
      <td><?= htmlspecialchars($student_id_display) ?></td>
      <td><?= htmlspecialchars($row['course']) ?></td>
      <td><?= htmlspecialchars($row['year'] ?? 'N/A') ?></td>
      <td><?= htmlspecialchars($row['section'] ?? 'N/A') ?></td>
      <td><?= htmlspecialchars($adviser_name) ?></td>
      <td class="file-cell">
        <div class="file-link-container" 
             data-filename="<?= htmlspecialchars($row['document']) ?>" 
             data-filepath="uploads/<?= rawurlencode($row['document']) ?>" 
             onmouseover="showHoverPreview(this)" 
             onmouseout="hideHoverPreview()">
            <a href="#" class="file-link" 
                onclick="event.preventDefault(); openFilePreviewModal('uploads/<?= rawurlencode($row['document']) ?>', '<?= htmlspecialchars($row['document']) ?>')" 
                title="<?= htmlspecialchars($row['document']) ?>">
                <?= htmlspecialchars($row['document']) ?>
            </a>
        </div>
      </td>
      <td>
        <?php 
        $status_class = str_replace(' ', '_', $row['status']);
        ?>
        <?php if ($row['status'] === 'Totally Rejected'): ?>
            <span class="status Totally_Rejected">
                ⛔ Totally Rejected
                <span class="rejection-badge">(Permanent)</span>
            </span>
            <form method="POST" action="overturn_rejection.php" style="margin-top:6px;"
                  onsubmit="return confirm('Overturn this rejection and mark as Approved?')">
                <input type="hidden" name="app_id" value="<?= intval($row['id']) ?>">
                <button type="submit" onclick="event.stopPropagation();" style="background:#28a745; color:white; border:none; padding:5px 12px; border-radius:5px; cursor:pointer; font-size:12px; font-weight:600;">
                    ↩️ Overturn Decision
                </button>
            </form>
        <?php else: ?>
            <span class="status <?= $status_class ?>">
                <?= htmlspecialchars($row['status']) ?>
                <?php if ($rejection_count > 0 && $row['status'] === 'Requires Action'): ?>
                    <span class="rejection-badge">(<?= $rejection_count ?>x rejected, awaiting resubmit)</span>
                <?php elseif ($rejection_count > 0 && $row['status'] === 'Pending'): ?>
                    <span class="rejection-badge">(<?= $rejection_count ?>x rejected, resubmitted)</span>
                <?php endif; ?>
                <?php if ($has_logs): ?>
                    <span class="expand-icon" id="icon_<?= $group_id ?>">▼</span>
                <?php endif; ?>
            </span>
        <?php endif; ?>
      </td>
      <td class="remarks"><?= htmlspecialchars($row['rejection_reason'] ?? $row['remarks'] ?? '—') ?></td>
      <td><?= htmlspecialchars($row['reviewed_at'] ?? $row['submitted_at']) ?></td>
    </tr>

    <!-- REJECTION LOG ROWS (hidden by default) -->
    <?php foreach ($logs as $log): ?>
    <tr class="rejection-log-row" data-group="<?= $group_id ?>">
      <td colspan="6" style="text-align:right; padding-right:20px; color:#888; font-size:12px;">
          ↳ Rejection #<?= $log['_rejection_number'] ?>
      </td>
      <td class="file-cell">
        <div class="file-link-container"
             data-filename="<?= htmlspecialchars($log['document']) ?>"
             data-filepath="uploads/<?= rawurlencode($log['document']) ?>"
             onmouseover="showHoverPreview(this)"
             onmouseout="hideHoverPreview()">
            <a href="#" class="file-link"
                onclick="event.preventDefault(); openFilePreviewModal('uploads/<?= rawurlencode($log['document']) ?>', '<?= htmlspecialchars($log['document']) ?>')"
                title="<?= htmlspecialchars($log['document']) ?>">
                <?= htmlspecialchars($log['document']) ?>
            </a>
        </div>
      </td>
      <td>
          <span class="status Rejected">
              Rejected
              <span class="rejection-badge">(#<?= $log['_rejection_number'] ?>)</span>
          </span>
      </td>
      <td class="remarks"><?= htmlspecialchars($log['rejection_reason'] ?? '—') ?></td>
      <td><?= htmlspecialchars($log['reviewed_at']) ?></td>
    </tr>
    <?php endforeach; ?>

  <?php endforeach; ?>
<?php else: ?>
  <tr><td colspan="10">No records found.</td></tr>
<?php endif; ?>
      </tbody>
    </table>

    <div class="pagination">
      <?php for ($i=1; $i <= $pages; $i++): ?>
        <a href="?page=<?= $i ?>&course=<?= urlencode($courseFilter) ?>&year=<?= urlencode($yearFilter) ?>&section=<?= urlencode($sectionFilter) ?>&search=<?= urlencode($search) ?>" class="<?= $i==$page ? 'active' : '' ?>"><?= $i ?></a>
      <?php endfor; ?>
    </div>
</section>

<div id="hoverPreviewPanel">
    <div id="hoverPreviewLoading">
        <i class='bx bx-loader-alt bx-spin' style="font-size: 24px; margin-bottom: 10px;"></i>
        Loading Preview...
    </div>
    <iframe id="hoverPreviewIframe" frameborder="0" allowfullscreen></iframe>
</div>
<div id="downloadModal" class="modal" style="display:none;">
    <div class="modal-content">
      <span class="close" onclick="closeDownloadModal()">&times;</span>
      <h3>📥 Export Processed Applications</h3>
      
      <form id="exportForm" action="export_signatory_history.php" method="POST">
        <input type="hidden" name="signatory" value="<?php echo htmlspecialchars($signatory); ?>">

        <div class="filter-group">
          <label>File Format</label>
          <div class="format-options">
            <label>
              <input type="radio" name="format" value="csv" checked> CSV
            </label>
            <label>
              <input type="radio" name="format" value="excel"> Excel (XLSX)
            </label>
          </div>
        </div>

        <div class="filter-group">
          <label>Date Range</label>
          <select name="date_filter" id="dateFilter" onchange="toggleCustomDates()">
            <option value="all">All Time</option>
            <option value="today">Today</option>
            <option value="yesterday">Yesterday</option>
            <option value="this_week">This Week</option>
            <option value="last_week">Last Week</option>
            <option value="this_month">This Month</option>
            <option value="last_month">Last Month</option>
            <option value="this_year">This Year</option>
            <option value="custom">Custom Range</option>
          </select>
        </div>

        <div id="customDateRange" style="display:none;">
          <div class="filter-group">
            <label>From Date</label>
            <input type="date" name="date_from">
            </div>
          <div class="filter-group">
            <label>To Date</label>
            <input type="date" name="date_to">
          </div>
        </div>

        <div class="filter-group">
          <label>Status Filter</label>
          <select name="status_filter">
            <option value="all">All Status</option>
            <option value="Approved">Approved</option>
            <option value="Rejected">Rejected</option>
          </select>
        </div>

        <div class="filter-group">
          <label>Course Filter</label>
          <select name="course_filter">
            <option value="all">All Courses</option>
            <?php foreach($coursesArray as $c): ?>
              <option value="<?= htmlspecialchars($c['course']) ?>"><?= htmlspecialchars($c['course']) ?></option>
            <?php endforeach; ?>
          </select>
        </div>

        <div class="filter-group">
          <label>Year Level Filter</label>
          <select name="year_filter">
            <option value="all">All Years</option>
            <?php foreach($yearsArray as $y): ?>
              <option value="<?= htmlspecialchars($y['year']) ?>"><?= htmlspecialchars($y['year']) ?></option>
            <?php endforeach; ?>
          </select>
        </div>

        <div class="filter-group">
          <label>Section Filter</label>
          <select name="section_filter">
            <option value="all">All Sections</option>
            <?php foreach($sectionsArray as $s): ?>
              <option value="<?= htmlspecialchars($s['section']) ?>"><?= htmlspecialchars($s['section']) ?></option>
            <?php endforeach; ?>
          </select>
        </div>

        <div class="btn-group">
          <button type="button" class="cancel-btn" onclick="closeDownloadModal()">Cancel</button>
          <button type="submit" class="export-btn">
            <i class='bx bx-download'></i> Download
          </button>
        </div>
      </form>
    </div>
</div>
<div id="filePreviewModal" class="modal" style="display:none;">
    <div class="file-preview-content">
        <div class="file-preview-header">
            <h4 id="filePreviewTitle">Document Preview</h4>
            <div>
                <a id="fileDownloadLink" href="#" class="download-btn" style="margin-right: 10px; padding: 5px 10px; font-size: 14px;" download>
                    <i class='bx bx-download'></i> Download
                </a>
                <span class="close" onclick="closeFilePreviewModal()">&times;</span>
            </div>
        </div>
        <iframe id="filePreviewIframe" class="file-preview-iframe" frameborder="0" allowfullscreen></iframe>
    </div>
</div>
<script>
function confirmLogout(){
    if(confirm("Are you sure you want to logout?"))
      window.location.href='logout.php';
}

function openDownloadModal() {
    document.getElementById('downloadModal').style.display = 'flex';
}

function closeDownloadModal() {
    document.getElementById('downloadModal').style.display = 'none';
}

function toggleCustomDates() {
    const dateFilter = document.getElementById('dateFilter').value;
    const customRange = document.getElementById('customDateRange');
    
    if (dateFilter === 'custom') {
      customRange.style.display = 'block';
    } else {
      customRange.style.display = 'none';
    }
}

function getPreviewUrl(fileUrl) {
    const baseUrl = window.location.origin + '/'; 
    const fullFileUrl = baseUrl + fileUrl;
    return 'https://docs.google.com/gview?url=' + encodeURIComponent(fullFileUrl) + '&embedded=true';
}

function openFilePreviewModal(localFileUrl, fileName) {
    hideHoverPreview(); 
    const previewUrl = getPreviewUrl(localFileUrl);
    
    document.getElementById('filePreviewTitle').textContent = fileName;
    document.getElementById('filePreviewIframe').src = previewUrl;
    document.getElementById('fileDownloadLink').href = localFileUrl;
    document.getElementById('filePreviewModal').style.display = 'flex';
}

function closeFilePreviewModal() {
    document.getElementById('filePreviewModal').style.display = 'none';
    document.getElementById('filePreviewIframe').src = '';
}

const hoverPanel = document.getElementById('hoverPreviewPanel');
const hoverIframe = document.getElementById('hoverPreviewIframe');
const hoverLoading = document.getElementById('hoverPreviewLoading');
let hoverTimer;

function showHoverPreview(element) {
    clearTimeout(hoverTimer);
    const filePath = element.getAttribute('data-filepath');
    const rect = element.getBoundingClientRect();
    hoverPanel.style.left = (rect.left + rect.width / 2) + 'px';
    hoverPanel.style.top = (rect.bottom + window.scrollY) + 'px';
    hoverPanel.style.transform = 'translateX(-50%)';
    hoverLoading.style.display = 'flex';
    hoverIframe.src = '';
    
    hoverTimer = setTimeout(() => {
        const previewUrl = getPreviewUrl(filePath);
        if (!hoverPanel.classList.contains('active')) {
             hoverIframe.onload = function() {
                 hoverLoading.style.display = 'none';
             };
             hoverIframe.src = previewUrl;
        }
        hoverPanel.classList.add('active');
    }, 500);
}

function hideHoverPreview() {
    clearTimeout(hoverTimer);
    hoverPanel.classList.remove('active');
    setTimeout(() => {
        hoverIframe.src = '';
        hoverLoading.style.display = 'flex';
    }, 250); 
}

window.onclick = function(event) {
    const downloadModal = document.getElementById('downloadModal');
    const previewModal = document.getElementById('filePreviewModal');

    if (downloadModal.style.display === 'flex' && event.target == downloadModal) { 
        closeDownloadModal();
    }
    if (previewModal.style.display === 'flex' && event.target == previewModal) {
        closeFilePreviewModal();
    }
}

const body = document.querySelector('body'),
      sidebar = body.querySelector('nav'),
      toggle = body.querySelector(".toggle");
if (toggle && sidebar) {
    toggle.addEventListener("click" , () =>{
        sidebar.classList.toggle("close");
    });
}
function toggleRejectionLogs(groupId) {
    const logRows = document.querySelectorAll(`tr[data-group="${groupId}"]`);
    const icon    = document.getElementById('icon_' + groupId);
    const isOpen  = logRows.length > 0 && logRows[0].classList.contains('visible');
    logRows.forEach(r => r.classList.toggle('visible', !isOpen));
    if (icon) icon.textContent = isOpen ? '▼' : '▲';
}

</script>
</body>
</html>