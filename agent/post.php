<?php
/*
 * ITFlow - Agent GET/POST request handler
 */

require_once __DIR__ . "/../config.php";
require_once __DIR__ . "/../functions.php";
require_once __DIR__ . "/../includes/check_login.php";

// Only allow running post files via inclusion (prevents people/bots poking them directly)
define('FROM_POST_HANDLER', true);

// Load all agent module POST logic
// TODO: selectively load per-module like admin does, keyed off request path (not referer)
foreach (glob(__DIR__ . "/post/*.php") as $user_module) {
    if (!str_ends_with($user_module, '_model.php')) {
        require_once $user_module;
    }
}

// Logout is shared between portals
require_once __DIR__ . "/../post/logout.php";
require_once __DIR__ . "/../post/misc.php";
