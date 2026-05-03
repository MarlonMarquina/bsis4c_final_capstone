<?php
// FILE: process_student_import.php

// 1. I-load ang PhpSpreadsheet Library
require 'vendor/autoload.php'; 
// 2. I-load ang iyong Database Connection
    
include('conn.php');

use PhpOffice\PhpSpreadsheet\IOFactory;

// --- Default Account Settings ---
$default_password = '@Student01';
// Ito ang secure na hash
$hashed_password = password_hash($default_password, PASSWORD_DEFAULT); 

if (isset($_POST['import_data']) && $_FILES['excel_file']['error'] == 0) {
    $file_name = $_FILES['excel_file']['tmp_name'];
    echo "<style>body { font-family: Arial, sans-serif; padding: 30px; }</style>";

    try {
        $spreadsheet = IOFactory::load($file_name);
        $worksheet = $spreadsheet->getActiveSheet();
        $rows = $worksheet->toArray();
        
        // Alisin ang Header Row
        array_shift($rows); 
        
        $imported_count = 0;
        $failed_students = [];

        // --- MODIFIED PREPARED STATEMENT ---
        // May 10 parameters na tayo ngayon: first_name, last_name, sex, birthdate, contact_no, street_address, province, city_municipality, username, password
        $stmt = $conn->prepare("INSERT INTO students (first_name, last_name, sex, birthdate, contact_no, street_address, province, city_municipality, username, password) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
        
        // String (s) for all columns except the password (hashed string)
        $stmt->bind_param("ssssssssss", $firstName, $lastName, $sex, $birthdate, $contactNo, $streetAddress, $province, $cityMunicipality, $username, $passwordHash);

        foreach ($rows as $row) {
            // Tiyakin na may sapat na 8 columns
            if (count($row) < 8 || empty($row[0]) || empty($row[1])) {
                continue; 
            }

            // Data Mapping mula sa Excel
            // [0]Last Name, [1]First Name, [2]Sex, [3]Birthdate, [4]Contact No., [5]Street Address, [6]Province, [7]City/Municipality
            $lastName = trim($row[0]);
            $firstName = trim($row[1]);
            $sex = trim($row[2]);
            $birthdate = date('Y-m-d', strtotime(trim($row[3]))); // Ensure date is in YYYY-MM-DD
            $contactNo = trim($row[4]);
            $streetAddress = trim($row[5]);
            $province = trim($row[6]);
            $cityMunicipality = trim($row[7]);
            
            // --- ACCOUNT GENERATION ---
            // Username: "firstname.lastname"
            $username_raw = strtolower($firstName . '.' . $lastName);
            $username = preg_replace('/[^A-Za-z0-9.]/', '', $username_raw); 
            
            $passwordHash = $hashed_password; 

            // Execute the Insertion
            if ($stmt->execute()) {
                $imported_count++;
            } else {
                $failed_students[] = [
                    'name' => "$firstName $lastName",
                    'reason' => $stmt->error
                ];
            }
        }

        $stmt->close();
        $conn->close();

        // --- Display Results ---
        echo "<h1>✅ Import Successful!</h1>";
        echo "<p>Successfully created *$imported_count* student accounts with complete profile data.</p>";
        
        if (!empty($failed_students)) {
            echo "<h2>⚠️ Failed Imports:</h2>";
            echo "<ul>";
            foreach ($failed_students as $student) {
                echo "<li>**{$student['name']}** - Reason: {$student['reason']}</li>";
            }
            echo "</ul>";
        }


    } catch (\PhpOffice\PhpSpreadsheet\Reader\Exception $e) {
        echo "<h1>❌ Error Reading File</h1>";
        echo "<p>Error: " . $e->getMessage() . "</p>";
    } catch (Exception $e) {
        echo "<h1>❌ General Error</h1>";
        echo "<p>Error: " . $e->getMessage() . "</p>";
    }

} else {
    echo "<h1>❌ Error</h1>";
    echo "<p>Please upload a valid Excel file.</p>";
}
?>