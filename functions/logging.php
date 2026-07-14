<?php

// Logging, notifications and custom action triggers
// Split from the former monolithic functions.php


function customAction($trigger, $entity) {
    $original_dir = getcwd(); // Save

    chdir(dirname(__FILE__));
    if (file_exists(__DIR__ . "/custom/custom_action_handler.php")) {
        include_once __DIR__ . "/custom/custom_action_handler.php";
    }

    chdir($original_dir); // Restore original working directory
}

function appNotify($type, $details, $action = null, $client_id = 0, $entity_id = 0) {
    global $mysqli;

    if (is_null($action)) {
        $action = "NULL"; // Without quotes for SQL NULL
    }

    $type = substr($type, 0, 200);
    $details = substr($details, 0, 1000);
    $action = substr($action, 0, 250);

    $sql = mysqli_query($mysqli, "SELECT user_id FROM users
        WHERE user_type = 1 AND user_status = 1 AND user_archived_at IS NULL
    ");

    while ($row = mysqli_fetch_assoc($sql)) {
        $user_id = intval($row['user_id']);

        mysqli_query($mysqli, "INSERT INTO notifications SET notification_type = '$type', notification = '$details', notification_action = '$action', notification_client_id = $client_id, notification_entity_id = $entity_id, notification_user_id = $user_id");
    }
}

function logAction($type, $action, $description, $client_id = 0, $entity_id = 0) {
    global $mysqli, $session_user_agent, $session_ip, $session_user_id;

    $client_id = intval($client_id);
    $entity_id = intval($entity_id);
    $session_user_id = intval($session_user_id);

    if (empty($session_user_id)) {
        $session_user_id = 0;
    }

    $type = substr($type, 0, 200);
    $action = substr($action, 0, 255);
    $description = substr($description, 0, 1000);

    mysqli_query($mysqli, "INSERT INTO logs SET log_type = '$type', log_action = '$action', log_description = '$description', log_ip = '$session_ip', log_user_agent = '$session_user_agent', log_client_id = $client_id, log_user_id = $session_user_id, log_entity_id = $entity_id");
}

function logApp($category, $type, $details) {
    global $mysqli;

    $category = mysqli_real_escape_string($mysqli, substr($category, 0, 200));
    $details = mysqli_real_escape_string($mysqli, substr($details, 0, 1000));

    mysqli_query($mysqli, "INSERT INTO app_logs SET app_log_category = '$category', app_log_type = '$type', app_log_details = '$details'");
}
