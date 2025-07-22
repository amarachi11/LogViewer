<?php
session_start();

$timeout = 60;

if (!isset($_SESSION['user'])) {
    header("Location: login.html");
    exit;
}

if (isset($_SESSION['last_activity']) && (time() - $_SESSION['last_activity']) > $timeout) {
    session_unset();
    session_destroy();
    header("Location: login.html?timeout=1");
    exit;
}

$_SESSION['last_activity'] = time(); 
?>
