<?php
session_start();
require_once "includes/config.php";
require_once 'MailUtils/EmailSender.php';

if(isset($_POST['register'])) {
    $name = trim($_POST['name']);
    $email = trim($_POST['email']);
    $phone = trim($_POST['phone']);
    $password = trim($_POST['password']);
    
    // Validate phone number (10 digits)
    if(!preg_match("/^[0-9]{10}$/", $phone)) {
        $_SESSION['toast'] = ['type' => 'error', 'message' => 'Please enter a valid 10-digit phone number'];
    } else {
        // Hash password
        $hashed_password = password_hash($password, PASSWORD_DEFAULT);
        
        // Check if email already exists
        $stmt = $pdo->prepare("SELECT id FROM users WHERE email = ?");
        $stmt->execute([$email]);
        
        if($stmt->rowCount() > 0) {
            $_SESSION['toast'] = ['type' => 'error', 'message' => 'Email already registered'];
        } else {
            // Insert new user
            $sql = "INSERT INTO users (name, email, phone, password) VALUES (:name, :email, :phone, :password)";
            $stmt = $pdo->prepare($sql);
            $stmt->bindParam(":name", $name, PDO::PARAM_STR);
            $stmt->bindParam(":email", $email, PDO::PARAM_STR);
            $stmt->bindParam(":phone", $phone, PDO::PARAM_STR);
            $stmt->bindParam(":password", $hashed_password, PDO::PARAM_STR);

            if($stmt->execute()) {
                // Send welcome email
                try {
                    $emailSender = new EmailSender();
                    $welcomeEmailBody = "
                        <h2>Welcome to RailYatra!</h2>
                        <p>Dear {$name},</p>
                        <p>Thank you for registering with RailYatra. We're excited to have you on board!</p>
                        <h3>Your Account Details:</h3>
                        <ul>
                            <li>Name: {$name}</li>
                            <li>Email: {$email}</li>
                            <li>Phone: {$phone}</li>
                        </ul>
                        <p>You can now:</p>
                        <ul>
                            <li>Book train tickets</li>
                            <li>View train schedules</li>
                            <li>Track your bookings</li>
                            <li>And much more!</li>
                        </ul>
                        <p>To get started, simply <a href='http://localhost/Railway%20reservation/login.php'>login to your account</a>.</p>
                        <p>If you have any questions or need assistance, feel free to contact our support team.</p>
                        <p>Best regards,<br>RailYatra Team</p>
                    ";
                    
                    $emailSent = $emailSender->sendEmail($email, "Welcome to RailYatra!", $welcomeEmailBody);
                    
                    if($emailSent) {
                        $_SESSION['toast'] = ['type' => 'success', 'message' => 'Registration successful! Please check your email for confirmation.'];
                    } else {
                        $_SESSION['toast'] = ['type' => 'success', 'message' => 'Registration successful! Please login.'];
                        error_log("Failed to send welcome email to: " . $email);
                    }
                } catch (Exception $e) {
                    error_log("Email error: " . $e->getMessage());
                    $_SESSION['toast'] = ['type' => 'success', 'message' => 'Registration successful! Please login.'];
                }
                
                header("location: login.php");
                exit;
            } else {
                $_SESSION['toast'] = ['type' => 'error', 'message' => 'Something went wrong'];
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
    <title>Register - RailYatra</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/toastr.js/latest/toastr.min.css">
    <style>
        :root {
            --primary-color: #2c3e50;
            --accent-color: #e74c3c;
            --secondary-color: #3498db;
            --light-bg: #f8f9fa;
            --dark-bg: #2c3e50;
        }

        body {
            min-height: 100vh;
            background: linear-gradient(135deg, var(--primary-color), var(--accent-color));
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        }

        .register-container {
            background: white;
            border-radius: 20px;
            overflow: hidden;
            box-shadow: 0 15px 30px rgba(0,0,0,0.2);
            margin: 50px auto;
            max-width: 1000px;
            animation: fadeIn 0.5s ease;
        }

        @keyframes fadeIn {
            from { opacity: 0; transform: translateY(20px); }
            to { opacity: 1; transform: translateY(0); }
        }

        .register-content {
            padding: 2rem;
        }

        .form-control {
            border-radius: 10px;
            padding: 12px;
            margin-bottom: 20px;
            border: 1px solid #ddd;
            transition: all 0.3s ease;
        }

        .form-control:focus {
            border-color: var(--accent-color);
            box-shadow: 0 0 0 0.2rem rgba(231, 76, 60, 0.25);
        }

        .btn-register {
            background: var(--accent-color);
            color: white;
            padding: 12px;
            border-radius: 10px;
            width: 100%;
            border: none;
            margin-top: 10px;
            transition: all 0.3s ease;
            font-weight: 500;
        }

        .btn-register:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(231, 76, 60, 0.3);
        }

        .info-section {
            background: linear-gradient(135deg, var(--primary-color), var(--accent-color));
            color: white;
            padding: 3rem;
            height: 100%;
            display: flex;
            align-items: center;
            position: relative;
            overflow: hidden;
        }

        .info-section::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: url('images/train-register.jpg') center/cover;
            opacity: 0.1;
        }

        .info-content {
            position: relative;
            z-index: 1;
        }

        .feature-item {
            background: rgba(255, 255, 255, 0.1);
            border-radius: 10px;
            padding: 1.5rem;
            margin-bottom: 1rem;
            backdrop-filter: blur(10px);
            transition: transform 0.3s ease;
        }

        .feature-item:hover {
            transform: translateY(-5px);
        }

        .feature-icon {
            font-size: 2.5rem;
            margin-bottom: 1rem;
        }

        .back-to-home {
            position: absolute;
            top: 20px;
            left: 20px;
            color: white;
            text-decoration: none;
            display: flex;
            align-items: center;
            gap: 5px;
            font-weight: 500;
            transition: all 0.3s ease;
            z-index: 100;
        }

        .back-to-home:hover {
            color: var(--accent-color);
        }

        .input-group-text {
            background: var(--light-bg);
            border: 1px solid #ddd;
            color: var(--primary-color);
        }

        .password-toggle {
            position: relative;
        }

        .toggle-password {
            position: absolute;
            right: 15px;
            top: 50%;
            transform: translateY(-50%);
            cursor: pointer;
            color: #666;
            transition: color 0.3s ease;
        }

        .toggle-password:hover {
            color: var(--accent-color);
        }

        .form-header {
            text-align: center;
            margin-bottom: 2rem;
        }

        .form-header i {
            font-size: 3rem;
            color: var(--accent-color);
            margin-bottom: 1rem;
        }

        .progress-steps {
            display: flex;
            justify-content: space-between;
            margin-bottom: 2rem;
            position: relative;
        }

        .progress-step {
            flex: 1;
            text-align: center;
            position: relative;
            padding: 0 10px;
        }

        .step-number {
            width: 30px;
            height: 30px;
            background: var(--light-bg);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 10px;
            color: var(--primary-color);
            font-weight: bold;
            position: relative;
            z-index: 1;
        }

        .progress-steps::before {
            content: '';
            position: absolute;
            top: 15px;
            left: 0;
            right: 0;
            height: 2px;
            background: var(--light-bg);
            transform: translateY(-50%);
        }

        .step-label {
            font-size: 0.9rem;
            color: var(--primary-color);
        }

        .progress-step.active .step-number {
            background: var(--accent-color);
            color: white;
        }

        .progress-step.active .step-label {
            color: var(--accent-color);
            font-weight: bold;
        }

        .otp-input-group {
            max-width: 300px;
            margin: 0 auto;
        }

        .otp-input-group input {
            font-size: 1.5rem;
            letter-spacing: 0.5rem;
            padding: 1rem;
            text-align: center;
        }

        .verification-icon {
            font-size: 4rem;
            color: var(--accent-color);
        }

        #resendOtp {
            color: var(--accent-color);
            text-decoration: none;
        }

        #resendOtp:disabled {
            color: #999;
            cursor: not-allowed;
        }

        .progress-step.active[data-step="2"] ~ .progress-step .step-number {
            background: var(--light-bg);
            color: var(--primary-color);
        }

        .progress-step.active[data-step="2"] .step-number {
            background: var(--accent-color);
            color: white;
        }

        .toast {
            position: fixed;
            top: 20px;
            right: 20px;
            padding: 15px 25px;
            border-radius: 4px;
            color: white;
            opacity: 0;
            transition: opacity 0.3s ease;
            z-index: 1000;
        }

        .toast.show {
            opacity: 1;
        }

        .toast-success {
            background-color: #28a745;
        }

        .toast-error {
            background-color: #dc3545;
        }

        #timer {
            font-size: 1.2em;
            font-weight: bold;
            color: #dc3545;
            margin: 0 10px;
        }

        #resendOTP:disabled {
            opacity: 0.6;
            cursor: not-allowed;
        }
    </style>
</head>
<body>
    <div class="container">
        <a href="index.php" class="back-to-home">
            <i class="fas fa-arrow-left"></i> Back to Home
        </a>
        <div class="register-container">
            <div class="row g-0">
                <div class="col-md-6">
                    <div class="register-content">
                        <div class="text-center mb-4">
                            <i class="fas fa-user-plus" style="font-size: 3rem; color: var(--accent-color);"></i>
                            <h2 class="mt-3">Create Account</h2>
                            <p class="text-muted">Join RailYatra for a seamless journey experience</p>
                        </div>

                        <form method="POST" id="registrationForm">
                            <div class="mb-3">
                                <div class="input-group">
                                    <span class="input-group-text"><i class="fas fa-user"></i></span>
                                    <input type="text" name="name" class="form-control" placeholder="Full Name" required>
                                </div>
                            </div>
                            <div class="mb-3">
                                <div class="input-group">
                                    <span class="input-group-text"><i class="fas fa-envelope"></i></span>
                                    <input type="email" name="email" class="form-control" placeholder="Email Address" required>
                                </div>
                            </div>
                            <div class="mb-3">
                                <div class="input-group">
                                    <span class="input-group-text"><i class="fas fa-phone"></i></span>
                                    <input type="tel" name="phone" class="form-control" placeholder="Phone Number" required>
                                </div>
                            </div>
                            <div class="mb-3 password-toggle">
                                <div class="input-group">
                                    <span class="input-group-text"><i class="fas fa-lock"></i></span>
                                    <input type="password" name="password" class="form-control" placeholder="Password" required>
                                    <span class="input-group-text toggle-password">
                                        <i class="fas fa-eye"></i>
                                    </span>
                                </div>
                            </div>
                            <div class="mb-3 password-toggle">
                                <div class="input-group">
                                    <span class="input-group-text"><i class="fas fa-lock"></i></span>
                                    <input type="password" name="confirm_password" class="form-control" placeholder="Confirm Password" required>
                                    <span class="input-group-text toggle-password">
                                        <i class="fas fa-eye"></i>
                                    </span>
                                </div>
                            </div>
                            <button type="submit" name="register" class="btn btn-register">
                                <i class="fas fa-user-plus me-2"></i>Create Account
                            </button>
                            <div class="text-center mt-3">
                                <span class="text-muted">Already have an account?</span>
                                <a href="login.php" class="text-decoration-none ms-1">Login here</a>
                            </div>
                        </form>
                    </div>
                </div>
                <div class="col-md-6">
                    <div class="info-section">
                        <div class="info-content">
                            <div class="text-center mb-4">
                                <i class="fas fa-train" style="font-size: 4rem;"></i>
                                <h2 class="mt-3">Welcome to RailYatra</h2>
                                <p class="lead">Start Your Journey with Us</p>
                            </div>
                            <div class="features">
                                <div class="feature-item">
                                    <i class="fas fa-shield-alt feature-icon"></i>
                                    <h5>Secure Account</h5>
                                    <p>Your data is protected with industry-standard encryption</p>
                                </div>
                                <div class="feature-item">
                                    <i class="fas fa-clock feature-icon"></i>
                                    <h5>Quick Booking</h5>
                                    <p>Book your tickets in less than 2 minutes</p>
                                </div>
                                <div class="feature-item">
                                    <i class="fas fa-gift feature-icon"></i>
                                    <h5>Special Offers</h5>
                                    <p>Get exclusive deals and discounts on your first booking</p>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/toastr.js/latest/toastr.min.js"></script>
    <script>
        // Password visibility toggle
        document.querySelectorAll('.toggle-password').forEach(toggle => {
            toggle.addEventListener('click', function() {
                const input = this.closest('.password-toggle').querySelector('input');
                const icon = this.querySelector('i');
                
                if (input.type === 'password') {
                    input.type = 'text';
                    icon.classList.remove('fa-eye');
                    icon.classList.add('fa-eye-slash');
                } else {
                    input.type = 'password';
                    icon.classList.remove('fa-eye-slash');
                    icon.classList.add('fa-eye');
                }
            });
        });

        // Form validation
        document.getElementById('registrationForm').addEventListener('submit', function(e) {
            const password = document.querySelector('input[name="password"]').value;
            const confirmPassword = document.querySelector('input[name="confirm_password"]').value;
            
            if (password !== confirmPassword) {
                e.preventDefault();
                toastr.error('Passwords do not match');
            }
        });

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
    </script>
</body>
</html> 