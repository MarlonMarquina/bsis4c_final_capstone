<?php
// FILE: signatories_import_form.php
session_start();
include 'conn.php'; 

// Tiyakin na Admin ang user
if (!isset($_SESSION['role']) || $_SESSION['role'] != "admin") {
    header("Location: login.php");
    exit();
}

$msg = '';
$msg_type = ''; // Para sa colored messages (success, error, warning)
$error_details = []; // Para sa errors list

// Kumuha ng message mula sa session kung mayroon man
if (isset($_SESSION['import_msg'])) {
    $msg = $_SESSION['import_msg'];
    unset($_SESSION['import_msg']); 
    
    // I-determine ang message type
    if (strpos($msg, '✅ Import Complete') !== false) {
        $msg_type = 'success';
    } elseif (strpos($msg, '❌') !== false || strpos($msg, 'Fatal Error') !== false) {
        $msg_type = 'error';
    } else {
        $msg_type = 'warning';
    }
}

// Kunin ang detalyadong error list kung ipinasa ng process file
if (isset($_SESSION['error_details'])) {
    $error_details = $_SESSION['error_details'];
    unset($_SESSION['error_details']);
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Import Signatories | Smart Clearance System</title>
    <link href='https://unpkg.com/boxicons@2.1.4/css/boxicons.min.css' rel='stylesheet'>
    <link rel="stylesheet" href="styles.css"> 
    <style>
        /* EKSKATO MULA SA student_import_form.php STYLES */
        body { font-family: 'Poppins', sans-serif; background-color: #f5f5f5; }
        .container { 
            max-width: 600px; 
            margin: 50px auto; 
            background: white; 
            padding: 30px; 
            border-radius: 10px; 
            box-shadow: 0 4px 15px rgba(0,0,0,0.1); 
        }
        h2 { 
            text-align: center; 
            color: darkgreen; 
            margin-bottom: 30px; 
            font-size: 24px; 
        }
        
        /* Message Styles */
        .message { padding: 15px; border-radius: 5px; margin-bottom: 20px; font-weight: 600; text-align: left; }
        .message.success { background: #e6ffe6; border: 1px solid #a1dba1; color: darkgreen; }
        .message.error { background: #ffe6e6; border: 1px solid #dba1a1; color: #e74c3c; }
        .message.warning { background: #fffbe6; border: 1px solid #dbd6a1; color: #f39c12; }
        
        .form-group { margin-bottom: 20px; }
        label { display: block; margin-bottom: 8px; font-weight: 600; }
        
        /* File Input Styling */
        input[type="file"] { 
            width: 100%; 
            padding: 10px; 
            border: 1px solid #ccc; 
            border-radius: 5px; 
            box-sizing: border-box; 
        }
        
        /* Submit Button Styling - (GREEN) */
        button { 
            background-color: darkgreen; 
            color: white; 
            padding: 12px 20px; 
            border: none; 
            border-radius: 5px; 
            cursor: pointer; 
            width: 100%; 
            font-size: 16px; 
            font-weight: 700; 
            margin-top: 10px; 
        }
        button:hover { background-color: #045d04; }
        
        /* Back Link */
        .back-link { 
            display: block; 
            text-align: center; 
            margin-top: 20px; 
            color: #3498db; 
            text-decoration: none; 
        }
        
        /* Error Details List */
        .errors-list { margin-top: 15px; border-top: 1px solid #eee; padding-top: 10px; }
        .errors-list h4 { color: #e74c3c; margin-bottom: 5px; }
        .errors-list ul { list-style: disc; margin-left: 20px; padding-left: 0; font-size: 14px; color: #555; }

        /* Template instructions box */
        .template-info { 
            background: #f4f4ff; 
            border: 1px solid #ccc; 
            padding: 15px; 
            border-radius: 5px; 
            margin-top: 20px; 
            text-align: left; 
        }
        .template-info h4 { color: #3498db; margin-top: 0; }
        
        /* Download Button Styling - (BLUE) */
        .download-btn {
            display: block;
            background-color: #3498db; 
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
            margin-top: 20px; 
        }
        .download-btn:hover { 
            background-color: #2980b9; 
        }
        
        /* Template Table Styling */
        .template-info table {
            width:100%; 
            text-align:left; 
            border: 1px solid #ddd; 
            border-collapse: collapse; 
            font-size: 12px;
        }
        .template-info th, .template-info td {
            padding: 5px; 
            border: 1px solid #ddd;
        }

    </style>
</head>
<body>
    <div class="container">
        <h2><i class='bx bx-upload'></i> Signatory Data Import</h2>

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

        <form action="process_signatory_import.php" method="POST" enctype="multipart/form-data">
            <div class="form-group">
                <label for="excel_file">Select Excel/CSV File (.xlsx, .xls, .csv):</label>
                <input type="file" id="excel_file" name="excel_file" accept=".xlsx, .xls, .csv" required>
            </div>
            
            <button type="submit" name="import_signatories">
                <i class='bx bx-data'></i> Start Import
            </button>
        </form>

        <div class="template-info">
            <h4>Template Instructions:</h4>
            <p>Ensure your file follows this column order exactly. **Row 1 must contain these exact headers (lowercase)**. The data should start on **Row 2**.</p>
            
            <table style="width:100%; text-align:left; border: 1px solid #ddd; border-collapse: collapse; font-size: 12px;">
                <tr>
                    <th>A</th>
                    <th>B</th>
                    <th>C</th>
                    <th>D</th>
                    <th>E</th>
                    <th>F</th>
                </tr>
                <tr>
                    <td>**full_name**</td>
                    <td>**username** (Employee ID)</td>
                    <td>**email**</td>
                    <td>**signatory_type** (e.g., Program Head, Class Adviser)</td>
                    <td>**related_value** (Department or Section. Required for Program Head/Class Adviser. Leave blank otherwise.)</td>
                    <td>**password** (Optional)</td>
                </tr>
            </table>
            <p style="font-size: 12px; color: #777;">*If the **signatory_type** is 'Program Head', Column E must contain the **Department** (e.g., BSIT). If 'Class Adviser', Column E must contain the **Section** (e.g., A-1).</p>
        </div>

        <a href="TEMPLATE_SIGNATORY_IMPORT.xlsx" download="TEMPLATE_SIGNATORY_IMPORT.xlsx" class="download-btn">
            <i class='bx bx-download'></i> Download Template
        </a>
        
        <a href="manage_signatories.php" class="back-link">
            <i class='bx bx-arrow-back'></i> Back to Manage Signatories
        </a>
    </div>
</body>
</html>