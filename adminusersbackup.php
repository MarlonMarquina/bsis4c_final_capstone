<?php
/**
 * FILE: admin_users.php
 * DESCRIPTION: Fully Integrated Admin Panel with AJAX Search, Audit, and Verification.
 * FIX: Corrected clearance status display logic to match get_student_status.php
 */
include 'conn.php';
session_start();

// --- Authorization ---
if (!isset($_SESSION['role']) || $_SESSION['role'] != "admin") {
    header("Location: login.php");
    exit();
}

$msg = $_GET['msg'] ?? '';

// Initial Fetch (Para sa unang load ng page)
$sql = "SELECT username, full_name, email, year, section, role, signatory_type, department, status, final_clearance_status, admin_approved 
        FROM users ORDER BY role DESC, username ASC";
$result = $conn->query($sql);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Users Directory | Admin Control</title>
    <link href='https://unpkg.com/boxicons@2.1.4/css/boxicons.min.css' rel='stylesheet'>
    <style>
        body { font-family: 'Poppins', sans-serif; background-color: #f4f7f6; margin: 0; }
        .home { position: relative; left: 320px; width: calc(100% - 320px); transition: all 0.5s ease; padding: 25px; min-height: 100vh; box-sizing: border-box; }
        .sidebar.close ~ .home { left: 88px; width: calc(100% - 88px); }

        .table-container { background: #fff; padding: 20px; border-radius: 12px; box-shadow: 0 4px 10px rgba(0,0,0,0.05); }
        .table { width:100%; border-collapse:collapse; background:white; font-size: 13px; }
        .table th, .table td { padding:12px; border-bottom: 1px solid #eee; text-align:center; }
        .table th { background: #006400; color:white; font-weight: 600; }
        
        .message { background:#d4edda; color:#155724; padding:12px; border-radius:8px; margin-bottom:20px; font-weight: 500; }
        
        /* Filter Bar */
        .filter-bar { display: flex; gap: 15px; margin-bottom: 25px; background: #fff; padding: 15px; border-radius: 10px; align-items: center; }
        input[type="text"], select { padding: 10px; border-radius: 8px; border: 1px solid #ddd; outline: none; }

        /* Badges & Buttons */
        .status-pill { padding: 4px 10px; border-radius: 50px; font-size: 10px; font-weight: 700; text-transform: uppercase; }
        .status-active { background: #e6fffa; color: #008767; border: 1px solid #008767; }
        .status-inactive { background: #fff5f5; color: #e53e3e; border: 1px solid #e53e3e; }
        .status-pending { background: #fff3cd; color: #856404; border: 1px solid #ffc107; }
        .status-under-review { background: #d1ecf1; color: #0c5460; border: 1px solid #bee5eb; }
        .status-approved { background: #d4edda; color: #155724; border: 1px solid #c3e6cb; }
        .status-not-requested { background: #f8f9fa; color: #6c757d; border: 1px solid #dee2e6; }
        
        .btn-action { padding: 6px 12px; border-radius: 6px; cursor: pointer; font-size: 11px; border: none; font-weight: 600; color: white; transition: 0.3s; }
        .btn-visit { background: #3182ce; }
        .btn-deactivate { background: #e53e3e; }
        .btn-activate { background: #38a169; }
        .btn-visit:hover, .btn-deactivate:hover, .btn-activate:hover { opacity: 0.8; transform: translateY(-1px); }

        /* Modal Styles */
        .modal { display: none; position: fixed; z-index: 9999; left: 0; top: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.6); backdrop-filter: blur(3px); }
        .modal-content { background: white; margin: 8% auto; padding: 25px; width: 500px; border-radius: 15px; position: relative; box-shadow: 0 10px 30px rgba(0,0,0,0.2); max-height: 80vh; overflow-y: auto; }
        .close-modal { position: absolute; right: 20px; top: 20px; font-size: 24px; cursor: pointer; color: #999; }
        .status-item { display: flex; justify-content: space-between; padding: 12px; border-bottom: 1px solid #f0f0f0; align-items: center; }
        .status-badge { padding: 4px 10px; border-radius: 4px; font-size: 11px; font-weight: 600; }
        .badge-cleared { background: #d4edda; color: #155724; }
        .badge-pending { background: #f8d7da; color: #721c24; }
        .btn-approve { background: #27ae60; color: white; border: none; padding: 12px; border-radius: 8px; cursor: pointer; width: 100%; font-weight: 700; margin-top: 15px; }
        .btn-approve:hover { background: #229954; }
        .student-info { background: #f8f9fa; padding: 15px; border-radius: 8px; margin-bottom: 15px; }
        .student-info p { margin: 5px 0; font-size: 13px; }
    </style>
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
    <div style="margin-bottom: 20px;">
        <h2 style="color: #006400; margin: 0;">USER AUDIT & DIRECTORY</h2>
        <p style="color: #666; font-size: 14px;">Monitor account status and verify clearance compliance.</p>
    </div>

    <?php if ($msg): ?>
        <div class="message"><i class='bx bx-info-circle'></i> <?= htmlspecialchars($msg) ?></div>
    <?php endif; ?>

    <div class="filter-bar">
        <select id="roleFilter" onchange="fetchUsers()">
            <option value="">All Roles</option>
            <option value="admin">Admin</option>
            <option value="signatory">Signatories</option>
            <option value="student">Students</option>
        </select>
        <input type="text" id="searchInput" onkeyup="fetchUsers()" placeholder="Search by name or ID..." style="flex-grow: 1;">
    </div>

    <div class="table-container">
        <table class="table">
            <thead>
                <tr>
                    <th>Full Name</th>
                    <th>Role</th>
                    <th>Dept / Office</th>
                    <th>Account Status</th>
                    <th>Clearance Status</th>
                    <th>Action</th>
                </tr>
            </thead>
            <tbody id="userTableBody">
                <?php while($row = $result->fetch_assoc()): 
                    $statusClass = ($row['status'] == 'active') ? 'status-active' : 'status-inactive';
                    $dept = ($row['role'] == 'student') ? $row['year']." - ".$row['section'] : ($row['signatory_type'] ?? 'N/A');
                    
                    // FIX: Clearance status - DAPAT SAME LOGIC SA get_student_status.php
                    $clearance_status = '';
                    $clearance_class = '';
                    if ($row['role'] == 'student') {
                        if ($row['admin_approved'] == 1) {
                            $clearance_status = 'Approved';
                            $clearance_class = 'status-approved';
                        } elseif ($row['final_clearance_status'] == 'pending') {
                            $clearance_status = 'Under Review';
                            $clearance_class = 'status-under-review';
                        } else {
                            // Kung wala pa silang request (not_requested)
                            $clearance_status = 'Not Requested';
                            $clearance_class = 'status-not-requested';
                        }
                    }
                ?>
                <tr>
                    <td style="text-align:left;"><b><?= htmlspecialchars($row['full_name']) ?></b><br><small><?= htmlspecialchars($row['username']) ?></small></td>
                    <td><?= ucfirst($row['role']) ?></td>
                    <td><?= htmlspecialchars($dept) ?></td>
                    <td><span class="status-pill <?= $statusClass ?>"><?= $row['status'] ?></span></td>
                    <td>
                        <?php if ($row['role'] == 'student'): ?>
                            <span class="status-pill <?= $clearance_class ?>"><?= $clearance_status ?></span>
                        <?php else: ?>
                            ---
                        <?php endif; ?>
                    </td>
                    <td>
                        <?php if($row['role'] == 'student'): ?>
                            <button class="btn-action btn-visit" onclick="openVerification('<?= $row['username'] ?>', '<?= addslashes($row['full_name']) ?>')">
                                <i class='bx bx-show'></i> View
                            </button>
                        <?php elseif($row['role'] == 'signatory'): ?>
                            <?php if($row['status'] == 'active'): ?>
                                <button class="btn-action btn-deactivate" onclick="toggleStatus('<?= $row['username'] ?>', 'inactive')">Deactivate</button>
                            <?php else: ?>
                                <button class="btn-action btn-activate" onclick="toggleStatus('<?= $row['username'] ?>', 'active')">Activate</button>
                            <?php endif; ?>
                        <?php else: ?>
                            ---
                        <?php endif; ?>
                    </td>
                </tr>
                <?php endwhile; ?>
            </tbody>
        </table>
    </div>
</section>

<div id="verifyModal" class="modal">
    <div class="modal-content">
        <span class="close-modal" onclick="closeModal()">&times;</span>
        <h3><i class='bx bx-shield-check'></i> Student Clearance Verification</h3>
        
        <div id="studentInfo" class="student-info"></div>
        
        <h4 style="margin-top: 20px; color: #333;">Signatory Compliance:</h4>
        <div id="requirementsList"></div>
        <div id="modalFooter"></div>
    </div>
</div>

<script>
// --- AJAX SEARCH ---
function fetchUsers() {
    let s = document.getElementById('searchInput').value;
    let r = document.getElementById('roleFilter').value;
    fetch(`search_logic.php?search=${s}&role=${r}`)
        .then(res => res.text())
        .then(data => { document.getElementById('userTableBody').innerHTML = data; })
        .catch(err => console.error('Search error:', err));
}

// --- AUDIT STATUS TOGGLE ---
function toggleStatus(username, newStatus) {
    if(confirm(`Set account ${username} to ${newStatus}?`)) {
        window.location.href = `admin_audit_action.php?id=${username}&status=${newStatus}`;
    }
}

// --- STUDENT VERIFICATION MODAL ---
function openVerification(studentId, fullName) {
    const modal = document.getElementById('verifyModal');
    modal.style.display = 'block';
    
    fetch('get_student_status.php?id=' + studentId)
        .then(res => res.json())
        .then(data => {
            // Student Info
            document.getElementById('studentInfo').innerHTML = `
                <p><strong>Student Name:</strong> ${data.student_info.full_name}</p>
                <p><strong>Course:</strong> ${data.student_info.course} ${data.student_info.year}</p>
                <p><strong>Section:</strong> ${data.student_info.section}</p>
                <p><strong>Clearance Status:</strong> <span class="status-badge ${data.student_info.clearance_class}">${data.student_info.clearance_status}</span></p>
            `;
            
            // Requirements List
            let html = '';
            let clearedCount = 0;
            data.requirements.forEach(item => {
                const bClass = item.status === 'cleared' ? 'badge-cleared' : 'badge-pending';
                if(item.status === 'cleared') clearedCount++;
                html += `
                    <div class="status-item">
                        <span><strong>${item.office}</strong></span>
                        <span class="status-badge ${bClass}">${item.status.toUpperCase()}</span>
                    </div>`;
            });
            document.getElementById('requirementsList').innerHTML = html;
            
            // Footer Actions
            const totalRequired = data.requirements.length;
            const canApprove = (clearedCount === totalRequired && totalRequired > 0 && data.student_info.clearance_status === 'Under Review');
            
            if (data.student_info.admin_approved == 1) {
                document.getElementById('modalFooter').innerHTML = `
                    <div style="text-align:center; color:#28a745; font-weight:600; margin-top:15px;">
                        <i class='bx bx-check-circle' style="font-size:24px;"></i><br>
                        Already Approved
                    </div>`;
            } else if (canApprove) {
                document.getElementById('modalFooter').innerHTML = `
                    <button class="btn-approve" onclick="confirmFinal('${studentId}')">
                        <i class='bx bx-check-shield'></i> APPROVE FINAL CLEARANCE
                    </button>`;
            } else if (data.student_info.clearance_status === 'Under Review') {
                document.getElementById('modalFooter').innerHTML = `
                    <p style="text-align:center; color:#dc3545; font-size:12px; margin-top:10px;">
                        ⚠️ Incomplete Requirements (${clearedCount}/${totalRequired})
                    </p>`;
            } else {
                document.getElementById('modalFooter').innerHTML = `
                    <p style="text-align:center; color:#6c757d; font-size:12px; margin-top:10px;">
                        Student has not requested verification yet
                    </p>`;
            }
        })
        .catch(err => {
            console.error('Error:', err);
            alert('Error loading student data');
        });
}

function closeModal() { 
    document.getElementById('verifyModal').style.display = 'none'; 
}

function confirmFinal(sid) {
    if(confirm("Confirm final approval? Student can then generate their clearance PDF.")) {
        window.location.href = "admin_verify_action.php?action=approve&id=" + sid;
    }
}
</script>

</body>
</html>