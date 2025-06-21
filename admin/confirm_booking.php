<?php
session_start();
require_once "../includes/config.php";
require_once "../includes/db.php";
require_once "../MailUtils/EmailSender.php";

// Check if admin is logged in
if (!isset($_SESSION['admin_id']) || !isset($_SESSION['is_admin'])) {
    $_SESSION['toast'] = [
        'type' => 'error',
        'message' => 'Please login as admin'
    ];
    header("Location: ../login.php");
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $booking_id = $_POST['booking_id'] ?? null;
        $action = $_POST['action'] ?? null;
        $reason = $_POST['reason'] ?? '';

        if (!$booking_id || !$action) {
            throw new Exception('Invalid request parameters');
        }

        // Start transaction
        $pdo->beginTransaction();

        // Get booking details
        $stmt = $pdo->prepare("
            SELECT b.*, u.email, u.name as user_name, t.train_name, t.train_number 
            FROM bookings b
            JOIN users u ON b.user_id = u.id
            JOIN trains t ON b.train_id = t.id
            WHERE b.id = ?
        ");
        $stmt->execute([$booking_id]);
        $booking = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$booking) {
            throw new Exception('Booking not found');
        }

        // Update booking status
        $new_status = ($action === 'confirm') ? 'Confirmed' : 'Cancelled';
        $stmt = $pdo->prepare("UPDATE bookings SET status = ? WHERE id = ?");
        $stmt->execute([$new_status, $booking_id]);

        // Get passenger details
        $stmt = $pdo->prepare("SELECT * FROM passengers WHERE booking_id = ?");
        $stmt->execute([$booking_id]);
        $passengers = $stmt->fetchAll(PDO::FETCH_ASSOC);

        // Get food orders if any
        $stmt = $pdo->prepare("
            SELECT fo.*, fm.item_name, fm.price 
            FROM food_orders fo
            JOIN food_menu fm ON fo.menu_item_id = fm.id
            WHERE fo.booking_id = ?
        ");
        $stmt->execute([$booking_id]);
        $food_orders = $stmt->fetchAll(PDO::FETCH_ASSOC);

        // Send email notification
        try {
            $emailSender = new EmailSender();
            
            $emailSubject = $action === 'confirm' 
                ? "Booking Confirmed - PNR: {$booking['pnr_number']}"
                : "Booking Cancelled - PNR: {$booking['pnr_number']}";

            $emailBody = "
                <h2>Booking " . ucfirst($new_status) . "</h2>
                <p>Dear {$booking['user_name']},</p>
            ";

            if ($action === 'confirm') {
                $emailBody .= "<p>Your booking has been confirmed. Here are your journey details:</p>";
            } else {
                $emailBody .= "<p>Your booking has been cancelled. Reason: " . htmlspecialchars($reason) . "</p>";
            }

            $emailBody .= "
                <div style='background: #f5f5f5; padding: 15px; border-radius: 5px; margin: 15px 0;'>
                    <p><strong>PNR Number:</strong> {$booking['pnr_number']}</p>
                    <p><strong>Train:</strong> {$booking['train_name']} ({$booking['train_number']})</p>
                    <p><strong>From:</strong> {$booking['from_station']}</p>
                    <p><strong>To:</strong> {$booking['to_station']}</p>
                    <p><strong>Journey Date:</strong> " . date('d M Y', strtotime($booking['journey_date'])) . "</p>
                    <p><strong>Total Amount:</strong> ₹" . number_format($booking['total_amount'], 2) . "</p>
                    <p><strong>Status:</strong> {$new_status}</p>
                </div>
            ";

            if ($action === 'confirm') {
                $emailBody .= "
                    <h3>Passenger Details:</h3>
                    <table style='width: 100%; border-collapse: collapse; margin: 15px 0;'>
                        <tr style='background: #eee;'>
                            <th style='padding: 8px; text-align: left;'>Name</th>
                            <th style='padding: 8px; text-align: left;'>Age</th>
                            <th style='padding: 8px; text-align: left;'>Gender</th>
                        </tr>
                ";

                foreach ($passengers as $passenger) {
                    $emailBody .= "
                        <tr>
                            <td style='padding: 8px; border-bottom: 1px solid #ddd;'>{$passenger['name']}</td>
                            <td style='padding: 8px; border-bottom: 1px solid #ddd;'>{$passenger['age']}</td>
                            <td style='padding: 8px; border-bottom: 1px solid #ddd;'>{$passenger['gender']}</td>
                        </tr>
                    ";
                }

                $emailBody .= "</table>";

                if (!empty($food_orders)) {
                    $emailBody .= "
                        <h3>Food Orders:</h3>
                        <table style='width: 100%; border-collapse: collapse; margin: 15px 0;'>
                            <tr style='background: #eee;'>
                                <th style='padding: 8px; text-align: left;'>Item</th>
                                <th style='padding: 8px; text-align: left;'>Quantity</th>
                                <th style='padding: 8px; text-align: left;'>Delivery At</th>
                                <th style='padding: 8px; text-align: left;'>Amount</th>
                            </tr>
                    ";

                    foreach ($food_orders as $order) {
                        $emailBody .= "
                            <tr>
                                <td style='padding: 8px; border-bottom: 1px solid #ddd;'>{$order['item_name']}</td>
                                <td style='padding: 8px; border-bottom: 1px solid #ddd;'>{$order['quantity']}</td>
                                <td style='padding: 8px; border-bottom: 1px solid #ddd;'>{$order['delivery_station']}</td>
                                <td style='padding: 8px; border-bottom: 1px solid #ddd;'>₹" . number_format($order['total_amount'], 2) . "</td>
                            </tr>
                        ";
                    }

                    $emailBody .= "</table>";
                }

                $emailBody .= "
                    <p>You can view your booking details and print your ticket by visiting: 
                       <a href='http://localhost/Railway%20reservation/user/booking_confirmation.php?pnr={$booking['pnr_number']}'>Click here</a>
                    </p>
                ";
            }

            $emailBody .= "
                <p>Thank you for choosing RailYatra!</p>
                <p>Best regards,<br>RailYatra Team</p>
            ";

            $emailSender->sendEmail(
                $booking['email'],
                $emailSubject,
                $emailBody
            );

        } catch (Exception $e) {
            error_log("Failed to send booking " . strtolower($new_status) . " email: " . $e->getMessage());
        }

        // Commit transaction
        $pdo->commit();

        $_SESSION['toast'] = [
            'type' => 'success',
            'message' => "Booking successfully " . strtolower($new_status)
        ];

    } catch (Exception $e) {
        $pdo->rollBack();
        error_log("Error in confirm_booking.php: " . $e->getMessage());
        $_SESSION['toast'] = [
            'type' => 'error',
            'message' => $e->getMessage()
        ];
    }

    header("Location: manage_bookings.php");
    exit;
}

// If not POST request, redirect to manage bookings
header("Location: manage_bookings.php");
exit;
?> 