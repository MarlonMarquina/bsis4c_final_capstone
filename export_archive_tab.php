<?php
include 'conn.php';
session_start();
date_default_timezone_set('Asia/Manila');
if (!isset($_SESSION['role']) || $_SESSION['role'] != "admin") {
    die("Access Denied.");
}

require 'vendor/autoload.php';
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;

$sem  = $_GET['sem']  ?? '';
$sy   = $_GET['sy']   ?? '';
$tab  = $_GET['tab']  ?? 'students';

if (empty($sem) || empty($sy)) die("Missing parameters.");

$sem_safe = $conn->real_escape_string($sem);
$sy_safe  = $conn->real_escape_string($sy);

$spreadsheet = new Spreadsheet();
$sheet = $spreadsheet->getActiveSheet();

// Header style
$headerStyle = [
    'font' => ['bold' => true, 'color' => ['rgb' => 'FFFFFF'], 'size' => 11],
    'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => '1a3409']],
    'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER, 'vertical' => Alignment::VERTICAL_CENTER],
    'borders' => ['allBorders' => ['borderStyle' => Border::BORDER_THIN, 'color' => ['rgb' => 'FFFFFF']]]
];

$rowStyle = [
    'borders' => ['allBorders' => ['borderStyle' => Border::BORDER_THIN, 'color' => ['rgb' => 'DDDDDD']]],
    'alignment' => ['vertical' => Alignment::VERTICAL_CENTER]
];

if ($tab === 'students') {

    $sheet->setTitle('Student Data');
    $filename = "Students_{$sy}_{$sem}.xlsx";

    // Match bulk import template format exactly
    $headers = ['Username', 'Full Name', 'Email', 'Course', 'Year', 'Section', 'Password'];
    $sheet->fromArray($headers, null, 'A1');
    $sheet->getStyle('A1:G1')->applyFromArray($headerStyle);

    // Add instruction row like original template
    $sheet->setCellValue('A2', 'INSTRUCTIONS: Do not edit the header row. Password column is optional — leave blank to use default @Student01.');
    $sheet->mergeCells('A2:G2');
    $sheet->getStyle('A2')->applyFromArray([
        'font' => ['italic' => true, 'color' => ['rgb' => '888888'], 'size' => 9],
        'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => 'F9F9F9']],
    ]);

    $data = $conn->query("
        SELECT username, full_name, email, course, year, section
        FROM archived_user_accounts
        WHERE semester='$sem_safe' AND school_year='$sy_safe' AND role='student'
        ORDER BY course ASC, full_name ASC
    ");

    $row = 3;
    while ($d = $data->fetch_assoc()) {
        $sheet->setCellValue("A$row", $d['username']);
        $sheet->setCellValue("B$row", $d['full_name']);
        $sheet->setCellValue("C$row", $d['email']);
        $sheet->setCellValue("D$row", $d['course']);
        $sheet->setCellValue("E$row", $d['year']);
        $sheet->setCellValue("F$row", $d['section']);
        $sheet->setCellValue("G$row", ''); // password blank for security
        $sheet->getStyle("A$row:G$row")->applyFromArray($rowStyle);
        $sheet->getStyle("A$row:G$row")->getFill()->setFillType(Fill::FILL_SOLID)
            ->getStartColor()->setRGB($row % 2 === 0 ? 'F4F9F4' : 'FFFFFF');
        $row++;
    }

    foreach (range('A', 'G') as $col) {
        $sheet->getColumnDimension($col)->setAutoSize(true);
    }

} elseif ($tab === 'advisers') {

    $sheet->setTitle('Class Adviser Data');
    $filename = "ClassAdvisers_{$sy}_{$sem}.xlsx";

    // Match adviser bulk import template format exactly
    $headers = ['Username', 'Full Name', 'Email', 'Signatory Type', 'Course', 'Year-Section', 'Password'];
    $sheet->fromArray($headers, null, 'A1');
    $sheet->getStyle('A1:G1')->applyFromArray($headerStyle);

    $sheet->setCellValue('A2', 'INSTRUCTIONS: Signatory Type must be "Class Adviser". Year-Section format: 1st Year|A (separate multiple with comma).');
    $sheet->mergeCells('A2:G2');
    $sheet->getStyle('A2')->applyFromArray([
        'font' => ['italic' => true, 'color' => ['rgb' => '888888'], 'size' => 9],
        'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => 'F9F9F9']],
    ]);

    $data = $conn->query("
        SELECT username, full_name, email, signatory_type, department, section
        FROM archived_user_accounts
        WHERE semester='$sem_safe' AND school_year='$sy_safe'
        AND role='signatory' AND signatory_type='Class Adviser'
        ORDER BY department ASC, full_name ASC
    ");

    $row = 3;
    while ($d = $data->fetch_assoc()) {
        // Convert stored format back to import format
        // stored: BSIS|1st Year|A,BSIS|2nd Year|B → export: 1st Year|A,2nd Year|B
        $raw_sections = $d['section'] ?? '';
        $parts = explode(',', $raw_sections);
        $formatted = [];
        foreach ($parts as $p) {
            $bits = explode('|', trim($p));
            if (count($bits) === 3) {
                $formatted[] = $bits[1] . '|' . $bits[2]; // Year|Section only
            }
        }
        $section_export = implode(',', $formatted);

        $sheet->setCellValue("A$row", $d['username']);
        $sheet->setCellValue("B$row", $d['full_name']);
        $sheet->setCellValue("C$row", $d['email']);
        $sheet->setCellValue("D$row", 'Class Adviser');
        $sheet->setCellValue("E$row", $d['department']);
        $sheet->setCellValue("F$row", $section_export);
        $sheet->setCellValue("G$row", ''); // password blank for security
        $sheet->getStyle("A$row:G$row")->applyFromArray($rowStyle);
        $sheet->getStyle("A$row:G$row")->getFill()->setFillType(Fill::FILL_SOLID)
            ->getStartColor()->setRGB($row % 2 === 0 ? 'F4F9F4' : 'FFFFFF');
        $row++;
    }

    foreach (range('A', 'G') as $col) {
        $sheet->getColumnDimension($col)->setAutoSize(true);
    }

} elseif ($tab === 'applications') {

    $sheet->setTitle('Applications');
    $filename = "Applications_{$sy}_{$sem}.xlsx";

    $headers = ['Student Username', 'Signatory', 'Course', 'Requirement', 'Status', 'Rejection Count', 'Submitted At', 'Reviewed At'];
    $sheet->fromArray($headers, null, 'A1');
    $sheet->getStyle('A1:H1')->applyFromArray($headerStyle);

    $data = $conn->query("
        SELECT aa.username, aa.signatory, aa.course, rl.requirement_name,
               aa.status, aa.rejection_count, aa.submitted_at, aa.reviewed_at
        FROM archived_applications aa
        LEFT JOIN requirement_library rl ON aa.requirement_id = rl.id
        WHERE aa.semester='$sem_safe' AND aa.school_year='$sy_safe'
        ORDER BY aa.submitted_at DESC
    ");

    $row = 2;
    while ($d = $data->fetch_assoc()) {
        $sheet->setCellValue("A$row", $d['username']);
        $sheet->setCellValue("B$row", $d['signatory']);
        $sheet->setCellValue("C$row", $d['course']);
        $sheet->setCellValue("D$row", $d['requirement_name'] ?? 'N/A');
        $sheet->setCellValue("E$row", $d['status']);
        $sheet->setCellValue("F$row", (int)$d['rejection_count']);
        $sheet->setCellValue("G$row", $d['submitted_at'] ?? '');
        $sheet->setCellValue("H$row", $d['reviewed_at'] ?? '');
        $sheet->getStyle("A$row:H$row")->applyFromArray($rowStyle);
        $sheet->getStyle("A$row:H$row")->getFill()->setFillType(Fill::FILL_SOLID)
            ->getStartColor()->setRGB($row % 2 === 0 ? 'F4F9F4' : 'FFFFFF');
        $row++;
    }

    foreach (range('A', 'H') as $col) {
        $sheet->getColumnDimension($col)->setAutoSize(true);
    }

} elseif ($tab === 'requirements') {

    $sheet->setTitle('Requirements');
    $filename = "Requirements_{$sy}_{$sem}.xlsx";

    $headers = ['Signatory', 'Type', 'Course', 'Requirement', 'Year Level', 'Sections', 'Configured'];
    $sheet->fromArray($headers, null, 'A1');
    $sheet->getStyle('A1:G1')->applyFromArray($headerStyle);

    $data = $conn->query("
        SELECT u.full_name AS signatory_name, u.signatory_type,
               c.course_name, rl.requirement_name,
               acr.year_level, acr.sections, acr.requirements_configured
        FROM archived_course_requirements acr
        LEFT JOIN courses c ON acr.course_id = c.id
        LEFT JOIN users u ON acr.signatory_id = u.id
        LEFT JOIN requirement_library rl ON acr.requirement_id = rl.id
        WHERE acr.semester='$sem_safe' AND acr.school_year='$sy_safe'
        ORDER BY c.course_name ASC
    ");

    $row = 2;
    while ($d = $data->fetch_assoc()) {
        $sheet->setCellValue("A$row", $d['signatory_name'] ?? '—');
        $sheet->setCellValue("B$row", $d['signatory_type'] ?? '—');
        $sheet->setCellValue("C$row", $d['course_name'] ?? '—');
        $sheet->setCellValue("D$row", $d['requirement_name'] ?? 'No Requirement');
        $sheet->setCellValue("E$row", $d['year_level'] ?? '—');
        $sheet->setCellValue("F$row", $d['sections'] ?? '—');
        $sheet->setCellValue("G$row", $d['requirements_configured'] ? 'Yes' : 'No');
        $sheet->getStyle("A$row:G$row")->applyFromArray($rowStyle);
        $sheet->getStyle("A$row:G$row")->getFill()->setFillType(Fill::FILL_SOLID)
            ->getStartColor()->setRGB($row % 2 === 0 ? 'F4F9F4' : 'FFFFFF');
        $row++;
    }

    foreach (range('A', 'G') as $col) {
        $sheet->getColumnDimension($col)->setAutoSize(true);
    }

} elseif ($tab === 'history') {

    $sheet->setTitle('Signatory Log');
    $filename = "SignatoryLog_{$sy}_{$sem}.xlsx";

    $headers = ['Signatory', 'Student Username', 'Student Name', 'Action', 'Reason', 'Remarks', 'Date'];
    $sheet->fromArray($headers, null, 'A1');
    $sheet->getStyle('A1:G1')->applyFromArray($headerStyle);

    $data = $conn->query("
        SELECT ash.signatory_username, ash.student_user,
               ua.full_name AS student_name,
               ash.action, ash.reason, ash.remarks, ash.created_at
        FROM archived_signatory_history ash
        LEFT JOIN archived_user_accounts ua
            ON ash.student_user = ua.username
            AND ua.semester = '$sem_safe'
            AND ua.school_year = '$sy_safe'
            AND ua.role = 'student'
        WHERE ash.semester='$sem_safe' AND ash.school_year='$sy_safe'
        ORDER BY ash.created_at DESC
    ");

    $row = 2;
    while ($d = $data->fetch_assoc()) {
        $sheet->setCellValue("A$row", $d['signatory_username']);
        $sheet->setCellValue("B$row", $d['student_user']);
        $sheet->setCellValue("C$row", $d['student_name'] ?? '—');
        $sheet->setCellValue("D$row", $d['action']);
        $sheet->setCellValue("E$row", $d['reason'] ?? '');
        $sheet->setCellValue("F$row", $d['remarks'] ?? '');
        $sheet->setCellValue("G$row", $d['created_at'] ?? '');
        $sheet->getStyle("A$row:G$row")->applyFromArray($rowStyle);
        $sheet->getStyle("A$row:G$row")->getFill()->setFillType(Fill::FILL_SOLID)
            ->getStartColor()->setRGB($row % 2 === 0 ? 'F4F9F4' : 'FFFFFF');
        $row++;
    }

    foreach (range('A', 'G') as $col) {
        $sheet->getColumnDimension($col)->setAutoSize(true);
    }

} else {
    die("Invalid tab.");
}

// Output
header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
header('Content-Disposition: attachment; filename="' . $filename . '"');
header('Cache-Control: max-age=0');

$writer = new Xlsx($spreadsheet);
$writer->save('php://output');
exit;
?>