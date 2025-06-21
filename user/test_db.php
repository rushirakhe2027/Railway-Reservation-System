<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

require_once "../includes/config.php";
require_once "../includes/db.php";

try {
    echo "<h2>Database Table Check</h2>";
    
    // Check trains table
    $sql = "DESCRIBE trains";
    $stmt = $pdo->prepare($sql);
    $stmt->execute();
    $columns = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo "<h3>Trains Table Structure:</h3>";
    echo "<pre>";
    print_r($columns);
    echo "</pre>";
    
    // Check train_stations table
    $sql = "DESCRIBE train_stations";
    $stmt = $pdo->prepare($sql);
    $stmt->execute();
    $columns = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo "<h3>Train Stations Table Structure:</h3>";
    echo "<pre>";
    print_r($columns);
    echo "</pre>";
    
    // Check station_pricing table
    $sql = "DESCRIBE station_pricing";
    $stmt = $pdo->prepare($sql);
    $stmt->execute();
    $columns = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo "<h3>Station Pricing Table Structure:</h3>";
    echo "<pre>";
    print_r($columns);
    echo "</pre>";
    
    // Check food_menu table
    $sql = "DESCRIBE food_menu";
    $stmt = $pdo->prepare($sql);
    $stmt->execute();
    $columns = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo "<h3>Food Menu Table Structure:</h3>";
    echo "<pre>";
    print_r($columns);
    echo "</pre>";

    // Check if train exists
    $train_id = isset($_GET['train_id']) ? $_GET['train_id'] : null;
    if ($train_id) {
        $sql = "SELECT * FROM trains WHERE id = :train_id";
        $stmt = $pdo->prepare($sql);
        $stmt->bindValue(':train_id', $train_id, PDO::PARAM_INT);
        $stmt->execute();
        $train = $stmt->fetch(PDO::FETCH_ASSOC);
        
        echo "<h3>Train Data:</h3>";
        echo "<pre>";
        print_r($train);
        echo "</pre>";
        
        // Check stations for this train
        $sql = "SELECT * FROM train_stations WHERE train_id = :train_id ORDER BY stop_number";
        $stmt = $pdo->prepare($sql);
        $stmt->bindValue(':train_id', $train_id, PDO::PARAM_INT);
        $stmt->execute();
        $stations = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        echo "<h3>Train Stations:</h3>";
        echo "<pre>";
        print_r($stations);
        echo "</pre>";
        
        // Check pricing for this train
        $sql = "SELECT * FROM station_pricing WHERE train_id = :train_id";
        $stmt = $pdo->prepare($sql);
        $stmt->bindValue(':train_id', $train_id, PDO::PARAM_INT);
        $stmt->execute();
        $pricing = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        echo "<h3>Station Pricing:</h3>";
        echo "<pre>";
        print_r($pricing);
        echo "</pre>";
    }

} catch (PDOException $e) {
    echo "<h2>Error:</h2>";
    echo "<pre>";
    echo "Message: " . $e->getMessage() . "\n";
    echo "Code: " . $e->getCode() . "\n";
    echo "File: " . $e->getFile() . "\n";
    echo "Line: " . $e->getLine() . "\n";
    echo "</pre>";
}
?> 