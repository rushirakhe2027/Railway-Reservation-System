<?php
/**
 * Check if user is logged in
 * Redirects to login page if not logged in
 */
function checkUserLogin() {
    if (!isset($_SESSION['user_id'])) {
        // Store the current URL in session to redirect back after login
        $_SESSION['redirect_url'] = $_SERVER['REQUEST_URI'];
        
        // Redirect to login page
        header("Location: ../login.php");
        exit();
    }
    
    // Check if user exists in database
    global $pdo;
    $stmt = $pdo->prepare("SELECT id FROM users WHERE id = ?");
    $stmt->execute([$_SESSION['user_id']]);
    
    if (!$stmt->fetch()) {
        // User not found, destroy session and redirect
        session_destroy();
        header("Location: ../login.php");
        exit();
    }
}

/**
 * Check if user is logged in and return boolean
 */
function isUserLoggedIn() {
    return isset($_SESSION['user_id']);
}

/**
 * Get current user's ID
 */
function getCurrentUserId() {
    return $_SESSION['user_id'] ?? null;
}

/**
 * Get current user's data
 */
function getCurrentUser() {
    if (!isUserLoggedIn()) {
        return null;
    }
    
    global $pdo;
    $stmt = $pdo->prepare("SELECT id, name, email, phone FROM users WHERE id = ?");
    $stmt->execute([$_SESSION['user_id']]);
    return $stmt->fetch(PDO::FETCH_ASSOC);
} 