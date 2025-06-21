<?php
session_start();
require_once "../includes/config.php";
require_once "../includes/admin_auth.php";

// Check if admin is logged in
requireAdminLogin();

// Get date range from query parameters
$start_date = isset($_GET['start_date']) ? $_GET['start_date'] : date('Y-m-d', strtotime('-30 days'));
$end_date = isset($_GET['end_date']) ? $_GET['end_date'] : date('Y-m-d');

// Get overall statistics
$sql = "SELECT 
        (SELECT COUNT(*) FROM users) as total_users,
        (SELECT COUNT(*) FROM trains) as total_trains,
        (SELECT COUNT(*) FROM bookings) as total_bookings,
        (SELECT COUNT(*) FROM food_vendors) as total_vendors,
        (SELECT SUM(total_amount) FROM bookings) as total_revenue";
$stmt = $pdo->query($sql);
$overall_stats = $stmt->fetch();

// Get booking statistics for the selected date range
$sql = "SELECT 
        COUNT(*) as bookings_count,
        SUM(total_amount) as revenue,
        COUNT(CASE WHEN status = 'Confirmed' THEN 1 END) as confirmed_bookings,
        COUNT(CASE WHEN status = 'Cancelled' THEN 1 END) as cancelled_bookings,
        COUNT(CASE WHEN status = 'Waiting' THEN 1 END) as waiting_bookings
        FROM bookings 
        WHERE DATE(created_at) BETWEEN ? AND ?";
$stmt = $pdo->prepare($sql);
$stmt->execute([$start_date, $end_date]);
$booking_stats = $stmt->fetch();

// Get daily booking counts for chart
$sql = "SELECT DATE(created_at) as date, COUNT(*) as count, SUM(total_amount) as revenue
        FROM bookings 
        WHERE DATE(created_at) BETWEEN ? AND ?
        GROUP BY DATE(created_at)
        ORDER BY date";
$stmt = $pdo->prepare($sql);
$stmt->execute([$start_date, $end_date]);
$daily_stats = $stmt->fetchAll();

// Get top 5 trains by bookings
$sql = "SELECT t.train_name, t.train_number, 
        COUNT(b.id) as booking_count,
        SUM(b.total_amount) as revenue
        FROM trains t
        LEFT JOIN bookings b ON b.train_id = t.id
        WHERE DATE(b.created_at) BETWEEN ? AND ?
        GROUP BY t.id
        ORDER BY booking_count DESC
        LIMIT 5";
$stmt = $pdo->prepare($sql);
$stmt->execute([$start_date, $end_date]);
$top_trains = $stmt->fetchAll();

// Get top 5 routes by revenue
$sql = "SELECT t.source_station, t.destination_station,
        COUNT(b.id) as booking_count,
        SUM(b.total_amount) as revenue
        FROM trains t
        LEFT JOIN bookings b ON b.train_id = t.id
        WHERE DATE(b.created_at) BETWEEN ? AND ?
        GROUP BY t.source_station, t.destination_station
        ORDER BY revenue DESC
        LIMIT 5";
$stmt = $pdo->prepare($sql);
$stmt->execute([$start_date, $end_date]);
$top_routes = $stmt->fetchAll();

// Get food order statistics
$sql = "SELECT 
        COUNT(*) as orders_count,
        SUM(total_amount) as revenue,
        COUNT(DISTINCT menu_item_id) as unique_items
        FROM food_orders
        WHERE DATE(created_at) BETWEEN ? AND ?";
$stmt = $pdo->prepare($sql);
$stmt->execute([$start_date, $end_date]);
$food_stats = $stmt->fetch();

// Get top 5 food items
$sql = "SELECT m.item_name, v.name as vendor_name,
        COUNT(o.id) as order_count,
        SUM(o.total_amount) as revenue
        FROM food_menu m
        JOIN food_vendors v ON m.vendor_id = v.id
        LEFT JOIN food_orders o ON o.menu_item_id = m.id
        WHERE DATE(o.created_at) BETWEEN ? AND ?
        GROUP BY m.id
        ORDER BY order_count DESC
        LIMIT 5";
$stmt = $pdo->prepare($sql);
$stmt->execute([$start_date, $end_date]);
$top_food_items = $stmt->fetchAll();

// Get booking status counts
$status_sql = "SELECT 
    status,
    COUNT(*) as count
FROM bookings
GROUP BY status";

$stmt = $pdo->query($status_sql);
$status_counts = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Format status data for the pie chart
$status_data = [
    'Confirmed' => 0,
    'Waiting' => 0,
    'Cancelled' => 0
];

foreach ($status_counts as $status) {
    if ($status['status'] === 'Approved') {
        $status_data['Confirmed'] = $status['count'];
    } elseif ($status['status'] === 'Pending') {
        $status_data['Waiting'] = $status['count'];
    } elseif ($status['status'] === 'Cancelled') {
        $status_data['Cancelled'] = $status['count'];
    }
}

// Get booking trends (last 7 days)
$trends_sql = "SELECT 
    DATE(created_at) as booking_date,
    COUNT(*) as total_bookings,
    SUM(total_amount) as daily_revenue
FROM bookings
WHERE created_at >= DATE_SUB(CURDATE(), INTERVAL 7 DAY)
GROUP BY DATE(created_at)
ORDER BY booking_date";

$stmt = $pdo->query($trends_sql);
$trends_data = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Reports - RailYatra Admin</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/flatpickr/dist/flatpickr.min.css">
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

        /* Reuse existing styles */
        body { font-family: 'Google Sans', Arial, sans-serif; background-color: var(--border-color); color: var(--text-color); }
        .sidebar { background: linear-gradient(135deg, var(--gradient-start), var(--gradient-end)); color: white; min-height: 100vh; }
        .nav-link { color: rgba(255,255,255,0.9); border-radius: 8px; margin: 4px 0; transition: all 0.3s ease; }
        .nav-link:hover, .nav-link.active { background: rgba(255,255,255,0.2); color: white; transform: translateX(5px); }
        .nav-link.active { background: white !important; color: var(--primary-color); }
        .card { border: none; border-radius: 12px; box-shadow: 0 2px 10px rgba(0,0,0,0.08); transition: transform 0.3s ease; }
        .card:hover { transform: translateY(-5px); }

        /* Additional styles for reports page */
        .stats-card {
            background: white;
            border-radius: 12px;
            padding: 1.5rem;
            margin-bottom: 1rem;
            position: relative;
            overflow: hidden;
        }

        .stats-card::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 4px;
            background: linear-gradient(to right, var(--gradient-start), var(--gradient-end));
        }

        .stats-icon {
            width: 48px;
            height: 48px;
            border-radius: 12px;
            background: linear-gradient(135deg, var(--gradient-start), var(--gradient-end));
            color: white;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.5rem;
            margin-bottom: 1rem;
        }

        .chart-card {
            background: white;
            border-radius: 12px;
            padding: 1.5rem;
            margin-bottom: 1.5rem;
        }

        .date-range-picker {
            border: 1px solid var(--border-color);
            border-radius: 8px;
            padding: 8px 16px;
            cursor: pointer;
        }

        .progress-bar {
            background: linear-gradient(to right, var(--gradient-start), var(--gradient-end));
        }

        .top-item {
            border-left: 4px solid var(--primary-color);
            padding: 1rem;
            margin-bottom: 1rem;
            background: white;
            border-radius: 0 8px 8px 0;
            transition: all 0.3s ease;
        }

        .top-item:hover {
            transform: translateX(5px);
            box-shadow: 0 2px 10px rgba(0,0,0,0.08);
        }

        .revenue-badge {
            background: var(--accent-color);
            color: white;
            padding: 4px 12px;
            border-radius: 20px;
            font-weight: 500;
        }

        .status-legend {
            display: flex;
            justify-content: center;
            gap: 20px;
            margin-top: 20px;
        }

        .legend-item {
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .legend-color {
            width: 12px;
            height: 12px;
            border-radius: 3px;
        }
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
                            <a class="nav-link active" href="reports.php">
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
                    <h2>Reports & Analytics</h2>
                    <div class="date-range-picker" id="dateRange">
                        <i class="fas fa-calendar me-2"></i>
                        <span><?php echo date('M d, Y', strtotime($start_date)); ?> - <?php echo date('M d, Y', strtotime($end_date)); ?></span>
                    </div>
                </div>

                <!-- Overall Statistics -->
                <div class="row mb-4">
                    <div class="col-md-3">
                        <div class="stats-card">
                            <div class="stats-icon">
                                <i class="fas fa-users"></i>
                            </div>
                            <h3 class="mb-1"><?php echo number_format($overall_stats['total_users']); ?></h3>
                            <p class="text-muted mb-0">Total Users</p>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="stats-card">
                            <div class="stats-icon">
                                <i class="fas fa-train"></i>
                            </div>
                            <h3 class="mb-1"><?php echo number_format($overall_stats['total_trains']); ?></h3>
                            <p class="text-muted mb-0">Total Trains</p>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="stats-card">
                            <div class="stats-icon">
                                <i class="fas fa-ticket-alt"></i>
                            </div>
                            <h3 class="mb-1"><?php echo number_format($overall_stats['total_bookings']); ?></h3>
                            <p class="text-muted mb-0">Total Bookings</p>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="stats-card">
                            <div class="stats-icon">
                                <i class="fas fa-rupee-sign"></i>
                            </div>
                            <h3 class="mb-1">₹<?php echo number_format($overall_stats['total_revenue'], 2); ?></h3>
                            <p class="text-muted mb-0">Total Revenue</p>
                        </div>
                    </div>
                </div>

                <!-- Booking Statistics -->
                <div class="row mb-4">
                    <div class="col-md-8">
                        <div class="chart-card">
                            <h5 class="mb-4">Booking Trends</h5>
                            <canvas id="bookingTrendsChart"></canvas>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="chart-card">
                            <h5 class="mb-4">Booking Status</h5>
                            <canvas id="bookingStatusChart"></canvas>
                            <div class="status-legend">
                                <div class="legend-item">
                                    <div class="legend-color bg-success"></div>
                                    <span>Confirmed (<?php echo $status_data['Confirmed']; ?>)</span>
                                </div>
                                <div class="legend-item">
                                    <div class="legend-color bg-warning"></div>
                                    <span>Waiting (<?php echo $status_data['Waiting']; ?>)</span>
                                </div>
                                <div class="legend-item">
                                    <div class="legend-color bg-danger"></div>
                                    <span>Cancelled (<?php echo $status_data['Cancelled']; ?>)</span>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Top Trains and Routes -->
                <div class="row mb-4">
                    <div class="col-md-6">
                        <div class="chart-card">
                            <h5 class="mb-4">Top Trains by Bookings</h5>
                            <?php foreach($top_trains as $train): ?>
                            <div class="top-item">
                                <div class="d-flex justify-content-between align-items-center">
                                    <div>
                                        <h6 class="mb-1"><?php echo htmlspecialchars($train['train_name']); ?></h6>
                                        <small class="text-muted"><?php echo $train['train_number']; ?></small>
                                    </div>
                                    <div class="text-end">
                                        <div class="revenue-badge mb-1">₹<?php echo number_format($train['revenue'], 2); ?></div>
                                        <small class="text-muted"><?php echo number_format($train['booking_count']); ?> bookings</small>
                                    </div>
                                </div>
                            </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="chart-card">
                            <h5 class="mb-4">Top Routes by Revenue</h5>
                            <?php foreach($top_routes as $route): ?>
                            <div class="top-item">
                                <div class="d-flex justify-content-between align-items-center">
                                    <div>
                                        <h6 class="mb-1"><?php echo htmlspecialchars($route['source_station']); ?> → <?php echo htmlspecialchars($route['destination_station']); ?></h6>
                                        <small class="text-muted"><?php echo number_format($route['booking_count']); ?> bookings</small>
                                    </div>
                                    <div class="revenue-badge">
                                        ₹<?php echo number_format($route['revenue'], 2); ?>
                                    </div>
                                </div>
                            </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                </div>

                <!-- Food Order Statistics -->
                <div class="row">
                    <div class="col-md-4">
                        <div class="stats-card">
                            <div class="stats-icon">
                                <i class="fas fa-utensils"></i>
                            </div>
                            <h3 class="mb-1"><?php echo number_format($food_stats['orders_count']); ?></h3>
                            <p class="text-muted mb-0">Food Orders</p>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="stats-card">
                            <div class="stats-icon">
                                <i class="fas fa-rupee-sign"></i>
                            </div>
                            <h3 class="mb-1">₹<?php echo number_format($food_stats['revenue'], 2); ?></h3>
                            <p class="text-muted mb-0">Food Revenue</p>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="stats-card">
                            <div class="stats-icon">
                                <i class="fas fa-hamburger"></i>
                            </div>
                            <h3 class="mb-1"><?php echo number_format($food_stats['unique_items']); ?></h3>
                            <p class="text-muted mb-0">Unique Items Ordered</p>
                        </div>
                    </div>
                </div>

                <!-- Top Food Items -->
                <div class="chart-card mt-4">
                    <h5 class="mb-4">Top Food Items</h5>
                    <?php foreach($top_food_items as $item): ?>
                    <div class="top-item">
                        <div class="d-flex justify-content-between align-items-center">
                            <div>
                                <h6 class="mb-1"><?php echo htmlspecialchars($item['item_name']); ?></h6>
                                <small class="text-muted"><?php echo htmlspecialchars($item['vendor_name']); ?></small>
                            </div>
                            <div class="text-end">
                                <div class="revenue-badge mb-1">₹<?php echo number_format($item['revenue'], 2); ?></div>
                                <small class="text-muted"><?php echo number_format($item['order_count']); ?> orders</small>
                            </div>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>
    </div>

    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/flatpickr"></script>
    <script>
        // Initialize date range picker
        flatpickr("#dateRange", {
            mode: "range",
            dateFormat: "Y-m-d",
            defaultDate: ["<?php echo $start_date; ?>", "<?php echo $end_date; ?>"],
            onChange: function(selectedDates) {
                if (selectedDates.length === 2) {
                    window.location.href = `reports.php?start_date=${selectedDates[0].toISOString().split('T')[0]}&end_date=${selectedDates[1].toISOString().split('T')[0]}`;
                }
            }
        });

        // Prepare data for booking trends chart
        const dates = <?php echo json_encode(array_column($daily_stats, 'date')); ?>;
        const bookingCounts = <?php echo json_encode(array_column($daily_stats, 'count')); ?>;
        const revenues = <?php echo json_encode(array_column($daily_stats, 'revenue')); ?>;

        // Initialize booking trends chart
        new Chart(document.getElementById('bookingTrendsChart'), {
            type: 'line',
            data: {
                labels: dates,
                datasets: [{
                    label: 'Bookings',
                    data: bookingCounts,
                    borderColor: '#e74c3c',
                    tension: 0.4,
                    fill: false
                }, {
                    label: 'Revenue (₹)',
                    data: revenues,
                    borderColor: '#f39c12',
                    tension: 0.4,
                    fill: false,
                    yAxisID: 'revenue'
                }]
            },
            options: {
                responsive: true,
                interaction: {
                    intersect: false,
                    mode: 'index'
                },
                scales: {
                    y: {
                        beginAtZero: true,
                        title: {
                            display: true,
                            text: 'Number of Bookings'
                        }
                    },
                    revenue: {
                        position: 'right',
                        beginAtZero: true,
                        title: {
                            display: true,
                            text: 'Revenue (₹)'
                        }
                    }
                }
            }
        });

        // Initialize booking status chart
        new Chart(document.getElementById('bookingStatusChart'), {
            type: 'doughnut',
            data: {
                labels: ['Confirmed', 'Waiting', 'Cancelled'],
                datasets: [{
                    data: [
                        <?php echo $status_data['Confirmed']; ?>,
                        <?php echo $status_data['Waiting']; ?>,
                        <?php echo $status_data['Cancelled']; ?>
                    ],
                    backgroundColor: [
                        '#28a745', // Success
                        '#ffc107', // Warning
                        '#dc3545'  // Danger
                    ]
                }]
            },
            options: {
                responsive: true,
                plugins: {
                    legend: {
                        display: false
                    }
                }
            }
        });
    </script>
</body>
</html> 