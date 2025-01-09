<?php

// Vendor Templates

defined('FROM_POST_HANDLER') || die("Direct file access is not allowed");

// Import shared code from user-side vendor management  as we reuse functions
require_once 'post/user/vendor.php';

if (isset($_POST['add_vendor_template'])) {

    require_once 'post/user/vendor_model.php';

    mysqli_query($mysqli,"INSERT INTO vendors SET vendor_name = '$name', vendor_description = '$description', vendor_contact_name = '$contact_name', vendor_phone = '$phone', vendor_extension = '$extension', vendor_email = '$email', vendor_website = '$website', vendor_hours = '$hours', vendor_sla = '$sla', vendor_code = '$code', vendor_account_number = '$account_number', vendor_notes = '$notes', vendor_template = 1, vendor_client_id = 0");

    $vendor_id = mysqli_insert_id($mysqli);

    // Logging
    logAction("Vendor Template", "Create", "$session_name created vendor template $name", 0, $vendor_id);

    $_SESSION['alert_message'] = "Vendor template <strong>$name</strong> created";

    header("Location: " . $_SERVER["HTTP_REFERER"]);
}

if (isset($_POST['edit_vendor_template'])) {

    require_once 'post/user/vendor_model.php';

    $vendor_id = intval($_POST['vendor_id']);
    $vendor_template_id = intval($_POST['vendor_template_id']);

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
        $sql_global_update_vendor_phone = ", vendor_phone = '$phone', vendor_extension = '$extension'";
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
    mysqli_query($mysqli,"UPDATE vendors SET vendor_name = '$name', vendor_description = '$description', vendor_contact_name = '$contact_name', vendor_phone = '$phone', vendor_extension = '$extension', vendor_email = '$email', vendor_website = '$website', vendor_hours = '$hours', vendor_sla = '$sla', vendor_code = '$code', vendor_account_number = '$account_number', vendor_notes = '$notes' WHERE vendor_id = $vendor_id");

    if ($_POST['update_base_vendors'] == 1) {
        // Update client related vendors if anything is checked
        $sql = "$sql_global_update_vendor_name $sql_global_update_vendor_description $sql_global_update_vendor_account_number $sql_global_update_vendor_contact_name $sql_global_update_vendor_phone $sql_global_update_vendor_hours $sql_global_update_vendor_email $sql_global_update_vendor_website $sql_global_update_vendor_sla $sql_global_update_vendor_code $sql_global_update_vendor_notes";

        // Remove the first comma to prevent MySQL error
        $sql = preg_replace('/,/', '', $sql, 1);

        mysqli_query($mysqli,"UPDATE vendors SET $sql WHERE vendor_template_id = $vendor_id");
    }

    // Logging
    logAction("Vendor Template", "Edit", "$session_name edited vendor template $name", 0, $vendor_template_id);

    $_SESSION['alert_message'] = "Vendor template <strong>$name</strong> edited";

    header("Location: " . $_SERVER["HTTP_REFERER"]);
}
