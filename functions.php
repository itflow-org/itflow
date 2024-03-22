<?php

// Role check failed wording
DEFINE("WORDING_ROLECHECK_FAILED", "You are not permitted to do that!");

//Function used to get rest of functions, can also be used for other folders.
function requireOnceAll($functionsPath) {
    foreach (glob($functionsPath . '/*.php') as $file) {
        require_once($file);
    }
}

// Load all functions -----------------------------------------
// Other functions are categorized in different files

// Path to functions folder using the server root path
if (isset($cron)) { 
    $functionsPath = $_SERVER['DOCUMENT_ROOT'] . "/functions";
} else { 
    $functionsPath = dirname(__FILE__) . "/functions";
}

// Require Once All in the functions folder
requireOnceAll($functionsPath);
