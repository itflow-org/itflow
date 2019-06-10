<?php

$dbhost = "tiny";
$dbusername = "tryme";
$dbpassword = "overme";
$database="what";

$mysqli = mysqli_connect($dbhost, $dbusername, $dbpassword, $database);

include("get_settings.php");

?>