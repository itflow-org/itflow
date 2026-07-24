<?php

// Keep PHP sessions alive
// Receives requests via AJAX in the background every 8 mins to prevent PHP garbage collection ending sessions
//  See footer.php & js/keepalive.js

require_once __DIR__ . "/config.php";

ini_set("session.cookie_httponly", true);
ini_set("session.cookie_samesite", "Lax");
if ($config_https_only) {
    ini_set("session.cookie_secure", true);
}
session_start();
session_write_close();
