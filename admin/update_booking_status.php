<?php
session_start();
require_once "../includes/admin_auth.php";
require_once "../includes/config.php";
require_once "../MailUtils/EmailSender.php";

// Set JSON header at the very beginning
header('Content-Type: application/json');

// Check if admin is logged in
requireAdminLogin();

// At the beginning of the file, after session_start()
$redirect_page = isset($_SERVER['HTTP_REFERER']) && strpos($_SERVER['HTTP_REFERER'], 'bookings.php') !== false ? 'bookings.php' : 'dashboard.php';

if ($_SERVER['REQUEST_METHOD'] === 'GET' && isset($_GET['status']) && (isset($_GET['id']) || isset($_GET['booking_id']))) {
    $booking_id = isset($_GET['booking_id']) ? $_GET['booking_id'] : $_GET['id'];
    $new_status = $_GET['status'];
    
    // Validate status
    $valid_statuses = ['Approved', 'Pending', 'Cancelled'];
    if (!in_array($new_status, $valid_statuses)) {
        echo json_encode([
            'success' => false,
            'message' => 'Invalid status provided'
        ]);
        exit;
    }

    try {
        $db = Database::getInstance();
        $pdo = $db->getConnection();
        $pdo->beginTransaction();

        // Get booking details first
        $check_sql = "SELECT b.*, u.email, u.name as user_name, t.train_name, t.train_number,
                     GROUP_CONCAT(p.name) as passenger_list,
                     (SELECT GROUP_CONCAT(CONCAT(fm.item_name, ' (', fo.quantity, ')'))
                      FROM food_orders fo 
                      JOIN food_menu fm ON fo.menu_item_id = fm.id
                      WHERE fo.booking_id = b.id) as food_items
                     FROM bookings b 
                     LEFT JOIN users u ON b.user_id = u.id 
                     LEFT JOIN trains t ON b.train_id = t.id 
                     LEFT JOIN passengers p ON p.booking_id = b.id
                     WHERE b.id = :booking_id
                     GROUP BY b.id";
        $check_stmt = $pdo->prepare($check_sql);
        $check_stmt->bindParam(':booking_id', $booking_id, PDO::PARAM_INT);
        $check_stmt->execute();
        $booking = $check_stmt->fetch(PDO::FETCH_ASSOC);

        if (!$booking) {
            throw new Exception('Booking not found');
        }

        // Update booking status
        $update_sql = "UPDATE bookings SET status = :new_status WHERE id = :booking_id";
        $update_stmt = $pdo->prepare($update_sql);
        $update_stmt->bindValue(':new_status', $new_status, PDO::PARAM_STR);
        $update_stmt->bindValue(':booking_id', $booking_id, PDO::PARAM_INT);
        $update_result = $update_stmt->execute();

        if (!$update_result) {
            throw new Exception('Failed to update booking status');
        }

        // Update food orders status if booking is cancelled
        $food_cancelled = false;
        if ($new_status === 'Cancelled') {
            $update_food_sql = "UPDATE food_orders SET status = 'Cancelled' WHERE booking_id = :booking_id";
            $update_food_stmt = $pdo->prepare($update_food_sql);
            $update_food_stmt->bindParam(':booking_id', $booking_id, PDO::PARAM_INT);
            $update_food_stmt->execute();
            $food_cancelled = true;
        }

        // Prepare email content
        $status_color = [
            'Approved' => '#28a745',
            'Pending' => '#ffc107',
            'Cancelled' => '#dc3545'
        ][$new_status];

        $email_body = "
        <html>
        <head>
            <style>
                body { font-family: Arial, sans-serif; }
                .container { max-width: 600px; margin: 0 auto; padding: 20px; }
                .header { background: #ff5722; color: white; padding: 20px; text-align: center; }
                .content { padding: 20px; background: #f9f9f9; }
                .status { background-color: {$status_color}; color: white; padding: 5px 10px; border-radius: 5px; }
                .food-details { background: #f0f0f0; padding: 10px; margin: 10px 0; border-radius: 5px; }
            </style>
        </head>
        <body>
            <div class='container'>
                <div class='header'>
                    <h2>Booking Status Update</h2>
                </div>
                <div class='content'>
                    <p>Dear {$booking['user_name']},</p>
                    <p>Your booking status has been updated.</p>
                    <p><strong>PNR Number:</strong> {$booking['pnr_number']}</p>
                    <p><strong>Train:</strong> {$booking['train_number']} - {$booking['train_name']}</p>
                    <p><strong>Journey Date:</strong> " . date('d M Y', strtotime($booking['journey_date'])) . "</p>
                    <p><strong>New Status:</strong> <span class='status'>{$new_status}</span></p>
                    <p><strong>Passengers:</strong> {$booking['passenger_list']}</p>";

        if (!empty($booking['food_items'])) {
            $email_body .= "
                    <div class='food-details'>
                        <h3>Food Order Details</h3>
                        <p>{$booking['food_items']}</p>";
            if ($new_status === 'Cancelled') {
                $email_body .= "<p><strong>Note:</strong> Your food orders have also been cancelled. A refund will be processed within 5-7 business days.</p>";
            }
            $email_body .= "</div>";
        }

        $email_body .= "
                </div>
            </div>
        </body>
        </html>";

        // Try to send email using EmailSender class
        $mail_sent = false;
        try {
            $emailSender = new EmailSender();
            $mail_sent = $emailSender->sendEmail(
                $booking['email'],
                "Booking Status Updated - PNR: " . $booking['pnr_number'],
                $email_body,
                true
            );
        } catch (Exception $e) {
            // Log email error but continue with status update
            error_log("Failed to send email: " . $e->getMessage());
        }

        // Commit transaction
        $pdo->commit();

        // Return success even if email fails
        echo json_encode([
            'success' => true,
            'message' => $mail_sent ? 'Status updated and notification sent' : 'Status updated successfully (email notification failed)',
            'data' => [
                'status' => $new_status,
                'booking_id' => $booking_id,
                'food_cancelled' => $food_cancelled
            ]
        ]);
        exit;

    } catch (Exception $e) {
        if (isset($pdo) && $pdo->inTransaction()) {
            $pdo->rollBack();
        }
        
        echo json_encode([
            'success' => false,
            'message' => 'Error: ' . $e->getMessage()
        ]);
        exit;
    }
} else {
    echo json_encode([
        'success' => false,
        'message' => 'Invalid request'
    ]);
    exit;
} 