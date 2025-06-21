<?php
require_once 'config.php';

function sendTicketConfirmationEmail($booking_id) {
    global $pdo;
    
    try {
        // Get booking details
        $sql = "SELECT b.*, t.train_name, t.train_number, t.source_station, t.destination_station, 
                t.departure_time, u.email, u.name as user_name
                FROM bookings b
                JOIN trains t ON b.train_id = t.id
                JOIN users u ON b.user_id = u.id
                WHERE b.id = ?";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([$booking_id]);
        $booking = $stmt->fetch();

        if (!$booking) {
            throw new Exception('Booking not found');
        }

        // Get passenger details
        $sql = "SELECT * FROM passengers WHERE booking_id = ?";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([$booking_id]);
        $passengers = $stmt->fetchAll();

        // Get food orders if any
        $sql = "SELECT fo.*, fm.item_name, fv.vendor_name, fv.station_name
                FROM food_orders fo
                JOIN food_menu fm ON fo.menu_item_id = fm.id
                JOIN food_vendors fv ON fm.vendor_id = fv.id
                WHERE fo.booking_id = ?";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([$booking_id]);
        $food_orders = $stmt->fetchAll();

        // Create email content
        $to = $booking['email'];
        $subject = "Ticket Confirmed - PNR: " . $booking['pnr_number'];

        $message = "
        <html>
        <head>
            <style>
                body { font-family: Arial, sans-serif; line-height: 1.6; color: #333; }
                .container { max-width: 600px; margin: 0 auto; padding: 20px; }
                .header { background: linear-gradient(135deg, #e74c3c, #f39c12); color: white; padding: 20px; text-align: center; }
                .content { padding: 20px; }
                .section { margin-bottom: 20px; }
                .footer { text-align: center; padding: 20px; background: #f8f9fa; }
                table { width: 100%; border-collapse: collapse; margin-bottom: 20px; }
                th, td { padding: 10px; border: 1px solid #ddd; }
                th { background: #f5f5f5; }
            </style>
        </head>
        <body>
            <div class='container'>
                <div class='header'>
                    <h2>Ticket Confirmed</h2>
                    <p>PNR: {$booking['pnr_number']}</p>
                </div>
                <div class='content'>
                    <div class='section'>
                        <h3>Train Details</h3>
                        <table>
                            <tr>
                                <th>Train Name</th>
                                <td>{$booking['train_name']} ({$booking['train_number']})</td>
                            </tr>
                            <tr>
                                <th>From</th>
                                <td>{$booking['source_station']}</td>
                            </tr>
                            <tr>
                                <th>To</th>
                                <td>{$booking['destination_station']}</td>
                            </tr>
                            <tr>
                                <th>Departure</th>
                                <td>" . date('d M Y h:i A', strtotime($booking['journey_date'] . ' ' . $booking['departure_time'])) . "</td>
                            </tr>
                        </table>
                    </div>

                    <div class='section'>
                        <h3>Passenger Details</h3>
                        <table>
                            <tr>
                                <th>Name</th>
                                <th>Age</th>
                                <th>Gender</th>
                                <th>Seat</th>
                            </tr>";
        
        foreach ($passengers as $passenger) {
            $message .= "
                            <tr>
                                <td>{$passenger['name']}</td>
                                <td>{$passenger['age']}</td>
                                <td>{$passenger['gender']}</td>
                                <td>{$passenger['seat_number']}</td>
                            </tr>";
        }

        $message .= "
                        </table>
                    </div>";

        if (!empty($food_orders)) {
            $message .= "
                    <div class='section'>
                        <h3>Food Orders</h3>
                        <table>
                            <tr>
                                <th>Item</th>
                                <th>Quantity</th>
                                <th>Amount</th>
                                <th>Delivery Station</th>
                            </tr>";
            
            foreach ($food_orders as $order) {
                $message .= "
                            <tr>
                                <td>{$order['item_name']}</td>
                                <td>{$order['quantity']}</td>
                                <td>₹{$order['total_amount']}</td>
                                <td>{$order['station_name']}</td>
                            </tr>";
            }

            $message .= "
                        </table>
                    </div>";
        }

        $message .= "
                    <div class='section'>
                        <h3>Payment Details</h3>
                        <table>
                            <tr>
                                <th>Total Amount Paid</th>
                                <td>₹{$booking['total_amount']}</td>
                            </tr>
                        </table>
                    </div>

                    <div class='footer'>
                        <p>Thank you for choosing RailYatra!</p>
                        <p>For any queries, please contact our support team.</p>
                    </div>
                </div>
            </div>
        </body>
        </html>";

        // Email headers
        $headers = "MIME-Version: 1.0" . "\r\n";
        $headers .= "Content-type:text/html;charset=UTF-8" . "\r\n";
        $headers .= 'From: RailYatra <noreply@railyatra.com>' . "\r\n";

        // Send email
        if(mail($to, $subject, $message, $headers)) {
            return true;
        } else {
            throw new Exception('Failed to send email');
        }

    } catch (Exception $e) {
        error_log('Email notification error: ' . $e->getMessage());
        return false;
    }
}
?> 