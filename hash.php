<?php
// hash.php - Passwort-Hash generieren
$password = '1';
$hash = password_hash($password, PASSWORD_DEFAULT);
echo "Passwort: $password<br>";
echo "Hash: $hash<br>";
echo "<br>SQL:<br>";
echo "UPDATE users SET password = '$hash' WHERE email = 'r@r.r';";
?>