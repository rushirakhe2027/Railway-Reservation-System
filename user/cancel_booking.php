<?php
session_start();
require_once "../includes/config.php";
require_once "../includes/db.php";
require_once "../MailUtils/vendor/autoload.php";
require_once "../includes/email.php";

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

if (!isset($_SESSION['user_id'])) {
    $_SESSION['toast'] = [
        'type' => 'error',
        'message' => 'Please login to continue'
    ];
    header("Location: ../login.php");
    exit;
}

$booking_id = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);

if (!$booking_id) {
    $_SESSION['toast'] = [
        'type' => 'error',
        'message' => 'Invalid booking ID'
    ];
    header("Location: my_bookings.php");
    exit;
}

try {
    $pdo->beginTransaction();

    // Get booking details and passenger count
    $sql = "SELECT b.*, t.train_name, t.train_number, u.email as user_email, u.name as user_name,
            COUNT(p.id) as passenger_count 
            FROM bookings b 
            JOIN trains t ON b.train_id = t.id
            JOIN users u ON b.user_id = u.id
            LEFT JOIN passengers p ON b.id = p.booking_id 
            WHERE b.id = :booking_id AND b.user_id = :user_id 
            GROUP BY b.id";
    $stmt = $pdo->prepare($sql);
    $stmt->execute([
        'booking_id' => $booking_id,
        'user_id' => $_SESSION['user_id']
    ]);
    $booking = $stmt->fetch();

    if (!$booking) {
        throw new Exception("Booking not found or unauthorized access");
    }

    if ($booking['status'] === 'Cancelled') {
        throw new Exception("This booking is already cancelled");
    }

    // Update booking status
    $sql = "UPDATE bookings SET status = 'Cancelled' WHERE id = :booking_id AND user_id = :user_id";
    $stmt = $pdo->prepare($sql);
    $stmt->execute([
        'booking_id' => $booking_id,
        'user_id' => $_SESSION['user_id']
    ]);

    // Update train's booked seats
    $sql = "UPDATE trains SET booked_seats = booked_seats - ? 
            WHERE id = ? AND booked_seats >= ?";
    $stmt = $pdo->prepare($sql);
    $stmt->execute([
        $booking['passenger_count'],
        $booking['train_id'],
        $booking['passenger_count']
    ]);

    // Cancel associated food orders
    $sql = "UPDATE food_orders SET status = 'Cancelled' WHERE booking_id = :booking_id";
    $stmt = $pdo->prepare($sql);
    $stmt->execute(['booking_id' => $booking_id]);

    // Get passenger details for email
    $sql = "SELECT name, age, gender FROM passengers WHERE booking_id = :booking_id";
    $stmt = $pdo->prepare($sql);
    $stmt->execute(['booking_id' => $booking_id]);
    $passengers = $stmt->fetchAll();

    // Get food order details if any
    $sql = "SELECT fo.*, fm.item_name, fm.price 
            FROM food_orders fo 
            JOIN food_menu fm ON fo.menu_item_id = fm.id 
            WHERE fo.booking_id = :booking_id";
    $stmt = $pdo->prepare($sql);
    $stmt->execute(['booking_id' => $booking_id]);
    $food_orders = $stmt->fetchAll();

    // Prepare and send cancellation email
    $emailService = new EmailService();
    
    // Create booking details array for email
    $bookingDetails = [
        'booking_id' => $booking['id'],
        'status' => 'Cancelled',
        'train_name' => $booking['train_name'] . ' (' . $booking['train_number'] . ')',
        'journey_date' => date('d M Y', strtotime($booking['journey_date']))
    ];

    // Send booking status update email
    $emailService->sendBookingStatusUpdate(
        $booking['user_email'],
        $booking['user_name'],
        $bookingDetails
    );

    $pdo->commit();

    $_SESSION['toast'] = [
        'type' => 'success',
        'message' => 'Booking cancelled successfully'
    ];
    header("Location: my_bookings.php");
    exit;

} catch (Exception $e) {
    $pdo->rollBack();
    error_log("Cancellation error: " . $e->getMessage());
    $_SESSION['toast'] = [
        'type' => 'error',
        'message' => $e->getMessage()
    ];
    header("Location: my_bookings.php");
    exit;
} 