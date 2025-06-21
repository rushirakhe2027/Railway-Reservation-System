<?php
session_start();
require_once "../../includes/config.php";
require_once "../../includes/admin_auth.php";

// Check if admin is logged in
requireAdminLogin();

$user_id = isset($_GET['id']) ? $_GET['id'] : 0;

// Get user details
$sql = "SELECT * FROM users WHERE id = ?";
$stmt = $pdo->prepare($sql);
$stmt->execute([$user_id]);
$user = $stmt->fetch(PDO::FETCH_ASSOC);

if ($user) {
    echo json_encode($user);
} else {
    echo json_encode(['error' => 'User not found']);
} 