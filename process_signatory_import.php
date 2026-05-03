<?php
// FILE: process_signatory_import.php
session_start();

require 'vendor/autoload.php'; 
include('conn.php');

use PhpOffice\PhpSpreadsheet\IOFactory;

if (!isset($_SESSION['role']) || $_SESSION['role'] != "admin") {
    header("Location: login.php");
    exit();
}

if (isset($_POST['import_signatories']) && $_FILES['excel_file']['error'] == 0) {
    
    $file_name = $_FILES['excel_file']['tmp_name'];
    
    // Required Headers: full_name, username, email, signatory_type, related_value, password
    $required_headers = ['full_name', 'username', 'email', 'signatory_type', 'related_value', 'password'];
    $imported_count = 0;
    $skipped_count = 0;
    $error_details = []; 
    
    try {
        $spreadsheet = IOFactory::load($file_name);
        $worksheet = $spreadsheet->getActiveSheet();
        
        // I-load ang rows, gamit ang true para sa $index_by_row (Start sa 1)
        $rows = $worksheet->toArray(null, true, true, true);
        
        if (empty($rows)) {
            throw new Exception("File is empty or could not be read.");
        }
        
        // 1. I-check ang Headers (Row 1)
        $headers_row_data = $rows[1]; 
        $headers = array_map('strtolower', array_map('trim', array_values($headers_row_data)));
        
        // Tanggalin ang Row 1 sa array para maproseso ang data lang
        unset($rows[1]); 
        
        $missing_headers = array_diff($required_headers, $headers);
        if (!empty($missing_headers)) {
            throw new Exception("❌ Missing required column headers: " . implode(', ', $missing_headers) . ". Please use: full_name, username, email, signatory_type, related_value, password");
        }
        
        // I-flip ang headers para mahanap ang column index/key (A, B, C...)
        $header_map = array_flip($headers_row_data); 
        
        // --- PREPARED STATEMENT ---
        // INSERT VALUES: username, full_name, email, section, password, signatory_type, department
        $insert_stmt = $conn->prepare("INSERT INTO users (username, full_name, email, course, year, section, password, role, signatory_type, department) 
                                       VALUES (?, ?, ?, '', '', ?, ?, 'signatory', ?, ?)");
        
        // Bind parameters: 7 s's (username, full_name, email, section, password, signatory_type, department)
        $insert_stmt->bind_param("sssssss", $username, $fullName, $email, $section, $passwordHash, $signatoryType, $department);

        // Ang $row_index ay ang Actual Excel Row Number (Start sa 2)
        foreach ($rows as $row_index => $row) {
            
            // Data Mapping
            $username       = trim($row[$header_map['username']]);
            $fullName       = trim($row[$header_map['full_name']]);
            $email          = trim($row[$header_map['email']]);
            $signatoryType  = trim($row[$header_map['signatory_type']]);
            $relatedValue   = trim($row[$header_map['related_value']]); 
            $rawPassword    = trim((string)$row[$header_map['password']]) ?: '@Signatory01'; 

            // Initialize department/section to empty
            $department = '';
            $section = '';

            if (empty($username) || empty($fullName) || empty($email) || empty($signatoryType)) {
                $skipped_count++;
                $error_details[] = "Row {$row_index} (User: {$username}): Missing essential fields (Username, Full Name, Email, or Signatory Type). Skipped.";
                continue; 
            }
            
            // --- LOGIC CHECK FOR DEPARTMENT/SECTION ---
            $signatoryTypeLower = strtolower($signatoryType);
            
            if ($signatoryTypeLower == 'program head') {
                if (empty($relatedValue)) {
                    $skipped_count++;
                    $error_details[] = "Row {$row_index} (User: {$username}): Signatory Type is 'Program Head', but 'related_value' (Department) is empty. Skipped.";
                    continue;
                }
                $department = $relatedValue;
            } elseif ($signatoryTypeLower == 'class adviser') {
                if (empty($relatedValue)) {
                    $skipped_count++;
                    $error_details[] = "Row {$row_index} (User: {$username}): Signatory Type is 'Class Adviser', but 'related_value' (Section) is empty. Skipped.";
                    continue;
                }
                $section = $relatedValue;
            } 
            
            $passwordHash = password_hash($rawPassword, PASSWORD_DEFAULT); 
            
            // --- VALIDATION: Check for Duplicates ---
            $check_stmt = $conn->prepare("SELECT username FROM users WHERE username = ? OR email = ?"); 
            $check_stmt->bind_param("ss", $username, $email); 
            $check_stmt->execute();
            $check_res = $check_stmt->get_result();
            $check_stmt->close();

            if ($check_res->num_rows > 0) {
                $skipped_count++;
                $error_details[] = "Row {$row_index} (User: {$username}): Username or Email already exists. Skipped.";
                continue;
            }

            // Execute the Insertion
            if ($insert_stmt->execute()) {
                $imported_count++;
            } else {
                $skipped_count++;
                $error_details[] = "Row {$row_index} (User: {$username}): Database Error: " . $insert_stmt->error;
            }
        }

        $insert_stmt->close();
        // $conn->close(); // Opsyonal na i-close ang koneksyon dito
        
        // --- FINAL MESSAGE GENERATION ---
        $final_msg = "✅ Import Complete! Successfully imported **{$imported_count}** signatories. ";
        
        if ($skipped_count > 0) {
            $final_msg .= "⚠️ **{$skipped_count}** rows were skipped (check errors below).";
            $_SESSION['error_details'] = $error_details; 
        }

        $_SESSION['import_msg'] = $final_msg;

    } catch (Exception $e) {
         $_SESSION['import_msg'] = "❌ Fatal Error during file processing: " . htmlspecialchars($e->getMessage());
    }
    
    // Redirect pabalik sa form
    header("Location: signatories_import_form.php"); 
    exit();

} else {
    $_SESSION['import_msg'] = "❌ Error: Please upload a valid Excel file.";
    header("Location: signatories_import_form.php");
    exit();
}
?>