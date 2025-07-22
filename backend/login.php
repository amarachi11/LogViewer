<?php
session_start();
include("db_connect_string.php");

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $email = $_POST['email'];
    $password = $_POST['password'];

    $stmt = $conn->prepare("SELECT * FROM users WHERE email=?");
    $stmt->bind_param("s", $email);
    $stmt->execute();
    $result = $stmt->get_result();
    if ($result->num_rows === 1) {
        $user = $result->fetch_assoc();
        if (password_verify($password, $user['password'])) {
            $_SESSION['user'] = $email;
            $_SESSION['last_activity'] = time(); 
            header("Location: ../home.html");
            exit;
        } else {
            header("Location: ../index.html?error=Incorrect+password");
            exit;
        }
    } else {
        header("Location: ../index.html?error=Account+not+found");
        exit;
    }
}
?>
