<?php

require_once "../config.php";
require_once "../functions.php";
require_once "check_login.php";

header('Content-Type: application/json');

// Check for the 'id' parameter
//if (!isset($_GET['id'])) {
//    echo json_encode(['error' => 'ID missing.']);
//    exit;
//}
