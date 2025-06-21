<?php
// Email Configuration
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\SMTP;
use PHPMailer\PHPMailer\Exception;

return [
    'smtp_host' => 'smtp.gmail.com',
    'smtp_port' => 587,
    'smtp_secure' => 'tls',
    'smtp_auth' => true,
    'smtp_username' => 'shrutidodatale@gmail.com',
    'smtp_password' => 'afix rnev pnko pgae',
    'from_email' => 'shrutidodatale@gmail.com',
    'from_name' => 'RailYatra',
    'reply_to' => 'shrutidodatale@gmail.com',
    'charset' => 'UTF-8',
    'smtp_debug' => 0
];
?> 