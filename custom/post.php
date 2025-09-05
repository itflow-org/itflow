<?php

/*
 * ITFlow - Custom GET/POST request handler
 */

require_once "../config.php";
require_once "../functions.php";
require_once "../includes/check_login.php";

// Define a variable that we can use to only allow running post files via inclusion (prevents people/bots poking them)
define('FROM_POST_HANDLER', true);


// Determine which files we should load

// Parse URL & get the path
$path = parse_url($_SERVER['HTTP_REFERER'], PHP_URL_PATH);

// Get the base name (the page name)
$module = explode(".", basename($path))[0];

// Strip off any _details bits
$module = str_ireplace('_details', '', $module);

// Dynamically load admin-related module POST logic
if (str_contains($module, 'custom')) {
    // Dynamically load any custom POST logic

    include_once "post/$module.php";

}

// Logout is the same for user and admin
require_once "../post/logout.php";

// TODO: Find a home for these

require_once "../post/ai.php";
require_once "../post/misc.php";
