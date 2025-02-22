<?php

defined('FROM_POST_HANDLER') || die("Direct file access is not allowed");

if (isset($_POST['edit_ticket_settings'])) {

    $config_ticket_prefix = sanitizeInput($_POST['config_ticket_prefix']);
    $config_ticket_next_number = intval($_POST['config_ticket_next_number']);
    $config_ticket_email_parse = intval($_POST['config_ticket_email_parse'] ?? 0);
    $config_ticket_email_parse_unknown_senders = intval($_POST['config_ticket_email_parse_unknown_senders'] ?? 0);
    $config_ticket_default_billable = intval($_POST['config_ticket_default_billable'] ?? 0);
    $config_ticket_autoclose_hours = intval($_POST['config_ticket_autoclose_hours']);
    $config_ticket_new_ticket_notification_email = '';
    if (filter_var($_POST['config_ticket_new_ticket_notification_email'], FILTER_VALIDATE_EMAIL)) {
        $config_ticket_new_ticket_notification_email = sanitizeInput($_POST['config_ticket_new_ticket_notification_email']);
    }
    $config_ticket_default_view = intval($_POST['config_ticket_default_view']);
    $config_ticket_moving_columns = intval($_POST['config_ticket_moving_columns']);
    $config_ticket_ordering = intval($_POST['config_ticket_ordering']);
    
    mysqli_query($mysqli,"UPDATE settings SET config_ticket_prefix = '$config_ticket_prefix', config_ticket_next_number = $config_ticket_next_number, config_ticket_email_parse = $config_ticket_email_parse, config_ticket_email_parse_unknown_senders = $config_ticket_email_parse_unknown_senders, config_ticket_autoclose_hours = $config_ticket_autoclose_hours, config_ticket_new_ticket_notification_email = '$config_ticket_new_ticket_notification_email', config_ticket_default_billable = $config_ticket_default_billable, config_ticket_default_view = $config_ticket_default_view, config_ticket_moving_columns = $config_ticket_moving_columns, config_ticket_ordering = $config_ticket_ordering  WHERE company_id = 1");

    // Logging
    logAction("Settings", "Edit", "$session_name edited ticket settings");

    $_SESSION['alert_message'] = "Ticket Settings updated";

    header("Location: " . $_SERVER["HTTP_REFERER"]);

}