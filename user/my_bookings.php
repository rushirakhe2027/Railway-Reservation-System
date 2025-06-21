<?php
session_start();
require_once "../includes/config.php";

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header("Location: ../login.php");
    exit;
}

// Handle booking cancellation
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['cancel_booking'])) {
    $booking_id = $_POST['booking_id'];
    
    try {
        $pdo->beginTransaction();

        // Check if booking exists and belongs to user
        $sql = "SELECT * FROM bookings WHERE id = ? AND user_id = ? AND status != 'Cancelled'";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([$booking_id, $_SESSION['user_id']]);
        $booking = $stmt->fetch();

        if ($booking) {
            // Update booking status
            $sql = "UPDATE bookings SET status = 'Cancelled' WHERE id = ?";
            $stmt = $pdo->prepare($sql);
            $stmt->execute([$booking_id]);

            // Cancel associated food orders
            $sql = "UPDATE food_orders SET status = 'Cancelled' WHERE booking_id = ?";
            $stmt = $pdo->prepare($sql);
            $stmt->execute([$booking_id]);

            $pdo->commit();
            $_SESSION['toast'] = [
                'type' => 'success',
                'message' => 'Booking cancelled successfully!'
            ];
        } else {
            throw new Exception('Invalid booking or already cancelled');
        }
    } catch (Exception $e) {
        $pdo->rollBack();
        $_SESSION['toast'] = [
            'type' => 'error',
            'message' => 'Failed to cancel booking: ' . $e->getMessage()
        ];
    }
}

// Get user's bookings with train and passenger details
$sql = "SELECT b.*, t.train_name, t.train_number, 
        t.source_station, t.destination_station,
        t.departure_time, t.arrival_time,
        GROUP_CONCAT(COALESCE(p.name, 'N/A')) as passenger_names,
        GROUP_CONCAT(COALESCE(p.age, 0)) as passenger_ages,
        GROUP_CONCAT(COALESCE(p.gender, 'N/A')) as passenger_genders
        FROM bookings b
        JOIN trains t ON b.train_id = t.id
        LEFT JOIN passengers p ON b.id = p.booking_id
        WHERE b.user_id = ?
        GROUP BY b.id
        ORDER BY b.created_at DESC";
$stmt = $pdo->prepare($sql);
$stmt->execute([$_SESSION['user_id']]);
$bookings = $stmt->fetchAll();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>My Bookings - RailYatra</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/toastr.js/latest/toastr.min.css">
    <style>
        :root {
            --primary-color: #e74c3c;
            --secondary-color: #e67e22;
            --accent-color: #f39c12;
            --text-color: #2c3e50;
            --light-text: #7f8c8d;
            --border-color: #ecf0f1;
            --gradient-start: #e74c3c;
            --gradient-end: #f39c12;
            --hover-color: #c0392b;
        }

        body {
            font-family: 'Google Sans', Arial, sans-serif;
            background-color: var(--border-color);
            color: var(--text-color);
            min-height: 100vh;
        }

        .sidebar {
            background: linear-gradient(135deg, var(--gradient-start), var(--gradient-end));
            color: white;
            min-height: 100vh;
        }

        .nav-link {
            color: rgba(255,255,255,0.9);
            border-radius: 8px;
            margin: 4px 0;
            transition: all 0.3s ease;
        }

        .nav-link:hover, .nav-link.active {
            background: rgba(255,255,255,0.2);
            color: white;
            transform: translateX(5px);
        }

        .nav-link.active {
            background: white !important;
            color: var(--primary-color);
        }

        .booking-card {
            background: white;
            border-radius: 12px;
            margin-bottom: 1.5rem;
            overflow: hidden;
            box-shadow: 0 2px 10px rgba(0,0,0,0.08);
            transition: transform 0.3s ease;
        }

        .booking-card:hover {
            transform: translateY(-5px);
        }

        .booking-header {
            background: linear-gradient(135deg, var(--gradient-start), var(--gradient-end));
            color: white;
            padding: 1.5rem;
        }

        .booking-body {
            padding: 1.5rem;
        }

        .status-badge {
            padding: 6px 12px;
            border-radius: 20px;
            font-size: 0.85rem;
            font-weight: 500;
            color: white;
        }

        .status-Confirmed { background-color: #27ae60; }
        .status-Pending { background-color: #f39c12; }
        .status-Cancelled { background-color: #c0392b; }

        .passenger-list {
            list-style: none;
            padding: 0;
            margin: 0;
        }

        .passenger-item {
            padding: 10px;
            border-bottom: 1px solid var(--border-color);
        }

        .passenger-item:last-child {
            border-bottom: none;
        }

        .btn-cancel {
            color: var(--primary-color);
            background: rgba(231, 76, 60, 0.1);
            border: none;
            padding: 8px 20px;
            border-radius: 8px;
            transition: all 0.3s ease;
        }

        .btn-cancel:hover {
            background: rgba(231, 76, 60, 0.2);
            transform: translateY(-2px);
        }

        .train-info {
            display: flex;
            align-items: center;
            margin: 1rem 0;
        }

        .station-dot {
            width: 12px;
            height: 12px;
            background: white;
            border-radius: 50%;
        }

        .station-line {
            flex-grow: 1;
            height: 2px;
            background: rgba(255,255,255,0.5);
            margin: 0 10px;
        }

        .booking-actions {
            display: flex;
            gap: 1rem;
            margin-top: 1rem;
        }

        .btn-action {
            flex: 1;
            text-align: center;
            padding: 8px;
            border-radius: 8px;
            transition: all 0.3s ease;
        }

        .btn-view-ticket {
            background: var(--primary-color);
            color: white;
            border: none;
        }

        .btn-view-ticket:hover {
            background: var(--hover-color);
            transform: translateY(-2px);
        }
    </style>
</head>
<body>
    <div class="container-fluid">
        <div class="row">
            <!-- Sidebar -->
            <div class="col-md-3 col-lg-2 px-0 sidebar">
                <div class="p-4">
                    <h4 class="text-center mb-4">RailYatra</h4>
                    <ul class="nav flex-column">
                        <li class="nav-item">
                            <a class="nav-link" href="dashboard.php">
                                <i class="fas fa-home me-2"></i>Dashboard
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" href="search_trains.php">
                                <i class="fas fa-search me-2"></i>Search Trains
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link active" href="my_bookings.php">
                                <i class="fas fa-ticket-alt me-2"></i>My Bookings
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" href="food_orders.php">
                                <i class="fas fa-utensils me-2"></i>Food Orders
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" href="profile.php">
                                <i class="fas fa-user me-2"></i>Profile
                            </a>
                        </li>
                        <li class="nav-item mt-3">
                            <a class="nav-link text-danger" href="../logout.php">
                                <i class="fas fa-sign-out-alt me-2"></i>Logout
                            </a>
                        </li>
                    </ul>
                </div>
            </div>

            <!-- Main Content -->
            <div class="col-md-9 col-lg-10 p-4">
                <h2 class="mb-4">My Bookings</h2>

                <?php if (empty($bookings)): ?>
                <div class="alert alert-info">
                    <i class="fas fa-info-circle me-2"></i>You haven't made any bookings yet.
                    <a href="search_trains.php" class="alert-link">Search trains</a> to book your journey.
                </div>
                <?php else: ?>
                    <?php foreach ($bookings as $booking): ?>
                    <div class="booking-card">
                        <div class="booking-header">
                            <div class="d-flex justify-content-between align-items-center">
                                <div>
                                    <h4 class="mb-1"><?php echo htmlspecialchars($booking['train_name']); ?></h4>
                                    <span class="me-3"><?php echo htmlspecialchars($booking['train_number']); ?></span>
                                    <span class="status-badge status-<?php echo $booking['status']; ?>">
                                        <?php echo $booking['status']; ?>
                                    </span>
                                </div>
                                <?php if ($booking['status'] == 'Confirmed'): ?>
                                <form method="POST" onsubmit="return confirm('Are you sure you want to cancel this booking?');">
                                    <input type="hidden" name="booking_id" value="<?php echo $booking['id']; ?>">
                                    <button type="submit" name="cancel_booking" class="btn btn-cancel">
                                        <i class="fas fa-times me-2"></i>Cancel Booking
                                    </button>
                                </form>
                                <?php endif; ?>
                            </div>

                            <div class="train-info">
                                <div class="text-center">
                                    <div class="station-dot"></div>
                                    <small><?php echo htmlspecialchars($booking['source_station']); ?></small><br>
                                    <small><?php echo date('h:i A', strtotime($booking['departure_time'])); ?></small>
                                </div>
                                <div class="station-line"></div>
                                <div class="text-center">
                                    <div class="station-dot"></div>
                                    <small><?php echo htmlspecialchars($booking['destination_station']); ?></small><br>
                                    <small><?php echo date('h:i A', strtotime($booking['arrival_time'])); ?></small>
                                </div>
                            </div>

                            <div class="mt-2">
                                <small class="text-white">
                                    <i class="fas fa-calendar me-2"></i>
                                    <?php echo date('d M Y', strtotime($booking['booking_date'])); ?>
                                </small>
                            </div>
                        </div>

                        <div class="booking-body">
                            <h5 class="mb-3">Passenger Details</h5>
                            <ul class="passenger-list">
                                <?php
                                $names = explode(',', $booking['passenger_names']);
                                $ages = explode(',', $booking['passenger_ages']);
                                $genders = explode(',', $booking['passenger_genders']);
                                
                                foreach ($names as $i => $name):
                                ?>
                                <li class="passenger-item">
                                    <div class="d-flex justify-content-between">
                                        <div>
                                            <strong><?php echo htmlspecialchars($name); ?></strong>
                                            <br>
                                            <small class="text-muted">
                                                <?php echo $ages[$i]; ?> years • <?php echo $genders[$i]; ?>
                                            </small>
                                        </div>
                                        <div class="text-end">
                                            <strong>Seat <?php echo $i + 1; ?></strong>
                                        </div>
                                    </div>
                                </li>
                                <?php endforeach; ?>
                            </ul>

                            <div class="d-flex justify-content-between align-items-center mt-3">
                                <div>
                                    <small class="text-muted">Total Amount</small>
                                    <h5 class="mb-0">₹<?php echo number_format($booking['total_amount'], 2); ?></h5>
                                </div>
                                <a href="view_ticket.php?booking_id=<?php echo $booking['id']; ?>" 
                                   class="btn btn-view-ticket">
                                    <i class="fas fa-ticket-alt me-2"></i>View Ticket
                                </a>
                            </div>
                        </div>
                    </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/toastr.js/latest/toastr.min.js"></script>
    <script>
        // Initialize toastr
        toastr.options = {
            closeButton: true,
            progressBar: true,
            positionClass: "toast-top-right",
            timeOut: 3000
        };

        <?php if(isset($_SESSION['toast'])): ?>
        toastr.<?php echo $_SESSION['toast']['type']; ?>('<?php echo $_SESSION['toast']['message']; ?>');
        <?php unset($_SESSION['toast']); endif; ?>
    </script>
</body>
</html> 