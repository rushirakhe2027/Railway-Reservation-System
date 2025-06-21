<?php
$current_page = basename($_SERVER['PHP_SELF']);
?>
<div class="col-md-3 col-lg-2 p-0">
    <div class="sidebar bg-white shadow-sm h-100 py-4">
        <div class="px-4 mb-4">
            <h4 class="text-danger mb-0">RailYatra Admin</h4>
        </div>
        <ul class="nav flex-column">
            <li class="nav-item">
                <a class="nav-link <?php echo $current_page == 'dashboard.php' ? 'active' : ''; ?>" href="dashboard.php">
                    <i class="fas fa-tachometer-alt me-2"></i>Dashboard
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link <?php echo $current_page == 'trains.php' ? 'active' : ''; ?>" href="trains.php">
                    <i class="fas fa-train me-2"></i>Trains
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link <?php echo $current_page == 'bookings.php' ? 'active' : ''; ?>" href="bookings.php">
                    <i class="fas fa-ticket-alt me-2"></i>Bookings
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link <?php echo $current_page == 'users.php' ? 'active' : ''; ?>" href="users.php">
                    <i class="fas fa-users me-2"></i>Users
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link <?php echo $current_page == 'food_vendors.php' ? 'active' : ''; ?>" href="food_vendors.php">
                    <i class="fas fa-utensils me-2"></i>Food Vendors
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link <?php echo $current_page == 'reports.php' ? 'active' : ''; ?>" href="reports.php">
                    <i class="fas fa-chart-bar me-2"></i>Reports
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link <?php echo $current_page == 'settings.php' ? 'active' : ''; ?>" href="settings.php">
                    <i class="fas fa-cog me-2"></i>Settings
                </a>
            </li>
            <li class="nav-item mt-4">
                <a class="nav-link text-danger" href="logout.php">
                    <i class="fas fa-sign-out-alt me-2"></i>Logout
                </a>
            </li>
        </ul>
    </div>
</div>

<style>
.sidebar {
    position: sticky;
    top: 0;
    height: 100vh;
}

.nav-link {
    color: var(--text-color);
    padding: 0.75rem 1.5rem;
    border-radius: 0;
    transition: all 0.2s ease;
}

.nav-link:hover {
    background-color: #f8f9fa;
    color: var(--primary-color);
}

.nav-link.active {
    background-color: #fee2e2;
    color: var(--primary-color);
    font-weight: 500;
}

.nav-link i {
    width: 20px;
    text-align: center;
}
</style> 