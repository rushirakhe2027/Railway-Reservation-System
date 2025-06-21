<?php
session_start();

// Check if user is logged in and is admin
if (!isset($_SESSION['admin_id']) || !isset($_SESSION['user_type']) || $_SESSION['user_type'] !== 'admin') {
    header("Location: ../login.php");
    exit();
}

require_once "../includes/config.php";
require_once "../includes/admin_auth.php";
require_once "../includes/db.php";

// Require admin login
requireAdminLogin();

// Get admin info
$admin = getAdminInfo();

// Handle train operations
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    if (isset($_POST['action'])) {
        switch ($_POST['action']) {
            case 'add':
                try {
                    // Enable error reporting
                    error_reporting(E_ALL);
                    ini_set('display_errors', 1);
                    
                    // Log the POST data
                    error_log("Adding new train with data: " . print_r($_POST, true));
                    
                    $pdo->beginTransaction();
                    
                    // Format times for database
                    $departure_time = date('H:i:s', strtotime($_POST['departure_time']));
                    $arrival_time = date('H:i:s', strtotime($_POST['arrival_time']));
                    
                    // Create route first
                    $sql = "INSERT INTO routes (name, source_station, destination_station) 
                            VALUES (?, ?, ?)";
                    $stmt = $pdo->prepare($sql);
                    $stmt->execute([
                        $_POST['route_name'],
                        $_POST['source_station'],
                        $_POST['destination_station']
                    ]);
                    $route_id = $pdo->lastInsertId();
                    
                    // Insert train details with route_id
                    $sql = "INSERT INTO trains (train_number, train_name, source_station, destination_station, 
                            departure_time, arrival_time, total_seats, status, route_id) 
                            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)";
                    $stmt = $pdo->prepare($sql);
                    $stmt->execute([
                        $_POST['train_number'],
                        $_POST['train_name'],
                        $_POST['source_station'],
                        $_POST['destination_station'],
                        $departure_time,
                        $arrival_time,
                        $_POST['total_seats'],
                        'Active',
                        $route_id
                    ]);
                    
                    $train_id = $pdo->lastInsertId();
                    
                    // Insert source station with pricing
                    $sql = "INSERT INTO train_stations (train_id, station_name, departure_time, stop_number, platform_number) 
                            VALUES (?, ?, ?, ?, ?)";
                    $stmt = $pdo->prepare($sql);
                    $stmt->execute([
                        $train_id,
                        $_POST['source_station'],
                        $departure_time,
                        1,
                        $_POST['source_platform']
                    ]);

                    // Insert source to first station pricing
                    if (isset($_POST['source_distance']) && isset($_POST['source_base_fare'])) {
                        $sql = "INSERT INTO station_pricing (
                            train_id, from_station, to_station, distance, base_fare
                        ) VALUES (?, ?, ?, ?, ?)";
                        
                        $stmt = $pdo->prepare($sql);
                        $stmt->execute([
                            $train_id,
                            $_POST['source_station'],
                            $_POST['intermediate_stations'][0] ?? $_POST['destination_station'],
                            $_POST['source_distance'],
                            $_POST['source_base_fare']
                        ]);
                    } else {
                        throw new Exception("Source station pricing details are required");
                    }
                    
                    // Insert intermediate stations if any
                    if (!empty($_POST['intermediate_stations'])) {
                        $station_sql = "INSERT INTO train_stations (train_id, station_name, arrival_time, departure_time, 
                                stop_number, platform_number) VALUES (?, ?, ?, ?, ?, ?)";
                        $station_stmt = $pdo->prepare($station_sql);
                        
                        $pricing_sql = "INSERT INTO station_pricing (
                            train_id, from_station, to_station, distance, base_fare
                        ) VALUES (?, ?, ?, ?, ?)";
                        $pricing_stmt = $pdo->prepare($pricing_sql);
                        
                        foreach ($_POST['intermediate_stations'] as $index => $station) {
                            if (!empty($station)) {
                                // Insert station
                                $station_stmt->execute([
                                    $train_id,
                                    $station,
                                    $_POST['arrival_times'][$index],
                                    $_POST['departure_times'][$index],
                                    $index + 2, // +2 because source station is 1
                                    $_POST['platform_numbers'][$index]
                                ]);
                                
                                // Insert pricing to next station
                                $next_station = isset($_POST['intermediate_stations'][$index + 1]) 
                                    ? $_POST['intermediate_stations'][$index + 1] 
                                    : $_POST['destination_station'];
                                
                                if (isset($_POST['distances'][$index])) {
                                    $pricing_stmt->execute([
                                        $train_id,
                                        $station,
                                        $next_station,
                                        $_POST['distances'][$index],
                                        $_POST['intermediate_base_fares'][$index]
                                    ]);
                                }
                            }
                        }
                    }
                    
                    // Insert destination station
                    $sql = "INSERT INTO train_stations (train_id, station_name, arrival_time, stop_number, platform_number) 
                            VALUES (?, ?, ?, ?, ?)";
                    $stmt = $pdo->prepare($sql);
                    $stmt->execute([
                        $train_id,
                        $_POST['destination_station'],
                        $arrival_time,
                        count($_POST['intermediate_stations'] ?? []) + 2, // +2 for source and destination
                        $_POST['destination_platform']
                    ]);
                    
                    $pdo->commit();
                    showToast("Train added successfully!", "success");
                } catch (Exception $e) {
                    $pdo->rollBack();
                    error_log("Error adding train: " . $e->getMessage());
                    showToast("Error adding train: " . $e->getMessage(), "error");
                }
                break;

            case 'edit':
                $sql = "UPDATE trains SET 
                        train_number = ?, 
                        train_name = ?, 
                        source_station = ?, 
                        destination_station = ?, 
                        departure_time = ?, 
                        arrival_time = ?, 
                        total_seats = ?, 
                        status = ? 
                        WHERE id = ?";
                $stmt = $pdo->prepare($sql);
                $stmt->execute([
                    $_POST['train_number'],
                    $_POST['train_name'],
                    $_POST['source_station'],
                    $_POST['destination_station'],
                    $_POST['departure_time'],
                    $_POST['arrival_time'],
                    $_POST['total_seats'],
                    $_POST['status'],
                    $_POST['train_id']
                ]);
                showToast("Train updated successfully!", "success");
                break;

            case 'delete':
                // Check if train has any bookings
                $sql = "SELECT COUNT(*) FROM bookings WHERE train_id = ?";
                $stmt = $pdo->prepare($sql);
                $stmt->execute([$_POST['train_id']]);
                $bookingCount = $stmt->fetchColumn();

                if ($bookingCount > 0) {
                    showToast("Cannot delete train with existing bookings!", "error");
                } else {
                    try {
                        $pdo->beginTransaction();
                        
                        // First delete station pricing records
                        $sql = "DELETE FROM station_pricing WHERE train_id = ?";
                        $stmt = $pdo->prepare($sql);
                        $stmt->execute([$_POST['train_id']]);
                        
                        // Then delete train stations
                        $sql = "DELETE FROM train_stations WHERE train_id = ?";
                        $stmt = $pdo->prepare($sql);
                        $stmt->execute([$_POST['train_id']]);
                        
                        // Finally delete the train
                        $sql = "DELETE FROM trains WHERE id = ?";
                        $stmt = $pdo->prepare($sql);
                        $stmt->execute([$_POST['train_id']]);
                        
                        $pdo->commit();
                        showToast("Train deleted successfully!", "success");
                    } catch (Exception $e) {
                        $pdo->rollBack();
                        showToast("Error deleting train: " . $e->getMessage(), "error");
                    }
                }
                break;
        }
    }
}

// Get all trains
$sql = "SELECT t.*, 
        (SELECT COUNT(*) FROM bookings b WHERE b.train_id = t.id) as booking_count,
        (SELECT COUNT(*) FROM seats s WHERE s.train_id = t.id AND s.is_available = 1) as available_seats,
        (SELECT MIN(base_fare) FROM station_pricing sp WHERE sp.train_id = t.id) as min_fare
        FROM trains t ORDER BY t.created_at DESC";
$stmt = $pdo->query($sql);
$trains = $stmt->fetchAll();

// Get statistics
$sql = "SELECT 
        COUNT(*) as total_trains,
        COUNT(CASE WHEN status = 'Active' THEN 1 END) as active_trains,
        COUNT(CASE WHEN status = 'Delayed' THEN 1 END) as delayed_trains,
        COUNT(CASE WHEN status = 'Cancelled' THEN 1 END) as cancelled_trains
        FROM trains";
$stmt = $pdo->query($sql);
$stats = $stmt->fetch();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Trains - RailYatra Admin</title>
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

        .btn-primary {
            background: var(--primary-color);
            border: none;
        }

        .btn-primary:hover {
            background: var(--hover-color);
        }

        .status-badge {
            padding: 6px 12px;
            border-radius: 20px;
            font-size: 0.85rem;
            font-weight: 500;
        }

        .status-Active { background-color: #27ae60; color: white; }
        .status-Delayed { background-color: #f39c12; color: white; }
        .status-Cancelled { background-color: #c0392b; color: white; }
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
                            <a class="nav-link active" href="trains.php">
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
            <div class="col-md-9 col-lg-10 p-4">
                <div class="d-flex justify-content-between align-items-center mb-4">
                    <h2>Manage Trains</h2>
                    <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addTrainModal">
                        <i class="fas fa-plus me-2"></i>Add New Train
                    </button>
                </div>

                <!-- Statistics Cards -->
                <div class="row mb-4">
                    <div class="col-md-3">
                        <div class="stats-card">
                            <div class="d-flex justify-content-between align-items-center">
                                <div>
                                    <h3 class="mb-0"><?php echo $stats['total_trains']; ?></h3>
                                    <p class="text-muted mb-0">Total Trains</p>
                                </div>
                                <i class="fas fa-train"></i>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="stats-card">
                            <div class="d-flex justify-content-between align-items-center">
                                <div>
                                    <h3 class="mb-0"><?php echo $stats['active_trains']; ?></h3>
                                    <p class="text-muted mb-0">Active Trains</p>
                                </div>
                                <i class="fas fa-check-circle"></i>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="stats-card">
                            <div class="d-flex justify-content-between align-items-center">
                                <div>
                                    <h3 class="mb-0"><?php echo $stats['delayed_trains']; ?></h3>
                                    <p class="text-muted mb-0">Delayed Trains</p>
                                </div>
                                <i class="fas fa-clock"></i>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="stats-card">
                            <div class="d-flex justify-content-between align-items-center">
                                <div>
                                    <h3 class="mb-0"><?php echo $stats['cancelled_trains']; ?></h3>
                                    <p class="text-muted mb-0">Cancelled Trains</p>
                                </div>
                                <i class="fas fa-times-circle"></i>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Trains Table -->
                <div class="card">
                    <div class="card-body p-0">
                        <div class="table-responsive">
                            <table class="table table-hover mb-0">
                                <thead>
                                    <tr>
                                        <th>Train Number</th>
                                        <th>Name</th>
                                        <th>Route</th>
                                        <th>Time</th>
                                        <th>Seats</th>
                                        <th>Fare</th>
                                        <th>Status</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach($trains as $train): ?>
                                    <tr>
                                        <td><?php echo htmlspecialchars($train['train_number']); ?></td>
                                        <td><?php echo htmlspecialchars($train['train_name']); ?></td>
                                        <td>
                                            <div>
                                                <?php echo htmlspecialchars($train['source_station']); ?>
                                                <i class="fas fa-arrow-right mx-2"></i>
                                                <?php echo htmlspecialchars($train['destination_station']); ?>
                                            </div>
                                        </td>
                                        <td>
                                            <div>
                                                <?php echo date('h:i A', strtotime($train['departure_time'])); ?>
                                                <small class="text-muted d-block">to</small>
                                                <?php echo date('h:i A', strtotime($train['arrival_time'])); ?>
                                            </div>
                                        </td>
                                        <td>
                                            <div>
                                                Available: <?php echo $train['available_seats']; ?>
                                                <small class="text-muted d-block">Total: <?php echo $train['total_seats']; ?></small>
                                            </div>
                                        </td>
                                        <td>₹<?php echo $train['min_fare'] ? number_format($train['min_fare'], 2) : '0.00'; ?></td>
                                        <td>
                                            <span class="status-badge status-<?php echo $train['status']; ?>">
                                                <?php echo $train['status']; ?>
                                            </span>
                                        </td>
                                        <td>
                                            <button class="btn btn-sm btn-primary me-1" onclick="editTrain(<?php echo htmlspecialchars(json_encode($train)); ?>)">
                                                <i class="fas fa-edit"></i>
                                            </button>
                                            <button class="btn btn-sm btn-danger" onclick="deleteTrain(<?php echo $train['id']; ?>)">
                                                <i class="fas fa-trash"></i>
                                            </button>
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

    <!-- Add Train Modal -->
    <div class="modal fade" id="addTrainModal" tabindex="-1">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header bg-primary text-white">
                    <h5 class="modal-title"><i class="fas fa-train me-2"></i>Add New Train</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <form method="POST" id="addTrainForm" onsubmit="return validateForm()">
                        <input type="hidden" name="action" value="add">
                        
                        <div class="row mb-3">
                            <div class="col-md-6">
                                <label class="form-label">Train Number</label>
                                <input type="text" class="form-control" name="train_number" required 
                                       pattern="[0-9]+" title="Please enter a valid train number">
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">Train Name</label>
                                <input type="text" class="form-control" name="train_name" required>
                            </div>
                        </div>

                        <div class="row mb-3">
                            <div class="col-md-12">
                                <label class="form-label">Route Name</label>
                                <input type="text" class="form-control" name="route_name" required 
                                       placeholder="e.g. Solapur-Pune Express Route">
                            </div>
                        </div>

                        <div class="row mb-3">
                            <div class="col-md-6">
                                <label class="form-label">Source Station</label>
                                <input type="text" class="form-control" name="source_station" required>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">Source Platform</label>
                                <input type="text" class="form-control" name="source_platform" required 
                                       placeholder="Platform number">
                            </div>
                        </div>

                        <div class="row mb-3">
                            <div class="col-md-6">
                                <label class="form-label">Destination Station</label>
                                <input type="text" class="form-control" name="destination_station" required>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">Destination Platform</label>
                                <input type="text" class="form-control" name="destination_platform" required 
                                       placeholder="Platform number">
                            </div>
                        </div>

                        <div class="row mb-3">
                            <div class="col-md-6">
                                <label class="form-label">Departure Time (24-hour format)</label>
                                <input type="time" class="form-control" name="departure_time" required>
                                <small class="text-muted">Daily departure time from source station</small>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">Arrival Time (24-hour format)</label>
                                <input type="time" class="form-control" name="arrival_time" required>
                                <small class="text-muted">Daily arrival time at destination station</small>
                            </div>
                        </div>

                        <div class="row mb-3">
                            <div class="col-md-6">
                                <label class="form-label">Total Seats</label>
                                <div class="input-group">
                                    <input type="number" class="form-control" name="total_seats" required min="1" 
                                           placeholder="Enter total seats">
                                    <span class="input-group-text"><i class="fas fa-chair"></i></span>
                                </div>
                            </div>
                        </div>

                        <!-- Source Station Pricing -->
                        <div class="card mb-3">
                            <div class="card-header bg-light">
                                <h6 class="mb-0">Source Station to First Station</h6>
                            </div>
                            <div class="card-body">
                                <div class="row">
                                    <div class="col-md-6">
                                        <label class="form-label">Base Fare (₹)</label>
                                        <input type="number" step="0.01" min="0" class="form-control" 
                                               name="source_base_fare" required>
                                    </div>
                                    <div class="col-md-6">
                                        <label class="form-label">Distance (km)</label>
                                        <input type="number" step="0.01" min="0" class="form-control" 
                                               name="source_distance" required>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Intermediate Stations -->
                        <div class="card mb-3">
                            <div class="card-header bg-light">
                                <div class="d-flex justify-content-between align-items-center">
                                    <h6 class="mb-0">Intermediate Stations</h6>
                                    <button type="button" class="btn btn-sm btn-primary" onclick="addStation()">
                                        <i class="fas fa-plus me-1"></i>Add Station
                                    </button>
                                </div>
                            </div>
                            <div class="card-body" id="intermediateStations">
                                <!-- Stations will be added here dynamically -->
                            </div>
                        </div>

                        <div class="text-end">
                            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                            <button type="submit" class="btn btn-primary">Add Train</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <!-- Edit Train Modal -->
    <div class="modal fade" id="editTrainModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Edit Train</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <form action="" method="POST">
                    <div class="modal-body">
                        <input type="hidden" name="action" value="edit">
                        <input type="hidden" name="train_id" id="edit_train_id">
                        <div class="mb-3">
                            <label class="form-label">Train Number</label>
                            <input type="text" class="form-control" name="train_number" id="edit_train_number" required>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Train Name</label>
                            <input type="text" class="form-control" name="train_name" id="edit_train_name" required>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Source Station</label>
                            <input type="text" class="form-control" name="source_station" id="edit_source_station" required>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Destination Station</label>
                            <input type="text" class="form-control" name="destination_station" id="edit_destination_station" required>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Departure Time</label>
                            <input type="time" class="form-control" name="departure_time" id="edit_departure_time" required>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Arrival Time</label>
                            <input type="time" class="form-control" name="arrival_time" id="edit_arrival_time" required>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Total Seats</label>
                            <input type="number" class="form-control" name="total_seats" id="edit_total_seats" required>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Status</label>
                            <select class="form-select" name="status" id="edit_status" required>
                                <option value="Active">Active</option>
                                <option value="Delayed">Delayed</option>
                                <option value="Cancelled">Cancelled</option>
                            </select>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-primary">Save Changes</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11.0.18/dist/sweetalert2.min.js"></script>
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

        // Edit train
        function editTrain(train) {
            $('#edit_train_id').val(train.id);
            $('#edit_train_number').val(train.train_number);
            $('#edit_train_name').val(train.train_name);
            $('#edit_source_station').val(train.source_station);
            $('#edit_destination_station').val(train.destination_station);
            $('#edit_departure_time').val(train.departure_time);
            $('#edit_arrival_time').val(train.arrival_time);
            $('#edit_total_seats').val(train.total_seats);
            $('#edit_status').val(train.status);
            $('#editTrainModal').modal('show');
        }

        // Delete train
        function deleteTrain(trainId) {
            Swal.fire({
                title: 'Are you sure?',
                text: "This action cannot be undone!",
                icon: 'warning',
                showCancelButton: true,
                confirmButtonColor: '#e74c3c',
                cancelButtonColor: '#95a5a6',
                confirmButtonText: 'Yes, delete it!'
            }).then((result) => {
                if (result.isConfirmed) {
                    const form = document.createElement('form');
                    form.method = 'POST';
                    form.innerHTML = `
                        <input type="hidden" name="action" value="delete">
                        <input type="hidden" name="train_id" value="${trainId}">
                    `;
                    document.body.appendChild(form);
                    form.submit();
                }
            });
        }

        function addStation() {
            const container = document.getElementById('intermediateStations');
            const stationCount = container.children.length;
            
            const stationHtml = `
                <div class="station-entry border-bottom pb-3 mb-3">
                    <div class="d-flex justify-content-between align-items-center mb-2">
                        <h6 class="mb-0">Stop ${stationCount + 1}</h6>
                        <button type="button" class="btn btn-sm btn-danger" onclick="this.parentElement.parentElement.remove()">
                            <i class="fas fa-times"></i>
                        </button>
                    </div>
                    <div class="row mb-3">
                        <div class="col-md-3">
                            <label class="form-label">Station Name</label>
                            <input type="text" class="form-control" name="intermediate_stations[]" required>
                        </div>
                        <div class="col-md-3">
                            <label class="form-label">Platform Number</label>
                            <input type="number" class="form-control" name="platform_numbers[]" required>
                        </div>
                        <div class="col-md-3">
                            <label class="form-label">Arrival Time</label>
                            <input type="time" class="form-control" name="arrival_times[]" required>
                        </div>
                        <div class="col-md-3">
                            <label class="form-label">Departure Time</label>
                            <input type="time" class="form-control" name="departure_times[]" required>
                        </div>
                    </div>
                    <div class="row">
                        <div class="col-md-6">
                            <label class="form-label">Base Fare (₹)</label>
                            <input type="number" step="0.01" min="0" class="form-control" 
                                   name="intermediate_base_fares[]" required>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Distance from Previous Station (km)</label>
                            <input type="number" step="0.01" min="0" class="form-control" 
                                   name="distances[]" required>
                        </div>
                    </div>
                </div>
            `;
            
            container.insertAdjacentHTML('beforeend', stationHtml);
        }

        function validateForm() {
            const departureTime = document.querySelector('input[name="departure_time"]').value;
            const arrivalTime = document.querySelector('input[name="arrival_time"]').value;
            const totalSeats = document.querySelector('input[name="total_seats"]').value;
            const sourceFare = document.querySelector('input[name="source_base_fare"]').value;
            const sourceDistance = document.querySelector('input[name="source_distance"]').value;

            if (!departureTime || !arrivalTime) {
                toastr.error('Please enter both departure and arrival times');
                return false;
            }

            if (parseInt(totalSeats) < 1) {
                toastr.error('Total seats must be at least 1');
                return false;
            }

            if (!sourceFare || parseFloat(sourceFare) < 0) {
                toastr.error('Please enter a valid base fare for source station');
                return false;
            }

            if (!sourceDistance || parseFloat(sourceDistance) < 0) {
                toastr.error('Please enter a valid distance for source station');
                return false;
            }

            return true;
        }
    </script>
</body>
</html> 