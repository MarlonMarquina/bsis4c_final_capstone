<?php
// student_import_form.php
session_start();
include('conn.php');


// Check if admin is logged in
if (!isset($_SESSION['role']) || $_SESSION['role'] != "admin") {
    header("Location: login.php");
    exit();
}

require 'vendor/autoload.php'; // I-load ang Composer autoloader para sa PhpSpreadsheet

use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Reader\Csv;
use PhpOffice\PhpSpreadsheet\Reader\Xlsx;
use PhpOffice\PhpSpreadsheet\Reader\Xls;

$msg = '';
$msg_type = '';

// --- IMPORT LOGIC: Mag-process kapag may POST request galing sa form ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_FILES['excel_file'])) {
    
    $file = $_FILES['excel_file'];
    
    // Tiyakin na may file na in-upload at walang error
    if ($file['error'] !== UPLOAD_ERR_OK) {
        $msg = "❌ Error uploading file. Code: " . $file['error'];
        $msg_type = 'error';
    } else {
        // Kumuha ng file extension at itakda ang Reader type
        $file_ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
        $allowed_extensions = ['xlsx', 'xls', 'csv'];
        
        if (!in_array($file_ext, $allowed_extensions)) {
            $msg = "⚠️ Invalid file type. Only .xlsx, .xls, and .csv are allowed.";
            $msg_type = 'error';
        } else {
            // Gumawa ng temporary path para sa file
            $inputFileName = $file['tmp_name'];
            
            try {
                // Itakda ang Reader batay sa extension
                if ($file_ext === 'csv') {
                    $reader = new Csv();
                    $reader->setInputEncoding('UTF-8'); // Tiyakin na tama ang encoding
                    $reader->setDelimiter(',');
                } elseif ($file_ext === 'xlsx') {
                    $reader = new Xlsx();
                } elseif ($file_ext === 'xls') {
                    $reader = new Xls();
                } else {
                    throw new Exception("Unsupported file type.");
                }
                
                $spreadsheet = $reader->load($inputFileName);
                $sheet = $spreadsheet->getActiveSheet();
                $highestRow = $sheet->getHighestRow();
                
                $imported_count = 0;
                $skipped_count = 0;
                $error_details = [];
                
                // --- SIMULAN ANG PAG-READ MULA SA ROW 2 (Assuming Row 1 is the header) ---
                for ($row = 2; $row <= $highestRow; $row++) {
                    // Kumuha ng data mula sa bawat column (A=1, B=2, C=3...)
                    // COLUMN MAPPING: 
                    // A=Username/Student ID, B=Full Name, C=Email, D=Course, E=Year, F=Section, G=Default Password
                    
                    $username = trim($sheet->getCell('A' . $row)->getValue());
                    $full_name = trim($sheet->getCell('B' . $row)->getValue());
                    $email = trim($sheet->getCell('C' . $row)->getValue());
                    $course = trim($sheet->getCell('D' . $row)->getValue());
                    $year = trim($sheet->getCell('E' . $row)->getValue());
                    $section = trim($sheet->getCell('F' . $row)->getValue());
                    $raw_password = trim($sheet->getCell('G' . $row)->getValue() ?? '@Student01'); // Default password if empty
                    
                    $role = 'student';
                    $signatory_type = ''; // Blangko para sa students
                    $department = ''; // Blangko para sa students
                    
                    // Validation: Tiyakin na mayroon ang mandatory fields
                    if (empty($username) || empty($full_name) || empty($email) || empty($course) || empty($raw_password)) {
                        $skipped_count++;
                        $error_details[] = "Row {$row}: Missing mandatory fields (Username, Full Name, Email, Course, or Password). Skipped.";
                        continue;
                    }
                    
                    $password = password_hash($raw_password, PASSWORD_DEFAULT);
                    
                    // 1. Check if user already exists
                    $check = $conn->prepare("SELECT username FROM users WHERE username = ? OR email = ?"); 
                    $check->bind_param("ss", $username, $email); 
                    $check->execute();
                    $res = $check->get_result();
                    
                    if ($res->num_rows > 0) {
                        $skipped_count++;
                        $error_details[] = "Row {$row}: Username '{$username}' or Email '{$email}' already exists. Skipped."; 
                    } else {
                        // 2. Insert user (Student)
                        $insert = $conn->prepare("INSERT INTO users (username, full_name, email, course, year, section, password, role, signatory_type, department) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
                        $insert->bind_param("ssssssssss", $username, $full_name, $email, $course, $year, $section, $password, $role, $signatory_type, $department);
                        
                        if ($insert->execute()) {
                            $imported_count++;
                        } else {
                            $skipped_count++;
                            $error_details[] = "Row {$row}: Database error: " . $insert->error;
                        }
                        $insert->close();
                    }
                    $check->close();
                }
                
                // Final Message
                if ($imported_count > 0) {
                    $msg = "✅ Import Complete! Successfully imported **{$imported_count}** students. **{$skipped_count}** rows were skipped (check errors below).";
                    $msg_type = 'success';
                } elseif ($skipped_count > 0) {
                    $msg = "⚠️ Import finished, but **0** students were added. **{$skipped_count}** rows skipped. Check errors below.";
                    $msg_type = 'warning';
                } else {
                    $msg = "ℹ️ No data found in the file, or all rows were skipped.";
                    $msg_type = 'warning';
                }
                
            } catch (Exception $e) {
                $msg = "❌ Fatal Error during file processing: " . htmlspecialchars($e->getMessage());
                $msg_type = 'error';
            }
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Import Students | Smart Clearance System</title>
    <link href='https://unpkg.com/boxicons@2.1.4/css/boxicons.min.css' rel='stylesheet'>
    <link rel="stylesheet" href="styles.css"> 
    <style>
        body { font-family: 'Poppins', sans-serif; background-color: #f5f5f5; }
        .container { max-width: 600px; margin: 50px auto; background: white; padding: 30px; border-radius: 10px; box-shadow: 0 4px 15px rgba(0,0,0,0.1); }
        h2 { text-align: center; color: darkgreen; margin-bottom: 30px; }
        .message { padding: 15px; border-radius: 5px; margin-bottom: 20px; font-weight: 600; text-align: left; }
        .message.success { background: #e6ffe6; border: 1px solid #a1dba1; color: darkgreen; }
        .message.error { background: #ffe6e6; border: 1px solid #dba1a1; color: #e74c3c; }
        .message.warning { background: #fffbe6; border: 1px solid #dbd6a1; color: #f39c12; }
        .form-group { margin-bottom: 20px; }
        label { display: block; margin-bottom: 8px; font-weight: 600; }
        input[type="file"] { width: 100%; padding: 10px; border: 1px solid #ccc; border-radius: 5px; box-sizing: border-box; }
        button { background-color: darkgreen; color: white; padding: 12px 20px; border: none; border-radius: 5px; cursor: pointer; width: 100%; font-size: 16px; font-weight: 700; }
        button:hover { background-color: #045d04; }
        .back-link { display: block; text-align: center; margin-top: 20px; color: #3498db; text-decoration: none; }
        .errors-list { margin-top: 15px; border-top: 1px solid #eee; padding-top: 10px; }
        .errors-list h4 { color: #e74c3c; margin-bottom: 5px; }
        .errors-list ul { list-style: disc; margin-left: 20px; padding-left: 0; font-size: 14px; color: #555; }

        /* Template instructions */
        .template-info { background: #f4f4ff; border: 1px solid #ccc; padding: 15px; border-radius: 5px; margin-top: 20px; text-align: left; }
        .template-info h4 { color: #3498db; margin-top: 0; }
        
        /* New: Template download button style (Anchor tag) */
        .download-btn {
            display: block;
            background-color: #3498db; /* Blue color to differentiate from green submit */
            color: white;
            padding: 12px 20px;
            border: none;
            border-radius: 5px;
            cursor: pointer;
            width: 100%;
            font-size: 16px;
            font-weight: 700;
            text-align: center;
            text-decoration: none;
            margin-top: 20px; /* Space from instructions above */
        }
        .download-btn:hover { 
            background-color: #2980b9; 
        }

    </style>
</head>
<body>
    <div class="container">
        <h2><i class='bx bx-upload'></i> Student Data Import</h2>

        <?php if (!empty($msg)): ?>
            <div class="message <?= $msg_type ?>">
                <?= $msg ?>
                <?php if (!empty($error_details)): ?>
                    <div class="errors-list">
                        <h4>Details of Skipped Rows:</h4>
                        <ul>
                            <?php foreach ($error_details as $detail): ?>
                                <li><?= htmlspecialchars($detail) ?></li>
                            <?php endforeach; ?>
                        </ul>
                    </div>
                <?php endif; ?>
            </div>
        <?php endif; ?>

        <form method="POST" enctype="multipart/form-data">
            <div class="form-group">
                <label for="excel_file">Select Excel/CSV File (.xlsx, .xls, .csv):</label>
                <input type="file" id="excel_file" name="excel_file" accept=".xlsx, .xls, .csv" required>
            </div>
            
            <button type="submit">
                <i class='bx bx-data'></i> Import Data
            </button>
        </form>

        <div class="template-info">
            <h4>Template Instructions:</h4>
            <p>Ensure your file follows this column order exactly. The data should start on **Row 2**.</p>
            <table style="width:100%; text-align:left; border: 1px solid #ddd; border-collapse: collapse;">
                <tr>
                    <th style="padding: 5px; border: 1px solid #ddd;">A</th>
                    <th style="padding: 5px; border: 1px solid #ddd;">B</th>
                    <th style="padding: 5px; border: 1px solid #ddd;">C</th>
                    <th style="padding: 5px; border: 1px solid #ddd;">D</th>
                    <th style="padding: 5px; border: 1px solid #ddd;">E</th>
                    <th style="padding: 5px; border: 1px solid #ddd;">F</th>
                    <th style="padding: 5px; border: 1px solid #ddd;">G</th>
                    
                </tr>
                <tr>
                    <td style="padding: 5px; border: 1px solid #ddd;">Username (Student ID)</td>
                    <td style="padding: 5px; border: 1px solid #ddd;">Full Name</td>
                    <td style="padding: 5px; border: 1px solid #ddd;">Email</td>
                    <td style="padding: 5px; border: 1px solid #ddd;">Course (e.g., BSIT)</td>
                    <td style="padding: 5px; border: 1px solid #ddd;">Year (e.g., 2nd Year)</td>
                    <td style="padding: 5px; border: 1px solid #ddd;">Section (e.g., A)</td>
                    <td style="padding: 5px; border: 1px solid #ddd;">Password (Optional)</td>
                    
                </tr>
            </table>
            <p style="font-size: 12px; color: #777;">*If the Password column is empty, a default password (`@Student01`) will be used.</p>
        </div>

        <a href="TEMPLATE_STUDENT_IMPORT.xlsx" download="TEMPLATE_STUDENT_IMPORT.xlsx" class="download-btn">
            <i class='bx bx-download'></i> Download Template
        </a>
        <a href="manage_students.php" class="back-link"><i class='bx bx-arrow-back'></i> Back to Manage Students</a>
    </div>
</body>
</html>