<?php
session_start();
require_once "../includes/config.php";
require_once "../includes/admin_auth.php";

// Check if admin is logged in
requireAdminLogin();

// Handle vendor operations
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    if (isset($_POST['action'])) {
        switch ($_POST['action']) {
            case 'add_vendor':
                $sql = "INSERT INTO food_vendors (name, contact_number, station_name, status) VALUES (?, ?, ?, 'Active')";
                $stmt = $pdo->prepare($sql);
                $stmt->execute([$_POST['vendor_name'], $_POST['contact_number'], $_POST['address']]);
                showToast("Vendor added successfully!", "success");
                break;

            case 'update_vendor':
                $sql = "UPDATE food_vendors SET name = ?, contact_number = ?, station_name = ? WHERE id = ?";
                $stmt = $pdo->prepare($sql);
                $stmt->execute([$_POST['vendor_name'], $_POST['contact_number'], $_POST['address'], $_POST['vendor_id']]);
                showToast("Vendor updated successfully!", "success");
                break;

            case 'delete_vendor':
                // Check if vendor has any menu items
                $sql = "SELECT COUNT(*) FROM food_menu WHERE vendor_id = ?";
                $stmt = $pdo->prepare($sql);
                $stmt->execute([$_POST['vendor_id']]);
                $menuCount = $stmt->fetchColumn();

                if ($menuCount > 0) {
                    showToast("Cannot delete vendor with existing menu items!", "error");
                } else {
                    $sql = "DELETE FROM food_vendors WHERE id = ?";
                    $stmt = $pdo->prepare($sql);
                    $stmt->execute([$_POST['vendor_id']]);
                    showToast("Vendor deleted successfully!", "success");
                }
                break;

            case 'add_menu_item':
                $image_name = null;
                if (isset($_FILES['item_image']) && $_FILES['item_image']['error'] == 0) {
                    $target_dir = "../uploads/menu_items/";
                    if (!file_exists($target_dir)) {
                        mkdir($target_dir, 0777, true);
                    }
                    $file_extension = strtolower(pathinfo($_FILES['item_image']['name'], PATHINFO_EXTENSION));
                    $image_name = uniqid() . '.' . $file_extension;
                    $target_file = $target_dir . $image_name;
                    
                    // Check if image file is a actual image or fake image
                    $check = getimagesize($_FILES['item_image']['tmp_name']);
                    if($check !== false && in_array($file_extension, ['jpg', 'jpeg', 'png', 'gif'])) {
                        move_uploaded_file($_FILES['item_image']['tmp_name'], $target_file);
                    }
                }
                
                $sql = "INSERT INTO food_menu (vendor_id, item_name, description, price, image) VALUES (?, ?, ?, ?, ?)";
                $stmt = $pdo->prepare($sql);
                $stmt->execute([$_POST['vendor_id'], $_POST['item_name'], $_POST['description'], $_POST['price'], $image_name]);
                showToast("Menu item added successfully!", "success");
                break;

            case 'update_menu_item':
                $image_name = null;
                if (isset($_FILES['item_image']) && $_FILES['item_image']['error'] == 0) {
                    $target_dir = "../uploads/menu_items/";
                    if (!file_exists($target_dir)) {
                        mkdir($target_dir, 0777, true);
                    }
                    $file_extension = strtolower(pathinfo($_FILES['item_image']['name'], PATHINFO_EXTENSION));
                    $image_name = uniqid() . '.' . $file_extension;
                    $target_file = $target_dir . $image_name;
                    
                    // Check if image file is a actual image or fake image
                    $check = getimagesize($_FILES['item_image']['tmp_name']);
                    if($check !== false && in_array($file_extension, ['jpg', 'jpeg', 'png', 'gif'])) {
                        move_uploaded_file($_FILES['item_image']['tmp_name'], $target_file);
                        
                        // Update SQL to include image
                        $sql = "UPDATE food_menu SET item_name = ?, description = ?, price = ?, image = ? WHERE id = ?";
                        $stmt = $pdo->prepare($sql);
                        $stmt->execute([$_POST['item_name'], $_POST['description'], $_POST['price'], $image_name, $_POST['item_id']]);
                    }
                } else {
                    // Update without changing image
                    $sql = "UPDATE food_menu SET item_name = ?, description = ?, price = ? WHERE id = ?";
                    $stmt = $pdo->prepare($sql);
                    $stmt->execute([$_POST['item_name'], $_POST['description'], $_POST['price'], $_POST['item_id']]);
                }
                showToast("Menu item updated successfully!", "success");
                break;

            case 'delete_menu_item':
                // Check if menu item has any orders
                $sql = "SELECT COUNT(*) FROM food_orders WHERE menu_item_id = ?";
                $stmt = $pdo->prepare($sql);
                $stmt->execute([$_POST['item_id']]);
                $orderCount = $stmt->fetchColumn();

                if ($orderCount > 0) {
                    showToast("Cannot delete menu item with existing orders!", "error");
                } else {
                    $sql = "DELETE FROM food_menu WHERE id = ?";
                    $stmt = $pdo->prepare($sql);
                    $stmt->execute([$_POST['item_id']]);
                    showToast("Menu item deleted successfully!", "success");
                }
                break;
        }
    }
}

// Get vendor statistics
$sql = "SELECT 
        COUNT(*) as total_vendors,
        (SELECT COUNT(*) FROM food_menu) as total_menu_items,
        (SELECT COUNT(*) FROM food_orders WHERE DATE(created_at) = CURDATE()) as orders_today,
        (SELECT SUM(total_amount) FROM food_orders WHERE DATE(created_at) = CURDATE()) as revenue_today
        FROM food_vendors";
$stmt = $pdo->query($sql);
$stats = $stmt->fetch();

// Get all vendors with their menu items count
$sql = "SELECT v.*, 
        (SELECT COUNT(*) FROM food_menu m WHERE m.vendor_id = v.id) as menu_items_count,
        (SELECT COUNT(*) FROM food_orders fo 
         INNER JOIN food_menu fm ON fo.menu_item_id = fm.id 
         WHERE fm.vendor_id = v.id) as total_orders
        FROM food_vendors v 
        ORDER BY v.name";
$stmt = $pdo->prepare($sql);
$stmt->execute();
$vendors = $stmt->fetchAll();

// Get menu categories (for dropdown)
$categories = ['Breakfast', 'Lunch', 'Dinner', 'Snacks', 'Beverages', 'Desserts'];
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Food Vendors - RailYatra Admin</title>
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

        /* Reuse existing styles from users.php */
        body { font-family: 'Google Sans', Arial, sans-serif; background-color: var(--border-color); color: var(--text-color); }
        .sidebar { background: linear-gradient(135deg, var(--gradient-start), var(--gradient-end)); color: white; min-height: 100vh; }
        .nav-link { color: rgba(255,255,255,0.9); border-radius: 8px; margin: 4px 0; transition: all 0.3s ease; }
        .nav-link:hover, .nav-link.active { background: rgba(255,255,255,0.2); color: white; transform: translateX(5px); }
        .nav-link.active { background: white !important; color: var(--primary-color); }
        .card { border: none; border-radius: 12px; box-shadow: 0 2px 10px rgba(0,0,0,0.08); transition: transform 0.3s ease; }
        .card:hover { transform: translateY(-5px); }
        .stats-card { background: white; border-radius: 12px; padding: 1.5rem; margin-bottom: 1rem; }
        .stats-card i { font-size: 2rem; color: var(--primary-color); }
        .table { background: white; border-radius: 12px; overflow: hidden; }
        .table th { background: var(--primary-color); color: white; font-weight: 500; }

        /* Additional styles for food vendors page */
        .vendor-card {
            background: white;
            border-radius: 12px;
            margin-bottom: 1.5rem;
            overflow: hidden;
        }

        .vendor-header {
            background: linear-gradient(135deg, var(--gradient-start), var(--gradient-end));
            color: white;
            padding: 1.5rem;
        }

        .menu-item {
            border-left: 4px solid var(--primary-color);
            margin-bottom: 1rem;
            padding: 1rem;
            background: white;
            border-radius: 0 8px 8px 0;
            transition: all 0.3s ease;
        }

        .menu-item:hover {
            transform: translateX(5px);
            box-shadow: 0 2px 10px rgba(0,0,0,0.08);
        }

        .price-tag {
            background: var(--accent-color);
            color: white;
            padding: 4px 12px;
            border-radius: 20px;
            font-weight: 500;
        }

        .category-badge {
            background: var(--secondary-color);
            color: white;
            padding: 4px 12px;
            border-radius: 20px;
            font-size: 0.85rem;
        }

        .status-Available { background-color: #27ae60; }
        .status-Unavailable { background-color: #95a5a6; }
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
                            <a class="nav-link active" href="food_vendors.php">
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
                    <h2>Food Vendors</h2>
                    <button class="btn btn-primary" onclick="showAddVendorModal()">
                        <i class="fas fa-plus me-2"></i>Add New Vendor
                    </button>
                </div>

                <!-- Statistics Cards -->
                <div class="row mb-4">
                    <div class="col-md-3">
                        <div class="stats-card">
                            <div class="d-flex justify-content-between align-items-center">
                                <div>
                                    <h3 class="mb-0"><?php echo $stats['total_vendors']; ?></h3>
                                    <p class="text-muted mb-0">Total Vendors</p>
                                </div>
                                <i class="fas fa-store"></i>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="stats-card">
                            <div class="d-flex justify-content-between align-items-center">
                                <div>
                                    <h3 class="mb-0"><?php echo $stats['total_menu_items']; ?></h3>
                                    <p class="text-muted mb-0">Menu Items</p>
                                </div>
                                <i class="fas fa-utensils"></i>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="stats-card">
                            <div class="d-flex justify-content-between align-items-center">
                                <div>
                                    <h3 class="mb-0"><?php echo $stats['orders_today']; ?></h3>
                                    <p class="text-muted mb-0">Orders Today</p>
                                </div>
                                <i class="fas fa-shopping-cart"></i>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="stats-card">
                            <div class="d-flex justify-content-between align-items-center">
                                <div>
                                    <h3 class="mb-0">₹<?php echo number_format($stats['revenue_today'] ?? 0, 2); ?></h3>
                                    <p class="text-muted mb-0">Revenue Today</p>
                                </div>
                                <i class="fas fa-rupee-sign"></i>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Vendors List -->
                <?php foreach($vendors as $vendor): ?>
                <div class="vendor-card">
                    <div class="vendor-header">
                        <div class="d-flex justify-content-between align-items-center">
                            <div>
                                <h4 class="mb-1"><?php echo htmlspecialchars($vendor['name']); ?></h4>
                                <p class="mb-0">
                                    <i class="fas fa-phone me-2"></i><?php echo htmlspecialchars($vendor['contact_number']); ?>
                                </p>
                            </div>
                            <div>
                                <button class="btn btn-light btn-sm me-2" onclick="showEditVendorModal(<?php echo $vendor['id']; ?>)">
                                    <i class="fas fa-edit me-1"></i>Edit
                                </button>
                                <button class="btn btn-light btn-sm" onclick="deleteVendor(<?php echo $vendor['id']; ?>)">
                                    <i class="fas fa-trash me-1"></i>Delete
                                </button>
                            </div>
                        </div>
                        <div class="mt-3">
                            <small class="me-3">
                                <i class="fas fa-list me-1"></i><?php echo $vendor['menu_items_count']; ?> Menu Items
                            </small>
                            <small>
                                <i class="fas fa-shopping-cart me-1"></i><?php echo $vendor['total_orders']; ?> Total Orders
                            </small>
                        </div>
                    </div>
                    <div class="p-3">
                        <div class="d-flex justify-content-between align-items-center mb-3">
                            <h5 class="mb-0">Menu Items</h5>
                            <button class="btn btn-sm btn-outline-primary" onclick="showAddMenuItemModal(<?php echo $vendor['id']; ?>)">
                                <i class="fas fa-plus me-1"></i>Add Item
                            </button>
                        </div>
                        <?php
                        $sql = "SELECT * FROM food_menu WHERE vendor_id = ? ORDER BY item_name";
                        $stmt = $pdo->prepare($sql);
                        $stmt->execute([$vendor['id']]);
                        $menuItems = $stmt->fetchAll();
                        ?>
                        <?php foreach($menuItems as $item): ?>
                        <div class="menu-item">
                            <div class="d-flex justify-content-between align-items-center">
                                <div class="d-flex">
                                    <div>
                                        <h6 class="mb-1"><?php echo htmlspecialchars($item['item_name']); ?></h6>
                                        <p class="mb-2 text-muted"><?php echo htmlspecialchars($item['description']); ?></p>
                                    </div>
                                </div>
                                <div class="text-end">
                                    <div class="price-tag mb-2">₹<?php echo number_format($item['price'], 2); ?></div>
                                    <div>
                                        <button class="btn btn-sm btn-outline-primary me-1" onclick="showEditMenuItemModal(<?php echo $item['id']; ?>)">
                                            <i class="fas fa-edit"></i>
                                        </button>
                                        <button class="btn btn-sm btn-outline-danger" onclick="deleteMenuItem(<?php echo $item['id']; ?>)">
                                            <i class="fas fa-trash"></i>
                                        </button>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
        </div>
    </div>

    <!-- Add/Edit Vendor Modal -->
    <div class="modal fade" id="vendorModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="vendorModalTitle">Add New Vendor</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <form id="vendorForm" method="POST">
                    <div class="modal-body">
                        <input type="hidden" name="action" id="vendorAction" value="add_vendor">
                        <input type="hidden" name="vendor_id" id="vendorId">
                        <div class="mb-3">
                            <label class="form-label">Vendor Name</label>
                            <input type="text" class="form-control" name="vendor_name" required>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Contact Number</label>
                            <input type="tel" class="form-control" name="contact_number" required>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Station Name</label>
                            <input type="text" class="form-control" name="address" required>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-primary">Save</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Add/Edit Menu Item Modal -->
    <div class="modal fade" id="menuItemModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="menuItemModalTitle">Add Menu Item</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <form id="menuItemForm" method="POST" enctype="multipart/form-data">
                    <div class="modal-body">
                        <input type="hidden" name="action" id="menuItemAction" value="add_menu_item">
                        <input type="hidden" name="vendor_id" id="menuItemVendorId">
                        <input type="hidden" name="item_id" id="menuItemId">
                        <div class="mb-3">
                            <label class="form-label">Item Name</label>
                            <input type="text" class="form-control" name="item_name" required>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Description</label>
                            <textarea class="form-control" name="description" rows="3" required></textarea>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Price (₹)</label>
                            <input type="number" class="form-control" name="price" step="0.01" required>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Item Image</label>
                            <input type="file" class="form-control" name="item_image" accept="image/*">
                            <small class="text-muted">Supported formats: JPG, JPEG, PNG, GIF</small>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-primary">Save</button>
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

        // Vendor Modal Functions
        function showAddVendorModal() {
            $('#vendorModalTitle').text('Add New Vendor');
            $('#vendorAction').val('add_vendor');
            $('#vendorId').val('');
            $('#vendorForm')[0].reset();
            $('#vendorModal').modal('show');
        }

        function showEditVendorModal(vendorId) {
            $('#vendorModalTitle').text('Edit Vendor');
            $('#vendorAction').val('update_vendor');
            $('#vendorId').val(vendorId);
            // TODO: Fetch vendor details and populate form
            $('#vendorModal').modal('show');
        }

        function deleteVendor(vendorId) {
            Swal.fire({
                title: 'Delete Vendor',
                text: "This will also delete all menu items. This action cannot be undone!",
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
                        <input type="hidden" name="action" value="delete_vendor">
                        <input type="hidden" name="vendor_id" value="${vendorId}">
                    `;
                    document.body.appendChild(form);
                    form.submit();
                }
            });
        }

        // Menu Item Modal Functions
        function showAddMenuItemModal(vendorId) {
            $('#menuItemModalTitle').text('Add Menu Item');
            $('#menuItemAction').val('add_menu_item');
            $('#menuItemVendorId').val(vendorId);
            $('#menuItemId').val('');
            $('#menuItemForm')[0].reset();
            $('#menuItemModal').modal('show');
        }

        function showEditMenuItemModal(itemId) {
            $('#menuItemModalTitle').text('Edit Menu Item');
            $('#menuItemAction').val('update_menu_item');
            $('#menuItemId').val(itemId);
            // TODO: Fetch menu item details and populate form
            $('#menuItemModal').modal('show');
        }

        function deleteMenuItem(itemId) {
            Swal.fire({
                title: 'Delete Menu Item',
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
                        <input type="hidden" name="action" value="delete_menu_item">
                        <input type="hidden" name="item_id" value="${itemId}">
                    `;
                    document.body.appendChild(form);
                    form.submit();
                }
            });
        }
    </script>
</body>
</html> 