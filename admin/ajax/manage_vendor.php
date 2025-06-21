<?php
session_start();
require_once "../../includes/config.php";
require_once "../../includes/admin_auth.php";

// Check if admin is logged in
requireAdminLogin();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'];
    $response = ['success' => false, 'message' => ''];

    try {
        switch ($action) {
            case 'add':
                $name = $_POST['name'];
                $station = $_POST['station'];
                $contact = $_POST['contact'];
                $status = $_POST['status'];

                $sql = "INSERT INTO food_vendors (name, station_name, contact_number, status) VALUES (?, ?, ?, ?)";
                $stmt = $pdo->prepare($sql);
                
                if ($stmt->execute([$name, $station, $contact, $status])) {
                    $response = [
                        'success' => true,
                        'message' => 'Vendor added successfully',
                        'vendor_id' => $pdo->lastInsertId()
                    ];
                } else {
                    $response['message'] = 'Failed to add vendor';
                }
                break;

            case 'update':
                $vendor_id = $_POST['vendor_id'];
                $name = $_POST['name'];
                $station = $_POST['station'];
                $contact = $_POST['contact'];
                $status = $_POST['status'];

                $sql = "UPDATE food_vendors SET name = ?, station_name = ?, contact_number = ?, status = ? WHERE id = ?";
                $stmt = $pdo->prepare($sql);
                
                if ($stmt->execute([$name, $station, $contact, $status, $vendor_id])) {
                    $response = [
                        'success' => true,
                        'message' => 'Vendor updated successfully'
                    ];
                } else {
                    $response['message'] = 'Failed to update vendor';
                }
                break;

            case 'delete':
                $vendor_id = $_POST['vendor_id'];

                // Check if vendor has any menu items with orders
                $check_sql = "SELECT COUNT(*) as order_count FROM food_orders fo 
                            JOIN food_menu fm ON fo.menu_item_id = fm.id 
                            WHERE fm.vendor_id = ?";
                $check_stmt = $pdo->prepare($check_sql);
                $check_stmt->execute([$vendor_id]);
                $result = $check_stmt->fetch(PDO::FETCH_ASSOC);

                if ($result['order_count'] > 0) {
                    $response['message'] = 'Cannot delete vendor as they have existing orders';
                    break;
                }

                // Delete menu items first
                $delete_menu_sql = "DELETE FROM food_menu WHERE vendor_id = ?";
                $delete_menu_stmt = $pdo->prepare($delete_menu_sql);
                $delete_menu_stmt->execute([$vendor_id]);

                // Then delete the vendor
                $delete_vendor_sql = "DELETE FROM food_vendors WHERE id = ?";
                $delete_vendor_stmt = $pdo->prepare($delete_vendor_sql);
                
                if ($delete_vendor_stmt->execute([$vendor_id])) {
                    $response = [
                        'success' => true,
                        'message' => 'Vendor deleted successfully'
                    ];
                } else {
                    $response['message'] = 'Failed to delete vendor';
                }
                break;

            default:
                $response['message'] = 'Invalid action';
                break;
        }
    } catch (PDOException $e) {
        $response['message'] = 'Database error: ' . $e->getMessage();
    }

    echo json_encode($response);
} else {
    echo json_encode([
        'success' => false,
        'message' => 'Invalid request method'
    ]);
} 