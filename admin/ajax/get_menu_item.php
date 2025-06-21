<?php
session_start();
require_once "../../includes/config.php";
require_once "../../includes/admin_auth.php";

// Check if admin is logged in
requireAdminLogin();

$item_id = isset($_GET['id']) ? $_GET['id'] : 0;

// Get menu item details
$sql = "SELECT * FROM food_menu WHERE id = ?";
$stmt = $pdo->prepare($sql);
$stmt->execute([$item_id]);
$item = $stmt->fetch(PDO::FETCH_ASSOC);

if ($item) {
    echo json_encode($item);
} else {
    echo json_encode(['error' => 'Menu item not found']);
} 