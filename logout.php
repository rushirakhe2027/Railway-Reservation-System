<?php
session_start();

// Clear all session variables
$_SESSION = array();

// Destroy the session
session_destroy();

// Show success message
session_start();
require_once "includes/config.php";
showToast("You have been successfully logged out", "success");

// Redirect to login page
header("location: login.php");
exit;
?> 