<?php

/*
 * ITFlow - GET/POST request handler for Contract Templates
 */

defined('FROM_POST_HANDLER') || die("Direct file access is not allowed");

if (isset($_POST['add_contract_template'])) {

    // Sanitize text inputs
    $name = sanitizeInput($_POST['contract_template_name']);
    $type = sanitizeInput($_POST['contract_template_type']);
    $update_frequency = sanitizeInput($_POST['contract_template_update_frequency']);
    $support_hours = sanitizeInput($_POST['contract_template_support_hours']);
    $details = mysql_escape_string($mysqli, $_POST['contract_template_details']);

    // Numeric fields cast to integer
    $sla_low_resp = intval($_POST['sla_low_response_time']);
    $sla_med_resp = intval($_POST['sla_medium_response_time']);
    $sla_high_resp = intval($_POST['sla_high_response_time']);
    $sla_low_res = intval($_POST['sla_low_resolution_time']);
    $sla_med_res = intval($_POST['sla_medium_resolution_time']);
    $sla_high_res = intval($_POST['sla_high_resolution_time']);
    $hourly_rate = intval($_POST['contract_template_hourly_rate']);
    $after_hours_rate = intval($_POST['contract_template_after_hours_hourly_rate']);
    $net_terms = intval($_POST['contract_template_net_terms']);

    // Insert into database (numbers not quoted)
    mysqli_query($mysqli, "
        INSERT INTO contract_templates SET
        contract_template_name = '$name',
        contract_template_description = '$description',
        contract_template_details = '$details',
        contract_template_type = '$type',
        contract_template_update_frequency = '$update_frequency',
        sla_low_response_time = $sla_low_resp,
        sla_medium_response_time = $sla_med_resp,
        sla_high_response_time = $sla_high_resp,
        sla_low_resolution_time = $sla_low_res,
        sla_medium_resolution_time = $sla_med_res,
        sla_high_resolution_time = $sla_high_res,
        contract_template_hourly_rate = $hourly_rate,
        contract_template_after_hours_hourly_rate = $after_hours_rate,
        contract_template_support_hours = '$support_hours',
        contract_template_net_terms = $net_terms,
        contract_template_created_by = $session_user_id,
        contract_template_created_at = NOW()
    ");

    $contract_template_id = mysqli_insert_id($mysqli);

    // Log action
    logAction("Contract Template", "Create", "$session_name created contract template $name", 0, $contract_template_id);

    // Flash message
    flash_alert("Contract Template <strong>$name</strong> created");

    // Redirect back
    redirect();
}

?>
