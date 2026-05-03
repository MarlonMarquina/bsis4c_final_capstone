<?php
ob_start();

session_start();
if (!isset($_SESSION['role']) || $_SESSION['role'] != "admin") {
    ob_end_clean();
    exit('Unauthorized');
}

include('conn.php');
require 'vendor/autoload.php';

use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Alignment;

$spreadsheet = new Spreadsheet();

// ===================================
// INSTRUCTIONS SHEET
// ===================================
$instructionSheet = $spreadsheet->createSheet(0);
$instructionSheet->setTitle('Instructions');

// Title row
$instructionSheet->setCellValue('A1', 'STUDENT IMPORT TEMPLATE - INSTRUCTIONS');
$instructionSheet->mergeCells('A1:D1');
$instructionSheet->getStyle('A1')->getFont()->setName('Arial')->setBold(true)->setSize(16)->getColor()->setARGB('FFFFFFFF');
$instructionSheet->getStyle('A1')->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
$instructionSheet->getStyle('A1')->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setARGB('FF2D5016');
$instructionSheet->getRowDimension(1)->setRowHeight(30);

// How to use header
$instructionSheet->setCellValue('A3', 'HOW TO USE THIS TEMPLATE:');
$instructionSheet->getStyle('A3')->getFont()->setName('Arial')->setBold(true)->setSize(12);

$instructions = [
    '1. Fill in the "Student Data" sheet with your student information',
    '2. Follow the column format exactly as shown in the sample data',
    '3. Do NOT modify the header row (Row 1)',
    '4. Save the file and upload it through the Import feature',
    '',
    'REQUIRED COLUMNS:',
    '• Username - Unique student ID or username (e.g., Username01)',
    '• Full Name - Student\'s complete name',
    '• Email - Valid email address (must be unique)',
    '• Course - Exact course name from the reference table below (e.g., BSIT)',
    '• Year - Year level (e.g., 1st Year, 2nd Year, 3rd Year, 4th Year)',
    '• Section - Section letter from the reference table below (e.g., A)',
    '',
    'OPTIONAL COLUMNS:',
    '• Password - Initial password (leave blank for default: @Student01)',
    '',
    'IMPORTANT NOTES:',
    '✓ First row contains headers and will be skipped during import',
    '✓ Username and Email must be unique across all students',
    '✓ Course name must match EXACTLY as shown in the Course Reference table below',
    '✓ Year must match exactly: 1st Year, 2nd Year, 3rd Year, 4th Year, or 5th Year',
    '✓ Section must match exactly as shown in the Section Reference table below',
    '✓ Empty Password cells will use the default password: @Student01',
    '✓ Delete sample data rows before importing your own data',
];

$row = 4;
foreach ($instructions as $instruction) {
    $instructionSheet->setCellValue("A{$row}", $instruction);
    $isHeader = (strpos($instruction, ':') !== false && strpos($instruction, '•') === false && strpos($instruction, '✓') === false);
    $instructionSheet->getStyle("A{$row}")->getFont()->setName('Arial')->setSize(11)->setBold($isHeader);
    $row++;
}

$instructionSheet->getColumnDimension('A')->setWidth(100);

// ===================================
// COURSE REFERENCE TABLE
// ===================================
$row += 2;
$instructionSheet->setCellValue("A{$row}", 'COURSE REFERENCE - Use exact values in the Course column');
$instructionSheet->mergeCells("A{$row}:C{$row}");
$instructionSheet->getStyle("A{$row}")->getFont()->setName('Arial')->setBold(true)->setSize(12)->getColor()->setARGB('FFFFFFFF');
$instructionSheet->getStyle("A{$row}")->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setARGB('FF2D5016');
$row++;

$courseHeaders = ['Course Name', 'Duration', 'Type This Exact Value in Column D'];
$col = 'A';
foreach ($courseHeaders as $header) {
    $instructionSheet->setCellValue("{$col}{$row}", $header);
    $instructionSheet->getStyle("{$col}{$row}")->getFont()->setName('Arial')->setBold(true)->setSize(11);
    $instructionSheet->getStyle("{$col}{$row}")->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setARGB('FFE0E0E0');
    $col++;
}
$row++;

try {
    $courseResult = $conn->query("SELECT course_name, duration FROM courses ORDER BY course_name ASC");
    if ($courseResult && $courseResult->num_rows > 0) {
        while ($course = $courseResult->fetch_assoc()) {
            $instructionSheet->setCellValue("A{$row}", $course['course_name']);
            $instructionSheet->setCellValue("B{$row}", $course['duration'] . ' Year(s)');
            $instructionSheet->setCellValue("C{$row}", $course['course_name']);
            $instructionSheet->getStyle("A{$row}:C{$row}")->getFont()->setName('Arial')->setSize(11);
            $instructionSheet->getStyle("C{$row}")->getFont()->setBold(true)->getColor()->setARGB('FF2D5016');
            $row++;
        }
    } else {
        $instructionSheet->setCellValue("A{$row}", 'No courses available. Please add courses first.');
        $instructionSheet->mergeCells("A{$row}:C{$row}");
        $instructionSheet->getStyle("A{$row}")->getFont()->setName('Arial')->setSize(11)->setItalic(true);
        $row++;
    }
} catch (Exception $e) {
    $instructionSheet->setCellValue("A{$row}", 'Unable to load courses from database.');
    $instructionSheet->mergeCells("A{$row}:C{$row}");
    $instructionSheet->getStyle("A{$row}")->getFont()->setName('Arial')->setSize(11)->setItalic(true)->getColor()->setARGB('FFFF0000');
    $row++;
}

// ===================================
// SECTION REFERENCE TABLE
// ===================================
$row += 2;
$instructionSheet->setCellValue("A{$row}", 'SECTION REFERENCE - Available sections grouped by Course and Year');
$instructionSheet->mergeCells("A{$row}:C{$row}");
$instructionSheet->getStyle("A{$row}")->getFont()->setName('Arial')->setBold(true)->setSize(12)->getColor()->setARGB('FFFFFFFF');
$instructionSheet->getStyle("A{$row}")->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setARGB('FF3498DB');
$row++;

$sectionHeaders = ['Course', 'Year', 'Available Sections (use exact letter in Column F)'];
$col = 'A';
foreach ($sectionHeaders as $header) {
    $instructionSheet->setCellValue("{$col}{$row}", $header);
    $instructionSheet->getStyle("{$col}{$row}")->getFont()->setName('Arial')->setBold(true)->setSize(11);
    $instructionSheet->getStyle("{$col}{$row}")->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setARGB('FFE0E0E0');
    $col++;
}
$row++;

try {
    $sectionResult = $conn->query("
        SELECT c.course_name, s.year, 
               GROUP_CONCAT(s.section_name ORDER BY s.section_name ASC SEPARATOR ', ') AS sections
        FROM sections s
        JOIN courses c ON s.course_id = c.id
        GROUP BY c.course_name, s.year
        ORDER BY c.course_name ASC, s.year ASC
    ");
    if ($sectionResult && $sectionResult->num_rows > 0) {
        while ($section = $sectionResult->fetch_assoc()) {
            $instructionSheet->setCellValue("A{$row}", $section['course_name']);
            $instructionSheet->setCellValue("B{$row}", $section['year']);
            $instructionSheet->setCellValue("C{$row}", $section['sections']);
            $instructionSheet->getStyle("A{$row}:C{$row}")->getFont()->setName('Arial')->setSize(11);
            $row++;
        }
    } else {
        $instructionSheet->setCellValue("A{$row}", 'No sections available. Please add sections in Manage Students first.');
        $instructionSheet->mergeCells("A{$row}:C{$row}");
        $instructionSheet->getStyle("A{$row}")->getFont()->setName('Arial')->setSize(11)->setItalic(true);
        $row++;
    }
} catch (Exception $e) {
    $instructionSheet->setCellValue("A{$row}", 'Unable to load sections from database.');
    $instructionSheet->mergeCells("A{$row}:C{$row}");
    $instructionSheet->getStyle("A{$row}")->getFont()->setName('Arial')->setSize(11)->setItalic(true)->getColor()->setARGB('FFFF0000');
    $row++;
}

$instructionSheet->getColumnDimension('A')->setWidth(20);
$instructionSheet->getColumnDimension('B')->setWidth(15);
$instructionSheet->getColumnDimension('C')->setWidth(55);

// ===================================
// STUDENT DATA SHEET
// ===================================
$dataSheet = $spreadsheet->createSheet(1);
$dataSheet->setTitle('Student Data');

$headers = ['Username', 'Full Name', 'Email', 'Course', 'Year', 'Section', 'Password'];
$col = 'A';
foreach ($headers as $header) {
    $dataSheet->setCellValue("{$col}1", $header);
    $dataSheet->getStyle("{$col}1")->getFont()->setName('Arial')->setBold(true)->setSize(11)->getColor()->setARGB('FFFFFFFF');
    $dataSheet->getStyle("{$col}1")->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setARGB('FF2D5016');
    $dataSheet->getStyle("{$col}1")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
    $dataSheet->getStyle("{$col}1")->getBorders()->getAllBorders()->setBorderStyle(Border::BORDER_THIN);
    $col++;
}

$sampleData = [
    ['Username-00001', 'Juan dela Cruz', 'juan.delacruz@school.edu', 'BSIS', '1st Year', 'A', '@Student01'],
    ['Username-00002', 'Maria Santos',   'maria.santos@school.edu',  'BSCA', '2nd Year', 'B', 'CustomPass123!'],
    ['Username-00003', 'Pedro Reyes',    'pedro.reyes@school.edu',   'BSOM', '3rd Year', 'A', ''],
];

$dataRow = 2;
foreach ($sampleData as $data) {
    $col = 'A';
    foreach ($data as $value) {
        $dataSheet->setCellValue("{$col}{$dataRow}", $value);
        $dataSheet->getStyle("{$col}{$dataRow}")->getFont()->setName('Arial')->setSize(11);
        $dataSheet->getStyle("{$col}{$dataRow}")->getBorders()->getAllBorders()->setBorderStyle(Border::BORDER_THIN);
        $col++;
    }
    $dataSheet->getStyle("A{$dataRow}:G{$dataRow}")->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setARGB('FFFFF4E6');
    $dataRow++;
}

$dataSheet->getColumnDimension('A')->setWidth(18);
$dataSheet->getColumnDimension('B')->setWidth(25);
$dataSheet->getColumnDimension('C')->setWidth(30);
$dataSheet->getColumnDimension('D')->setWidth(15);
$dataSheet->getColumnDimension('E')->setWidth(15);
$dataSheet->getColumnDimension('F')->setWidth(12);
$dataSheet->getColumnDimension('G')->setWidth(20);

$dataSheet->freezePane('A2');

// Set active sheet to Instructions
$spreadsheet->setActiveSheetIndex(0);

$conn->close();

ob_end_clean();

header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
header('Content-Disposition: attachment; filename="Student_Import_Template.xlsx"');
header('Cache-Control: max-age=0');

$writer = new Xlsx($spreadsheet);
$writer->save('php://output');
exit;
?>