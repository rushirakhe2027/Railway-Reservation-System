<?php
session_start();
require_once "../../includes/config.php";
require_once "../../includes/admin_auth.php";

// Check if admin is logged in
requireAdminLogin();

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
    echo '<div class="text-center text-muted">User not found</div>';
    exit;
}

// Get user's recent bookings
$sql = "SELECT b.*, t.train_number, t.train_name, 
        t.source_station, t.destination_station
        FROM bookings b
        INNER JOIN trains t ON b.train_id = t.id
        WHERE b.user_id = ?
        ORDER BY b.booking_date DESC
        LIMIT 5";
$stmt = $pdo->prepare($sql);
$stmt->execute([$user_id]);
$recent_bookings = $stmt->fetchAll();
?>

<div class="user-profile text-center mb-4">
    <div class="user-avatar mx-auto mb-3">
        <?php echo strtoupper(substr($user['name'], 0, 1)); ?>
    </div>
    <h4><?php echo htmlspecialchars($user['name']); ?></h4>
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

<h5 class="mb-3">Recent Bookings</h5>
<?php if(empty($recent_bookings)): ?>
    <p class="text-muted text-center">No bookings found</p>
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
<?php endif; ?> 