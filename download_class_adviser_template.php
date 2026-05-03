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
$instructionSheet->setCellValue('A1', 'CLASS ADVISER IMPORT TEMPLATE - INSTRUCTIONS');
$instructionSheet->mergeCells('A1:D1');
$instructionSheet->getStyle('A1')->getFont()->setName('Arial')->setBold(true)->setSize(16)->getColor()->setARGB('FFFFFFFF');
$instructionSheet->getStyle('A1')->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
$instructionSheet->getStyle('A1')->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setARGB('FF2D5016');
$instructionSheet->getRowDimension(1)->setRowHeight(30);

// How to use header
$instructionSheet->setCellValue('A3', 'HOW TO USE THIS TEMPLATE:');
$instructionSheet->getStyle('A3')->getFont()->setName('Arial')->setBold(true)->setSize(12);

$instructions = [
    '1. Fill in the "Class Adviser Data" sheet with adviser information',
    '2. Follow the column format exactly as shown in the sample data',
    '3. Do NOT modify the header row (Row 1)',
    '4. Save the file and upload it through the Import feature',
    '',
    'REQUIRED COLUMNS:',
    '• Username - Unique username for the adviser (e.g., adviser01)',
    '• Full Name - Adviser\'s complete name',
    '• Email - Valid email address (must be unique)',
    '• Signatory Type - MUST be "Class Adviser" (case-insensitive)',
    '• Course - Course/Department they will advise (e.g., BSIT)',
    '• Year-Section - Multiple formats accepted:',
    '  → Standard: "1st Year|A" or "2nd Year|B"',
    '  → With dash: "1-A", "2-B", "3-C"',
    '  → Compact: "1A", "2B", "3C"',
    '  → Multiple: Comma-separated (e.g., "1-A,2-B" or "3A,4B")',
    '',
    'OPTIONAL COLUMNS:',
    '• Password - Initial password (leave blank for default: @Signatory01)',
    '',
    'IMPORTANT NOTES:',
    '✓ First row contains headers and will be skipped during import',
    '✓ Username and Email must be unique',
    '✓ Signatory Type must be exactly "Class Adviser"',
    '✓ Course must match exactly as shown in the Course Reference table below',
    '✓ Year-Section format is strict: use exact format from Section Reference table',
    '✓ One adviser can handle multiple year-sections (comma-separated)',
    '✓ Empty Password cells will use default: @Signatory01',
    '✓ System will check for conflicts (if year-section already has an adviser)',
    '✓ Delete sample data rows before importing',
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
$instructionSheet->mergeCells("A{$row}:B{$row}");
$instructionSheet->getStyle("A{$row}")->getFont()->setName('Arial')->setBold(true)->setSize(12)->getColor()->setARGB('FFFFFFFF');
$instructionSheet->getStyle("A{$row}")->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setARGB('FF2D5016');
$row++;

$courseHeaders = ['Course Name', 'Type This Exact Value in Column E'];
$col = 'A';
foreach ($courseHeaders as $header) {
    $instructionSheet->setCellValue("{$col}{$row}", $header);
    $instructionSheet->getStyle("{$col}{$row}")->getFont()->setName('Arial')->setBold(true)->setSize(11);
    $instructionSheet->getStyle("{$col}{$row}")->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setARGB('FFE0E0E0');
    $col++;
}
$row++;

try {
    $courseResult = $conn->query("SELECT DISTINCT course_name FROM courses ORDER BY course_name ASC");
    if ($courseResult && $courseResult->num_rows > 0) {
        while ($course = $courseResult->fetch_assoc()) {
            $instructionSheet->setCellValue("A{$row}", $course['course_name']);
            $instructionSheet->setCellValue("B{$row}", $course['course_name']);
            $instructionSheet->getStyle("A{$row}:B{$row}")->getFont()->setName('Arial')->setSize(11);
            $instructionSheet->getStyle("B{$row}")->getFont()->setBold(true)->getColor()->setARGB('FF2D5016');
            $row++;
        }
    } else {
        $instructionSheet->setCellValue("A{$row}", 'No courses available. Please add courses first.');
        $instructionSheet->mergeCells("A{$row}:B{$row}");
        $row++;
    }
} catch (Exception $e) {
    $instructionSheet->setCellValue("A{$row}", 'Unable to load courses from database.');
    $instructionSheet->mergeCells("A{$row}:B{$row}");
    $row++;
}

// ===================================
// SECTION REFERENCE TABLE
// ===================================
$row += 2;
$instructionSheet->setCellValue("A{$row}", 'SECTION REFERENCE - Available year-sections grouped by Course');
$instructionSheet->mergeCells("A{$row}:C{$row}");
$instructionSheet->getStyle("A{$row}")->getFont()->setName('Arial')->setBold(true)->setSize(12)->getColor()->setARGB('FFFFFFFF');
$instructionSheet->getStyle("A{$row}")->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setARGB('FF3498DB');
$row++;

$sectionHeaders = ['Course', 'Year', 'Format for Column F (Year-Section)'];
$col = 'A';
foreach ($sectionHeaders as $header) {
    $instructionSheet->setCellValue("{$col}{$row}", $header);
    $instructionSheet->getStyle("{$col}{$row}")->getFont()->setName('Arial')->setBold(true)->setSize(11);
    $instructionSheet->getStyle("{$col}{$row}")->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setARGB('FFE0E0E0');
    $col++;
}
$row++;

try {
    // Fetch year-section data from students
    $sectionResult = $conn->query("
        SELECT DISTINCT u.course, u.year, u.section
        FROM users u
        INNER JOIN courses c ON u.course = c.course_name
        WHERE u.role = 'student' 
        AND u.year IS NOT NULL 
        AND u.year != '' 
        AND u.section IS NOT NULL 
        AND u.section != ''
        ORDER BY u.course ASC, u.year ASC, u.section ASC
    ");
    
    if ($sectionResult && $sectionResult->num_rows > 0) {
        while ($section = $sectionResult->fetch_assoc()) {
            $instructionSheet->setCellValue("A{$row}", $section['course']);
            $instructionSheet->setCellValue("B{$row}", $section['year']);
            $instructionSheet->setCellValue("C{$row}", $section['year'] . '|' . $section['section']);
            $instructionSheet->getStyle("A{$row}:C{$row}")->getFont()->setName('Arial')->setSize(11);
            $instructionSheet->getStyle("C{$row}")->getFont()->setBold(true)->getColor()->setARGB('FF2D5016');
            $row++;
        }
    } else {
        $instructionSheet->setCellValue("A{$row}", 'No sections available. Students must be enrolled first.');
        $instructionSheet->mergeCells("A{$row}:C{$row}");
        $row++;
    }
} catch (Exception $e) {
    $instructionSheet->setCellValue("A{$row}", 'Unable to load sections from database.');
    $instructionSheet->mergeCells("A{$row}:C{$row}");
    $row++;
}

$instructionSheet->getColumnDimension('A')->setWidth(20);
$instructionSheet->getColumnDimension('B')->setWidth(15);
$instructionSheet->getColumnDimension('C')->setWidth(55);

// ===================================
// CLASS ADVISER DATA SHEET
// ===================================
$dataSheet = $spreadsheet->createSheet(1);
$dataSheet->setTitle('Class Adviser Data');

$headers = ['Username', 'Full Name', 'Email', 'Signatory Type', 'Course', 'Year-Section', 'Password'];
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
    ['adviser01', 'Juan Dela Cruz', 'juan.adviser@school.edu', 'Class Adviser', 'ACT', '1st Year|A', '@Signatory01'],
    ['adviser02', 'Maria Santos', 'maria.adviser@school.edu', 'Class Adviser', 'BSCA', '2-B,2-C', 'CustomPass123!'],
    ['adviser03', 'Pedro Reyes', 'pedro.adviser@school.edu', 'Class Adviser', 'BSOM', '3A', ''],
    ['adviser04', 'Ana Garcia', 'ana.adviser@school.edu', 'Class Adviser', 'BSIS', '1-A,2-A,3-A', ''],
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
$dataSheet->getColumnDimension('D')->setWidth(20);
$dataSheet->getColumnDimension('E')->setWidth(15);
$dataSheet->getColumnDimension('F')->setWidth(35);
$dataSheet->getColumnDimension('G')->setWidth(20);

$dataSheet->freezePane('A2');

// Set active sheet to Instructions
$spreadsheet->setActiveSheetIndex(0);

$conn->close();

ob_end_clean();

header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
header('Content-Disposition: attachment; filename="Class_Adviser_Import_Template.xlsx"');
header('Cache-Control: max-age=0');

$writer = new Xlsx($spreadsheet);
$writer->save('php://output');