<?php
session_start();
require_once "../includes/config.php";

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header("Location: ../login.php");
    exit;
}

// Get user's active bookings
$sql = "SELECT b.*, t.train_name, t.train_number,
        t.source_station, t.destination_station,
        r.stations as route_stations
        FROM bookings b
        JOIN trains t ON b.train_id = t.id
        LEFT JOIN routes r ON t.route_id = r.id
        WHERE b.user_id = ? AND b.status = 'Confirmed'
        AND b.booking_date >= CURDATE()
        ORDER BY b.booking_date ASC";
$stmt = $pdo->prepare($sql);
$stmt->execute([$_SESSION['user_id']]);
$bookings = $stmt->fetchAll();

if (empty($bookings)) {
    $_SESSION['toast'] = [
        'type' => 'error',
        'message' => 'You have no active bookings to order food for.'
    ];
    header("Location: food_orders.php");
    exit;
}

// Handle food order submission
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['place_order'])) {
    try {
        // Validate input
        if (empty($_POST['booking_id']) || empty($_POST['menu_item_id']) || empty($_POST['quantity'])) {
            throw new Exception('Please fill all required fields');
        }

        $booking_id = $_POST['booking_id'];
        $menu_item_id = $_POST['menu_item_id'];
        $quantity = (int)$_POST['quantity'];

        // Validate booking belongs to user
        $sql = "SELECT id FROM bookings WHERE id = ? AND user_id = ?";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([$booking_id, $_SESSION['user_id']]);
        if (!$stmt->fetch()) {
            throw new Exception('Invalid booking selected');
        }

        // Get menu item details
        $sql = "SELECT price FROM food_menu WHERE id = ?";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([$menu_item_id]);
        $menu_item = $stmt->fetch();
        if (!$menu_item) {
            throw new Exception('Invalid menu item selected');
        }

        $total_amount = $menu_item['price'] * $quantity;

        // Begin transaction
        $pdo->beginTransaction();

        // Insert order
        $sql = "INSERT INTO food_orders (booking_id, menu_item_id, quantity, total_amount, status)
                VALUES (?, ?, ?, ?, 'Confirmed')";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([$booking_id, $menu_item_id, $quantity, $total_amount]);

        $pdo->commit();

        $_SESSION['toast'] = [
            'type' => 'success',
            'message' => 'Food order placed successfully!'
        ];
        header("Location: food_orders.php");
        exit;

    } catch (Exception $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        $_SESSION['toast'] = [
            'type' => 'error',
            'message' => 'Failed to place order: ' . $e->getMessage()
        ];
    }
}

// Get all vendors and their menu items
$sql = "SELECT v.*, fm.id as menu_item_id, fm.item_name, fm.description, fm.price, fm.is_available
        FROM food_vendors v
        JOIN food_menu fm ON v.id = fm.vendor_id
        WHERE fm.is_available = 1
        ORDER BY v.vendor_name ASC, fm.item_name ASC";
$stmt = $pdo->prepare($sql);
$stmt->execute();
$menu_items = $stmt->fetchAll();

// Group menu items by vendor
$vendors = [];
foreach ($menu_items as $item) {
    if (!isset($vendors[$item['id']])) {
        $vendors[$item['id']] = [
            'id' => $item['id'],
            'vendor_name' => $item['vendor_name'],
            'station_name' => $item['station_name'],
            'menu_items' => []
        ];
    }
    $vendors[$item['id']]['menu_items'][] = [
        'id' => $item['menu_item_id'],
        'name' => $item['item_name'],
        'description' => $item['description'],
        'price' => $item['price']
    ];
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Order Food - RailYatra</title>
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

        .vendor-card {
            background: white;
            border-radius: 12px;
            margin-bottom: 1.5rem;
            overflow: hidden;
            box-shadow: 0 2px 10px rgba(0,0,0,0.08);
        }

        .vendor-header {
            background: linear-gradient(135deg, var(--gradient-start), var(--gradient-end));
            color: white;
            padding: 1.5rem;
        }

        .menu-item {
            border-bottom: 1px solid var(--border-color);
            padding: 1rem;
            transition: background-color 0.3s ease;
        }

        .menu-item:last-child {
            border-bottom: none;
        }

        .menu-item:hover {
            background-color: rgba(236, 240, 241, 0.5);
        }

        .btn-order {
            background: var(--primary-color);
            color: white;
            border: none;
            padding: 8px 20px;
            border-radius: 8px;
            transition: all 0.3s ease;
        }

        .btn-order:hover {
            background: var(--hover-color);
            transform: translateY(-2px);
            color: white;
        }

        .booking-select {
            background-color: white;
            border: 2px solid var(--border-color);
            border-radius: 8px;
            padding: 1rem;
            margin-bottom: 2rem;
        }

        .booking-option {
            border: 2px solid var(--border-color);
            border-radius: 8px;
            padding: 1rem;
            margin-bottom: 1rem;
            cursor: pointer;
            transition: all 0.3s ease;
        }

        .booking-option:hover {
            border-color: var(--primary-color);
            transform: translateY(-2px);
        }

        .booking-option.selected {
            border-color: var(--primary-color);
            background-color: rgba(231, 76, 60, 0.1);
        }

        #orderModal .modal-content {
            border-radius: 12px;
            overflow: hidden;
        }

        #orderModal .modal-header {
            background: linear-gradient(135deg, var(--gradient-start), var(--gradient-end));
            color: white;
            border: none;
        }

        #orderModal .modal-title {
            font-weight: 600;
        }

        #orderModal .btn-close {
            color: white;
            filter: brightness(0) invert(1);
        }

        .quantity-input {
            width: 80px;
            text-align: center;
            border: 2px solid var(--border-color);
            border-radius: 8px;
            padding: 0.5rem;
        }

        .station-badge {
            display: inline-block;
            padding: 4px 12px;
            background: rgba(255,255,255,0.2);
            border-radius: 20px;
            font-size: 0.85rem;
            margin-top: 10px;
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
                            <a class="nav-link" href="my_bookings.php">
                                <i class="fas fa-ticket-alt me-2"></i>My Bookings
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link active" href="food_orders.php">
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
                <div class="d-flex justify-content-between align-items-center mb-4">
                    <h2>Order Food</h2>
                    <a href="food_orders.php" class="btn btn-outline-secondary">
                        <i class="fas fa-arrow-left me-2"></i>Back to Orders
                    </a>
                </div>

                <!-- Booking Selection -->
                <div class="booking-select">
                    <h4 class="mb-3">Select Your Booking</h4>
                    <?php foreach ($bookings as $booking): ?>
                    <div class="booking-option" data-booking-id="<?php echo $booking['id']; ?>">
                        <div class="d-flex justify-content-between align-items-center">
                            <div>
                                <h5 class="mb-1"><?php echo htmlspecialchars($booking['train_name']); ?></h5>
                                <p class="mb-2 text-muted">
                                    <small>
                                        <i class="fas fa-train me-2"></i><?php echo htmlspecialchars($booking['train_number']); ?>
                                    </small>
                                </p>
                                <p class="mb-0">
                                    <i class="fas fa-map-marker-alt me-2"></i>
                                    <?php echo htmlspecialchars($booking['source_station']); ?> → 
                                    <?php echo htmlspecialchars($booking['destination_station']); ?>
                                </p>
                            </div>
                            <div class="text-end">
                                <p class="mb-1">
                                    <i class="fas fa-calendar me-2"></i>
                                    <?php echo date('d M Y', strtotime($booking['booking_date'])); ?>
                                </p>
                            </div>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>

                <!-- Vendors and Menu Items -->
                <?php if (empty($vendors)): ?>
                <div class="alert alert-info">
                    <i class="fas fa-info-circle me-2"></i>No food vendors available at the moment.
                </div>
                <?php else: ?>
                    <?php foreach ($vendors as $vendor): ?>
                    <div class="vendor-card">
                        <div class="vendor-header">
                            <h4 class="mb-1"><?php echo htmlspecialchars($vendor['vendor_name']); ?></h4>
                            <div class="station-badge">
                                <i class="fas fa-map-marker-alt me-2"></i>
                                <?php echo htmlspecialchars($vendor['station_name']); ?>
                            </div>
                        </div>
                        <div class="menu-items">
                            <?php foreach ($vendor['menu_items'] as $item): ?>
                            <div class="menu-item">
                                <div class="row align-items-center">
                                    <div class="col-md-8">
                                        <h5 class="mb-1"><?php echo htmlspecialchars($item['name']); ?></h5>
                                        <p class="mb-0 text-muted">
                                            <?php echo htmlspecialchars($item['description']); ?>
                                        </p>
                                    </div>
                                    <div class="col-md-4 text-end">
                                        <h5 class="mb-2">₹<?php echo number_format($item['price'], 2); ?></h5>
                                        <button class="btn btn-order btn-sm" onclick="showOrderModal(<?php echo $item['id']; ?>, '<?php echo htmlspecialchars($item['name']); ?>', <?php echo $item['price']; ?>)">
                                            <i class="fas fa-shopping-cart me-2"></i>Order
                                        </button>
                                    </div>
                                </div>
                            </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- Order Modal -->
    <div class="modal fade" id="orderModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Place Order</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <form method="POST" id="orderForm">
                    <div class="modal-body">
                        <input type="hidden" name="menu_item_id" id="menuItemId">
                        <input type="hidden" name="booking_id" id="bookingId">
                        <input type="hidden" name="place_order" value="1">
                        
                        <div class="mb-3">
                            <label class="form-label">Item Name</label>
                            <input type="text" class="form-control" id="itemName" readonly>
                        </div>
                        
                        <div class="mb-3">
                            <label class="form-label">Price</label>
                            <input type="text" class="form-control" id="itemPrice" readonly>
                        </div>
                        
                        <div class="mb-3">
                            <label class="form-label">Quantity</label>
                            <input type="number" class="form-control quantity-input" name="quantity" id="quantity" min="1" max="10" value="1" required>
                        </div>
                        
                        <div class="mb-3">
                            <label class="form-label">Total Amount</label>
                            <input type="text" class="form-control" id="totalAmount" readonly>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-order">Place Order</button>
                    </div>
                </form>
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

        // Booking selection
        let selectedBookingId = null;
        const bookingOptions = document.querySelectorAll('.booking-option');
        
        bookingOptions.forEach(option => {
            option.addEventListener('click', function() {
                bookingOptions.forEach(opt => opt.classList.remove('selected'));
                this.classList.add('selected');
                selectedBookingId = this.dataset.bookingId;
            });
        });

        // Order modal
        function showOrderModal(menuItemId, itemName, price) {
            if (!selectedBookingId) {
                toastr.error('Please select a booking first');
                return;
            }

            document.getElementById('menuItemId').value = menuItemId;
            document.getElementById('bookingId').value = selectedBookingId;
            document.getElementById('itemName').value = itemName;
            document.getElementById('itemPrice').value = '₹' + price.toFixed(2);
            updateTotal();

            const modal = new bootstrap.Modal(document.getElementById('orderModal'));
            modal.show();
        }

        // Update total amount
        document.getElementById('quantity').addEventListener('input', updateTotal);

        function updateTotal() {
            const price = parseFloat(document.getElementById('itemPrice').value.replace('₹', ''));
            const quantity = parseInt(document.getElementById('quantity').value);
            const total = price * quantity;
            document.getElementById('totalAmount').value = '₹' + total.toFixed(2);
        }

        // Form validation
        document.getElementById('orderForm').addEventListener('submit', function(e) {
            if (!selectedBookingId) {
                e.preventDefault();
                toastr.error('Please select a booking first');
            }
        });
    </script>
</body>
</html> 