<?php
session_start();
require_once "../../includes/config.php";
require_once "../../includes/admin_auth.php";

// Check if admin is logged in
requireAdminLogin();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $item_id = $_POST['id'];
    $vendor_id = $_POST['vendor_id'];

    try {
        // First check if there are any orders for this menu item
        $check_sql = "SELECT COUNT(*) as order_count FROM food_orders WHERE menu_item_id = ?";
        $check_stmt = $pdo->prepare($check_sql);
        $check_stmt->execute([$item_id]);
        $result = $check_stmt->fetch(PDO::FETCH_ASSOC);

        if ($result['order_count'] > 0) {
            echo json_encode([
                'success' => false,
                'message' => 'Cannot delete menu item as it has existing orders'
            ]);
            exit;
        }

        // If no orders exist, proceed with deletion
        $sql = "DELETE FROM food_menu WHERE id = ? AND vendor_id = ?";
        $stmt = $pdo->prepare($sql);
        
        if ($stmt->execute([$item_id, $vendor_id])) {
            echo json_encode([
                'success' => true,
                'message' => 'Menu item deleted successfully'
            ]);
        } else {
            echo json_encode([
                'success' => false,
                'message' => 'Failed to delete menu item'
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