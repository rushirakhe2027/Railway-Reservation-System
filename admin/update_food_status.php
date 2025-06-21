<?php
session_start();
require_once "../includes/admin_auth.php";
require_once "../includes/config.php";
require_once "../MailUtils/EmailSender.php";

// Set JSON header
header('Content-Type: application/json');

// Check if admin is logged in
requireAdminLogin();

// Check if required parameters are present
if (!isset($_GET['booking_id']) || !isset($_GET['status'])) {
    echo json_encode([
        'success' => false,
        'message' => 'Missing required parameters'
    ]);
    exit;
}

$booking_id = $_GET['booking_id'];
$status = $_GET['status'];

// Validate status
$valid_statuses = ['Approved', 'Delivered', 'Cancelled'];
if (!in_array($status, $valid_statuses)) {
    echo json_encode([
        'success' => false,
        'message' => 'Invalid status'
    ]);
    exit;
}

try {
    // Start transaction
    $pdo->beginTransaction();

    // Update food order status
    $sql = "UPDATE food_orders SET status = ? WHERE booking_id = ?";
    $stmt = $pdo->prepare($sql);
    $stmt->execute([$status, $booking_id]);

    // Get booking and user details for email
    $sql = "SELECT b.*, u.email, u.name, 
            GROUP_CONCAT(CONCAT(fm.item_name, ' (', fo.quantity, ')') SEPARATOR ', ') as food_items,
            GROUP_CONCAT(DISTINCT fo.delivery_station) as delivery_stations
            FROM bookings b 
            JOIN users u ON b.user_id = u.id
            JOIN food_orders fo ON fo.booking_id = b.id
            JOIN food_menu fm ON fm.id = fo.menu_item_id
            WHERE b.id = ?
            GROUP BY b.id";
    $stmt = $pdo->prepare($sql);
    $stmt->execute([$booking_id]);
    $booking = $stmt->fetch();

    // Commit transaction
    $pdo->commit();

    // Send email notification
    try {
        $emailSender = new EmailSender();
        
        // Prepare email content based on status
        $subject = "Food Order Status Update - " . $booking['pnr_number'];
        
        $message = "Dear " . $booking['name'] . ",<br><br>";
        $message .= "Your food order for booking PNR: " . $booking['pnr_number'] . " has been " . strtolower($status) . ".<br><br>";
        $message .= "Order Details:<br>";
        $message .= "Items: " . $booking['food_items'] . "<br>";
        $message .= "Delivery Station: " . $booking['delivery_stations'] . "<br><br>";
        
        if ($status === 'Approved') {
            $message .= "Your food order has been approved and will be prepared for delivery.<br>";
            $message .= "We will notify you once the order is ready for delivery.";
        } elseif ($status === 'Delivered') {
            $message .= "Your food order has been delivered. We hope you enjoy your meal!<br>";
            $message .= "Thank you for choosing our service.";
        }
        
        $message .= "<br><br>Best regards,<br>RailYatra Team";

        $emailSender->sendEmail($booking['email'], $subject, $message);
    } catch (Exception $e) {
        // Log email error but don't stop the process
        error_log("Failed to send food status email: " . $e->getMessage());
    }

    echo json_encode([
        'success' => true,
        'message' => 'Food order status updated successfully',
        'data' => [
            'status' => $status
        ]
    ]);

} catch (Exception $e) {
    // Rollback transaction on error
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }

    echo json_encode([
        'success' => false,
        'message' => 'Failed to update food order status: ' . $e->getMessage()
    ]);
} 