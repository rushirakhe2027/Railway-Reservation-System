<?php
session_start();
require_once "../includes/config.php";
require_once "../includes/admin_auth.php";

// Check if admin is logged in
requireAdminLogin();

// Handle user operations
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    if (isset($_POST['action'])) {
        switch ($_POST['action']) {
            case 'update_status':
                $sql = "UPDATE users SET status = ? WHERE id = ?";
                $stmt = $pdo->prepare($sql);
                $stmt->execute([$_POST['status'], $_POST['user_id']]);
                showToast("User status updated successfully!", "success");
                break;

            case 'delete':
                // Check if user has any bookings
                $sql = "SELECT COUNT(*) FROM bookings WHERE user_id = ?";
                $stmt = $pdo->prepare($sql);
                $stmt->execute([$_POST['user_id']]);
                $bookingCount = $stmt->fetchColumn();

                if ($bookingCount > 0) {
                    showToast("Cannot delete user with existing bookings!", "error");
                } else {
                    $sql = "DELETE FROM users WHERE id = ?";
                    $stmt = $pdo->prepare($sql);
                    $stmt->execute([$_POST['user_id']]);
                    showToast("User deleted successfully!", "success");
                }
                break;
        }
    }
}

// Get user statistics
$sql = "SELECT 
        COUNT(*) as total_users,
        COUNT(CASE WHEN DATE(created_at) = CURDATE() THEN 1 END) as new_users
        FROM users";
$stmt = $pdo->query($sql);
$stats = $stmt->fetch();

// Get all users with their booking counts
$sql = "SELECT u.*, 
        (SELECT COUNT(*) FROM bookings b WHERE b.user_id = u.id) as booking_count,
        (SELECT SUM(total_amount) FROM bookings b WHERE b.user_id = u.id) as total_spent
        FROM users u 
        ORDER BY u.created_at DESC";
$stmt = $pdo->query($sql);
$users = $stmt->fetchAll();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Users - RailYatra Admin</title>
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

        .status-badge {
            padding: 6px 12px;
            border-radius: 20px;
            font-size: 0.85rem;
            font-weight: 500;
        }

        .status-Active { background-color: #27ae60; color: white; }
        .status-Inactive { background-color: #95a5a6; color: white; }

        .user-avatar {
            width: 40px;
            height: 40px;
            border-radius: 50%;
            background: var(--primary-color);
            color: white;
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: 500;
            margin-right: 10px;
        }

        .search-box {
            position: relative;
            max-width: 300px;
        }

        .search-box input {
            padding-left: 40px;
            border-radius: 20px;
        }

        .search-box i {
            position: absolute;
            left: 15px;
            top: 50%;
            transform: translateY(-50%);
            color: var(--light-text);
        }

        .btn-action {
            width: 32px;
            height: 32px;
            padding: 0;
            display: flex;
            align-items: center;
            justify-content: center;
            border-radius: 6px;
            background: white;
            border: 1px solid #dee2e6;
            color: var(--text-color);
            transition: all 0.2s ease;
        }

        .btn-action:hover {
            background-color: #f8f9fa;
            border-color: #ced4da;
            transform: translateY(-1px);
            box-shadow: 0 2px 4px rgba(0,0,0,0.05);
        }

        .btn-action.text-danger:hover {
            background-color: #fff5f5;
            border-color: #dc3545;
            color: #dc3545;
        }

        .btn-action:focus {
            box-shadow: none;
        }

        .btn-action i {
            font-size: 14px;
        }

        .d-flex.gap-2 {
            gap: 0.5rem !important;
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
                            <a class="nav-link active" href="users.php">
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
                    <h2>Manage Users</h2>
                    <div class="search-box">
                        <i class="fas fa-search"></i>
                        <input type="text" class="form-control" id="searchInput" placeholder="Search users...">
                    </div>
                </div>

                <!-- Statistics Cards -->
                <div class="row mb-4">
                    <div class="col-md-6">
                        <div class="stats-card">
                            <div class="d-flex justify-content-between align-items-center">
                                <div>
                                    <h3 class="mb-0"><?php echo $stats['total_users']; ?></h3>
                                    <p class="text-muted mb-0">Total Users</p>
                                </div>
                                <i class="fas fa-users"></i>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="stats-card">
                            <div class="d-flex justify-content-between align-items-center">
                                <div>
                                    <h3 class="mb-0"><?php echo $stats['new_users']; ?></h3>
                                    <p class="text-muted mb-0">New Today</p>
                                </div>
                                <i class="fas fa-user-plus"></i>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Users Table -->
                <div class="card">
                    <div class="card-body p-0">
                        <div class="table-responsive">
                            <table class="table table-hover mb-0">
                                <thead>
                                    <tr>
                                        <th>User</th>
                                        <th>Contact</th>
                                        <th>Bookings</th>
                                        <th>Total Spent</th>
                                        <th>Joined</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach($users as $user): ?>
                                    <tr>
                                        <td>
                                            <div class="d-flex align-items-center">
                                                <div class="user-avatar">
                                                    <?php echo strtoupper(substr($user['name'], 0, 1)); ?>
                                                </div>
                                                <div>
                                                    <strong><?php echo htmlspecialchars($user['name']); ?></strong>
                                                    <small class="d-block text-muted">ID: <?php echo $user['id']; ?></small>
                                                </div>
                                            </div>
                                        </td>
                                        <td>
                                            <div>
                                                <?php echo htmlspecialchars($user['email']); ?>
                                                <?php if($user['phone']): ?>
                                                    <small class="d-block text-muted"><?php echo htmlspecialchars($user['phone']); ?></small>
                                                <?php endif; ?>
                                            </div>
                                        </td>
                                        <td>
                                            <div>
                                                <?php echo $user['booking_count']; ?> bookings
                                                <div class="progress" style="height: 4px; width: 100px;">
                                                    <div class="progress-bar" style="width: <?php echo min(($user['booking_count'] / 10) * 100, 100); ?>%"></div>
                                                </div>
                                            </div>
                                        </td>
                                        <td>₹<?php echo number_format($user['total_spent'] ?? 0, 2); ?></td>
                                        <td>
                                            <div>
                                                <?php echo date('d M Y', strtotime($user['created_at'])); ?>
                                                <small class="d-block text-muted">
                                                    <?php echo date('h:i A', strtotime($user['created_at'])); ?>
                                                </small>
                                            </div>
                                        </td>
                                        <td>
                                            <div class="d-flex gap-2">
                                                <button onclick="viewUser(<?php echo $user['id']; ?>)" 
                                                   class="btn btn-light btn-sm btn-action" 
                                                   title="View Details">
                                                    <i class="fas fa-eye"></i>
                                                </button>
                                                <button onclick="editUser(<?php echo $user['id']; ?>)" 
                                                   class="btn btn-light btn-sm btn-action"
                                                   title="Edit User">
                                                    <i class="fas fa-edit"></i>
                                                </button>
                                                <?php if ($user['booking_count'] == 0): ?>
                                                <button onclick="deleteUser(<?php echo $user['id']; ?>)" 
                                                        class="btn btn-light btn-sm btn-action text-danger"
                                                        title="Delete User">
                                                    <i class="fas fa-trash"></i>
                                                </button>
                                                <?php endif; ?>
                                            </div>
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

    <!-- View User Modal -->
    <div class="modal fade" id="viewUserModal" tabindex="-1">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">User Details</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body" id="viewUserContent">
                    <!-- Content will be loaded dynamically -->
                </div>
            </div>
        </div>
    </div>

    <!-- Edit User Modal -->
    <div class="modal fade" id="editUserModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Edit User</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <form id="editUserForm" method="POST">
                        <input type="hidden" id="edit_user_id" name="user_id">
                        <div class="mb-3">
                            <label for="edit_name" class="form-label">Full Name</label>
                            <input type="text" class="form-control" id="edit_name" name="name" required>
                        </div>
                        <div class="mb-3">
                            <label for="edit_email" class="form-label">Email Address</label>
                            <input type="email" class="form-control" id="edit_email" name="email" required>
                        </div>
                        <div class="mb-3">
                            <label for="edit_phone" class="form-label">Phone Number</label>
                            <input type="tel" class="form-control" id="edit_phone" name="phone">
                        </div>
                        <div class="mb-3">
                            <label for="edit_status" class="form-label">Account Status</label>
                            <select class="form-select" id="edit_status" name="status">
                                <option value="Active">Active</option>
                                <option value="Inactive">Inactive</option>
                                <option value="Suspended">Suspended</option>
                            </select>
                        </div>
                    </form>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-light" data-bs-dismiss="modal">Cancel</button>
                    <button type="button" class="btn btn-primary" onclick="saveUserChanges()">Save Changes</button>
                </div>
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

        // Search functionality
        document.getElementById('searchInput').addEventListener('keyup', function() {
            const searchText = this.value.toLowerCase();
            const tableRows = document.querySelectorAll('tbody tr');
            
            tableRows.forEach(row => {
                const text = row.textContent.toLowerCase();
                row.style.display = text.includes(searchText) ? '' : 'none';
            });
        });

        // View user details
        function viewUser(userId) {
            $.ajax({
                url: 'ajax/get_user_details.php',
                type: 'GET',
                data: { id: userId },
                success: function(response) {
                    $('#viewUserContent').html(response);
                    $('#viewUserModal').modal('show');
                },
                error: function() {
                    toastr.error('Failed to load user details');
                }
            });
        }

        // Edit user
        function editUser(userId) {
            $.ajax({
                url: 'ajax/get_user.php',
                type: 'GET',
                data: { id: userId },
                success: function(response) {
                    const user = JSON.parse(response);
                    $('#edit_user_id').val(user.id);
                    $('#edit_name').val(user.name);
                    $('#edit_email').val(user.email);
                    $('#edit_phone').val(user.phone);
                    $('#edit_status').val(user.status || 'Active');
                    $('#editUserModal').modal('show');
                },
                error: function() {
                    toastr.error('Failed to load user data');
                }
            });
        }

        // Save user changes
        function saveUserChanges() {
            const formData = $('#editUserForm').serialize();
            $.ajax({
                url: 'ajax/update_user.php',
                type: 'POST',
                data: formData,
                success: function(response) {
                    const result = JSON.parse(response);
                    if (result.success) {
                        $('#editUserModal').modal('hide');
                        toastr.success('User updated successfully');
                        // Reload the users table or update the row
                        location.reload();
                    } else {
                        toastr.error(result.message || 'Failed to update user');
                    }
                },
                error: function() {
                    toastr.error('Failed to update user');
                }
            });
        }

        // Delete user
        function deleteUser(userId) {
            Swal.fire({
                title: 'Delete User',
                text: "This action cannot be undone!",
                icon: 'warning',
                showCancelButton: true,
                confirmButtonColor: '#e74c3c',
                cancelButtonColor: '#95a5a6',
                confirmButtonText: 'Yes, delete it!'
            }).then((result) => {
                if (result.isConfirmed) {
                    $.ajax({
                        url: 'ajax/delete_user.php',
                        type: 'POST',
                        data: { id: userId },
                        success: function(response) {
                            const result = JSON.parse(response);
                            if (result.success) {
                                toastr.success('User deleted successfully');
                                // Remove the row from the table
                                $(`tr[data-user-id="${userId}"]`).remove();
                            } else {
                                toastr.error(result.message || 'Failed to delete user');
                            }
                        },
                        error: function() {
                            toastr.error('Failed to delete user');
                        }
                    });
                }
            });
        }
    </script>
</body>
</html> 