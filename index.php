<?php 

if (file_exists("config.php")) {
    //require_once "includes/check_login.php";

    header("Location: /user/$config_start_page");

} else {
	header("Location: /setup");
    exit();
}
