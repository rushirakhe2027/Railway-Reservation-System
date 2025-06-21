<?php
session_start();

require_once "../includes/admin_auth.php";
require_once "../includes/db.php";

// Require admin login
requireAdminLogin();

// Get admin info
$admin = getAdminInfo();

// Check if user is logged in and is admin
if (!isset($_SESSION['is_admin']) || $_SESSION['is_admin'] !== true) {
    header("Location: ../login.php");
    exit();
}

require_once "../includes/config.php";

// Get dashboard statistics
$stats_sql = "SELECT 
    (SELECT COUNT(*) FROM users WHERE role = 'user') as total_users,
    (SELECT COUNT(*) FROM bookings) as total_bookings,
    (SELECT COUNT(*) FROM trains WHERE status = 'Active') as active_trains,
    (SELECT COALESCE(SUM(total_amount), 0) + 
            COALESCE((SELECT SUM(fo.quantity * fm.price) 
                     FROM food_orders fo 
                     JOIN food_menu fm ON fo.menu_item_id = fm.id), 0)
     FROM bookings) as total_revenue";

$stmt = $pdo->query($stats_sql);
$stats = $stmt->fetch(PDO::FETCH_ASSOC);

// Format the statistics
$total_users = $stats['total_users'];
$total_bookings = $stats['total_bookings'];
$active_trains = $stats['active_trains'];
$total_revenue = $stats['total_revenue'];

// Get recent bookings with food order details
$recent_bookings_sql = "SELECT 
    b.*, 
    u.name as user_name, 
    u.email,
    t.train_number, 
    t.train_name, 
    t.source_station, 
    t.destination_station,
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
     WHERE fo.booking_id = b.id) as delivery_stations,
    (SELECT status 
     FROM food_orders 
     WHERE booking_id = b.id 
     ORDER BY id DESC 
     LIMIT 1) as food_status,
    b.total_amount + COALESCE(
        (SELECT SUM(fo.quantity * fm.price)
         FROM food_orders fo 
         JOIN food_menu fm ON fo.menu_item_id = fm.id
         WHERE fo.booking_id = b.id), 0
    ) as grand_total
        FROM bookings b 
        JOIN users u ON b.user_id = u.id 
        JOIN trains t ON b.train_id = t.id 
LEFT JOIN passengers p ON p.booking_id = b.id
GROUP BY b.id
        ORDER BY b.created_at DESC 
LIMIT 10";

$stmt = $pdo->query($recent_bookings_sql);
$recent_bookings = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Get trains with seat availability
$sql = "SELECT 
        t.id,
        t.train_number,
        t.train_name,
        t.source_station,
        t.destination_station,
        t.departure_time,
        t.total_seats,
        (
            SELECT COUNT(*) 
            FROM bookings b 
            WHERE b.train_id = t.id 
            AND b.status != 'Cancelled'
            AND b.journey_date >= CURDATE()
            GROUP BY b.train_id
        ) as booked_seats,
        t.total_seats - COALESCE(
            (
                SELECT COUNT(*) 
                FROM bookings b 
                WHERE b.train_id = t.id 
                AND b.status != 'Cancelled'
                AND b.journey_date >= CURDATE()
                GROUP BY b.train_id
            ), 0
        ) as available_seats
    FROM trains t 
    WHERE t.status = 'Active'
    ORDER BY available_seats ASC";

$stmt = $pdo->query($sql);
$trains = $stmt->fetchAll();

// Add this code to handle the display of low seat alerts
foreach($trains as &$train) {
    // If booked_seats is null, set it to 0
    $train['booked_seats'] = $train['booked_seats'] ?? 0;
    
    // Calculate percentage
    $train['seat_percentage'] = ($train['available_seats'] / $train['total_seats']) * 100;
    
    // Set alert status
    if($train['available_seats'] <= 10) {
        $train['alert_status'] = 'danger';
        $train['alert_message'] = 'Critical: Low seats';
    } elseif($train['available_seats'] <= 20) {
        $train['alert_status'] = 'warning';
        $train['alert_message'] = 'Limited seats';
    } else {
        $train['alert_status'] = 'success';
        $train['alert_message'] = 'Good availability';
    }
}
unset($train); // Break the reference
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Dashboard - RailYatra</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/toastr.js/latest/toastr.min.css">
    <style>
        :root {
            --primary-color: #ff5722;
            --secondary-color: #ff7043;
            --accent-color: #ff8a65;
            --text-color: #263238;
            --light-text: #546e7a;
            --border-color: #eceff1;
            --gradient-start: #ff5722;
            --gradient-end: #ff8a65;
            --hover-color: #f4511e;
        }

        body {
            font-family: 'Google Sans', Arial, sans-serif;
            background-color: var(--border-color);
            color: var(--text-color);
        }

        .sidebar {
            min-height: 100vh;
            background: linear-gradient(135deg, var(--gradient-start), var(--gradient-end));
            color: white;
            box-shadow: 2px 0 10px rgba(0,0,0,0.1);
        }

        .nav-link {
            color: rgba(255,255,255,0.9);
            border-radius: 8px;
            margin: 4px 0;
            transition: all 0.3s ease;
        }

        .nav-link:hover {
            background: rgba(255,255,255,0.2);
            color: white;
            transform: translateX(5px);
        }

        .nav-link.active {
            background: white !important;
            color: var(--primary-color);
            box-shadow: 0 2px 5px rgba(0,0,0,0.2);
        }

        .card {
            border: none;
            border-radius: 12px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.08);
            transition: transform 0.3s ease;
            background: white;
        }

        .card:hover {
            transform: translateY(-5px);
        }

        .stat-card {
            background: white;
            border-left: 4px solid var(--primary-color);
        }

        .stat-icon {
            width: 48px;
            height: 48px;
            background: rgba(255, 87, 34, 0.1);
            border-radius: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
            color: var(--primary-color);
            font-size: 24px;
        }

        .table {
            background: white;
            border-radius: 12px;
            overflow: hidden;
        }

        .table thead {
            background: var(--primary-color);
            color: white;
        }

        .table th {
            font-weight: 500;
            border: none;
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

        .status-badge {
            padding: 6px 12px;
            border-radius: 20px;
            font-size: 0.85rem;
            font-weight: 500;
            display: inline-block;
            color: white;
        }

        .status-badge.status-approved { 
            background-color: #28a745;
        }
        .status-badge.status-pending { 
            background-color: #ffc107;
            color: #000;
        }
        .status-badge.status-cancelled { 
            background-color: #dc3545;
        }
        .status-badge.status-processing { 
            background-color: #3498db;
        }

        .admin-profile {
            padding: 20px;
            text-align: center;
            border-bottom: 1px solid rgba(255,255,255,0.1);
        }

        .admin-avatar {
            width: 80px;
            height: 80px;
            border-radius: 50%;
            background: white;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 15px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.2);
        }

        .admin-avatar i {
            color: var(--primary-color);
        }

        .card-header {
            background: var(--primary-color);
            color: white;
            border-bottom: none;
            padding: 15px 20px;
        }

        .btn-primary {
            background: var(--primary-color);
            border: none;
            padding: 8px 20px;
            border-radius: 8px;
        }

        .btn-primary:hover {
            background: var(--hover-color);
            transform: translateY(-2px);
        }

        .progress-bar {
            background-color: var(--primary-color);
        }

        .progress-bar.bg-success {
            background-color: #4caf50 !important;
        }

        .progress-bar.bg-warning {
            background-color: #ff9800 !important;
        }

        .progress-bar.bg-danger {
            background-color: #f44336 !important;
        }

        .btn-light {
            background: white;
            border: 1px solid #e0e0e0;
        }

        .btn-light:hover {
            background: #f5f5f5;
        }

        .dropdown-item:hover {
            background-color: rgba(255, 87, 34, 0.1);
            color: var(--primary-color);
        }

        .dropdown-item.text-danger:hover {
            background-color: rgba(244, 67, 54, 0.1);
            color: #f44336;
        }

        /* Update dropdown menu styles */
        .dropdown-menu {
            background: white;
            border: none;
            border-radius: 8px;
            box-shadow: 0 4px 20px rgba(0,0,0,0.15);
            padding: 8px 0;
            min-width: 180px;
            margin-top: 5px;
            z-index: 1000;
        }

        .dropdown-item {
            padding: 10px 16px;
            color: var(--text-color);
            font-size: 0.9rem;
            display: flex;
            align-items: center;
            transition: all 0.2s ease;
        }

        .dropdown-item i {
            width: 24px;
            text-align: center;
            margin-right: 10px;
            font-size: 1rem;
            color: var(--primary-color);
        }

        .dropdown-item.text-danger i {
            color: #dc3545;
        }

        .dropdown-item:hover {
            background-color: #f8f9fa;
            transform: translateX(3px);
        }

        .dropdown-item.text-danger:hover {
            background-color: #fff5f5;
        }

        .dropdown-divider {
            margin: 8px 0;
            border-color: #eee;
        }

        .btn-action {
            width: 32px;
            height: 32px;
            padding: 0;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            border-radius: 6px;
            background: white;
            border: 1px solid #dee2e6;
            transition: all 0.2s ease;
            margin: 0 2px;
        }

        .btn-action:hover {
            transform: translateY(-1px);
            box-shadow: 0 2px 4px rgba(0,0,0,0.05);
        }

        .btn-action i {
            font-size: 14px;
        }

        /* Status action buttons */
        .btn-action[title="Approve"] {
            color: #28a745;
        }
        .btn-action[title="Approve"]:hover,
        .btn-action[title="Approve"].active {
            background-color: #28a745;
            border-color: #28a745;
            color: white;
        }

        .btn-action[title="Mark as Pending"] {
            color: #ffc107;
        }
        .btn-action[title="Mark as Pending"]:hover,
        .btn-action[title="Mark as Pending"].active {
            background-color: #ffc107;
            border-color: #ffc107;
            color: #000;
        }

        .btn-action[title="Cancel"] {
            color: #dc3545;
        }
        .btn-action[title="Cancel"]:hover,
        .btn-action[title="Cancel"].active {
            background-color: #dc3545;
            border-color: #dc3545;
            color: white;
        }

        .d-flex.gap-2 {
            gap: 0.5rem !important;
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

        .food-order-actions {
            display: flex;
            gap: 0.5rem;
            margin-top: 0.5rem;
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

        .badge {
            padding: 0.35em 0.65em;
            font-size: 0.75em;
            font-weight: 500;
            border-radius: 0.25rem;
        }

        .bg-success {
            background-color: #28a745 !important;
            color: white;
        }

        .train-status-btn {
            padding: 8px 16px;
            border-radius: 6px;
            transition: all 0.3s ease;
        }

        .train-status-btn.active {
            color: white;
        }

        .train-status-btn[data-status="Active"].active {
            background-color: #28a745;
            border-color: #28a745;
        }

        .train-status-btn[data-status="Delayed"].active {
            background-color: #ffc107;
            border-color: #ffc107;
        }

        .train-status-btn[data-status="Cancelled"].active {
            background-color: #dc3545;
            border-color: #dc3545;
        }

        .table tr.table-danger {
            background-color: rgba(220, 53, 69, 0.1);
        }

        .table tr.table-warning {
            background-color: rgba(255, 193, 7, 0.1);
        }

        .train-details {
            padding: 15px;
            background: #f8f9fa;
            border-radius: 8px;
            margin-bottom: 20px;
        }

        .train-details i {
            width: 20px;
            color: var(--primary-color);
        }

        #delayDetails {
            padding: 15px;
            background: #fff8e1;
            border-radius: 8px;
            border: 1px solid #ffe082;
        }
    </style>
</head>
<body>
    <div class="container-fluid">
        <div class="row">
            <!-- Sidebar -->
            <div class="col-md-3 col-lg-2 px-0 sidebar">
                <div class="admin-profile">
                    <div class="admin-avatar">
                        <i class="fas fa-user-shield fa-2x text-primary"></i>
                    </div>
                    <h5 class="mb-1">Admin Panel</h5>
                    <small class="text-white-50">RailYatra Management</small>
                </div>
                <div class="p-3">
                    <ul class="nav flex-column">
                        <li class="nav-item">
                            <a class="nav-link active" href="dashboard.php">
                                <i class="fas fa-tachometer-alt me-2"></i>Dashboard
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" href="trains.php">
                                <i class="fas fa-train me-2"></i>Trains
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" href="bookings.php">
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
            <div class="col-md-9 col-lg-10 content">
                <div class="d-flex justify-content-between align-items-center py-4">
                    <h2 class="mb-0">Dashboard Overview</h2>
                    <div class="d-flex align-items-center">
                        <span class="text-muted"><?php echo date('l, d M Y'); ?></span>
                    </div>
                </div>

                <!-- Statistics Cards -->
                <div class="row mb-4">
                    <div class="col-md-3">
                        <div class="stats-card">
                            <div class="d-flex justify-content-between align-items-center">
                                    <div>
                                    <h3 class="mb-0"><?php echo $total_users; ?></h3>
                                    <p class="text-muted mb-0">Total Users</p>
                                    </div>
                                <i class="fas fa-users"></i>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="stats-card">
                            <div class="d-flex justify-content-between align-items-center">
                                    <div>
                                    <h3 class="mb-0"><?php echo $total_bookings; ?></h3>
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
                                    <h3 class="mb-0"><?php echo $active_trains; ?></h3>
                                    <p class="text-muted mb-0">Active Trains</p>
                                    </div>
                                <i class="fas fa-train"></i>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="stats-card revenue-card">
                            <div class="d-flex justify-content-between align-items-center">
                                    <div>
                                    <h3 class="mb-0">₹<?php echo number_format($total_revenue, 2); ?></h3>
                                    <p class="mb-0">Total Revenue</p>
                                    </div>
                                <i class="fas fa-rupee-sign"></i>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Recent Bookings Table -->
                <div class="card mt-4">
                    <div class="card-header d-flex justify-content-between align-items-center">
                        <h5 class="mb-0">Recent Bookings</h5>
                        <a href="bookings.php" class="btn btn-light btn-sm">View All</a>
                    </div>
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
                                    <?php foreach($recent_bookings as $booking): ?>
                                    <tr>
                                        <td>
                                            <?php echo htmlspecialchars($booking['pnr_number']); ?><br>
                                            <small class="text-muted"><?php echo date('d M Y', strtotime($booking['created_at'])); ?></small>
                                        </td>
                                        <td>
                                            <?php echo htmlspecialchars($booking['user_name']); ?><br>
                                            <small class="text-muted"><?php echo htmlspecialchars($booking['email']); ?></small>
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
                                                    <?php if ($booking['food_status'] === 'Delivered'): ?>
                                                        <span class="badge bg-success">
                                                            <i class="fas fa-check-circle"></i> Delivered
                                                        </span>
                                                    <?php elseif ($booking['food_status'] === 'Approved'): ?>
                                                        <button class="btn btn-outline-info btn-sm food-status-btn" 
                                                                data-booking-id="<?= $booking['id'] ?>" 
                                                                data-status="Delivered" 
                                                                title="Mark as Delivered">
                                                            <i class="fas fa-truck"></i> Delivered
                                                        </button>
                                                    <?php elseif ($booking['food_status'] !== 'Cancelled'): ?>
                                                        <button class="btn btn-outline-success btn-sm food-status-btn" 
                                                                data-booking-id="<?= $booking['id'] ?>" 
                                                                data-status="Approved" 
                                                                title="Approve Food Order">
                                                            <i class="fas fa-check"></i> Approve
                                                        </button>
                                                    <?php else: ?>
                                                        <span class="badge bg-danger">
                                                            <i class="fas fa-times-circle"></i> Cancelled
                                                        </span>
                                                    <?php endif; ?>
                                                </div>
                                            </div>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            ₹<?php echo number_format($booking['grand_total'], 2); ?>
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

                <!-- Train Seat Availability -->
                <div class="card mt-4">
                    <div class="card-header d-flex justify-content-between align-items-center">
                        <h5 class="mb-0">Train Seat Availability</h5>
                        <a href="trains.php" class="btn btn-light btn-sm">View All Trains</a>
                    </div>
                    <div class="card-body p-0">
                        <div class="table-responsive">
                            <table class="table table-hover">
                                <thead>
                                    <tr class="bg-primary text-white">
                                        <th>Train</th>
                                        <th>Route</th>
                                        <th>Available Seats</th>
                                        <th>Next Departure</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php if(empty($trains)): ?>
                                    <tr>
                                        <td colspan="4" class="text-center">
                                            <span class="text-muted">
                                                <i class="fas fa-info-circle me-2"></i>
                                                No trains available
                                            </span>
                                        </td>
                                    </tr>
                                    <?php else: ?>
                                    <?php foreach($trains as $train): ?>
                                    <tr>
                                        <td>
                                            <strong><?php echo htmlspecialchars($train['train_number']); ?></strong><br>
                                            <small class="text-muted"><?php echo htmlspecialchars($train['train_name']); ?></small>
                                        </td>
                                        <td>
                                            <?php echo htmlspecialchars($train['source_station']); ?> → 
                                            <?php echo htmlspecialchars($train['destination_station']); ?>
                                        </td>
                                        <td>
                                            <div class="d-flex align-items-center">
                                                <div class="progress me-2" style="width: 100px; height: 6px;">
                                                    <div class="progress-bar bg-<?php echo $train['alert_status']; ?>" 
                                                         role="progressbar" 
                                                         style="width: <?php echo $train['seat_percentage']; ?>%" 
                                                         aria-valuenow="<?php echo $train['seat_percentage']; ?>" 
                                                         aria-valuemin="0" 
                                                         aria-valuemax="100">
                                                    </div>
                                                </div>
                                                <span class="<?php echo $train['available_seats'] <= 10 ? 'text-danger' : ''; ?>">
                                                    <?php echo $train['available_seats']; ?>/<?php echo $train['total_seats']; ?>
                                                    <?php if($train['available_seats'] <= 10): ?>
                                                    <i class="fas fa-exclamation-circle text-danger ms-1" 
                                                       title="<?php echo $train['alert_message']; ?>"></i>
                                                    <?php endif; ?>
                                                </span>
                                            </div>
                                            <?php if($train['available_seats'] <= 10): ?>
                                            <small class="text-danger d-block mt-1">
                                                <i class="fas fa-exclamation-triangle"></i>
                                                <?php echo $train['alert_message']; ?>
                                            </small>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <?php 
                                            $departure = strtotime('today ' . $train['departure_time']);
                                            if ($departure < time()) {
                                                $departure = strtotime('tomorrow ' . $train['departure_time']);
                                            }
                                            echo date('d M Y h:i A', $departure); 
                                            ?>
                                        </td>
                                    </tr>
                                    <?php endforeach; ?>
                                    <?php endif; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Add this modal before the closing body tag -->
    <div class="modal fade" id="manageSeatModal" tabindex="-1" aria-labelledby="manageSeatModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header bg-primary text-white">
                    <h5 class="modal-title" id="manageSeatModalLabel">Manage Train Status</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <div class="train-details mb-4">
                        <h6 class="train-name mb-2"></h6>
                        <p class="train-route mb-1"></p>
                        <p class="train-schedule mb-2"></p>
                        <div class="seat-availability"></div>
                    </div>
                    <div class="train-status-update">
                        <h6 class="mb-3">Update Train Status</h6>
                        <div class="d-flex gap-3 mb-4">
                            <button class="btn btn-outline-success train-status-btn" data-status="Active">
                                <i class="fas fa-check-circle"></i> Active
                            </button>
                            <button class="btn btn-outline-warning train-status-btn" data-status="Delayed">
                                <i class="fas fa-clock"></i> Delayed
                            </button>
                            <button class="btn btn-outline-danger train-status-btn" data-status="Cancelled">
                                <i class="fas fa-times-circle"></i> Cancelled
                            </button>
                        </div>
                        <div id="delayDetails" class="collapse mb-3">
                            <div class="form-group">
                                <label for="delayDuration" class="form-label">Delay Duration (minutes)</label>
                                <input type="number" class="form-control" id="delayDuration" min="1" value="30">
                            </div>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                    <button type="button" class="btn btn-primary" id="updateTrainStatus">Update Status</button>
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

        // Function to handle food status updates
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
                            const foodOrderActions = button.closest('.food-order-actions');
                            
                            if (status === 'Delivered') {
                                // Hide all food status buttons after delivery
                                foodOrderActions.html(
                                    '<span class="badge bg-success"><i class="fas fa-check-circle"></i> Delivered</span>'
                                );
                            } else if (status === 'Approved') {
                                // Show only the Delivered button
                                foodOrderActions.html(`
                                    <button class="btn btn-outline-info btn-sm food-status-btn" 
                                            data-booking-id="${bookingId}" 
                                            data-status="Delivered" 
                                            title="Mark as Delivered">
                                        <i class="fas fa-truck"></i> Delivered
                                    </button>
                                `);
                                // Reattach handlers to the new button
                                attachFoodStatusHandlers();
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

        // Function to open manage seats modal
        function openManageSeatsModal(trainId, trainNumber, trainName, source, destination, departureTime, availableSeats, totalSeats) {
            const modal = $('#manageSeatModal');
            
            // Update modal content
            modal.find('.train-name').html(`<strong>${trainNumber}</strong> - ${trainName}`);
            modal.find('.train-route').html(`<i class="fas fa-route"></i> ${source} → ${destination}`);
            modal.find('.train-schedule').html(`<i class="fas fa-clock"></i> Departure: ${departureTime}`);
            modal.find('.seat-availability').html(`
                <div class="alert alert-info">
                    <i class="fas fa-chair"></i> Available Seats: ${availableSeats}/${totalSeats}
                </div>
            `);

            // Store train ID for later use
            modal.data('trainId', trainId);

            // Show the modal
            modal.modal('show');
        }

        // Handle train status button clicks
        $('.train-status-btn').on('click', function() {
            $('.train-status-btn').removeClass('active');
            $(this).addClass('active');
            
            // Show/hide delay details based on status
            if ($(this).data('status') === 'Delayed') {
                $('#delayDetails').collapse('show');
            } else {
                $('#delayDetails').collapse('hide');
            }
        });

        // Handle status update submission
        $('#updateTrainStatus').on('click', function() {
            const modal = $('#manageSeatModal');
            const trainId = modal.data('trainId');
            const activeButton = $('.train-status-btn.active');
            
            if (!activeButton.length) {
                toastr.error('Please select a status');
                return;
            }
            
            const status = activeButton.data('status');
            
            // Show loading state
            const button = $(this);
            button.prop('disabled', true);
            button.html('<i class="fas fa-spinner fa-spin"></i> Updating...');
            
            // Make AJAX request
            $.ajax({
                url: 'update_train_status.php',
                method: 'POST',
                data: {
                    train_id: trainId,
                    status: status,
                    delay_duration: status === 'Delayed' ? $('#delayDuration').val() : null
                },
                dataType: 'json',
                success: function(response) {
                    if (response.success) {
                        toastr.success(response.message);
                        modal.modal('hide');
                        
                        // Refresh the page after a short delay to update all information
                        setTimeout(function() {
                            location.reload();
                        }, 1500);
                    } else {
                        toastr.error(response.message || 'Failed to update train status');
                    }
                },
                error: function() {
                    toastr.error('Failed to update train status. Please try again.');
                },
                complete: function() {
                    button.prop('disabled', false);
                    button.html('Update Status');
                }
            });
        });

        // Update the manage seats button click handler
        $(document).ready(function() {
            // ... existing ready handlers ...

            // Replace the manage seats links with buttons that open the modal
            $('a[href^="manage_seats.php"]').each(function() {
                const link = $(this);
                const row = link.closest('tr');
                const trainId = link.attr('href').split('=')[1];
                const trainNumber = row.find('td:first strong').text();
                const trainName = row.find('td:first small').text();
                const source = row.find('td:eq(1)').text().split('→')[0].trim();
                const destination = row.find('td:eq(1)').text().split('→')[1].trim();
                const availableSeats = row.find('td:eq(2) span').text().split('/')[0].trim();
                const totalSeats = row.find('td:eq(2) span').text().split('/')[1].trim();
                const departureTime = row.find('td:eq(3)').text().trim();

                link.replaceWith(`
                    <button class="btn btn-primary btn-sm manage-seats-btn" 
                            data-train-id="${trainId}"
                            onclick="openManageSeatsModal('${trainId}', '${trainNumber}', '${trainName}', '${source}', '${destination}', '${departureTime}', '${availableSeats}', '${totalSeats}')">
                        <i class="fas fa-cog me-1"></i>
                        Manage Train
                    </button>
                `);
            });
        });
    </script>
</body>
</html> 