<?php
header('Content-Type: application/json');
session_start();
include 'conn.php';

$course = $_GET['course'] ?? '';
$year = $_GET['year'] ?? '';

error_log("GET_SECTIONS - Course: $course, Year: $year");

if (empty($course) || empty($year)) {
    echo json_encode([]);
    exit;
}

// Get signatory info
$signatory = $_SESSION['username'] ?? '';
$sig_stmt = $conn->prepare("SELECT signatory_type, section, department FROM users WHERE username = ? AND role = 'signatory' LIMIT 1");
$sig_stmt->bind_param("s", $signatory);
$sig_stmt->execute();
$sig_data = $sig_stmt->get_result()->fetch_assoc();
$sig_stmt->close();

error_log("SIGNATORY DATA: " . json_encode($sig_data));

$sig_type = strtolower($sig_data['signatory_type'] ?? '');
$is_class_adviser = (strpos($sig_type, 'class adviser') !== false);

error_log("IS CLASS ADVISER: " . ($is_class_adviser ? 'YES' : 'NO'));

// Parse adviser's assigned sections
$allowed_sections = [];
if ($is_class_adviser && !empty($sig_data['section'])) {
    $classes = explode(',', $sig_data['section']);
    error_log("RAW SECTIONS: " . $sig_data['section']);
    
    foreach ($classes as $class) {
        $parts = explode('|', trim($class));
        error_log("PARTS: " . json_encode($parts));
        
        if (count($parts) == 3) {
            $c = trim($parts[0]);
            $y = trim($parts[1]);
            $s = trim($parts[2]);
            
            error_log("PARSED - Course: $c, Year: $y, Section: $s");
            error_log("MATCH? Course='$c'=='$course' AND Year='$y'=='$year'");
            
            if ($c === $course && $y === $year) {
                $allowed_sections[] = $s;
                error_log("MATCH FOUND! Added section: $s");
            }
        }
    }
}

error_log("ALLOWED SECTIONS: " . json_encode($allowed_sections));

// Fetch all sections from database
$sections = [];
if ($year === 'All Years') {
    $stmt = $conn->prepare("
        SELECT DISTINCT u.section 
        FROM users u
        WHERE u.role = 'student' 
        AND u.course = ?
        AND u.section IS NOT NULL 
        AND u.section != ''
        ORDER BY u.section ASC
    ");
    $stmt->bind_param("s", $course);
} else {
    $stmt = $conn->prepare("
        SELECT DISTINCT u.section 
        FROM users u
        WHERE u.role = 'student' 
        AND u.course = ?
        AND u.year = ?
        AND u.section IS NOT NULL 
        AND u.section != ''
        ORDER BY u.section ASC
    ");
    $stmt->bind_param("ss", $course, $year);
}

$stmt->execute();
$result = $stmt->get_result();

while ($row = $result->fetch_assoc()) {
    $section = $row['section'];
    error_log("DB SECTION: $section");
    
    // Filter: If class adviser, only return their assigned sections
    if ($is_class_adviser) {
        if (in_array($section, $allowed_sections)) {
            $sections[] = $section;
            error_log("SECTION ALLOWED: $section");
        } else {
            error_log("SECTION BLOCKED: $section");
        }
    } else {
        $sections[] = $section;
    }
}

$stmt->close();

sort($sections, SORT_NATURAL);

error_log("FINAL SECTIONS RETURNED: " . json_encode($sections));

echo json_encode($sections);
?>