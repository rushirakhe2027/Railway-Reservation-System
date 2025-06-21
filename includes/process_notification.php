<?php
session_start();
require_once "config.php";

// Simple success response
header('Content-Type: application/json');
echo json_encode(['status' => 'success']);

/* Temporarily disabled email notifications
// Process notification in the background after sending response
fastcgi_finish_request();

// Only process if notification data exists
if (isset($_SESSION['login_notification'])) {
    try {
        require_once "email.php";
        $data = $_SESSION['login_notification'];
        $emailService = new EmailService();
        $emailService->sendLoginNotification(
            $data['email'],
            $data['name'],
            $data['time'],
            $data['ip']
        );
    } catch (Exception $e) {
        error_log("Failed to send login notification: " . $e->getMessage());
    }
    
    unset($_SESSION['login_notification']);
}
*/
?> 