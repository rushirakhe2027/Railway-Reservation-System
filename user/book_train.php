<?php
session_start();
require_once "../includes/config.php";
require_once "../includes/db.php";
require_once "../includes/session.php";

// Enable error reporting
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Debug session information
error_log("Session data in book_train.php: " . print_r($_SESSION, true));

// Simplified session check focusing on just user_id and logged_in
if (!isset($_SESSION['user_id']) || !isset($_SESSION['logged_in']) || $_SESSION['logged_in'] !== true) {
    error_log("User not logged in - redirecting to login");
    $_SESSION['redirect_after_login'] = $_SERVER['REQUEST_URI']; // Store the current URL
    $_SESSION['toast'] = [
        'type' => 'warning',
        'message' => 'Please login to continue booking'
    ];
    header("Location: ../login.php");
    exit;
}

// Store user info for easy access
$user_id = $_SESSION['user_id'];
$user_name = $_SESSION['user_name'] ?? '';
$user_email = $_SESSION['user_email'] ?? '';

// Verify database connection early
try {
    $pdo->query("SELECT 1");
} catch (PDOException $e) {
    error_log("Database connection failed: " . $e->getMessage());
    $_SESSION['toast'] = [
        'type' => 'error',
        'message' => 'Database connection error. Please try again later.'
    ];
    header("Location: search_trains.php");
    exit;
}

// Get train details
if (!isset($_GET['train_id'])) {
    error_log("No train_id provided in URL");
    $_SESSION['toast'] = [
        'type' => 'error',
        'message' => 'No train ID provided'
    ];
    header("Location: search_trains.php");
    exit;
}

// Validate train_id is numeric
if (!is_numeric($_GET['train_id'])) {
    error_log("Invalid train_id format provided: " . $_GET['train_id']);
    $_SESSION['toast'] = [
        'type' => 'error',
        'message' => 'Invalid train ID format'
    ];
    header("Location: search_trains.php");
    exit;
}

$train_id = intval($_GET['train_id']);
error_log("Attempting to fetch details for train_id: " . $train_id);
$food_items = []; // Initialize with empty array as fallback
$food_by_station = [];

try {
    // Verify database connection
    $pdo->query("SELECT 1");
    
    // First check if train exists
    $sql = "SELECT * FROM trains WHERE id = :train_id";
    $stmt = $pdo->prepare($sql);
    $stmt->bindValue(':train_id', $train_id, PDO::PARAM_INT);
    $stmt->execute();
    $train = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$train) {
        error_log("Train not found with ID: " . $train_id);
        $_SESSION['toast'] = [
            'type' => 'error',
            'message' => 'Train not found'
        ];
        header("Location: search_trains.php");
        exit;
    }
    error_log("Train found: " . print_r($train, true));

    // Get stations for this train
    $sql = "SELECT station_name, arrival_time, departure_time, platform_number, stop_number 
            FROM train_stations 
            WHERE train_id = :train_id 
            ORDER BY stop_number ASC";
    $stmt = $pdo->prepare($sql);
    $stmt->bindValue(':train_id', $train_id, PDO::PARAM_INT);
    $stmt->execute();
    $stations = $stmt->fetchAll(PDO::FETCH_ASSOC);

    if (empty($stations)) {
        error_log("No stations found for train ID: " . $train_id);
        $_SESSION['toast'] = [
            'type' => 'error',
            'message' => 'No stations found for this train'
        ];
        header("Location: search_trains.php");
        exit;
    }
    error_log("Found " . count($stations) . " stations for train");

    // Get base fare range
    $sql = "SELECT MIN(base_fare) as min_fare, MAX(base_fare) as max_fare 
            FROM station_pricing 
            WHERE train_id = :train_id";
    $stmt = $pdo->prepare($sql);
    $stmt->bindValue(':train_id', $train_id, PDO::PARAM_INT);
    $stmt->execute();
    $pricing = $stmt->fetch(PDO::FETCH_ASSOC);
    error_log("Pricing data: " . print_r($pricing, true));

    // Merge pricing into train array with default values if null
    $train['min_fare'] = $pricing['min_fare'] ?? 0;
    $train['max_fare'] = $pricing['max_fare'] ?? 0;

    // Check if food_menu table exists
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM information_schema.tables WHERE table_schema = DATABASE() AND table_name = 'food_menu'");
    $stmt->execute();
    $food_menu_exists = (bool)$stmt->fetchColumn();
    
    // Get food menu items only if the table exists
    if ($food_menu_exists) {
        try {
            // Check if food_vendors table exists
            $stmt = $pdo->prepare("SELECT COUNT(*) FROM information_schema.tables WHERE table_schema = DATABASE() AND table_name = 'food_vendors'");
            $stmt->execute();
            $food_vendors_exists = (bool)$stmt->fetchColumn();
            
            if ($food_vendors_exists) {
                // Modified query to only get food vendors from stations on this train's route
                $sql = "SELECT fm.*, fv.name as vendor_name, fv.station_name as vendor_station 
                        FROM food_menu fm 
                        JOIN food_vendors fv ON fm.vendor_id = fv.id 
                        JOIN train_stations ts ON fv.station_name = ts.station_name 
                        WHERE fm.is_available = 1 
                        AND fv.status = 'Active' 
                        AND ts.train_id = :train_id
                        ORDER BY ts.stop_number, fm.item_name";
                        
                $stmt = $pdo->prepare($sql);
                $stmt->bindValue(':train_id', $train_id, PDO::PARAM_INT);
            } else {
                $sql = "SELECT *, 'Unknown Vendor' as vendor_name, '' as vendor_station 
                        FROM food_menu 
                        WHERE is_available = 1
                        ORDER BY item_name";
                $stmt = $pdo->prepare($sql);
            }
            
            $stmt->execute();
            $food_items = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            // Group food items by vendor station
            $food_by_station = [];
            foreach ($food_items as $item) {
                $station = $item['vendor_station'] ?: 'Other Stations';
                if (!isset($food_by_station[$station])) {
                    $food_by_station[$station] = [];
                }
                $food_by_station[$station][] = $item;
            }
            
            error_log("Found " . count($food_items) . " food items from stations on this route");
        } catch (PDOException $foodEx) {
            error_log("Error fetching food menu: " . $foodEx->getMessage());
            $food_items = [];
            $food_by_station = [];
        }
    } else {
        error_log("Food menu table does not exist");
        $food_items = [];
        $food_by_station = [];
    }

} catch (PDOException $e) {
    error_log("Database error in book_train.php: " . $e->getMessage());
    error_log("SQL State: " . $e->getCode());
    error_log("Stack trace: " . $e->getTraceAsString());
    
    // Check specific error conditions
    if ($e->getCode() == '42S02') {
        error_log("Table not found error - one of the required tables is missing");
        $_SESSION['toast'] = [
            'type' => 'error',
            'message' => 'Database configuration error. Please contact support.'
        ];
    } else {
        $_SESSION['toast'] = [
            'type' => 'error',
            'message' => 'An error occurred while fetching train details'
        ];
    }
    header("Location: search_trains.php");
    exit;
}

// Verify we have the minimum required data before proceeding
if (!isset($train) || !isset($stations)) {
    error_log("Missing required train or station data after database queries");
    $_SESSION['toast'] = [
        'type' => 'error',
        'message' => 'Unable to load all required booking information'
    ];
    header("Location: search_trains.php");
    exit;
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Book Train - RailYatra</title>
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
            --gradient-start: #e74c3c;
            --gradient-end: #f39c12;
            --hover-color: #c0392b;
        }

        body {
            font-family: 'Google Sans', Arial, sans-serif;
            background-color: var(--border-color);
            color: var(--text-color);
        }

        .container {
            max-width: 1200px;
            margin: 0 auto;
            padding: 2rem;
        }

        .card {
            border: none;
            border-radius: 15px;
            box-shadow: 0 4px 15px rgba(0,0,0,0.1);
            margin-bottom: 1.5rem;
        }

        .card-header {
            background: linear-gradient(135deg, var(--gradient-start), var(--gradient-end));
            color: white;
            border-radius: 15px 15px 0 0 !important;
            padding: 1rem 1.5rem;
        }

        .btn-primary {
            background: linear-gradient(135deg, var(--gradient-start), var(--gradient-end));
            border: none;
            padding: 0.8rem 2rem;
            border-radius: 50px;
            transition: all 0.3s ease;
        }

        .btn-primary:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 15px rgba(231, 76, 60, 0.3);
        }

        .btn-secondary {
            background: #95a5a6;
            border: none;
            padding: 0.8rem 2rem;
            border-radius: 50px;
        }

        .station-badge {
            background: white;
            border: 2px solid var(--primary-color);
            color: var(--primary-color);
            padding: 0.5rem 1.5rem;
            border-radius: 50px;
            margin: 0.3rem;
            font-weight: 500;
            transition: all 0.3s ease;
        }

        .station-badge.selected {
            background: var(--primary-color);
            color: white;
        }

        .food-item-card {
            border: 2px solid var(--border-color);
            border-radius: 15px;
            padding: 1.5rem;
            margin-bottom: 1rem;
            transition: all 0.3s ease;
            background: white;
        }

        .food-item-card:hover {
            border-color: var(--primary-color);
            box-shadow: 0 4px 15px rgba(231, 76, 60, 0.1);
        }

        .form-control {
            border-radius: 10px;
            padding: 0.8rem 1rem;
            border: 2px solid var(--border-color);
        }

        .form-control:focus {
            border-color: var(--primary-color);
            box-shadow: none;
        }

        .page-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 2rem;
        }

        .train-info {
            background: linear-gradient(135deg, var(--gradient-start), var(--gradient-end));
            color: white;
            padding: 2rem;
            border-radius: 15px;
            margin-bottom: 2rem;
        }

        .train-info h4 {
            margin: 0;
            font-size: 1.8rem;
        }

        .train-info p {
            margin: 0;
            opacity: 0.9;
        }

        .fare-card {
            position: sticky;
            top: 20px;
        }

        .remove-passenger {
            color: var(--primary-color);
            background: none;
            border: 2px solid var(--primary-color);
            border-radius: 50%;
            width: 35px;
            height: 35px;
            padding: 0;
            display: flex;
            align-items: center;
            justify-content: center;
            transition: all 0.3s ease;
        }

        .remove-passenger:hover {
            background: var(--primary-color);
            color: white;
        }

        .food-quantity {
            width: 80px;
            text-align: center;
        }

        .section-title {
            color: var(--primary-color);
            margin-bottom: 1.5rem;
            font-weight: 600;
        }

        /* Toast Notification Styles */
        .toast-container {
            position: fixed;
            top: 20px;
            right: 20px;
            z-index: 1050;
        }

        .custom-toast {
            min-width: 300px;
            padding: 15px 20px;
            border-radius: 8px;
            margin-bottom: 10px;
            box-shadow: 0 4px 12px rgba(0,0,0,0.15);
            display: flex;
            align-items: center;
            justify-content: space-between;
            animation: slideIn 0.3s ease-out;
        }

        .toast-success {
            background-color: #28a745;
            color: white;
        }

        .toast-error {
            background-color: #dc3545;
            color: white;
        }

        .toast-warning {
            background-color: #ffc107;
            color: #333;
        }

        .toast-info {
            background-color: #17a2b8;
            color: white;
        }

        @keyframes slideIn {
            from {
                transform: translateX(100%);
                opacity: 0;
            }
            to {
                transform: translateX(0);
                opacity: 1;
            }
        }

        .toast-close {
            background: none;
            border: none;
            color: inherit;
            font-size: 1.25rem;
            padding: 0;
            margin-left: 15px;
            opacity: 0.8;
            cursor: pointer;
        }

        .toast-close:hover {
            opacity: 1;
        }
    </style>
</head>
<body>
    <!-- Toast Container -->
    <div class="toast-container">
        <?php
        if (isset($_SESSION['toast'])) {
            $type = $_SESSION['toast']['type'] ?? 'info';
            $message = $_SESSION['toast']['message'] ?? '';
            $icon = '';
            
            switch($type) {
                case 'success':
                    $icon = 'check-circle';
                    break;
                case 'error':
                    $icon = 'exclamation-circle';
                    break;
                case 'warning':
                    $icon = 'exclamation-triangle';
                    break;
                default:
                    $icon = 'info-circle';
            }
            ?>
            <div class="custom-toast toast-<?php echo $type; ?>" id="toast">
                <div>
                    <i class="fas fa-<?php echo $icon; ?> me-2"></i>
                    <?php echo htmlspecialchars($message); ?>
                </div>
                <button class="toast-close" onclick="closeToast()">×</button>
            </div>
            <?php
            unset($_SESSION['toast']);
        }
        ?>
    </div>

    <div class="container">
        <div class="page-header">
            <h2>Book Train Ticket</h2>
            <a href="dashboard.php" class="btn btn-secondary">
                <i class="fas fa-arrow-left me-2"></i>Back to Dashboard
            </a>
        </div>
        
        <!-- Train Details -->
        <div class="train-info">
            <div class="row">
                <div class="col-md-6">
                    <h4><?php echo htmlspecialchars($train['train_name']); ?></h4>
                    <p class="mb-3">#<?php echo htmlspecialchars($train['train_number']); ?></p>
                </div>
                <div class="col-md-6 text-md-end">
                    <p class="mb-2">
                        <strong><?php echo htmlspecialchars($train['source_station']); ?></strong>
                        <i class="fas fa-arrow-right mx-2"></i>
                        <strong><?php echo htmlspecialchars($train['destination_station']); ?></strong>
                    </p>
                    <p>
                        <?php echo date('h:i A', strtotime($train['departure_time'])); ?>
                        -
                        <?php echo date('h:i A', strtotime($train['arrival_time'])); ?>
                    </p>
                </div>
            </div>
        </div>

        <form id="bookingForm" method="POST" action="process_booking.php">
            <input type="hidden" name="train_id" value="<?php echo $train_id; ?>">
            
            <div class="row">
                <div class="col-md-8">
                    <!-- Station Selection -->
                    <div class="card">
                        <div class="card-header">
                            <h5 class="mb-0"><i class="fas fa-map-marker-alt me-2"></i>Select Your Stations</h5>
                        </div>
                        <div class="card-body">
                            <div class="row">
                                <div class="col-md-4">
                                    <label class="form-label">From Station</label>
                                    <select name="from_station" class="form-control" required>
                                        <option value="">Select departure station</option>
                                        <?php foreach($stations as $station): ?>
                                        <option value="<?php echo htmlspecialchars($station['station_name']); ?>">
                                            <?php echo htmlspecialchars($station['station_name']); ?> 
                                            (<?php echo date('h:i A', strtotime($station['arrival_time'])); ?>)
                                        </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                <div class="col-md-4">
                                    <label class="form-label">To Station</label>
                                    <select name="to_station" class="form-control" required>
                                        <option value="">Select arrival station</option>
                                        <?php foreach($stations as $station): ?>
                                        <option value="<?php echo htmlspecialchars($station['station_name']); ?>">
                                            <?php echo htmlspecialchars($station['station_name']); ?>
                                            (<?php echo date('h:i A', strtotime($station['arrival_time'])); ?>)
                                        </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                <div class="col-md-4">
                                    <label class="form-label">Journey Date</label>
                                    <input type="date" name="journey_date" class="form-control" required 
                                           min="<?php echo date('Y-m-d'); ?>" 
                                           value="<?php echo date('Y-m-d'); ?>">
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Passenger Details -->
                    <div class="card">
                        <div class="card-header">
                            <h5 class="mb-0"><i class="fas fa-users me-2"></i>Passenger Details</h5>
                        </div>
                        <div class="card-body">
                            <div id="passengers">
                                <div class="passenger-row mb-3">
                                    <div class="row">
                                        <div class="col-md-3">
                                            <label class="form-label">Name</label>
                                            <input type="text" name="passenger_name[]" class="form-control" required>
                                        </div>
                                        <div class="col-md-2">
                                            <label class="form-label">Age</label>
                                            <input type="number" name="passenger_age[]" class="form-control" required>
                                        </div>
                                        <div class="col-md-3">
                                            <label class="form-label">Gender</label>
                                            <select name="passenger_gender[]" class="form-control" required>
                                                <option value="Male">Male</option>
                                                <option value="Female">Female</option>
                                                <option value="Other">Other</option>
                                            </select>
                                        </div>
                                        <div class="col-md-3">
                                            <label class="form-label">ID Proof</label>
                                            <input type="text" name="passenger_id_proof[]" class="form-control" required>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            <button type="button" class="btn btn-secondary" onclick="addPassenger()">
                                <i class="fas fa-plus me-2"></i>Add Passenger
                            </button>
                        </div>
                    </div>

                    <!-- Food Pre-booking -->
                    <div class="card">
                        <div class="card-header">
                            <h5 class="mb-0"><i class="fas fa-utensils me-2"></i>Pre-book Food</h5>
                        </div>
                        <div class="card-body">
                            <?php if (empty($food_by_station)): ?>
                            <div class="alert alert-info">
                                <i class="fas fa-info-circle me-2"></i>
                                Food pre-booking is currently unavailable for this journey.
                            </div>
                            <?php else: ?>
                            <div class="accordion" id="foodAccordion">
                                <?php 
                                // Sort stations based on stop_number
                                $train_stations_order = array_column($stations, 'stop_number', 'station_name');
                                
                                // Sort food_by_station array based on station order
                                uksort($food_by_station, function($a, $b) use ($train_stations_order) {
                                    $a_order = $train_stations_order[$a] ?? PHP_INT_MAX;
                                    $b_order = $train_stations_order[$b] ?? PHP_INT_MAX;
                                    return $a_order - $b_order;
                                });
                                
                                foreach($food_by_station as $station => $items): 
                                    // Skip if station is not in train's route
                                    if (!isset($train_stations_order[$station])) continue;
                                ?>
                                <div class="accordion-item mb-3">
                                    <h2 class="accordion-header">
                                        <button class="accordion-button" type="button" data-bs-toggle="collapse" 
                                                data-bs-target="#station<?php echo md5($station); ?>">
                                            <i class="fas fa-map-marker-alt me-2"></i>
                                            <?php echo htmlspecialchars($station); ?>
                                            <small class="ms-2 text-muted">
                                                (Stop <?php echo $train_stations_order[$station]; ?>)
                                            </small>
                                        </button>
                                    </h2>
                                    <div id="station<?php echo md5($station); ?>" class="accordion-collapse collapse show">
                                        <div class="accordion-body">
                                            <div class="row">
                                                <?php foreach($items as $item): ?>
                                                <div class="col-md-6 mb-3">
                                                    <div class="food-item-card">
                                                        <div class="d-flex justify-content-between align-items-start">
                                                            <div>
                                                                <h6 class="mb-1"><?php echo htmlspecialchars($item['item_name']); ?></h6>
                                                                <?php if (!empty($item['description'])): ?>
                                                                <p class="text-muted small mb-2"><?php echo htmlspecialchars($item['description']); ?></p>
                                                                <?php endif; ?>
                                                                <p class="text-muted small mb-2">
                                                                    <i class="fas fa-store me-1"></i>
                                                                    <?php echo htmlspecialchars($item['vendor_name']); ?>
                                                                </p>
                                                                <p class="mb-0 text-primary fw-bold">₹<?php echo number_format($item['price'], 2); ?></p>
                                                            </div>
                                                            <div class="text-end">
                                                                <label class="form-label">Quantity</label>
                                                                <input type="number" name="food_items[<?php echo $item['id']; ?>]" 
                                                                       class="form-control food-quantity" value="0" min="0" 
                                                                       onchange="updateTotal()">
                                                            </div>
                                                        </div>
                                                        <div class="mt-3">
                                                            <label class="form-label">Delivery Station</label>
                                                            <select name="food_station[<?php echo $item['id']; ?>]" class="form-control">
                                                                <option value="">Select Station</option>
                                                                <?php 
                                                                $vendor_stop_number = $train_stations_order[$station];
                                                                foreach($stations as $train_station): 
                                                                    // Only show stations after the vendor's station
                                                                    if ($train_station['stop_number'] >= $vendor_stop_number): 
                                                                ?>
                                                                <option value="<?php echo htmlspecialchars($train_station['station_name']); ?>">
                                                                    <?php echo htmlspecialchars($train_station['station_name']); ?>
                                                                    (<?php echo date('h:i A', strtotime($train_station['arrival_time'])); ?>)
                                                                </option>
                                                                <?php endif; endforeach; ?>
                                                            </select>
                                                        </div>
                                                    </div>
                                                </div>
                                                <?php endforeach; ?>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                                <?php endforeach; ?>
                            </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>

                <div class="col-md-4">
                    <!-- Fare Summary -->
                    <div class="card fare-card">
                        <div class="card-header">
                            <h5 class="mb-0"><i class="fas fa-receipt me-2"></i>Fare Summary</h5>
                        </div>
                        <div class="card-body">
                            <div id="fareSummary">
                                <div class="d-flex justify-content-between mb-3">
                                    <span>Base Fare:</span>
                                    <span id="baseFare" class="fw-bold">₹0.00</span>
                                </div>
                                <div class="d-flex justify-content-between mb-3">
                                    <span>Food Total:</span>
                                    <span id="foodTotal" class="fw-bold">₹0.00</span>
                                </div>
                                <hr>
                                <div class="d-flex justify-content-between">
                                    <span class="h5">Total Amount:</span>
                                    <span id="totalAmount" class="h5 text-primary">₹0.00</span>
                                </div>
                                <button type="submit" class="btn btn-primary w-100 mt-4">
                                    <i class="fas fa-ticket-alt me-2"></i>Proceed to Payment
                                </button>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </form>
    </div>

    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Debug logging function
        function debug(message, data = null) {
            console.log(`[Debug] ${message}`, data || '');
        }

        // Function to fetch fare between stations
        async function getFare() {
            const fromStation = document.querySelector('select[name="from_station"]').value;
            const toStation = document.querySelector('select[name="to_station"]').value;
            
            debug('Fetching fare for:', { fromStation, toStation });
            
            if (!fromStation || !toStation) {
                debug('Missing station selection');
                return 0;
            }

            try {
                const url = `get_fare.php?train_id=<?php echo $train_id; ?>&from=${encodeURIComponent(fromStation)}&to=${encodeURIComponent(toStation)}`;
                debug('Fetching from URL:', url);

                const response = await fetch(url);
                const data = await response.json();
                debug('Fare API response:', data);
                
                if (data.success) {
                    const fare = parseFloat(data.fare) || 0;
                    debug('Received fare:', fare);
                    return fare;
                } else {
                    console.error('Error getting fare:', data.message);
                    return 0;
                }
            } catch (error) {
                console.error('Error fetching fare:', error);
                return 0;
            }
        }

        // Function to update total amount
        async function updateTotal() {
            debug('Updating total...');
            const passengers = document.querySelectorAll('.passenger-row').length;
            debug('Number of passengers:', passengers);
            
            // Get base fare
            const baseFare = await getFare();
            debug('Base fare per passenger:', baseFare);
            const totalBaseFare = baseFare * passengers;
            debug('Total base fare:', totalBaseFare);
            
            // Calculate food total
            let foodTotal = 0;
            const foodItems = document.querySelectorAll('input[name^="food_items"]');
            foodItems.forEach(input => {
                const quantity = parseInt(input.value) || 0;
                const priceElem = input.closest('.food-item-card').querySelector('.text-primary');
                if (priceElem) {
                    const price = parseFloat(priceElem.textContent.replace('₹', '').replace(',', '')) || 0;
                    foodTotal += quantity * price;
                    debug('Food item:', { quantity, price, subtotal: quantity * price });
                }
            });
            debug('Total food cost:', foodTotal);

            // Update display
            const elements = {
                baseFare: document.getElementById('baseFare'),
                foodTotal: document.getElementById('foodTotal'),
                totalAmount: document.getElementById('totalAmount')
            };

            if (!elements.baseFare || !elements.foodTotal || !elements.totalAmount) {
                console.error('Missing required elements for fare display');
                return;
            }

            debug('Updating display with:', {
                baseFare: totalBaseFare,
                foodTotal: foodTotal,
                total: totalBaseFare + foodTotal
            });

            // Format the values with Indian Rupee symbol and proper decimal places
            elements.baseFare.textContent = `₹${totalBaseFare.toFixed(2)}`;
            elements.foodTotal.textContent = `₹${foodTotal.toFixed(2)}`;
            elements.totalAmount.textContent = `₹${(totalBaseFare + foodTotal).toFixed(2)}`;
        }

        // Add event listeners
        document.addEventListener('DOMContentLoaded', function() {
            debug('Initializing booking form...');
            
            const fromStationSelect = document.querySelector('select[name="from_station"]');
            const toStationSelect = document.querySelector('select[name="to_station"]');
            
            if (!fromStationSelect || !toStationSelect) {
                console.error('Station select elements not found');
                return;
            }

            // Add change event listeners
            fromStationSelect.addEventListener('change', function(e) {
                debug('From station changed to:', e.target.value);
                updateTotal();
            });

            toStationSelect.addEventListener('change', function(e) {
                debug('To station changed to:', e.target.value);
                updateTotal();
            });
            
            // Add event listeners to food items if they exist
            const foodItems = document.querySelectorAll('input[name^="food_items"]');
            foodItems.forEach(input => {
                input.addEventListener('change', function(e) {
                    debug('Food quantity changed:', {
                        id: input.name,
                        quantity: e.target.value
                    });
                    updateTotal();
                });
            });

            debug('Event listeners attached, performing initial calculation');
            // Initial calculation
            updateTotal();
        });

        // Function to add passenger
        function addPassenger() {
            const passengerHtml = `
                <div class="passenger-row mb-3">
                    <div class="row">
                        <div class="col-md-3">
                            <label class="form-label">Name</label>
                            <input type="text" name="passenger_name[]" class="form-control" required>
                        </div>
                        <div class="col-md-2">
                            <label class="form-label">Age</label>
                            <input type="number" name="passenger_age[]" class="form-control" required>
                        </div>
                        <div class="col-md-3">
                            <label class="form-label">Gender</label>
                            <select name="passenger_gender[]" class="form-control" required>
                                <option value="Male">Male</option>
                                <option value="Female">Female</option>
                                <option value="Other">Other</option>
                            </select>
                        </div>
                        <div class="col-md-3">
                            <label class="form-label">ID Proof</label>
                            <input type="text" name="passenger_id_proof[]" class="form-control" required>
                        </div>
                        <div class="col-md-1">
                            <label class="form-label">&nbsp;</label>
                            <button type="button" class="btn remove-passenger" onclick="removePassenger(this)">
                                <i class="fas fa-times"></i>
                            </button>
                        </div>
                    </div>
                </div>
            `;
            document.getElementById('passengers').insertAdjacentHTML('beforeend', passengerHtml);
            updateTotal();
        }

        // Function to remove passenger
        function removePassenger(button) {
            button.closest('.passenger-row').remove();
            updateTotal();
        }

        // Validate form before submission
        document.getElementById('bookingForm').addEventListener('submit', function(e) {
            // Check required fields
            const fromStation = document.querySelector('select[name="from_station"]').value;
            const toStation = document.querySelector('select[name="to_station"]').value;
            const journeyDate = document.querySelector('input[name="journey_date"]').value;
            const passengers = document.querySelectorAll('.passenger-row').length;
            
            if (!fromStation || !toStation || !journeyDate) {
                e.preventDefault();
                alert('Please fill in all required fields (From Station, To Station, and Journey Date).');
                return false;
            }
            
            if (fromStation === toStation) {
                e.preventDefault();
                alert('Please select different stations for departure and arrival.');
                return false;
            }

            if (passengers === 0) {
                e.preventDefault();
                alert('Please add at least one passenger.');
                return false;
            }

            // Validate passenger details
            let hasInvalidPassenger = false;
            document.querySelectorAll('.passenger-row').forEach(row => {
                const name = row.querySelector('input[name="passenger_name[]"]').value;
                const age = row.querySelector('input[name="passenger_age[]"]').value;
                const gender = row.querySelector('select[name="passenger_gender[]"]').value;
                const idProof = row.querySelector('input[name="passenger_id_proof[]"]').value;

                if (!name || !age || !gender || !idProof) {
                    hasInvalidPassenger = true;
                }
            });

            if (hasInvalidPassenger) {
                e.preventDefault();
                alert('Please fill in all passenger details.');
                return false;
            }

            const foodItems = document.querySelectorAll('input[name^="food_items"]');
            let hasInvalidFood = false;
            
            foodItems.forEach(input => {
                const quantity = parseInt(input.value) || 0;
                if (quantity > 0) {
                    const deliveryStation = input.closest('.food-item-card')
                        .querySelector('select[name^="food_station"]').value;
                    if (!deliveryStation) {
                        hasInvalidFood = true;
                    }
                }
            });

            if (hasInvalidFood) {
                e.preventDefault();
                alert('Please select delivery stations for all food items you want to order.');
                return false;
            }
        });

        // Add this at the beginning of your script section
        function closeToast() {
            const toast = document.getElementById('toast');
            if (toast) {
                toast.style.opacity = '0';
                setTimeout(() => toast.remove(), 300);
            }
        }

        // Auto-close toast after 5 seconds
        setTimeout(closeToast, 5000);
    </script>
</body>
</html> 