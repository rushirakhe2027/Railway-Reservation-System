<?php
require_once('../includes/config.php');
require_once('../includes/auth.php');

// Ensure admin is logged in
checkAdminLogin();

$error = '';
$success = '';
$train_id = isset($_GET['train_id']) ? (int)$_GET['train_id'] : 0;

// Get all trains for dropdown
$query = "SELECT id, train_number, name FROM trains ORDER BY train_number";
$result = $conn->query($query);
$trains = $result->fetch_all(MYSQLI_ASSOC);

// Handle pricing save
if (isset($_POST['save_pricing'])) {
    $train_id = (int)$_POST['train_id'];
    $from_stations = $_POST['from_station'] ?? [];
    $to_stations = $_POST['to_station'] ?? [];
    $base_fares = $_POST['base_fare'] ?? [];

    $conn->begin_transaction();
    try {
        // Delete existing pricing for this train
        $stmt = $conn->prepare("DELETE FROM station_pricing WHERE train_id = ?");
        $stmt->bind_param("i", $train_id);
        $stmt->execute();

        // Add new pricing
        $stmt = $conn->prepare("INSERT INTO station_pricing (train_id, from_station, to_station, base_fare) VALUES (?, ?, ?, ?)");
        foreach ($from_stations as $index => $from_station) {
            if (!empty($from_station) && !empty($to_stations[$index]) && isset($base_fares[$index])) {
                $fare = (float)$base_fares[$index];
                $stmt->bind_param("issd", $train_id, $from_station, $to_stations[$index], $fare);
                $stmt->execute();
            }
        }

        $conn->commit();
        $success = "Pricing updated successfully";
    } catch (Exception $e) {
        $conn->rollback();
        $error = "Failed to update pricing. Please check all fields and try again.";
    }
}

// Get train details and stations if train is selected
$train = null;
$stations = [];
$pricing = [];

if ($train_id) {
    // Get train details
    $query = "SELECT * FROM trains WHERE id = ?";
    $stmt = $conn->prepare($query);
    $stmt->bind_param("i", $train_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $train = $result->fetch_assoc();

    if ($train) {
        // Get stations for this train
        $query = "SELECT station_name FROM train_stations WHERE train_id = ? ORDER BY sequence";
        $stmt = $conn->prepare($query);
        $stmt->bind_param("i", $train_id);
        $stmt->execute();
        $result = $stmt->get_result();
        $stations = $result->fetch_all(MYSQLI_ASSOC);

        // Get existing pricing
        $query = "SELECT * FROM station_pricing WHERE train_id = ?";
        $stmt = $conn->prepare($query);
        $stmt->bind_param("i", $train_id);
        $stmt->execute();
        $result = $stmt->get_result();
        $pricing = $result->fetch_all(MYSQLI_ASSOC);
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Pricing - Railway Reservation</title>
    <link rel="stylesheet" href="../css/bootstrap.min.css">
    <link rel="stylesheet" href="../css/style.css">
</head>
<body>
    <?php include('includes/header.php'); ?>

    <div class="container mt-4">
        <h2>Manage Station-wise Pricing</h2>

        <?php if ($error): ?>
            <div class="alert alert-danger"><?php echo htmlspecialchars($error); ?></div>
        <?php endif; ?>

        <?php if ($success): ?>
            <div class="alert alert-success"><?php echo htmlspecialchars($success); ?></div>
        <?php endif; ?>

        <form method="GET" class="mb-4">
            <div class="row">
                <div class="col-md-6">
                    <label class="form-label">Select Train</label>
                    <select name="train_id" class="form-select" onchange="this.form.submit()">
                        <option value="">Select Train</option>
                        <?php foreach ($trains as $t): ?>
                            <option value="<?php echo $t['id']; ?>" <?php echo $train_id == $t['id'] ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($t['train_number'] . ' - ' . $t['name']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>
        </form>

        <?php if ($train && !empty($stations)): ?>
            <form method="POST" id="pricingForm">
                <input type="hidden" name="train_id" value="<?php echo $train_id; ?>">
                
                <div class="card mb-4">
                    <div class="card-body">
                        <h5 class="card-title"><?php echo htmlspecialchars($train['name']); ?> (<?php echo htmlspecialchars($train['train_number']); ?>)</h5>
                        <p class="text-muted">Set pricing between stations</p>

                        <div id="pricingContainer">
                            <?php if (!empty($pricing)): ?>
                                <?php foreach ($pricing as $price): ?>
                                    <div class="pricing-row mb-3">
                                        <div class="row">
                                            <div class="col-md-4">
                                                <label class="form-label">From Station</label>
                                                <select name="from_station[]" class="form-select" required>
                                                    <?php foreach ($stations as $station): ?>
                                                        <option value="<?php echo htmlspecialchars($station['station_name']); ?>"
                                                                <?php echo $price['from_station'] == $station['station_name'] ? 'selected' : ''; ?>>
                                                            <?php echo htmlspecialchars($station['station_name']); ?>
                                                        </option>
                                                    <?php endforeach; ?>
                                                </select>
                                            </div>
                                            <div class="col-md-4">
                                                <label class="form-label">To Station</label>
                                                <select name="to_station[]" class="form-select" required>
                                                    <?php foreach ($stations as $station): ?>
                                                        <option value="<?php echo htmlspecialchars($station['station_name']); ?>"
                                                                <?php echo $price['to_station'] == $station['station_name'] ? 'selected' : ''; ?>>
                                                            <?php echo htmlspecialchars($station['station_name']); ?>
                                                        </option>
                                                    <?php endforeach; ?>
                                                </select>
                                            </div>
                                            <div class="col-md-3">
                                                <label class="form-label">Base Fare (₹)</label>
                                                <input type="number" name="base_fare[]" class="form-control" 
                                                       value="<?php echo htmlspecialchars($price['base_fare']); ?>" 
                                                       min="0" step="0.01" required>
                                            </div>
                                            <div class="col-md-1">
                                                <label class="form-label">&nbsp;</label>
                                                <button type="button" class="btn btn-danger form-control" 
                                                        onclick="this.closest('.pricing-row').remove()">×</button>
                                            </div>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <div class="pricing-row mb-3">
                                    <div class="row">
                                        <div class="col-md-4">
                                            <label class="form-label">From Station</label>
                                            <select name="from_station[]" class="form-select" required>
                                                <?php foreach ($stations as $station): ?>
                                                    <option value="<?php echo htmlspecialchars($station['station_name']); ?>">
                                                        <?php echo htmlspecialchars($station['station_name']); ?>
                                                    </option>
                                                <?php endforeach; ?>
                                            </select>
                                        </div>
                                        <div class="col-md-4">
                                            <label class="form-label">To Station</label>
                                            <select name="to_station[]" class="form-select" required>
                                                <?php foreach ($stations as $station): ?>
                                                    <option value="<?php echo htmlspecialchars($station['station_name']); ?>">
                                                        <?php echo htmlspecialchars($station['station_name']); ?>
                                                    </option>
                                                <?php endforeach; ?>
                                            </select>
                                        </div>
                                        <div class="col-md-3">
                                            <label class="form-label">Base Fare (₹)</label>
                                            <input type="number" name="base_fare[]" class="form-control" min="0" step="0.01" required>
                                        </div>
                                        <div class="col-md-1">
                                            <label class="form-label">&nbsp;</label>
                                            <button type="button" class="btn btn-danger form-control" 
                                                    onclick="this.closest('.pricing-row').remove()">×</button>
                                        </div>
                                    </div>
                                </div>
                            <?php endif; ?>
                        </div>

                        <div class="mb-3">
                            <button type="button" class="btn btn-secondary" onclick="addPricingRow()">Add Price</button>
                        </div>

                        <button type="submit" name="save_pricing" class="btn btn-primary">Save Pricing</button>
                    </div>
                </div>
            </form>
        <?php elseif ($train_id): ?>
            <div class="alert alert-warning">No stations found for this train. Please add stations first.</div>
        <?php endif; ?>
    </div>

    <?php include('includes/footer.php'); ?>
    <script src="../js/bootstrap.bundle.min.js"></script>
    <script>
        function addPricingRow() {
            const container = document.getElementById('pricingContainer');
            const template = container.querySelector('.pricing-row').cloneNode(true);
            
            // Clear values
            template.querySelectorAll('select').forEach(select => select.selectedIndex = 0);
            template.querySelector('input[type="number"]').value = '';
            
            container.appendChild(template);
        }
    </script>
</body>
</html> 