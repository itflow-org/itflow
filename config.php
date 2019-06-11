<?php

$dbhost = "localhost";
$dbusername = "root";
$dbpassword = "password";
$database = "pittpc";

$mysqli = mysqli_connect($dbhost, $dbusername, $dbpassword, $database);

include("get_settings.php");

?>