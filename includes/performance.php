<?php
class Performance {
    private static $instance = null;
    private $startTime;
    private $queries = [];
    private $markers = [];
    
    private function __construct() {
        $this->startTime = microtime(true);
    }
    
    public static function getInstance() {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    public function logQuery($sql, $params = [], $time = 0) {
        $this->queries[] = [
            'sql' => $sql,
            'params' => $params,
            'time' => $time,
            'timestamp' => microtime(true)
        ];
    }
    
    public function addMarker($name) {
        $this->markers[$name] = microtime(true);
    }
    
    public function getExecutionTime($marker = null) {
        if ($marker && isset($this->markers[$marker])) {
            return round((microtime(true) - $this->markers[$marker]) * 1000, 2);
        }
        return round((microtime(true) - $this->startTime) * 1000, 2);
    }
    
    public function getQueryCount() {
        return count($this->queries);
    }
    
    public function getTotalQueryTime() {
        return array_sum(array_column($this->queries, 'time'));
    }
    
    public function getPerformanceMetrics() {
        return [
            'total_time' => $this->getExecutionTime(),
            'query_count' => $this->getQueryCount(),
            'total_query_time' => $this->getTotalQueryTime(),
            'memory_usage' => round(memory_get_peak_usage() / 1024 / 1024, 2)
        ];
    }
    
    public function logMetrics() {
        $metrics = $this->getPerformanceMetrics();
        $log = sprintf(
            "[%s] Execution: %sms, Queries: %d, Query Time: %sms, Memory: %sMB",
            date('Y-m-d H:i:s'),
            $metrics['total_time'],
            $metrics['query_count'],
            $metrics['total_query_time'],
            $metrics['memory_usage']
        );
        
        if (defined('PERFORMANCE_LOG') && PERFORMANCE_LOG) {
            error_log($log);
        }
        
        return $metrics;
    }
}

// Initialize performance monitoring
$performance = Performance::getInstance();
?> 