<?php

// Check user is logged in with a valid session
if (!isset($_SESSION['logged']) || !$_SESSION['logged']) {
    if ($_SERVER["REQUEST_URI"] == "/") {
        header("Location: /login.php");
    } else {
        header("Location: /login.php?last_visited=" . base64_encode($_SERVER["REQUEST_URI"]) );
    }
    exit;
}
