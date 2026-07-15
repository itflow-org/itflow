<?php

/*
 * ITFlow - Admin GET/POST request handler
 */

require_once "../config.php";
require_once "../functions.php";
require_once "../includes/check_login.php";

// Define a variable that we can use to only allow running post files via inclusion (prevents people/bots poking them)
define('FROM_POST_HANDLER', true);

// Dynamically load admin module POST logic
//  Every handler self-gates on its own if(isset($_POST['action'])) check,
//  so we load them all and let the matching one fire. The page filename is
//  irrelevant to dispatch - matches how agent/post.php works.
//  To add a new admin POST handler, drop a file in admin/post/.
if (isset($session_is_admin) && $session_is_admin) {
    foreach (glob("post/*.php") as $admin_module) {
        if (!preg_match('/_model\.php$/', basename($admin_module))) {
            require_once $admin_module;
        }
    }
}

// Logout is the same for user and admin
require_once "../post/logout.php";

// TODO: Find a home for these
require_once "../post/misc.php";