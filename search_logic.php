<?php
// FILE: search_logic.php
// AJAX handler for user search with filters
include 'conn.php';

$search = $_GET['search'] ?? '';
$role_filter = $_GET['role'] ?? '';

// Build query
$sql = "SELECT username, full_name, email, year, section, role, signatory_type, department, status, final_clearance_status, admin_approved 
        FROM users WHERE 1=1";

$params = [];
$types = '';

if (!empty($search)) {
    $sql .= " AND (username LIKE ? OR full_name LIKE ? OR email LIKE ?)";
    $search_param = "%{$search}%";
    $params[] = $search_param;
    $params[] = $search_param;
    $params[] = $search_param;
    $types .= 'sss';
}

if (!empty($role_filter)) {
    $sql .= " AND role = ?";
    $params[] = $role_filter;
    $types .= 's';
}

$sql .= " ORDER BY role DESC, username ASC";

$stmt = $conn->prepare($sql);
if (!empty($params)) {
    $stmt->bind_param($types, ...$params);
}
$stmt->execute();
$result = $stmt->get_result();

// Generate table rows
if ($result->num_rows > 0) {
    while($row = $result->fetch_assoc()) {
        $statusClass = ($row['status'] == 'active') ? 'status-active' : 'status-inactive';
        $dept = ($row['role'] == 'student') ? $row['year']." - ".$row['section'] : ($row['signatory_type'] ?? 'N/A');
        
        // FIX: Clearance status - SAME LOGIC as admin_users.php
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
                $clearance_status = 'Not Requested';
                $clearance_class = 'status-not-requested';
            }
        }
        
        echo "<tr>";
        echo "<td style='text-align:left;'><b>" . htmlspecialchars($row['full_name']) . "</b><br><small>" . htmlspecialchars($row['username']) . "</small></td>";
        echo "<td>" . ucfirst($row['role']) . "</td>";
        echo "<td>" . htmlspecialchars($dept) . "</td>";
        echo "<td><span class='status-pill {$statusClass}'>" . $row['status'] . "</span></td>";
        echo "<td>";
        if ($row['role'] == 'student') {
            echo "<span class='status-pill {$clearance_class}'>{$clearance_status}</span>";
        } else {
            echo "---";
        }
        echo "</td>";
        echo "<td>";
        
        if($row['role'] == 'student') {
            echo "<button class='btn-action btn-visit' onclick=\"openVerification('" . $row['username'] . "', '" . addslashes($row['full_name']) . "')\">
                    <i class='bx bx-show'></i> View
                  </button>";
        } elseif($row['role'] == 'signatory') {
            if($row['status'] == 'active') {
                echo "<button class='btn-action btn-deactivate' onclick=\"toggleStatus('" . $row['username'] . "', 'inactive')\">Deactivate</button>";
            } else {
                echo "<button class='btn-action btn-activate' onclick=\"toggleStatus('" . $row['username'] . "', 'active')\">Activate</button>";
            }
        } else {
            echo "---";
        }
        
        echo "</td>";
        echo "</tr>";
    }
} else {
    echo "<tr><td colspan='6' style='text-align:center; color:#999;'>No users found</td></tr>";
}

$stmt->close();
$conn->close();
?>