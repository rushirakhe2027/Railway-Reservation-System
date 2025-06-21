<?php
require_once "../includes/db.php";

try {
    $sql = "ALTER TABLE trains ADD COLUMN IF NOT EXISTS delay_duration INT DEFAULT NULL COMMENT 'Delay duration in minutes'";
    $pdo->exec($sql);
    echo "Successfully added delay_duration column to trains table";
} catch (PDOException $e) {
    echo "Error: " . $e->getMessage();
} 