<?php
session_start();
require_once "../includes/config.php";
require_once "../includes/admin_auth.php";

// Check if admin is logged in
requireAdminLogin();

// Handle settings update
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    if (isset($_POST['action'])) {
        switch ($_POST['action']) {
            case 'update_profile':
                $sql = "UPDATE admins SET username = ?, email = ? WHERE id = 1";
                $stmt = $pdo->prepare($sql);
                $stmt->execute([$_POST['name'], $_POST['email']]);
                showToast("Profile updated successfully!", "success");
                break;

            case 'change_password':
                // Verify current password
                $sql = "SELECT password FROM admins WHERE id = 1";
                $stmt = $pdo->prepare($sql);
                $stmt->execute();
                $admin = $stmt->fetch();

                if (password_verify($_POST['current_password'], $admin['password'])) {
                    if ($_POST['new_password'] === $_POST['confirm_password']) {
                        $hashed_password = password_hash($_POST['new_password'], PASSWORD_DEFAULT);
                        $sql = "UPDATE admins SET password = ? WHERE id = 1";
                        $stmt = $pdo->prepare($sql);
                        $stmt->execute([$hashed_password]);
                        showToast("Password changed successfully!", "success");
                    } else {
                        showToast("New passwords do not match!", "error");
                    }
                } else {
                    showToast("Current password is incorrect!", "error");
                }
                break;

            case 'update_site_settings':
                // Update site settings in the database
                $sql = "INSERT INTO site_settings (setting_key, setting_value) 
                        VALUES ('booking_window', ?), ('cancellation_fee', ?)
                        ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value)";
                $stmt = $pdo->prepare($sql);
                $stmt->execute([$_POST['booking_window'], $_POST['cancellation_fee']]);
                showToast("Site settings updated successfully!", "success");
                break;

            case 'update_notification_settings':
                // Update notification settings
                // This is just a placeholder - implement according to your needs
                showToast("Notification settings updated successfully!", "success");
                break;
        }
    }
}

// Get admin profile
$sql = "SELECT * FROM admins WHERE id = 1"; // Since we have a defined admin, we'll use ID 1
$stmt = $pdo->prepare($sql);
$stmt->execute();
$admin = $stmt->fetch(PDO::FETCH_ASSOC); // Use FETCH_ASSOC to ensure we get an array

if (!$admin) {
    // If no admin exists, create one
    $sql = "INSERT INTO admins (id, name, email, password) VALUES (1, 'Admin', 'admin@railyatra.com', ?)";
    $stmt = $pdo->prepare($sql);
    $stmt->execute([password_hash('admin123', PASSWORD_DEFAULT)]);
    
    // Fetch the newly created admin
    $sql = "SELECT * FROM admins WHERE id = 1";
    $stmt = $pdo->prepare($sql);
    $stmt->execute();
    $admin = $stmt->fetch(PDO::FETCH_ASSOC);
}

// Get site settings
$sql = "SELECT setting_key, setting_value FROM site_settings 
        WHERE setting_key IN ('booking_window', 'cancellation_fee')";
$stmt = $pdo->query($sql);
$settings = [];
while ($row = $stmt->fetch()) {
    $settings[$row['setting_key']] = $row['setting_value'];
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Settings - RailYatra Admin</title>
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

        /* Reuse existing styles */
        body { font-family: 'Google Sans', Arial, sans-serif; background-color: var(--border-color); color: var(--text-color); }
        .sidebar { background: linear-gradient(135deg, var(--gradient-start), var(--gradient-end)); color: white; min-height: 100vh; }
        .nav-link { color: rgba(255,255,255,0.9); border-radius: 8px; margin: 4px 0; transition: all 0.3s ease; }
        .nav-link:hover, .nav-link.active { background: rgba(255,255,255,0.2); color: white; transform: translateX(5px); }
        .nav-link.active { background: white !important; color: var(--primary-color); }
        .card { border: none; border-radius: 12px; box-shadow: 0 2px 10px rgba(0,0,0,0.08); transition: transform 0.3s ease; }

        /* Additional styles for settings page */
        .settings-card {
            background: white;
            border-radius: 12px;
            padding: 1.5rem;
            margin-bottom: 1.5rem;
        }

        .settings-card h5 {
            color: var(--primary-color);
            margin-bottom: 1.5rem;
            padding-bottom: 1rem;
            border-bottom: 2px solid var(--border-color);
        }

        .form-control:focus {
            border-color: var(--primary-color);
            box-shadow: 0 0 0 0.2rem rgba(231, 76, 60, 0.25);
        }

        .btn-primary {
            background: linear-gradient(135deg, var(--gradient-start), var(--gradient-end));
            border: none;
            padding: 8px 20px;
            border-radius: 8px;
        }

        .btn-primary:hover {
            background: var(--hover-color);
            transform: translateY(-2px);
        }

        .settings-icon {
            width: 40px;
            height: 40px;
            border-radius: 10px;
            background: linear-gradient(135deg, var(--gradient-start), var(--gradient-end));
            color: white;
            display: flex;
            align-items: center;
            justify-content: center;
            margin-right: 1rem;
        }

        .setting-item {
            display: flex;
            align-items: center;
            padding: 1rem;
            border: 1px solid var(--border-color);
            border-radius: 8px;
            margin-bottom: 1rem;
            transition: all 0.3s ease;
        }

        .setting-item:hover {
            transform: translateX(5px);
            border-color: var(--primary-color);
        }

        .form-switch .form-check-input:checked {
            background-color: var(--primary-color);
            border-color: var(--primary-color);
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
                            <a class="nav-link" href="reports.php">
                                <i class="fas fa-chart-bar me-2"></i>Reports
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link active" href="settings.php">
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
                <h2 class="mb-4">Settings</h2>

                <!-- Profile Settings -->
                <div class="settings-card">
                    <h5><i class="fas fa-user me-2"></i>Profile Settings</h5>
                    <form method="POST" id="profileForm">
                        <input type="hidden" name="action" value="update_profile">
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Name</label>
                                <input type="text" class="form-control" name="name" value="<?php echo htmlspecialchars($admin['username'] ?? ''); ?>" required>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Email</label>
                                <input type="email" class="form-control" name="email" value="<?php echo htmlspecialchars($admin['email'] ?? ''); ?>" required>
                            </div>
                        </div>
                        <button type="submit" class="btn btn-primary">Update Profile</button>
                    </form>
                </div>

                <!-- Password Settings -->
                <div class="settings-card">
                    <h5><i class="fas fa-lock me-2"></i>Change Password</h5>
                    <form method="POST" id="passwordForm">
                        <input type="hidden" name="action" value="change_password">
                        <div class="row">
                            <div class="col-md-4 mb-3">
                                <label class="form-label">Current Password</label>
                                <input type="password" class="form-control" name="current_password" required>
                            </div>
                            <div class="col-md-4 mb-3">
                                <label class="form-label">New Password</label>
                                <input type="password" class="form-control" name="new_password" required>
                            </div>
                            <div class="col-md-4 mb-3">
                                <label class="form-label">Confirm New Password</label>
                                <input type="password" class="form-control" name="confirm_password" required>
                            </div>
                        </div>
                        <button type="submit" class="btn btn-primary">Change Password</button>
                    </form>
                </div>

                <!-- Site Settings -->
                <div class="settings-card">
                    <h5><i class="fas fa-globe me-2"></i>Site Settings</h5>
                    <form method="POST" id="siteSettingsForm">
                        <input type="hidden" name="action" value="update_site_settings">
                        <div class="setting-item">
                            <div class="settings-icon">
                                <i class="fas fa-clock"></i>
                            </div>
                            <div class="flex-grow-1">
                                <h6 class="mb-1">Booking Window</h6>
                                <p class="text-muted mb-2">Set how many days in advance users can book tickets</p>
                                <input type="number" class="form-control" name="booking_window" 
                                       value="<?php echo htmlspecialchars($settings['booking_window'] ?? '60'); ?>" 
                                       min="1" max="120">
                            </div>
                        </div>
                        <div class="setting-item">
                            <div class="settings-icon">
                                <i class="fas fa-percentage"></i>
                            </div>
                            <div class="flex-grow-1">
                                <h6 class="mb-1">Cancellation Fee (%)</h6>
                                <p class="text-muted mb-2">Set the percentage charged for ticket cancellations</p>
                                <input type="number" class="form-control" name="cancellation_fee" 
                                       value="<?php echo htmlspecialchars($settings['cancellation_fee'] ?? '10'); ?>" 
                                       min="0" max="100">
                            </div>
                        </div>
                        <button type="submit" class="btn btn-primary">Save Settings</button>
                    </form>
                </div>

                <!-- Email Notification Settings -->
                <div class="settings-card">
                    <h5><i class="fas fa-bell me-2"></i>Notification Settings</h5>
                    <form method="POST" id="notificationForm">
                        <input type="hidden" name="action" value="update_notification_settings">
                        <div class="setting-item">
                            <div class="settings-icon">
                                <i class="fas fa-envelope"></i>
                            </div>
                            <div class="flex-grow-1">
                                <div class="d-flex justify-content-between align-items-center">
                                    <div>
                                        <h6 class="mb-1">Email Notifications</h6>
                                        <p class="text-muted mb-0">Send booking confirmations and updates via email</p>
                                    </div>
                                    <div class="form-check form-switch">
                                        <input class="form-check-input" type="checkbox" name="email_notifications" checked>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <button type="submit" class="btn btn-primary">Save Settings</button>
                    </form>
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

        // Form validation
        document.getElementById('passwordForm').addEventListener('submit', function(e) {
            const newPassword = this.querySelector('[name="new_password"]').value;
            const confirmPassword = this.querySelector('[name="confirm_password"]').value;

            if (newPassword !== confirmPassword) {
                e.preventDefault();
                toastr.error('New passwords do not match!');
            }
        });

        // Confirm changes
        document.getElementById('siteSettingsForm').addEventListener('submit', function(e) {
            e.preventDefault();
            Swal.fire({
                title: 'Save Changes?',
                text: "Are you sure you want to update the site settings?",
                icon: 'warning',
                showCancelButton: true,
                confirmButtonColor: '#e74c3c',
                cancelButtonColor: '#95a5a6',
                confirmButtonText: 'Yes, save changes!'
            }).then((result) => {
                if (result.isConfirmed) {
                    this.submit();
                }
            });
        });
    </script>
</body>
</html> 