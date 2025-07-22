<?php
// Database credentials
$host = "localhost";
$user = "root";
$pass = "";
$db = "logviewer";

// Connect to database
$conn = new mysqli($host, $user, $pass, $db);

// Connection logic
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

 // echo "Connected Successfully";
?>
