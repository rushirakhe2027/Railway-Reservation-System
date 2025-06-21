<?php
session_start();
require_once "../includes/config.php";
require_once "../includes/admin_auth.php";

// Check if admin is logged in
requireAdminLogin();

// Get user ID from URL
$user_id = isset($_GET['id']) ? $_GET['id'] : 0;

// Get user details with statistics
$sql = "SELECT u.*, 
        (SELECT COUNT(*) FROM bookings b WHERE b.user_id = u.id) as booking_count,
        (SELECT SUM(total_amount) FROM bookings b WHERE b.user_id = u.id) as total_spent,
        (SELECT COUNT(*) FROM food_orders fo 
         INNER JOIN bookings b ON fo.booking_id = b.id 
         WHERE b.user_id = u.id) as food_orders_count
        FROM users u 
        WHERE u.id = ?";
$stmt = $pdo->prepare($sql);
$stmt->execute([$user_id]);
$user = $stmt->fetch();

if (!$user) {
    header("Location: users.php");
    exit;
}

// Get user's recent bookings
$sql = "SELECT b.*, t.train_number, t.train_name, 
        b.source_station,
        b.destination_station
        FROM bookings b
        INNER JOIN trains t ON b.train_id = t.id
        WHERE b.user_id = ?
        ORDER BY b.booking_date DESC
        LIMIT 5";
$stmt = $pdo->prepare($sql);
$stmt->execute([$user_id]);
$recent_bookings = $stmt->fetchAll();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>User Details - RailYatra Admin</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        :root {
            --primary-color: #e74c3c;
            --secondary-color: #e67e22;
            --accent-color: #f39c12;
            --text-color: #2c3e50;
            --light-text: #7f8c8d;
            --border-color: #ecf0f1;
        }

        body {
            background-color: var(--border-color);
            color: var(--text-color);
        }

        .card {
            border: none;
            border-radius: 12px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.08);
        }

        .user-avatar {
            width: 100px;
            height: 100px;
            border-radius: 50%;
            background: var(--primary-color);
            color: white;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 2.5rem;
            margin: 0 auto 1rem;
        }

        .stat-card {
            background: white;
            border-radius: 12px;
            padding: 1.5rem;
            text-align: center;
            margin-bottom: 1rem;
        }

        .stat-card i {
            font-size: 2rem;
            color: var(--primary-color);
            margin-bottom: 1rem;
        }

        .booking-item {
            border-left: 4px solid var(--primary-color);
            padding: 1rem;
            margin-bottom: 1rem;
            background: white;
            border-radius: 0 12px 12px 0;
        }

        .status-badge {
            padding: 6px 12px;
            border-radius: 20px;
            font-size: 0.85rem;
            font-weight: 500;
        }

        .status-Confirmed { background-color: #27ae60; color: white; }
        .status-Pending { background-color: #f39c12; color: white; }
        .status-Cancelled { background-color: #e74c3c; color: white; }
    </style>
</head>
<body>
    <div class="container-fluid">
        <div class="row">
            <!-- Include your sidebar here -->
            <?php include 'includes/sidebar.php'; ?>

            <!-- Main Content -->
            <div class="col-md-9 col-lg-10 p-4">
                <div class="d-flex justify-content-between align-items-center mb-4">
                    <h2>User Details</h2>
                    <a href="users.php" class="btn btn-outline-secondary">
                        <i class="fas fa-arrow-left me-2"></i>Back to Users
                    </a>
                </div>

                <!-- User Profile Card -->
                <div class="card mb-4">
                    <div class="card-body text-center">
                        <div class="user-avatar">
                            <?php echo strtoupper(substr($user['name'], 0, 1)); ?>
                        </div>
                        <h3><?php echo htmlspecialchars($user['name']); ?></h3>
                        <p class="text-muted mb-3">Member since <?php echo date('d M Y', strtotime($user['created_at'])); ?></p>
                        <div class="d-flex justify-content-center gap-3">
                            <a href="mailto:<?php echo $user['email']; ?>" class="btn btn-light">
                                <i class="fas fa-envelope me-2"></i><?php echo htmlspecialchars($user['email']); ?>
                            </a>
                            <?php if($user['phone']): ?>
                            <a href="tel:<?php echo $user['phone']; ?>" class="btn btn-light">
                                <i class="fas fa-phone me-2"></i><?php echo htmlspecialchars($user['phone']); ?>
                            </a>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>

                <!-- Statistics -->
                <div class="row mb-4">
                    <div class="col-md-4">
                        <div class="stat-card">
                            <i class="fas fa-ticket-alt"></i>
                            <h4><?php echo $user['booking_count']; ?></h4>
                            <p class="text-muted mb-0">Total Bookings</p>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="stat-card">
                            <i class="fas fa-rupee-sign"></i>
                            <h4>₹<?php echo number_format($user['total_spent'] ?? 0, 2); ?></h4>
                            <p class="text-muted mb-0">Total Spent</p>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="stat-card">
                            <i class="fas fa-utensils"></i>
                            <h4><?php echo $user['food_orders_count']; ?></h4>
                            <p class="text-muted mb-0">Food Orders</p>
                        </div>
                    </div>
                </div>

                <!-- Recent Bookings -->
                <div class="card">
                    <div class="card-header bg-white">
                        <h5 class="mb-0">Recent Bookings</h5>
                    </div>
                    <div class="card-body">
                        <?php if(empty($recent_bookings)): ?>
                            <p class="text-muted text-center mb-0">No bookings found</p>
                        <?php else: ?>
                            <?php foreach($recent_bookings as $booking): ?>
                                <div class="booking-item">
                                    <div class="d-flex justify-content-between align-items-start">
                                        <div>
                                            <h6 class="mb-1">
                                                <?php echo htmlspecialchars($booking['train_number']); ?> - 
                                                <?php echo htmlspecialchars($booking['train_name']); ?>
                                            </h6>
                                            <p class="mb-1">
                                                <?php echo htmlspecialchars($booking['source_station']); ?> → 
                                                <?php echo htmlspecialchars($booking['destination_station']); ?>
                                            </p>
                                            <small class="text-muted">
                                                Booked on <?php echo date('d M Y, h:i A', strtotime($booking['booking_date'])); ?>
                                            </small>
                                        </div>
                                        <div>
                                            <span class="status-badge status-<?php echo $booking['status']; ?>">
                                                <?php echo $booking['status']; ?>
                                            </span>
                                        </div>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                            <div class="text-center mt-3">
                                <a href="bookings.php?user_id=<?php echo $user_id; ?>" class="btn btn-outline-primary">
                                    View All Bookings
                                </a>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html> 