<?php

if (session_status() === PHP_SESSION_NONE) {
    // HTTP Only cookies
    ini_set("session.cookie_httponly", true);
    ini_set("session.cookie_samesite", "Lax");

    // Refuse to adopt a session ID the server never issued
    ini_set("session.use_strict_mode", 1);

    if (!isset($config_https_only) || $config_https_only) {
        // Tell client to only send cookie(s) over HTTPS
        ini_set("session.cookie_secure", true);
    }
    
    session_start();

}
