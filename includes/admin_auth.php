<?php
// Start session if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Define admin credentials
define('ADMIN_EMAIL', 'admin@gmail.com');
define('ADMIN_PASSWORD', '12345678');

// Function to check if user is logged in as admin
function isAdminLoggedIn() {
    return isset($_SESSION['admin_id']) && 
           isset($_SESSION['user_type']) && 
           $_SESSION['user_type'] === 'admin' && 
           $_SESSION['is_admin'] === true;
}

function verifyAdminCredentials($email, $password) {
    return $email === ADMIN_EMAIL && $password === ADMIN_PASSWORD;
}

function adminLogin($email, $password) {
    if (verifyAdminCredentials($email, $password)) {
        $_SESSION['admin_logged_in'] = true;
        $_SESSION['admin_email'] = $email;
        $_SESSION['admin_type'] = 'admin';  // Add admin type
        return true;
    }
    return false;
}

function adminLogout() {
    unset($_SESSION['admin_logged_in']);
    unset($_SESSION['admin_email']);
    unset($_SESSION['admin_type']);
    session_destroy();
}

// Function to require admin login
function requireAdminLogin() {
    if (!isAdminLoggedIn()) {
        header("Location: ../login.php");
        exit();
    }
}

// Function to get admin info
function getAdminInfo() {
    if (isAdminLoggedIn()) {
        return [
            'id' => $_SESSION['admin_id'],
            'email' => $_SESSION['admin_email'],
            'name' => $_SESSION['admin_name'] ?? 'Administrator'
        ];
    }
    return null;
}
?> 