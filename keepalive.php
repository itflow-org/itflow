<?php

// Keep PHP sessions alive
// Receives requests via AJAX in the background every 8 mins to prevent PHP garbage collection ending sessions
//  See footer.php & js/keepalive.js

require_once __DIR__ . "/config.php";

require_once __DIR__ . "/includes/session_init.php";
session_write_close();
