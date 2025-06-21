<?php
require_once 'includes/config.php';
require_once 'includes/session.php';
require_once 'includes/performance.php';
require_once 'includes/assets.php';
require_once 'includes/admin_auth.php';
require_once 'MailUtils/EmailSender.php';

// Initialize performance monitoring
$performance->addMarker('page_start');

// Initialize email sender
$emailSender = new EmailSender();

// Check if user is already logged in
if ($sessionManager->isAuthenticated()) {
    $userType = $sessionManager->getUserType();
    if ($userType === 'admin') {
        header('Location: admin/dashboard.php');
        exit;
    } elseif ($userType === 'user') {
        header('Location: user/dashboard.php');
        exit;
    }
}

// Define admin credentials
if (!defined('ADMIN_EMAIL')) {
    define('ADMIN_EMAIL', 'admin@gmail.com');
}
if (!defined('ADMIN_PASSWORD')) {
    define('ADMIN_PASSWORD', '12345678');
}

// Process login form
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        // Get form data
        $email = trim($_POST['email']);
        $password = trim($_POST['password']);
        $loginType = trim($_POST['login_type']);

        // Validate required fields
        if (empty($email) || empty($password) || empty($loginType)) {
            throw new Exception('All fields are required.');
        }

        // Admin Login
        if ($loginType === 'admin') {
            if ($email === 'admin@gmail.com' && $password === '12345678') {
                // Set all necessary admin session variables
                $_SESSION['admin_id'] = 1;
                $_SESSION['admin_email'] = $email;
                $_SESSION['user_type'] = 'admin';
                $_SESSION['is_admin'] = true;
                $_SESSION['admin_name'] = 'Administrator';
                
                // Clear any existing error messages
                unset($_SESSION['error']);
                
                // Ensure headers haven't been sent yet
                if (!headers_sent()) {
                    header("Location: admin/dashboard.php");
                    exit();
                } else {
                    echo "<script>window.location.href = 'admin/dashboard.php';</script>";
                    exit();
                }
            } else {
                throw new Exception("Invalid admin credentials!");
            }
        }
        // User Login
        else {
            // Database connection
            $conn = mysqli_connect("localhost", "root", "", "railway_reservation");
            if (!$conn) {
                throw new Exception("Database connection failed");
            }

            $sql = "SELECT * FROM users WHERE email = ?";
            $stmt = mysqli_prepare($conn, $sql);
            mysqli_stmt_bind_param($stmt, "s", $email);
            mysqli_stmt_execute($stmt);
            $result = mysqli_stmt_get_result($stmt);
            $user = mysqli_fetch_assoc($result);

            if ($user && password_verify($password, $user['password'])) {
                session_regenerate_id(true); // Prevent session fixation
                
                // Set all necessary session variables
                $_SESSION['user_id'] = $user['id'];
                $_SESSION['user_email'] = $user['email'];
                $_SESSION['user_name'] = $user['name'];
                $_SESSION['user_type'] = 'user';
                $_SESSION['is_admin'] = false;
                $_SESSION['logged_in'] = true;
                
                // Send login notification email
                try {
                    $loginEmailBody = "
                        <h2>New Login to Your RailYatra Account</h2>
                        <p>Hello {$user['name']},</p>
                        <p>We detected a new login to your RailYatra account.</p>
                        <p><strong>Login Details:</strong></p>
                        <ul>
                            <li>Date & Time: " . date('Y-m-d H:i:s') . "</li>
                            <li>IP Address: " . $_SERVER['REMOTE_ADDR'] . "</li>
                            <li>Browser: " . $_SERVER['HTTP_USER_AGENT'] . "</li>
                        </ul>
                        <p>If this wasn't you, please contact support immediately.</p>
                        <p>Best regards,<br>RailYatra Team</p>
                    ";
                    
                    $emailSender->sendEmail($user['email'], "New Login to RailYatra Account", $loginEmailBody);
                } catch (Exception $e) {
                    error_log("Failed to send login notification email: " . $e->getMessage());
                }
                
                // Clear any existing error messages
                unset($_SESSION['error']);
                
                // Check if there's a redirect URL stored in session
                if (isset($_SESSION['redirect_after_login']) && !empty($_SESSION['redirect_after_login'])) {
                    $redirect_url = $_SESSION['redirect_after_login'];
                    unset($_SESSION['redirect_after_login']); // Clear the stored URL
                    
                    // Ensure headers haven't been sent yet
                    if (!headers_sent()) {
                        header("Location: " . $redirect_url);
                        exit();
                    } else {
                        echo "<script>window.location.href = '" . $redirect_url . "';</script>";
                        exit();
                    }
                } else {
                    // Default redirect to dashboard
                    if (!headers_sent()) {
                        header("Location: user/dashboard.php");
                        exit();
                    } else {
                        echo "<script>window.location.href = 'user/dashboard.php';</script>";
                        exit();
                    }
                }
            } else {
                throw new Exception("Invalid email or password!");
            }
        }
    } catch (Exception $e) {
        $_SESSION['error'] = $e->getMessage();
    }
}

// Generate new CSRF token
$csrf_token = generateCSRFToken();

// Log page load time
$performance->addMarker('page_end');
logActivity('page_load', 'Login page loaded', [
    'load_time' => $performance->getExecutionTime('page_start', 'page_end')
]);

// Close session for writing to improve concurrent requests
$sessionManager->writeClose();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="description" content="Login to RailYatra - Your Railway Reservation System">
    <title>Login - RailYatra</title>
    <?php echo commonCSS(); ?>
    <style>
        body {
            min-height: 100vh;
            background: linear-gradient(135deg, var(--primary-color), var(--accent-color));
        }

        .login-container {
            background: white;
            border-radius: 20px;
            box-shadow: 0 15px 30px rgba(0,0,0,0.2);
            margin: 50px auto;
            max-width: 1000px;
        }

        .login-content { 
            padding: 2rem; 
        }

        .info-section {
            background: linear-gradient(135deg, var(--primary-color), var(--accent-color));
            color: white;
            padding: 2rem;
            height: 100%;
            display: flex;
            align-items: center;
            border-radius: 0 20px 20px 0;
        }

        .info-section ul {
            list-style: none;
            padding-left: 0;
        }

        .info-section ul li {
            margin-bottom: 10px;
            position: relative;
            padding-left: 25px;
        }

        .info-section ul li:before {
            content: '✓';
            position: absolute;
            left: 0;
            color: #2ecc71;
        }

        .form-control:focus {
            border-color: var(--accent-color);
            box-shadow: 0 0 0 0.2rem rgba(231, 76, 60, 0.25);
        }

        @keyframes fadeIn {
            from { opacity: 0; transform: translateX(20px); }
            to { opacity: 1; transform: translateX(0); }
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="login-container">
            <div class="row g-0">
                <div class="col-md-6">
                    <div class="login-content">
                        <h2 class="mb-4">Login to RailYatra</h2>
                        <?php
                        if (isset($_SESSION['error'])) {
                            echo '<div class="alert alert-danger">' . htmlspecialchars($_SESSION['error']) . '</div>';
                            unset($_SESSION['error']);
                        }
                        ?>
                        <form method="POST" action="login.php" id="loginForm">
                            <div class="mb-3">
                                <label class="form-label">Email</label>
                                <input type="email" name="email" class="form-control" required>
                            </div>
                            
                            <div class="mb-3">
                                <label class="form-label">Password</label>
                                <input type="password" name="password" class="form-control" required>
                            </div>
                            
                            <div class="mb-3">
                                <label class="form-label">Login As</label>
                                <select name="login_type" class="form-control" id="loginType" required>
                                    <option value="">Select login type</option>
                                    <option value="user">User</option>
                                    <option value="admin">Admin</option>
                                </select>
                            </div>
                            
                            <button type="submit" class="btn btn-primary w-100">
                                Login
                            </button>
                        </form>
                        
                        <p class="mt-3 text-center">
                            Don't have an account? <a href="register.php">Register here</a>
                        </p>
                    </div>
                </div>
                <div class="col-md-6 d-none d-md-block">
                    <div class="info-section" id="userInfo">
                        <div>
                            <h3>Welcome Back!</h3>
                            <p>Login to access your RailYatra account and manage your train bookings.</p>
                            <ul class="mt-3">
                                <li>Book train tickets easily</li>
                                <li>Track your booking status</li>
                                <li>Manage your travel history</li>
                                <li>Order food for your journey</li>
                            </ul>
                        </div>
                    </div>
                    <div class="info-section" id="adminInfo" style="display: none;">
                        <div>
                            <h3>Admin Portal</h3>
                            <p>Welcome to the RailYatra administration panel.</p>
                            <ul class="mt-3">
                                <li>Manage train schedules</li>
                                <li>Handle user bookings</li>
                                <li>Monitor food vendors</li>
                                <li>View system analytics</li>
                            </ul>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <?php 
    echo commonJS();
    echo loadJS('toastr', true);
    ?>
    <script>
        // Form submission handler
        document.getElementById('loginForm').addEventListener('submit', function(e) {
            const btn = document.querySelector('.btn-primary');
            btn.disabled = true;
            btn.innerHTML = '<span class="spinner-border spinner-border-sm" role="status" aria-hidden="true"></span> Logging in...';
        });

        // Login type switcher
        document.getElementById('loginType').addEventListener('change', function() {
            const userInfo = document.getElementById('userInfo');
            const adminInfo = document.getElementById('adminInfo');
            
            if (this.value === 'admin') {
                userInfo.style.display = 'none';
                adminInfo.style.display = 'flex';
                adminInfo.style.animation = 'fadeIn 0.3s ease-in';
            } else {
                adminInfo.style.display = 'none';
                userInfo.style.display = 'flex';
                userInfo.style.animation = 'fadeIn 0.3s ease-in';
            }
        });
    </script>
</body>
</html> 