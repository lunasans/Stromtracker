<?php
// logout.php
// User ausloggen

require_once 'config/database.php';
require_once 'config/session.php';

// User ausloggen
Auth::logout();

// Diese Zeile wird normalerweise nicht erreicht, da Auth::logout() einen Redirect macht
// Aber für Sicherheit:
header('Location: index.php');
exit;
?>