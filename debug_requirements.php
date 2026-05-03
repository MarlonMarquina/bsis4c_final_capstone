<?php
// DEBUG SCRIPT: Check what's stored in course_requirements for Program Head and Research Office
include 'conn.php';

echo "<h2>DEBUG: Course Requirements Data</h2>";
echo "<style>table{border-collapse:collapse;width:100%;}th,td{border:1px solid #ddd;padding:8px;text-align:left;}th{background:#4CAF50;color:white;}</style>";

// Get all course requirements with signatory info
$query = "
    SELECT 
        cr.id,
        cr.course_id,
        cr.requirement_id,
        cr.signatory_id,
        cr.document_type_id,
        c.course_name,
        u.username AS signatory_username,
        u.signatory_type,
        rl.requirement_name
    FROM course_requirements cr
    JOIN courses c ON cr.course_id = c.id
    JOIN users u ON cr.signatory_id = u.id
    LEFT JOIN requirement_library rl ON cr.requirement_id = rl.id
    WHERE u.signatory_type LIKE '%Program Head%' OR u.signatory_type LIKE '%Research%'
    ORDER BY u.signatory_type, c.course_name
";

$result = $conn->query($query);

if ($result && $result->num_rows > 0) {
    echo "<table>";
    echo "<tr>
            <th>ID</th>
            <th>Signatory Type</th>
            <th>Course</th>
            <th>Requirement ID</th>
            <th>Requirement Name</th>
            <th>Document Type ID</th>
            <th>Issue?</th>
          </tr>";
    
    while ($row = $result->fetch_assoc()) {
        $req_id = $row['requirement_id'];
        $doc_type = $row['document_type_id'];
        
        // Check if it's a problem
        $issue = '';
        if (($req_id == 0 || $req_id === null) && $doc_type !== 'N/A') {
            $issue = '<span style="color:red;font-weight:bold;">❌ WRONG! Should be N/A</span>';
        } elseif ($req_id > 0 && $doc_type === 'N/A') {
            $issue = '<span style="color:orange;font-weight:bold;">⚠️ Has req_id but N/A format</span>';
        } elseif (($req_id == 0 || $req_id === null) && $doc_type === 'N/A') {
            $issue = '<span style="color:green;font-weight:bold;">✅ Correct N/A</span>';
        } elseif ($req_id > 0 && !empty($doc_type) && $doc_type !== 'N/A') {
            $issue = '<span style="color:green;font-weight:bold;">✅ Correct with file</span>';
        } else {
            $issue = '<span style="color:red;font-weight:bold;">❌ EMPTY/NULL</span>';
        }
        
        echo "<tr>";
        echo "<td>" . $row['id'] . "</td>";
        echo "<td>" . htmlspecialchars($row['signatory_type']) . "</td>";
        echo "<td>" . htmlspecialchars($row['course_name']) . "</td>";
        echo "<td>" . ($req_id ?? 'NULL') . "</td>";
        echo "<td>" . htmlspecialchars($row['requirement_name'] ?? 'NULL') . "</td>";
        echo "<td>" . htmlspecialchars($doc_type ?? 'NULL') . "</td>";
        echo "<td>" . $issue . "</td>";
        echo "</tr>";
    }
    echo "</table>";
} else {
    echo "<p style='color:orange;'>No assignments found for Program Head or Research Office.</p>";
}

echo "<hr>";
echo "<h3>Quick Fix Options:</h3>";
echo "<p>If you see ❌ WRONG entries above, you can:</p>";
echo "<ol>";
echo "<li><strong>Delete them:</strong> Go to requirementssigna.php and delete the wrong assignments</li>";
echo "<li><strong>Run SQL Fix:</strong> Execute the SQL below to fix all wrong entries</li>";
echo "</ol>";

echo "<h4>SQL Fix Command (copy to phpMyAdmin):</h4>";
echo "<pre style='background:#f4f4f4;padding:10px;border:1px solid #ddd;'>";
echo "-- Fix entries where requirement_id is 0 or NULL but document_type_id is not 'N/A'\n";
echo "UPDATE course_requirements \n";
echo "SET document_type_id = 'N/A' \n";
echo "WHERE (requirement_id = 0 OR requirement_id IS NULL) \n";
echo "AND document_type_id != 'N/A';\n\n";

echo "-- Or delete them completely if they're wrong:\n";
echo "-- DELETE FROM course_requirements \n";
echo "-- WHERE (requirement_id = 0 OR requirement_id IS NULL) \n";
echo "-- AND (document_type_id IS NULL OR document_type_id = '');";
echo "</pre>";

$conn->close();
?>