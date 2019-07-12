<?php

$dbhost = "localhost";
$dbusername = "admin_crm";
$dbpassword = "password";
$database = "admin_crm";

$mysqli = mysqli_connect($dbhost, $dbusername, $dbpassword, $database);

include("get_settings.php");

?>