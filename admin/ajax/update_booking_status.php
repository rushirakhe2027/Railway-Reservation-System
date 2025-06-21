<?php
session_start();
require_once "../../includes/config.php";
require_once "../../includes/email_notification.php";

// Check if user is logged in and is admin
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Unauthorized access']);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        if (!isset($_POST['booking_id']) || !isset($_POST['status'])) {
            throw new Exception('Missing required parameters');
        }

        $booking_id = $_POST['booking_id'];
        $status = $_POST['status'];
        $seat_numbers = isset($_POST['seat_numbers']) ? $_POST['seat_numbers'] : [];

        // Begin transaction
        $pdo->beginTransaction();

        // Update booking status
        $sql = "UPDATE bookings SET status = ? WHERE id = ?";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([$status, $booking_id]);

        // If status is 'Confirmed', update seat numbers and send email
        if ($status === 'Confirmed' && !empty($seat_numbers)) {
            $sql = "UPDATE passengers SET seat_number = ? WHERE id = ?";
            $stmt = $pdo->prepare($sql);
            
            foreach ($seat_numbers as $passenger_id => $seat_number) {
                $stmt->execute([$seat_number, $passenger_id]);
            }

            // Send confirmation email
            if (!sendTicketConfirmationEmail($booking_id)) {
                throw new Exception('Failed to send confirmation email');
            }
        }

        $pdo->commit();

        echo json_encode([
            'success' => true,
            'message' => 'Booking status updated successfully' . 
                        ($status === 'Confirmed' ? ' and confirmation email sent' : '')
        ]);

    } catch (Exception $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => $e->getMessage()]);
    }
} else {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method not allowed']);
}
?> 