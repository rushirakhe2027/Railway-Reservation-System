<?php
session_start();
require_once "../includes/config.php";

// Check if user is logged in and is admin
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header("Location: ../login.php");
    exit;
}

// Get all bookings with user and train details
$sql = "SELECT b.*, t.train_name, t.train_number, t.source_station, t.destination_station, 
        t.departure_time, u.name as user_name, u.email, u.phone
        FROM bookings b
        JOIN trains t ON b.train_id = t.id
        JOIN users u ON b.user_id = u.id
        ORDER BY b.created_at DESC";
$stmt = $pdo->prepare($sql);
$stmt->execute();
$bookings = $stmt->fetchAll();

// Get passenger details for each booking
$booking_passengers = [];
foreach ($bookings as $booking) {
    $sql = "SELECT * FROM passengers WHERE booking_id = ?";
    $stmt = $pdo->prepare($sql);
    $stmt->execute([$booking['id']]);
    $booking_passengers[$booking['id']] = $stmt->fetchAll();
}

// Get food orders for each booking
$booking_food_orders = [];
foreach ($bookings as $booking) {
    $sql = "SELECT fo.*, fm.item_name, fv.vendor_name, fv.station_name
            FROM food_orders fo
            JOIN food_menu fm ON fo.menu_item_id = fm.id
            JOIN food_vendors fv ON fm.vendor_id = fv.id
            WHERE fo.booking_id = ?";
    $stmt = $pdo->prepare($sql);
    $stmt->execute([$booking['id']]);
    $booking_food_orders[$booking['id']] = $stmt->fetchAll();
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Bookings - RailYatra Admin</title>
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
            overflow: hidden;
            box-shadow: 0 2px 10px rgba(0,0,0,0.08);
            margin-bottom: 2rem;
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
            padding: 4px 12px;
            border-radius: 20px;
            font-size: 0.85rem;
        }

        .status-waiting {
            background: var(--accent-color);
            color: white;
        }

        .status-confirmed {
            background: #27ae60;
            color: white;
        }

        .status-cancelled {
            background: #e74c3c;
            color: white;
        }

        .btn-action {
            padding: 8px 20px;
            border-radius: 8px;
            transition: all 0.3s ease;
            border: none;
        }

        .btn-confirm {
            background: #27ae60;
            color: white;
        }

        .btn-cancel {
            background: #e74c3c;
            color: white;
        }

        .btn-action:hover {
            transform: translateY(-2px);
            opacity: 0.9;
            color: white;
        }

        .passenger-card {
            border: 1px solid var(--border-color);
            border-radius: 8px;
            padding: 1rem;
            margin-bottom: 1rem;
        }

        .food-order-card {
            border: 1px solid var(--border-color);
            border-radius: 8px;
            padding: 1rem;
            margin-bottom: 1rem;
        }

        .modal-content {
            border-radius: 12px;
            overflow: hidden;
        }

        .modal-header {
            background: linear-gradient(135deg, var(--gradient-start), var(--gradient-end));
            color: white;
            border: none;
        }

        .modal-header .btn-close {
            color: white;
        }

        .seat-input {
            border: 2px solid var(--border-color);
            border-radius: 8px;
            padding: 0.5rem;
            width: 100px;
            text-align: center;
        }

        .seat-input:focus {
            border-color: var(--primary-color);
            outline: none;
            box-shadow: 0 0 0 0.2rem rgba(231, 76, 60, 0.25);
        }
    </style>
</head>
<body>
    <div class="container-fluid">
        <div class="row">
            <!-- Sidebar -->
            <div class="col-md-3 col-lg-2 px-0 sidebar">
                <div class="p-4">
                    <h4 class="text-center mb-4">RailYatra Admin</h4>
                    <ul class="nav flex-column">
                        <li class="nav-item">
                            <a class="nav-link" href="dashboard.php">
                                <i class="fas fa-home me-2"></i>Dashboard
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" href="manage_trains.php">
                                <i class="fas fa-train me-2"></i>Manage Trains
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link active" href="manage_bookings.php">
                                <i class="fas fa-ticket-alt me-2"></i>Manage Bookings
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" href="manage_food_vendors.php">
                                <i class="fas fa-utensils me-2"></i>Food Vendors
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" href="manage_users.php">
                                <i class="fas fa-users me-2"></i>Manage Users
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
                <h2 class="mb-4">Manage Bookings</h2>

                <?php foreach ($bookings as $booking): ?>
                <div class="booking-card">
                    <div class="booking-header">
                        <div class="d-flex justify-content-between align-items-center">
                            <div>
                                <h4 class="mb-1">PNR: <?php echo htmlspecialchars($booking['pnr_number']); ?></h4>
                                <p class="mb-0">
                                    <?php echo htmlspecialchars($booking['train_name']); ?> 
                                    (<?php echo htmlspecialchars($booking['train_number']); ?>)
                                </p>
                            </div>
                            <div>
                                <span class="status-badge status-<?php echo strtolower($booking['status']); ?>">
                                    <?php echo htmlspecialchars($booking['status']); ?>
                                </span>
                            </div>
                        </div>
                    </div>
                    <div class="booking-body">
                        <div class="row mb-4">
                            <div class="col-md-4">
                                <h6>Journey Details</h6>
                                <p class="mb-1">
                                    <strong>From:</strong> <?php echo htmlspecialchars($booking['source_station']); ?>
                                </p>
                                <p class="mb-1">
                                    <strong>To:</strong> <?php echo htmlspecialchars($booking['destination_station']); ?>
                                </p>
                                <p class="mb-1">
                                    <strong>Date:</strong> 
                                    <?php echo date('d M Y', strtotime($booking['journey_date'])); ?>
                                </p>
                                <p class="mb-1">
                                    <strong>Time:</strong> 
                                    <?php echo date('h:i A', strtotime($booking['departure_time'])); ?>
                                </p>
                            </div>
                            <div class="col-md-4">
                                <h6>User Details</h6>
                                <p class="mb-1">
                                    <strong>Name:</strong> <?php echo htmlspecialchars($booking['user_name']); ?>
                                </p>
                                <p class="mb-1">
                                    <strong>Email:</strong> <?php echo htmlspecialchars($booking['email']); ?>
                                </p>
                                <p class="mb-1">
                                    <strong>Phone:</strong> <?php echo htmlspecialchars($booking['phone']); ?>
                                </p>
                            </div>
                            <div class="col-md-4">
                                <h6>Payment Details</h6>
                                <p class="mb-1">
                                    <strong>Amount:</strong> ₹<?php echo number_format($booking['total_amount'], 2); ?>
                                </p>
                                <p class="mb-1">
                                    <strong>Booked On:</strong> 
                                    <?php echo date('d M Y h:i A', strtotime($booking['created_at'])); ?>
                                </p>
                            </div>
                        </div>

                        <div class="row">
                            <div class="col-md-6">
                                <h6>Passengers</h6>
                                <?php foreach ($booking_passengers[$booking['id']] as $passenger): ?>
                                <div class="passenger-card">
                                    <div class="d-flex justify-content-between align-items-center">
                                        <div>
                                            <p class="mb-1">
                                                <strong>Name:</strong> <?php echo htmlspecialchars($passenger['name']); ?>
                                            </p>
                                            <p class="mb-1">
                                                <strong>Age:</strong> <?php echo htmlspecialchars($passenger['age']); ?>
                                            </p>
                                            <p class="mb-1">
                                                <strong>Gender:</strong> <?php echo htmlspecialchars($passenger['gender']); ?>
                                            </p>
                                        </div>
                                        <div>
                                            <?php if ($passenger['seat_number']): ?>
                                            <span class="badge bg-success">
                                                Seat: <?php echo htmlspecialchars($passenger['seat_number']); ?>
                                            </span>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                </div>
                                <?php endforeach; ?>
                            </div>

                            <?php if (!empty($booking_food_orders[$booking['id']])): ?>
                            <div class="col-md-6">
                                <h6>Food Orders</h6>
                                <?php foreach ($booking_food_orders[$booking['id']] as $order): ?>
                                <div class="food-order-card">
                                    <p class="mb-1">
                                        <strong>Item:</strong> <?php echo htmlspecialchars($order['item_name']); ?>
                                    </p>
                                    <p class="mb-1">
                                        <strong>Quantity:</strong> <?php echo htmlspecialchars($order['quantity']); ?>
                                    </p>
                                    <p class="mb-1">
                                        <strong>Amount:</strong> ₹<?php echo number_format($order['total_amount'], 2); ?>
                                    </p>
                                    <p class="mb-1">
                                        <strong>Delivery at:</strong> <?php echo htmlspecialchars($order['station_name']); ?>
                                    </p>
                                    <p class="mb-0">
                                        <strong>Status:</strong> 
                                        <span class="badge bg-<?php echo $order['status'] === 'Pending' ? 'warning' : 
                                            ($order['status'] === 'Confirmed' ? 'success' : 
                                            ($order['status'] === 'Delivered' ? 'info' : 'danger')); ?>">
                                            <?php echo htmlspecialchars($order['status']); ?>
                                        </span>
                                    </p>
                                </div>
                                <?php endforeach; ?>
                            </div>
                            <?php endif; ?>
                        </div>

                        <?php if ($booking['status'] === 'Waiting'): ?>
                        <div class="mt-4">
                            <button type="button" class="btn btn-action btn-confirm me-2" 
                                    onclick="showConfirmModal(<?php echo $booking['id']; ?>)">
                                <i class="fas fa-check me-2"></i>Confirm Booking
                            </button>
                            <button type="button" class="btn btn-action btn-cancel" 
                                    onclick="updateBookingStatus(<?php echo $booking['id']; ?>, 'Cancelled')">
                                <i class="fas fa-times me-2"></i>Cancel Booking
                            </button>
                        </div>
                        <?php endif; ?>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
        </div>
    </div>

    <!-- Confirm Booking Modal -->
    <div class="modal fade" id="confirmBookingModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Assign Seats</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <form id="seatAssignmentForm">
                        <input type="hidden" id="bookingId" name="booking_id">
                        <div id="seatInputs"></div>
                    </form>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="button" class="btn btn-primary" onclick="confirmBooking()">
                        Confirm Booking
                    </button>
                </div>
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

        // Store passengers data
        const bookingPassengers = <?php echo json_encode($booking_passengers); ?>;

        // Show confirm modal with seat inputs
        function showConfirmModal(bookingId) {
            const passengers = bookingPassengers[bookingId];
            let inputs = '';
            
            passengers.forEach(passenger => {
                inputs += `
                    <div class="mb-3">
                        <label class="form-label">Seat for ${passenger.name}</label>
                        <input type="text" class="seat-input" 
                               name="seat_numbers[${passenger.id}]" required
                               placeholder="e.g. A1">
                    </div>
                `;
            });

            document.getElementById('bookingId').value = bookingId;
            document.getElementById('seatInputs').innerHTML = inputs;
            
            new bootstrap.Modal(document.getElementById('confirmBookingModal')).show();
        }

        // Confirm booking with seat assignments
        function confirmBooking() {
            const form = document.getElementById('seatAssignmentForm');
            const formData = new FormData(form);
            formData.append('status', 'Confirmed');

            fetch('ajax/update_booking_status.php', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    toastr.success(data.message);
                    setTimeout(() => location.reload(), 2000);
                } else {
                    toastr.error(data.message);
                }
            })
            .catch(error => {
                toastr.error('Failed to update booking status');
            });
        }

        // Update booking status
        function updateBookingStatus(bookingId, status) {
            if (!confirm(`Are you sure you want to ${status.toLowerCase()} this booking?`)) {
                return;
            }

            const formData = new FormData();
            formData.append('booking_id', bookingId);
            formData.append('status', status);

            fetch('ajax/update_booking_status.php', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    toastr.success(data.message);
                    setTimeout(() => location.reload(), 2000);
                } else {
                    toastr.error(data.message);
                }
            })
            .catch(error => {
                toastr.error('Failed to update booking status');
            });
        }

        <?php if(isset($_SESSION['toast'])): ?>
        toastr.<?php echo $_SESSION['toast']['type']; ?>('<?php echo $_SESSION['toast']['message']; ?>');
        <?php unset($_SESSION['toast']); endif; ?>
    </script>
</body>
</html> 