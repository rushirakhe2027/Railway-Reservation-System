<?php
// Enable error reporting for debugging
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Start session if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Database configuration
$db_host = 'localhost';
$db_name = 'railway_reservation';
$db_user = 'root';
$db_pass = '';

try {
    // Create PDO connection
    $pdo = new PDO(
        "mysql:host=$db_host;dbname=$db_name;charset=utf8mb4",
        $db_user,
        $db_pass,
        [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false
        ]
    );
    
    // Test connection
    $pdo->query("SELECT 1");
    
} catch (PDOException $e) {
    // Log the error
    error_log("Database connection error: " . $e->getMessage());
    
    if (!isset($_SESSION['toast'])) {
        $_SESSION['toast'] = [
            'type' => 'error',
            'message' => 'Database connection failed. Please try again later.'
        ];
    }
    // Don't expose error details to user
    die("Connection failed. Please try again later.");
}

// Function to check if user is logged in
function isLoggedIn() {
    return isset($_SESSION['user_id']) && 
           !empty($_SESSION['user_id']) && 
           isset($_SESSION['logged_in']) && 
           $_SESSION['logged_in'] === true;
}

// Function to verify user session
function verifyUserSession() {
    if (!isLoggedIn()) {
        // Store current URL for redirect after login
        $_SESSION['redirect_after_login'] = $_SERVER['REQUEST_URI'];
        
        // Set toast notification
        $_SESSION['toast'] = [
            'type' => 'warning',
            'message' => 'Please login to continue'
        ];
        
        // Get the correct path to login page
        $login_path = '/Railway%20resrvation/login.php';
        if (headers_sent()) {
            echo "<script>window.location.href = '$login_path';</script>";
            exit;
        } else {
            header("Location: $login_path");
            exit;
        }
    }
    return true;
}
?> 