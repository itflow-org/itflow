<?php

/*
 * ITFlow - Main GET/POST request handler
 */

require_once "config.php";

require_once "functions.php";

require_once "check_login.php";

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
if (str_contains($module, 'admin') && isset($session_is_admin) && $session_is_admin) {
    // As (almost) every admin setting is only changed from 1 page, we can dynamically load the relevant logic inside this single admin check IF statement
    //  To add a new admin POST request handler, add a file named after the admin page
    //    e.g. changes made on the page http://itflow/admin_ticket_statues.php will load the page post/admin/admin_ticket_statues.php to handle the changes

    require_once "post/admin/$module.php";

} elseif (str_contains($module, 'xcustom')) {
    // Dynamically load any custom POST logic

    require_once "post/xcustom/$module.php";

} else {

    // Load all module POST logic
    //  Loads everything in post/user/
    //  Eventually, it would be nice to only specifically load what we need like we do for admins

    foreach (glob("post/user/*.php") as $user_module) {
        if (!preg_match('/_model\.php$/', basename($user_module))) {
            require_once $user_module;
        }
    }

}

// Logout is the same for user and admin
require_once "post/logout.php";

// TODO: Find a home for these

require_once "post/ai.php";

require_once "post/misc.php";

