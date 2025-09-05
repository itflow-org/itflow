<?php

/*
 * ITFlow - GET/POST request handler for tax
 */

defined('FROM_POST_HANDLER') || die("Direct file access is not allowed");

if (isset($_POST['add_tax'])) {

    validateCSRFToken($_POST['csrf_token']);
    $name = sanitizeInput($_POST['name']);
    $percent = floatval($_POST['percent']);

    mysqli_query($mysqli,"INSERT INTO taxes SET tax_name = '$name', tax_percent = $percent");

    $tax_id = mysqli_insert_id($mysqli);

    logAction("Tax", "Create", "$session_name created tax $name - $percent%", 0, $tax_id);

    flash_alert("Tax <strong>$name</strong> ($percent%) created");

    redirect();

}

if (isset($_POST['edit_tax'])) {

    validateCSRFToken($_POST['csrf_token']);
    $tax_id = intval($_POST['tax_id']);
    $name = sanitizeInput($_POST['name']);
    $percent = floatval($_POST['percent']);

    mysqli_query($mysqli,"UPDATE taxes SET tax_name = '$name', tax_percent = $percent WHERE tax_id = $tax_id");

    logAction("Tax", "Edit", "$session_name edited tax $name - $percent%", 0, $tax_id);

    flash_alert("Tax <strong>$name</strong> ($percent%) edited");

    redirect();

}

if (isset($_GET['archive_tax'])) {
    
    validateCSRFToken($_GET['csrf_token']);
    $tax_id = intval($_GET['archive_tax']);

    $tax_name = sanitizeInput(getFieldById('taxes', $tax_id, 'tax_name'));

    mysqli_query($mysqli,"UPDATE taxes SET tax_archived_at = NOW() WHERE tax_id = $tax_id");

    logAction("Tax", "Archive", "$session_name archived tax $tax_name", 0, $tax_id);

    flash_alert("Tax <strong>$tax_name</strong> Archived", 'error');

    redirect();

}

if (isset($_GET['delete_tax'])) {
    
    $tax_id = intval($_GET['delete_tax']);

    $tax_name = sanitizeInput(getFieldById('taxes', $tax_id, 'tax_name'));

    mysqli_query($mysqli,"DELETE FROM taxes WHERE tax_id = $tax_id");

    logAction("Tax", "Delete", "$session_name deleted tax $tax_name");

    flash_alert("Tax <strong>$tax_name</strong> deleted", 'error');

    redirect();

}
