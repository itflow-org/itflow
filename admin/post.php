<?php
/*
 * ITFlow - Admin GET/POST request handler
 */

require_once __DIR__ . "/../config.php";
require_once __DIR__ . "/../functions.php";
require_once __DIR__ . "/../includes/check_login.php";

// Only allow running post files via inclusion (prevents people/bots poking them directly)
define('FROM_POST_HANDLER', true);

// Load all admin module POST logic
if (!empty($session_is_admin)) {
    foreach (glob(__DIR__ . "/post/*.php") as $admin_module) {
        if (!str_ends_with($admin_module, '_model.php')) {
            require_once $admin_module;
        }
    }
}

// Logout is shared between portals
require_once __DIR__ . "/../post/logout.php";
require_once __DIR__ . "/../post/misc.php";
