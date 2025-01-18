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

    // Logging
    logAction("Tax", "Create", "$session_name created tax $name - $percent%", 0, $tax_id);

    $_SESSION['alert_message'] = "Tax <strong>$name</strong> ($percent%) created";

    header("Location: " . $_SERVER["HTTP_REFERER"]);

}

if (isset($_POST['edit_tax'])) {

    validateCSRFToken($_POST['csrf_token']);
    $tax_id = intval($_POST['tax_id']);
    $name = sanitizeInput($_POST['name']);
    $percent = floatval($_POST['percent']);

    mysqli_query($mysqli,"UPDATE taxes SET tax_name = '$name', tax_percent = $percent WHERE tax_id = $tax_id");

    // Logging
    logAction("Tax", "Edit", "$session_name edited tax $name - $percent%", 0, $tax_id);

    $_SESSION['alert_message'] = "Tax <strong>$name</strong> ($percent%) edited";

    header("Location: " . $_SERVER["HTTP_REFERER"]);

}

if (isset($_GET['archive_tax'])) {
    validateCSRFToken($_GET['csrf_token']);
    $tax_id = intval($_GET['archive_tax']);

    // Get Tax Name for logging
    $sql = mysqli_query($mysqli,"SELECT tax_name FROM taxes WHERE tax_id = $tax_id");
    $row = mysqli_fetch_array($sql);
    $tax_name = sanitizeInput($row['tax_name']);

    mysqli_query($mysqli,"UPDATE taxes SET tax_archived_at = NOW() WHERE tax_id = $tax_id");

    // Logging
    logAction("Tax", "Archive", "$session_name archived tax $tax_name", 0, $tax_id);

    $_SESSION['alert_type'] = "error";
    $_SESSION['alert_message'] = "Tax <strong>$tax_name</strong> Archived";

    header("Location: " . $_SERVER["HTTP_REFERER"]);

}

if (isset($_GET['delete_tax'])) {
    $tax_id = intval($_GET['delete_tax']);

    // Get Tax Name for logging
    $sql = mysqli_query($mysqli,"SELECT tax_name FROM taxs WHERE tax_id = $tax_id");
    $row = mysqli_fetch_array($sql);
    $tax_name = sanitizeInput($row['tax_name']);

    mysqli_query($mysqli,"DELETE FROM taxes WHERE tax_id = $tax_id");

    // Logging
    logAction("Tax", "Delete", "$session_name deleted tax $tax_name");

    $_SESSION['alert_type'] = "error";
    $_SESSION['alert_message'] = "Tax <strong>$tax_name</strong> deleted";

    header("Location: " . $_SERVER["HTTP_REFERER"]);

}
