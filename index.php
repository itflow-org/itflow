<?php


// App setup is complete?
if (file_exists("config.php")) {
    require_once "config.php";
    require_once "includes/session_init.php";

    // If they are an app user, send them to their start page
    if (isset($_SESSION['logged'])) {
        require_once "includes/load_global_settings.php";
        header("Location: /agent/$config_start_page");

    // If they're a client, send them to the client area
    } elseif (isset($_SESSION['client_logged_in'])) {
        header("Location: /client/");

    // Else, require login
    } else {
        header("Location: /login.php");
    }


// Installation needs to be completed
} else {
	header("Location: /setup");
    exit();
}
