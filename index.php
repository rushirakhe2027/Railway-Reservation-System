<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Welcome to RailYatra - Modern Railway Reservation System</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        :root {
            --primary-color: #2c3e50;
            --accent-color: #e74c3c;
            --secondary-color: #3498db;
            --light-bg: #f8f9fa;
            --dark-bg: #2c3e50;
        }

        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        }

        /* Hero Section */
        .hero-section {
            background: linear-gradient(rgba(0, 0, 0, 0.7), rgba(0, 0, 0, 0.7)), url('images/train-hero.jpg');
            background-size: cover;
            background-position: center;
            height: 100vh;
            display: flex;
            align-items: center;
            color: white;
        }

        /* Navbar */
        .navbar {
            background-color: rgba(0, 0, 0, 0.8) !important;
            padding: 1rem 0;
        }

        .navbar-brand {
            font-size: 1.5rem;
            font-weight: bold;
            color: var(--accent-color) !important;
        }

        .nav-link {
            color: white !important;
            margin: 0 1rem;
            position: relative;
        }

        .nav-link::after {
            content: '';
            position: absolute;
            bottom: 0;
            left: 0;
            width: 0;
            height: 2px;
            background-color: var(--accent-color);
            transition: width 0.3s ease;
        }

        .nav-link:hover::after {
            width: 100%;
        }

        /* Features Section */
        .features-section {
            padding: 5rem 0;
            background-color: var(--light-bg);
        }

        .feature-card {
            text-align: center;
            padding: 2rem;
            border-radius: 15px;
            background: white;
            box-shadow: 0 5px 15px rgba(0,0,0,0.1);
            transition: transform 0.3s ease;
        }

        .feature-card:hover {
            transform: translateY(-10px);
        }

        .feature-icon {
            width: 80px;
            height: 80px;
            background: var(--accent-color);
            color: white;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 2rem;
            margin: 0 auto 1.5rem;
        }

        /* Info Section */
        .info-section {
            padding: 5rem 0;
            background: linear-gradient(135deg, var(--primary-color), var(--accent-color));
            color: white;
        }

        .info-card {
            background: rgba(255,255,255,0.1);
            border-radius: 15px;
            padding: 2rem;
            backdrop-filter: blur(10px);
        }

        /* CTA Section */
        .cta-section {
            padding: 5rem 0;
            background: var(--light-bg);
        }

        .btn-custom {
            background: var(--accent-color);
            color: white;
            padding: 1rem 2rem;
            border-radius: 30px;
            border: none;
            transition: all 0.3s ease;
        }

        .btn-custom:hover {
            background: var(--primary-color);
            transform: translateY(-3px);
            box-shadow: 0 5px 15px rgba(231, 76, 60, 0.3);
        }

        /* Footer */
        .footer {
            background: var(--dark-bg);
            color: white;
            padding: 3rem 0;
        }

        .social-links a {
            color: white;
            margin: 0 10px;
            font-size: 1.5rem;
            transition: color 0.3s ease;
        }

        .social-links a:hover {
            color: var(--accent-color);
        }

        /* Animations */
        .fade-in {
            opacity: 0;
            transform: translateY(20px);
            animation: fadeIn 1s ease forwards;
        }

        @keyframes fadeIn {
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        .stat-number {
            font-size: 3rem;
            font-weight: bold;
            color: var(--accent-color);
        }
    </style>
</head>
<body>
    <!-- Navbar -->
    <nav class="navbar navbar-expand-lg navbar-dark fixed-top">
        <div class="container">
            <a class="navbar-brand" href="#">
                <i class="fas fa-train me-2"></i>RailYatra
            </a>
            <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav">
                <span class="navbar-toggler-icon"></span>
            </button>
            <div class="collapse navbar-collapse" id="navbarNav">
                <ul class="navbar-nav ms-auto">
                    <li class="nav-item">
                        <a class="nav-link" href="#features">Features</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="#about">About</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="login.php">Login</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="register.php">Register</a>
                    </li>
                </ul>
            </div>
        </div>
    </nav>

    <!-- Hero Section -->
    <section class="hero-section">
        <div class="container">
            <div class="row">
                <div class="col-lg-8 fade-in">
                    <h1 class="display-4 fw-bold mb-4">Welcome to RailYatra</h1>
                    <p class="lead mb-4">Experience hassle-free train booking with our state-of-the-art reservation system. Book tickets, order food, and manage your journey all in one place.</p>
                    <div class="d-flex gap-3">
                        <a href="register.php" class="btn btn-custom">Get Started</a>
                        <a href="#features" class="btn btn-outline-light">Learn More</a>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- Features Section -->
    <section class="features-section" id="features">
        <div class="container">
            <h2 class="text-center mb-5">Why Choose Us?</h2>
            <div class="row g-4">
                <div class="col-md-4">
                    <div class="feature-card">
                        <div class="feature-icon">
                            <i class="fas fa-ticket"></i>
                        </div>
                        <h4>Easy Booking</h4>
                        <p>Book your train tickets with just a few clicks. Simple and intuitive booking process.</p>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="feature-card">
                        <div class="feature-icon">
                            <i class="fas fa-utensils"></i>
                        </div>
                        <h4>Food Pre-ordering</h4>
                        <p>Pre-order meals from verified vendors at stations along your journey.</p>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="feature-card">
                        <div class="feature-icon">
                            <i class="fas fa-bell"></i>
                        </div>
                        <h4>Real-time Updates</h4>
                        <p>Get instant notifications about your booking status and journey updates.</p>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- Info Section -->
    <section class="info-section" id="about">
        <div class="container">
            <div class="row g-4">
                <div class="col-md-6">
                    <div class="info-card">
                        <h3>About Us</h3>
                        <p>We are committed to providing the best railway reservation experience. Our platform is designed with the latest technology to ensure smooth and efficient booking process.</p>
                        <div class="row mt-4">
                            <div class="col-6 text-center">
                                <div class="stat-number">1M+</div>
                                <p>Happy Travelers</p>
                            </div>
                            <div class="col-6 text-center">
                                <div class="stat-number">500+</div>
                                <p>Daily Bookings</p>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="col-md-6">
                    <div class="info-card">
                        <h3>Key Benefits</h3>
                        <ul class="list-unstyled">
                            <li class="mb-3"><i class="fas fa-check-circle me-2"></i> 24/7 Customer Support</li>
                            <li class="mb-3"><i class="fas fa-check-circle me-2"></i> Secure Payment Gateway</li>
                            <li class="mb-3"><i class="fas fa-check-circle me-2"></i> Easy Cancellation & Refunds</li>
                            <li class="mb-3"><i class="fas fa-check-circle me-2"></i> Multiple Payment Options</li>
                        </ul>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- CTA Section -->
    <section class="cta-section">
        <div class="container text-center">
            <h2 class="mb-4">Ready to Start Your Journey?</h2>
            <p class="mb-4">Join thousands of satisfied travelers who use our platform daily.</p>
            <a href="register.php" class="btn btn-custom">Create Account</a>
        </div>
    </section>

    <!-- Search Results Section -->
    <?php if (isset($searchResults) && !empty($searchResults)): ?>
        <div class="container mt-4">
            <h2>Search Results for "<?php echo htmlspecialchars($search); ?>"</h2>
            <div class="row">
                <?php foreach ($searchResults as $train): ?>
                    <div class="col-md-4 mb-4">
                        <div class="card">
                            <div class="card-body">
                                <h5 class="card-title"><?php echo htmlspecialchars($train['name']); ?></h5>
                                <p class="card-text">
                                    <strong>Train Number:</strong> <?php echo htmlspecialchars($train['train_number']); ?><br>
                                    <strong>Route:</strong> <?php echo htmlspecialchars($train['route_name']); ?><br>
                                    <strong>From:</strong> <?php echo htmlspecialchars($train['source_station']); ?><br>
                                    <strong>To:</strong> <?php echo htmlspecialchars($train['destination_station']); ?><br>
                                    <strong>Departure:</strong> <?php echo date('h:i A', strtotime($train['departure_time'])); ?><br>
                                    <strong>Arrival:</strong> <?php echo date('h:i A', strtotime($train['arrival_time'])); ?><br>
                                    <strong>Available Seats:</strong> <?php echo $train['available_seats']; ?>
                                </p>
                                <a href="user/book_train.php?id=<?php echo $train['id']; ?>" class="btn btn-primary">Book Now</a>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
    <?php elseif (isset($search) && empty($searchResults)): ?>
        <div class="container mt-4">
            <div class="alert alert-info">
                No trains found matching your search criteria. Please try different keywords.
            </div>
        </div>
    <?php endif; ?>

    <!-- Footer -->
    <footer class="footer">
        <div class="container">
            <div class="row">
                <div class="col-md-6">
                    <h5>RailYatra</h5>
                    <p>Your trusted partner for railway reservations.</p>
                </div>
                <div class="col-md-6 text-end">
                    <div class="social-links">
                        <a href="#"><i class="fab fa-facebook"></i></a>
                        <a href="#"><i class="fab fa-twitter"></i></a>
                        <a href="#"><i class="fab fa-instagram"></i></a>
                        <a href="#"><i class="fab fa-linkedin"></i></a>
                    </div>
                </div>
            </div>
            <hr class="mt-4">
            <div class="row">
                <div class="col-md-6">
                    <p>&copy; 2024 RailYatra. All rights reserved.</p>
                </div>
                <div class="col-md-6 text-end">
                    <a href="#" class="text-white me-3">Privacy Policy</a>
                    <a href="#" class="text-white me-3">Terms of Service</a>
                    <a href="#" class="text-white">Contact</a>
                </div>
            </div>
        </div>
    </footer>

    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Navbar scroll effect
        window.addEventListener('scroll', function() {
            if (window.scrollY > 50) {
                document.querySelector('.navbar').style.backgroundColor = 'rgba(0, 0, 0, 0.9) !important';
            } else {
                document.querySelector('.navbar').style.backgroundColor = 'rgba(0, 0, 0, 0.8) !important';
            }
        });

        // Smooth scroll for navigation links
        document.querySelectorAll('a[href^="#"]').forEach(anchor => {
            anchor.addEventListener('click', function(e) {
                e.preventDefault();
                const target = document.querySelector(this.getAttribute('href'));
                if (target) {
                    target.scrollIntoView({
                        behavior: 'smooth',
                        block: 'start'
                    });
                }
            });
        });
    </script>
</body>
</html> 