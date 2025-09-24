<?php

// Keep PHP sessions alive
// Receives requests via AJAX in the background every 8 mins to prevent PHP garbage collection ending sessions
//  See footer.php & js/keepalive.js

session_start();
session_write_close();
