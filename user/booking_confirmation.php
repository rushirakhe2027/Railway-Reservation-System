<?php
session_start();
require_once "../includes/config.php";
require_once "../includes/db.php";
require_once "../MailUtils/EmailSender.php";

// Check if user is logged in
if (!isset($_SESSION['user_id']) || !isset($_SESSION['logged_in'])) {
    $_SESSION['toast'] = [
        'type' => 'error',
        'message' => 'Please login to view booking details'
    ];
    header("Location: ../login.php");
    exit;
}

// Check if PNR is provided
if (!isset($_GET['pnr'])) {
    $_SESSION['toast'] = [
        'type' => 'error',
        'message' => 'No booking reference provided'
    ];
    header("Location: dashboard.php");
    exit;
}

$pnr = $_GET['pnr'];

try {
    // Get booking details with user information
    $stmt = $pdo->prepare("
        SELECT b.*, t.train_name, t.train_number, u.name as user_name, u.email as user_email 
        FROM bookings b
        JOIN trains t ON b.train_id = t.id
        JOIN users u ON b.user_id = u.id
        WHERE b.pnr_number = ? AND b.user_id = ?
    ");
    $stmt->execute([$pnr, $_SESSION['user_id']]);
    $booking = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$booking) {
        $_SESSION['toast'] = [
            'type' => 'error',
            'message' => 'Booking not found'
        ];
        header("Location: dashboard.php");
        exit;
    }

    // Get passenger details
    $stmt = $pdo->prepare("SELECT * FROM passengers WHERE booking_id = ?");
    $stmt->execute([$booking['id']]);
    $passengers = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Get food orders if any
    $food_orders = [];
    $stmt = $pdo->prepare("
        SELECT fo.*, fm.item_name, fm.price, fv.name as vendor_name
        FROM food_orders fo
        JOIN food_menu fm ON fo.menu_item_id = fm.id
        JOIN food_vendors fv ON fm.vendor_id = fv.id
        WHERE fo.booking_id = ?
    ");
    $stmt->execute([$booking['id']]);
    $food_orders = $stmt->fetchAll(PDO::FETCH_ASSOC);

} catch (PDOException $e) {
    error_log("Database error: " . $e->getMessage());
    $_SESSION['toast'] = [
        'type' => 'error',
        'message' => 'Error retrieving booking details'
    ];
    header("Location: dashboard.php");
    exit;
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>E-Ticket - PNR: <?php echo htmlspecialchars($pnr); ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        :root {
            --primary-color: #e74c3c;
            --secondary-color: #e67e22;
            --accent-color: #f39c12;
            --text-color: #2c3e50;
            --border-color: #ecf0f1;
        }

        body {
            background-color: var(--border-color);
            color: var(--text-color);
            font-family: 'Arial', sans-serif;
        }

        .container {
            max-width: 1000px;
            margin: 50px auto;
        }

        .card {
            border: none;
            border-radius: 15px;
            box-shadow: 0 4px 15px rgba(0,0,0,0.1);
        }

        .ticket-header {
            background: linear-gradient(135deg, var(--primary-color), var(--secondary-color));
            color: white;
            padding: 2rem;
            border-radius: 15px 15px 0 0;
            position: relative;
            overflow: hidden;
        }

        .ticket-header::before {
            content: '';
            position: absolute;
            top: 0;
            right: 0;
            bottom: 0;
            left: 0;
            background: url('data:image/svg+xml,<svg width="100" height="100" viewBox="0 0 100 100" xmlns="http://www.w3.org/2000/svg"><circle cx="50" cy="50" r="40" stroke="white" stroke-width="2" fill="none" opacity="0.2"/></svg>') repeat;
            opacity: 0.1;
        }

        .status-badge {
            padding: 0.5rem 1rem;
            border-radius: 50px;
            font-weight: 500;
            text-transform: uppercase;
            letter-spacing: 1px;
        }

        .status-pending { background-color: #ffc107; color: #000; }
        .status-confirmed { background-color: #28a745; color: white; }
        .status-cancelled { background-color: #dc3545; color: white; }

        .detail-row {
            padding: 1.5rem;
            border-bottom: 1px solid var(--border-color);
        }

        .passenger-card {
            background: #f8f9fa;
            border-radius: 10px;
            padding: 1.5rem;
            margin-bottom: 1rem;
            transition: transform 0.2s;
        }

        .passenger-card:hover {
            transform: translateY(-2px);
        }

        .food-order-card {
            background: #fff;
            border: 1px solid var(--border-color);
            border-radius: 10px;
            padding: 1.5rem;
            margin-bottom: 1rem;
        }

        .qr-code {
            width: 100px;
            height: 100px;
            margin-left: auto;
        }

        @media print {
            body {
                background: white;
            }
            .no-print {
                display: none !important;
            }
            .container {
                margin: 0;
                padding: 0;
                width: 100%;
                max-width: none;
            }
            .card {
                box-shadow: none;
                border: 1px solid #ddd;
            }
        }

        .download-btn {
            background: var(--accent-color);
            color: white;
            border: none;
            padding: 10px 20px;
            border-radius: 5px;
            transition: background 0.3s;
        }

        .download-btn:hover {
            background: var(--secondary-color);
        }

        .important-info {
            background: #e8f5e9;
            border-radius: 10px;
            padding: 1.5rem;
            margin-top: 1.5rem;
        }
    </style>
</head>
<body>
    <div class="container" id="ticket-container">
        <div class="card">
            <div class="ticket-header">
                <div class="d-flex justify-content-between align-items-start">
                    <div>
                        <h3 class="mb-2">E-Ticket</h3>
                        <h4 class="mb-3">PNR: <?php echo htmlspecialchars($pnr); ?></h4>
                        <h5 class="mb-0">
                            <?php echo htmlspecialchars($booking['train_name']); ?> 
                            (<?php echo htmlspecialchars($booking['train_number']); ?>)
                        </h5>
                    </div>
                    <div class="text-end">
                        <span class="status-badge status-<?php echo strtolower($booking['status']); ?>">
                            <?php echo htmlspecialchars($booking['status']); ?>
                        </span>
                        <div class="mt-3">
                            <img src="https://api.qrserver.com/v1/create-qr-code/?size=100x100&data=<?php echo urlencode($pnr); ?>" 
                                 alt="Ticket QR Code" class="qr-code">
                        </div>
                    </div>
                </div>
            </div>

            <div class="card-body">
                <div class="detail-row">
                    <div class="row">
                        <div class="col-md-6">
                            <h6 class="text-muted mb-2">Passenger Details</h6>
                            <p class="mb-1"><?php echo htmlspecialchars($booking['user_name']); ?></p>
                            <p class="mb-0"><?php echo htmlspecialchars($booking['user_email']); ?></p>
                        </div>
                        <div class="col-md-6 text-md-end">
                            <h6 class="text-muted mb-2">Booking Reference</h6>
                            <p class="mb-1">PNR: <?php echo htmlspecialchars($pnr); ?></p>
                            <p class="mb-0">Booked on: <?php echo date('d M Y', strtotime($booking['booking_date'])); ?></p>
                        </div>
                    </div>
                </div>

                <div class="detail-row">
                    <div class="row">
                        <div class="col-md-3">
                            <h6 class="text-muted mb-2">From</h6>
                            <p class="mb-0"><?php echo htmlspecialchars($booking['from_station']); ?></p>
                        </div>
                        <div class="col-md-3">
                            <h6 class="text-muted mb-2">To</h6>
                            <p class="mb-0"><?php echo htmlspecialchars($booking['to_station']); ?></p>
                        </div>
                        <div class="col-md-3">
                            <h6 class="text-muted mb-2">Journey Date</h6>
                            <p class="mb-0"><?php echo date('d M Y', strtotime($booking['journey_date'])); ?></p>
                        </div>
                        <div class="col-md-3">
                            <h6 class="text-muted mb-2">Total Amount</h6>
                            <p class="mb-0">₹<?php echo number_format($booking['total_amount'], 2); ?></p>
                        </div>
                    </div>
                </div>

                <div class="detail-row">
                    <h5 class="mb-3">Passenger Details</h5>
                    <?php foreach ($passengers as $index => $passenger): ?>
                    <div class="passenger-card">
                        <div class="row">
                            <div class="col-md-1">
                                <h6 class="text-muted mb-2">#<?php echo $index + 1; ?></h6>
                            </div>
                            <div class="col-md-4">
                                <h6 class="text-muted mb-2">Name</h6>
                                <p class="mb-0"><?php echo htmlspecialchars($passenger['name']); ?></p>
                            </div>
                            <div class="col-md-2">
                                <h6 class="text-muted mb-2">Age</h6>
                                <p class="mb-0"><?php echo htmlspecialchars($passenger['age']); ?></p>
                            </div>
                            <div class="col-md-2">
                                <h6 class="text-muted mb-2">Gender</h6>
                                <p class="mb-0"><?php echo htmlspecialchars($passenger['gender']); ?></p>
                            </div>
                            <?php if (isset($passenger['id_proof']) && !empty($passenger['id_proof'])): ?>
                            <div class="col-md-3">
                                <h6 class="text-muted mb-2">ID Proof</h6>
                                <p class="mb-0"><?php echo htmlspecialchars($passenger['id_proof']); ?></p>
                            </div>
                            <?php endif; ?>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>

                <?php if (!empty($food_orders)): ?>
                <div class="detail-row">
                    <h5 class="mb-3">Food Orders</h5>
                    <?php foreach ($food_orders as $order): ?>
                    <div class="food-order-card">
                        <div class="row">
                            <div class="col-md-4">
                                <h6 class="text-muted mb-2">Item</h6>
                                <p class="mb-0"><?php echo htmlspecialchars($order['item_name']); ?></p>
                                <small class="text-muted">by <?php echo htmlspecialchars($order['vendor_name']); ?></small>
                            </div>
                            <div class="col-md-2">
                                <h6 class="text-muted mb-2">Quantity</h6>
                                <p class="mb-0"><?php echo htmlspecialchars($order['quantity']); ?></p>
                            </div>
                            <div class="col-md-3">
                                <h6 class="text-muted mb-2">Delivery At</h6>
                                <p class="mb-0"><?php echo htmlspecialchars($order['delivery_station']); ?></p>
                            </div>
                            <div class="col-md-3">
                                <h6 class="text-muted mb-2">Amount</h6>
                                <p class="mb-0">₹<?php echo number_format($order['total_amount'], 2); ?></p>
                            </div>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
                <?php endif; ?>

                <?php if ($booking['status'] === 'Confirmed'): ?>
                <div class="detail-row">
                    <div class="important-info">
                        <h5 class="mb-3"><i class="fas fa-info-circle me-2"></i>Important Information</h5>
                        <ul class="mb-0">
                            <li>Please carry a valid ID proof during the journey</li>
                            <li>Arrive at the station at least 30 minutes before departure</li>
                            <li>Keep this e-ticket handy for verification</li>
                            <li>Food orders will be delivered at the specified stations</li>
                        </ul>
                    </div>
                </div>
                <?php endif; ?>

                <div class="detail-row">
                    <div class="row">
                        <div class="col-12 text-end no-print">
                            <a href="dashboard.php" class="btn btn-secondary me-2">
                                <i class="fas fa-arrow-left me-2"></i>Back to Dashboard
                            </a>
                            <?php if ($booking['status'] === 'Confirmed'): ?>
                            <button class="btn btn-primary me-2" onclick="window.print()">
                                <i class="fas fa-print me-2"></i>Print Ticket
                            </button>
                            <button class="download-btn" onclick="downloadPDF()">
                                <i class="fas fa-download me-2"></i>Download PDF
                            </button>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/html2pdf.js/0.10.1/html2pdf.bundle.min.js"></script>
    <script>
        function downloadPDF() {
            const element = document.getElementById('ticket-container');
            const opt = {
                margin: 1,
                filename: 'E-Ticket-<?php echo $pnr; ?>.pdf',
                image: { type: 'jpeg', quality: 0.98 },
                html2canvas: { scale: 2 },
                jsPDF: { unit: 'mm', format: 'a4', orientation: 'portrait' }
            };

            // Remove no-print elements temporarily
            const noPrintElements = document.querySelectorAll('.no-print');
            noPrintElements.forEach(el => el.style.display = 'none');

            html2pdf().set(opt).from(element).save().then(() => {
                // Restore no-print elements
                noPrintElements.forEach(el => el.style.display = '');
            });
        }
    </script>
</body>
</html> 