<?php

// Check if the app is set up
if (file_exists("config.php")) {
    require_once "config.php";

    // Check if setup is enabled (not completed)
    if (!isset($config_enable_setup) || $config_enable_setup == 1) {
        header("Location: /setup");
        exit();
    }

    // Start the session
    require_once "includes/session_init.php";

    // If user is an agent
    if (isset($_SESSION['logged'])) {
        require_once "includes/load_global_settings.php";
        header("Location: /agent/$config_start_page");
        exit();

    // If user is a client
    } elseif (isset($_SESSION['client_logged_in'])) {
        header("Location: /client/");
        exit();

    // Not logged in
    } else {
        header("Location: /login.php");
        exit();
    }

} else {
    // If config.php doesn't exist, redirect to setup
    header("Location: /setup");
    exit();
}
