<?php
session_start();
require_once "../includes/config.php";
require_once "../includes/admin_auth.php";

// Check if admin is logged in
requireAdminLogin();

// Handle booking operations
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    if (isset($_POST['action'])) {
        switch ($_POST['action']) {
            case 'update_status':
                $sql = "UPDATE bookings SET status = ? WHERE id = ?";
                $stmt = $pdo->prepare($sql);
                $stmt->execute([$_POST['status'], $_POST['booking_id']]);
                showToast("Booking status updated successfully!", "success");
                break;

            case 'delete':
                $sql = "DELETE FROM bookings WHERE id = ?";
                $stmt = $pdo->prepare($sql);
                $stmt->execute([$_POST['booking_id']]);
                showToast("Booking deleted successfully!", "success");
                break;
        }
    }
}

// Get booking statistics
$sql = "SELECT 
        COUNT(*) as total_bookings,
        COUNT(CASE WHEN status = 'Confirmed' THEN 1 END) as confirmed_bookings,
        COUNT(CASE WHEN status = 'Cancelled' THEN 1 END) as cancelled_bookings,
        COUNT(CASE WHEN status = 'Waiting' THEN 1 END) as waiting_bookings,
        SUM(total_amount) as total_revenue
        FROM bookings";
$stmt = $pdo->query($sql);
$stats = $stmt->fetch();

// Get all bookings with user and train details
$sql = "SELECT b.*, u.name as user_name, u.email as user_email, 
        t.train_number, t.train_name, t.source_station, t.destination_station,
        COALESCE(p.seat_number, 'Not Assigned') as seat_number,
        'General' as class_type,
        (SELECT GROUP_CONCAT(CONCAT(fm.item_name, ' (', fo.quantity, ')') SEPARATOR ', ')
         FROM food_orders fo 
         JOIN food_menu fm ON fo.menu_item_id = fm.id
         WHERE fo.booking_id = b.id) as food_items,
        (SELECT SUM(fo.quantity * fm.price)
         FROM food_orders fo 
         JOIN food_menu fm ON fo.menu_item_id = fm.id
         WHERE fo.booking_id = b.id) as food_total,
        (SELECT GROUP_CONCAT(DISTINCT fo.delivery_station) 
         FROM food_orders fo 
         WHERE fo.booking_id = b.id) as delivery_stations
        FROM bookings b 
        JOIN users u ON b.user_id = u.id
        JOIN trains t ON b.train_id = t.id
        LEFT JOIN passengers p ON p.booking_id = b.id
        GROUP BY b.id
        ORDER BY b.created_at DESC";
$stmt = $pdo->query($sql);
$bookings = $stmt->fetchAll();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Bookings - RailYatra Admin</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/sweetalert2@11.0.18/dist/sweetalert2.min.css">
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

        .card {
            border: none;
            border-radius: 12px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.08);
            transition: transform 0.3s ease;
        }

        .card:hover {
            transform: translateY(-5px);
        }

        .stats-card {
            background: white;
            border-radius: 12px;
            padding: 1.5rem;
            margin-bottom: 1rem;
        }

        .stats-card i {
            font-size: 2rem;
            color: var(--primary-color);
        }

        .table {
            background: white;
            border-radius: 12px;
            overflow: hidden;
        }

        .table th {
            background: var(--primary-color);
            color: white;
            font-weight: 500;
        }

        .status-badge {
            padding: 6px 12px;
            border-radius: 20px;
            font-size: 0.85rem;
            font-weight: 500;
        }

        .status-Confirmed { background-color: #27ae60; color: white; }
        .status-Waiting { background-color: #f39c12; color: white; }
        .status-Cancelled { background-color: #c0392b; color: white; }

        .revenue-card {
            background: linear-gradient(135deg, var(--gradient-start), var(--gradient-end));
            color: white;
        }

        .revenue-card i {
            color: white !important;
        }

        /* Status colors */
        .status-dot.status-approved { 
            background-color: #28a745; 
        }
        .status-dot.status-pending { 
            background-color: #ffc107; 
        }
        .status-dot.status-cancelled { 
            background-color: #dc3545; 
        }

        /* Status text colors */
        .status-text.status-approved { 
            color: #28a745;
        }
        .status-text.status-pending { 
            color: #ffc107;
        }
        .status-text.status-cancelled { 
            color: #dc3545;
        }

        .status-indicator {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            padding: 6px 12px;
            border-radius: 20px;
            background: rgba(0, 0, 0, 0.05);
        }

        .status-dot {
            width: 8px;
            height: 8px;
            border-radius: 50%;
            display: inline-block;
        }

        .status-text {
            font-size: 0.875rem;
            font-weight: 500;
        }

        .btn-action {
            padding: 0.25rem 0.5rem;
            font-size: 0.875rem;
            transition: all 0.2s;
        }

        .btn-action:hover {
            background-color: #e9ecef;
        }

        .food-order-details {
            padding: 8px;
            background: rgba(40, 167, 69, 0.1);
            border-radius: 4px;
            margin-top: 4px;
            border-left: 3px solid #28a745;
        }

        .food-order-details small {
            display: block;
            line-height: 1.4;
        }

        .food-order-details .text-success {
            color: #28a745 !important;
            font-weight: 500;
        }

        .btn-sm {
            padding: 0.25rem 0.5rem;
            font-size: 0.875rem;
            border-radius: 4px;
        }

        .btn-success {
            background-color: #28a745;
            border-color: #28a745;
            color: white;
        }

        .btn-warning {
            background-color: #ffc107;
            border-color: #ffc107;
            color: #000;
        }

        .btn-danger {
            background-color: #dc3545;
            border-color: #dc3545;
            color: white;
        }

        .btn-info {
            background-color: #17a2b8;
            border-color: #17a2b8;
            color: white;
        }

        .btn-secondary {
            background-color: #6c757d;
            border-color: #6c757d;
            color: white;
        }

        .btn:hover {
            opacity: 0.9;
            transform: translateY(-1px);
        }

        .gap-2 {
            gap: 0.5rem !important;
        }

        .food-order-actions {
            display: flex;
            gap: 0.5rem;
        }

        .food-order-actions .btn {
            font-size: 0.75rem;
            padding: 0.2rem 0.5rem;
        }

        .btn-outline-success {
            color: #28a745;
            border-color: #28a745;
        }

        .btn-outline-success:hover {
            color: white;
            background-color: #28a745;
        }

        .btn-outline-info {
            color: #17a2b8;
            border-color: #17a2b8;
        }

        .btn-outline-info:hover {
            color: white;
            background-color: #17a2b8;
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
                                <i class="fas fa-tachometer-alt me-2"></i>Dashboard
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" href="trains.php">
                                <i class="fas fa-train me-2"></i>Trains
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link active" href="bookings.php">
                                <i class="fas fa-ticket-alt me-2"></i>Bookings
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" href="users.php">
                                <i class="fas fa-users me-2"></i>Users
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" href="food_vendors.php">
                                <i class="fas fa-utensils me-2"></i>Food Vendors
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" href="reports.php">
                                <i class="fas fa-chart-bar me-2"></i>Reports
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" href="settings.php">
                                <i class="fas fa-cog me-2"></i>Settings
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
                <div class="d-flex justify-content-between align-items-center mb-4">
                    <h2>Manage Bookings</h2>
                    <div>
                        <button class="btn btn-light me-2" onclick="exportBookings()">
                            <i class="fas fa-download me-2"></i>Export
                        </button>
                        <button class="btn btn-primary" onclick="printBookings()">
                            <i class="fas fa-print me-2"></i>Print
                        </button>
                    </div>
                </div>

                <!-- Statistics Cards -->
                <div class="row mb-4">
                    <div class="col-md-3">
                        <div class="stats-card">
                            <div class="d-flex justify-content-between align-items-center">
                                <div>
                                    <h3 class="mb-0"><?php echo $stats['total_bookings']; ?></h3>
                                    <p class="text-muted mb-0">Total Bookings</p>
                                </div>
                                <i class="fas fa-ticket-alt"></i>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="stats-card">
                            <div class="d-flex justify-content-between align-items-center">
                                <div>
                                    <h3 class="mb-0"><?php echo $stats['confirmed_bookings']; ?></h3>
                                    <p class="text-muted mb-0">Confirmed</p>
                                </div>
                                <i class="fas fa-check-circle"></i>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="stats-card">
                            <div class="d-flex justify-content-between align-items-center">
                                <div>
                                    <h3 class="mb-0"><?php echo $stats['waiting_bookings']; ?></h3>
                                    <p class="text-muted mb-0">Waiting</p>
                                </div>
                                <i class="fas fa-clock"></i>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="stats-card revenue-card">
                            <div class="d-flex justify-content-between align-items-center">
                                <div>
                                    <h3 class="mb-0">₹<?php echo number_format($stats['total_revenue'], 2); ?></h3>
                                    <p class="mb-0">Total Revenue</p>
                                </div>
                                <i class="fas fa-rupee-sign"></i>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Bookings Table -->
                <div class="card">
                    <div class="card-body p-0">
                        <div class="table-responsive">
                            <table class="table table-hover">
                                <thead>
                                    <tr class="bg-primary text-white">
                                        <th>PNR</th>
                                        <th>Passenger</th>
                                        <th>Train</th>
                                        <th>Journey</th>
                                        <th>Seat & Food</th>
                                        <th>Amount</th>
                                        <th>Status</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($bookings as $booking): ?>
                                    <tr>
                                        <td>
                                            <?php echo htmlspecialchars($booking['pnr_number']); ?><br>
                                            <small class="text-muted"><?php echo date('d M Y', strtotime($booking['created_at'])); ?></small>
                                        </td>
                                        <td>
                                            <?php echo htmlspecialchars($booking['user_name']); ?><br>
                                            <small class="text-muted"><?php echo htmlspecialchars($booking['user_email']); ?></small>
                                        </td>
                                        <td>
                                            <?php echo htmlspecialchars($booking['train_name']); ?><br>
                                            <small class="text-muted"><?php echo htmlspecialchars($booking['train_number']); ?></small>
                                        </td>
                                        <td>
                                            <?php echo htmlspecialchars($booking['source_station']); ?> → <?php echo htmlspecialchars($booking['destination_station']); ?><br>
                                            <small class="text-muted"><?php echo date('d M Y', strtotime($booking['journey_date'])); ?></small>
                                        </td>
                                        <td>
                                            <div class="mb-1">
                                                <strong><?php echo $booking['seat_number'] ?? 'Not Assigned'; ?></strong>
                                                <small class="text-muted">(<?php echo htmlspecialchars($booking['class_type']); ?>)</small>
                                            </div>
                                            <?php if (!empty($booking['food_items'])): ?>
                                            <div class="food-order-details">
                                                <small class="text-success">
                                                    <i class="fas fa-utensils me-1"></i>
                                                    <?php echo htmlspecialchars($booking['food_items']); ?>
                                                </small>
                                                <div class="mt-1">
                                                    <small class="text-muted">
                                                        Food Total: ₹<?php echo number_format($booking['food_total'], 2); ?><br>
                                                        Delivery at: <?php echo htmlspecialchars($booking['delivery_stations']); ?>
                                                    </small>
                                                </div>
                                                <div class="mt-2 food-order-actions">
                                                    <button class="btn btn-outline-success btn-sm food-status-btn" 
                                                            data-booking-id="<?= $booking['id'] ?>" 
                                                            data-status="Approved" 
                                                            title="Approve Food Order">
                                                        <i class="fas fa-check"></i> Approve
                                                    </button>
                                                    <button class="btn btn-outline-info btn-sm food-status-btn" 
                                                            data-booking-id="<?= $booking['id'] ?>" 
                                                            data-status="Delivered" 
                                                            title="Mark as Delivered">
                                                        <i class="fas fa-truck"></i> Delivered
                                                    </button>
                                                </div>
                                            </div>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            ₹<?php echo number_format($booking['total_amount'], 2); ?>
                                            <?php if (!empty($booking['food_total'])): ?>
                                            <br>
                                            <small class="text-muted">
                                                <i class="fas fa-utensils me-1"></i>+₹<?php echo number_format($booking['food_total'], 2); ?>
                                            </small>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <div class="status-indicator">
                                                <span class="status-dot status-<?= strtolower($booking['status']) ?>"></span>
                                                <span class="status-text status-<?= strtolower($booking['status']) ?>"><?= $booking['status'] ?></span>
                                            </div>
                                        </td>
                                        <td>
                                            <div class="d-flex gap-2">
                                                <a href="view_booking.php?id=<?= $booking['id'] ?>" class="btn btn-info btn-sm" title="View">
                                                    <i class="fas fa-eye"></i>
                                                </a>
                                                <a href="print_ticket.php?id=<?= $booking['id'] ?>" class="btn btn-secondary btn-sm" title="Print" target="_blank">
                                                    <i class="fas fa-print"></i>
                                                </a>
                                                <?php if ($booking['status'] !== 'Approved'): ?>
                                                <button class="btn btn-success btn-sm status-btn" data-status="Approved" data-booking-id="<?= $booking['id'] ?>" title="Approve">
                                                    <i class="fas fa-check-circle"></i>
                                                </button>
                                                <?php endif; ?>
                                                <?php if ($booking['status'] !== 'Pending'): ?>
                                                <button class="btn btn-warning btn-sm status-btn" data-status="Pending" data-booking-id="<?= $booking['id'] ?>" title="Mark as Pending">
                                                    <i class="fas fa-clock"></i>
                                                </button>
                                                <?php endif; ?>
                                                <?php if ($booking['status'] !== 'Cancelled'): ?>
                                                <button class="btn btn-danger btn-sm status-btn" data-status="Cancelled" data-booking-id="<?= $booking['id'] ?>" title="Cancel">
                                                    <i class="fas fa-times-circle"></i>
                                                </button>
                                                <?php endif; ?>
                                            </div>
                                        </td>
                                    </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11.0.18/dist/sweetalert2.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/toastr.js/latest/toastr.min.js"></script>
    <script>
        // Initialize toastr
        toastr.options = {
            "closeButton": true,
            "progressBar": true,
            "positionClass": "toast-top-right",
            "timeOut": "3000"
        };

        // Function to update status display
        function updateStatusDisplay(row, newStatus, foodCancelled) {
            const statusCell = row.find('.status-indicator');
            const actionButtons = row.find('.d-flex.gap-2');
            const foodDetails = row.find('.food-order-details');
            
            // Update status indicator
            const newStatusHtml = `
                <div class="status-indicator">
                    <span class="status-dot status-${newStatus.toLowerCase()}"></span>
                    <span class="status-text status-${newStatus.toLowerCase()}">${newStatus}</span>
                </div>`;
            statusCell.html(newStatusHtml);
            
            // Update food order status if cancelled
            if (foodCancelled && foodDetails.length) {
                if (!foodDetails.find('.text-danger').length) {
                    foodDetails.prepend('<small class="text-danger"><strong>Status:</strong> Cancelled</small><br>');
                }
            }
            
            // Keep view and print buttons
            const viewPrintButtons = actionButtons.find('a[href*="view_booking.php"], a[href*="print_ticket.php"]').clone();
            actionButtons.empty().append(viewPrintButtons);
            
            // Get booking ID
            const bookingId = row.find('a[href*="view_booking.php"]').attr('href').match(/id=(\d+)/)[1];
            
            // Add appropriate buttons based on new status
            if (newStatus !== 'Approved') {
                actionButtons.append(`
                    <button class="btn btn-success btn-sm status-btn" data-status="Approved" data-booking-id="${bookingId}" title="Approve">
                        <i class="fas fa-check-circle"></i>
                    </button>`);
            }
            
            if (newStatus !== 'Pending') {
                actionButtons.append(`
                    <button class="btn btn-warning btn-sm status-btn" data-status="Pending" data-booking-id="${bookingId}" title="Mark as Pending">
                        <i class="fas fa-clock"></i>
                    </button>`);
            }
            
            if (newStatus !== 'Cancelled') {
                actionButtons.append(`
                    <button class="btn btn-danger btn-sm status-btn" data-status="Cancelled" data-booking-id="${bookingId}" title="Cancel">
                        <i class="fas fa-times-circle"></i>
                    </button>`);
            }
            
            // Reattach click handlers
            attachStatusUpdateHandlers();
        }

        // Function to handle status updates
        function attachStatusUpdateHandlers() {
            $('.status-btn').off('click').on('click', function(e) {
                e.preventDefault();
                const button = $(this);
                const status = button.data('status');
                const bookingId = button.data('booking-id');
                
                // For cancel action, confirm first
                if (status === 'Cancelled' && !confirm('Are you sure you want to cancel this booking and its associated food orders?')) {
                    return;
                }
                
                // Show loading state
                button.prop('disabled', true);
                const originalHtml = button.html();
                button.html('<i class="fas fa-spinner fa-spin"></i>');
                
                // Make AJAX request
                $.ajax({
                    url: `update_booking_status.php?booking_id=${bookingId}&status=${status}`,
                    method: 'GET',
                    dataType: 'json',
                    success: function(response) {
                        if (response.success) {
                            toastr.success(response.message);
                            updateStatusDisplay(button.closest('tr'), response.data.status, response.data.food_cancelled);
                        } else {
                            toastr.error(response.message || 'Failed to update status');
                        }
                    },
                    error: function() {
                        toastr.error('Failed to update status. Please try again.');
                    },
                    complete: function() {
                        button.prop('disabled', false);
                        button.html(originalHtml);
                    }
                });
            });
        }

        // Initial attachment of click handlers
        attachStatusUpdateHandlers();

        // Export bookings
        function exportBookings() {
            // Implement export functionality
        }

        // Print bookings
        function printBookings() {
            window.print();
        }

        $(document).ready(function() {
            // Add this to your existing JavaScript
            function attachFoodStatusHandlers() {
                $('.food-status-btn').off('click').on('click', function(e) {
                    e.preventDefault();
                    const button = $(this);
                    const status = button.data('status');
                    const bookingId = button.data('booking-id');
                    
                    // Show loading state
                    button.prop('disabled', true);
                    const originalHtml = button.html();
                    button.html('<i class="fas fa-spinner fa-spin"></i>');
                    
                    // Make AJAX request
                    $.ajax({
                        url: `update_food_status.php?booking_id=${bookingId}&status=${status}`,
                        method: 'GET',
                        dataType: 'json',
                        success: function(response) {
                            if (response.success) {
                                toastr.success(response.message);
                                // Update the food order status display
                                const foodDetails = button.closest('.food-order-details');
                                if (status === 'Delivered') {
                                    // Hide all food status buttons after delivery
                                    foodDetails.find('.food-order-actions').html(
                                        '<span class="badge bg-success"><i class="fas fa-check-circle"></i> Delivered</span>'
                                    );
                                } else if (status === 'Approved') {
                                    // Show only the Delivered button
                                    button.remove();
                                }
                            } else {
                                toastr.error(response.message || 'Failed to update food order status');
                            }
                        },
                        error: function() {
                            toastr.error('Failed to update food order status. Please try again.');
                        },
                        complete: function() {
                            button.prop('disabled', false);
                            button.html(originalHtml);
                        }
                    });
                });
            }

            // Call this after document ready
            attachFoodStatusHandlers();
        });
    </script>
</body>
</html> 