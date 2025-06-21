<?php
require_once('../includes/config.php');
require_once('../includes/auth.php');

// Ensure user is logged in
checkUserLogin();

$train_id = isset($_GET['train_id']) ? (int)$_GET['train_id'] : 0;
$error = '';
$stations = [];
$train = null;

// Get train details
if ($train_id) {
    $query = "SELECT t.*, ts1.station_name as source_station, ts2.station_name as destination_station 
              FROM trains t 
              JOIN train_stations ts1 ON t.id = ts1.train_id AND ts1.sequence = 1
              JOIN train_stations ts2 ON t.id = ts2.train_id AND ts2.sequence = (
                  SELECT MAX(sequence) FROM train_stations WHERE train_id = t.id
              )
              WHERE t.id = ? AND t.status = 'Active'";
    
    $stmt = $conn->prepare($query);
    $stmt->bind_param("i", $train_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $train = $result->fetch_assoc();

    // Get all stations for this train
    $query = "SELECT station_name, sequence FROM train_stations 
              WHERE train_id = ? ORDER BY sequence";
    $stmt = $conn->prepare($query);
    $stmt->bind_param("i", $train_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $stations = $result->fetch_all(MYSQLI_ASSOC);
}

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $from_station = $_POST['from_station'] ?? '';
    $to_station = $_POST['to_station'] ?? '';
    $journey_date = $_POST['journey_date'] ?? '';
    $passengers = $_POST['passengers'] ?? [];

    if (!$from_station || !$to_station || !$journey_date || empty($passengers)) {
        $error = 'Please fill all required fields';
    } else {
        // Calculate fare
        $stmt = $conn->prepare("SELECT base_fare FROM station_pricing 
                               WHERE train_id = ? AND 
                               ((from_station = ? AND to_station = ?) OR 
                                (from_station = ? AND to_station = ?))");
        $stmt->bind_param("issss", $train_id, $from_station, $to_station, $to_station, $from_station);
        $stmt->execute();
        $result = $stmt->get_result();
        $fare_result = $result->fetch_assoc();
        $base_fare = $fare_result['base_fare'] ?? 0;
        
        if ($base_fare <= 0) {
            $error = 'Could not calculate fare for the selected route';
        } else {
            $total_amount = $base_fare * count($passengers);
            
            // Generate PNR
            $pnr = 'PNR' . date('YmdHis') . rand(1000, 9999);
            
            // Start transaction
            $conn->begin_transaction();
            
            try {
                // Create booking
                $stmt = $conn->prepare("INSERT INTO bookings (user_id, train_id, from_station, to_station, booking_date, journey_date, pnr_number, total_amount, status) 
                                     VALUES (?, ?, ?, ?, CURDATE(), ?, ?, ?, 'Pending')");
                $user_id = $_SESSION['user_id'];
                $stmt->bind_param("iissssd", $user_id, $train_id, $from_station, $to_station, $journey_date, $pnr, $total_amount);
                $stmt->execute();
                $booking_id = $conn->insert_id;
                
                // Add passengers
                $stmt = $conn->prepare("INSERT INTO passengers (booking_id, name, age, gender) VALUES (?, ?, ?, ?)");
                foreach ($passengers as $passenger) {
                    $stmt->bind_param("isis", $booking_id, $passenger['name'], $passenger['age'], $passenger['gender']);
                    $stmt->execute();
                }
                
                $conn->commit();
                header("Location: booking_confirmation.php?pnr=" . urlencode($pnr));
                exit;
            } catch (Exception $e) {
                $conn->rollback();
                $error = 'Failed to create booking. Please try again.';
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
    <title>Book Ticket - Railway Reservation</title>
    <link rel="stylesheet" href="../css/bootstrap.min.css">
    <link rel="stylesheet" href="../css/style.css">
</head>
<body>
    <?php include('../includes/header.php'); ?>

    <div class="container mt-4">
        <h2>Book Train Ticket</h2>
        
        <?php if ($error): ?>
            <div class="alert alert-danger"><?php echo htmlspecialchars($error); ?></div>
        <?php endif; ?>

        <?php if ($train): ?>
            <div class="card mb-4">
                <div class="card-body">
                    <h5 class="card-title"><?php echo htmlspecialchars($train['name']); ?> (<?php echo htmlspecialchars($train['train_number']); ?>)</h5>
                    <p class="card-text">
                        From: <?php echo htmlspecialchars($train['source_station']); ?><br>
                        To: <?php echo htmlspecialchars($train['destination_station']); ?><br>
                        Departure: <?php echo date('H:i', strtotime($train['departure_time'])); ?><br>
                        Arrival: <?php echo date('H:i', strtotime($train['arrival_time'])); ?>
                    </p>
                </div>
        </div>

            <form method="POST" id="bookingForm">
                <div class="row mb-3">
                    <div class="col-md-4">
                        <label class="form-label">From Station</label>
                        <select name="from_station" class="form-select" required>
                            <option value="">Select Station</option>
                            <?php foreach ($stations as $station): ?>
                                <option value="<?php echo htmlspecialchars($station['station_name']); ?>">
                                    <?php echo htmlspecialchars($station['station_name']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-4">
                        <label class="form-label">To Station</label>
                        <select name="to_station" class="form-select" required>
                            <option value="">Select Station</option>
                            <?php foreach ($stations as $station): ?>
                                <option value="<?php echo htmlspecialchars($station['station_name']); ?>">
                                    <?php echo htmlspecialchars($station['station_name']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-4">
                        <label class="form-label">Journey Date</label>
                        <input type="date" name="journey_date" class="form-control" required 
                               min="<?php echo date('Y-m-d'); ?>" 
                               max="<?php echo date('Y-m-d', strtotime('+3 months')); ?>">
                    </div>
                </div>

                        <div id="passengersContainer">
                    <h4>Passenger Details</h4>
                    <div class="passenger-form mb-3">
                                <div class="row">
                                    <div class="col-md-4">
                                <label class="form-label">Name</label>
                                <input type="text" name="passengers[0][name]" class="form-control" required>
                                    </div>
                                    <div class="col-md-3">
                                <label class="form-label">Age</label>
                                <input type="number" name="passengers[0][age]" class="form-control" min="1" max="120" required>
                                    </div>
                                    <div class="col-md-3">
                                <label class="form-label">Gender</label>
                                <select name="passengers[0][gender]" class="form-select" required>
                                    <option value="">Select</option>
                                            <option value="Male">Male</option>
                                            <option value="Female">Female</option>
                                            <option value="Other">Other</option>
                                        </select>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="mb-3">
                    <button type="button" class="btn btn-secondary" onclick="addPassenger()">Add Passenger</button>
                </div>

                <button type="submit" class="btn btn-primary">Proceed to Payment</button>
            </form>
        <?php else: ?>
            <div class="alert alert-danger">Invalid train selected</div>
                <?php endif; ?>
            </div>

    <?php include('../includes/footer.php'); ?>
    <script src="../js/bootstrap.bundle.min.js"></script>
    <script>
        let passengerCount = 1;
        
        function addPassenger() {
            const container = document.getElementById('passengersContainer');
            const template = `
                <div class="passenger-form mb-3">
                    <div class="row">
            <div class="col-md-4">
                            <label class="form-label">Name</label>
                            <input type="text" name="passengers[${passengerCount}][name]" class="form-control" required>
                        </div>
                        <div class="col-md-3">
                            <label class="form-label">Age</label>
                            <input type="number" name="passengers[${passengerCount}][age]" class="form-control" min="1" max="120" required>
                        </div>
                        <div class="col-md-3">
                            <label class="form-label">Gender</label>
                            <select name="passengers[${passengerCount}][gender]" class="form-select" required>
                                <option value="">Select</option>
                                <option value="Male">Male</option>
                                <option value="Female">Female</option>
                                <option value="Other">Other</option>
                            </select>
                        </div>
                        <div class="col-md-2">
                            <label class="form-label">&nbsp;</label>
                            <button type="button" class="btn btn-danger form-control" onclick="this.closest('.passenger-form').remove()">Remove</button>
                        </div>
                    </div>
                </div>
            `;
            container.insertAdjacentHTML('beforeend', template);
            passengerCount++;
        }
    </script>
</body>
</html> 