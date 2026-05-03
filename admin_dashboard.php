<?php
// FILE: admin_dashboard.php
include('conn.php');
session_start();
if (!isset($_SESSION['role']) || $_SESSION['role'] != "admin") {
    header("Location: login.php");
    exit();
}

// 1. I-fetch ang full_name ng admin mula sa database
$admin_username = $_SESSION['username'];
$admin_name = '';

$stmt = $conn->prepare("SELECT full_name FROM users WHERE username = ? AND role = 'admin'");
$stmt->bind_param("s", $admin_username);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows > 0) {
    $admin_name = $result->fetch_assoc()['full_name'];
}
$stmt->close();

// 2. Fetch basic stats
$total_students = $conn->query("SELECT COUNT(*) AS total FROM users WHERE role='student'")->fetch_assoc()['total'];
$total_signatories = $conn->query("SELECT COUNT(*) AS total FROM users WHERE role='signatory'")->fetch_assoc()['total'];
$total_applications = $conn->query("SELECT COUNT(*) AS total FROM applications")->fetch_assoc()['total'];
$pending = $conn->query("SELECT COUNT(*) AS total FROM applications WHERE status='Pending'")->fetch_assoc()['total'];
$approved = $conn->query("SELECT COUNT(*) AS total FROM applications WHERE status='Approved'")->fetch_assoc()['total'];
$requires_action = $conn->query("SELECT COUNT(*) AS total FROM applications WHERE status='Requires Action'")->fetch_assoc()['total'];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <link rel="stylesheet" href="styles.css">
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width,initial-scale=1.0">
    <title>Admin Dashboard | Smart Clearance System</title>

    <link href='https://unpkg.com/boxicons@2.1.4/css/boxicons.min.css' rel='stylesheet'>
    <link rel="stylesheet" href="styles.css">

    <style>
    body { font-family: 'Poppins', sans-serif;  background: #E4E9F7;}
    .home { transition: 0.3s;
 background: #E4E9F7; }
    .dashboard-container {
        width: 90%;
        margin: 30px auto;
        background: rgba(255, 255, 255, 0.95);
        border-radius: 10px;
        padding: 20px;
        text-align: center;
        box-shadow: 0 4px 8px rgba(0,0,0,0.1);
    }
    .dashboard-header {
        display: flex; 
        justify-content: center; 
        align-items: center; 
        background: darkgreen;
        color: white; 
        border-radius: 25px; 
        padding: 10px 20px; 
        margin-bottom: 30px;
    }
    .dashboard-header h2 { 
        font-size: 20px; 
        font-weight: 700; 
        text-align: center;
    }
    .dashboard-header .admin-name {
        font-size: 14px;
        font-weight: 500;
        opacity: 0.8;
        display: block; 
    }

    .stats-container {
        display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 20px; justify-content: center;
    }
    .stat-card {
        background: #fff; border: 1px solid #ddd; border-radius: 10px; padding: 20px; text-align: center; transition: 0.3s;
    }
    .stat-card:hover { transform: translateY(-5px); box-shadow: 0 4px 12px rgba(0,0,0,0.15); }
    .stat-card .icon { font-size: 40px; color: darkgreen; margin-bottom: 10px; }
    .stat-info h3 { font-size: 16px; font-weight: 600; color: #333; }
    .stat-info p { font-size: 20px; font-weight: 700; margin-top: 5px; }

    table { width: 100%; border-collapse: collapse; margin-top: 25px; font-size: 14px; }
    table th, table td { border: 1px solid #ddd; padding: 10px; text-align: center; }
    table th { background: darkgreen; color: white; }
    table tr:nth-child(even) { background: #f9f9f9; }
    table tr:hover { background: #f1f1f1; }
    
    /* ===== Hover Preview Styles ===== */
    .preview-container {
        position: relative; 
        display: inline-block;
        cursor: pointer;
        font-weight: bold;
        color: darkgreen;
    }
    .file-preview {
        display: none;
        position: absolute;
        top: 100%; 
        left: 50%;
        transform: translateX(-50%);
        z-index: 10;
        background: #fff;
        border: 2px solid darkgreen;
        box-shadow: 0 0 15px rgba(0,0,0,0.2);
        padding: 10px;
        border-radius: 8px;
        width: 400px; 
        height: 300px; 
        text-align: center;
        overflow: hidden;
    }
    .preview-container:hover .file-preview {
        display: block;
    }
    .file-preview iframe, .file-preview img {
        width: 100%;
        height: 100%;
        border: none;
        object-fit: contain;
    }
    .preview-placeholder {
        padding-top: 50px;
        color: #999;
    }
    .download-btn {
        background: darkgreen;
        color: white;
        padding: 5px 10px;
        border-radius: 4px;
        text-decoration: none;
        display: inline-block;
        font-size: 13px;
    }
    .download-btn:hover {
        background: #045d04;
    }
    </style>
</head>

<body>
    <?php
// ... after fetching $admin_name from DB
$signatoryFullName = $admin_name; 
$userRole = "Administrator";

include 'sidebar_admin.php';
?>
    <section class="home">
        <div class="text">
            <div class="dashboard-header">
                <h2>ADMIN DASHBOARD 
                    <span class="admin-name">
                        Welcome, <?php echo htmlspecialchars($admin_name ?: $_SESSION['username']); ?>
                    </span>
                </h2>
            </div>
        </div>

        <div class="dashboard-container">
            <div class="stats-container">
                <div class="stat-card">
                    <i class='bx bx-user icon'></i>
                    <div class="stat-info"><h3>Total Students</h3><p><?php echo $total_students; ?></p></div>
                </div>
                <div class="stat-card">
                    <i class='bx bx-id-card icon'></i>
                    <div class="stat-info"><h3>Signatories</h3><p><?php echo $total_signatories; ?></p></div>
                </div>
                <div class="stat-card">
                    <i class='bx bx-file icon'></i>
                    <div class="stat-info"><h3>Total Applications</h3><p><?php echo $total_applications; ?></p></div>
                </div>
                <div class="stat-card">
                    <i class='bx bx-time-five icon'></i>
                    <div class="stat-info"><h3>Pending</h3><p><?php echo $pending; ?></p></div>
                </div>
                <div class="stat-card">
                    <i class='bx bx-check-circle icon' style="color:green;"></i>
                    <div class="stat-info"><h3>Approved</h3><p><?php echo $approved; ?></p></div>
                </div>
                <div class="stat-card">
                    <i class='bx bx-error-circle icon' style="color:red;"></i>
                    <div class="stat-info"><h3>Requires Action</h3><p><?php echo $requires_action; ?></p></div>
                </div>
            </div>

            <h3 style="margin-top:30px;">Recent Applications</h3>
            <table>
                <tr>
                    <th>Student Name</th>
<th>Student ID</th>
<th>Signatory Position</th>
                    <th>Student Course</th> <th>Document</th>
                    <th>Status</th>
                    <th>Submitted At</th>
                </tr>
                <?php
                // UPDATED QUERY: Added student.course AS student_course
                $sql = "SELECT 
                            a.*, 
                            student.full_name AS student_fullname,
                            student.course AS student_course,
                            student.student_id AS student_id_num,
                            sig.signatory_type AS signatory_position,
                            sig.department AS signatory_dept,
                            sig.full_name AS signatory_fullname
                        FROM applications a
                        LEFT JOIN users student ON a.username = student.username
                        LEFT JOIN users sig ON a.signatory = sig.username
                        ORDER BY a.submitted_at DESC 
                        LIMIT 10";
                
                $result = $conn->query($sql);

                if ($result && $result->num_rows > 0) {
                    while ($row = $result->fetch_assoc()) {
                        $document_path = 'uploads/' . $row['document'];
                        $filename = htmlspecialchars($row['document']);
                        
                        // Display Logic
                        $display_student = !empty($row['student_fullname']) ? $row['student_fullname'] : $row['username'];
                        $display_course = !empty($row['student_course']) ? $row['student_course'] : $row['course'];
                        
                        // Get basic position
                        $display_signatory = !empty($row['signatory_position']) ? $row['signatory_position'] : $row['signatory'];
                        
                        // Check if Program Head and append Department
                        if (stripos($display_signatory, 'Program Head') !== false && !empty($row['signatory_dept'])) {
                            $display_signatory .= " (" . $row['signatory_dept'] . ")";
                        }
                        // Check if Class Adviser and append adviser name
                        if (stripos($display_signatory, 'Class Adviser') !== false && !empty($row['signatory_fullname'])) {
                            $display_signatory .= " - " . $row['signatory_fullname'];
                        }

                        // Determine if it's an image or PDF for preview.
                        $file_ext = pathinfo($filename, PATHINFO_EXTENSION);
                        $is_previewable = in_array(strtolower($file_ext), ['pdf', 'jpg', 'jpeg', 'png']);
                        
                        // Content for the preview pop-up
                        $preview_content = '';
                        if ($is_previewable) {
                            $preview_content = (strtolower($file_ext) == 'pdf') 
                                ? "<iframe src='{$document_path}'></iframe>" 
                                : "<img src='{$document_path}' alt='Submitted Document'>";
                        } else {
                            $preview_content = "<div class='preview-placeholder'>File type ({$file_ext}) cannot be previewed.</div>";
                        }
                        
                        $display_student_id = !empty($row['student_id_num']) ? $row['student_id_num'] : $row['username'];

                        echo "<tr>
                            <td>
                                <div class='preview-container'>
                                    " . htmlspecialchars($display_student) . "
                                    <div class='file-preview'>
                                        {$preview_content}
                                    </div>
                                </div>
                            </td>
                            <td>" . htmlspecialchars($display_student_id) . "</td>
                            <td>" . htmlspecialchars($display_signatory) . "</td>
                            <td>" . htmlspecialchars($display_course) . "</td>
                            <td>
                                <a href='{$document_path}' download='{$filename}' class='download-btn'>
                                    <i class='bx bx-download'></i> Download
                                </a>
                            </td>
                            <td>{$row['status']}</td>
                            <td>{$row['submitted_at']}</td>
                        </tr>";
                    }
                } else {
                    echo "<tr><td colspan='7'>No recent applications found.</td></tr>";
                }
                ?>
            </table>
        </div>
    </section>

   
    </body>
</html>