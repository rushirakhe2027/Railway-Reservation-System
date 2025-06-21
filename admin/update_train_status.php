<?php
session_start();
require_once "../includes/admin_auth.php";
require_once "../includes/db.php";
require_once "../includes/config.php";

// Require admin login
requireAdminLogin();

// Set JSON response header
header('Content-Type: application/json');

// Check if request is POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Invalid request method']);
    exit;
}

// Get POST data
$trainId = $_POST['train_id'] ?? null;
$status = $_POST['status'] ?? null;

// Validate input
if (!$trainId || !$status) {
    echo json_encode(['success' => false, 'message' => 'Missing required parameters']);
    exit;
}

// Validate status
$validStatuses = ['Active', 'Delayed', 'Cancelled'];
if (!in_array($status, $validStatuses)) {
    echo json_encode(['success' => false, 'message' => 'Invalid status']);
    exit;
}

try {
    // Start transaction
    $pdo->beginTransaction();

    if ($status === 'Delayed') {
        // For Delayed status, validate and set delay duration
        $delayDuration = $_POST['delay_duration'] ?? null;
        if (!is_numeric($delayDuration) || $delayDuration < 1) {
            throw new Exception('Invalid delay duration');
        }
        $sql = "UPDATE trains SET status = ?, delay_duration = ? WHERE id = ?";
        $params = [$status, $delayDuration, $trainId];
    } else {
        // For Active or Cancelled status, set delay_duration to NULL
        $sql = "UPDATE trains SET status = ?, delay_duration = NULL WHERE id = ?";
        $params = [$status, $trainId];
    }

    // Execute the update
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);

    // If train is cancelled, update all pending bookings and food orders
    if ($status === 'Cancelled') {
        // Cancel pending bookings
        $sql = "UPDATE bookings SET status = 'Cancelled' 
                WHERE train_id = ? AND status = 'Pending' 
                AND journey_date >= CURDATE()";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([$trainId]);

        // Cancel pending food orders
        $sql = "UPDATE food_orders fo 
                JOIN bookings b ON fo.booking_id = b.id 
                SET fo.status = 'Cancelled' 
                WHERE b.train_id = ? AND fo.status = 'Pending'";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([$trainId]);
    }

    // Get affected bookings for notifications
    $sql = "SELECT b.id, b.pnr_number, u.email, u.name, t.train_number, t.train_name 
            FROM bookings b 
            JOIN users u ON b.user_id = u.id 
            JOIN trains t ON b.train_id = t.id 
            WHERE b.train_id = ? AND b.journey_date >= CURDATE()";
    $stmt = $pdo->prepare($sql);
    $stmt->execute([$trainId]);
    $affectedBookings = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Commit transaction
    $pdo->commit();

    // Send notifications to affected users
    foreach ($affectedBookings as $booking) {
        // Prepare email content
        $subject = "Train Status Update - PNR: {$booking['pnr_number']}";
        
        // Create status message based on the new status
        if ($status === 'Cancelled') {
            $statusMessage = 'cancelled';
        } elseif ($status === 'Delayed') {
            $statusMessage = "delayed by {$delayDuration} minutes";
        } else {
            $statusMessage = 'now active and running as scheduled';
        }
        
        $message = "
            <h2>Train Status Update</h2>
            <p>Dear {$booking['name']},</p>
            <p>This is to inform you that the train {$booking['train_number']} - {$booking['train_name']} 
               for your booking (PNR: {$booking['pnr_number']}) has been {$statusMessage}.</p>
            <p>We apologize for any inconvenience caused.</p>
            <p>Thank you for choosing RailYatra.</p>";

        // Send email using EmailSender class
        $emailSender = new EmailSender();
        $emailSender->sendEmail($booking['email'], $subject, $message);
    }

    // Return success response
    echo json_encode([
        'success' => true, 
        'message' => 'Train status updated successfully',
        'data' => [
            'status' => $status,
            'affected_bookings' => count($affectedBookings)
        ]
    ]);

} catch (Exception $e) {
    // Rollback transaction on error
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    
    // Return error response
    echo json_encode([
        'success' => false,
        'message' => 'Failed to update train status: ' . $e->getMessage()
    ]);
} 