<?php
require_once 'config.php';

class SessionManager {
    private static $instance = null;
    private $isStarted = false;
    
    const SESSION_LIFETIME = 3600; // 1 hour
    const SESSION_NAME = 'RAILYATRA_SESSION';
    const SESSION_REGENERATE_TIME = 300; // 5 minutes
    
    private function __construct() {
        if (session_status() === PHP_SESSION_NONE) {
            $this->configure();
            session_name(self::SESSION_NAME);
            session_start();
            $this->isStarted = true;
        }
    }
    
    public static function getInstance() {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    private function configure() {
        if (session_status() === PHP_SESSION_NONE) {
            // Set secure session parameters
            ini_set('session.use_strict_mode', 1);
            ini_set('session.use_only_cookies', 1);
            ini_set('session.cookie_httponly', 1);
            ini_set('session.cookie_secure', isset($_SERVER['HTTPS']));
            ini_set('session.cookie_samesite', 'Lax');
            ini_set('session.gc_maxlifetime', self::SESSION_LIFETIME);
            ini_set('session.cookie_lifetime', self::SESSION_LIFETIME);
        }
    }
    
    public function start() {
        if ($this->isStarted) {
            return true;
        }
        
        if (session_status() === PHP_SESSION_ACTIVE) {
            $this->isStarted = true;
            return true;
        }
        
        if (session_start()) {
            $this->isStarted = true;
            $this->validate();
            return true;
        }
        
        return false;
    }
    
    private function validate() {
        if (!isset($_SESSION['created'])) {
            $_SESSION['created'] = time();
        }
        
        if (!isset($_SESSION['last_activity'])) {
            $_SESSION['last_activity'] = time();
        }
        
        // Check session age
        if (time() - $_SESSION['created'] > self::SESSION_LIFETIME) {
            $this->destroy();
            return false;
        }
        
        // Check for session hijacking
        if (isset($_SESSION['ip']) && $_SESSION['ip'] !== $_SERVER['REMOTE_ADDR']) {
            $this->destroy();
            return false;
        }
        
        if (isset($_SESSION['user_agent']) && $_SESSION['user_agent'] !== $_SERVER['HTTP_USER_AGENT']) {
            $this->destroy();
            return false;
        }
        
        // Regenerate session ID periodically
        if (time() - $_SESSION['last_activity'] > self::SESSION_REGENERATE_TIME) {
            $this->regenerate();
        }
        
        $_SESSION['last_activity'] = time();
        $_SESSION['ip'] = $_SERVER['REMOTE_ADDR'];
        $_SESSION['user_agent'] = $_SERVER['HTTP_USER_AGENT'];
        
        return true;
    }
    
    public function regenerate() {
        if (session_regenerate_id(true)) {
            $_SESSION['last_activity'] = time();
            return true;
        }
        return false;
    }
    
    public function destroy() {
        if (session_status() === PHP_SESSION_ACTIVE) {
            session_unset();
            session_destroy();
            setcookie(session_name(), '', time() - 3600, '/');
        }
        $this->isStarted = false;
    }
    
    public function isAuthenticated() {
        return isset($_SESSION['user_id']) && !empty($_SESSION['user_id']);
    }
    
    public function getUserId() {
        return $_SESSION['user_id'] ?? null;
    }
    
    public function getUserType() {
        return $_SESSION['user_type'] ?? null;
    }
    
    public function set($key, $value) {
        $_SESSION[$key] = $value;
    }
    
    public function get($key, $default = null) {
        return $_SESSION[$key] ?? $default;
    }
    
    public function remove($key) {
        unset($_SESSION[$key]);
    }
    
    public function clear() {
        session_unset();
    }
    
    public function writeClose() {
        if ($this->isStarted) {
            session_write_close();
            $this->isStarted = false;
        }
    }
}

// Initialize session manager
$sessionManager = SessionManager::getInstance();
$sessionManager->start(); 