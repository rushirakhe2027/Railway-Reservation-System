<?php
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\SMTP;
use PHPMailer\PHPMailer\Exception;

// Fix the paths to use a relative path from the includes directory to the MailUtils directory
require_once __DIR__ . '/../MailUtils/vendor/phpmailer/phpmailer/src/PHPMailer.php';
require_once __DIR__ . '/../MailUtils/vendor/phpmailer/phpmailer/src/SMTP.php';
require_once __DIR__ . '/../MailUtils/vendor/phpmailer/phpmailer/src/Exception.php';

class EmailService {
    private $mailer;
    private $fromEmail = 'shrutidodatale@gmail.com';
    private $fromName = 'Railway Reservation System';

    public function __construct() {
        $this->mailer = new PHPMailer(true);
        
        // Server settings
        $this->mailer->Debugoutput = 'error_log';
        $this->mailer->SMTPDebug = 0;
        $this->mailer->isSMTP();
        $this->mailer->Host = 'smtp.gmail.com';
        $this->mailer->SMTPAuth = true;
        $this->mailer->Username = 'shrutidodatale@gmail.com';
        $this->mailer->Password = 'afix rnev pnko pgae';
        $this->mailer->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
        $this->mailer->Port = 587;
        $this->mailer->setFrom($this->fromEmail, $this->fromName);
        $this->mailer->isHTML(true);
        $this->mailer->CharSet = 'UTF-8';
    }

    public function sendWelcomeEmail($userEmail, $userName) {
        try {
            $this->mailer->clearAddresses();
            $this->mailer->addAddress($userEmail, $userName);
            $this->mailer->Subject = 'Welcome to Railway Reservation System!';
            
            $body = "
                <div style='font-family: Arial, sans-serif; max-width: 600px; margin: 0 auto;'>
                    <h2 style='color: #2c3e50;'>Welcome to Railway Reservation System, {$userName}!</h2>
                    <p>Thank you for creating an account with us. Your account has been successfully created!</p>
                    <div style='background-color: #f8f9fa; padding: 15px; border-radius: 5px; margin: 20px 0;'>
                        <h3 style='color: #e74c3c;'>What you can do with your account:</h3>
                        <ul style='list-style-type: none; padding-left: 0;'>
                            <li style='margin: 10px 0;'>✓ Search and book train tickets</li>
                            <li style='margin: 10px 0;'>✓ View your booking history</li>
                            <li style='margin: 10px 0;'>✓ Cancel or modify bookings</li>
                            <li style='margin: 10px 0;'>✓ Order food during your journey</li>
                        </ul>
                    </div>
                    <p>You can now login to your account using your email and password.</p>
                    <p style='margin-top: 20px;'>Best regards,<br>Railway Reservation Team</p>
                </div>
            ";
            
            $this->mailer->Body = $body;
            $this->mailer->send();
            return true;
        } catch (Exception $e) {
            error_log("Welcome email sending failed: " . $e->getMessage());
            return false;
        }
    }

    public function sendLoginNotification($userEmail, $userName, $loginTime, $ipAddress) {
        try {
            $this->mailer->clearAddresses();
            $this->mailer->addAddress($userEmail, $userName);
            $this->mailer->Subject = 'New Login to Your Railway Reservation Account';
            
            $body = "
                <div style='font-family: Arial, sans-serif; max-width: 600px; margin: 0 auto;'>
                    <h2 style='color: #2c3e50;'>New Login Detected</h2>
                    <p>Hello {$userName},</p>
                    <p>We detected a new login to your Railway Reservation account.</p>
                    <div style='background-color: #f8f9fa; padding: 15px; border-radius: 5px; margin: 20px 0;'>
                        <h3 style='color: #e74c3c;'>Login Details:</h3>
                        <p><strong>Time:</strong> {$loginTime}</p>
                        <p><strong>IP Address:</strong> {$ipAddress}</p>
                    </div>
                    <p>If this wasn't you, please change your password immediately and contact our support team.</p>
                    <p style='margin-top: 20px;'>Best regards,<br>Railway Reservation Team</p>
                </div>
            ";
            
            $this->mailer->Body = $body;
            $this->mailer->send();
            return true;
        } catch (Exception $e) {
            error_log("Login notification email sending failed: " . $e->getMessage());
            return false;
        }
    }

    public function sendBookingConfirmation($userEmail, $userName, $bookingDetails) {
        try {
            $this->mailer->addAddress($userEmail, $userName);
            $this->mailer->Subject = 'Booking Confirmation - RailYatra';
            
            $body = "
                <h2>Booking Confirmation</h2>
                <p>Dear {$userName},</p>
                <p>Your booking has been confirmed. Here are your booking details:</p>
                <table style='width:100%; border-collapse:collapse;'>
                    <tr>
                        <td style='padding:10px; border:1px solid #ddd;'><strong>Booking ID:</strong></td>
                        <td style='padding:10px; border:1px solid #ddd;'>{$bookingDetails['booking_id']}</td>
                    </tr>
                    <tr>
                        <td style='padding:10px; border:1px solid #ddd;'><strong>Train:</strong></td>
                        <td style='padding:10px; border:1px solid #ddd;'>{$bookingDetails['train_name']}</td>
                    </tr>
                    <tr>
                        <td style='padding:10px; border:1px solid #ddd;'><strong>From:</strong></td>
                        <td style='padding:10px; border:1px solid #ddd;'>{$bookingDetails['from_station']}</td>
                    </tr>
                    <tr>
                        <td style='padding:10px; border:1px solid #ddd;'><strong>To:</strong></td>
                        <td style='padding:10px; border:1px solid #ddd;'>{$bookingDetails['to_station']}</td>
                    </tr>
                    <tr>
                        <td style='padding:10px; border:1px solid #ddd;'><strong>Date:</strong></td>
                        <td style='padding:10px; border:1px solid #ddd;'>{$bookingDetails['journey_date']}</td>
                    </tr>
                    <tr>
                        <td style='padding:10px; border:1px solid #ddd;'><strong>Amount:</strong></td>
                        <td style='padding:10px; border:1px solid #ddd;'>₹{$bookingDetails['amount']}</td>
                    </tr>
                </table>
                <p>Thank you for choosing RailYatra!</p>
            ";
            
            $this->mailer->Body = $body;
            $this->mailer->send();
            return true;
        } catch (Exception $e) {
            error_log("Email sending failed: " . $e->getMessage());
            return false;
        }
    }

    public function sendBookingStatusUpdate($userEmail, $userName, $bookingDetails) {
        try {
            $this->mailer->addAddress($userEmail, $userName);
            $this->mailer->Subject = 'Booking Status Update - RailYatra';
            
            $body = "
                <h2>Booking Status Update</h2>
                <p>Dear {$userName},</p>
                <p>Your booking status has been updated:</p>
                <table style='width:100%; border-collapse:collapse;'>
                    <tr>
                        <td style='padding:10px; border:1px solid #ddd;'><strong>Booking ID:</strong></td>
                        <td style='padding:10px; border:1px solid #ddd;'>{$bookingDetails['booking_id']}</td>
                    </tr>
                    <tr>
                        <td style='padding:10px; border:1px solid #ddd;'><strong>Status:</strong></td>
                        <td style='padding:10px; border:1px solid #ddd;'>{$bookingDetails['status']}</td>
                    </tr>
                    <tr>
                        <td style='padding:10px; border:1px solid #ddd;'><strong>Train:</strong></td>
                        <td style='padding:10px; border:1px solid #ddd;'>{$bookingDetails['train_name']}</td>
                    </tr>
                    <tr>
                        <td style='padding:10px; border:1px solid #ddd;'><strong>Journey Date:</strong></td>
                        <td style='padding:10px; border:1px solid #ddd;'>{$bookingDetails['journey_date']}</td>
                    </tr>
                </table>
                <p>Thank you for choosing RailYatra!</p>
            ";
            
            $this->mailer->Body = $body;
            $this->mailer->send();
            return true;
        } catch (Exception $e) {
            error_log("Email sending failed: " . $e->getMessage());
            return false;
        }
    }

    public function sendFoodOrderConfirmation($userEmail, $userName, $orderDetails) {
        try {
            $this->mailer->addAddress($userEmail, $userName);
            $this->mailer->Subject = 'Food Order Confirmation - RailYatra';
            
            $body = "
                <h2>Food Order Confirmation</h2>
                <p>Dear {$userName},</p>
                <p>Your food order has been confirmed. Here are your order details:</p>
                <table style='width:100%; border-collapse:collapse;'>
                    <tr>
                        <td style='padding:10px; border:1px solid #ddd;'><strong>Order ID:</strong></td>
                        <td style='padding:10px; border:1px solid #ddd;'>{$orderDetails['order_id']}</td>
                    </tr>
                    <tr>
                        <td style='padding:10px; border:1px solid #ddd;'><strong>Vendor:</strong></td>
                        <td style='padding:10px; border:1px solid #ddd;'>{$orderDetails['vendor_name']}</td>
                    </tr>
                    <tr>
                        <td style='padding:10px; border:1px solid #ddd;'><strong>Station:</strong></td>
                        <td style='padding:10px; border:1px solid #ddd;'>{$orderDetails['station']}</td>
                    </tr>
                    <tr>
                        <td style='padding:10px; border:1px solid #ddd;'><strong>Items:</strong></td>
                        <td style='padding:10px; border:1px solid #ddd;'>{$orderDetails['items']}</td>
                    </tr>
                    <tr>
                        <td style='padding:10px; border:1px solid #ddd;'><strong>Total Amount:</strong></td>
                        <td style='padding:10px; border:1px solid #ddd;'>₹{$orderDetails['total_amount']}</td>
                    </tr>
                </table>
                <p>Thank you for choosing RailYatra!</p>
            ";
            
            $this->mailer->Body = $body;
            $this->mailer->send();
            return true;
        } catch (Exception $e) {
            error_log("Email sending failed: " . $e->getMessage());
            return false;
        }
    }

    public function sendFoodOrderStatusUpdate($userEmail, $userName, $orderDetails) {
        try {
            $this->mailer->addAddress($userEmail, $userName);
            $this->mailer->Subject = 'Food Order Status Update - RailYatra';
            
            $body = "
                <h2>Food Order Status Update</h2>
                <p>Dear {$userName},</p>
                <p>Your food order status has been updated:</p>
                <table style='width:100%; border-collapse:collapse;'>
                    <tr>
                        <td style='padding:10px; border:1px solid #ddd;'><strong>Order ID:</strong></td>
                        <td style='padding:10px; border:1px solid #ddd;'>{$orderDetails['order_id']}</td>
                    </tr>
                    <tr>
                        <td style='padding:10px; border:1px solid #ddd;'><strong>Status:</strong></td>
                        <td style='padding:10px; border:1px solid #ddd;'>{$orderDetails['status']}</td>
                    </tr>
                    <tr>
                        <td style='padding:10px; border:1px solid #ddd;'><strong>Vendor:</strong></td>
                        <td style='padding:10px; border:1px solid #ddd;'>{$orderDetails['vendor_name']}</td>
                    </tr>
                    <tr>
                        <td style='padding:10px; border:1px solid #ddd;'><strong>Station:</strong></td>
                        <td style='padding:10px; border:1px solid #ddd;'>{$orderDetails['station']}</td>
                    </tr>
                </table>
                <p>Thank you for choosing RailYatra!</p>
            ";
            
            $this->mailer->Body = $body;
            $this->mailer->send();
            return true;
        } catch (Exception $e) {
            error_log("Email sending failed: " . $e->getMessage());
            return false;
        }
    }
} 