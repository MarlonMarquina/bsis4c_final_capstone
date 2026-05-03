<?php
session_start();
if (!isset($_SESSION['role']) || $_SESSION['role'] != "signatory") {
    header("Location: login.php");
    exit();
}

include 'conn.php';

// Get POST data
$format = $_POST['format'] ?? 'csv';
$date_filter = $_POST['date_filter'] ?? 'all';
$status_filter = $_POST['status_filter'] ?? 'all';
$course_filter = $_POST['course_filter'] ?? 'all';
$section_filter = $_POST['section_filter'] ?? 'all';
$year_filter = $_POST['year_filter'] ?? 'all';
$date_from = $_POST['date_from'] ?? '';
$date_to = $_POST['date_to'] ?? '';

$signatory = $_POST['signatory'] ?? $_SESSION['username'];
// Build query with filters
$where_clauses = ["a.signatory = ?", "(a.status IN ('Approved','Totally Rejected','Requires Action') OR (a.status = 'Rejected') OR (a.status = 'Pending' AND a.rejection_count > 0))"];
$params = [$signatory];
$types = "s";

// Date filter
switch ($date_filter) {
    case 'today':
        $where_clauses[] = "DATE(a.submitted_at) = CURDATE()";
        break;
    case 'yesterday':
        $where_clauses[] = "DATE(a.submitted_at) = DATE_SUB(CURDATE(), INTERVAL 1 DAY)";
        break;
    case 'this_week':
        $where_clauses[] = "YEARWEEK(a.submitted_at, 1) = YEARWEEK(CURDATE(), 1)";
        break;
    case 'last_week':
        $where_clauses[] = "YEARWEEK(a.submitted_at, 1) = YEARWEEK(DATE_SUB(CURDATE(), INTERVAL 1 WEEK), 1)";
        break;
    case 'this_month':
        $where_clauses[] = "MONTH(a.submitted_at) = MONTH(CURDATE()) AND YEAR(a.submitted_at) = YEAR(CURDATE())";
        break;
    case 'last_month':
        $where_clauses[] = "MONTH(a.submitted_at) = MONTH(DATE_SUB(CURDATE(), INTERVAL 1 MONTH)) AND YEAR(a.submitted_at) = YEAR(DATE_SUB(CURDATE(), INTERVAL 1 MONTH))";
        break;
    case 'this_year':
        $where_clauses[] = "YEAR(a.submitted_at) = YEAR(CURDATE())";
        break;
    case 'custom':
        if (!empty($date_from) && !empty($date_to)) {
            $where_clauses[] = "DATE(a.submitted_at) BETWEEN ? AND ?";
            $params[] = $date_from;
            $params[] = $date_to;
            $types .= "ss";
        }
        break;
}

// Status filter
if ($status_filter !== 'all') {
    if ($status_filter === 'Rejected') {
        // Catch all rejection-related statuses
        $where_clauses[] = "(a.status = 'Rejected' OR a.status = 'Totally Rejected' OR a.status = 'Requires Action')";
    } else {
        $where_clauses[] = "a.status = ?";
        $params[] = $status_filter;
        $types .= "s";
    }
}

// Course filter
if ($course_filter !== 'all') {
    $where_clauses[] = "a.course = ?";
    $params[] = $course_filter;
    $types .= "s";
}

// Section filter
if ($section_filter !== 'all') {
    $where_clauses[] = "u.section = ?";
    $params[] = $section_filter;
    $types .= "s";
}
// Year filter
if ($year_filter !== 'all') {
    $where_clauses[] = "u.year = ?";
    $params[] = $year_filter;
    $types .= "s";
}

$where_sql = implode(' AND ', $where_clauses);

// Join with users table to get section info
$sql = "SELECT a.*, u.section, u.year, u.full_name
        FROM applications a 
        LEFT JOIN users u ON a.username = u.username 
        WHERE $where_sql 
        ORDER BY a.submitted_at DESC";

$stmt = $conn->prepare($sql);
$stmt->bind_param($types, ...$params);
$stmt->execute();
$result = $stmt->get_result();

if (!$result || $result->num_rows === 0) {
    echo "<script>alert('No records found for the selected filters.'); window.location.href='signatory_history.php';</script>";
    exit();
}

// Generate filename
$timestamp = date('Y-m-d_His');
$filter_label = $date_filter === 'all' ? 'all-time' : str_replace(' ', '-', $date_filter);
$filename = "signatory_history_{$signatory}_{$filter_label}_{$timestamp}";

// Export based on format
if ($format === 'excel') {
    // Excel export using simple HTML table format (compatible with Excel)
    header('Content-Type: application/vnd.ms-excel');
    header('Content-Disposition: attachment; filename="' . $filename . '.xls"');
    header('Pragma: no-cache');
    header('Expires: 0');
    
    echo '<?xml version="1.0"?>';
    echo '<?mso-application progid="Excel.Sheet"?>';
    echo '<Workbook xmlns="urn:schemas-microsoft-com:office:spreadsheet"
        xmlns:o="urn:schemas-microsoft-com:office:office"
        xmlns:x="urn:schemas-microsoft-com:office:excel"
        xmlns:ss="urn:schemas-microsoft-com:office:spreadsheet"
        xmlns:html="http://www.w3.org/TR/REC-html40">';
    
    echo '<Worksheet ss:Name="Processed Applications">';
    echo '<Table>';
    
    // Header row
    echo '<Row>';
    echo '<Cell><Data ss:Type="String">Student Name</Data></Cell>';
    echo '<Cell><Data ss:Type="String">Student ID</Data></Cell>';
    echo '<Cell><Data ss:Type="String">Course</Data></Cell>';
    echo '<Cell><Data ss:Type="String">Section</Data></Cell>';
    echo '<Cell><Data ss:Type="String">Year Level</Data></Cell>';
    echo '<Cell><Data ss:Type="String">Document</Data></Cell>';
    echo '<Cell><Data ss:Type="String">Status</Data></Cell>';
    echo '<Cell><Data ss:Type="String">Remarks</Data></Cell>';
    echo '<Cell><Data ss:Type="String">Date Submitted</Data></Cell>';
    echo '<Cell><Data ss:Type="String">Date Reviewed</Data></Cell>';
    echo '</Row>';
    
    // Data rows
    while ($row = $result->fetch_assoc()) {
        echo '<Row>';
        echo '<Cell><Data ss:Type="String">' . htmlspecialchars($row['full_name'] ?? $row['username']) . '</Data></Cell>';
        echo '<Cell><Data ss:Type="String">' . htmlspecialchars($row['username']) . '</Data></Cell>';
        echo '<Cell><Data ss:Type="String">' . htmlspecialchars($row['course']) . '</Data></Cell>';
        echo '<Cell><Data ss:Type="String">' . htmlspecialchars($row['section'] ?? '---') . '</Data></Cell>';
        echo '<Cell><Data ss:Type="String">' . htmlspecialchars($row['year'] ?? '---') . '</Data></Cell>';
        echo '<Cell><Data ss:Type="String">' . htmlspecialchars($row['document']) . '</Data></Cell>';
        echo '<Cell><Data ss:Type="String">' . htmlspecialchars($row['status']) . '</Data></Cell>';
        echo '<Cell><Data ss:Type="String">' . htmlspecialchars($row['rejection_reason'] ?? $row['remarks'] ?? '---') . '</Data></Cell>';
        echo '<Cell><Data ss:Type="String">' . date('M d, Y h:i A', strtotime($row['submitted_at'])) . '</Data></Cell>';
        echo '<Cell><Data ss:Type="String">' . ($row['reviewed_at'] ? date('M d, Y h:i A', strtotime($row['reviewed_at'])) : '---') . '</Data></Cell>';
        echo '</Row>';
    }
    
    echo '</Table>';
    echo '</Worksheet>';
    echo '</Workbook>';
    
} else {
    // CSV export
    header('Content-Type: text/csv');
    header('Content-Disposition: attachment; filename="' . $filename . '.csv"');
    header('Pragma: no-cache');
    header('Expires: 0');
    
    $output = fopen('php://output', 'w');
    
    // Add BOM for Excel UTF-8 support
    fprintf($output, chr(0xEF).chr(0xBB).chr(0xBF));
    
    // Header row
    fputcsv($output, [
        'Student Name',
        'Student ID',
        'Course',
        'Section',
        'Year Level',
        'Document',
        'Status',
        'Remarks',
        'Date Submitted',
        'Date Reviewed'
    ]);
    
    // Data rows
    while ($row = $result->fetch_assoc()) {
        fputcsv($output, [
            $row['full_name'] ?? $row['username'],
            $row['username'],
            $row['course'],
            $row['section'] ?? '---',
            $row['year'] ?? '---',
            $row['document'],
           $row['status'],
            $row['rejection_reason'] ?? $row['remarks'] ?? '---',
            date('M d, Y h:i A', strtotime($row['submitted_at'])),
            $row['reviewed_at'] ? date('M d, Y h:i A', strtotime($row['reviewed_at'])) : '---'
        ]);
    }
    
    fclose($output);
}

$stmt->close();
$conn->close();
exit();
?>