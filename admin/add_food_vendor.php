<?php
session_start();
require_once "../includes/config.php";

// Check if admin is logged in
if (!isset($_SESSION['admin_id'])) {
    header("Location: login.php");
    exit;
}

// Get all stations for dropdown
$sql = "SELECT DISTINCT station_name FROM train_stations ORDER BY station_name";
$stmt = $pdo->prepare($sql);
$stmt->execute();
$stations = $stmt->fetchAll(PDO::FETCH_COLUMN);

// Handle form submission
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['add_vendor'])) {
    try {
        // Validate input
        if (empty($_POST['name']) || empty($_POST['station_name']) || empty($_POST['contact_number'])) {
            throw new Exception('Please fill all required fields');
        }

        // Start transaction
        $pdo->beginTransaction();

        // Insert vendor
        $sql = "INSERT INTO food_vendors (name, station_name, description, contact_number, status) 
                VALUES (?, ?, ?, ?, ?)";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([
            $_POST['name'],
            $_POST['station_name'],
            $_POST['description'],
            $_POST['contact_number'],
            'Active'
        ]);

        $vendor_id = $pdo->lastInsertId();

        // Insert menu items
        if (!empty($_POST['item_name'])) {
            $sql = "INSERT INTO food_menu (vendor_id, item_name, description, price, is_available) 
                    VALUES (?, ?, ?, ?, ?)";
            $stmt = $pdo->prepare($sql);

            foreach ($_POST['item_name'] as $key => $item_name) {
                if (!empty($item_name) && isset($_POST['item_price'][$key])) {
                    $stmt->execute([
                        $vendor_id,
                        $item_name,
                        $_POST['item_description'][$key],
                        $_POST['item_price'][$key],
                        1
                    ]);
                }
            }
        }

        $pdo->commit();
        $_SESSION['toast'] = ['type' => 'success', 'message' => 'Food vendor added successfully'];
        header("Location: manage_food_vendors.php");
        exit;

    } catch (Exception $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        $_SESSION['toast'] = ['type' => 'error', 'message' => 'Failed to add food vendor: ' . $e->getMessage()];
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Add Food Vendor - RailYatra Admin</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/toastr.js/latest/toastr.min.css">
    <style>
        .menu-item {
            background: #f8f9fa;
            padding: 15px;
            border-radius: 8px;
            margin-bottom: 15px;
        }
        .remove-item {
            color: #dc3545;
            cursor: pointer;
        }
        .remove-item:hover {
            color: #c82333;
        }
    </style>
</head>
<body>
    <div class="container py-4">
        <div class="row justify-content-center">
            <div class="col-lg-10">
                <div class="d-flex justify-content-between align-items-center mb-4">
                    <h2>Add Food Vendor</h2>
                    <a href="manage_food_vendors.php" class="btn btn-outline-secondary">
                        <i class="fas fa-arrow-left me-2"></i>Back to Vendors
                    </a>
                </div>

                <div class="card">
                    <div class="card-body">
                        <form method="POST" id="vendorForm" class="needs-validation" novalidate>
                            <!-- Vendor Details -->
                            <div class="mb-4">
                                <h4 class="card-title mb-3">Vendor Details</h4>
                                <div class="row g-3">
                                    <div class="col-md-6">
                                        <label class="form-label">Vendor Name *</label>
                                        <input type="text" class="form-control" name="name" required>
                                    </div>
                                    <div class="col-md-6">
                                        <label class="form-label">Station *</label>
                                        <select class="form-select" name="station_name" required>
                                            <option value="">Select Station</option>
                                            <?php foreach ($stations as $station): ?>
                                            <option value="<?php echo htmlspecialchars($station); ?>">
                                                <?php echo htmlspecialchars($station); ?>
                                            </option>
                                            <?php endforeach; ?>
                                        </select>
                                    </div>
                                    <div class="col-md-6">
                                        <label class="form-label">Contact Number *</label>
                                        <input type="tel" class="form-control" name="contact_number" 
                                               pattern="[0-9]{10}" title="Please enter a valid 10-digit number" required>
                                    </div>
                                    <div class="col-md-6">
                                        <label class="form-label">Description</label>
                                        <textarea class="form-control" name="description" rows="2"></textarea>
                                    </div>
                                </div>
                            </div>

                            <!-- Menu Items -->
                            <div class="mb-4">
                                <div class="d-flex justify-content-between align-items-center mb-3">
                                    <h4 class="card-title mb-0">Menu Items</h4>
                                    <button type="button" class="btn btn-sm btn-primary" onclick="addMenuItem()">
                                        <i class="fas fa-plus me-2"></i>Add Item
                                    </button>
                                </div>
                                <div id="menuItems">
                                    <div class="menu-item">
                                        <div class="row g-3">
                                            <div class="col-md-4">
                                                <label class="form-label">Item Name *</label>
                                                <input type="text" class="form-control" name="item_name[]" required>
                                            </div>
                                            <div class="col-md-3">
                                                <label class="form-label">Price *</label>
                                                <input type="number" class="form-control" name="item_price[]" 
                                                       min="0" step="0.01" required>
                                            </div>
                                            <div class="col-md-5">
                                                <label class="form-label">Description</label>
                                                <textarea class="form-control" name="item_description[]" rows="1"></textarea>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <div class="text-end">
                                <button type="submit" name="add_vendor" class="btn btn-primary">
                                    <i class="fas fa-save me-2"></i>Save Vendor
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
    <script src="https://cdnjs.cloudflare.com/ajax/libs/toastr.js/latest/toastr.min.js"></script>
    <script>
        // Initialize toastr
        toastr.options = {
            closeButton: true,
            progressBar: true,
            positionClass: "toast-top-right",
            timeOut: 5000
        };

        <?php if(isset($_SESSION['toast'])): ?>
        toastr.<?php echo $_SESSION['toast']['type']; ?>('<?php echo $_SESSION['toast']['message']; ?>');
        <?php unset($_SESSION['toast']); endif; ?>

        function addMenuItem() {
            const template = `
                <div class="menu-item">
                    <div class="d-flex justify-content-between mb-2">
                        <strong>Menu Item</strong>
                        <i class="fas fa-times remove-item" onclick="removeMenuItem(this)"></i>
                    </div>
                    <div class="row g-3">
                        <div class="col-md-4">
                            <label class="form-label">Item Name *</label>
                            <input type="text" class="form-control" name="item_name[]" required>
                        </div>
                        <div class="col-md-3">
                            <label class="form-label">Price *</label>
                            <input type="number" class="form-control" name="item_price[]" 
                                   min="0" step="0.01" required>
                        </div>
                        <div class="col-md-5">
                            <label class="form-label">Description</label>
                            <textarea class="form-control" name="item_description[]" rows="1"></textarea>
                        </div>
                    </div>
                </div>
            `;
            document.getElementById('menuItems').insertAdjacentHTML('beforeend', template);
        }

        function removeMenuItem(element) {
            element.closest('.menu-item').remove();
        }

        // Form validation
        document.getElementById('vendorForm').addEventListener('submit', function(event) {
            if (!this.checkValidity()) {
                event.preventDefault();
                event.stopPropagation();
            }
            this.classList.add('was-validated');
        });
    </script>
</body>
</html> 