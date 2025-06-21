<?php
session_start();
require_once "../../includes/config.php";
require_once "../../includes/admin_auth.php";

// Check if admin is logged in
requireAdminLogin();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $user_id = $_POST['user_id'];
    $name = $_POST['name'];
    $email = $_POST['email'];
    $phone = $_POST['phone'];
    $status = $_POST['status'];

    // Update user information
    $sql = "UPDATE users SET 
            name = ?, 
            email = ?, 
            phone = ?,
            status = ?,
            updated_at = CURRENT_TIMESTAMP 
            WHERE id = ?";
    $stmt = $pdo->prepare($sql);
    
    try {
        if ($stmt->execute([$name, $email, $phone, $status, $user_id])) {
            echo json_encode([
                'success' => true,
                'message' => 'User updated successfully'
            ]);
        } else {
            echo json_encode([
                'success' => false,
                'message' => 'Failed to update user'
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