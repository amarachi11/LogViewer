<?php
header('Content-Type: application/json');
include("db_connect_string.php");

$response = ['status' => 'danger', 'message' => 'Unknown error'];

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $email = $conn->real_escape_string($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';
    $confirm = $_POST['confirm'] ?? '';

    if (!$email || !$password || !$confirm) {
        $response = ['status' => 'warning', 'message' => 'All fields are required.'];
    } elseif ($password !== $confirm) {
        $response = ['status' => 'danger', 'message' => 'Passwords do not match.'];
    } else {
        $check = $conn->query("SELECT * FROM users WHERE email='$email'");
        if ($check->num_rows > 0) {
            $response = ['status' => 'warning', 'message' => 'Email already exists.'];
        } else {
            $hashed = password_hash($password, PASSWORD_DEFAULT);
            $sql = "INSERT INTO users (email, password) VALUES ('$email', '$hashed')";
            if ($conn->query($sql)) {
                $response = ['status' => 'success', 'message' => 'Signup successful. <a href="index.html">Login now</a>'];
            } else {
                $response = ['status' => 'danger', 'message' => 'Database error: ' . $conn->error];
            }
        }
    }
}

echo json_encode($response);
?>
