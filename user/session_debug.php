<?php
session_start();
require_once "../includes/config.php";
require_once "../includes/db.php";

// Enable error reporting
error_reporting(E_ALL);
ini_set('display_errors', 1);

echo "<h1>Session Debug Information</h1>";
echo "<pre>";
print_r($_SESSION);
echo "</pre>";

echo "<h2>Is Logged In Check</h2>";
echo "isLoggedIn() function result: " . (isLoggedIn() ? 'true' : 'false') . "<br>";
echo "user_id set: " . (isset($_SESSION['user_id']) ? 'true' : 'false') . "<br>";
echo "logged_in set: " . (isset($_SESSION['logged_in']) ? 'true' : 'false') . "<br>";
echo "logged_in value: " . ($_SESSION['logged_in'] ?? 'not set') . "<br>";
echo "user_type: " . ($_SESSION['user_type'] ?? 'not set') . "<br>";

echo "<h2>Server Information</h2>";
echo "Session ID: " . session_id() . "<br>";
echo "Session Name: " . session_name() . "<br>";
echo "Cookie Path: " . ini_get('session.cookie_path') . "<br>";
echo "Session Cookie lifetime: " . ini_get('session.cookie_lifetime') . "<br>";
echo "Session Save Path: " . session_save_path() . "<br>";

echo "<h2>Current URL</h2>";
echo "Current URL: " . $_SERVER['REQUEST_URI'] . "<br>";

echo "<h2>Navigation Links for Testing</h2>";
echo "<a href='search_trains.php'>Go to Search Trains</a><br>";
echo "<a href='book_train.php?train_id=14'>Try to Book Train ID 14</a><br>";
echo "<a href='../logout.php'>Logout</a><br>";
?> 