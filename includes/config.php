<?php
// Error reporting for development (comment out in production)
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Session configuration - MUST be before session_start()
if (session_status() == PHP_SESSION_NONE) {
    ini_set('session.gc_maxlifetime', 3600); // 1 hour
    ini_set('session.cookie_lifetime', 3600); // 1 hour
    session_start();
}

// Database configuration
define('DB_HOST', 'localhost');
define('DB_NAME', 'railway_reservation');
define('DB_USER', 'root');
define('DB_PASS', '');
define('DB_CHARSET', 'utf8mb4');
define('DB_CONNECTION_TIMEOUT', 5);
define('DB_MAX_RETRIES', 3);
define('DB_RETRY_DELAY', 1); // seconds

// PDO options for better performance and error handling
$pdoOptions = [
    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    PDO::ATTR_EMULATE_PREPARES => false,
    PDO::ATTR_TIMEOUT => DB_CONNECTION_TIMEOUT,
    PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES utf8mb4 COLLATE utf8mb4_unicode_ci",
    PDO::ATTR_PERSISTENT => true // Enable connection pooling
];

class Database {
    private static $instance = null;
    private $pdo;
    private $retryCount = 0;

    private function __construct() {
        $this->connect();
    }

    public static function getInstance() {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    private function connect() {
        global $pdoOptions;

        try {
            if ($this->retryCount >= DB_MAX_RETRIES) {
                throw new Exception("Maximum connection retries exceeded");
            }

            $this->pdo = new PDO(
                "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=" . DB_CHARSET,
                DB_USER,
                DB_PASS,
                $pdoOptions
            );

            // Reset retry count on successful connection
            $this->retryCount = 0;

        } catch (PDOException $e) {
            $this->retryCount++;
            error_log("Database Connection Error (Attempt {$this->retryCount}): " . $e->getMessage());

            if ($this->retryCount < DB_MAX_RETRIES) {
                sleep(DB_RETRY_DELAY);
                $this->connect();
            } else {
                throw new Exception("Failed to connect to database after " . DB_MAX_RETRIES . " attempts");
            }
        }
    }

    public function getConnection() {
        try {
            // Test the connection
            $this->pdo->query('SELECT 1');
            return $this->pdo;
        } catch (PDOException $e) {
            // Connection lost, try to reconnect
            error_log("Database connection lost: " . $e->getMessage());
            $this->connect();
            return $this->pdo;
        }
    }

    public function beginTransaction() {
        return $this->pdo->beginTransaction();
    }

    public function commit() {
        return $this->pdo->commit();
    }

    public function rollBack() {
        return $this->pdo->rollBack();
    }
}

// Initialize database connection
try {
    $db = Database::getInstance();
    $pdo = $db->getConnection();
} catch (Exception $e) {
    error_log("Fatal Database Error: " . $e->getMessage());
    die("A system error has occurred. Please try again later.");
}

// Cache configuration
define('CACHE_ENABLED', true);
define('CACHE_TTL', 3600); // 1 hour

class Cache {
    private static $instance = null;
    private $cache = [];

    private function __construct() {}

    public static function getInstance() {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    public function get($key) {
        if (!CACHE_ENABLED) return null;
        
        if (isset($this->cache[$key]) && $this->cache[$key]['expires'] > time()) {
            return $this->cache[$key]['data'];
        }
        return null;
    }

    public function set($key, $value, $ttl = CACHE_TTL) {
        if (!CACHE_ENABLED) return;

        $this->cache[$key] = [
            'data' => $value,
            'expires' => time() + $ttl
        ];
    }

    public function delete($key) {
        unset($this->cache[$key]);
    }

    public function clear() {
        $this->cache = [];
    }
}

// Initialize cache
$cache = Cache::getInstance();

// Rate limiting configuration
define('RATE_LIMIT_ENABLED', true);
define('RATE_LIMIT_REQUESTS', 100); // requests per window
define('RATE_LIMIT_WINDOW', 3600); // 1 hour

class RateLimiter {
    private static $instance = null;
    private $limits = [];

    private function __construct() {}

    public static function getInstance() {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    public function checkLimit($key) {
        if (!RATE_LIMIT_ENABLED) return true;

        $now = time();
        if (!isset($this->limits[$key])) {
            $this->limits[$key] = [
                'count' => 0,
                'window_start' => $now
            ];
        }

        // Reset window if expired
        if ($now - $this->limits[$key]['window_start'] >= RATE_LIMIT_WINDOW) {
            $this->limits[$key] = [
                'count' => 0,
                'window_start' => $now
            ];
        }

        // Check limit
        if ($this->limits[$key]['count'] >= RATE_LIMIT_REQUESTS) {
            return false;
        }

        $this->limits[$key]['count']++;
        return true;
    }

    public function delete($key) {
        if (isset($this->limits[$key])) {
            unset($this->limits[$key]);
            return true;
        }
        return false;
    }
}

// Initialize rate limiter
$rateLimiter = RateLimiter::getInstance();

// Helper functions
function showToast($message, $type = 'info') {
    $_SESSION['toast'] = [
        'message' => $message,
        'type' => $type
    ];
}

// Security functions
function sanitizeInput($data) {
    return htmlspecialchars(strip_tags(trim($data)));
}

function generateCSRFToken() {
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

function validateCSRFToken($token) {
    return isset($_SESSION['csrf_token']) && hash_equals($_SESSION['csrf_token'], $token);
}

// Enhanced logging function
function logActivity($type, $message, $data = []) {
    $logEntry = [
        'timestamp' => date('Y-m-d H:i:s'),
        'type' => $type,
        'message' => $message,
        'ip' => $_SERVER['REMOTE_ADDR'],
        'user_id' => $_SESSION['user_id'] ?? 'guest',
        'data' => json_encode($data)
    ];

    error_log(implode(' | ', $logEntry));
}

// Cache control headers
header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
header("Cache-Control: post-check=0, pre-check=0", false);
header("Pragma: no-cache");
?> 