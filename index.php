<?php 

if (file_exists("config.php")) {
    //require_once "includes/check_login.php";
 
    if (isset($config_start_page)) {
        header("Location: /user/$config_start_page");
    } else {
        header("Location: /user");
    }
} else {
	header("Location: setup");
    exit();
}

