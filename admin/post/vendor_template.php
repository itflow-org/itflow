<?php

// Vendor Templates

defined('FROM_POST_HANDLER') || die("Direct file access is not allowed");

// Import shared code from user-side vendor management  as we reuse functions
require_once 'post/user/vendor.php';

if (isset($_POST['add_vendor_template'])) {

    $name = sanitizeInput($_POST['name']);
    $description = sanitizeInput($_POST['description']);
    $account_number = sanitizeInput($_POST['account_number']);
    $contact_name = sanitizeInput($_POST['contact_name']);
    $phone_country_code = preg_replace("/[^0-9]/", '', $_POST['phone_country_code']);
    $phone = preg_replace("/[^0-9]/", '', $_POST['phone']);
    $extension = preg_replace("/[^0-9]/", '', $_POST['extension']);
    $email = sanitizeInput($_POST['email']);
    $website = preg_replace("(^https?://)", "", sanitizeInput($_POST['website']));
    $hours = sanitizeInput($_POST['hours']);
    $sla = sanitizeInput($_POST['sla']);
    $code = sanitizeInput($_POST['code']);
    $notes = sanitizeInput($_POST['notes']);

    mysqli_query($mysqli,"INSERT INTO vendor_templates SET vendor_template_name = '$name', vendor_template_description = '$description', vendor_template_contact_name = '$contact_name', vendor_template_phone = '$phone', vendor_template_extension = '$extension', vendor_template_email = '$email', vendor_template_website = '$website', vendor_template_hours = '$hours', vendor_template_sla = '$sla', vendor_template_code = '$code', vendor_template_account_number = '$account_number', vendor_template_notes = '$notes'");

    $vendor_template_id = mysqli_insert_id($mysqli);

    // Logging
    logAction("Vendor Template", "Create", "$session_name created vendor template $name", 0, $vendor_template_id);

    $_SESSION['alert_message'] = "Vendor template <strong>$name</strong> created";

    header("Location: " . $_SERVER["HTTP_REFERER"]);
}

if (isset($_POST['edit_vendor_template'])) {

    $vendor_template_id = intval($_POST['vendor_template_id']);
    $name = sanitizeInput($_POST['name']);
    $description = sanitizeInput($_POST['description']);
    $account_number = sanitizeInput($_POST['account_number']);
    $contact_name = sanitizeInput($_POST['contact_name']);
    $phone_country_code = preg_replace("/[^0-9]/", '', $_POST['phone_country_code']);
    $phone = preg_replace("/[^0-9]/", '', $_POST['phone']);
    $extension = preg_replace("/[^0-9]/", '', $_POST['extension']);
    $email = sanitizeInput($_POST['email']);
    $website = preg_replace("(^https?://)", "", sanitizeInput($_POST['website']));
    $hours = sanitizeInput($_POST['hours']);
    $sla = sanitizeInput($_POST['sla']);
    $code = sanitizeInput($_POST['code']);
    $notes = sanitizeInput($_POST['notes']);

    if ($_POST['global_update_vendor_name'] == 1) {
        $sql_global_update_vendor_name = ", vendor_name = '$name'";
    } else {
        $sql_global_update_vendor_name = "";
    }

    if ($_POST['global_update_vendor_description'] == 1) {
        $sql_global_update_vendor_description = ", vendor_description = '$description'";
    } else {
        $sql_global_update_vendor_description = "";
    }

    if ($_POST['global_update_vendor_account_number'] == 1) {
        $sql_global_update_vendor_account_number = ", vendor_account_number = '$account_number'";
    } else {
        $sql_global_update_vendor_account_number = "";
    }

    if ($_POST['global_update_vendor_contact_name'] == 1) {
        $sql_global_update_vendor_contact_name = ", vendor_contact_name = '$contact_name'";
    } else {
        $sql_global_update_vendor_contact_name = "";
    }

    if ($_POST['global_update_vendor_phone'] == 1) {
        $sql_global_update_vendor_phone = ", vendor_phone_country_code = '$phone_country_code', vendor_phone = '$phone', vendor_extension = '$extension'";
    } else {
        $sql_global_update_vendor_phone = "";
    }

    if ($_POST['global_update_vendor_hours'] == 1) {
        $sql_global_update_vendor_hours = ", vendor_hours = '$hours'";
    } else {
        $sql_global_update_vendor_hours = "";
    }

    if ($_POST['global_update_vendor_email'] == 1) {
        $sql_global_update_vendor_email = ", vendor_email = '$email'";
    } else {
        $sql_global_update_vendor_email = "";
    }

    if ($_POST['global_update_vendor_website'] == 1) {
        $sql_global_update_vendor_website = ", vendor_website = '$website'";
    } else {
        $sql_global_update_vendor_website = "";
    }

    if ($_POST['global_update_vendor_sla'] == 1) {
        $sql_global_update_vendor_sla = ", vendor_sla = '$sla'";
    } else {
        $sql_global_update_vendor_sla = "";
    }

    if ($_POST['global_update_vendor_code'] == 1) {
        $sql_global_update_vendor_code = ", vendor_code = '$code'";
    } else {
        $sql_global_update_vendor_code = "";
    }

    if ($_POST['global_update_vendor_notes'] == 1) {
        $sql_global_update_vendor_notes = ", vendor_notes = '$notes'";
    } else {
        $sql_global_update_vendor_notes = "";
    }

    // Update just the template
    mysqli_query($mysqli,"UPDATE vendor_templates SET vendor_template_name = '$name', vendor_template_description = '$description', vendor_template_contact_name = '$contact_name', vendor_template_phone_country_code = '$phone_country_code', vendor_template_phone = '$phone', vendor_template_extension = '$extension', vendor_template_email = '$email', vendor_template_website = '$website', vendor_template_hours = '$hours', vendor_template_sla = '$sla', vendor_template_code = '$code', vendor_template_account_number = '$account_number', vendor_template_notes = '$notes' WHERE vendor_template_id = $vendor_template_id");

    if ($_POST['update_base_vendors'] == 1) {
        // Update client related vendors if anything is checked
        $sql = "$sql_global_update_vendor_name $sql_global_update_vendor_description $sql_global_update_vendor_account_number $sql_global_update_vendor_contact_name $sql_global_update_vendor_phone $sql_global_update_vendor_hours $sql_global_update_vendor_email $sql_global_update_vendor_website $sql_global_update_vendor_sla $sql_global_update_vendor_code $sql_global_update_vendor_notes";

        // Remove the first comma to prevent MySQL error
        $sql = preg_replace('/,/', '', $sql, 1);

        mysqli_query($mysqli,"UPDATE vendors SET $sql WHERE vendor_template_id = $vendor_template_id");
    }

    // Logging
    logAction("Vendor Template", "Edit", "$session_name edited vendor template $name", 0, $vendor_template_id);

    $_SESSION['alert_message'] = "Vendor template <strong>$name</strong> edited";

    header("Location: " . $_SERVER["HTTP_REFERER"]);
}

if (isset($_GET['delete_vendor_template'])) {
    $vendor_template_id = intval($_GET['delete_vendor_template']);

    //Get Vendor Template Name
    $sql = mysqli_query($mysqli,"SELECT vendor_template_name FROM vendor_templates WHERE vendor_template_id = $vendor_template_id");
    $row = mysqli_fetch_array($sql);
    $vendor_template_name = sanitizeInput($row['vendor_template_name']);

    // If its a template reset all vendors based off this template to no template base
    mysqli_query($mysqli,"UPDATE vendors SET vendor_template_id = 0 WHERE vendor_template_id = $vendor_template_id");

    mysqli_query($mysqli,"DELETE FROM vendor_templates WHERE vendor_template_id = $vendor_template_id");

    // Logging
    logAction("Vendor Template", "Delete", "$session_name deleted vendor template $vendor_template_name");

    $_SESSION['alert_type'] = "error";
    $_SESSION['alert_message'] = "Vendor Template <strong>$vendor_template_name</strong> deleted";

    header("Location: " . $_SERVER["HTTP_REFERER"]);
}
