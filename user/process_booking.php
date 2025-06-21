<?php
session_start();
require_once "../includes/config.php";
require_once "../includes/db.php";
require_once "../includes/email.php";  // Changed from EmailSender.php to email.php
require_once "../MailUtils/vendor/autoload.php"; // Add PHPMailer

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

// Enable error reporting for debugging
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Verify user is logged in
if (!isLoggedIn()) {
    $_SESSION['toast'] = [
        'type' => 'error',
        'message' => 'Please login to continue'
    ];
    header("Location: ../login.php");
    exit;
}

try {
    // Validate form data
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        throw new Exception('Invalid request method');
    }

    // Get and validate required fields
    $train_id = filter_input(INPUT_POST, 'train_id', FILTER_VALIDATE_INT);
    $from_station = trim($_POST['from_station'] ?? '');
    $to_station = trim($_POST['to_station'] ?? '');
    $journey_date = trim($_POST['journey_date'] ?? '');
    
    if (!$train_id || empty($from_station) || empty($to_station) || empty($journey_date)) {
        throw new Exception('Missing required booking information');
    }

    // Validate journey date
    $journey_timestamp = strtotime($journey_date);
    $today = strtotime('today');
    if ($journey_timestamp < $today) {
        throw new Exception('Journey date cannot be in the past');
    }

    // Validate stations are different
    if ($from_station === $to_station) {
        throw new Exception('Source and destination stations cannot be the same');
    }

    // Validate passenger information
    $passenger_names = $_POST['passenger_name'] ?? [];
    $passenger_ages = $_POST['passenger_age'] ?? [];
    $passenger_genders = $_POST['passenger_gender'] ?? [];

    if (empty($passenger_names)) {
        throw new Exception('At least one passenger is required');
    }

    // Validate each passenger's details
    foreach ($passenger_names as $index => $name) {
        if (empty($name) || empty($passenger_ages[$index]) || empty($passenger_genders[$index])) {
            throw new Exception('All passenger details are required');
        }
        if ($passenger_ages[$index] < 1 || $passenger_ages[$index] > 120) {
            throw new Exception('Invalid passenger age');
        }
    }

    // Start transaction
    $pdo->beginTransaction();

    // Check seat availability first
    $stmt = $pdo->prepare("SELECT total_seats, booked_seats, train_name FROM trains WHERE id = ? FOR UPDATE");
    $stmt->execute([$train_id]);
    $train = $stmt->fetch();
    
    if (!$train) {
        throw new Exception('Train not found');
    }

    $num_passengers = count($passenger_names);
    if (($train['booked_seats'] + $num_passengers) > $train['total_seats']) {
        throw new Exception("Not enough seats available. Only " . 
            ($train['total_seats'] - $train['booked_seats']) . " seats left.");
    }

    // Calculate fare
    $stmt = $pdo->prepare("SELECT base_fare FROM station_pricing 
                          WHERE train_id = ? AND 
                          ((from_station = ? AND to_station = ?) OR 
                           (from_station = ? AND to_station = ?))");
    $stmt->execute([$train_id, $from_station, $to_station, $to_station, $from_station]);
    $pricing = $stmt->fetch();

    // If pricing is not found, try to get a default fare or calculate based on distance
    if (!$pricing) {
        // Try to get average fare for this train
        $stmt = $pdo->prepare("SELECT AVG(base_fare) as avg_fare FROM station_pricing WHERE train_id = ?");
        $stmt->execute([$train_id]);
        $avg_pricing = $stmt->fetch();
        
        if ($avg_pricing && $avg_pricing['avg_fare'] > 0) {
            $base_fare = $avg_pricing['avg_fare'];
        } else {
            // Use a default fare as last resort
            $base_fare = 500; // Default fare of 500 rupees
        }
        
        // Log that we're using a fallback fare
        error_log("Using fallback fare for train {$train_id} from {$from_station} to {$to_station}: {$base_fare}");
    } else {
        $base_fare = $pricing['base_fare'];
    }
    
    $total_base_fare = $base_fare * $num_passengers;
    
    // Process any food orders (this is optional)
    $food_total = 0;
    $food_items = isset($_POST['food_items']) ? $_POST['food_items'] : [];
    
    // Process food items - only those with quantity > 0
    $food_orders = [];
    if (!empty($food_items)) {
        foreach ($food_items as $item_id => $quantity) {
            if ($quantity > 0) {
                // Get food item details
                $stmt = $pdo->prepare("SELECT item_name, price FROM food_menu WHERE id = ?");
                $stmt->execute([$item_id]);
                $food_item = $stmt->fetch();
                
                if ($food_item) {
                    $item_total = $food_item['price'] * $quantity;
                    $food_total += $item_total;
                    $food_orders[] = [
                        'item_id' => $item_id,
                        'quantity' => $quantity,
                        'total' => $item_total,
                        'name' => $food_item['item_name']
                    ];
                }
            }
        }
    }
    
    // Total amount including food
    $total_amount = $total_base_fare + $food_total;

    // Generate unique PNR number
    do {
        $pnr_number = date('ym') . str_pad(mt_rand(0, 999999), 6, '0', STR_PAD_LEFT);
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM bookings WHERE pnr_number = ?");
        $stmt->execute([$pnr_number]);
    } while ($stmt->fetchColumn() > 0);

    // Create booking
    $stmt = $pdo->prepare("INSERT INTO bookings (user_id, train_id, from_station, to_station, 
                          journey_date, pnr_number, total_amount, status, created_at) 
                          VALUES (?, ?, ?, ?, ?, ?, ?, 'Pending', NOW())");
    $stmt->execute([
        $_SESSION['user_id'],
        $train_id,
        $from_station,
        $to_station,
        $journey_date,
        $pnr_number,
        $total_amount  // Using the total amount that includes food
    ]);
    $booking_id = $pdo->lastInsertId();

    // Add passengers
    $stmt = $pdo->prepare("INSERT INTO passengers (booking_id, name, age, gender) 
                          VALUES (?, ?, ?, ?)");
    foreach ($passenger_names as $index => $name) {
        $stmt->execute([
            $booking_id,
            $name,
            $passenger_ages[$index],
            $passenger_genders[$index]
        ]);
    }

    // Add food orders if any
    if (!empty($food_orders)) {
        $stmt = $pdo->prepare("INSERT INTO food_orders (booking_id, menu_item_id, quantity, 
                              total_amount, delivery_station, status, created_at) 
                              VALUES (?, ?, ?, ?, ?, 'Pending', NOW())");
        
        foreach ($food_orders as $order) {
            $delivery_station = $to_station; // Default delivery at destination
            $stmt->execute([
                $booking_id,
                $order['item_id'],
                $order['quantity'],
                $order['total'],
                $delivery_station
            ]);
        }
    }

    // Update train's booked seats
    $stmt = $pdo->prepare("UPDATE trains SET booked_seats = booked_seats + ? 
                          WHERE id = ? AND (total_seats - booked_seats) >= ?");
    if (!$stmt->execute([$num_passengers, $train_id, $num_passengers])) {
        throw new Exception('Failed to update seat availability');
    }

    // Send booking confirmation email
    try {
        $emailService = new EmailService();  // Using EmailService instead of EmailSender
        $stmt = $pdo->prepare("SELECT name, email FROM users WHERE id = ?");
        $stmt->execute([$_SESSION['user_id']]);
        $user = $stmt->fetch();

        $bookingDetails = [
            'booking_id' => $booking_id,
            'train_name' => $train['train_name'],
            'from_station' => $from_station,
            'to_station' => $to_station,
            'journey_date' => $journey_date,
            'amount' => $total_amount  // Using the total amount that includes food
        ];

        $emailService->sendBookingConfirmation(
            $user['email'],
            $user['name'],
            $bookingDetails
        );
    } catch (Exception $e) {
        // Log email error but don't rollback transaction
        error_log("Failed to send booking confirmation email: " . $e->getMessage());
    }

    $pdo->commit();

    $_SESSION['toast'] = [
        'type' => 'success',
        'message' => 'Booking successful! Your PNR number is: ' . $pnr_number
    ];
    header("Location: booking_confirmation.php?pnr=" . urlencode($pnr_number));
    exit;

} catch (Exception $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    error_log("Booking error: " . $e->getMessage());
    $_SESSION['toast'] = [
        'type' => 'error',
        'message' => 'Booking failed: ' . $e->getMessage()
    ];
    header("Location: book_train.php?train_id=" . ($train_id ?? ''));
    exit;
}
?> 