<?php
session_start();
require_once "../includes/config.php";
require_once "../includes/db.php";

// Enable error logging
error_reporting(E_ALL);
ini_set('display_errors', 0);
ini_set('log_errors', 1);

// Set content type to JSON
header('Content-Type: application/json');

// Log incoming request
error_log("get_fare.php - Request parameters: " . print_r($_GET, true));

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    error_log("get_fare.php - User not logged in");
    echo json_encode(['success' => false, 'message' => 'User not logged in']);
    exit;
}

// Validate input parameters
if (!isset($_GET['train_id']) || !isset($_GET['from']) || !isset($_GET['to'])) {
    error_log("get_fare.php - Missing parameters");
    echo json_encode(['success' => false, 'message' => 'Missing required parameters']);
    exit;
}

$train_id = intval($_GET['train_id']);
$from_station = trim($_GET['from']);
$to_station = trim($_GET['to']);

error_log("get_fare.php - Processing request for train_id: $train_id, from: $from_station, to: $to_station");

try {
    // First get all stations in order for this train
    $sql = "SELECT station_name, stop_number 
            FROM train_stations 
            WHERE train_id = :train_id 
            ORDER BY stop_number ASC";
    
    $stmt = $pdo->prepare($sql);
    $stmt->bindValue(':train_id', $train_id, PDO::PARAM_INT);
    $stmt->execute();
    $stations = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Find the stop numbers for source and destination
    $from_stop = null;
    $to_stop = null;
    foreach ($stations as $station) {
        if ($station['station_name'] === $from_station) {
            $from_stop = $station['stop_number'];
        }
        if ($station['station_name'] === $to_station) {
            $to_stop = $station['stop_number'];
        }
    }
    
    if ($from_stop === null || $to_stop === null) {
        throw new Exception("Invalid stations selected");
    }

    // Get all intermediate stations between source and destination
    $intermediate_stations = [];
    $start = min($from_stop, $to_stop);
    $end = max($from_stop, $to_stop);
    
    for ($i = $start; $i < $end; $i++) {
        $intermediate_stations[] = [
            'from' => $stations[$i - 1]['station_name'],
            'to' => $stations[$i]['station_name']
        ];
    }

    // Calculate total fare by summing up all intermediate segments
    $total_fare = 0;
    foreach ($intermediate_stations as $segment) {
        $sql = "SELECT base_fare 
                FROM station_pricing 
                WHERE train_id = :train_id 
                AND from_station = :from_station 
                AND to_station = :to_station";
                
        $stmt = $pdo->prepare($sql);
        $stmt->bindValue(':train_id', $train_id, PDO::PARAM_INT);
        $stmt->bindValue(':from_station', $segment['from'], PDO::PARAM_STR);
        $stmt->bindValue(':to_station', $segment['to'], PDO::PARAM_STR);
        $stmt->execute();
        
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        if ($result && isset($result['base_fare'])) {
            $total_fare += floatval($result['base_fare']);
        }
    }
    
    error_log("get_fare.php - Calculated total fare: $total_fare for segments: " . print_r($intermediate_stations, true));
    
    if ($total_fare > 0) {
        echo json_encode([
            'success' => true,
            'fare' => $total_fare,
            'segments' => $intermediate_stations,
            'source' => 'cumulative'
        ]);
    } else {
        // If no fare found, get the minimum fare for this train
        $sql = "SELECT MIN(base_fare) as min_fare 
                FROM station_pricing 
                WHERE train_id = :train_id";
        $stmt = $pdo->prepare($sql);
        $stmt->bindValue(':train_id', $train_id, PDO::PARAM_INT);
        $stmt->execute();
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        
        $min_fare = floatval($result['min_fare'] ?? 0);
        error_log("get_fare.php - Using minimum fare: $min_fare");
        
        echo json_encode([
            'success' => true,
            'fare' => $min_fare * abs($to_stop - $from_stop), // Multiply by number of segments
            'source' => 'minimum'
        ]);
    }
    
} catch (Exception $e) {
    error_log("Error in get_fare.php: " . $e->getMessage());
    error_log("Stack trace: " . $e->getTraceAsString());
    
    echo json_encode([
        'success' => false,
        'message' => 'An error occurred while calculating the fare',
        'error' => $e->getMessage()
    ]);
}
?> 