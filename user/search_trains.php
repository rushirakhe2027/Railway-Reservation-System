<?php
session_start();
require_once "../includes/config.php";
require_once "../includes/db.php";

// Enable error reporting
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Debug session information
error_log("Session data in search_trains.php: " . print_r($_SESSION, true));

// Verify user is logged in
if (!isset($_SESSION['user_id']) || !isset($_SESSION['logged_in']) || $_SESSION['logged_in'] !== true) {
    error_log("User not logged in from search_trains.php - redirecting to login");
    $_SESSION['redirect_after_login'] = $_SERVER['REQUEST_URI']; // Store the current URL
    $_SESSION['toast'] = [
        'type' => 'warning',
        'message' => 'Please login to continue'
    ];
    header("Location: ../login.php");
    exit;
}

// Get list of stations for autocomplete
try {
    $stmt = $pdo->query("SELECT DISTINCT source_station FROM trains UNION SELECT DISTINCT destination_station FROM trains");
    $stations = $stmt->fetchAll(PDO::FETCH_COLUMN);
} catch (PDOException $e) {
    $stations = [];
    error_log("Station fetch error: " . $e->getMessage());
}

// Fetch all active trains by default
$trains = [];
$error = null;
$search = '';

try {
    // Modified SQL query to prevent duplicate trains by using GROUP_CONCAT for stations
    $sql = "SELECT 
            t.id,
            t.train_number,
            t.train_name,
            t.source_station,
            t.destination_station,
            t.departure_time,
            t.arrival_time,
            t.status,
            GROUP_CONCAT(DISTINCT ts.station_name SEPARATOR '||') as station_names,
            MIN(sp.base_fare) as base_fare
            FROM trains t 
            LEFT JOIN train_stations ts ON t.id = ts.train_id
            LEFT JOIN station_pricing sp ON t.id = sp.train_id
            WHERE t.status = 'Active'";
    
    // If search parameter exists, add filtering conditions
    if (isset($_GET['search']) && !empty(trim($_GET['search']))) {
        $search = trim($_GET['search']);
        $sql .= " AND (t.source_station LIKE :search1 
                OR t.destination_station LIKE :search2 
                OR t.train_name LIKE :search3 
                OR t.train_number LIKE :search4
                OR ts.station_name LIKE :search5)";
    }
    
    // Complete the query with grouping and ordering
    $sql .= " GROUP BY t.id, t.train_number, t.train_name, t.source_station, 
            t.destination_station, t.departure_time, t.arrival_time, t.status
            ORDER BY t.departure_time";
    
    $stmt = $pdo->prepare($sql);
    
    // Bind parameters only if search is provided
    if (!empty($search)) {
        $searchParam = "%{$search}%";
        $stmt->bindValue(':search1', $searchParam, PDO::PARAM_STR);
        $stmt->bindValue(':search2', $searchParam, PDO::PARAM_STR);
        $stmt->bindValue(':search3', $searchParam, PDO::PARAM_STR);
        $stmt->bindValue(':search4', $searchParam, PDO::PARAM_STR);
        $stmt->bindValue(':search5', $searchParam, PDO::PARAM_STR);
    }
    
    $stmt->execute();
    $trains = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Display message if no trains found after search
    if (empty($trains) && !empty($search)) {
        $_SESSION['toast'] = [
            'type' => 'info',
            'message' => 'No trains found matching "' . htmlspecialchars($search) . '"'
        ];
    }
} catch (PDOException $e) {
    $error = $e->getMessage();
    error_log("Train fetch error: " . $error);
    $_SESSION['toast'] = [
        'type' => 'error',
        'message' => 'Database error: ' . $error
    ];
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Search Trains - RailYatra</title>
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

        .search-container {
            max-width: 800px;
            margin: 2rem auto;
        }

        .search-box {
            background: white;
            border-radius: 50px;
            padding: 1rem;
            box-shadow: 0 4px 20px rgba(0,0,0,0.1);
            margin-bottom: 2rem;
        }

        .search-input {
            border: none;
            outline: none;
            width: 100%;
            padding: 0.5rem 1rem;
            font-size: 1.1rem;
        }

        .search-input:focus {
            box-shadow: none;
        }

        .search-button {
            background: linear-gradient(135deg, var(--gradient-start), var(--gradient-end));
            color: white;
            border: none;
            border-radius: 50px;
            padding: 0.5rem 2rem;
            transition: all 0.3s ease;
        }

        .search-button:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 15px rgba(231, 76, 60, 0.3);
            color: white;
        }

        .train-card {
            background: white;
            border-radius: 12px;
            padding: 1.5rem;
            margin-bottom: 1rem;
            box-shadow: 0 2px 10px rgba(0,0,0,0.08);
            transition: transform 0.3s ease;
        }

        .train-card:hover {
            transform: translateY(-5px);
        }

        .station-badge {
            background: var(--border-color);
            color: var(--text-color);
            padding: 0.3rem 0.8rem;
            border-radius: 50px;
            font-size: 0.9rem;
            margin: 0.2rem;
            display: inline-block;
        }

        .suggestions {
            position: absolute;
            top: 100%;
            left: 0;
            right: 0;
            background: white;
            border-radius: 12px;
            box-shadow: 0 4px 20px rgba(0,0,0,0.1);
            max-height: 200px;
            overflow-y: auto;
            z-index: 1000;
            display: none;
        }

        .suggestion-item {
            padding: 0.5rem 1rem;
            cursor: pointer;
            transition: all 0.2s ease;
        }

        .suggestion-item:hover {
            background: var(--border-color);
        }
        
        .no-trains {
            background: white;
            border-radius: 12px;
            padding: 2rem;
            text-align: center;
            box-shadow: 0 2px 10px rgba(0,0,0,0.08);
        }
        
        .search-title {
            margin-bottom: 1.5rem;
            font-weight: 700;
            color: var(--primary-color);
        }
        
        /* Loading spinner */
        .spinner-container {
            display: none;
            text-align: center;
            padding: 2rem 0;
        }
        
        .spinner-border {
            color: var(--primary-color);
            width: 3rem;
            height: 3rem;
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
                            <a class="nav-link active" href="search_trains.php">
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
                <h2 class="mb-4">Search Trains</h2>

                <div class="search-container">
                    <form action="search_trains.php" method="GET" id="searchForm">
                        <div class="search-box d-flex">
                            <div class="flex-grow-1 position-relative">
                                <input type="text" name="search" class="search-input" id="searchInput" 
                                       placeholder="Search by train name, number or station..." 
                                       value="<?php echo htmlspecialchars($search); ?>" autocomplete="off">
                                <div class="suggestions" id="suggestions"></div>
                            </div>
                            <button type="submit" class="search-button ms-3">
                                <i class="fas fa-search me-2"></i>Search
                            </button>
                        </div>
                    </form>
                    
                    <?php if (!empty($search)): ?>
                    <h5 class="search-title">
                        <?php if(count($trains) > 0): ?>
                            Showing results for "<?php echo htmlspecialchars($search); ?>"
                        <?php else: ?>
                            No trains found for "<?php echo htmlspecialchars($search); ?>"
                        <?php endif; ?>
                    </h5>
                    <?php else: ?>
                    <h5 class="search-title">All Available Trains</h5>
                    <?php endif; ?>
                    
                    <!-- Loading spinner -->
                    <div class="spinner-container" id="loading">
                        <div class="spinner-border" role="status">
                            <span class="visually-hidden">Loading...</span>
                        </div>
                        <p class="mt-3">Searching for trains...</p>
                    </div>

                    <div id="searchResults">
                        <?php if (count($trains) > 0): ?>
                            <?php foreach ($trains as $train): ?>
                                <div class="train-card">
                                    <div class="row">
                                        <div class="col-md-9">
                                            <div class="d-flex justify-content-between align-items-start mb-3">
                                                <div>
                                                    <h4 class="mb-1"><?php echo htmlspecialchars($train['train_name']); ?></h4>
                                                    <p class="text-muted mb-0">#<?php echo htmlspecialchars($train['train_number']); ?></p>
                                                </div>
                                                <?php if($train['status'] === 'Active'): ?>
                                                    <span class="badge bg-success">Active</span>
                                                <?php elseif($train['status'] === 'Delayed'): ?>
                                                    <span class="badge bg-warning">Delayed</span>
                                                <?php else: ?>
                                                    <span class="badge bg-danger">Cancelled</span>
                                                <?php endif; ?>
                                            </div>
                                            
                                            <div class="row mb-3">
                                                <div class="col-md-5">
                                                    <div class="d-flex flex-column">
                                                        <span class="text-muted">From</span>
                                                        <span class="fw-bold"><?php echo htmlspecialchars($train['source_station']); ?></span>
                                                        <span><?php echo date('h:i A', strtotime($train['departure_time'])); ?></span>
                                                    </div>
                                                </div>
                                                <div class="col-md-2 text-center">
                                                    <i class="fas fa-arrow-right text-muted mt-4"></i>
                                                </div>
                                                <div class="col-md-5">
                                                    <div class="d-flex flex-column">
                                                        <span class="text-muted">To</span>
                                                        <span class="fw-bold"><?php echo htmlspecialchars($train['destination_station']); ?></span>
                                                        <span><?php echo date('h:i A', strtotime($train['arrival_time'])); ?></span>
                                                    </div>
                                                </div>
                                            </div>
                                            
                                            <?php if($train['station_names']): ?>
                                            <div>
                                                <span class="text-muted">Stops at:</span>
                                                <?php 
                                                // Split the station names and display each as a badge
                                                $stations = explode('||', $train['station_names']);
                                                // Remove source and destination stations from the list
                                                $stations = array_filter($stations, function($station) use ($train) {
                                                    return $station != $train['source_station'] && $station != $train['destination_station'];
                                                });
                                                
                                                foreach ($stations as $station): ?>
                                                    <span class="station-badge"><?php echo htmlspecialchars($station); ?></span>
                                                <?php endforeach; ?>
                                            </div>
                                            <?php endif; ?>
                                        </div>
                                        <div class="col-md-3 text-end">
                                            <div class="d-flex flex-column h-100 justify-content-between">
                                                <div>
                                                    <p class="mb-1">Base fare from:</p>
                                                    <h5 class="text-primary mb-3">
                                                        ₹<?php echo number_format($train['base_fare'] ?? 0, 2); ?>
                                                    </h5>
                                                </div>
                                                <a href="book_train.php?train_id=<?php echo $train['id']; ?>" class="btn btn-primary">
                                                    <i class="fas fa-ticket-alt me-2"></i>Book Now
                                                </a>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <div class="no-trains">
                                <i class="fas fa-train fa-3x mb-3 text-muted"></i>
                                <h4>No trains found</h4>
                                <p class="text-muted">Try searching with different keywords or stations.</p>
                            </div>
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
        // Search suggestions
        const searchInput = document.getElementById('searchInput');
        const suggestionsContainer = document.getElementById('suggestions');
        const stations = <?php echo json_encode($stations); ?>;
        
        // Form submission with loading indicator
        document.getElementById('searchForm').addEventListener('submit', function(e) {
            document.getElementById('loading').style.display = 'block';
            document.getElementById('searchResults').style.display = 'none';
        });

        searchInput.addEventListener('input', function() {
            const value = this.value.toLowerCase();
            
            if (value.length < 2) {
                suggestionsContainer.style.display = 'none';
                return;
            }
            
            const filteredStations = stations.filter(station => 
                station.toLowerCase().includes(value)
            );
            
            if (filteredStations.length > 0) {
                suggestionsContainer.innerHTML = '';
                filteredStations.forEach(station => {
                    const item = document.createElement('div');
                    item.className = 'suggestion-item';
                    item.textContent = station;
                    item.addEventListener('click', function() {
                        searchInput.value = station;
                        suggestionsContainer.style.display = 'none';
                        document.getElementById('searchForm').submit();
                    });
                    suggestionsContainer.appendChild(item);
                });
                suggestionsContainer.style.display = 'block';
            } else {
                suggestionsContainer.style.display = 'none';
            }
        });

        document.addEventListener('click', function(e) {
            if (e.target !== searchInput) {
                suggestionsContainer.style.display = 'none';
            }
        });
        
        // Toast notifications
        <?php if (isset($_SESSION['toast'])): ?>
        toastr.options = {
            closeButton: true,
            progressBar: true,
            positionClass: "toast-top-right",
            timeOut: 5000
        };
        
        toastr.<?php echo $_SESSION['toast']['type']; ?>(
            "<?php echo addslashes($_SESSION['toast']['message']); ?>"
        );
        <?php unset($_SESSION['toast']); endif; ?>
    </script>
</body>
</html> 