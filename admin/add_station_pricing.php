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

// Get list of trains
$trains_query = "SELECT id, train_number, train_name FROM trains ORDER BY train_number";
$trains = $pdo->query($trains_query)->fetchAll();

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    try {
        $pdo->beginTransaction();

        $train_id = $_POST['train_id'];
        $from_station = $_POST['from_station'];
        $to_station = $_POST['to_station'];
        $distance = $_POST['distance'];
        $first_ac_fare = $_POST['first_ac_fare'];
        $second_ac_fare = $_POST['second_ac_fare'];
        $third_ac_fare = $_POST['third_ac_fare'];
        $sleeper_fare = $_POST['sleeper_fare'];
        $general_fare = $_POST['general_fare'];

        // Insert station pricing
        $sql = "INSERT INTO station_pricing (train_id, from_station, to_station, distance, first_ac_fare, second_ac_fare, third_ac_fare, sleeper_fare, general_fare) 
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([
            $train_id,
            $from_station,
            $to_station,
            $distance,
            $first_ac_fare,
            $second_ac_fare,
            $third_ac_fare,
            $sleeper_fare,
            $general_fare
        ]);

        $pdo->commit();
        $success_message = "Station pricing added successfully!";
    } catch (Exception $e) {
        $pdo->rollBack();
        $error_message = "Error: " . $e->getMessage();
    }
}

// Get existing station pricing records
$pricing_query = "SELECT sp.*, t.train_number, t.train_name 
                 FROM station_pricing sp 
                 JOIN trains t ON sp.train_id = t.id 
                 ORDER BY t.train_number, sp.from_station";
$pricing_records = $pdo->query($pricing_query)->fetchAll();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Add Station Pricing - RailYatra</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        .fare-card {
            background: #f8f9fa;
            padding: 15px;
            border-radius: 8px;
            margin-bottom: 15px;
        }
        .fare-card h5 {
            color: #0d6efd;
            margin-bottom: 15px;
        }
    </style>
</head>
<body class="bg-light">
    <div class="container py-5">
        <div class="row justify-content-center">
            <div class="col-md-10">
                <div class="card shadow">
                    <div class="card-header bg-primary text-white">
                        <h3 class="card-title mb-0">Add Station Pricing</h3>
                    </div>
                    <div class="card-body">
                        <?php if ($success_message): ?>
                            <div class="alert alert-success"><?php echo $success_message; ?></div>
                        <?php endif; ?>
                        <?php if ($error_message): ?>
                            <div class="alert alert-danger"><?php echo $error_message; ?></div>
                        <?php endif; ?>

                        <form method="POST">
                            <div class="row mb-3">
                                <div class="col-md-12">
                                    <label class="form-label">Select Train</label>
                                    <select name="train_id" class="form-select" required>
                                        <option value="">Choose train...</option>
                                        <?php foreach ($trains as $train): ?>
                                            <option value="<?php echo $train['id']; ?>">
                                                <?php echo $train['train_number'] . ' - ' . $train['train_name']; ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                            </div>

                            <div class="row mb-3">
                                <div class="col-md-6">
                                    <label class="form-label">From Station</label>
                                    <input type="text" name="from_station" class="form-control" required>
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label">To Station</label>
                                    <input type="text" name="to_station" class="form-control" required>
                                </div>
                            </div>

                            <div class="row mb-3">
                                <div class="col-md-12">
                                    <label class="form-label">Distance (km)</label>
                                    <input type="number" step="0.01" name="distance" class="form-control" required>
                                </div>
                            </div>

                            <div class="fare-card">
                                <h5>Class-wise Fares</h5>
                                <div class="row">
                                    <div class="col-md-4 mb-3">
                                        <label class="form-label">First AC Fare (₹)</label>
                                        <div class="input-group">
                                            <span class="input-group-text">₹</span>
                                            <input type="number" step="0.01" name="first_ac_fare" class="form-control" required>
                                        </div>
                                    </div>
                                    <div class="col-md-4 mb-3">
                                        <label class="form-label">Second AC Fare (₹)</label>
                                        <div class="input-group">
                                            <span class="input-group-text">₹</span>
                                            <input type="number" step="0.01" name="second_ac_fare" class="form-control" required>
                                        </div>
                                    </div>
                                    <div class="col-md-4 mb-3">
                                        <label class="form-label">Third AC Fare (₹)</label>
                                        <div class="input-group">
                                            <span class="input-group-text">₹</span>
                                            <input type="number" step="0.01" name="third_ac_fare" class="form-control" required>
                                        </div>
                                    </div>
                                </div>
                                <div class="row">
                                    <div class="col-md-6 mb-3">
                                        <label class="form-label">Sleeper Fare (₹)</label>
                                        <div class="input-group">
                                            <span class="input-group-text">₹</span>
                                            <input type="number" step="0.01" name="sleeper_fare" class="form-control" required>
                                        </div>
                                    </div>
                                    <div class="col-md-6 mb-3">
                                        <label class="form-label">General Fare (₹)</label>
                                        <div class="input-group">
                                            <span class="input-group-text">₹</span>
                                            <input type="number" step="0.01" name="general_fare" class="form-control" required>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <div class="text-end">
                                <button type="submit" class="btn btn-primary">
                                    <i class="fas fa-save"></i> Add Pricing
                                </button>
                            </div>
                        </form>

                        <hr>

                        <h4 class="mt-4">Existing Station Pricing Records</h4>
                        <div class="table-responsive">
                            <table class="table table-striped">
                                <thead>
                                    <tr>
                                        <th>Train</th>
                                        <th>From Station</th>
                                        <th>To Station</th>
                                        <th>Distance (km)</th>
                                        <th>First AC (₹)</th>
                                        <th>Second AC (₹)</th>
                                        <th>Third AC (₹)</th>
                                        <th>Sleeper (₹)</th>
                                        <th>General (₹)</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($pricing_records as $record): ?>
                                        <tr>
                                            <td><?php echo $record['train_number'] . ' - ' . $record['train_name']; ?></td>
                                            <td><?php echo $record['from_station']; ?></td>
                                            <td><?php echo $record['to_station']; ?></td>
                                            <td><?php echo number_format($record['distance'], 2); ?></td>
                                            <td>₹<?php echo number_format($record['first_ac_fare'], 2); ?></td>
                                            <td>₹<?php echo number_format($record['second_ac_fare'], 2); ?></td>
                                            <td>₹<?php echo number_format($record['third_ac_fare'], 2); ?></td>
                                            <td>₹<?php echo number_format($record['sleeper_fare'], 2); ?></td>
                                            <td>₹<?php echo number_format($record['general_fare'], 2); ?></td>
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

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html> 