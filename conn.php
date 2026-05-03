<?php
$conn = mysqli_connect(
    'db5019666126.hosting-data.io', // Host from IONOS
    'dbu1355068',                // Username from IONOS
    'pcBPC12345',                // The password you set
    'dbs15304861'                  // Database name from IONOS
);

if (!$conn) {
    die("Database connection failed: " . mysqli_connect_error());
}
?>