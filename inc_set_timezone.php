<?php

// Set Timezone to the companies timezone
// 2024-02-08 JQ - The option to set the timezone in PHP was disabled to prevent inconsistencies with MariaDB/MySQL, which utilize the system's timezone, It is now consdered best practice to set the timezone on system itself
//date_default_timezone_set($session_timezone);

// 2024-03-21 JQ - Re-Enabled Timezone setting as new PHP update does not respect System Time but defaulted to UTC

$sql_timezone = mysqli_query($mysqli, "SELECT config_timezone FROM settings WHERE company_id = 1");
$row_timezone = mysqli_fetch_array($sql_timezone);
$session_timezone = $row_timezone['config_timezone'];

date_default_timezone_set($session_timezone);