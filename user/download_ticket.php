<?php
session_start();
require_once "../includes/config.php";
require_once "../includes/db.php";

// Check if user is logged in
if (!isset($_SESSION['user_id']) || !isset($_SESSION['logged_in'])) {
    $_SESSION['toast'] = [
        'type' => 'error',
        'message' => 'Please login to download ticket'
    ];
    header("Location: ../login.php");
    exit;
}

// Get PNR from URL
$pnr = isset($_GET['pnr']) ? $_GET['pnr'] : null;

if (!$pnr) {
    $_SESSION['toast'] = [
        'type' => 'error',
        'message' => 'Invalid PNR number'
    ];
    header("Location: dashboard.php");
    exit;
}

try {
    // Get booking details with train and user information
    $stmt = $pdo->prepare("
        SELECT b.*, t.train_name, t.train_number, u.name as user_name, u.email as user_email,
        (SELECT SUM(fm.price * fo.quantity)
         FROM food_orders fo
         JOIN food_menu fm ON fo.menu_item_id = fm.id
         WHERE fo.booking_id = b.id) as food_total,
        (SELECT base_fare FROM station_pricing 
         WHERE train_id = b.train_id 
         AND ((from_station = b.from_station AND to_station = b.to_station)
         OR (from_station = b.to_station AND to_station = b.from_station))
        ) * (SELECT COUNT(*) FROM passengers WHERE booking_id = b.id) as ticket_fare
        FROM bookings b
        JOIN trains t ON b.train_id = t.id
        JOIN users u ON b.user_id = u.id
        WHERE b.pnr_number = ? AND b.user_id = ?
    ");
    $stmt->execute([$pnr, $_SESSION['user_id']]);
    $booking = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$booking) {
        throw new Exception('Booking not found');
    }

    // Calculate total amount
    $booking['total_amount'] = ($booking['ticket_fare'] ?? 0) + ($booking['food_total'] ?? 0);

    // Get passenger details
    $stmt = $pdo->prepare("SELECT * FROM passengers WHERE booking_id = ?");
    $stmt->execute([$booking['id']]);
    $passengers = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Get food orders if any
    $stmt = $pdo->prepare("
        SELECT fo.*, fm.item_name, fm.price, fv.name as vendor_name
        FROM food_orders fo
        JOIN food_menu fm ON fo.menu_item_id = fm.id
        JOIN food_vendors fv ON fm.vendor_id = fv.id
        WHERE fo.booking_id = ?
    ");
    $stmt->execute([$booking['id']]);
    $food_orders = $stmt->fetchAll(PDO::FETCH_ASSOC);

} catch (Exception $e) {
    $_SESSION['toast'] = [
        'type' => 'error',
        'message' => $e->getMessage()
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
    <title>Download E-Ticket - <?php echo htmlspecialchars($pnr); ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <script src="https://cdnjs.cloudflare.com/ajax/libs/html2pdf.js/0.10.1/html2pdf.bundle.min.js"></script>
    <style>
        body {
            background: #f4f4f4;
            font-family: Arial, sans-serif;
        }
        .download-container {
            max-width: 600px;
            margin: 50px auto;
            text-align: center;
            background: white;
            padding: 30px;
            border-radius: 10px;
            box-shadow: 0 0 20px rgba(0,0,0,0.1);
        }
        .ticket-preview {
            margin: 20px 0;
            padding: 20px;
            border: 1px solid #ddd;
            border-radius: 5px;
            text-align: left;
        }
        .btn-download {
            background: #e74c3c;
            color: white;
            padding: 15px 30px;
            border: none;
            border-radius: 5px;
            font-size: 18px;
            cursor: pointer;
            transition: background 0.3s;
        }
        .btn-download:hover {
            background: #c0392b;
        }
        #ticket {
            display: none;
        }
        .loading {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(255,255,255,0.9);
            display: flex;
            justify-content: center;
            align-items: center;
            z-index: 9999;
        }
        .loading-content {
            text-align: center;
            background: white;
            padding: 20px;
            border-radius: 10px;
            box-shadow: 0 0 20px rgba(0,0,0,0.1);
        }
        @media print {
            body {
                width: 210mm;
                height: 297mm;
                margin: 0;
                padding: 0;
            }
            #ticket {
                width: 210mm;
                padding: 10mm;
                margin: 0;
            }
        }
        .ticket-class {
            display: inline-block;
            background: rgba(255, 255, 255, 0.2);
            padding: 5px 15px;
            border-radius: 20px;
            margin-top: 10px;
            font-size: 0.9rem;
        }
    </style>
</head>
<body>
    <div class="download-container">
        <h1 class="mb-4">Download Your E-Ticket</h1>
        <div class="ticket-preview">
            <h5>Ticket Details:</h5>
            <p><strong>PNR:</strong> <?php echo htmlspecialchars($booking['pnr_number']); ?></p>
            <p><strong>Train:</strong> <?php echo htmlspecialchars($booking['train_name']); ?> (<?php echo htmlspecialchars($booking['train_number']); ?>)</p>
            <p><strong>Journey:</strong> <?php echo htmlspecialchars($booking['from_station']); ?> to <?php echo htmlspecialchars($booking['to_station']); ?></p>
            <p><strong>Date:</strong> <?php echo date('d M Y', strtotime($booking['journey_date'])); ?></p>
            <p><strong>Total Amount:</strong> ₹<?php echo number_format($booking['total_amount'], 2); ?></p>
        </div>
        <button onclick="downloadTicket()" class="btn-download">
            <i class="fas fa-download me-2"></i> Download E-Ticket
        </button>
        <p class="mt-3">
            <a href="booking_confirmation.php?pnr=<?php echo urlencode($pnr); ?>" class="btn btn-secondary">
                <i class="fas fa-arrow-left me-2"></i> Back to Ticket Details
            </a>
        </p>
    </div>

    <!-- Hidden ticket template for PDF generation -->
    <div id="ticket" style="display: none;">
        <div style="padding: 20px; width: 210mm; margin: auto; background: white;">
            <div style="background: linear-gradient(135deg, #e74c3c, #e67e22); color: white; padding: 20px; border-radius: 10px; position: relative;">
                <h1 style="margin: 0; font-size: 24px;">E-Ticket</h1>
                <h2 style="margin: 10px 0; font-size: 20px;">PNR: <?php echo htmlspecialchars($booking['pnr_number']); ?></h2>
                <h3 style="margin: 5px 0; font-size: 18px;">
                    <?php echo htmlspecialchars($booking['train_name']); ?> (<?php echo htmlspecialchars($booking['train_number']); ?>)
                </h3>
                <div style="position: absolute; top: 20px; right: 20px;">
                    <img src="https://api.qrserver.com/v1/create-qr-code/?size=100x100&data=<?php echo urlencode($booking['pnr_number']); ?>" 
                         alt="QR Code" style="width: 100px; height: 100px; background: white; padding: 5px; border-radius: 5px;">
                </div>
            </div>

            <div style="margin-top: 20px;">
                <div style="margin-bottom: 20px; padding-bottom: 20px; border-bottom: 1px solid #eee;">
                    <div style="display: flex; justify-content: space-between;">
                        <div>
                            <h4 style="margin: 0; color: #666;">Passenger Details</h4>
                            <p style="margin: 5px 0;"><?php echo htmlspecialchars($booking['user_name']); ?></p>
                            <p style="margin: 5px 0;"><?php echo htmlspecialchars($booking['user_email']); ?></p>
                        </div>
                        <div style="text-align: right;">
                            <h4 style="margin: 0; color: #666;">Booking Reference</h4>
                            <p style="margin: 5px 0;">PNR: <?php echo htmlspecialchars($booking['pnr_number']); ?></p>
                            <p style="margin: 5px 0;">Booked on: <?php echo date('d M Y', strtotime($booking['booking_date'])); ?></p>
                        </div>
                    </div>
                </div>

                <div style="margin-bottom: 20px; padding-bottom: 20px; border-bottom: 1px solid #eee;">
                    <div style="display: grid; grid-template-columns: repeat(4, 1fr); gap: 20px;">
                        <div>
                            <h4 style="margin: 0; color: #666;">From</h4>
                            <p style="margin: 5px 0;"><?php echo htmlspecialchars($booking['from_station']); ?></p>
                        </div>
                        <div>
                            <h4 style="margin: 0; color: #666;">To</h4>
                            <p style="margin: 5px 0;"><?php echo htmlspecialchars($booking['to_station']); ?></p>
                        </div>
                        <div>
                            <h4 style="margin: 0; color: #666;">Journey Date</h4>
                            <p style="margin: 5px 0;"><?php echo date('d M Y', strtotime($booking['journey_date'])); ?></p>
                        </div>
                        <div>
                            <h4 style="margin: 0; color: #666;">Total Amount</h4>
                            <p style="margin: 5px 0;">₹<?php echo number_format($booking['total_amount'], 2); ?></p>
                        </div>
                    </div>
                </div>

                <div style="margin-bottom: 20px;">
                    <h4 style="margin: 0 0 15px 0; color: #666;">Passenger Details</h4>
                    <?php foreach ($passengers as $index => $passenger): ?>
                    <div style="background: #f8f9fa; padding: 15px; margin-bottom: 10px; border-radius: 5px;">
                        <div style="display: grid; grid-template-columns: 50px 1fr 100px 100px; gap: 20px;">
                            <div>#<?php echo $index + 1; ?></div>
                            <div><?php echo htmlspecialchars($passenger['name']); ?></div>
                            <div><?php echo htmlspecialchars($passenger['age']); ?> Yrs</div>
                            <div><?php echo htmlspecialchars($passenger['gender']); ?></div>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>

                <?php if (!empty($food_orders)): ?>
                <div style="margin-bottom: 20px;">
                    <h4 style="margin: 0 0 15px 0; color: #666;">Food Orders</h4>
                    <?php foreach ($food_orders as $order): ?>
                    <div style="background: #fff8f5; padding: 15px; margin-bottom: 10px; border-radius: 5px;">
                        <div style="display: grid; grid-template-columns: 2fr 1fr 1fr 1fr; gap: 20px;">
                            <div>
                                <strong><?php echo htmlspecialchars($order['item_name']); ?></strong><br>
                                <small>by <?php echo htmlspecialchars($order['vendor_name']); ?></small>
                            </div>
                            <div>Qty: <?php echo htmlspecialchars($order['quantity']); ?></div>
                            <div><?php echo htmlspecialchars($order['delivery_station']); ?></div>
                            <div>₹<?php echo number_format($order['total_amount'], 2); ?></div>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
                <?php endif; ?>

                <?php if ($booking['status'] === 'Confirmed'): ?>
                <div style="background: #e8f5e9; padding: 15px; border-radius: 5px; margin-bottom: 20px;">
                    <h4 style="margin: 0 0 10px 0; color: #2e7d32;">Important Information</h4>
                    <ul style="margin: 0; padding-left: 20px;">
                        <li>Please carry a valid ID proof during the journey</li>
                        <li>Arrive at the station at least 30 minutes before departure</li>
                        <li>Keep this e-ticket handy for verification</li>
                        <?php if (!empty($food_orders)): ?>
                        <li>Food orders will be delivered at the specified stations</li>
                        <?php endif; ?>
                    </ul>
                </div>
                <?php endif; ?>

                <div style="text-align: center; margin-top: 30px; color: #666; font-size: 12px;">
                    <p style="margin: 5px 0;">This is a computer generated ticket and does not require a physical signature.</p>
                    <p style="margin: 5px 0;">For any assistance, contact our 24x7 helpline: 1800-XXX-XXXX or email: support@railyatra.com</p>
                </div>
            </div>
        </div>
    </div>

    <script>
        function downloadTicket() {
            // Show loading
            const loading = document.createElement('div');
            loading.className = 'loading';
            loading.innerHTML = `
                <div class="loading-content">
                    <div class="spinner-border text-primary mb-3" role="status"></div>
                    <div>Generating your ticket...</div>
                </div>
            `;
            document.body.appendChild(loading);

            const element = document.getElementById('ticket');
            const opt = {
                margin: [0, 0],
                filename: 'E-Ticket-<?php echo $booking['pnr_number']; ?>.pdf',
                image: { type: 'jpeg', quality: 0.98 },
                html2canvas: { 
                    scale: 2,
                    useCORS: true,
                    logging: true,
                    width: 793.7, // A4 width in pixels at 96 DPI
                    height: 1122.5 // A4 height in pixels at 96 DPI
                },
                jsPDF: { 
                    unit: 'mm', 
                    format: 'a4', 
                    orientation: 'portrait'
                }
            };

            // Generate PDF
            html2pdf().set(opt).from(element).save().then(() => {
                document.body.removeChild(loading);
            }).catch(error => {
                console.error('PDF generation failed:', error);
                alert('Failed to generate PDF. Please try again.');
                document.body.removeChild(loading);
            });
        }

        // Check if html2pdf is loaded
        document.addEventListener('DOMContentLoaded', function() {
            if (typeof html2pdf === 'undefined') {
                alert('PDF generation library not loaded. Please refresh the page.');
            }
        });
    </script>
</body>
</html> 