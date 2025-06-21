<?php
require_once('../includes/config.php');
require_once('../includes/auth.php');

// Ensure user is logged in
checkUserLogin();

$booking_id = isset($_GET['booking_id']) ? (int)$_GET['booking_id'] : 0;
$error = '';
$success = '';
$booking = null;
$vendors = [];

// Get booking details
if ($booking_id) {
    $query = "SELECT b.*, t.name as train_name, t.train_number 
              FROM bookings b 
              JOIN trains t ON b.train_id = t.id 
              WHERE b.id = ? AND b.user_id = ?";
    
    $stmt = $conn->prepare($query);
    $stmt->bind_param("ii", $booking_id, $_SESSION['user_id']);
    $stmt->execute();
    $result = $stmt->get_result();
    $booking = $result->fetch_assoc();

    if ($booking) {
        // Get food vendors at stations between source and destination
        $query = "SELECT fv.* 
                 FROM food_vendors fv
                 JOIN train_stations ts ON fv.station_name = ts.station_name
                 WHERE ts.train_id = ? 
                 AND ts.sequence BETWEEN 
                     (SELECT sequence FROM train_stations WHERE train_id = ? AND station_name = ?)
                 AND 
                     (SELECT sequence FROM train_stations WHERE train_id = ? AND station_name = ?)
                 AND fv.status = 'Active'
                 ORDER BY ts.sequence";
        
        $stmt = $conn->prepare($query);
        $stmt->bind_param("iiiii", $booking['train_id'], $booking['train_id'], $booking['from_station'], 
                         $booking['train_id'], $booking['to_station']);
        $stmt->execute();
        $result = $stmt->get_result();
        $vendors = $result->fetch_all(MYSQLI_ASSOC);

        // Get menu items for each vendor
        foreach ($vendors as &$vendor) {
            $query = "SELECT * FROM food_menu WHERE vendor_id = ? AND is_available = 1";
            $stmt = $conn->prepare($query);
            $stmt->bind_param("i", $vendor['id']);
            $stmt->execute();
            $result = $stmt->get_result();
            $vendor['menu_items'] = $result->fetch_all(MYSQLI_ASSOC);
        }
    }
}

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $booking) {
    $orders = $_POST['orders'] ?? [];
    $delivery_station = $_POST['delivery_station'] ?? '';
    
    if (!$delivery_station || empty($orders)) {
        $error = 'Please select at least one item and delivery station';
    } else {
        $total_amount = 0;
        foreach ($orders as $menu_item_id => $quantity) {
            if ($quantity > 0) {
                // Get item price
                $stmt = $conn->prepare("SELECT price FROM food_menu WHERE id = ?");
                $stmt->bind_param("i", $menu_item_id);
                $stmt->execute();
                $result = $stmt->get_result();
                $item = $result->fetch_assoc();
                $total_amount += $item['price'] * $quantity;
            }
        }

        if ($total_amount > 0) {
            $conn->begin_transaction();
            
            try {
                foreach ($orders as $menu_item_id => $quantity) {
                    if ($quantity > 0) {
                        $stmt = $conn->prepare("INSERT INTO food_orders (booking_id, menu_item_id, quantity, total_amount, delivery_station) VALUES (?, ?, ?, ?, ?)");
                        $item_total = $quantity * $item['price'];
                        $stmt->bind_param("iiids", $booking_id, $menu_item_id, $quantity, $item_total, $delivery_station);
                        $stmt->execute();
                    }
                }
                
                $conn->commit();
                $success = 'Food order placed successfully!';
            } catch (Exception $e) {
                $conn->rollback();
                $error = 'Failed to place food order. Please try again.';
            }
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Food Pre-booking - Railway Reservation</title>
    <link rel="stylesheet" href="../css/bootstrap.min.css">
    <link rel="stylesheet" href="../css/style.css">
</head>
<body>
    <?php include('../includes/header.php'); ?>

    <div class="container mt-4">
        <h2>Food Pre-booking</h2>
        
        <?php if ($error): ?>
            <div class="alert alert-danger"><?php echo htmlspecialchars($error); ?></div>
        <?php endif; ?>

        <?php if ($success): ?>
            <div class="alert alert-success"><?php echo htmlspecialchars($success); ?></div>
        <?php endif; ?>

        <?php if ($booking): ?>
            <div class="card mb-4">
                <div class="card-body">
                    <h5 class="card-title">Journey Details</h5>
                    <p class="card-text">
                        Train: <?php echo htmlspecialchars($booking['train_name']); ?> (<?php echo htmlspecialchars($booking['train_number']); ?>)<br>
                        From: <?php echo htmlspecialchars($booking['from_station']); ?><br>
                        To: <?php echo htmlspecialchars($booking['to_station']); ?><br>
                        Journey Date: <?php echo date('d M Y', strtotime($booking['journey_date'])); ?><br>
                        PNR: <?php echo htmlspecialchars($booking['pnr_number']); ?>
                    </p>
                </div>
            </div>

            <?php if (count($vendors) > 0): ?>
                <form method="POST">
                    <div class="mb-3">
                        <label class="form-label">Delivery Station</label>
                        <select name="delivery_station" class="form-select" required>
                            <option value="">Select Station</option>
                            <?php foreach ($vendors as $vendor): ?>
                                <option value="<?php echo htmlspecialchars($vendor['station_name']); ?>">
                                    <?php echo htmlspecialchars($vendor['station_name']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <?php foreach ($vendors as $vendor): ?>
                        <div class="card mb-4">
                            <div class="card-header">
                                <h5 class="mb-0"><?php echo htmlspecialchars($vendor['name']); ?></h5>
                                <small class="text-muted">Station: <?php echo htmlspecialchars($vendor['station_name']); ?></small>
                            </div>
                            <div class="card-body">
                                <div class="table-responsive">
                                    <table class="table">
                                        <thead>
                                            <tr>
                                                <th>Item</th>
                                                <th>Description</th>
                                                <th>Price</th>
                                                <th>Quantity</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php foreach ($vendor['menu_items'] as $item): ?>
                                                <tr>
                                                    <td><?php echo htmlspecialchars($item['item_name']); ?></td>
                                                    <td><?php echo htmlspecialchars($item['description']); ?></td>
                                                    <td>₹<?php echo number_format($item['price'], 2); ?></td>
                                                    <td>
                                                        <input type="number" name="orders[<?php echo $item['id']; ?>]" 
                                                               class="form-control" style="width: 100px" min="0" value="0">
                                                    </td>
                                                </tr>
                                            <?php endforeach; ?>
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>

                    <button type="submit" class="btn btn-primary">Place Food Order</button>
                </form>
            <?php else: ?>
                <div class="alert alert-info">No food vendors available for your journey route</div>
            <?php endif; ?>
        <?php else: ?>
            <div class="alert alert-danger">Invalid booking selected</div>
        <?php endif; ?>
    </div>

    <?php include('../includes/footer.php'); ?>
    <script src="../js/bootstrap.bundle.min.js"></script>
</body>
</html> 