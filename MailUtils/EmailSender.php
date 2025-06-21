<?php
require_once __DIR__ . '/vendor/autoload.php';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\SMTP;
use PHPMailer\PHPMailer\Exception;

class EmailSender {
    private $mailer;
    private $config;

    public function __construct() {
        $this->config = require __DIR__ . '/config.php';
        $this->mailer = new PHPMailer(true);
        $this->setupMailer();
    }

    private function setupMailer() {
        try {
            // Server settings
            $this->mailer->Debugoutput = 'error_log';
            $this->mailer->SMTPDebug = $this->config['smtp_debug'];
            $this->mailer->isSMTP();
            $this->mailer->Host = $this->config['smtp_host'];
            $this->mailer->SMTPAuth = $this->config['smtp_auth'];
            $this->mailer->Username = $this->config['smtp_username'];
            $this->mailer->Password = $this->config['smtp_password'];
            $this->mailer->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
            $this->mailer->Port = $this->config['smtp_port'];
            $this->mailer->CharSet = $this->config['charset'];

            // Default sender
            $this->mailer->setFrom($this->config['from_email'], $this->config['from_name']);
        } catch (Exception $e) {
            error_log("Mailer setup failed: " . $e->getMessage());
            throw new Exception("Mailer setup failed: " . $e->getMessage());
        }
    }

    public function sendEmail($to, $subject, $body, $isHTML = true) {
        try {
            $this->mailer->clearAddresses();
            $this->mailer->addAddress($to);
            $this->mailer->isHTML($isHTML);
            $this->mailer->Subject = $subject;
            $this->mailer->Body = $body;

            if (!$this->mailer->send()) {
                error_log("Email sending failed: " . $this->mailer->ErrorInfo);
                return false;
            }
            return true;
        } catch (Exception $e) {
            error_log("Email sending failed: " . $e->getMessage());
            throw new Exception("Failed to send email: " . $e->getMessage());
        }
    }
}
?>