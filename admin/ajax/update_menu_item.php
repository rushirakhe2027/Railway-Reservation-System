<?php
session_start();
require_once "../../includes/config.php";
require_once "../../includes/admin_auth.php";

// Check if admin is logged in
requireAdminLogin();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $item_id = isset($_POST['menu_item_id']) ? $_POST['menu_item_id'] : null;
    $vendor_id = $_POST['vendor_id'];
    $item_name = $_POST['item_name'];
    $description = $_POST['description'];
    $price = $_POST['price'];
    $is_available = isset($_POST['is_available']) ? 1 : 0;

    try {
        if ($item_id) {
            // Update existing item
            $sql = "UPDATE food_menu SET 
                    item_name = ?, 
                    description = ?, 
                    price = ?,
                    is_available = ?
                    WHERE id = ? AND vendor_id = ?";
            $stmt = $pdo->prepare($sql);
            $success = $stmt->execute([$item_name, $description, $price, $is_available, $item_id, $vendor_id]);
        } else {
            // Add new item
            $sql = "INSERT INTO food_menu (vendor_id, item_name, description, price, is_available) 
                    VALUES (?, ?, ?, ?, ?)";
            $stmt = $pdo->prepare($sql);
            $success = $stmt->execute([$vendor_id, $item_name, $description, $price, $is_available]);
        }

        if ($success) {
            echo json_encode([
                'success' => true,
                'message' => $item_id ? 'Menu item updated successfully' : 'Menu item added successfully'
            ]);
        } else {
            echo json_encode([
                'success' => false,
                'message' => 'Failed to save menu item'
            ]);
        }
    } catch (PDOException $e) {
        echo json_encode([
            'success' => false,
            'message' => 'Database error: ' . $e->getMessage()
        ]);
    }
} else {
    echo json_encode([
        'success' => false,
        'message' => 'Invalid request method'
    ]);
} 