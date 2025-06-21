<?php
session_start();
require_once "../../includes/config.php";
require_once "../../includes/admin_auth.php";

// Check if admin is logged in
requireAdminLogin();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $item_id = $_POST['id'];
    $is_available = $_POST['is_available'] === 'true' ? 1 : 0;

    try {
        $sql = "UPDATE food_menu SET is_available = ? WHERE id = ?";
        $stmt = $pdo->prepare($sql);
        
        if ($stmt->execute([$is_available, $item_id])) {
            echo json_encode([
                'success' => true,
                'message' => 'Item availability updated successfully'
            ]);
        } else {
            echo json_encode([
                'success' => false,
                'message' => 'Failed to update item availability'
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