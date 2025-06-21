# Railway Reservation System

A comprehensive railway reservation system built with PHP, MySQL, and modern web technologies.

## Features

### User Features
1. User Registration and Authentication
   - Secure login/register system
   - Email verification
   - Password recovery
   - Profile management

2. Train Search and Booking
   - Smart station search with autocomplete
   - Real-time train availability
   - Dynamic pricing based on route
   - Multiple passenger booking
   - Food ordering during journey

3. Booking Management
   - View all bookings
   - Cancel bookings
   - Track booking status
   - View booking history

4. Food Ordering
   - Station-wise food vendors
   - Menu items with prices
   - Order tracking
   - Order history

### Admin Features
1. Train Management
   - Add/Edit/Delete trains
   - Manage train routes
   - Set station-wise pricing
   - Update train status

2. Station Management
   - Add/Edit/Delete stations
   - Manage station routes
   - Set station timings

3. Food Vendor Management
   - Add/Edit/Delete vendors
   - Manage menu items
   - Set pricing

4. Booking Management
   - View all bookings
   - Update booking status
   - Process refunds
   - Generate reports

## Email Notifications
The system sends email notifications for various events:

1. User Registration
   - Welcome email
   - Email verification link

2. Booking Updates
   - Booking confirmation
   - Booking cancellation
   - Status changes (Pending, Accepted, Cancelled)
   - Payment confirmation

3. Food Orders
   - Order confirmation
   - Order status updates
   - Delivery updates

4. System Updates
   - Train schedule changes
   - Route modifications
   - Price updates

## Setup Instructions

1. Database Setup
   ```sql
   - Import railway_reservation.sql to your MySQL database
   - Configure database connection in includes/config.php
   ```

2. Email Configuration
   ```php
   SMTP_HOST = smtp.gmail.com
   SMTP_PORT = 587
   SMTP_USERNAME = your mail
   SMTP_PASSWORD = your pass
   ```

3. Server Requirements
   - PHP 7.4 or higher
   - MySQL 5.7 or higher
   - Apache/Nginx web server
   - mod_rewrite enabled
   - PHP extensions:
     - PDO
     - mysqli
     - mbstring
     - json
     - openssl

4. Installation Steps
   ```bash
   1. Clone the repository
   2. Configure your web server to point to the project directory
   3. Import the database schema
   4. Update database credentials in includes/config.php
   5. Update email settings in includes/email.php
   6. Set proper permissions for uploads directory
   ```

5. Directory Structure
   ```
   railway_reservation/
   ├── admin/
   │   ├── add_train.php
   │   ├── manage_trains.php
   │   └── ...
   ├── user/
   │   ├── dashboard.php
   │   ├── search_trains.php
   │   └── ...
   ├── includes/
   │   ├── config.php
   │   ├── functions.php
   │   └── email.php
   ├── assets/
   │   ├── css/
   │   ├── js/
   │   └── images/
   └── uploads/
   ```

## Security Features
1. Password Hashing
2. SQL Injection Prevention
3. XSS Protection
4. CSRF Protection
5. Session Management
6. Input Validation
7. Secure File Upload

## Remaining Tasks
1. Implement real-time notifications using WebSocket
2. Add payment gateway integration
3. Implement train delay notifications
4. Add mobile app integration
5. Implement multi-language support
6. Add booking cancellation refund system
7. Implement seat selection feature
8. Add booking history export feature
9. Implement user feedback system
10. Add admin dashboard analytics

## Contributing
1. Fork the repository
2. Create your feature branch
3. Commit your changes
4. Push to the branch
5. Create a new Pull Request

## License
This project is licensed under the MIT License - see the LICENSE file for details. 
