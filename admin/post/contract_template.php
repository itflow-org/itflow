<?php

/*
 * ITFlow - GET/POST request handler for Contract Templates
 */

defined('FROM_POST_HANDLER') || die("Direct file access is not allowed");

if (isset($_POST['add_contract_template'])) {

    // Sanitize text inputs
    $name = sanitizeInput($_POST['name']);
    $description = sanitizeInput($_POST['description']);
    $type = sanitizeInput($_POST['type']);
    $renewal_frequency = sanitizeInput($_POST['renewal_frequency']);
    $support_hours = sanitizeInput($_POST['support_hours']);
    $details = mysqli_escape_string($mysqli, $_POST['details']);

    // Numeric fields cast to integer
    $sla_low_resp = intval($_POST['sla_low_response_time']);
    $sla_med_resp = intval($_POST['sla_medium_response_time']);
    $sla_high_resp = intval($_POST['sla_high_response_time']);
    $sla_low_res = intval($_POST['sla_low_resolution_time']);
    $sla_med_res = intval($_POST['sla_medium_resolution_time']);
    $sla_high_res = intval($_POST['sla_high_resolution_time']);
    $rate_standard = intval($_POST['rate_standard']);
    $rate_after_hours = intval($_POST['hourly_rate_after_hours']);
    $net_terms = intval($_POST['net_terms']);

    // Insert into database (numbers not quoted)
    mysqli_query($mysqli, "
        INSERT INTO contract_templates SET
        contract_template_name = '$name',
        contract_template_description = '$description',
        contract_template_details = '$details',
        contract_template_type = '$type',
        contract_template_renewal_frequency = '$renewal_frequency',
        contract_template_sla_low_response_time = $sla_low_resp,
        contract_template_sla_medium_response_time = $sla_med_resp,
        contract_template_sla_high_response_time = $sla_high_resp,
        contract_template_sla_low_resolution_time = $sla_low_res,
        contract_template_sla_medium_resolution_time = $sla_med_res,
        contract_template_sla_high_resolution_time = $sla_high_res,
        contract_template_rate_standard = $rate_standard,
        contract_template_rate_after_hours = $rate_after_hours,
        contract_template_support_hours = '$support_hours',
        contract_template_net_terms = $net_terms
    ");

    $contract_template_id = mysqli_insert_id($mysqli);

    // Log action
    logAction("Contract Template", "Create", "$session_name created contract template $name", 0, $contract_template_id);

    // Flash message
    flash_alert("Contract Template <strong>$name</strong> created");

    // Redirect back
    redirect();
}

if (isset($_POST['edit_contract_template'])) {

    $contract_template_id = intval($_POST['contract_template_id']);
    $name            = sanitizeInput($_POST['name']);
    $description     = sanitizeInput($_POST['description']);
    $type            = sanitizeInput($_POST['type']);
    $renewal_frequency= sanitizeInput($_POST['renewal_frequency']);
    $support_hours   = sanitizeInput($_POST['support_hours']);
    $details         = mysqli_escape_string($mysqli, $_POST['details']);
    $sla_low_resp  = intval($_POST['sla_low_response_time']);
    $sla_med_resp  = intval($_POST['sla_medium_response_time']);
    $sla_high_resp = intval($_POST['sla_high_response_time']);
    $sla_low_res   = intval($_POST['sla_low_resolution_time']);
    $sla_med_res   = intval($_POST['sla_medium_resolution_time']);
    $sla_high_res  = intval($_POST['sla_high_resolution_time']);
    $rate_standard   = intval($_POST['rate_standard']);
    $rate_after_hours = intval($_POST['rate_after_hours']);
    $net_terms     = intval($_POST['net_terms']);

    mysqli_query($mysqli, "
        UPDATE contract_templates SET
            contract_template_name = '$name',
            contract_template_description = '$description',
            contract_template_details = '$details',
            contract_template_type = '$type',
            contract_template_renewal_frequency = '$renewal_frequency',
            contract_template_sla_low_response_time = $sla_low_resp,
            contract_template_sla_medium_response_time = $sla_med_resp,
            contract_template_sla_high_response_time = $sla_high_resp,
            contract_template_sla_low_resolution_time = $sla_low_res,
            contract_template_sla_medium_resolution_time = $sla_med_res,
            contract_template_sla_high_resolution_time = $sla_high_res,
            contract_template_rate_standard = $rate_standard,
            contract_template_rate_after_hours = $rate_after_hours,
            contract_template_support_hours = '$support_hours',
            contract_template_net_terms = $net_terms
        WHERE contract_template_id = $contract_template_id
    ");

    // Log action
    logAction("Contract Template", "Update", "$session_name updated contract template $name", 0, $contract_template_id);

    // Flash + redirect
    flash_alert("Contract Template <strong>$name</strong> updated");
    redirect();
}

if (isset($_GET['archive_contract_template'])) {
    $contract_template_id = intval($_GET['archive_contract_template']);

    $name = getFieldById('contract_templates', $contract_template_id, 'contract_template_name');

    mysqli_query($mysqli, "
        UPDATE contract_templates SET contract_template_archived_at = NOW()
        WHERE contract_template_id = $contract_template_id
        LIMIT 1
    ");

    logAction("Contract Template", "Archive", "$session_name archived contract template $name", 0, $contract_template_id);
    flash_alert("Contract Template <strong>$name</strong> archived", "danger");
    redirect();
}

if (isset($_GET['restore_contract_template'])) {
    $contract_template_id = intval($_GET['restore_contract_template']);

    $name = getFieldById('contract_templates', $contract_template_id, 'contract_template_name');

    mysqli_query($mysqli, "
        UPDATE contract_templates SET contract_template_archived_at = NULL
        WHERE contract_template_id = $contract_template_id
        LIMIT 1
    ");

    logAction("Contract Template", "Restore", "$session_name restored contract template $name", 0, $contract_template_id);
    flash_alert("Contract Template <strong>$name</strong> restored");
    redirect();
}

if (isset($_GET['delete_contract_template'])) {
    $contract_template_id = intval($_GET['delete_contract_template']);

    $name = getFieldById('contract_templates', $contract_template_id, 'contract_template_name');

    mysqli_query($mysqli, "
        DELETE FROM contract_templates
        WHERE contract_template_id = $contract_template_id
        LIMIT 1
    ");

    logAction("Contract Template", "Delete", "$session_name deleted contract template $name", 0, $contract_template_id);
    flash_alert("Contract Template <strong>$name</strong> deleted", "danger");
    redirect();
}

?>
