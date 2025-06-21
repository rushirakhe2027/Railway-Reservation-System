<?php
require_once "../includes/config.php";

header('Content-Type: application/json');

if (!isset($_POST['train_id']) || !isset($_POST['from_station']) || !isset($_POST['to_station'])) {
    echo json_encode(['error' => 'Missing required parameters']);
    exit;
}

$train_id = $_POST['train_id'];
$from_station = $_POST['from_station'];
$to_station = $_POST['to_station'];

try {
    // First try to get direct fare
    $sql = "SELECT base_fare, distance FROM station_pricing 
            WHERE train_id = ? 
            AND ((from_station = ? AND to_station = ?) 
            OR (from_station = ? AND to_station = ?))
            LIMIT 1";
            
    $stmt = $pdo->prepare($sql);
    $stmt->execute([$train_id, $from_station, $to_station, $to_station, $from_station]);
    $result = $stmt->fetch();

    if ($result) {
        echo json_encode([
            'fare' => floatval($result['base_fare']),
            'distance' => floatval($result['distance'])
        ]);
        exit;
    }

    // If no direct fare, calculate based on intermediate stations
    $sql = "WITH RECURSIVE route_stations AS (
        -- Base case: direct connection
        SELECT from_station, to_station, base_fare, distance, 1 as hops,
               ARRAY[from_station, to_station] as path
        FROM station_pricing
        WHERE train_id = ?
        
        UNION ALL
        
        -- Recursive case: add one more station
        SELECT r.from_station, s.to_station, 
               r.base_fare + s.base_fare as base_fare,
               r.distance + s.distance as distance,
               r.hops + 1 as hops,
               r.path || s.to_station
        FROM route_stations r
        JOIN station_pricing s ON r.to_station = s.from_station
        WHERE s.train_id = ? AND r.hops < 5
        AND NOT s.to_station = ANY(r.path)  -- Prevent cycles
    )
    SELECT base_fare, distance
    FROM route_stations
    WHERE from_station = ? AND to_station = ?
    ORDER BY hops ASC
    LIMIT 1";

    $stmt = $pdo->prepare($sql);
    $stmt->execute([$train_id, $train_id, $from_station, $to_station]);
    $result = $stmt->fetch();

    if ($result) {
        echo json_encode([
            'fare' => floatval($result['base_fare']),
            'distance' => floatval($result['distance'])
        ]);
    } else {
        // If still no fare found, get the default fare for the train
        $sql = "SELECT base_fare 
                FROM station_pricing 
                WHERE train_id = ? 
                AND from_station = (
                    SELECT station_name 
                    FROM train_stations 
                    WHERE train_id = ? 
                    ORDER BY stop_number ASC 
                    LIMIT 1
                )
                AND to_station = (
                    SELECT station_name 
                    FROM train_stations 
                    WHERE train_id = ? 
                    ORDER BY stop_number DESC 
                    LIMIT 1
                )";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([$train_id, $train_id, $train_id]);
        $result = $stmt->fetch();

        echo json_encode([
            'fare' => floatval($result['base_fare'] ?? 0),
            'distance' => 0
        ]);
    }
} catch (Exception $e) {
    echo json_encode([
        'error' => 'Failed to calculate fare: ' . $e->getMessage(),
        'fare' => 0
    ]);
} 