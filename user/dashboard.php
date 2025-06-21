<?php
session_start();
require_once "../includes/config.php";

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header("Location: ../login.php");
    exit;
}

// Get user details
$sql = "SELECT * FROM users WHERE id = ?";
$stmt = $pdo->prepare($sql);
$stmt->execute([$_SESSION['user_id']]);
$user = $stmt->fetch();

// Get user's active bookings count
$sql = "SELECT COUNT(*) FROM bookings WHERE user_id = ? AND status != 'Cancelled'";
$stmt = $pdo->prepare($sql);
$stmt->execute([$_SESSION['user_id']]);
$active_bookings = $stmt->fetchColumn();

// Get user's total bookings
$sql = "SELECT COUNT(*) FROM bookings WHERE user_id = ?";
$stmt = $pdo->prepare($sql);
$stmt->execute([$_SESSION['user_id']]);
$total_bookings = $stmt->fetchColumn();

// Get recent bookings with train details
$sql = "SELECT b.*, t.train_name, t.train_number, b.pnr_number as pnr, b.status,
        (SELECT station_name FROM train_stations WHERE train_id = t.id ORDER BY stop_number ASC LIMIT 1) as source_station,
        (SELECT station_name FROM train_stations WHERE train_id = t.id ORDER BY stop_number DESC LIMIT 1) as destination_station
        FROM bookings b 
        JOIN trains t ON b.train_id = t.id 
        WHERE b.user_id = ? 
        ORDER BY b.created_at DESC 
        LIMIT 5";
$stmt = $pdo->prepare($sql);
$stmt->execute([$_SESSION['user_id']]);
$recent_bookings = $stmt->fetchAll();

// Get recent food orders
$sql = "SELECT fo.*, fo.id as order_id, fm.item_name, fm.price, fv.name as vendor_name 
        FROM food_orders fo 
        JOIN food_menu fm ON fo.menu_item_id = fm.id 
        JOIN food_vendors fv ON fm.vendor_id = fv.id 
        JOIN bookings b ON fo.booking_id = b.id 
        WHERE b.user_id = ? 
        ORDER BY fo.created_at DESC 
        LIMIT 5";
$stmt = $pdo->prepare($sql);
$stmt->execute([$_SESSION['user_id']]);
$recent_food_orders = $stmt->fetchAll();

// Get recent trains (keep this for the carousel)
$sql = "SELECT DISTINCT t.id, t.train_name, t.train_number, t.status,
        (SELECT station_name FROM train_stations WHERE train_id = t.id ORDER BY stop_number ASC LIMIT 1) as source_station,
        (SELECT station_name FROM train_stations WHERE train_id = t.id ORDER BY stop_number DESC LIMIT 1) as destination_station,
        (SELECT TIME_FORMAT(departure_time, '%h:%i %p') FROM train_stations WHERE train_id = t.id ORDER BY stop_number ASC LIMIT 1) as first_departure,
        (SELECT TIME_FORMAT(arrival_time, '%h:%i %p') FROM train_stations WHERE train_id = t.id ORDER BY stop_number DESC LIMIT 1) as last_arrival,
        (SELECT COUNT(*) FROM bookings WHERE train_id = t.id AND status != 'Cancelled') as booking_count,
        (SELECT base_fare FROM station_pricing WHERE train_id = t.id AND source_station = (
            SELECT station_name FROM train_stations WHERE train_id = t.id ORDER BY stop_number ASC LIMIT 1
        ) LIMIT 1) as fare,
        GROUP_CONCAT(DISTINCT ts.station_name ORDER BY ts.stop_number ASC) as station_list,
        GROUP_CONCAT(DISTINCT TIME_FORMAT(ts.departure_time, '%h:%i %p') ORDER BY ts.stop_number ASC) as departure_times
        FROM trains t 
        LEFT JOIN train_stations ts ON t.id = ts.train_id
        WHERE t.status = 'Active'
        GROUP BY t.id, t.train_name, t.train_number, t.status
        ORDER BY t.created_at DESC 
        LIMIT 5";
$stmt = $pdo->prepare($sql);
$stmt->execute();
$recent_trains = $stmt->fetchAll();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard - RailYatra</title>
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
            padding: 10px 15px;
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
            overflow: hidden;
            background: white;
        }

        .card:hover {
            transform: translateY(-5px);
        }

        .card-header {
            background: var(--primary-color);
            color: white;
            border-bottom: none;
            padding: 15px 20px;
        }

        .table {
            margin-bottom: 0;
        }

        .table thead {
            background: var(--primary-color);
            color: white;
        }

        .table th {
            font-weight: 500;
            border: none;
            padding: 15px;
        }

        .table td {
            padding: 15px;
            vertical-align: middle;
        }

        .status-badge {
            padding: 6px 12px;
            border-radius: 20px;
            font-size: 0.85rem;
            font-weight: 500;
            display: inline-block;
        }

        .status-confirmed { background-color: #27ae60; color: white; }
        .status-pending { background-color: #f39c12; color: white; }
        .status-cancelled { background-color: #c0392b; color: white; }
        .status-processing { background-color: #3498db; color: white; }

        .user-profile {
            padding: 20px;
            text-align: center;
            border-bottom: 1px solid rgba(255,255,255,0.1);
        }

        .user-avatar {
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

        .user-avatar i {
            color: var(--primary-color);
        }

        .quick-actions {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 1rem;
            margin-bottom: 2rem;
        }

        .quick-action-card {
            background: white;
            border-radius: 12px;
            padding: 1.5rem;
            text-align: center;
            transition: all 0.3s ease;
            cursor: pointer;
            border: 1px solid var(--border-color);
        }

        .quick-action-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 5px 15px rgba(0,0,0,0.1);
            border-color: var(--primary-color);
            background: linear-gradient(135deg, rgba(231, 76, 60, 0.05), rgba(243, 156, 18, 0.05));
        }

        .quick-action-icon {
            width: 50px;
            height: 50px;
            background: rgba(231, 76, 60, 0.1);
            border-radius: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 1rem;
            color: var(--primary-color);
            font-size: 1.5rem;
            transition: all 0.3s ease;
        }

        .quick-action-card:hover .quick-action-icon {
            transform: scale(1.1);
            background: var(--primary-color);
            color: white;
        }

        .btn-primary {
            background: var(--primary-color);
            border: none;
            padding: 8px 20px;
            border-radius: 8px;
            transition: all 0.3s ease;
        }

        .btn-primary:hover {
            background: var(--hover-color);
            transform: translateY(-2px);
        }

        .btn-light {
            background: white;
            border: 1px solid #e0e0e0;
            transition: all 0.3s ease;
        }

        .btn-light:hover {
            background: #f5f5f5;
            transform: translateY(-2px);
        }

        .progress {
            height: 6px;
            border-radius: 3px;
            margin-top: 8px;
            overflow: hidden;
        }

        .progress-bar {
            background: linear-gradient(to right, var(--gradient-start), var(--gradient-end));
        }

        .journey-card {
            position: relative;
            padding: 1.5rem;
        }

        .journey-card::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            width: 4px;
            height: 100%;
            background: var(--primary-color);
            border-radius: 4px;
        }

        .station-dot {
            width: 12px;
            height: 12px;
            background: var(--primary-color);
            border-radius: 50%;
            margin-right: 10px;
        }

        .station-line {
            width: 2px;
            height: 30px;
            background: var(--border-color);
            margin: 5px 0 5px 5px;
        }

        .dropdown-item:hover {
            background-color: rgba(231, 76, 60, 0.1);
            color: var(--primary-color);
        }

        .dropdown-item.text-danger:hover {
            background-color: rgba(192, 57, 43, 0.1);
            color: var(--hover-color);
        }

        .badge {
            padding: 6px 10px;
            border-radius: 6px;
            font-weight: 500;
        }

        .badge.bg-danger {
            background: var(--primary-color) !important;
        }

        .notification-dot {
            position: absolute;
            top: -2px;
            right: -2px;
            width: 8px;
            height: 8px;
            background: var(--primary-color);
            border-radius: 50%;
            border: 2px solid white;
        }

        .dashboard-card {
            background: white;
            border-radius: 12px;
            overflow: hidden;
            box-shadow: 0 2px 10px rgba(0,0,0,0.08);
            margin-bottom: 2rem;
        }

        .welcome-section {
            background: linear-gradient(135deg, var(--primary-color), var(--secondary-color));
            color: white;
            padding: 2rem;
            border-radius: 12px;
            margin-bottom: 2rem;
        }

        .stats-card {
            background: white;
            border-radius: 12px;
            padding: 1.5rem;
            margin-bottom: 1rem;
            transition: transform 0.3s ease;
        }

        .stats-card:hover {
            transform: translateY(-5px);
        }

        .stats-icon {
            width: 48px;
            height: 48px;
            background: rgba(231, 76, 60, 0.1);
            border-radius: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
            color: var(--primary-color);
            font-size: 1.5rem;
            margin-bottom: 1rem;
        }

        .carousel-inner {
            border-radius: 12px;
            overflow: hidden;
        }

        .train-slide {
            background: linear-gradient(135deg, var(--primary-color), var(--secondary-color));
            color: white;
            padding: 2rem;
        }

        .train-info {
            background: rgba(255, 255, 255, 0.1);
            border-radius: 8px;
            padding: 1rem;
            margin-top: 1rem;
        }

        .station-badge {
            background: rgba(255, 255, 255, 0.2);
            border-radius: 20px;
            padding: 0.25rem 0.75rem;
            font-size: 0.85rem;
            margin: 0.25rem;
            display: inline-block;
        }

        .action-card {
            background: white;
            border-radius: 12px;
            padding: 1.5rem;
            text-align: center;
            transition: transform 0.3s ease;
            text-decoration: none;
            color: var(--text-color);
        }

        .action-card:hover {
            transform: translateY(-5px);
            color: var(--primary-color);
        }

        .action-icon {
            font-size: 2rem;
            color: var(--primary-color);
            margin-bottom: 1rem;
        }

        .profile-section {
            background: linear-gradient(135deg, var(--primary-color), var(--secondary-color));
            color: white;
            padding: 2rem;
            border-radius: 12px;
            margin-bottom: 2rem;
        }

        .stats-card {
            background: white;
            border-radius: 12px;
            padding: 1.5rem;
            box-shadow: 0 2px 10px rgba(0,0,0,0.08);
            transition: transform 0.3s ease;
        }

        .stats-card:hover {
            transform: translateY(-5px);
        }

        .stats-icon {
            width: 48px;
            height: 48px;
            background: rgba(231, 76, 60, 0.1);
            color: var(--primary-color);
            border-radius: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.5rem;
            margin-bottom: 1rem;
        }

        .quick-action {
            background: white;
            border-radius: 12px;
            padding: 1.5rem;
            text-decoration: none;
            color: var(--text-color);
            transition: all 0.3s ease;
            display: flex;
            align-items: center;
            margin-bottom: 1rem;
        }

        .quick-action:hover {
            transform: translateX(5px);
            color: var(--primary-color);
        }

        .quick-action i {
            margin-right: 1rem;
            color: var(--primary-color);
            font-size: 1.5rem;
        }

        .train-carousel {
            background: white;
            border-radius: 12px;
            padding: 1.5rem;
            margin-top: 2rem;
        }

        .train-card {
            background: #f8f9fa;
            border-radius: 8px;
            padding: 1rem;
            margin-bottom: 1rem;
            border: 1px solid var(--border-color);
            transition: all 0.3s ease;
        }

        .train-card:hover {
            border-color: var(--primary-color);
            transform: translateY(-2px);
        }

        .route-info {
            color: var(--light-text);
            font-size: 0.9rem;
        }

        .booking-count {
            background: rgba(231, 76, 60, 0.1);
            color: var(--primary-color);
            padding: 4px 12px;
            border-radius: 20px;
            font-size: 0.85rem;
        }
    </style>
</head>
<body>
    <div class="container-fluid">
        <div class="row">
            <!-- Sidebar -->
            <div class="col-md-3 col-lg-2 px-0 sidebar">
                <div class="user-profile">
                    <div class="user-avatar">
                        <i class="fas fa-user fa-2x"></i>
                    </div>
                    <h5 class="mb-1"><?php echo htmlspecialchars($user['name']); ?></h5>
                    <small class="text-white-50"><?php echo htmlspecialchars($user['email']); ?></small>
                </div>
                <div class="p-3">
                    <ul class="nav flex-column">
                        <li class="nav-item">
                            <a class="nav-link active" href="dashboard.php">
                                <i class="fas fa-home me-2"></i>Dashboard
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" href="search_trains.php">
                                <i class="fas fa-search me-2"></i>Search Trains
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" href="my_bookings.php">
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
                                <i class="fas fa-user-cog me-2"></i>Profile
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
            <div class="col-md-9 col-lg-10 content p-4">
                <!-- Profile Section -->
                <div class="profile-section">
                    <div class="row align-items-center">
                        <div class="col-md-8">
                            <h2 class="mb-1">Welcome back, <?php echo htmlspecialchars($user['name']); ?>!</h2>
                            <p class="mb-0">Here's what's happening with your travel plans.</p>
                        </div>
                        <div class="col-md-4 text-end">
                            <div class="date-display">
                                <?php echo date('l, d M Y'); ?>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Quick Actions -->
                <div class="quick-actions mb-4">
                    <div class="quick-action-card" onclick="window.location.href='search_trains.php'">
                        <div class="quick-action-icon">
                            <i class="fas fa-search"></i>
                        </div>
                        <h5>Search Trains</h5>
                        <p class="text-muted mb-0">Find and book your next journey</p>
                    </div>
                    <div class="quick-action-card" onclick="window.location.href='my_bookings.php'">
                        <div class="quick-action-icon">
                            <i class="fas fa-ticket-alt"></i>
                        </div>
                        <h5>My Bookings</h5>
                        <p class="text-muted mb-0">View and manage your bookings</p>
                    </div>
                    <div class="quick-action-card" onclick="window.location.href='food_orders.php'">
                        <div class="quick-action-icon">
                            <i class="fas fa-utensils"></i>
                        </div>
                        <h5>Food Orders</h5>
                        <p class="text-muted mb-0">Order food for your journey</p>
                    </div>
                </div>

                <!-- Recent Bookings -->
                <div class="card mb-4">
                    <div class="card-header d-flex justify-content-between align-items-center">
                        <h5 class="mb-0">Recent Bookings</h5>
                        <a href="my_bookings.php" class="btn btn-light btn-sm">View All</a>
                    </div>
                    <div class="card-body p-0">
                        <div class="table-responsive">
                            <table class="table table-hover">
                                <thead>
                                    <tr>
                                        <th>PNR</th>
                                        <th>Train</th>
                                        <th>Journey</th>
                                        <th>Date</th>
                                        <th>Status</th>
                                        <th>Action</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php if (empty($recent_bookings)): ?>
                                    <tr>
                                        <td colspan="6" class="text-center py-4">No bookings found</td>
                                    </tr>
                                    <?php else: ?>
                                    <?php foreach($recent_bookings as $booking): ?>
                                    <tr>
                                        <td>
                                            <strong><?php echo htmlspecialchars($booking['pnr']); ?></strong>
                                        </td>
                                        <td>
                                            <div>
                                                <strong><?php echo htmlspecialchars($booking['train_name']); ?></strong>
                                                <br>
                                                <small class="text-muted"><?php echo htmlspecialchars($booking['train_number']); ?></small>
                                            </div>
                                        </td>
                                        <td>
                                            <div class="d-flex align-items-center">
                                                <div>
                                                    <div class="station-dot"></div>
                                                    <div class="station-line"></div>
                                                    <div class="station-dot"></div>
                                                </div>
                                                <div>
                                                    <div><?php echo htmlspecialchars($booking['source_station']); ?></div>
                                                    <small class="text-muted">to</small>
                                                    <div><?php echo htmlspecialchars($booking['destination_station']); ?></div>
                                                </div>
                                            </div>
                                        </td>
                                        <td>
                                            <div>
                                                <?php echo date('d M Y', strtotime($booking['journey_date'])); ?>
                                                <br>
                                                <small class="text-muted"><?php echo date('h:i A', strtotime($booking['journey_date'])); ?></small>
                                            </div>
                                        </td>
                                        <td>
                                            <?php if (isset($booking['status']) && !empty($booking['status'])): ?>
                                            <span class="status-badge status-<?php echo strtolower($booking['status']); ?>">
                                                <?php echo htmlspecialchars($booking['status']); ?>
                                            </span>
                                            <?php else: ?>
                                            <span class="status-badge status-pending">Pending</span>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <div class="dropdown">
                                                <button class="btn btn-light btn-sm" type="button" data-bs-toggle="dropdown">
                                                    <i class="fas fa-ellipsis-v"></i>
                                                </button>
                                                <div class="dropdown-menu dropdown-menu-end">
                                                    <a href="booking_confirmation.php?pnr=<?php echo urlencode($booking['pnr']); ?>" class="btn btn-primary btn-sm">
                                                        <i class="fas fa-eye"></i> View
                                                    </a>
                                                    <a href="download_ticket.php?pnr=<?php echo urlencode($booking['pnr']); ?>" class="btn btn-success btn-sm">
                                                        <i class="fas fa-download"></i> Download
                                                    </a>
                                                    <a class="dropdown-item" href="print_ticket.php?id=<?php echo $booking['id']; ?>">
                                                        <i class="fas fa-print me-2"></i>Print Ticket
                                                    </a>
                                                    <a class="dropdown-item" href="order_food.php?booking_id=<?php echo $booking['id']; ?>">
                                                        <i class="fas fa-utensils me-2"></i>Order Food
                                                    </a>
                                                    <?php if ($booking['status'] != 'Cancelled'): ?>
                                                    <div class="dropdown-divider"></div>
                                                    <a class="dropdown-item text-danger" href="cancel_booking.php?id=<?php echo $booking['id']; ?>">
                                                        <i class="fas fa-times me-2"></i>Cancel Booking
                                                    </a>
                                                    <?php endif; ?>
                                                </div>
                                            </div>
                                        </td>
                                    </tr>
                                    <?php endforeach; ?>
                                    <?php endif; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>

                <!-- Recent Food Orders -->
                <div class="card">
                    <div class="card-header d-flex justify-content-between align-items-center">
                        <h5 class="mb-0">Recent Food Orders</h5>
                        <a href="food_orders.php" class="btn btn-light btn-sm">View All</a>
                    </div>
                    <div class="card-body p-0">
                        <div class="table-responsive">
                            <table class="table table-hover">
                                <thead>
                                    <tr>
                                        <th>Order ID</th>
                                        <th>Items</th>
                                        <th>Vendor</th>
                                        <th>Amount</th>
                                        <th>Status</th>
                                        <th>Action</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php if (empty($recent_food_orders)): ?>
                                    <tr>
                                        <td colspan="6" class="text-center py-4">No food orders found</td>
                                    </tr>
                                    <?php else: ?>
                                    <?php foreach($recent_food_orders as $order): ?>
                                    <tr>
                                        <td>
                                            <strong>#<?php echo htmlspecialchars($order['order_id']); ?></strong>
                                        </td>
                                        <td><?php echo htmlspecialchars($order['item_name']); ?></td>
                                        <td><?php echo htmlspecialchars($order['vendor_name']); ?></td>
                                        <td>₹<?php echo number_format($order['price'], 2); ?></td>
                                        <td>
                                            <span class="status-badge status-<?php echo strtolower($order['status']); ?>">
                                                <?php echo htmlspecialchars($order['status']); ?>
                                            </span>
                                        </td>
                                        <td>
                                            <div class="dropdown">
                                                <button class="btn btn-light btn-sm" type="button" data-bs-toggle="dropdown">
                                                    <i class="fas fa-ellipsis-v"></i>
                                                </button>
                                                <div class="dropdown-menu dropdown-menu-end">
                                                    <a class="dropdown-item" href="view_food_order.php?id=<?php echo $order['id']; ?>">
                                                        <i class="fas fa-eye me-2"></i>View Details
                                                    </a>
                                                    <a class="dropdown-item" href="download_invoice.php?id=<?php echo $order['id']; ?>">
                                                        <i class="fas fa-receipt me-2"></i>Download Invoice
                                                    </a>
                                                    <?php if ($order['status'] != 'Cancelled' && $order['status'] != 'Delivered'): ?>
                                                    <div class="dropdown-divider"></div>
                                                    <a class="dropdown-item text-danger" href="cancel_food_order.php?id=<?php echo $order['id']; ?>">
                                                        <i class="fas fa-times me-2"></i>Cancel Order
                                                    </a>
                                                    <?php endif; ?>
                                                </div>
                                            </div>
                                        </td>
                                    </tr>
                                    <?php endforeach; ?>
                                    <?php endif; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>

                <!-- Recent Trains Carousel -->
                <div class="dashboard-card mt-4">
                    <div id="recentTrainsCarousel" class="carousel slide" data-bs-ride="carousel">
                        <div class="carousel-inner">
                            <?php foreach ($recent_trains as $index => $train): 
                                $station_list = explode(',', $train['station_list'] ?? '');
                                $departure_times = explode(',', $train['departure_times'] ?? '');
                            ?>
                            <div class="carousel-item <?php echo $index === 0 ? 'active' : ''; ?>">
                                <div class="train-slide">
                                    <div class="d-flex justify-content-between align-items-start">
                                        <div>
                                            <h4><?php echo htmlspecialchars($train['train_name']); ?></h4>
                                            <p class="mb-2"><?php echo htmlspecialchars($train['train_number']); ?></p>
                                        </div>
                                        <div class="text-end">
                                            <h5>₹<?php echo number_format((float)$train['fare'], 2); ?></h5>
                                            <small><?php echo $train['booking_count']; ?> booking<?php echo $train['booking_count'] != 1 ? 's' : ''; ?></small>
                                        </div>
                                    </div>
                                    <div class="train-info">
                                        <div class="d-flex justify-content-between mb-2">
                                            <div>
                                                <i class="fas fa-clock me-2"></i>
                                                <?php echo $train['first_departure']; ?>
                                            </div>
                                            <div>
                                                <i class="fas fa-arrow-right mx-2"></i>
                                            </div>
                                            <div>
                                                <i class="fas fa-clock me-2"></i>
                                                <?php echo $train['last_arrival']; ?>
                                            </div>
                                        </div>
                                        <div class="stations-list">
                                            <?php if (!empty($station_list)): ?>
                                                <?php foreach ($station_list as $index => $station): ?>
                                                <span class="station-badge" title="Departure: <?php echo $departure_times[$index] ?? 'N/A'; ?>">
                                                    <?php echo htmlspecialchars(trim($station)); ?>
                                                </span>
                                                <?php endforeach; ?>
                                            <?php else: ?>
                                                <span class="station-badge">No stations available</span>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                    <div class="text-end mt-3">
                                        <a href="book_train.php?train_id=<?php echo $train['id']; ?>" 
                                           class="btn btn-light">Book Now</a>
                                    </div>
                                </div>
                            </div>
                            <?php endforeach; ?>
                        </div>
                        <?php if (count($recent_trains) > 1): ?>
                        <button class="carousel-control-prev" type="button" data-bs-target="#recentTrainsCarousel" data-bs-slide="prev">
                            <span class="carousel-control-prev-icon" aria-hidden="true"></span>
                            <span class="visually-hidden">Previous</span>
                        </button>
                        <button class="carousel-control-next" type="button" data-bs-target="#recentTrainsCarousel" data-bs-slide="next">
                            <span class="carousel-control-next-icon" aria-hidden="true"></span>
                            <span class="visually-hidden">Next</span>
                        </button>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/toastr.js/latest/toastr.min.js"></script>
    <script>
        // Toast notifications
        <?php if(isset($_SESSION['toast'])): ?>
        toastr.options = {
            closeButton: true,
            progressBar: true,
            positionClass: "toast-top-right",
            timeOut: 3000
        };
        toastr.<?php echo $_SESSION['toast']['type']; ?>('<?php echo $_SESSION['toast']['message']; ?>');
        <?php unset($_SESSION['toast']); endif; ?>
    </script>
</body>
</html> 