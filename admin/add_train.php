<?php
session_start();
require_once "../includes/config.php";

// Check if admin is logged in
if (!isset($_SESSION['admin_id'])) {
    header("Location: login.php");
    exit;
}

$success_message = '';
$error_message = '';

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    try {
        $pdo->beginTransaction();

        // Insert train details
        $sql = "INSERT INTO trains (train_number, train_name, source_station, destination_station, departure_time, arrival_time, total_seats, status) 
                VALUES (?, ?, ?, ?, ?, ?, ?, ?)";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([
            $_POST['train_number'],
            $_POST['train_name'],
            $_POST['stations'][0], // First station is source
            $_POST['stations'][count($_POST['stations'])-1], // Last station is destination
            $_POST['departure_times'][0], // Source departure time
            $_POST['arrival_times'][count($_POST['stations'])-1], // Destination arrival time
            $_POST['total_seats'],
            'Active'
        ]);
        
        $train_id = $pdo->lastInsertId();

        // Insert train stations
        $stations = $_POST['stations'];
        $arrival_times = $_POST['arrival_times'];
        $departure_times = $_POST['departure_times'];
        $platform_numbers = $_POST['platform_numbers'];

        for ($i = 0; $i < count($stations); $i++) {
            $sql = "INSERT INTO train_stations (train_id, station_name, arrival_time, departure_time, stop_number, platform_number) 
                    VALUES (?, ?, ?, ?, ?, ?)";
            $stmt = $pdo->prepare($sql);
            $stmt->execute([
                $train_id,
                $stations[$i],
                $arrival_times[$i],
                $departure_times[$i],
                $i + 1,
                $platform_numbers[$i]
            ]);
        }

        // Insert station pricing
        for ($i = 0; $i < count($stations); $i++) {
            for ($j = $i + 1; $j < count($stations); $j++) {
                $base_key = "price_{$i}_{$j}";
                $distance_key = "distance_{$i}_{$j}";
                
                if (isset($_POST[$distance_key])) {
                    $sql = "INSERT INTO station_pricing (
                        train_id, from_station, to_station, distance,
                        general_fare, sleeper_fare, third_ac_fare, second_ac_fare, first_ac_fare
                    ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)";
                    
                    $stmt = $pdo->prepare($sql);
                    $stmt->execute([
                        $train_id,
                        $stations[$i],
                        $stations[$j],
                        $_POST[$distance_key],
                        $_POST["{$base_key}_general"],
                        $_POST["{$base_key}_sleeper"],
                        $_POST["{$base_key}_third_ac"],
                        $_POST["{$base_key}_second_ac"],
                        $_POST["{$base_key}_first_ac"]
                    ]);
                }
            }
        }

        $pdo->commit();
        $success_message = "Train added successfully!";
    } catch (Exception $e) {
        $pdo->rollBack();
        $error_message = "Error: " . $e->getMessage();
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Add Train - RailYatra</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        .station-row {
            background: #f8f9fa;
            padding: 15px;
            margin-bottom: 10px;
            border-radius: 8px;
        }
        .pricing-matrix {
            max-height: 500px;
            overflow-y: auto;
        }
        .pricing-row {
            background: #fff;
            padding: 15px;
            margin-bottom: 15px;
            border-radius: 8px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.05);
        }
        .pricing-row:hover {
            box-shadow: 0 4px 8px rgba(0,0,0,0.1);
        }
        .class-prices {
            margin-top: 10px;
            padding-top: 10px;
            border-top: 1px solid #dee2e6;
        }
        .route-label {
            font-weight: 600;
            color: #2c3e50;
            margin-bottom: 10px;
        }
        .price-input-group {
            margin-bottom: 10px;
        }
    </style>
</head>
<body class="bg-light">
    <div class="container py-5">
        <div class="row justify-content-center">
            <div class="col-md-10">
                <div class="card shadow">
                    <div class="card-header bg-primary text-white">
                        <h3 class="card-title mb-0">Add New Train</h3>
                    </div>
                    <div class="card-body">
                        <?php if ($success_message): ?>
                            <div class="alert alert-success"><?php echo $success_message; ?></div>
                        <?php endif; ?>
                        <?php if ($error_message): ?>
                            <div class="alert alert-danger"><?php echo $error_message; ?></div>
                        <?php endif; ?>

                        <form method="POST" id="addTrainForm">
                            <!-- Basic Train Details -->
                            <div class="row mb-4">
                                <div class="col-md-6">
                                    <div class="mb-3">
                                        <label class="form-label">Train Number</label>
                                        <input type="text" class="form-control" name="train_number" required>
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="mb-3">
                                        <label class="form-label">Train Name</label>
                                        <input type="text" class="form-control" name="train_name" required>
                                    </div>
                                </div>
                            </div>

                            <div class="row mb-4">
                                <div class="col-md-12">
                                    <div class="mb-3">
                                        <label class="form-label">Total Seats</label>
                                        <input type="number" class="form-control" name="total_seats" required>
                                    </div>
                                </div>
                            </div>

                            <!-- Stations Section -->
                            <h4 class="mb-3">Stations and Timings</h4>
                            <div id="stationsContainer">
                                <div class="station-row">
                                    <div class="row">
                                        <div class="col-md-3">
                                            <div class="mb-3">
                                                <label class="form-label">Station Name</label>
                                                <input type="text" class="form-control station-name" name="stations[]" required>
                                            </div>
                                        </div>
                                        <div class="col-md-3">
                                            <div class="mb-3">
                                                <label class="form-label">Arrival Time</label>
                                                <input type="time" class="form-control" name="arrival_times[]" required>
                                            </div>
                                        </div>
                                        <div class="col-md-3">
                                            <div class="mb-3">
                                                <label class="form-label">Departure Time</label>
                                                <input type="time" class="form-control" name="departure_times[]" required>
                                            </div>
                                        </div>
                                        <div class="col-md-2">
                                            <div class="mb-3">
                                                <label class="form-label">Platform</label>
                                                <input type="text" class="form-control" name="platform_numbers[]" required>
                                            </div>
                                        </div>
                                        <div class="col-md-1">
                                            <div class="mb-3">
                                                <label class="form-label">&nbsp;</label>
                                                <button type="button" class="btn btn-danger btn-sm d-block" onclick="removeStation(this)">
                                                    <i class="fas fa-times"></i>
                                                </button>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <div class="mb-4">
                                <button type="button" class="btn btn-secondary" onclick="addStation()">
                                    <i class="fas fa-plus"></i> Add Station
                                </button>
                            </div>

                            <!-- Pricing Matrix -->
                            <h4 class="mb-3">Station-wise Pricing</h4>
                            <div class="pricing-matrix" id="pricingMatrix">
                                <!-- Will be populated dynamically -->
                            </div>

                            <div class="text-end mt-4">
                                <button type="submit" class="btn btn-primary">
                                    <i class="fas fa-save"></i> Save Train
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        function addStation() {
            const container = document.getElementById('stationsContainer');
            const newStation = document.querySelector('.station-row').cloneNode(true);
            
            // Clear input values
            newStation.querySelectorAll('input').forEach(input => input.value = '');
            
            container.appendChild(newStation);
            updatePricingMatrix();
        }

        function removeStation(button) {
            const stations = document.querySelectorAll('.station-row');
            if (stations.length > 1) {
                button.closest('.station-row').remove();
                updatePricingMatrix();
            }
        }

        function updatePricingMatrix() {
            const stations = Array.from(document.querySelectorAll('.station-name')).map(input => input.value).filter(Boolean);
            const matrix = document.getElementById('pricingMatrix');
            matrix.innerHTML = '';

            for (let i = 0; i < stations.length; i++) {
                for (let j = i + 1; j < stations.length; j++) {
                    const row = document.createElement('div');
                    row.className = 'pricing-row';
                    row.innerHTML = `
                        <div class="route-label">
                            <i class="fas fa-route"></i> ${stations[i]} → ${stations[j]}
                        </div>
                        <div class="row mb-3">
                            <div class="col-md-4">
                                <div class="input-group">
                                    <span class="input-group-text">Distance</span>
                                    <input type="number" step="0.01" class="form-control" 
                                           name="distance_${i}_${j}" placeholder="Distance (km)" required>
                                    <span class="input-group-text">km</span>
                                </div>
                            </div>
                        </div>
                        <div class="class-prices">
                            <div class="row">
                                <div class="col-md-4">
                                    <div class="price-input-group">
                                        <div class="input-group">
                                            <span class="input-group-text">First AC</span>
                                            <span class="input-group-text">₹</span>
                                            <input type="number" step="0.01" class="form-control" 
                                                   name="price_${i}_${j}_first_ac" placeholder="1AC Fare" required>
                                        </div>
                                    </div>
                                </div>
                                <div class="col-md-4">
                                    <div class="price-input-group">
                                        <div class="input-group">
                                            <span class="input-group-text">Second AC</span>
                                            <span class="input-group-text">₹</span>
                                            <input type="number" step="0.01" class="form-control" 
                                                   name="price_${i}_${j}_second_ac" placeholder="2AC Fare" required>
                                        </div>
                                    </div>
                                </div>
                                <div class="col-md-4">
                                    <div class="price-input-group">
                                        <div class="input-group">
                                            <span class="input-group-text">Third AC</span>
                                            <span class="input-group-text">₹</span>
                                            <input type="number" step="0.01" class="form-control" 
                                                   name="price_${i}_${j}_third_ac" placeholder="3AC Fare" required>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            <div class="row">
                                <div class="col-md-6">
                                    <div class="price-input-group">
                                        <div class="input-group">
                                            <span class="input-group-text">Sleeper</span>
                                            <span class="input-group-text">₹</span>
                                            <input type="number" step="0.01" class="form-control" 
                                                   name="price_${i}_${j}_sleeper" placeholder="Sleeper Fare" required>
                                        </div>
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="price-input-group">
                                        <div class="input-group">
                                            <span class="input-group-text">General</span>
                                            <span class="input-group-text">₹</span>
                                            <input type="number" step="0.01" class="form-control" 
                                                   name="price_${i}_${j}_general" placeholder="General Fare" required>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    `;
                    matrix.appendChild(row);
                }
            }
        }

        // Initialize pricing matrix when stations are added/changed
        document.addEventListener('input', function(e) {
            if (e.target.classList.contains('station-name')) {
                updatePricingMatrix();
            }
        });

        // Initial pricing matrix update
        updatePricingMatrix();
    </script>
</body>
</html> 