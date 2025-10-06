<?php

require_once $_SERVER['DOCUMENT_ROOT'] . '/config.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/functions.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/includes/check_login.php';

header('Content-Type: application/json');

// Check for the 'id' parameter
//if (!isset($_GET['id'])) {
//    echo json_encode(['error' => 'ID missing.']);
//    exit;
//}
