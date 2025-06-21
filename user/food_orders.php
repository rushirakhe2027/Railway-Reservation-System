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

// Get food orders with booking, menu item and vendor details
$sql = "SELECT fo.*, 
        b.pnr_number as pnr,
        b.journey_date,
        t.train_name,
        t.train_number,
        fm.item_name,
        fm.price,
        fv.name as vendor_name,
        fv.contact_number,
        (SELECT station_name FROM train_stations WHERE train_id = t.id ORDER BY stop_number ASC LIMIT 1) as source_station,
        (SELECT station_name FROM train_stations WHERE train_id = t.id ORDER BY stop_number DESC LIMIT 1) as destination_station
        FROM food_orders fo 
        JOIN bookings b ON fo.booking_id = b.id
        JOIN trains t ON b.train_id = t.id
        JOIN food_menu fm ON fo.menu_item_id = fm.id 
        JOIN food_vendors fv ON fm.vendor_id = fv.id 
        WHERE b.user_id = ? 
        ORDER BY fo.created_at DESC";

$stmt = $pdo->prepare($sql);
$stmt->execute([$_SESSION['user_id']]);
$food_orders = $stmt->fetchAll();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Food Orders - RailYatra</title>
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
            font-family: 'Google Sans', Arial, sans-serif;
            background-color: var(--border-color);
            color: var(--text-color);
        }

        .card {
            border: none;
            border-radius: 12px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.08);
        }

        .card-header {
            background: var(--primary-color);
            color: white;
            border-radius: 12px 12px 0 0 !important;
            padding: 15px 20px;
        }

        .table th {
            font-weight: 500;
            color: var(--text-color);
        }

        .status-badge {
            padding: 6px 12px;
            border-radius: 20px;
            font-size: 0.85rem;
            font-weight: 500;
        }

        .status-pending { background-color: #f39c12; color: white; }
        .status-confirmed { background-color: #27ae60; color: white; }
        .status-delivered { background-color: #2ecc71; color: white; }
        .status-cancelled { background-color: #e74c3c; color: white; }

        .btn-primary {
            background: var(--primary-color);
            border: none;
        }

        .btn-primary:hover {
            background: #c0392b;
        }

        .order-details {
            background: #f8f9fa;
            border-radius: 8px;
            padding: 15px;
            margin-bottom: 10px;
        }

        .vendor-info {
            border-left: 3px solid var(--primary-color);
            padding-left: 15px;
        }
    </style>
</head>
<body>
    <div class="container py-4">
        <div class="card">
            <div class="card-header d-flex justify-content-between align-items-center">
                <h4 class="mb-0">My Food Orders</h4>
                <a href="dashboard.php" class="btn btn-light btn-sm">
                    <i class="fas fa-arrow-left me-2"></i>Back to Dashboard
                </a>
            </div>
            <div class="card-body">
                <?php if (empty($food_orders)): ?>
                <div class="text-center py-5">
                    <i class="fas fa-utensils fa-3x text-muted mb-3"></i>
                    <h5>No food orders found</h5>
                    <p class="text-muted">You haven't placed any food orders yet.</p>
                    <a href="search_trains.php" class="btn btn-primary">
                        Book a Train and Order Food
                    </a>
                </div>
                <?php else: ?>
                <div class="table-responsive">
                    <table class="table table-hover">
                        <thead>
                            <tr>
                                <th>Order Details</th>
                                <th>Train & Journey</th>
                                <th>Vendor Details</th>
                                <th>Status</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach($food_orders as $order): ?>
                            <tr>
                                <td>
                                    <div class="order-details">
                                        <strong>Order #<?php echo htmlspecialchars($order['id']); ?></strong><br>
                                        <span class="text-muted"><?php echo htmlspecialchars($order['item_name']); ?></span><br>
                                        <strong class="text-primary">₹<?php echo number_format($order['total_amount'], 2); ?></strong>
                                    </div>
                                </td>
                                <td>
                                    <div class="order-details">
                                        <strong><?php echo htmlspecialchars($order['train_name']); ?></strong><br>
                                        <small class="text-muted"><?php echo htmlspecialchars($order['train_number']); ?></small><br>
                                        <small class="text-muted">
                                            <?php echo htmlspecialchars($order['source_station']); ?> → 
                                            <?php echo htmlspecialchars($order['destination_station']); ?>
                                        </small><br>
                                        <small class="text-muted">
                                            <?php echo date('d M Y h:i A', strtotime($order['journey_date'])); ?>
                                        </small>
                                    </div>
                                </td>
                                <td>
                                    <div class="vendor-info">
                                        <strong><?php echo htmlspecialchars($order['vendor_name']); ?></strong><br>
                                        <?php if (!empty($order['contact_number'])): ?>
                                        <small class="text-muted">
                                            <i class="fas fa-phone me-1"></i>
                                            <?php echo htmlspecialchars($order['contact_number']); ?>
                                        </small>
                                        <?php endif; ?>
                                    </div>
                                </td>
                                <td>
                                    <span class="status-badge status-<?php echo strtolower($order['status']); ?>">
                                        <?php echo htmlspecialchars($order['status']); ?>
                                    </span>
                                </td>
                                <td>
                                    <div class="btn-group">
                                        <a href="view_food_order.php?id=<?php echo $order['id']; ?>" 
                                           class="btn btn-sm btn-light" title="View Details">
                                            <i class="fas fa-eye"></i>
                                        </a>
                                        <?php if ($order['status'] != 'Cancelled' && $order['status'] != 'Delivered'): ?>
                                        <a href="cancel_food_order.php?id=<?php echo $order['id']; ?>" 
                                           class="btn btn-sm btn-danger" 
                                           onclick="return confirm('Are you sure you want to cancel this order?')"
                                           title="Cancel Order">
                                            <i class="fas fa-times"></i>
                                        </a>
                                        <?php endif; ?>
                                    </div>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html> 