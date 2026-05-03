<?php
$plain_password = 'admin'; 
$hashed_password = password_hash($plain_password, PASSWORD_DEFAULT);
echo "Ang Hashed Password para sa 'admin' ay: <br>";
echo "<strong>" . $hashed_password . "</strong>";
?>