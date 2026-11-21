<?php

// Check user is logged in with a valid session
if (!isset($_SESSION['logged']) || !$_SESSION['logged']) {
    redirect($_SERVER["REQUEST_URI"] == "/" ? "/login.php" : "/login.php?last_visited=" . base64_encode($_SERVER["REQUEST_URI"]));
}
