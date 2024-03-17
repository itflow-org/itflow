<?php

// Role check failed wording
DEFINE("WORDING_ROLECHECK_FAILED", "You are not permitted to do that!");

//Function used to get rest of functions, can also be used for other folders.
function requireOnceAll($functionsPath) {
    foreach (glob($functionsPath . '/*.php') as $file) {
        require_once($file);
    }
}

// Other functions are categorized in different files
// Load all functions

// Set the path to the functions folder
$functionsPath = 'functions/';
// Require Once All in the functions folder
requireOnceAll($functionsPath);