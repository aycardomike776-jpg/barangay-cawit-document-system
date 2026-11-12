
<?php

session_start();

// Clear all session data
$_SESSION = [];

// Destroy the current session
session_destroy();

// Return to login page
header("Location: login.php");
exit;

?>
